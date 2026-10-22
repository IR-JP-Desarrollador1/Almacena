<x-app-layout>
    <x-slot name="header">
        <h1>{{ __('Panel principal') }}</h1>
    </x-slot>

    <main class="dashboard-content">
        <section class="dashboard-links" aria-label="{{ __('Módulos disponibles') }}">
            @if (Auth::user()->hasPermission('catalogs.view') || Auth::user()->hasPermission('catalogs.manage'))
                <a class="dashboard-link" href="{{ route('admin.catalogs.products.index') }}">
                    <span class="dashboard-link-icon" aria-hidden="true">01</span>
                    <span>
                        <strong>{{ __('Datos maestros') }}</strong>
                        <small>{{ __('Consulta productos, unidades y proveedores.') }}</small>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif

            @if (Auth::user()->hasPermission('organization.view') || Auth::user()->hasPermission('organization.manage') || Auth::user()->hasPermission('departments.view') || Auth::user()->hasPermission('departments.manage'))
                <a class="dashboard-link" href="{{ Auth::user()->hasPermission('organization.view') || Auth::user()->hasPermission('organization.manage') ? route('admin.organization.branches.index') : route('admin.organization.departments.index') }}">
                    <span class="dashboard-link-icon" aria-hidden="true">02</span>
                    <span>
                        <strong>{{ __('Organización') }}</strong>
                        <small>{{ __('Gestiona sedes, bodegas y ubicaciones.') }}</small>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif

            @if (Auth::user()->hasPermission('purchases.requisitions.view') || Auth::user()->hasPermission('purchases.requisitions.create') || Auth::user()->hasPermission('purchases.requisitions.decide'))
                <a class="dashboard-link" href="{{ route('purchasing.requisitions.index') }}">
                    <span class="dashboard-link-icon" aria-hidden="true">03</span>
                    <span>
                        <strong>{{ __('Compras') }}</strong>
                        <small>{{ __('Da seguimiento a las solicitudes de compra.') }}</small>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif

            @if (Auth::user()->hasPermission('users.view'))
                <a class="dashboard-link" href="{{ route('admin.users.index') }}">
                    <span class="dashboard-link-icon" aria-hidden="true">04</span>
                    <span>
                        <strong>{{ __('Usuarios') }}</strong>
                        <small>{{ __('Consulta y administra las cuentas de acceso.') }}</small>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </section>
    </main>
</x-app-layout>
