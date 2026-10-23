<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Crear entrada directa') }}</h1>
            <a href="{{ route('inventory.receipts.create') }}" class="text-sm text-indigo-700 underline">{{ __('Cambiar origen de entrada') }}</a>
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

        <form method="POST" action="{{ route('inventory.receipts.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="source_type" value="direct">

            <section class="grid gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">{{ __('Bodega') }}</label>
                    <select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Selecciona una bodega') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="direct_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo de la entrada') }}</label>
                    <select id="direct_reason" name="direct_reason" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Selecciona un motivo') }}</option>
                        @foreach ($reasons as $reason => $label)
                            <option value="{{ $reason }}" @selected(old('direct_reason') === $reason)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('direct_reason')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="document_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia del documento (opcional)') }}</label>
                    <input id="document_reference" name="document_reference" value="{{ old('document_reference') }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ __('Productos de la entrada') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('El costo se registra por unidad en la moneda base de la empresa (:currency). Los productos con control de vencimiento requieren fecha.', ['currency' => $baseCurrency->code]) }}</p>
                    </div>
                    <button type="button" data-add-direct-receipt-item class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Agregar producto') }}</button>
                </div>

                <div id="direct-receipt-items" class="divide-y divide-gray-200">
                    @foreach (old('items', [[]]) as $index => $oldItem)
                        <div class="grid gap-4 p-5 md:grid-cols-2 md:p-6" data-direct-receipt-item>
                            <div>
                                @php($selectedProduct = $products->firstWhere('id', $oldItem['product_id'] ?? null))
                                <label for="receipt-product-search-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                <div class="relative mt-1" data-product-search-widget>
                                    <input id="receipt-product-search-{{ $index }}" type="search" value="{{ $selectedProduct ? $selectedProduct->code.' — '.$selectedProduct->name.' ('.$selectedProduct->unit->abbreviation.')' : '' }}" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="receipt-product-results-{{ $index }}" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $oldItem['product_id'] ?? '' }}" data-direct-receipt-product data-product-search-value>
                                    <ul id="receipt-product-results-{{ $index }}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                        @foreach ($products as $product)
                                            <li id="receipt-product-option-{{ $index }}-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                        @endforeach
                                        <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                    </ul>
                                </div>
                                @error("items.{$index}.product_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="quantity-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad recibida') }}</label>
                                <input id="quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" max="999999999.999" value="{{ $oldItem['quantity'] ?? '' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="unit-cost-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Costo unitario (:currency)', ['currency' => $baseCurrency->code]) }}</label>
                                <input id="unit-cost-{{ $index }}" name="items[{{ $index }}][unit_cost]" type="number" min="0.0001" step="0.0001" max="99999999999.9999" value="{{ $oldItem['unit_cost'] ?? '' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.unit_cost")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div data-direct-receipt-expiration @if (!($products->firstWhere('id', $oldItem['product_id'] ?? null)?->tracks_expiration ?? false)) hidden @endif>
                                <label for="expires-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Vencimiento obligatorio') }}</label>
                                <input id="expires-{{ $index }}" name="items[{{ $index }}][expires_on]" type="date" min="{{ today()->toDateString() }}" value="{{ $oldItem['expires_on'] ?? '' }}" @required($products->firstWhere('id', $oldItem['product_id'] ?? null)?->tracks_expiration ?? false) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.expires_on")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end md:col-span-2">
                                <button type="button" data-remove-direct-receipt-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="direct-receipt-item-template">
                    <div class="grid gap-4 p-5 md:grid-cols-2 md:p-6" data-direct-receipt-item>
                        <div>
                            <label for="receipt-product-search-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                            <div class="relative mt-1" data-product-search-widget>
                                <input id="receipt-product-search-__INDEX__" type="search" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="receipt-product-results-__INDEX__" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                <input type="hidden" name="items[__INDEX__][product_id]" value="" data-direct-receipt-product data-product-search-value>
                                <ul id="receipt-product-results-__INDEX__" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                    @foreach ($products as $product)
                                        <li id="receipt-product-option-__INDEX__-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                    @endforeach
                                    <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label for="quantity-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Cantidad recibida') }}</label>
                            <input id="quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" max="999999999.999" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label for="unit-cost-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Costo unitario (:currency)', ['currency' => $baseCurrency->code]) }}</label>
                            <input id="unit-cost-__INDEX__" name="items[__INDEX__][unit_cost]" type="number" min="0.0001" step="0.0001" max="99999999999.9999" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div data-direct-receipt-expiration hidden>
                            <label for="expires-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Vencimiento obligatorio') }}</label>
                            <input id="expires-__INDEX__" name="items[__INDEX__][expires_on]" type="date" min="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="flex justify-end md:col-span-2">
                            <button type="button" data-remove-direct-receipt-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                        </div>
                    </div>
                </template>
                @error('items')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('items.*')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 sm:p-6">
                    <a href="{{ route('inventory.receipts.create') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Cancelar') }}</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Confirmar entrada') }}</button>
                </div>
            </section>
        </form>
    </main>
</x-app-layout>
