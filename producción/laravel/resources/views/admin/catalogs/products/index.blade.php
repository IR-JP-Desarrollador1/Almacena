<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Productos') }}</h1>
            @if (Auth::user()->hasPermission('catalogs.manage'))
                <a data-admin-form-modal href="{{ route('admin.catalogs.products.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear producto') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @include('admin.catalogs._tabs')
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Código') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Unidad') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Maneja vencimiento') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Estado') }}</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($products as $product)
                        <tr><td class="px-6 py-4 text-sm">{{ $product->code }}</td><td class="px-6 py-4 text-sm">{{ $product->name }}</td><td class="px-6 py-4 text-sm">{{ $product->unit->abbreviation }}</td><td class="px-6 py-4 text-sm">{{ $product->tracks_expiration ? __('Sí') : __('No') }}</td><td class="px-6 py-4 text-sm">{{ $product->is_active ? __('Activo') : __('Inactivo') }}</td><td class="px-6 py-4 text-right text-sm">@if (Auth::user()->hasPermission('catalogs.manage'))<a data-admin-form-modal class="text-indigo-700 underline" href="{{ route('admin.catalogs.products.edit', $product) }}">{{ __('Editar') }}</a>@endif</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay productos registrados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </main>
</x-app-layout>
