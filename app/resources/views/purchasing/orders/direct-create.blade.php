@php
    $orderItems = old('items');

    if ($orderItems === null) {
        $orderItems = $mode === 'requisition' && $requisition
            ? $items->map(fn ($item) => [
                'requisition_item_id' => (string) $item->id,
                'quantity' => $item->remaining_quantity,
                'unit_cost' => '',
                'tax_rate' => '0',
                'brand' => '',
                'delivery_days' => '',
            ])->all()
            : [[]];
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1>{{ __('Crear orden directa') }}</h1>
            </div>
            <a href="{{ route('purchasing.orders.direct.create') }}" class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-indigo-700 underline underline-offset-4 hover:text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2">{{ __('Cambiar modalidad') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($mode === 'requisition' && ! $requisition)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-gray-800">{{ __('Requisiciones aprobadas') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Selecciona una requisición con productos pendientes de compra y sin orden generada para esos productos.') }}</p>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse ($requisitions as $candidate)
                        <a href="{{ route('purchasing.orders.direct.create', ['mode' => 'requisition', 'requisition_id' => $candidate->id]) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50 sm:px-6">
                            <span>
                                <span class="block font-medium text-gray-900">{{ $candidate->reference }}</span>
                                <span class="mt-1 block text-sm text-gray-600">{{ $candidate->department->name }} · {{ $candidate->warehouse->branch->name }} / {{ $candidate->warehouse->name }}</span>
                            </span>
                            <span class="text-sm font-semibold text-indigo-700">{{ __('Crear orden') }} →</span>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-gray-500">{{ __('No hay requisiciones aprobadas con saldo pendiente para una orden directa.') }}</p>
                    @endforelse
                </div>
                @if ($requisitions->hasPages())
                    <div class="border-t border-gray-200 p-4">{{ $requisitions->links() }}</div>
                @endif
            </section>
        @else
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($requisition)
                <section class="rounded-lg border border-indigo-200 bg-indigo-50 p-5">
                    <p class="text-sm font-semibold text-indigo-950">{{ __('Requisición aprobada') }}: {{ $requisition->reference }}</p>
                    <p class="mt-1 text-sm text-indigo-900">{{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</p>
                </section>
            @endif

            <form method="POST" action="{{ route('purchasing.orders.direct.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="mode" value="{{ $mode }}">
                @if ($requisition)
                    <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
                @endif

                <section class="grid gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                    @if ($mode === 'standalone')
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
                    @endif
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">{{ __('Proveedor') }}</label>
                        <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">{{ __('Selecciona un proveedor') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->legal_name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="quote_currency_id" class="block text-sm font-medium text-gray-700">{{ __('Moneda de la orden') }}</label>
                        <select id="quote_currency_id" name="quote_currency_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" @selected((string) old('quote_currency_id', $baseCurrency->id) === (string) $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        @error('quote_currency_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="exchange_rate" class="block text-sm font-medium text-gray-700">{{ __('Tasa de cambio') }}</label>
                        <input id="exchange_rate" name="exchange_rate" type="number" min="0.00000001" max="999999999.99999999" step="0.00000001" value="{{ old('exchange_rate', '1.00000000') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Los costos se convierten a la moneda base de la empresa (:currency).', ['currency' => $baseCurrency->code]) }}</p>
                        @error('exchange_rate')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 md:col-span-2">
                        {{ __('La orden se guardará como borrador y debe enviarse a aprobación. Solo después de aprobarla se podrá registrar una entrada de inventario.') }}
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                        <div>
                            <h2 class="font-semibold text-gray-800">{{ __('Productos de la orden') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Registra cantidad, costo e impuestos por producto. La moneda y tasa aplican a toda la orden.') }}</p>
                        </div>
                        <button type="button" data-add-direct-order-item class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Agregar producto') }}</button>
                    </div>
                    <div data-direct-order-items class="divide-y divide-gray-200">
                        @foreach ($orderItems as $index => $oldItem)
                            <div class="grid gap-4 p-5 md:grid-cols-2 md:p-6" data-direct-order-item>
                                <div>
                                    @if ($mode === 'requisition')
                                        <label for="requisition-item-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Producto de la requisición') }}</label>
                                        <select id="requisition-item-{{ $index }}" name="items[{{ $index }}][requisition_item_id]" required data-order-item-select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            <option value="">{{ __('Selecciona un producto') }}</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}" data-max-quantity="{{ $item->remaining_quantity }}" @selected((string) ($oldItem['requisition_item_id'] ?? '') === (string) $item->id)>{{ $item->product_code }} — {{ $item->product_name }} · {{ __('Saldo para comprar: :quantity :unit', ['quantity' => $item->remaining_quantity, 'unit' => $item->unit_abbreviation]) }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php($selectedProduct = $products->firstWhere('id', $oldItem['product_id'] ?? null))
                                        <label for="order-product-search-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                        <div class="relative mt-1" data-product-search-widget>
                                            <input id="order-product-search-{{ $index }}" type="search" value="{{ $selectedProduct ? $selectedProduct->code.' — '.$selectedProduct->name.' ('.$selectedProduct->unit->abbreviation.')' : '' }}" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="order-product-results-{{ $index }}" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                            <input id="order-product-value-{{ $index }}" type="hidden" name="items[{{ $index }}][product_id]" value="{{ $oldItem['product_id'] ?? '' }}" data-order-item-select data-product-search-value>
                                            <ul id="order-product-results-{{ $index }}" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                                @foreach ($products as $product)
                                                    <li id="order-product-option-{{ $index }}-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                                @endforeach
                                                <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                            </ul>
                                        </div>
                                    @endif
                                    @error("items.{$index}.product_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                    @error("items.{$index}.requisition_item_id")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="quantity-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad ordenada') }}</label>
                                    <input id="quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="{{ $mode === 'requisition' ? '999999999.999' : '999999999.999' }}" step="0.001" value="{{ $oldItem['quantity'] ?? '' }}" required data-order-item-quantity class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @error("items.{$index}.quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="unit-cost-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Costo unitario neto') }}</label>
                                    <input id="unit-cost-{{ $index }}" name="items[{{ $index }}][unit_cost]" type="number" min="0" max="99999999999.9999" step="0.0001" value="{{ $oldItem['unit_cost'] ?? '' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @error("items.{$index}.unit_cost")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-rate-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Impuesto (%)') }}</label>
                                    <input id="tax-rate-{{ $index }}" name="items[{{ $index }}][tax_rate]" type="number" min="0" max="100" step="0.0001" value="{{ $oldItem['tax_rate'] ?? '0' }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @error("items.{$index}.tax_rate")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="brand-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Marca (opcional)') }}</label>
                                    <input id="brand-{{ $index }}" name="items[{{ $index }}][brand]" maxlength="100" value="{{ $oldItem['brand'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div>
                                    <label for="delivery-days-{{ $index }}" class="block text-sm font-medium text-gray-700">{{ __('Plazo de entrega (días, opcional)') }}</label>
                                    <input id="delivery-days-{{ $index }}" name="items[{{ $index }}][delivery_days]" type="number" min="0" max="3650" value="{{ $oldItem['delivery_days'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="flex justify-end md:col-span-2">
                                    <button type="button" data-remove-direct-order-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <template id="direct-order-item-template">
                        <div class="grid gap-4 p-5 md:grid-cols-2 md:p-6" data-direct-order-item>
                            <div>
                                @if ($mode === 'requisition')
                                    <label for="requisition-item-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Producto de la requisición') }}</label>
                                    <select id="requisition-item-__INDEX__" name="items[__INDEX__][requisition_item_id]" required data-order-item-select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="">{{ __('Selecciona un producto') }}</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}" data-max-quantity="{{ $item->remaining_quantity }}">{{ $item->product_code }} — {{ $item->product_name }} · {{ __('Saldo para comprar: :quantity :unit', ['quantity' => $item->remaining_quantity, 'unit' => $item->unit_abbreviation]) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <label for="order-product-search-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Buscar producto') }}</label>
                                    <div class="relative mt-1" data-product-search-widget>
                                        <input id="order-product-search-__INDEX__" type="search" placeholder="{{ __('Escribe el código o nombre del producto') }}" autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="order-product-results-__INDEX__" data-product-search-input data-selection-error="{{ __('Selecciona un producto de las coincidencias.') }}" class="block w-full rounded-md border-gray-300 shadow-sm">
                                        <input id="order-product-value-__INDEX__" type="hidden" name="items[__INDEX__][product_id]" value="" data-order-item-select data-product-search-value>
                                        <ul id="order-product-results-__INDEX__" role="listbox" hidden class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                            @foreach ($products as $product)
                                                <li id="order-product-option-__INDEX__-{{ $product->id }}" role="option" aria-selected="false" data-product-search-option data-product-id="{{ $product->id }}" data-search-text="{{ $product->code }} {{ $product->name }} {{ $product->unit->abbreviation }}" class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-green-50">{{ $product->code }} — {{ $product->name }} ({{ $product->unit->abbreviation }})</li>
                                            @endforeach
                                            <li role="presentation" data-product-search-no-results hidden class="px-3 py-2 text-sm text-gray-500">{{ __('No hay coincidencias.') }}</li>
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            <div><label for="quantity-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Cantidad ordenada') }}</label><input id="quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" required data-order-item-quantity class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><label for="unit-cost-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Costo unitario neto') }}</label><input id="unit-cost-__INDEX__" name="items[__INDEX__][unit_cost]" type="number" min="0" max="99999999999.9999" step="0.0001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><label for="tax-rate-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Impuesto (%)') }}</label><input id="tax-rate-__INDEX__" name="items[__INDEX__][tax_rate]" type="number" min="0" max="100" step="0.0001" value="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><label for="brand-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Marca (opcional)') }}</label><input id="brand-__INDEX__" name="items[__INDEX__][brand]" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div><label for="delivery-days-__INDEX__" class="block text-sm font-medium text-gray-700">{{ __('Plazo de entrega (días, opcional)') }}</label><input id="delivery-days-__INDEX__" name="items[__INDEX__][delivery_days]" type="number" min="0" max="3650" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div class="flex justify-end md:col-span-2"><button type="button" data-remove-direct-order-item class="text-sm font-medium text-red-700 underline">{{ __('Quitar producto') }}</button></div>
                        </div>
                    </template>
                    @error('items')<p class="px-6 pb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 sm:p-6">
                        <a href="{{ route('purchasing.orders.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Cancelar') }}</a>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2">{{ __('Crear borrador de orden') }}</button>
                    </div>
                </section>
            </form>
        @endif
    </main>
</x-app-layout>
