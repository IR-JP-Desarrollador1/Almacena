<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Crear salida directa') }}</h1>
            <a href="{{ route('inventory.issues.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a salidas') }}</a>
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
            {{ __('La salida descuenta existencias al confirmarse y no requiere aprobación adicional. Registra solo consumos internos o pérdidas/daños autorizados.') }}
        </div>

        <script id="direct-issue-availability" type="application/json">@json($availableQuantities)</script>
        <form method="POST" action="{{ route('inventory.issues.store') }}" class="space-y-5" data-decimal-separator="{{ $regional->decimalSeparator }}" data-thousands-separator="{{ $regional->thousandsSeparator }}">
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
                    <label for="direct_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo de la salida') }}</label>
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
                    @error('document_reference')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ __('Productos de la salida') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Selecciona productos del catálogo activo. La salida se distribuye entre lotes FEFO o FIFO y no puede superar las existencias disponibles.') }}</p>
                    </div>
                    <button type="button" data-add-direct-issue-item class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Agregar producto') }}</button>
                </div>

                <div id="direct-issue-items" class="divide-y divide-gray-200" data-availability-source="direct-issue-availability">
                    @foreach (old('items', [[]]) as $index => $oldItem)
                        @php($selectedProduct = $products->firstWhere('id', $oldItem['product_id'] ?? null))
                        <div class="grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6" data-direct-issue-item>
                            <div class="md:col-span-2">
                                <label for="issue-product-search-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                <div class="relative mt-1" data-product-search-widget>
                                    <input id="issue-product-search-{{ $index }}" type="search" value="{{ $selectedProduct ? $selectedProduct->code.' — '.$selectedProduct->name.' ('.$selectedProduct->unit->abbreviation.')' : '' }}" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="issue-product-results-{{ $index }}" data-direct-issue-product-search data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $oldItem['product_id'] ?? '' }}" data-direct-issue-product data-product-search-value>
                                    <ul id="issue-product-results-{{ $index }}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                        @foreach ($products as $product)
                                            <li id="issue-product-option-{{ $index }}-{{ $product->id }}" role="option" aria-selected="false" data-direct-issue-option data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-unit="{{ $product->unit->abbreviation }}" data-allocation-note="{{ $product->tracks_expiration ? __('Asignación por vencimiento próximo (FEFO), excluyendo lotes vencidos.') : __('Asignación por fecha de recepción (FIFO).') }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                        @endforeach
                                        <li role="presentation" data-direct-issue-no-results data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                    </ul>
                                </div>
                                @error("items.{$index}.product_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Disponible vigente') }}</label>
                                <p data-direct-issue-available class="mt-1 text-sm text-gray-600">0{{ $regional->decimalSeparator }}000</p>
                            </div>
                            <div>
                                <label for="issue-quantity-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a despachar') }}</label>
                                <input id="issue-quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" max="0" value="{{ $oldItem['quantity'] ?? '' }}" disabled data-direct-issue-quantity class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$index}.quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <p data-direct-issue-allocation-note data-empty-note="{{ __('Selecciona un producto para consultar la regla de asignación de lotes.') }}" class="text-xs text-gray-500">{{ __('Selecciona un producto para consultar la regla de asignación de lotes.') }}</p>
                            </div>
                            <div class="flex justify-end md:col-span-5">
                                <button type="button" data-remove-direct-issue-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="direct-issue-item-template">
                    <div class="grid gap-4 p-5 md:grid-cols-5 md:items-end md:p-6" data-direct-issue-item>
                        <div class="md:col-span-2">
                            <label for="issue-product-search-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                            <div class="relative mt-1" data-product-search-widget>
                                <input id="issue-product-search-__INDEX__" type="search" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="issue-product-results-__INDEX__" data-direct-issue-product-search data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                <input type="hidden" name="items[__INDEX__][product_id]" value="" data-direct-issue-product data-product-search-value>
                                <ul id="issue-product-results-__INDEX__" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                    @foreach ($products as $product)
                                        <li id="issue-product-option-__INDEX__-{{ $product->id }}" role="option" aria-selected="false" data-direct-issue-option data-product-search-option data-product-id="{{ $product->id }}" data-tracks-expiration="{{ $product->tracks_expiration ? 'true' : 'false' }}" data-unit="{{ $product->unit->abbreviation }}" data-allocation-note="{{ $product->tracks_expiration ? __('Asignación por vencimiento próximo (FEFO), excluyendo lotes vencidos.') : __('Asignación por fecha de recepción (FIFO).') }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                    @endforeach
                                    <li role="presentation" data-direct-issue-no-results data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Disponible vigente') }}</label>
                            <p data-direct-issue-available class="mt-1 text-sm text-gray-600">0{{ $regional->decimalSeparator }}000</p>
                        </div>
                        <div>
                            <label for="issue-quantity-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Cantidad a despachar') }}</label>
                            <input id="issue-quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" max="0" disabled data-direct-issue-quantity class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <p data-direct-issue-allocation-note class="text-xs text-gray-500" data-empty-note="{{ __('Selecciona un producto para consultar la regla de asignación de lotes.') }}">{{ __('Selecciona un producto para consultar la regla de asignación de lotes.') }}</p>
                        </div>
                        <div class="flex justify-end md:col-span-5">
                            <button type="button" data-remove-direct-issue-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                        </div>
                    </div>
                </template>
                @error('items')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('items.*')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 sm:p-6">
                    <a href="{{ route('inventory.issues.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Cancelar') }}</a>
                    <x-primary-button>{{ __('Confirmar salida') }}</x-primary-button>
                </div>
            </section>
        </form>
    </main>
</x-app-layout>
