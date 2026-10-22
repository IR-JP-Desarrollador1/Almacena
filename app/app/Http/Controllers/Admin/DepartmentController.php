<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'manager_user_id', 'is_active'];

    public function index(): View
    {
        $company = $this->company();

        return view('admin.organization.departments.index', [
            'departments' => $company->departments()->with('manager')->withCount('requisitions')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $company = $this->company();

        return view('admin.organization.departments.form', [
            'department' => new Department,
            'managers' => $this->eligibleManagers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->where('company_id', $company->id)],
            'name' => ['required', 'string', 'max:255'],
            'manager_user_id' => ['required', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE)],
        ]);
        $this->ensureEligibleManager((int) $validated['manager_user_id']);
        $validated['is_active'] = true;

        $department = DB::transaction(function () use ($request, $company, $validated) {
            $department = $company->departments()->create($validated);
            $this->audit($request, $company, 'created', 'department', $department, null, $department->only(self::AUDIT_FIELDS));

            return $department;
        });

        return redirect()->route('admin.organization.departments.edit', $department)->with('status', __('Área solicitante creada.'));
    }

    public function edit(Department $department): View
    {
        $company = $this->company();
        $department = $company->departments()->findOrFail($department->id);

        return view('admin.organization.departments.form', [
            'department' => $department,
            'managers' => $this->eligibleManagers($department->manager_user_id),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $company = $this->company();
        $department = $company->departments()->findOrFail($department->id);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->where('company_id', $company->id)->ignore($department->id)],
            'name' => ['required', 'string', 'max:255'],
            'manager_user_id' => ['required', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE)],
            'is_active' => ['required', 'boolean'],
        ]);
        $this->ensureEligibleManager((int) $validated['manager_user_id']);

        if (! $validated['is_active'] && $department->requisitions()->where('status', Requisition::STATUS_PENDING_APPROVAL)->exists()) {
            return back()->withErrors(['is_active' => __('No se puede desactivar un área con requisiciones pendientes de aprobación.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $department, $validated) {
            $before = $department->only(self::AUDIT_FIELDS);
            $department->update($validated);
            $this->audit($request, $company, 'updated', 'department', $department, $before, $department->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.organization.departments.index')->with('status', __('Área solicitante actualizada.'));
    }

    private function eligibleManagers(?int $currentManagerId = null)
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function ($query) use ($currentManagerId) {
                $query->whereHas('roles.permissions', fn ($query) => $query->where('code', 'purchases.requisitions.decide'));

                if ($currentManagerId) {
                    $query->orWhere('id', $currentManagerId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function ensureEligibleManager(int $userId): void
    {
        $manager = User::query()->whereKey($userId)->where('status', User::STATUS_ACTIVE)->firstOrFail();
        abort_unless($manager->hasPermission('purchases.requisitions.decide'), 422, __('El jefe asignado debe tener permiso para aprobar requisiciones.'));
    }
}
