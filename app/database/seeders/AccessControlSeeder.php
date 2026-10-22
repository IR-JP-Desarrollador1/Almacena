<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $permissions = [
                ['name' => 'Consultar usuarios', 'code' => 'users.view', 'module' => 'usuarios'],
                ['name' => 'Administrar usuarios', 'code' => 'users.manage', 'module' => 'usuarios'],
                ['name' => 'Administrar perfiles y permisos', 'code' => 'roles.manage', 'module' => 'seguridad'],
                ['name' => 'Administrar empresa', 'code' => 'companies.manage', 'module' => 'empresa'],
                ['name' => 'Consultar auditoría', 'code' => 'audit.view', 'module' => 'seguridad'],
                ['name' => 'Consultar estructura organizacional', 'code' => 'organization.view', 'module' => 'organización'],
                ['name' => 'Administrar estructura organizacional', 'code' => 'organization.manage', 'module' => 'organización'],
                ['name' => 'Consultar datos maestros', 'code' => 'catalogs.view', 'module' => 'catálogos'],
                ['name' => 'Administrar datos maestros', 'code' => 'catalogs.manage', 'module' => 'catálogos'],
                ['name' => 'Consultar áreas solicitantes', 'code' => 'departments.view', 'module' => 'organización'],
                ['name' => 'Administrar áreas solicitantes', 'code' => 'departments.manage', 'module' => 'organización'],
                ['name' => 'Consultar requisiciones', 'code' => 'purchases.requisitions.view', 'module' => 'compras'],
                ['name' => 'Crear requisiciones', 'code' => 'purchases.requisitions.create', 'module' => 'compras'],
                ['name' => 'Aprobar o rechazar requisiciones asignadas', 'code' => 'purchases.requisitions.decide', 'module' => 'compras'],
                ['name' => 'Consultar órdenes de compra', 'code' => 'purchases.orders.view', 'module' => 'compras'],
                ['name' => 'Crear órdenes de compra', 'code' => 'purchases.orders.create', 'module' => 'compras'],
                ['name' => 'Aprobar órdenes de compra', 'code' => 'purchases.orders.approve', 'module' => 'compras'],
                ['name' => 'Consultar entradas de inventario', 'code' => 'inventory.receipts.view', 'module' => 'inventario'],
                ['name' => 'Registrar entradas de inventario', 'code' => 'inventory.receipts.create', 'module' => 'inventario'],
                ['name' => 'Consultar salidas de inventario', 'code' => 'inventory.issues.view', 'module' => 'inventario'],
                ['name' => 'Registrar salidas de inventario', 'code' => 'inventory.issues.create', 'module' => 'inventario'],
                ['name' => 'Consultar kardex de inventario', 'code' => 'inventory.movements.view', 'module' => 'inventario'],
            ];

            foreach ($permissions as $attributes) {
                Permission::query()->updateOrCreate(
                    ['code' => $attributes['code']],
                    $attributes,
                );
            }

            $roles = [
                ['name' => 'Administración', 'slug' => 'administracion', 'description' => 'Administración general del MVP.', 'is_system' => true],
                ['name' => 'Compras', 'slug' => 'compras', 'description' => 'Perfil inicial para procesos de compras.', 'is_system' => true],
                ['name' => 'Almacén', 'slug' => 'almacen', 'description' => 'Perfil inicial para operaciones de almacén.', 'is_system' => true],
                ['name' => 'Logística', 'slug' => 'logistica', 'description' => 'Perfil inicial para procesos logísticos.', 'is_system' => true],
                ['name' => 'Auditoría', 'slug' => 'auditoria', 'description' => 'Perfil inicial de consulta y auditoría.', 'is_system' => true],
                ['name' => 'Solicitante', 'slug' => 'solicitante', 'description' => 'Crea requisiciones y consulta su estado.', 'is_system' => true],
            ];

            foreach ($roles as $attributes) {
                Role::query()->firstOrCreate(
                    ['slug' => $attributes['slug']],
                    [...$attributes, 'is_active' => true],
                );
            }

            $administrator = Role::query()->where('slug', 'administracion')->firstOrFail();
            $administrator->permissions()->sync(Permission::query()->pluck('id'));

            $purchasing = Role::query()->where('slug', 'compras')->firstOrFail();
            $purchasing->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', [
                    'purchases.requisitions.view',
                    'purchases.requisitions.decide',
                    'purchases.orders.view',
                    'purchases.orders.create',
                    'purchases.orders.approve',
                ])->pluck('id'),
            );

            $warehouse = Role::query()->where('slug', 'almacen')->firstOrFail();
            $warehouse->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', [
                    'inventory.receipts.view',
                    'inventory.receipts.create',
                    'inventory.issues.view',
                    'inventory.issues.create',
                    'inventory.movements.view',
                ])->pluck('id'),
            );

            $auditor = Role::query()->where('slug', 'auditoria')->firstOrFail();
            $auditor->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', [
                    'inventory.movements.view',
                ])->pluck('id'),
            );

            $requester = Role::query()->where('slug', 'solicitante')->firstOrFail();
            $requester->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', [
                    'purchases.requisitions.view',
                    'purchases.requisitions.create',
                ])->pluck('id'),
            );
        });
    }
}
