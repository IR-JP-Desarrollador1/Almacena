<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Proveedores') }}</h1>
            @if (Auth::user()->hasPermission('catalogs.manage'))
                <a data-admin-form-modal href="{{ route('admin.catalogs.suppliers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear proveedor') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @include('admin.catalogs._tabs')
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Identificación') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Razón social') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Contacto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Correo') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Estado') }}</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($suppliers as $supplier)
                        <tr><td class="px-6 py-4 text-sm">{{ $supplier->tax_id ?: '—' }}</td><td class="px-6 py-4 text-sm">{{ $supplier->trade_name ?: $supplier->legal_name }}</td><td class="px-6 py-4 text-sm">{{ $supplier->contact_name ?: '—' }}</td><td class="px-6 py-4 text-sm">{{ $supplier->email ?: '—' }}</td><td class="px-6 py-4 text-sm">{{ $supplier->is_active ? __('Activo') : __('Inactivo') }}</td><td class="px-6 py-4 text-right text-sm">@if (Auth::user()->hasPermission('catalogs.manage'))<a data-admin-form-modal class="text-indigo-700 underline" href="{{ route('admin.catalogs.suppliers.edit', $supplier) }}">{{ __('Editar') }}</a>@endif</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay proveedores registrados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $suppliers->links() }}
    </main>
</x-app-layout>
