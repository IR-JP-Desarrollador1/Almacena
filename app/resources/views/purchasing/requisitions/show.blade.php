<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ ($inventoryContext ? __('Requisición de inventario') : __('Requisición')).' '.$requisition->reference }}</h1>
            <a href="{{ route($inventoryContext ? 'inventory.requisitions.index' : 'purchasing.requisitions.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a requisiciones') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="rounded-lg bg-white p-6 shadow">
            <div class="grid gap-5 md:grid-cols-2">
                <div><p class="text-xs uppercase text-gray-500">{{ __('Estado') }}</p><p class="mt-1 font-semibold">{{ __('requisition_status.'.$requisition->status) }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Área solicitante') }}</p><p class="mt-1">{{ $requisition->department->name }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Bodega de atención') }}</p><p class="mt-1">{{ $requisition->warehouse ? $requisition->warehouse->branch->name.' / '.$requisition->warehouse->name : '—' }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Solicitante') }}</p><p class="mt-1">{{ trim($requisition->requester->name.' '.$requisition->requester->last_name) }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Jefe asignado') }}</p><p class="mt-1">{{ $requisition->approver ? trim($requisition->approver->name.' '.$requisition->approver->last_name) : __('Aún no enviada') }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Fecha requerida') }}</p><p class="mt-1">{{ $regional->date($requisition->needed_by) }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Justificación') }}</p><p class="mt-1">{{ $requisition->justification }}</p></div>
                @if ($requisition->rejection_reason)
                    <div class="md:col-span-2"><p class="text-xs uppercase text-red-700">{{ __('Motivo del rechazo') }}</p><p class="mt-1 text-red-800">{{ $requisition->rejection_reason }}</p></div>
                @endif
            </div>
        </section>
        <section class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Cantidad solicitada') }}</th>@if ($inventoryContext)<th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Despachado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Saldo pendiente') }}</th>@endif<th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Notas') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requisition->items as $item)
                        @php($issuedQuantity = $item->inventoryIssueItems->sum('quantity'))
                        <tr><td class="px-6 py-4 text-sm">{{ $item->product_code }} — {{ $item->product_name }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($item->quantity, 3) }} {{ $item->unit_abbreviation }}</td>@if ($inventoryContext)<td class="px-6 py-4 text-sm">{{ $regional->number($issuedQuantity, 3) }} {{ $item->unit_abbreviation }}</td><td class="px-6 py-4 text-sm">{{ $regional->number(max(0, (float) $item->quantity - (float) $issuedQuantity), 3) }} {{ $item->unit_abbreviation }}</td>@endif<td class="px-6 py-4 text-sm">{{ $item->notes ?: '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        @php($hasPendingQuantity = $requisition->items->contains(fn ($item) => (float) $item->quantity > (float) $item->inventoryIssueItems->sum('quantity')))
        <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
            @if ($inventoryContext && Auth::user()->hasPermission('inventory.requisitions.manage') && $requisition->requester_user_id === Auth::id() && in_array($requisition->status, ['draft', 'rejected'], true) && $requisition->inventoryIssues->isEmpty())
                <a href="{{ route('inventory.requisitions.edit', $requisition) }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg border border-gray-300 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-800 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-green-700 hover:bg-green-50 hover:shadow active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Editar borrador') }}</a>
            @endif
            @if ($inventoryContext && Auth::user()->hasPermission('inventory.requisitions.manage') && $requisition->requester_user_id === Auth::id() && in_array($requisition->status, ['draft', 'rejected'], true) && $requisition->inventoryIssues->isEmpty())
                <form method="POST" action="{{ route('inventory.requisitions.activate', $requisition) }}" class="w-full sm:w-auto">@csrf<button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-green-800 hover:shadow-md active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Activar requisición') }}</button></form>
            @endif
            @if ($inventoryContext && Auth::user()->hasPermission('inventory.requisitions.submit') && $requisition->requester_user_id === Auth::id() && $requisition->status === 'active' && $hasPendingQuantity)
                <form method="POST" action="{{ route('inventory.requisitions.submit', $requisition) }}" class="w-full sm:w-auto">@csrf<button class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-green-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-green-800 hover:shadow-md active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Enviar a aprobación') }}</button></form>
            @endif
            @if ($inventoryContext && Auth::user()->hasPermission('inventory.requisitions.manage') && $requisition->requester_user_id === Auth::id() && in_array($requisition->status, ['draft', 'active', 'rejected'], true) && $requisition->inventoryIssues->isEmpty())
                <form method="POST" action="{{ route('inventory.requisitions.cancel', $requisition) }}" class="w-full sm:w-auto" onsubmit="return confirm('{{ __('¿Cancelar esta requisición? Esta acción no se puede deshacer.') }}')">@csrf<button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-800 hover:shadow-md active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 sm:w-auto">{{ __('Cancelar requisición') }}</button></form>
            @endif
            @if (! $inventoryContext && ($requisition->approver_user_id === Auth::id() || Auth::user()->hasPermission('purchases.requisitions.approve_any')) && ($requisition->requester_user_id !== Auth::id() || Auth::user()->hasPermission('purchases.requisitions.approve_any')) && $requisition->status === 'pending_approval' && (Auth::user()->hasPermission('purchases.requisitions.approve') || Auth::user()->hasPermission('purchases.requisitions.approve_any')))
                <form method="POST" action="{{ route('purchasing.requisitions.approve', $requisition) }}">@csrf<button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-800 hover:shadow focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">{{ __('Aprobar requisición') }}</button></form>
            @endif
            @if (! $inventoryContext && $requisition->approver_user_id === Auth::id() && $requisition->status === 'pending_approval' && Auth::user()->hasPermission('purchases.requisitions.reject'))
                <form method="POST" action="{{ route('purchasing.requisitions.reject', $requisition) }}" class="flex flex-wrap items-end gap-2">@csrf<div><label for="rejection_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo obligatorio para rechazar') }}</label><input id="rejection_reason" name="rejection_reason" required minlength="3" maxlength="2000" class="mt-1 rounded-md border-gray-300 shadow-sm"></div><button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-red-800 hover:shadow focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">{{ __('Rechazar') }}</button></form>
            @endif
            @if (! $inventoryContext && $requisition->status === 'approved' && Auth::user()->hasPermission('purchases.quotes.view'))
                <a href="{{ route('purchasing.quotes.index', $requisition) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-800 hover:shadow focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">{{ __('Comparar cotizaciones') }}</a>
            @endif
            @if (! $inventoryContext && $requisition->status === 'approved' && Auth::user()->hasPermission('purchases.quotes.manage'))
                <a href="{{ route('purchasing.quotes.register', $requisition) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-100">{{ __('Registrar cotización') }}</a>
            @endif
            @if ($inventoryContext && in_array($requisition->status, ['active', 'approved'], true) && Auth::user()->hasPermission('inventory.requisitions.dispatch'))
                <a href="{{ route('inventory.issues.create', ['requisition_id' => $requisition->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-800 hover:shadow focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">{{ __('Registrar salida parcial') }}</a>
            @endif
        </div>
        @php($visibleOrders = $requisition->purchaseOrders->filter(fn ($order) => Auth::user()->hasPermission('purchases.orders.view') || $order->created_by_user_id === Auth::id() || $order->approved_by_user_id === Auth::id()))
        @if ($visibleOrders->isNotEmpty())
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Órdenes de compra generadas') }}</h2>
                <ul class="mt-3 divide-y divide-gray-200">
                    @foreach ($visibleOrders as $purchaseOrder)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span class="text-sm">{{ $purchaseOrder->reference }} · {{ $purchaseOrder->supplier->legal_name }} · {{ $purchaseOrder->status === 'approved' ? __('Aprobada') : __('Borrador') }}</span>
                            <a class="text-sm text-indigo-700 underline" href="{{ route('purchasing.orders.show', $purchaseOrder) }}">{{ __('Ver') }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</x-app-layout>
