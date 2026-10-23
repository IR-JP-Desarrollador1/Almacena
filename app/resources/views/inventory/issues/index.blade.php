<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Salidas de inventario') }}</h1>
            @if (Auth::user()->hasPermission('inventory.issues.direct.create') || Auth::user()->hasPermission('inventory.requisitions.dispatch'))
                <a href="{{ route('inventory.issues.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">{{ __('Registrar salida') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8 movement-list-page">
        @if (session('status'))
            <div class="movement-list-notice" role="status">{{ session('status') }}</div>
        @endif
        <section class="movement-list-card rounded-xl border border-gray-200 bg-white shadow-sm" aria-label="{{ __('Salidas de inventario') }}">
            <div class="movement-list-scroll">
                <table class="movement-list-table" aria-label="{{ __('Salidas de inventario') }}">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Salida') }}</th>
                            <th scope="col">{{ __('Origen') }}</th>
                            <th scope="col">{{ __('Bodega') }}</th>
                            <th scope="col">{{ __('Productos') }}</th>
                            <th scope="col">{{ __('Entregada por') }}</th>
                            <th scope="col">{{ __('Fecha') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($issues as $issue)
                        <tr>
                            <td><a class="movement-list-reference" href="{{ route('inventory.issues.show', $issue) }}">{{ $issue->reference }}</a></td>
                            <td>
                                @if ($issue->source_type === \App\Models\InventoryIssue::SOURCE_DIRECT)
                                    <span class="movement-list-location">
                                        <span>{{ __('Salida directa') }}</span>
                                        <span>{{ __('issue_reason.'.$issue->direct_reason) }}</span>
                                    </span>
                                @else
                                    {{ $issue->requisition?->reference ?? '—' }}
                                @endif
                            </td>
                            <td>
                                <span class="movement-list-location">
                                    <span>{{ $issue->warehouse->branch->name }}</span>
                                    <span>{{ $issue->warehouse->name }}</span>
                                </span>
                            </td>
                            <td>{{ $issue->items->count() }}</td>
                            <td>{{ $issue->issuer->name }} {{ $issue->issuer->last_name }}</td>
                            <td>{{ $regional->dateTime($issue->issued_at) }}</td>
                        </tr>
                    @empty
                        <tr class="movement-list-empty-row">
                            <td class="movement-list-empty" colspan="6">{{ __('No hay salidas para mostrar.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="movement-list-pagination border-t border-gray-200 px-5 py-4">{{ $issues->links() }}</div>
        </section>
    </main>
</x-app-layout>
