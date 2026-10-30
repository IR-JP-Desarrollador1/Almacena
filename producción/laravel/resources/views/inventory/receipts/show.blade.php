<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Entrada').' '.$receipt->reference }}</h1>
            <a href="{{ route('inventory.receipts.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a entradas') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-5 rounded-lg bg-white p-6 shadow md:grid-cols-2">
            @if ($receipt->purchaseOrder)
                <div><p class="text-xs uppercase text-gray-500">{{ __('Orden de compra') }}</p><p class="mt-1">{{ $receipt->purchaseOrder->reference }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Proveedor') }}</p><p class="mt-1">{{ $receipt->purchaseOrder->supplier->legal_name }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Conversión de la orden') }}</p><p class="mt-1">1 {{ $receipt->purchaseOrder->quoteCurrency->code }} = {{ $regional->number($receipt->purchaseOrder->exchange_rate, 8) }} {{ $receipt->purchaseOrder->baseCurrency->code }}</p></div>
            @else
                <div><p class="text-xs uppercase text-gray-500">{{ __('Tipo de entrada') }}</p><p class="mt-1">{{ __('Entrada directa') }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Motivo de la entrada') }}</p><p class="mt-1">{{ __('receipt_reason.'.$receipt->direct_reason) }}</p></div>
            @endif
            <div><p class="text-xs uppercase text-gray-500">{{ __('Bodega') }}</p><p class="mt-1">{{ $receipt->warehouse->branch->name }} / {{ $receipt->warehouse->name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Recibida por') }}</p><p class="mt-1">{{ $receipt->receiver->name }} {{ $receipt->receiver->last_name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Fecha') }}</p><p class="mt-1">{{ $regional->dateTime($receipt->received_at) }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Documento de referencia') }}</p><p class="mt-1">{{ $receipt->document_reference ?: '—' }}</p></div>
        </section>
        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
            @php
                $quoteCurrency = $receipt->purchaseOrder?->quoteCurrency?->code ?? $receipt->items->first()?->lot?->quoteCurrency?->code;
                $baseCurrency = $receipt->purchaseOrder?->baseCurrency?->code ?? $receipt->items->first()?->lot?->baseCurrency?->code;
            @endphp
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Lote') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Cantidad') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Vencimiento') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo (:currency)', ['currency' => $quoteCurrency]) }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo base (:currency)', ['currency' => $baseCurrency]) }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($receipt->items as $item)
                        <tr><td class="px-6 py-4 text-sm">{{ $item->product->code }} — {{ $item->product->name }}</td><td class="px-6 py-4 text-sm">{{ $item->lot->lot_code }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->quantity, 3) }}</td><td class="px-6 py-4 text-sm">{{ $regional->date($item->expires_on) }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->unit_cost, 4) }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->unit_cost_base, 4) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
    </main>
</x-app-layout>
