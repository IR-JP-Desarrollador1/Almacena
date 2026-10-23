<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar salida') }}</h1>
            <a href="{{ route('inventory.requisitions.show', $requisition) }}" class="text-sm text-indigo-700 underline">{{ __('Volver a la requisición') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">{{ $requisition->status === \App\Models\Requisition::STATUS_ACTIVE ? __('Requisición activa para despacho') : __('Requisición aprobada') }}: <strong>{{ $requisition->reference }}</strong> · {{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</p>
            @if ($requisition->status === \App\Models\Requisition::STATUS_ACTIVE)
                <p class="mt-2 text-sm text-gray-500">{{ __('Puedes despachar esta requisición sin enviarla a Compras; el saldo restante puede mantenerse para despacho o enviarse posteriormente a aprobación.') }}</p>
            @endif
        </section>
        <form method="POST" action="{{ route('inventory.issues.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
            <section class="rounded-lg bg-white p-6 shadow">
                <label for="document_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia de entrega (opcional)') }}</label>
                <input id="document_reference" name="document_reference" value="{{ old('document_reference') }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </section>
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4"><h2 class="font-semibold text-gray-800">{{ __('Cantidades a despachar') }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('Se permiten entregas parciales. El sistema asigna lotes FEFO o FIFO y bloquea productos vencidos.') }} {{ __('Marca solo los productos que entregarás en esta salida.') }}</p></div>
                <div class="divide-y divide-gray-200">
                    @foreach ($items as $index => $item)
                        <div x-data="{ selected: {{ (string) old('items.'.$index.'.requisition_item_id') === (string) $item->id ? 'true' : 'false' }} }" class="grid gap-4 p-6 md:grid-cols-5 md:items-end">
                            <div class="md:col-span-2">
                                <label class="flex items-start gap-2 text-sm font-medium text-gray-800">
                                    <input type="checkbox" name="items[{{ $index }}][requisition_item_id]" value="{{ $item->id }}" x-model="selected" @disabled($item->available_milli <= 0) @checked((string) old('items.'.$index.'.requisition_item_id') === (string) $item->id) aria-label="{{ __('Incluir producto :product en esta salida', ['product' => $item->product_name]) }}" class="mt-1 rounded border-gray-300 text-green-700 focus:ring-green-600">
                                    <span>{{ $item->product_code }} — {{ $item->product_name }}<span class="mt-1 block text-xs font-normal text-gray-500">{{ __('Pendiente') }}: {{ $regional->number($item->remaining_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</span></span>
                                </label>
                            </div>
                            <div><label for="issue-quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Disponible vigente') }}</label><p id="available-{{ $item->id }}" class="mt-1 text-sm text-gray-600">{{ $regional->number($item->available_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</p></div>
                            <div><label for="issue-quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a despachar') }}</label><input id="issue-quantity-{{ $item->id }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="{{ number_format(min($item->remaining_milli, $item->available_milli) / 1000, 3, '.', '') }}" step="0.001" value="{{ old('items.'.$index.'.quantity') }}" x-bind:disabled="!selected" x-bind:required="selected" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><p class="text-xs text-gray-500">{{ $item->product->tracks_expiration ? __('Asignación por vencimiento próximo (FEFO), excluyendo lotes vencidos.') : __('Asignación por fecha de recepción (FIFO).') }}</p></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end p-6"><x-primary-button>{{ __('Confirmar salida') }}</x-primary-button></div>
            </section>
        </form>
    </main>
</x-app-layout>
