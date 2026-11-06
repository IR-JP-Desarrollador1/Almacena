<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Reporte de inventario') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-label="{{ __('Filtros del reporte') }}">
            <form method="GET" action="{{ route('reports.inventory') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="report" :value="__('Reporte')" />
                    <select id="report" name="report" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="movements" @selected($report === 'movements')>{{ __('Entradas, salidas y traslados') }}</option>
                        <option value="stock" @selected($report === 'stock')>{{ __('Existencias actuales') }}</option>
                        <option value="adjustments" @selected($report === 'adjustments')>{{ __('Ajustes por conteo') }}</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="warehouse_id" :value="__('Bodega')" />
                    <select id="warehouse_id" name="warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todas las bodegas autorizadas') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="product_id" :value="__('Producto')" />
                    <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los productos') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->code }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="cost_center_id" :value="__('Centro de costo')" />
                    <select id="cost_center_id" name="cost_center_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los centros de costo') }}</option>
                        @foreach ($costCenters as $costCenter)
                            <option value="{{ $costCenter->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $costCenter->id)>{{ $costCenter->code }} — {{ $costCenter->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($report === 'movements')
                <div>
                    <x-input-label for="movement" :value="__('Tipo de movimiento')" />
                    <select id="movement" name="movement" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="all" @selected(($filters['movement'] ?? 'all') === 'all')>{{ __('Todos') }}</option>
                        <option value="receipts" @selected(($filters['movement'] ?? '') === 'receipts')>{{ __('Entradas') }}</option>
                        <option value="issues" @selected(($filters['movement'] ?? '') === 'issues')>{{ __('Salidas') }}</option>
                        <option value="transfers" @selected(($filters['movement'] ?? '') === 'transfers')>{{ __('Traslados') }}</option>
                    </select>
                </div>
                @endif
                @if ($report !== 'stock')
                <div>
                    <x-input-label for="from_date" :value="__('Desde')" />
                    <x-text-input id="from_date" name="from_date" type="date" class="mt-1 block w-full" :value="$filters['from_date'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="to_date" :value="__('Hasta')" />
                    <x-text-input id="to_date" name="to_date" type="date" class="mt-1 block w-full" :value="$filters['to_date'] ?? ''" />
                </div>
                @endif
                <div class="sm:col-span-2 lg:col-span-3">
                    <x-primary-button>{{ __('Aplicar filtros') }}</x-primary-button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-sm font-semibold text-gray-800">{{ __('Resultados del reporte') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.inventory', array_merge($filters, ['format' => 'csv'])) }}"
                       class="inline-flex min-h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        {{ __('Exportar a CSV') }}
                    </a>
                    <a href="{{ route('reports.inventory', array_merge($filters, ['format' => 'xlsx'])) }}"
                       class="inline-flex min-h-10 items-center justify-center rounded-md bg-emerald-900 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        {{ __('Exportar a Excel') }}
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    @if ($report === 'movements')
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Fecha') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Bodega') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Producto') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Movimiento') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Cantidad') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Costo unitario base') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Costo total') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Documento') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $movement)
                                @php
                                    $kind = $movement->reference_type === 'inventory_transfer'
                                        ? __('Traslado')
                                        : ($movement->movement_type === 'receipt' ? __('Entrada') : __('Salida'));
                                    $reference = $movement->reference_type === 'inventory_transfer'
                                        ? $movement->inventoryIssue?->reference
                                        : $movement->reference_type.' #'.$movement->reference_id;
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $regional->dateTime($movement->occurred_at) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $movement->warehouse->branch->name }} — {{ $movement->warehouse->name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $movement->warehouse->costCenter?->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $movement->product->code }}</span><span class="block text-gray-500">{{ $movement->product->name }}</span></td>
                                    <td class="px-4 py-3 text-sm">{{ $kind }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($movement->quantity, 0) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($movement->unit_cost_base, 2) }} {{ $baseCurrency ?? '' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($movement->total_cost_base, 2) }} {{ $baseCurrency ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $reference ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No se encontraron movimientos con estos filtros.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @elseif ($report === 'stock')
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Bodega') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Producto') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Existencia actual') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Valor actual') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $stock)
                                <tr>
                                    <td class="px-4 py-3 text-sm">{{ $stock->branch_name }} — {{ $stock->warehouse_name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $stock->cost_center_code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $stock->product_code }}</span><span class="block text-gray-500">{{ $stock->product_name }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($stock->current_quantity, 0) }} {{ $stock->unit_abbreviation }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($stock->current_value, 2) }} {{ $baseCurrency ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No hay existencias positivas para mostrar.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @else
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Fecha de aprobación') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Conteo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Bodega') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Producto') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Existencia teórica') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Cantidad contada') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Diferencia') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $item)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $regional->dateTime($item->inventoryCount->approved_at) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->inventoryCount->reference }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->inventoryCount->warehouse->name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->inventoryCount->warehouse->costCenter?->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $item->product_code }}</span><span class="block text-gray-500">{{ $item->product_name }}</span></td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number($item->system_quantity, 0) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number($item->counted_quantity, 0) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number($item->difference_quantity, 0) }} ({{ (float) $item->difference_quantity > 0 ? __('Sobrante') : __('Faltante') }})</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No se encontraron ajustes aprobados con estos filtros.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>
            <div class="border-t border-gray-200 px-4 py-4">{{ $rows->links() }}</div>
        </section>
    </main>
</x-app-layout>
