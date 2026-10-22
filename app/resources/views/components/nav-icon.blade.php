@props(['name'])

<svg class="nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <path d="m2.5 9 7.5-6 7.5 6v8a1 1 0 0 1-1 1h-4.5v-5.5h-6V18H3.5a1 1 0 0 1-1-1V9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            @break
        @case('purchases')
            <path d="M3.5 4.5h13v12h-13zM6.5 2.5v4m7-4v4M6.5 9h7m-7 3h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('receipt')
            <path d="M4 2.5h12v15l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5v-15ZM7 7h6m-6 3h6m-6 3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('issue')
            <path d="M10 2.5v10m0 0 3.5-3.5M10 12.5 6.5 9M4 13.5v3a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('branch')
            <path d="M3 17.5h14M4.5 17.5v-11l5.5-3 5.5 3v11M7.5 9h1m3 0h1m-5 3h1m3 0h1m-2 5.5v-4h-2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('warehouse')
            <path d="m2.5 8 7.5-5 7.5 5v9.5h-15V8Zm0 0 7.5 5L17.5 8M10 13v4.5m-3-7v3m6-3v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('location')
            <path d="M16 8.5c0 4.2-6 9-6 9s-6-4.8-6-9a6 6 0 1 1 12 0Z" stroke="currentColor" stroke-width="1.5" /><circle cx="10" cy="8.5" r="2" stroke="currentColor" stroke-width="1.5" />
            @break
        @case('cost-center')
            <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5" /><path d="M10 6v8m2-6.5c-.5-.6-1.2-.9-2-.9-1.1 0-2 .6-2 1.5s.9 1.4 2 1.8 2 1 2 1.8-.9 1.5-2 1.5c-.8 0-1.5-.3-2-.9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
            @break
        @case('department')
            <circle cx="10" cy="6" r="2.5" stroke="currentColor" stroke-width="1.5" /><path d="M4 17v-1a6 6 0 0 1 12 0v1m-6-8v2m-3-1h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            @break
        @case('product')
            <path d="m10 2.5 7 4v7l-7 4-7-4v-7l7-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m3.5 6.5 6.5 4 6.5-4M10 10.5v7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            @break
        @case('unit')
            <path d="M3 3h5v5H3zM12 3h5v5h-5zM3 12h5v5H3zM12 12h5v5h-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            @break
        @case('supplier')
            <path d="M3 17.5h14M4.5 17.5V8l5.5-4 5.5 4v9.5M7.5 10h1m3 0h1m-5 3h1m3 0h1m-2 4.5v-3h-2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('currency')
            <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5" /><path d="M12.5 7.5c-.6-.7-1.5-1-2.5-1-1.4 0-2.5.8-2.5 1.8 0 1.1 1.1 1.6 2.5 1.9s2.5.8 2.5 1.9-1.1 1.8-2.5 1.8c-1 0-1.9-.4-2.5-1m2.5-7.5v10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
            @break
        @case('users')
            <circle cx="7" cy="6" r="2.5" stroke="currentColor" stroke-width="1.5" /><path d="M2.5 17v-1a4.5 4.5 0 0 1 9 0v1m2-7.5a2.5 2.5 0 1 0 0-5m1 7.5a4 4 0 0 1 3 3.9V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            @break
        @case('roles')
            <path d="M10 2.5 16 5v4.2c0 3.6-2.4 6.3-6 7.6-3.6-1.3-6-4-6-7.6V5l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m7.3 9.8 1.8 1.8 3.7-3.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('company')
            <path d="M3 17.5h14M4.5 17.5V5h11v12.5M7.5 8h1m3 0h1m-5 3h1m3 0h1m-5 3h1m3 0h1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('audit')
            <path d="M4 3.5h8l4 4v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-12a1 1 0 0 1 1-1Zm8 0v4h4m-8 3h4m-4 3h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('logout')
            <path d="M8 3.5H4.5a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1H8m4-3 4-3.5L12 6.5m4 3.5H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            @break
    @endswitch
</svg>
