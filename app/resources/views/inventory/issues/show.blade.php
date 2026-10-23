<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Salida').' '.$issue->reference }}</h1>
            <a href="{{ route('inventory.issues.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a salidas') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-5 rounded-lg bg-white p-6 shadow md:grid-cols-2">
            @if ($issue->source_type === \App\Models\InventoryIssue::SOURCE_DIRECT)
                <div><p class="text-xs uppercase text-gray-500">{{ __('Tipo de salida') }}</p><p class="mt-1">{{ __('Salida directa') }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">{{ __('Motivo de la salida') }}</p><p class="mt-1">{{ __('issue_reason.'.$issue->direct_reason) }}</p></div>
            @else
                <div><p class="text-xs uppercase text-gray-500">{{ __('Requisición') }}</p><p class="mt-1">{{ $issue->requisition?->reference ?? '—' }}</p></div>
            @endif
            <div><p class="text-xs uppercase text-gray-500">{{ __('Bodega') }}</p><p class="mt-1">{{ $issue->warehouse->branch->name }} / {{ $issue->warehouse->name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Entregada por') }}</p><p class="mt-1">{{ $issue->issuer->name }} {{ $issue->issuer->last_name }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Fecha') }}</p><p class="mt-1">{{ $regional->dateTime($issue->issued_at) }}</p></div>
            <div><p class="text-xs uppercase text-gray-500">{{ __('Referencia de entrega') }}</p><p class="mt-1">{{ $issue->document_reference ?: '—' }}</p></div>
        </section>
        @foreach ($issue->items as $item)
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4"><h2 class="font-semibold text-gray-800">{{ $item->product_code }} — {{ $item->product_name }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('Cantidad despachada') }}: {{ $item->quantity }} {{ $item->unit_abbreviation }}</p></div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Lote utilizado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Cantidad del lote') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Vencimiento') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo cotizado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo base') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($item->allocations as $allocation)
                            <tr><td class="px-6 py-4 text-sm">{{ $allocation->lot->lot_code }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($allocation->quantity, 3) }} {{ $item->unit_abbreviation }}</td><td class="px-6 py-4 text-sm">{{ $regional->date($allocation->lot->expires_on) }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($allocation->unit_cost, 4) }} {{ $allocation->lot->quoteCurrency->code }}</td><td class="px-6 py-4 text-sm">{{ $regional->number($allocation->unit_cost_base, 4) }} {{ $allocation->lot->baseCurrency->code }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </section>
        @endforeach
    </main>
</x-app-layout>
