<nav class="module-tabs" aria-label="{{ __('Navegación de organización') }}">
    @if (Auth::user()->hasPermission('organization.view') || Auth::user()->hasPermission('organization.manage'))
        <a class="module-tab {{ request()->routeIs('admin.organization.branches.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.organization.branches.index') }}">{{ __('Sedes') }}</a>
        <a class="module-tab {{ request()->routeIs('admin.organization.warehouses.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.organization.warehouses.index') }}">{{ __('Bodegas') }}</a>
        <a class="module-tab {{ request()->routeIs('admin.organization.locations.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.organization.locations.index') }}">{{ __('Ubicaciones') }}</a>
        <a class="module-tab {{ request()->routeIs('admin.organization.cost-centers.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.organization.cost-centers.index') }}">{{ __('Centros de costo') }}</a>
    @endif
    @if (Auth::user()->hasPermission('departments.view') || Auth::user()->hasPermission('departments.manage'))
        <a class="module-tab {{ request()->routeIs('admin.organization.departments.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.organization.departments.index') }}">{{ __('Áreas solicitantes') }}</a>
    @endif
</nav>
