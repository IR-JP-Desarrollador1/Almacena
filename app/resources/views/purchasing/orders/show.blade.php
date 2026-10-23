<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Orden de compra') }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-800">{{ $purchaseOrder->reference }}</h1>
            </div>
            <a href="{{ route('purchasing.orders.index') }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a órdenes') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->all()[0] }}</div>
        @endif

        @if ($purchaseOrder->rejection_reason)
            <section class="rounded-lg border border-red-200 bg-red-50 p-5" aria-labelledby="rejection-heading">
                <h2 id="rejection-heading" class="font-semibold text-red-900">{{ __('Observaciones de rechazo') }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-red-800">{{ $purchaseOrder->rejection_reason }}</p>
                @if ($purchaseOrder->rejector)
                    <p class="mt-2 text-xs text-red-700">{{ __('Rechazada por') }} {{ trim($purchaseOrder->rejector->name.' '.$purchaseOrder->rejector->last_name) }} · {{ $regional->date($purchaseOrder->rejected_at) }}</p>
                @endif
            </section>
        @endif

        <section class="grid gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Estado') }}</p><p class="mt-1 font-semibold text-gray-900">{{ __('order_status.'.$purchaseOrder->status) }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Origen') }}</p><p class="mt-1 text-gray-800">{{ $purchaseOrder->requisition?->reference ?? __('Orden directa') }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Proveedor') }}</p><p class="mt-1 text-gray-800">{{ $purchaseOrder->supplier->legal_name }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Bodega') }}</p><p class="mt-1 text-gray-800">{{ $purchaseOrder->warehouse->branch->name }} / {{ $purchaseOrder->warehouse->name }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Cotización') }}</p><p class="mt-1 text-gray-800">{{ $purchaseOrder->quote_reference ? $purchaseOrder->quote_reference.' · '.$regional->date($purchaseOrder->quote_date) : __('Sin cotización') }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Conversión') }}</p><p class="mt-1 text-gray-800">1 {{ $purchaseOrder->quoteCurrency->code }} = {{ $regional->number($purchaseOrder->exchange_rate, 8) }} {{ $purchaseOrder->baseCurrency->code }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Creada por') }}</p><p class="mt-1 text-gray-800">{{ trim($purchaseOrder->creator->name.' '.$purchaseOrder->creator->last_name) }}</p></div>
            @if ($purchaseOrder->approver)
                <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Aprobada por') }}</p><p class="mt-1 text-gray-800">{{ trim($purchaseOrder->approver->name.' '.$purchaseOrder->approver->last_name) }} · {{ $regional->date($purchaseOrder->approved_at) }}</p></div>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Ordenado') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Recibido') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo unitario neto (:currency)', ['currency' => $purchaseOrder->quoteCurrency->code]) }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Impuesto (%)') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Marca') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Plazo de entrega (días)') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo base neto (:currency)', ['currency' => $purchaseOrder->baseCurrency->code]) }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($purchaseOrder->items as $item)
                            @php($received = $item->receiptItems->sum('quantity'))
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $item->product_code }} — {{ $item->product_name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->number($item->quantity, 3) }} {{ $item->unit_abbreviation }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->number($received, 3) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->number($item->unit_cost, 4) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->number($item->tax_rate, 4) }}%</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $item->brand ?: '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $item->delivery_days === null ? '—' : __(':days días', ['days' => $item->delivery_days]) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $regional->number($item->unit_cost_base, 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-start">
            @if ($purchaseOrder->status === 'draft' && Auth::user()->hasPermission('purchases.orders.submit') && $purchaseOrder->created_by_user_id === Auth::id())
                <form method="POST" action="{{ route('purchasing.orders.submit', $purchaseOrder) }}" class="w-full sm:w-auto">
                    @csrf
                    <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Enviar orden a aprobación') }}</button>
                </form>
            @endif
            @if ($purchaseOrder->status === 'rejected' && Auth::user()->hasPermission('purchases.orders.submit') && $purchaseOrder->created_by_user_id === Auth::id())
                <a href="{{ route('purchasing.orders.edit', $purchaseOrder) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Editar orden rechazada') }}</a>
            @endif
            @if ($purchaseOrder->status === 'pending_approval' && (Auth::user()->hasPermission('purchases.orders.approve') || Auth::user()->hasPermission('purchases.orders.approve_any')) && ($purchaseOrder->created_by_user_id !== Auth::id() || Auth::user()->hasPermission('purchases.orders.approve_any')))
                <form method="POST" action="{{ route('purchasing.orders.approve', $purchaseOrder) }}" class="w-full sm:w-auto">
                    @csrf
                    <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Aprobar orden') }}</button>
                </form>
            @endif
            @if ($purchaseOrder->status === 'pending_approval' && Auth::user()->hasPermission('purchases.orders.reject') && $purchaseOrder->created_by_user_id !== Auth::id())
                <form method="POST" action="{{ route('purchasing.orders.reject', $purchaseOrder) }}" class="w-full space-y-3 sm:flex-1">
                    @csrf
                    <div>
                        <label for="rejection_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo obligatorio para rechazar') }}</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" minlength="3" maxlength="2000" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">{{ old('rejection_reason') }}</textarea>
                    </div>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2">{{ __('Rechazar orden') }}</button>
                </form>
            @endif
            @if ($purchaseOrder->status === 'approved' && Auth::user()->hasPermission('inventory.receipts.create') && $purchaseOrder->items->contains(fn ($item) => (float) $item->receiptItems->sum('quantity') < (float) $item->quantity))
                <a href="{{ route('inventory.receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto">{{ __('Registrar entrada parcial') }}</a>
            @endif
        </section>
    </main>
</x-app-layout>
