<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar entrada') }}</h1>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-5 md:grid-cols-2">
            @if ($canCreateDirect)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Entrada directa') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-800">{{ __('Seleccionar productos') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Registra saldos iniciales, donaciones, devoluciones o ajustes positivos indicando motivo y costo unitario en moneda base.') }}</p>
                    <a href="{{ route('inventory.receipts.create', ['mode' => 'direct']) }}" class="mt-5 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear entrada directa') }}</a>
                </article>
            @endif
            @if ($orders)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Orden de compra') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-800">{{ __('Entradas desde órdenes aprobadas') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Selecciona una orden aprobada con cantidades pendientes para registrar una recepción parcial o completa.') }}</p>
                </article>
            @endif
        </section>

        @if ($orders)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ __('Órdenes pendientes de recibir') }}">
                @if ($orders->isNotEmpty())
                    <div class="divide-y divide-gray-200">
                        @foreach ($orders as $order)
                            <article class="flex flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                                <div class="grid min-w-0 gap-2">
                                    <h2 class="font-semibold text-gray-800">{{ $order->reference }} · {{ $order->supplier->legal_name }}</h2>
                                    <p class="text-sm text-gray-600">{{ __('Bodega: :warehouse', ['warehouse' => $order->warehouse->branch->name.' / '.$order->warehouse->name]) }}</p>
                                    <ul class="grid gap-1 text-sm text-gray-500">
                                        @foreach ($order->items as $item)
                                            <li>{{ $item->product_code }} — {{ $item->product_name }} · {{ __('Pendiente') }}: {{ $regional->number($item->remaining_quantity, 3) }} {{ $item->unit_abbreviation }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <a href="{{ route('inventory.receipts.create', ['purchase_order_id' => $order->id]) }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Registrar entrada') }}</a>
                            </article>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-200 p-4">{{ $orders->links() }}</div>
                @else
                    <p class="p-8 text-center text-sm text-gray-500">{{ __('No hay órdenes aprobadas con cantidades pendientes de recibir.') }}</p>
                @endif
            </section>
        @endif

        <a href="{{ route('inventory.receipts.index') }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a entradas') }}</a>
    </main>
</x-app-layout>
