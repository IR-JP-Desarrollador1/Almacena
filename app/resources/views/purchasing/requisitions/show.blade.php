<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Requisición').' '.$requisition->reference }}</h1>
            <a href="{{ route('purchasing.requisitions.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a requisiciones') }}</a>
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
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Cantidad') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Notas') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requisition->items as $item)
                        <tr><td class="px-6 py-4 text-sm">{{ $item->product_code }} — {{ $item->product_name }}</td><td class="px-6 py-4 text-sm">{{ $item->quantity }} {{ $item->unit_abbreviation }}</td><td class="px-6 py-4 text-sm">{{ $item->notes ?: '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <div class="flex flex-wrap items-start gap-3">
            @if ($requisition->requester_user_id === Auth::id() && in_array($requisition->status, ['draft', 'rejected'], true))
                <a href="{{ route('purchasing.requisitions.edit', $requisition) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Editar borrador') }}</a>
            @endif
            @if ($requisition->requester_user_id === Auth::id() && $requisition->status === 'draft')
                <form method="POST" action="{{ route('purchasing.requisitions.submit', $requisition) }}">@csrf<button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Enviar a aprobación') }}</button></form>
            @endif
            @if ($requisition->approver_user_id === Auth::id() && $requisition->status === 'pending_approval' && Auth::user()->hasPermission('purchases.requisitions.decide'))
                <form method="POST" action="{{ route('purchasing.requisitions.approve', $requisition) }}">@csrf<button class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white">{{ __('Aprobar requisición') }}</button></form>
                <form method="POST" action="{{ route('purchasing.requisitions.reject', $requisition) }}" class="flex flex-wrap items-end gap-2">@csrf<div><label for="rejection_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo obligatorio para rechazar') }}</label><input id="rejection_reason" name="rejection_reason" required minlength="3" maxlength="2000" class="mt-1 rounded-md border-gray-300 shadow-sm"></div><button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white">{{ __('Rechazar') }}</button></form>
            @endif
            @if ($requisition->status === 'approved' && !$requisition->purchaseOrder && Auth::user()->hasPermission('purchases.orders.create'))
                <a href="{{ route('purchasing.orders.create', ['requisition_id' => $requisition->id]) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear orden de compra') }}</a>
            @elseif ($requisition->purchaseOrder && (Auth::user()->hasPermission('purchases.orders.view') || $requisition->purchaseOrder->created_by_user_id === Auth::id()))
                <a href="{{ route('purchasing.orders.show', $requisition->purchaseOrder) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Ver orden de compra') }}</a>
            @endif
            @if ($requisition->status === 'approved' && Auth::user()->hasPermission('inventory.issues.create'))
                <a href="{{ route('inventory.issues.create', ['requisition_id' => $requisition->id]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Registrar salida parcial') }}</a>
            @endif
        </div>
    </main>
</x-app-layout>
