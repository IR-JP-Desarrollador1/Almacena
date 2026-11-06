<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Conteos y ajustes de inventario') }}</h1>
            @if ($canCreate)
                <a data-admin-form-modal href="{{ route('inventory.counts.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">{{ __('Crear conteo') }}</a>
            @endif
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8 movement-list-page">
        @if (session('status'))
            <div class="movement-list-notice" role="status">{{ session('status') }}</div>
        @endif
        <section class="movement-list-card rounded-xl border border-gray-200 bg-white shadow-sm" aria-label="{{ __('Conteos y ajustes de inventario') }}">
            <div class="movement-list-scroll">
                <table class="movement-list-table" aria-label="{{ __('Conteos y ajustes de inventario') }}">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Conteo') }}</th>
                            <th scope="col">{{ __('Bodega') }}</th>
                            <th scope="col">{{ __('Productos') }}</th>
                            <th scope="col">{{ __('Estado') }}</th>
                            <th scope="col">{{ __('Creado por') }}</th>
                            <th scope="col">{{ __('Fecha') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($counts as $count)
                        <tr>
                            <td><a class="movement-list-reference" href="{{ route('inventory.counts.show', $count) }}">{{ $count->reference }}</a></td>
                            <td>
                                <span class="movement-list-location">
                                    <span>{{ $count->warehouse->branch->name }}</span>
                                    <span>{{ $count->warehouse->name }}</span>
                                </span>
                            </td>
                            <td>{{ $count->items_count }}</td>
                            <td>{{ __('inventory_count_status.'.$count->status) }}</td>
                            <td>{{ $count->creator->name }} {{ $count->creator->last_name }}</td>
                            <td>{{ $regional->dateTime($count->created_at) }}</td>
                        </tr>
                    @empty
                        <tr class="movement-list-empty-row">
                            <td class="movement-list-empty" colspan="6">{{ __('No hay conteos para mostrar.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="movement-list-pagination border-t border-gray-200 px-5 py-4">{{ $counts->links() }}</div>
        </section>
    </main>
</x-app-layout>
