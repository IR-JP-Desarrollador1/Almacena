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
                ['name' => 'Consultar requisiciones en Compras', 'code' => 'purchases.requisitions.view', 'module' => 'compras', 'description' => 'Consultar requisiciones enviadas a aprobación o aprobadas.'],
                ['name' => 'Aprobar requisiciones asignadas', 'code' => 'purchases.requisitions.approve', 'module' => 'compras', 'description' => 'Aprobar requisiciones pendientes asignadas al jefe del área.'],
                ['name' => 'Aprobar cualquier requisición (Administración)', 'code' => 'purchases.requisitions.approve_any', 'module' => 'compras', 'description' => 'Permite al perfil Administración aprobar requisiciones pendientes aunque no sea el jefe asignado.'],
                ['name' => 'Rechazar requisiciones asignadas', 'code' => 'purchases.requisitions.reject', 'module' => 'compras', 'description' => 'Rechazar requisiciones pendientes asignadas al jefe del área.'],
                ['name' => 'Consultar requisiciones de Inventario', 'code' => 'inventory.requisitions.view', 'module' => 'inventario', 'description' => 'Consultar requisiciones de la bodega autorizada; los borradores son visibles solo para su solicitante.'],
                ['name' => 'Gestionar requisiciones de Inventario', 'code' => 'inventory.requisitions.manage', 'module' => 'inventario', 'description' => 'Crear, editar, activar y cancelar las requisiciones propias.'],
                ['name' => 'Enviar requisiciones a aprobación', 'code' => 'inventory.requisitions.submit', 'module' => 'inventario', 'description' => 'Enviar el saldo pendiente de una requisición activa al jefe del área.'],
                ['name' => 'Registrar salidas parciales de requisiciones', 'code' => 'inventory.requisitions.dispatch', 'module' => 'inventario', 'description' => 'Registrar entregas parciales de productos para requisiciones activas o aprobadas.'],
                ['name' => 'Consultar comparación de cotizaciones', 'code' => 'purchases.quotes.view', 'module' => 'compras', 'description' => 'Consultar ofertas y comparaciones de las requisiciones aprobadas.'],
                ['name' => 'Registrar cotizaciones de proveedores', 'code' => 'purchases.quotes.manage', 'module' => 'compras', 'description' => 'Crear, completar o actualizar las ofertas recibidas para una requisición.'],
                ['name' => 'Adjudicar ofertas de cotización', 'code' => 'purchases.quotes.award', 'module' => 'compras', 'description' => 'Seleccionar la oferta ganadora por producto y guardar la comparación.'],
                ['name' => 'Generar órdenes desde cotizaciones', 'code' => 'purchases.quotes.generate_orders', 'module' => 'compras', 'description' => 'Generar órdenes de compra borrador a partir de ofertas adjudicadas.'],
                ['name' => 'Crear órdenes de compra directas', 'code' => 'purchases.orders.create_direct', 'module' => 'compras', 'description' => 'Crear órdenes sin cotización desde requisiciones aprobadas o sin requisición.'],
                ['name' => 'Consultar órdenes de compra', 'code' => 'purchases.orders.view', 'module' => 'compras'],
                ['name' => 'Enviar órdenes de compra a aprobación', 'code' => 'purchases.orders.submit', 'module' => 'compras', 'description' => 'Enviar órdenes propias para revisión independiente.'],
                ['name' => 'Aprobar órdenes de compra', 'code' => 'purchases.orders.approve', 'module' => 'compras'],
                ['name' => 'Aprobar cualquier orden de compra (Administración)', 'code' => 'purchases.orders.approve_any', 'module' => 'compras', 'description' => 'Permite al perfil Administración aprobar órdenes pendientes incluso si fueron creadas por el mismo usuario.'],
                ['name' => 'Rechazar órdenes de compra', 'code' => 'purchases.orders.reject', 'module' => 'compras', 'description' => 'Rechazar órdenes pendientes con un motivo y devolverlas a su creador para corrección.'],
                ['name' => 'Consultar entradas de inventario', 'code' => 'inventory.receipts.view', 'module' => 'inventario'],
                ['name' => 'Registrar entradas de inventario', 'code' => 'inventory.receipts.create', 'module' => 'inventario'],
                ['name' => 'Registrar entradas directas de inventario', 'code' => 'inventory.receipts.direct.create', 'module' => 'inventario', 'description' => 'Registrar entradas autorizadas sin orden de compra, con motivo y costo unitario en moneda base.'],
                ['name' => 'Consultar salidas de inventario', 'code' => 'inventory.issues.view', 'module' => 'inventario'],
                ['name' => 'Registrar salidas de inventario', 'code' => 'inventory.issues.create', 'module' => 'inventario'],
                ['name' => 'Registrar salidas directas de inventario', 'code' => 'inventory.issues.direct.create', 'module' => 'inventario', 'description' => 'Registrar salidas directas autorizadas con motivo y referencia documental opcional.'],
                ['name' => 'Consultar kardex de inventario', 'code' => 'inventory.movements.view', 'module' => 'inventario'],
            ];

            foreach ($permissions as $attributes) {
                Permission::query()->updateOrCreate(
                    ['code' => $attributes['code']],
                    $attributes,
                );
            }

            $legacyCreatePermission = Permission::query()->where('code', 'purchases.requisitions.create')->first();
            if ($legacyCreatePermission) {
                $inventoryManagePermission = Permission::query()
                    ->where('code', 'inventory.requisitions.manage')
                    ->firstOrFail();
                $inventorySubmitPermission = Permission::query()
                    ->where('code', 'inventory.requisitions.submit')
                    ->firstOrFail();

                foreach ($legacyCreatePermission->roles as $role) {
                    $role->permissions()->syncWithoutDetaching([
                        $inventoryManagePermission->id,
                        $inventorySubmitPermission->id,
                    ]);
                }

                $legacyCreatePermission->roles()->detach();
                $legacyCreatePermission->delete();
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
                    'purchases.requisitions.approve',
                    'purchases.requisitions.reject',
                    'purchases.quotes.view',
                    'purchases.quotes.manage',
                    'purchases.quotes.award',
                    'purchases.quotes.generate_orders',
                    'purchases.orders.create_direct',
                    'purchases.orders.view',
                    'purchases.orders.submit',
                    'purchases.orders.approve',
                    'purchases.orders.reject',
                ])->pluck('id'),
            );

            $warehouse = Role::query()->where('slug', 'almacen')->firstOrFail();
            $warehouse->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', [
                    'inventory.requisitions.view',
                    'inventory.requisitions.dispatch',
                    'inventory.receipts.view',
                    'inventory.receipts.create',
                    'inventory.receipts.direct.create',
                    'inventory.issues.view',
                    'inventory.issues.create',
                    'inventory.issues.direct.create',
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
                    'inventory.requisitions.manage',
                    'inventory.requisitions.submit',
                ])->pluck('id'),
            );
            $requester->permissions()->detach(
                Permission::query()->where('code', 'purchases.requisitions.view')->pluck('id'),
            );
        });
    }
}
