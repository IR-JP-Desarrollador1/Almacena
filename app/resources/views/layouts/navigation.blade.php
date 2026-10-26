@php
    $user = Auth::user();
    $canOrganization = $user->hasPermission('organization.view') || $user->hasPermission('organization.manage');
    $canDepartments = $user->hasPermission('departments.view') || $user->hasPermission('departments.manage');
    $canCatalogs = $user->hasPermission('catalogs.view') || $user->hasPermission('catalogs.manage');
    $canPurchases = $user->hasPermission('purchases.requisitions.view')
        || $user->hasPermission('purchases.requisitions.approve')
        || $user->hasPermission('purchases.requisitions.reject')
        || $user->hasPermission('purchases.quotes.view')
        || $user->hasPermission('purchases.quotes.manage')
        || $user->hasPermission('purchases.quotes.award')
        || $user->hasPermission('purchases.quotes.generate_orders')
        || $user->hasPermission('purchases.orders.view')
        || $user->hasPermission('purchases.orders.submit')
        || $user->hasPermission('purchases.orders.approve')
        || $user->hasPermission('purchases.orders.approve_any')
        || $user->hasPermission('purchases.orders.reject');
    $canInventory = $user->hasPermission('inventory.receipts.view')
        || $user->hasPermission('inventory.receipts.create')
        || $user->hasPermission('inventory.receipts.direct.create')
        || $user->hasPermission('inventory.issues.view')
        || $user->hasPermission('inventory.issues.create')
        || $user->hasPermission('inventory.issues.direct.create')
        || $user->hasPermission('inventory.requisitions.dispatch')
        || $user->hasPermission('inventory.movements.view')
        || $user->hasPermission('inventory.requisitions.view')
        || $user->hasPermission('inventory.requisitions.manage')
        || $user->hasPermission('inventory.requisitions.submit');
    $canAdministration = $user->hasPermission('users.view')
        || $user->hasPermission('roles.manage')
        || $user->hasPermission('companies.manage')
        || $user->hasPermission('audit.view');
@endphp

<aside class="sidebar">
    <a class="sidebar-brand brand-lockup" href="{{ route('dashboard') }}">
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 40 40" fill="none">
                <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                <path d="m20 11.5 9 4.8-9 4.8-9-4.8 9-4.8Z" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M11 16.3v9.2l9 4.8 9-4.8v-9.2M20 21.1v9.2" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <span>AlmacenaLite<span class="brand-period">.</span></span>
    </a>

    <span class="nav-caption">{{ __('MENÚ PRINCIPAL') }}</span>
    <nav class="primary-nav" aria-label="{{ __('Navegación principal') }}">
        <div class="nav-group">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" href="{{ route('dashboard') }}">
                <x-nav-icon name="dashboard" />
                <span>{{ __('Panel principal') }}</span>
            </a>
        </div>

        @if ($canPurchases)
            <section class="nav-group" aria-label="{{ __('Compras') }}" x-data="{ open: {{ request()->routeIs('purchasing.*') ? 'true' : 'false' }} }" :class="{ 'nav-group-collapsed': !open }">
                <button class="nav-group-label" type="button" @click="open = !open" :aria-expanded="open" aria-controls="nav-group-purchases">
                    <span>{{ __('COMPRAS') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="nav-group-items" id="nav-group-purchases">
                    @if ($user->hasPermission('purchases.requisitions.view') || $user->hasPermission('purchases.requisitions.approve') || $user->hasPermission('purchases.requisitions.reject'))
                        <a class="nav-link {{ request()->routeIs('purchasing.requisitions.*') ? 'nav-link-active' : '' }}" href="{{ route('purchasing.requisitions.index') }}">
                            <x-nav-icon name="purchases" />
                            <span>{{ __('Requisiciones') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('purchases.quotes.view') || $user->hasPermission('purchases.quotes.manage') || $user->hasPermission('purchases.quotes.award') || $user->hasPermission('purchases.quotes.generate_orders'))
                        <a class="nav-link {{ request()->routeIs('purchasing.quotes.*') ? 'nav-link-active' : '' }}" href="{{ route('purchasing.quotes.queue') }}">
                            <x-nav-icon name="receipt" />
                            <span>{{ __('Cotizaciones') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('purchases.orders.view') || $user->hasPermission('purchases.quotes.generate_orders') || $user->hasPermission('purchases.orders.submit') || $user->hasPermission('purchases.orders.approve') || $user->hasPermission('purchases.orders.approve_any') || $user->hasPermission('purchases.orders.reject'))
                        <a class="nav-link {{ request()->routeIs('purchasing.orders.*') ? 'nav-link-active' : '' }}" href="{{ route('purchasing.orders.index') }}">
                            <x-nav-icon name="receipt" />
                            <span>{{ __('Órdenes de compra') }}</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        @if ($canInventory)
            <section class="nav-group" aria-label="{{ __('Inventario') }}" x-data="{ open: {{ request()->routeIs('inventory.*') ? 'true' : 'false' }} }" :class="{ 'nav-group-collapsed': !open }">
                <button class="nav-group-label" type="button" @click="open = !open" :aria-expanded="open" aria-controls="nav-group-inventory">
                    <span>{{ __('INVENTARIO') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="nav-group-items" id="nav-group-inventory">
                    @if ($user->hasPermission('inventory.requisitions.view') || $user->hasPermission('inventory.requisitions.manage') || $user->hasPermission('inventory.requisitions.submit') || $user->hasPermission('inventory.requisitions.dispatch') || $user->hasPermission('inventory.issues.create'))
                        <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') ? 'nav-link-active' : '' }}" href="{{ route('inventory.requisitions.index') }}">
                            <x-nav-icon name="purchases" /><span>{{ __('Requisiciones') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('inventory.movements.view'))
                        <a class="nav-link {{ request()->routeIs('inventory.kardex') ? 'nav-link-active' : '' }}" href="{{ route('inventory.kardex') }}">
                            <x-nav-icon name="receipt" /><span>{{ __('Kardex') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('inventory.receipts.view') || $user->hasPermission('inventory.receipts.create') || $user->hasPermission('inventory.receipts.direct.create'))
                        <a class="nav-link {{ request()->routeIs('inventory.receipts.*') ? 'nav-link-active' : '' }}" href="{{ route('inventory.receipts.index') }}">
                            <x-nav-icon name="receipt" /><span>{{ __('Entradas') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('inventory.issues.view') || $user->hasPermission('inventory.issues.create') || $user->hasPermission('inventory.issues.direct.create'))
                        <a class="nav-link {{ request()->routeIs('inventory.issues.*') ? 'nav-link-active' : '' }}" href="{{ route('inventory.issues.index') }}">
                            <x-nav-icon name="issue" /><span>{{ __('Salidas') }}</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        @if ($canOrganization || $canDepartments)
            <section class="nav-group" aria-label="{{ __('Organización e infraestructura') }}" x-data="{ open: {{ request()->routeIs('admin.organization.*') ? 'true' : 'false' }} }" :class="{ 'nav-group-collapsed': !open }">
                <button class="nav-group-label" type="button" @click="open = !open" :aria-expanded="open" aria-controls="nav-group-organization">
                    <span>{{ __('ORGANIZACIÓN') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="nav-group-items" id="nav-group-organization">
                    @if ($canOrganization)
                        <a class="nav-link {{ request()->routeIs('admin.organization.branches.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.organization.branches.index') }}">
                            <x-nav-icon name="branch" /><span>{{ __('Sedes') }}</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.organization.warehouses.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.organization.warehouses.index') }}">
                            <x-nav-icon name="warehouse" /><span>{{ __('Bodegas') }}</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.organization.locations.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.organization.locations.index') }}">
                            <x-nav-icon name="location" /><span>{{ __('Ubicaciones') }}</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.organization.cost-centers.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.organization.cost-centers.index') }}">
                            <x-nav-icon name="cost-center" /><span>{{ __('Centros de costo') }}</span>
                        </a>
                    @endif
                    @if ($canDepartments)
                        <a class="nav-link {{ request()->routeIs('admin.organization.departments.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.organization.departments.index') }}">
                            <x-nav-icon name="department" /><span>{{ __('Áreas solicitantes') }}</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        @if ($canCatalogs)
            <section class="nav-group" aria-label="{{ __('Datos maestros') }}" x-data="{ open: {{ request()->routeIs('admin.catalogs.*') ? 'true' : 'false' }} }" :class="{ 'nav-group-collapsed': !open }">
                <button class="nav-group-label" type="button" @click="open = !open" :aria-expanded="open" aria-controls="nav-group-catalogs">
                    <span>{{ __('DATOS MAESTROS') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="nav-group-items" id="nav-group-catalogs">
                    <a class="nav-link {{ ! request()->routeIs('admin.catalogs.products.import*') && request()->routeIs('admin.catalogs.products.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.products.index') }}">
                        <x-nav-icon name="product" /><span>{{ __('Productos') }}</span>
                    </a>
                    @if ($user->hasPermission('catalogs.manage'))
                        <a class="nav-link {{ request()->routeIs('admin.catalogs.products.import*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.products.import.form') }}">
                            <x-nav-icon name="import" /><span>{{ __('Importación de datos maestros') }}</span>
                        </a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('admin.catalogs.units.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.units.index') }}">
                        <x-nav-icon name="unit" /><span>{{ __('Unidades') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.catalogs.suppliers.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.suppliers.index') }}">
                        <x-nav-icon name="supplier" /><span>{{ __('Proveedores') }}</span>
                    </a>
                    @if ($user->hasPermission('catalogs.manage'))
                        <a class="nav-link {{ request()->routeIs('admin.catalogs.supplier-evaluations.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.supplier-evaluations.index') }}">
                            <x-nav-icon name="supplier" /><span>{{ __('Calificación de proveedores') }}</span>
                        </a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('admin.catalogs.currencies.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.catalogs.currencies.index') }}">
                        <x-nav-icon name="currency" /><span>{{ __('Monedas') }}</span>
                    </a>
                </div>
            </section>
        @endif

        @if ($canAdministration)
            <section class="nav-group" aria-label="{{ __('Administración') }}" x-data="{ open: {{ request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.company.*', 'admin.regional-settings.*', 'admin.audit.*') ? 'true' : 'false' }} }" :class="{ 'nav-group-collapsed': !open }">
                <button class="nav-group-label" type="button" @click="open = !open" :aria-expanded="open" aria-controls="nav-group-administration">
                    <span>{{ __('ADMINISTRACIÓN') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <div class="nav-group-items" id="nav-group-administration">
                    @if ($user->hasPermission('users.view'))
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.users.index') }}">
                            <x-nav-icon name="users" /><span>{{ __('Usuarios') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('roles.manage'))
                        <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.roles.index') }}">
                            <x-nav-icon name="roles" /><span>{{ __('Perfiles y permisos') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('companies.manage'))
                        <a class="nav-link {{ request()->routeIs('admin.company.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.company.edit') }}">
                            <x-nav-icon name="company" /><span>{{ __('Empresa') }}</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.regional-settings.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.regional-settings.edit') }}">
                            <x-nav-icon name="company" /><span>{{ __('Configuración regional') }}</span>
                        </a>
                    @endif
                    @if ($user->hasPermission('audit.view'))
                        <a class="nav-link {{ request()->routeIs('admin.audit.*') ? 'nav-link-active' : '' }}" href="{{ route('admin.audit.index') }}">
                            <x-nav-icon name="audit" /><span>{{ __('Auditoría') }}</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif
    </nav>

    <div class="sidebar-bottom">
        <div class="connection-card">
            <span class="connection-indicator" aria-hidden="true"></span>
            <div>
                <strong>AlmacenaLite</strong>
                <span>{{ __('Gestión centralizada') }}</span>
            </div>
        </div>

        <a class="profile-button" href="{{ route('profile.edit') }}">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <span class="profile-copy">
                <strong>{{ trim($user->name.' '.$user->last_name) }}</strong>
                <span>{{ __('Mi perfil') }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="nav-link" type="submit">
                <x-nav-icon name="logout" />
                <span>{{ __('Cerrar sesión') }}</span>
            </button>
        </form>
    </div>
</aside>
