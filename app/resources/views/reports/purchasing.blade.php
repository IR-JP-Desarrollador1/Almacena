<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Reporte de compras') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-label="{{ __('Filtros del reporte') }}">
            <form method="GET" action="{{ route('reports.purchasing') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="report" :value="__('Reporte')" />
                    <select id="report" name="report" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="orders" @selected($report === 'orders')>{{ __('Órdenes de compra') }}</option>
                        <option value="spend" @selected($report === 'spend')>{{ __('Compras aprobadas por producto') }}</option>
                        <option value="requisitions" @selected($report === 'requisitions')>{{ __('Requisiciones') }}</option>
                    </select>
                </div>
                @if ($report !== 'spend')
                <div>
                    <x-input-label for="status" :value="__('Estado')" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los estados') }}</option>
                        @foreach (($report === 'requisitions'
                            ? ['draft' => __('Borrador'), 'active' => __('Activa'), 'pending_approval' => __('Pendiente de aprobación'), 'approved' => __('Aprobada'), 'rejected' => __('Rechazada'), 'cancelled' => __('Cancelada')]
                            : ['draft' => __('Borrador'), 'pending_approval' => __('Pendiente de aprobación'), 'approved' => __('Aprobada'), 'rejected' => __('Rechazada')]) as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if ($report !== 'requisitions')
                <div>
                    <x-input-label for="supplier_id" :value="__('Proveedor')" />
                    <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los proveedores') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->legal_name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <x-input-label for="cost_center_id" :value="__('Centro de costo')" />
                    <select id="cost_center_id" name="cost_center_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Todos los centros de costo') }}</option>
                        @foreach ($costCenters as $costCenter)
                            <option value="{{ $costCenter->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $costCenter->id)>{{ $costCenter->code }} — {{ $costCenter->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="from_date" :value="__('Desde')" />
                    <x-text-input id="from_date" name="from_date" type="date" class="mt-1 block w-full" :value="$filters['from_date'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="to_date" :value="__('Hasta')" />
                    <x-text-input id="to_date" name="to_date" type="date" class="mt-1 block w-full" :value="$filters['to_date'] ?? ''" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <x-primary-button>{{ __('Aplicar filtros') }}</x-primary-button>
                </div>
            </form>
        </section>

        @if ($report === 'spend')
            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-sm text-emerald-900">{{ __('Valor de líneas de órdenes aprobadas, calculado con costo unitario en moneda base. No representa facturas pagadas.') }}</p>
                <p class="mt-2 text-lg font-semibold text-emerald-950">{{ __('Total aprobado') }}: {{ $regional->number($rows['total'], 2) }} {{ $baseCurrency?->code }}</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-sm font-semibold text-gray-800">{{ __('Resultados del reporte') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.purchasing', array_merge($filters, ['format' => 'csv'])) }}"
                       class="inline-flex min-h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        {{ __('Exportar a CSV') }}
                    </a>
                    <a href="{{ route('reports.purchasing', array_merge($filters, ['format' => 'xlsx'])) }}"
                       class="inline-flex min-h-10 items-center justify-center rounded-md bg-emerald-900 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        {{ __('Exportar a Excel') }}
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    @if ($report === 'orders')
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Orden') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Proveedor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Bodega') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Fecha de creación') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Total estimado') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $order)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $order->reference }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $order->supplier->legal_name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $order->warehouse->name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $order->requisition?->department?->costCenter?->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ __('order_status.'.$order->status) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->date($order->created_at) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $regional->number($order->estimated_total_base, 2) }} {{ $baseCurrency?->code }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No se encontraron órdenes con estos filtros.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @elseif ($report === 'spend')
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Orden') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Proveedor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Producto') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Cantidad') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Costo unitario base') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Total de línea') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows['items'] as $item)
                                @php($order = $item->purchaseOrder)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $order->reference }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $order->supplier->legal_name }}</td>
                                    <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $item->product_code }}</span><span class="block text-gray-500">{{ $item->product_name }}</span></td>
                                    <td class="px-4 py-3 text-sm">{{ $order->requisition?->department?->costCenter?->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number($item->quantity, 0) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number($item->unit_cost_base, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->number((float) $item->quantity * (float) $item->unit_cost_base * (1 + (float) $item->tax_rate / 100), 2) }} {{ $baseCurrency?->code }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No se encontraron compras aprobadas con estos filtros.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @else
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Requisición') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Área') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Centro de costo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Bodega') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Solicitante') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Fecha de creación') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">{{ __('Productos') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $requisition)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $requisition->reference }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $requisition->department->name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $requisition->department->costCenter?->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $requisition->warehouse->name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ trim($requisition->requester->name.' '.$requisition->requester->last_name) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ __('requisition_status.'.$requisition->status) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $regional->date($requisition->created_at) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">{{ $requisition->items_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No se encontraron requisiciones con estos filtros.') }}</td></tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>
            <div class="border-t border-gray-200 px-4 py-4">
                @if ($report === 'spend')
                    {{ $rows['items']->links() }}
                @else
                    {{ $rows->links() }}
                @endif
            </div>
        </section>
    </main>
</x-app-layout>
