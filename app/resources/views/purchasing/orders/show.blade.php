<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Orden de compra').' '.$purchaseOrder->reference }}</h1>
            <a href="{{ route('purchasing.orders.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a órdenes') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="grid gap-5 rounded-lg bg-white p-6 shadow md:grid-cols-2">
            <div><p class="text-xs uppercase text-gray-500">{{ __('Estado') }}</p><p class="mt-1 font-semibold">{{ $purchaseOrder->status === 'approved' ? __('Aprobada') : __('Borrador') }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Requisición') }}</p><p class="mt-1">{{ $purchaseOrder->requisition->reference }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Proveedor') }}</p><p class="mt-1">{{ $purchaseOrder->supplier->legal_name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Bodega') }}</p><p class="mt-1">{{ $purchaseOrder->requisition->warehouse->branch->name }} / {{ $purchaseOrder->requisition->warehouse->name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Cotización') }}</p><p class="mt-1">{{ $purchaseOrder->quote_reference }} · {{ $regional->date($purchaseOrder->quote_date) }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Conversión') }}</p><p class="mt-1">1 {{ $purchaseOrder->quoteCurrency->code }} = {{ $regional->number($purchaseOrder->exchange_rate, 8) }} {{ $purchaseOrder->baseCurrency->code }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Creada por') }}</p><p class="mt-1">{{ $purchaseOrder->creator->name }} {{ $purchaseOrder->creator->last_name }}</p></div>
            @if ($purchaseOrder->approver)
                <div><p class="text-xs uppercase text-gray-500">{{ __('Aprobada por') }}</p><p class="mt-1">{{ $purchaseOrder->approver->name }} {{ $purchaseOrder->approver->last_name }}</p></div>
            @endif
        </section>
        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Ordenado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Recibido') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo unitario (:currency)', ['currency' => $purchaseOrder->quoteCurrency->code]) }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo base (:currency)', ['currency' => $purchaseOrder->baseCurrency->code]) }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($purchaseOrder->items as $item)
                        @php($received = $item->receiptItems->sum('quantity'))
                        <tr><td class="px-6 py-4 text-sm">{{ $item->product_code }} — {{ $item->product_name }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->quantity, 3) }} {{ $item->unit_abbreviation }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($received, 3) }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->unit_cost, 4) }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->unit_cost_base, 4) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
        <div class="flex flex-wrap gap-3">
            @if ($purchaseOrder->status === 'draft' && Auth::user()->hasPermission('purchases.orders.approve') && $purchaseOrder->created_by_user_id !== Auth::id())
                <form method="POST" action="{{ route('purchasing.orders.approve', $purchaseOrder) }}">@csrf<button class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white">{{ __('Aprobar orden') }}</button></form>
            @endif
            @if ($purchaseOrder->status === 'approved' && Auth::user()->hasPermission('inventory.receipts.create') && $purchaseOrder->items->contains(fn ($item) => (float) $item->receiptItems->sum('quantity') < (float) $item->quantity))
                <a href="{{ route('inventory.receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Registrar entrada parcial') }}</a>
            @endif
        </div>
    </main>
</x-app-layout>
