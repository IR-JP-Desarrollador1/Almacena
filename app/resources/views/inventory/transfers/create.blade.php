<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar traslado') }}</h1>
            <a href="{{ route('inventory.issues.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a movimientos') }}</a>
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
            {{ __('Registra un movimiento entre bodegas autorizadas. La bodega destino debe ser distinta y la cantidad debe estar disponible en la bodega origen.') }}
        </div>

        <form method="POST" action="{{ route('inventory.transfers.store') }}" class="space-y-5">
            @csrf

            <section class="grid gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <label for="origin_warehouse_id" class="block text-sm font-medium text-gray-700">{{ __('Bodega origen') }}</label>
                    <select id="origin_warehouse_id" name="origin_warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Selecciona una bodega') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('origin_warehouse_id') == $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('origin_warehouse_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="destination_warehouse_id" class="block text-sm font-medium text-gray-700">{{ __('Bodega destino') }}</label>
                    <select id="destination_warehouse_id" name="destination_warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Selecciona una bodega') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('destination_warehouse_id') == $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('destination_warehouse_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="document_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia del documento (opcional)') }}</label>
                    <input id="document_reference" name="document_reference" value="{{ old('document_reference') }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('document_reference')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ __('Productos del traslado') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('La cantidad debe estar disponible en la bodega origen.') }}</p>
                    </div>
                    <button type="button" onclick="addRow()" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Agregar producto') }}</button>
                </div>

                <div id="transfer-items" class="divide-y divide-gray-200">
                    @foreach (old('items', [[]]) as $index => $oldItem)
                        @php($selectedProduct = $products->firstWhere('id', $oldItem['product_id'] ?? null))
                        <div class="item-row grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6">
                            <div class="md:col-span-2">
                                <label for="transfer-product-search-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                <div class="relative mt-1" data-product-search-widget>
                                    <input id="transfer-product-search-{{ $index }}" type="search" value="{{ $selectedProduct ? $selectedProduct->code.' — '.$selectedProduct->name.' ('.$selectedProduct->unit->abbreviation.')' : '' }}" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="transfer-product-results-{{ $index }}" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $oldItem['product_id'] ?? '' }}" data-product-search-value>
                                    <ul id="transfer-product-results-{{ $index }}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                        @foreach ($products as $product)
                                            <li id="transfer-product-option-{{ $index }}-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                        @endforeach
                                        <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                    </ul>
                                </div>
                                @error("items.{$index}.product_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="transfer-quantity-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a trasladar') }}</label>
                                <input id="transfer-quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" value="{{ $oldItem['quantity'] ?? '' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end md:col-span-2">
                                <button type="button" class="remove-row text-sm font-medium text-red-700 underline" aria-label="{{ __('Quitar producto') }}">{{ __('Quitar producto') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 sm:p-6">
                    <a href="{{ route('inventory.issues.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Cancelar') }}</a>
                    <x-primary-button>{{ __('Confirmar traslado') }}</x-primary-button>
                </div>
            </section>
        </form>
    </main>

    @php($transferItems = old('items', [[]]))
    @php($nextItemIndex = count($transferItems) > 0 ? max(array_map('intval', array_keys($transferItems))) + 1 : 0)
    <script>
        let rowIndex = @json($nextItemIndex);

        function validateWarehouseSelection() {
            const origin = document.getElementById('origin_warehouse_id');
            const destination = document.getElementById('destination_warehouse_id');
            const selectedOrigin = Number(origin.value);
            const selectedDestination = Number(destination.value);

            if (selectedOrigin && selectedDestination && selectedOrigin === selectedDestination) {
                destination.setCustomValidity('{{ __('La bodega destino debe ser distinta a la bodega origen.') }}');
                destination.reportValidity();
                return;
            }

            destination.setCustomValidity('');
        }

        function updateRemoveButtons() {
            const rows = document.querySelectorAll('#transfer-items .item-row');
            rows.forEach((row) => {
                const button = row.querySelector('.remove-row');
                button.hidden = rows.length <= 1;
            });
        }

        document.getElementById('origin_warehouse_id').addEventListener('change', validateWarehouseSelection);
        document.getElementById('destination_warehouse_id').addEventListener('change', validateWarehouseSelection);
        document.getElementById('transfer-items').addEventListener('click', (event) => {
            if (!event.target.matches('.remove-row')) {
                return;
            }

            event.target.closest('.item-row').remove();
            updateRemoveButtons();
        });

        function addRow() {
            const container = document.getElementById('transfer-items');
            const template = document.createElement('div');
            template.className = 'item-row grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6';
            template.innerHTML = `
                <div class="md:col-span-2">
                    <label for="transfer-product-search-${rowIndex}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                    <div class="relative mt-1" data-product-search-widget>
                        <input id="transfer-product-search-${rowIndex}" type="search" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="transfer-product-results-${rowIndex}" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                        <input type="hidden" name="items[${rowIndex}][product_id]" value="" data-product-search-value>
                        <ul id="transfer-product-results-${rowIndex}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                            @foreach ($products as $product)
                                <li id="transfer-product-option-${rowIndex}-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                            @endforeach
                            <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                        </ul>
                    </div>
                </div>
                <div>
                    <label for="transfer-quantity-${rowIndex}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a trasladar') }}</label>
                    <input id="transfer-quantity-${rowIndex}" name="items[${rowIndex}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="flex justify-end md:col-span-2">
                    <button type="button" class="remove-row text-sm font-medium text-red-700 underline" aria-label="{{ __('Quitar producto') }}">{{ __('Quitar producto') }}</button>
                </div>
            `;
            container.appendChild(template);
            rowIndex += 1;
            updateRemoveButtons();
            template.querySelector('[data-product-search-input]').focus();
        }

        updateRemoveButtons();
    </script>
</x-app-layout>
