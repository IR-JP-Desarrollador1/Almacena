<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar entrada') }}</h1>
            <a href="{{ route('purchasing.orders.show', $purchaseOrder) }}" class="text-sm text-indigo-700 underline">{{ __('Volver a la orden') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">{{ __('Orden aprobada') }}: <strong>{{ $purchaseOrder->reference }}</strong> · {{ $purchaseOrder->supplier->legal_name }} · {{ $purchaseOrder->warehouse->name }}</p>
        </section>
        <form method="POST" action="{{ route('inventory.receipts.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="source_type" value="purchase_order">
            <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
            <section class="rounded-lg bg-white p-6 shadow">
                <label for="document_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia del documento de recepción (opcional)') }}</label>
                <input id="document_reference" name="document_reference" value="{{ old('document_reference') }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </section>
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4"><h2 class="font-semibold text-gray-800">{{ __('Cantidades recibidas') }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('Puedes recibir parcialmente. No se admiten excedentes; el costo procede de la orden aprobada.') }}</p></div>
                <div class="divide-y divide-gray-200">
                    @foreach ($items as $index => $item)
                        <div class="grid gap-4 p-6 md:grid-cols-4 md:items-end">
                            <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                            <div><p class="text-sm font-medium">{{ $item->product_code }} — {{ $item->product_name }}</p><p class="mt-1 text-xs text-gray-500">{{ __('Pendiente') }}: {{ $regional->number($item->remaining_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</p></div>
                            <div><label for="quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a recibir') }}</label><input id="quantity-{{ $item->id }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="{{ number_format($item->remaining_milli / 1000, 3, '.', '') }}" step="0.001" value="{{ old('items.'.$index.'.quantity') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            @if ($item->product->tracks_expiration)
                                <div><label for="expires-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Vencimiento obligatorio') }}</label><input id="expires-{{ $item->id }}" name="items[{{ $index }}][expires_on]" type="date" min="{{ today()->toDateString() }}" value="{{ old('items.'.$index.'.expires_on') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><p class="mt-1 text-xs text-gray-500">{{ __('Formato regional: :date', ['date' => $regional->date('2026-08-17')]) }}</p></div>
                            @else
                                <div><p class="text-sm text-gray-500">{{ __('Este producto no controla vencimiento.') }}</p></div>
                            @endif
                            <div><p class="text-sm text-gray-600">{{ __('Costo unitario') }}: {{ $regional->number($item->unit_cost, 4) }}</p></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end p-6"><x-primary-button>{{ __('Confirmar entrada') }}</x-primary-button></div>
            </section>
        </form>
    </main>
</x-app-layout>
