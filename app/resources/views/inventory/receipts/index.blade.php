<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Entradas de inventario') }}</h1>
            @if (Auth::user()->hasPermission('inventory.receipts.create') || Auth::user()->hasPermission('inventory.receipts.direct.create'))
                <a href="{{ route('inventory.receipts.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Registrar entrada') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8 movement-list-page">
        @if (session('status'))
            <div class="movement-list-notice" role="status">{{ session('status') }}</div>
        @endif
        <section class="movement-list-card rounded-xl border border-gray-200 bg-white shadow-sm" aria-label="{{ __('Entradas de inventario') }}">
            <div class="movement-list-scroll">
                <table class="movement-list-table" aria-label="{{ __('Entradas de inventario') }}">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Entrada') }}</th>
                            <th scope="col">{{ __('Origen') }}</th>
                            <th scope="col">{{ __('Bodega') }}</th>
                            <th scope="col">{{ __('Productos') }}</th>
                            <th scope="col">{{ __('Recibida por') }}</th>
                            <th scope="col">{{ __('Fecha') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td><a class="movement-list-reference" href="{{ route('inventory.receipts.show', $receipt) }}">{{ $receipt->reference }}</a></td>
                            <td>
                                @if ($receipt->purchaseOrder)
                                    <span class="movement-list-location">
                                        <span>{{ __('Orden de compra') }}</span>
                                        <span>{{ $receipt->purchaseOrder->reference }}</span>
                                    </span>
                                @else
                                    <span class="movement-list-location">
                                        <span>{{ __('Entrada directa') }}</span>
                                        <span>{{ __('receipt_reason.'.$receipt->direct_reason) }}</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="movement-list-location">
                                    <span>{{ $receipt->warehouse->branch->name }}</span>
                                    <span>{{ $receipt->warehouse->name }}</span>
                                </span>
                            </td>
                            <td>{{ $receipt->items->count() }}</td>
                            <td>{{ $receipt->receiver->name }} {{ $receipt->receiver->last_name }}</td>
                            <td>{{ $regional->dateTime($receipt->received_at) }}</td>
                        </tr>
                    @empty
                        <tr class="movement-list-empty-row">
                            <td class="movement-list-empty" colspan="6">{{ __('No hay entradas para mostrar.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="movement-list-pagination border-t border-gray-200 px-5 py-4">{{ $receipts->links() }}</div>
        </section>
    </main>
</x-app-layout>
