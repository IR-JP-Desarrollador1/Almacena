<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'department'])
            ->when($request->string('search')->trim()->isNotEmpty(), function (Builder $query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $user = new User;

        return view('admin.users.create', [
            'user' => $user,
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('company_id', Company::query()->value('id'))->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => $this->warehouseOptions($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', Company::query()->value('id'))->where('is_active', true)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'warehouse_assignments_submitted' => ['sometimes', 'accepted'],
            'warehouses' => ['sometimes', 'array'],
            'warehouses.*' => ['integer', 'distinct', 'exists:warehouses,id'],
        ]);

        $warehouseIds = $this->validatedWarehouseIds($request, $validated, new User);
        $user = DB::transaction(function () use ($validated, $request, $warehouseIds) {
            $user = User::create([
                'name' => $validated['name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'password' => Str::random(64),
                'status' => User::STATUS_PENDING,
                'must_change_password' => true,
            ]);

            $user->roles()->sync($validated['roles']);
            $user->warehouses()->sync($warehouseIds);

            $this->audit($request, 'created', $user, [
                'roles' => $validated['roles'],
                'warehouses' => $warehouseIds,
            ]);

            return $user;
        });

        $status = Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.index')->with(
            $status === Password::RESET_LINK_SENT ? 'status' : 'warning',
            $status === Password::RESET_LINK_SENT
                ? __('Usuario creado. Se envió la invitación para activar la cuenta.')
                : __('Usuario creado como pendiente, pero no se pudo enviar la invitación. Revisa el correo y reintenta.'),
        );
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load(['roles', 'warehouses']),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()
                ->where('company_id', Company::query()->value('id'))
                ->where(fn ($query) => $query->where('is_active', true)->when($user->department_id, fn ($query) => $query->orWhere('id', $user->department_id)))
                ->orderBy('name')
                ->get(),
            'warehouses' => $this->warehouseOptions($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', Company::query()->value('id'))->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $user->department_id),
            )],
            'status' => ['required', Rule::in(array_filter([
                User::STATUS_ACTIVE,
                User::STATUS_BLOCKED,
                User::STATUS_INACTIVE,
                $user->status === User::STATUS_PENDING ? User::STATUS_PENDING : null,
            ]))],
            'password' => ['nullable', 'string', PasswordRule::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'warehouse_assignments_submitted' => ['sometimes', 'accepted'],
            'warehouses' => ['sometimes', 'array'],
            'warehouses.*' => ['integer', 'distinct', 'exists:warehouses,id'],
        ]);

        $warehouseIds = $this->validatedWarehouseIds($request, $validated, $user);
        $currentWarehouseIds = $user->warehouses()->pluck('warehouses.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $warehouseAssignmentsChanged = $warehouseIds !== $currentWarehouseIds;
        $roleIds = collect($validated['roles'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $currentRoleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $roleAssignmentsChanged = $roleIds !== $currentRoleIds;
        $identityChanged = $validated['email'] !== $user->email;
        $passwordChanged = filled($validated['password'] ?? null);
        $accessWillBeRevoked = $validated['status'] !== User::STATUS_ACTIVE
            || $roleAssignmentsChanged
            || $warehouseAssignmentsChanged
            || $identityChanged
            || $passwordChanged;

        if ($user->is(auth()->user()) && $validated['status'] !== User::STATUS_ACTIVE) {
            return back()->withErrors(['status' => __('No puedes desactivar tu propia cuenta.')])->withInput();
        }

        if ($user->status === User::STATUS_PENDING && $validated['status'] === User::STATUS_ACTIVE) {
            return back()->withErrors(['status' => __('La cuenta se activa al completar la invitación por correo.')])->withInput();
        }

        if ($user->assignedRequisitions()->where('status', Requisition::STATUS_PENDING_APPROVAL)->exists()) {
            $retainsApprovalPermission = Role::query()
                ->whereIn('id', $roleIds)
                ->where('is_active', true)
                ->whereHas('permissions', fn ($query) => $query->whereIn('code', [
                    'purchases.requisitions.approve',
                    'purchases.requisitions.reject',
                ]))
                ->exists();

            if ($validated['status'] !== User::STATUS_ACTIVE || ! $retainsApprovalPermission) {
                return back()->withErrors(['roles' => __('El usuario tiene requisiciones pendientes asignadas; resuélvelas antes de quitar su permiso o desactivar su cuenta.')])->withInput();
            }
        }

        if ($this->isLastAdministratorBeingRemoved($user, $validated['status'], $roleIds)) {
            return back()->withErrors(['roles' => __('Debe permanecer al menos una cuenta activa con el perfil de administración.')])->withInput();
        }

        DB::transaction(function () use ($request, $user, $validated, $roleIds, $warehouseIds, $accessWillBeRevoked, $roleAssignmentsChanged, $warehouseAssignmentsChanged, $passwordChanged) {
            $before = [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'department_id' => $user->department_id,
                'status' => $user->status,
                'roles' => $user->roles()->pluck('roles.slug')->all(),
                'warehouses' => $user->warehouses()->pluck('warehouses.id')->all(),
            ];

            $user->fill([
                'name' => $validated['name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'status' => $validated['status'],
            ]);

            if ($passwordChanged) {
                $user->password = Hash::make($validated['password']);
            }

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($accessWillBeRevoked) {
                $user->session_version++;
                $user->setRememberToken(Str::random(60));
            }

            $user->save();
            $user->roles()->sync($roleIds);
            if ($request->boolean('warehouse_assignments_submitted') || array_key_exists('warehouses', $validated)) {
                $user->warehouses()->sync($warehouseIds);
            }

            if ($accessWillBeRevoked) {
                $this->revokeDatabaseSessions($user);
            }

            $this->audit($request, 'updated', $user, [
                'before' => $before,
                'after' => [
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'department_id' => $user->department_id,
                    'status' => $user->status,
                    'roles' => $user->roles()->pluck('roles.slug')->all(),
                    'warehouses' => $user->warehouses()->pluck('warehouses.id')->all(),
                ],
                'roles_changed' => $roleAssignmentsChanged,
                'warehouses_changed' => $warehouseAssignmentsChanged,
                'password_changed' => $passwordChanged,
            ]);
        });

        return redirect()->route('admin.users.index')->with('status', __('Usuario actualizado.'));
    }

    public function sendInvitation(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->status === User::STATUS_PENDING, 422);

        $status = Password::sendResetLink(['email' => $user->email]);
        $this->audit(
            $request,
            $status === Password::RESET_LINK_SENT ? 'invitation_sent' : 'invitation_send_failed',
            $user,
        );

        return back()->with(
            $status === Password::RESET_LINK_SENT ? 'status' : 'warning',
            $status === Password::RESET_LINK_SENT
                ? __('Se envió una nueva invitación.')
                : __('No se pudo enviar la invitación. Verifica la configuración de correo.'),
        );
    }

    private function isLastAdministratorBeingRemoved(User $user, string $status, array $roleIds): bool
    {
        $administratorRoleId = Role::query()->where('slug', 'administracion')->value('id');

        if (! $administratorRoleId) {
            return false;
        }

        $wasAdministrator = $user->roles()->where('roles.id', $administratorRoleId)->exists();
        $willRemainAdministrator = in_array((int) $administratorRoleId, $roleIds, true);

        if (! $wasAdministrator || ($status === User::STATUS_ACTIVE && $willRemainAdministrator)) {
            return false;
        }

        return ! User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $administratorRoleId))
            ->exists();
    }

    private function revokeDatabaseSessions(User $user): void
    {
        if (config('session.driver') === 'database' && DB::getSchemaBuilder()->hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function warehouseOptions(User $user)
    {
        $companyId = Company::query()->where('singleton_key', 'primary')->value('id');
        $assignedIds = $user->exists ? $user->warehouses()->pluck('warehouses.id')->all() : [];

        return Warehouse::query()
            ->with('branch')
            ->whereHas('branch', fn ($query) => $query->where('company_id', $companyId))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('is_active', true)->whereHas('branch', fn ($branch) => $branch->where('is_active', true)))
                ->when($assignedIds, fn ($query) => $query->orWhereIn('id', $assignedIds)))
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function validatedWarehouseIds(Request $request, array $validated, User $user): array
    {
        if (! $request->boolean('warehouse_assignments_submitted') && ! array_key_exists('warehouses', $validated)) {
            return $user->exists
                ? $user->warehouses()->pluck('warehouses.id')->map(fn ($id) => (int) $id)->all()
                : [];
        }

        $warehouseIds = collect($validated['warehouses'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $companyId = Company::query()->where('singleton_key', 'primary')->value('id');
        $existingIds = $user->exists ? $user->warehouses()->pluck('warehouses.id')->all() : [];
        $validCount = Warehouse::query()
            ->whereIn('id', $warehouseIds)
            ->whereHas('branch', fn ($query) => $query->where('company_id', $companyId))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('is_active', true)->whereHas('branch', fn ($branch) => $branch->where('is_active', true)))
                ->when($existingIds, fn ($query) => $query->orWhereIn('id', $existingIds)))
            ->count();

        if ($validCount !== count($warehouseIds)) {
            throw ValidationException::withMessages([
                'warehouses' => __('Selecciona solo bodegas activas de la empresa actual. Las bodegas inactivas ya asignadas solo pueden conservarse o quitarse.'),
            ]);
        }

        return $warehouseIds;
    }

    private function audit(Request $request, string $action, User $user, ?array $changes = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
