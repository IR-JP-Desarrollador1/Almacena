<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Almacena') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @php
            $pageMeta = match (true) {
                request()->routeIs('dashboard') => [
                    'section' => 'INICIO',
                    'breadcrumb' => 'Panel principal',
                    'description' => 'Administra desde un solo lugar los catálogos, la organización y las solicitudes de compra.',
                ],
                request()->routeIs('admin.company.*') => [
                    'section' => 'EMPRESA',
                    'breadcrumb' => 'Datos de la empresa',
                    'description' => 'Consulta y actualiza la información general de la empresa.',
                ],
                request()->routeIs('admin.regional-settings.*') => [
                    'section' => __('ADMINISTRACIÓN'),
                    'breadcrumb' => __('Configuración regional'),
                    'description' => __('Define el idioma y los formatos numéricos y de fecha para toda la empresa.'),
                ],
                request()->routeIs('admin.organization.branches.*') => [
                    'section' => 'ORGANIZACIÓN',
                    'breadcrumb' => 'Sedes',
                    'description' => 'Administra las sedes registradas para la empresa.',
                ],
                request()->routeIs('admin.organization.warehouses.*') => [
                    'section' => 'ORGANIZACIÓN',
                    'breadcrumb' => 'Bodegas',
                    'description' => 'Organiza las bodegas asociadas a cada sede.',
                ],
                request()->routeIs('admin.organization.locations.*') => [
                    'section' => 'ORGANIZACIÓN',
                    'breadcrumb' => 'Ubicaciones',
                    'description' => 'Gestiona las ubicaciones internas de las bodegas.',
                ],
                request()->routeIs('admin.organization.cost-centers.*') => [
                    'section' => 'ORGANIZACIÓN',
                    'breadcrumb' => 'Centros de costo',
                    'description' => 'Administra los centros de costo de la empresa.',
                ],
                request()->routeIs('admin.organization.departments.*') => [
                    'section' => 'ORGANIZACIÓN',
                    'breadcrumb' => 'Áreas solicitantes',
                    'description' => 'Configura las áreas y sus responsables de aprobación.',
                ],
                request()->routeIs('admin.catalogs.products.import*') => [
                    'section' => 'DATOS MAESTROS',
                    'breadcrumb' => 'Importación de datos maestros',
                    'description' => 'Carga productos desde un archivo CSV o Excel sin reemplazar los registros existentes.',
                ],
                request()->routeIs('admin.catalogs.products.*') => [
                    'section' => 'DATOS MAESTROS',
                    'breadcrumb' => 'Productos',
                    'description' => 'Administra el catálogo de productos y sus unidades.',
                ],
                request()->routeIs('admin.catalogs.units.*') => [
                    'section' => 'DATOS MAESTROS',
                    'breadcrumb' => 'Unidades',
                    'description' => 'Gestiona las unidades de medida de los productos.',
                ],
                request()->routeIs('admin.catalogs.suppliers.*') => [
                    'section' => 'DATOS MAESTROS',
                    'breadcrumb' => 'Proveedores',
                    'description' => 'Consulta y administra el directorio de proveedores.',
                ],
                request()->routeIs('admin.catalogs.supplier-evaluations.*') => [
                    'section' => 'DATOS MAESTROS',
                    'breadcrumb' => 'Calificación de proveedores',
                    'description' => 'Registra evaluaciones de distancia, tiempo de entrega y cumplimiento; cada evaluación conserva su historial.',
                ],
                request()->routeIs('admin.users.*') => [
                    'section' => 'ADMINISTRACIÓN',
                    'breadcrumb' => 'Usuarios',
                    'description' => 'Administra las cuentas con acceso a Almacena.',
                ],
                request()->routeIs('admin.roles.*') => [
                    'section' => 'ADMINISTRACIÓN',
                    'breadcrumb' => 'Perfiles y permisos',
                    'description' => 'Configura los perfiles y los permisos de la aplicación.',
                ],
                request()->routeIs('admin.audit.*') => [
                    'section' => 'ADMINISTRACIÓN',
                    'breadcrumb' => 'Auditoría',
                    'description' => 'Consulta el historial de cambios administrativos.',
                ],
                request()->routeIs('inventory.kardex') => [
                    'section' => 'INVENTARIO',
                    'breadcrumb' => 'Kardex',
                    'description' => 'Consulta en un mismo informe todos los movimientos de las bodegas y productos a los que tienes acceso. Los filtros son opcionales y el saldo se calcula por producto y bodega.',
                ],
                request()->routeIs('inventory.receipts.*') => [
                    'section' => 'INVENTARIO',
                    'breadcrumb' => 'Entradas de inventario',
                    'description' => 'Consulta los recibos registrados y revisa sus productos, bodegas y fechas.',
                ],
                request()->routeIs('inventory.issues.*') => [
                    'section' => 'INVENTARIO',
                    'breadcrumb' => 'Salidas de inventario',
                    'description' => 'Consulta los despachos registrados y revisa su origen, bodegas y fechas.',
                ],
                request()->routeIs('inventory.requisitions.*') => [
                    'section' => 'INVENTARIO',
                    'breadcrumb' => 'Requisiciones',
                    'description' => 'Gestiona borradores, envía solicitudes a aprobación y registra las salidas parciales de inventario.',
                ],
                request()->routeIs('purchasing.orders.direct.create') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Órdenes de compra',
                    'description' => request()->query('mode')
                        ? 'Completa los datos y productos de la orden. Debe aprobarse antes de recibir productos.'
                        : 'Elige cómo originar la orden. Todas las órdenes deben aprobarse antes de recibir productos.',
                ],
                request()->routeIs('purchasing.orders.*') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Órdenes de compra',
                    'description' => 'Consulta las órdenes de compra generadas y revisa su estado.',
                ],
                request()->routeIs('purchasing.requisitions.*') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Requisiciones',
                    'description' => 'Consulta y gestiona las solicitudes de compra.',
                ],
                request()->routeIs('purchasing.quotes.queue') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Registrar cotización',
                    'description' => 'Selecciona una requisición aprobada para registrar cotizaciones de proveedores.',
                ],
                request()->routeIs('purchasing.quotes.register') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Registrar cotización',
                    'description' => 'Captura una cotización de proveedor para la requisición seleccionada.',
                ],
                request()->routeIs('purchasing.quotes.index') => [
                    'section' => 'COMPRAS',
                    'breadcrumb' => 'Comparación de cotizaciones',
                    'description' => 'Compara ofertas por producto y genera órdenes de compra por etapas.',
                ],
                request()->routeIs('profile.*') => [
                    'section' => 'CUENTA',
                    'breadcrumb' => 'Mi perfil',
                    'description' => 'Actualiza tu información y las opciones de acceso.',
                ],
                default => [
                    'section' => 'ALMACENA',
                    'breadcrumb' => 'Espacio de trabajo',
                    'description' => '',
                ],
            };
        @endphp
        <div class="workspace">
            @include('layouts.navigation')

            <div class="main-content">
                <header class="topbar">
                    <div class="breadcrumb" aria-label="Ubicación actual">
                        <span>{{ __('Espacio de trabajo') }}</span>
                        <span class="breadcrumb-separator" aria-hidden="true">/</span>
                        <strong>{{ __($pageMeta['breadcrumb']) }}</strong>
                    </div>
                    <span class="local-badge">
                        <span class="status-dot" aria-hidden="true"></span>
                        {{ config('app.name', 'Almacena') }}
                    </span>
                </header>

                <div class="page-container">
                    <section class="page-heading">
                        <div class="page-heading-copy">
                            <span class="eyebrow">{{ __($pageMeta['section']) }}</span>
                            @isset($header)
                                <div class="page-heading-slot">{{ $header }}</div>
                            @else
                                <h1>{{ config('app.name', 'Almacena') }}</h1>
                            @endisset
                            @if ($pageMeta['description'])
                                <p>{{ __($pageMeta['description']) }}</p>
                            @endif
                        </div>
                    </section>
                    {{ $slot }}
                </div>
            </div>
        </div>
        <dialog class="admin-form-dialog" id="admin-form-dialog" aria-labelledby="admin-form-dialog-title">
            <section class="admin-form-panel">
                <header class="admin-form-header">
                    <h2 id="admin-form-dialog-title"></h2>
                    <button class="admin-form-close" type="button" aria-label="{{ __('Cerrar formulario') }}" data-admin-form-close>&times;</button>
                </header>
                <div class="admin-form-content" id="admin-form-dialog-content"></div>
            </section>
        </dialog>
    </body>
</html>
