<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar salida') }}</h1>
            <a href="{{ route('purchasing.requisitions.show', $requisition) }}" class="text-sm text-indigo-700 underline">{{ __('Volver a la requisición') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">{{ __('Requisición aprobada') }}: <strong>{{ $requisition->reference }}</strong> · {{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</p>
        </section>
        <form method="POST" action="{{ route('inventory.issues.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
            <section class="rounded-lg bg-white p-6 shadow">
                <label for="document_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia de entrega (opcional)') }}</label>
                <input id="document_reference" name="document_reference" value="{{ old('document_reference') }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </section>
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4"><h2 class="font-semibold text-gray-800">{{ __('Cantidades a despachar') }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('Se permiten entregas parciales. El sistema asigna lotes FEFO o FIFO y bloquea productos vencidos.') }}</p></div>
                <div class="divide-y divide-gray-200">
                    @foreach ($items as $index => $item)
                        <div class="grid gap-4 p-6 md:grid-cols-4 md:items-end">
                            <input type="hidden" name="items[{{ $index }}][requisition_item_id]" value="{{ $item->id }}">
                            <div><p class="text-sm font-medium">{{ $item->product_code }} — {{ $item->product_name }}</p><p class="mt-1 text-xs text-gray-500">{{ __('Pendiente') }}: {{ $regional->number($item->remaining_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</p></div>
                            <div><label for="issue-quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Disponible vigente') }}</label><p id="available-{{ $item->id }}" class="mt-1 text-sm text-gray-600">{{ $regional->number($item->available_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</p></div>
                            <div><label for="issue-quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a despachar') }}</label><input id="issue-quantity-{{ $item->id }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="{{ number_format(min($item->remaining_milli, $item->available_milli) / 1000, 3, '.', '') }}" step="0.001" value="{{ old('items.'.$index.'.quantity') }}" @disabled($item->available_milli <= 0) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><p class="text-xs text-gray-500">{{ $item->product->tracks_expiration ? __('Asignación por vencimiento próximo (FEFO), excluyendo lotes vencidos.') : __('Asignación por fecha de recepción (FIFO).') }}</p></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end p-6"><x-primary-button>{{ __('Confirmar salida') }}</x-primary-button></div>
            </section>
        </form>
    </main>
</x-app-layout>
