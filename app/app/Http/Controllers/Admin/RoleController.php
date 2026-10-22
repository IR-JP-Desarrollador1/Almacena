<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount('users')->with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role,
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $slug = Str::slug($validated['name']);
        $slug = $slug !== '' ? $slug : 'perfil';
        $baseSlug = $slug;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $role = DB::transaction(function () use ($request, $validated, $slug) {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
            ]);
            $role->permissions()->sync($validated['permissions'] ?? []);
            $this->audit($request, 'created', $role, ['permissions' => $validated['permissions'] ?? []]);

            return $role;
        });

        return redirect()->route('admin.roles.edit', $role)->with('status', __('Perfil creado.'));
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        if ($role->is_system && ! $validated['is_active']) {
            return back()->withErrors(['is_active' => __('Los perfiles iniciales del sistema no se pueden desactivar.')])->withInput();
        }

        $permissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($role->slug === 'administracion') {
            $requiredPermissions = Permission::query()
                ->whereIn('code', [
                    'users.view',
                    'users.manage',
                    'roles.manage',
                    'companies.manage',
                    'audit.view',
                    'organization.view',
                    'organization.manage',
                    'catalogs.view',
                    'catalogs.manage',
                    'departments.view',
                    'departments.manage',
                    'purchases.requisitions.view',
                    'purchases.requisitions.approve',
                    'purchases.requisitions.approve_any',
                    'purchases.requisitions.reject',
                    'purchases.quotes.view',
                    'purchases.quotes.manage',
                    'purchases.quotes.award',
                    'purchases.quotes.generate_orders',
                    'inventory.requisitions.view',
                    'inventory.requisitions.manage',
                    'inventory.requisitions.submit',
                    'inventory.requisitions.dispatch',
                ])
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            if ($requiredPermissions->diff($permissionIds)->isNotEmpty()) {
                return back()->withErrors(['permissions' => __('El perfil de administración debe conservar todos los permisos administrativos principales.')])->withInput();
            }
        }

        DB::transaction(function () use ($request, $role, $validated, $permissionIds) {
            $before = [
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions()->pluck('code')->all(),
            ];

            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ]);
            $role->permissions()->sync($permissionIds);

            $this->audit($request, 'updated', $role, [
                'before' => $before,
                'after' => [
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'permissions' => $role->permissions()->pluck('code')->all(),
                ],
            ]);
        });

        return redirect()->route('admin.roles.index')->with('status', __('Perfil actualizado.'));
    }

    private function audit(Request $request, string $action, Role $role, ?array $changes = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'role',
            'entity_id' => $role->id,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
