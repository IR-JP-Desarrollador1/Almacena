<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ $count->exists ? __('Editar conteo') : __('Crear conteo de existencias') }}</h1>
            <a href="{{ $count->exists ? route('inventory.counts.show', $count) : route('inventory.counts.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a conteos') }}</a>
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
            {{ __('Agrega solo los productos contados en esta bodega. Si no encuentras unidades físicas de un producto, registra 0. Los ajustes se aplicarán después de la aprobación.') }}
        </div>

        <form method="POST" action="{{ $count->exists ? route('inventory.counts.update', $count) : route('inventory.counts.store') }}" data-admin-form-title="{{ $count->exists ? __('Editar conteo') : __('Crear conteo de existencias') }}" class="space-y-5">
            @csrf
            @if ($count->exists)
                @method('PUT')
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="max-w-2xl">
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">{{ __('Bodega') }}</label>
                    <select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Selecciona una bodega') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $count->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ __('Productos contados') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('La cantidad puede ser 0. Los productos no agregados al conteo no se ajustan.') }}</p>
                    </div>
                    <button type="button" id="add-count-item" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Agregar producto') }}</button>
                </div>

                <div id="count-items" class="divide-y divide-gray-200">
                    @foreach ($items as $index => $item)
                        @php($itemData = is_array($item) ? $item : $item->toArray())
                        @php($selectedProduct = $products->firstWhere('id', $itemData['product_id'] ?? null))
                        <div class="count-item grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6" data-count-item-index="{{ $index }}">
                            <div class="md:col-span-3">
                                <label for="count-product-search-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                <div class="relative mt-1" data-product-search-widget>
                                    <input id="count-product-search-{{ $index }}" type="search" value="{{ $selectedProduct ? $selectedProduct->code.' — '.$selectedProduct->name.' ('.$selectedProduct->unit->abbreviation.')' : '' }}" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="count-product-results-{{ $index }}" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $itemData['product_id'] ?? '' }}" data-product-search-value>
                                    <ul id="count-product-results-{{ $index }}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                        @foreach ($products as $product)
                                            <li id="count-product-option-{{ $index }}-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                        @endforeach
                                        <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                    </ul>
                                </div>
                                @error("items.{$index}.product_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="count-quantity-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad contada') }}</label>
                                <input id="count-quantity-{{ $index }}" name="items[{{ $index }}][counted_quantity]" type="number" min="0" max="999999999.999" step="0.001" value="{{ $itemData['counted_quantity'] ?? '' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.counted_quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end md:col-span-5">
                                <button type="button" class="remove-count-item text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 sm:p-6">
                    <a data-admin-form-close href="{{ $count->exists ? route('inventory.counts.show', $count) : route('inventory.counts.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Cancelar') }}</a>
                    <x-primary-button>{{ __('Guardar borrador') }}</x-primary-button>
                </div>
            </section>
            <template data-count-item-template>
                <div class="count-item grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6" data-count-item-index="__INDEX__">
                    <div class="md:col-span-3">
                        <label for="count-product-search-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                        <div class="relative mt-1" data-product-search-widget>
                            <input id="count-product-search-__INDEX__" type="search" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="count-product-results-__INDEX__" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                            <input type="hidden" name="items[__INDEX__][product_id]" value="" data-product-search-value>
                            <ul id="count-product-results-__INDEX__" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                @foreach ($products as $product)
                                    <li id="count-product-option-__INDEX__-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                @endforeach
                                <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div>
                        <label for="count-quantity-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Cantidad contada') }}</label>
                        <input id="count-quantity-__INDEX__" name="items[__INDEX__][counted_quantity]" type="number" min="0" max="999999999.999" step="0.001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="flex justify-end md:col-span-5">
                        <button type="button" class="remove-count-item text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                    </div>
                </div>
            </template>
        </form>
    </main>
</x-app-layout>
