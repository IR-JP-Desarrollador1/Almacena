<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Kardex de inventario') }}</h1>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('inventory.kardex') }}" class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">{{ __('Bodega') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todas mis bodegas') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-1" />
                </div>
                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700">{{ __('Producto') }}</label>
                    <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los productos con movimientos') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->code }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('product_id')" class="mt-1" />
                </div>
                <div>
                    <label for="from_date" class="block text-sm font-medium text-gray-700">{{ __('Desde') }}</label>
                    <input id="from_date" name="from_date" type="date" value="{{ $filters['from_date'] ?? '' }}" max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Formato regional: :date', ['date' => $regional->date('2026-08-17')]) }}</p>
                    <x-input-error :messages="$errors->get('from_date')" class="mt-1" />
                </div>
                <div>
                    <label for="to_date" class="block text-sm font-medium text-gray-700">{{ __('Hasta') }}</label>
                    <input id="to_date" name="to_date" type="date" value="{{ $filters['to_date'] ?? '' }}" max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Formato regional: :date', ['date' => $regional->date('2026-08-17')]) }}</p>
                    <x-input-error :messages="$errors->get('to_date')" class="mt-1" />
                </div>
                <div class="flex flex-wrap items-center gap-3 md:col-span-2 lg:col-span-4">
                    <x-primary-button>{{ __('Consultar movimientos') }}</x-primary-button>
                    <a href="{{ route('inventory.kardex') }}" class="text-sm text-gray-600 underline">{{ __('Limpiar filtros') }}</a>
                </div>
            </form>
        </section>

        @if ($movements->total() > 0)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
                    <p class="text-sm text-gray-600">{{ __(':count movimientos en el informe', ['count' => $regional->number($movements->total(), 0)]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('inventory.kardex.export', [...$filters, 'format' => 'xlsx']) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Excel (.xlsx)') }}</a>
                        <a href="{{ route('inventory.kardex.export', [...$filters, 'format' => 'csv']) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('CSV') }}</a>
                        <a href="{{ route('inventory.kardex.export', [...$filters, 'format' => 'pdf']) }}" class="rounded-md bg-green-700 px-3 py-2 text-sm font-medium text-white hover:bg-green-800">{{ __('PDF') }}</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Sede / bodega') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Fecha') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Movimiento') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Documento') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Lote') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Cantidad') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Saldo') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Costo unitario base') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Moneda') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Registrado por') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($movements as $movement)
                                @php
                                    $signedQuantity = (float) $movement->quantity * ($movement->movement_type === \App\Models\StockMovement::TYPE_RECEIPT ? 1 : -1);
                                    $documentReference = $movement->movement_type === \App\Models\StockMovement::TYPE_RECEIPT
                                        ? $movement->receipt?->reference
                                        : $movement->inventoryIssue?->reference;
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $movement->warehouse->branch->name }} / {{ $movement->warehouse->name }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $movement->product->code }} — {{ $movement->product->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->dateTime($movement->occurred_at) }}</td>
                                    <td class="px-6 py-4 text-sm font-medium">{{ $movement->movement_type === \App\Models\StockMovement::TYPE_RECEIPT ? __('Entrada') : __('Salida') }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $documentReference ?: $movement->reference_type.' #'.$movement->reference_id }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $movement->lot->lot_code }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm {{ $signedQuantity < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $signedQuantity > 0 ? '+' : '' }}{{ $regional->number($signedQuantity, 3) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">{{ $regional->number($movement->balance_after, 3) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">{{ $regional->number($movement->unit_cost_base, 4) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $movement->lot->baseCurrency->code }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $movement->user->name }} {{ $movement->user->last_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay movimientos para los filtros seleccionados.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($movements->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">{{ $movements->withQueryString()->links() }}</div>
                @endif
            </section>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
                {{ __('No hay movimientos que coincidan con los filtros seleccionados. Prueba quitando los filtros para consultar todas las bodegas y productos autorizados.') }}
            </div>
        @endif
    </main>
</x-app-layout>
