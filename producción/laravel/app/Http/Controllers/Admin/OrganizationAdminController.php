<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

abstract class OrganizationAdminController extends Controller
{
    protected function company(): Company
    {
        $company = Company::query()->where('singleton_key', 'primary')->first();

        if (! $company) {
            throw new HttpResponseException(
                redirect()->route('admin.company.edit')->withErrors([
                    'organization' => __('Configure los datos de la empresa antes de crear catálogos organizacionales.'),
                ]),
            );
        }

        return $company;
    }

    protected function audit(
        Request $request,
        Company $company,
        string $action,
        string $entityType,
        Model $entity,
        ?array $before,
        array $after,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => $company->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entity->getKey(),
            'changes' => ['before' => $before, 'after' => $after],
            'ip_address' => $request->ip(),
        ]);
    }
}
