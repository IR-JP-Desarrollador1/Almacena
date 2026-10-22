<nav class="module-tabs" aria-label="{{ __('Navegación de datos maestros') }}">
    <a class="module-tab {{ request()->routeIs('admin.catalogs.products.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.catalogs.products.index') }}">{{ __('Productos') }}</a>
    <a class="module-tab {{ request()->routeIs('admin.catalogs.units.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.catalogs.units.index') }}">{{ __('Unidades') }}</a>
    <a class="module-tab {{ request()->routeIs('admin.catalogs.suppliers.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.catalogs.suppliers.index') }}">{{ __('Proveedores') }}</a>
    <a class="module-tab {{ request()->routeIs('admin.catalogs.currencies.*') ? 'module-tab-active' : '' }}" href="{{ route('admin.catalogs.currencies.index') }}">{{ __('Monedas') }}</a>
</nav>
