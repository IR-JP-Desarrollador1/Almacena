<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Orden devuelta para corrección') }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-800">{{ __('Editar orden de compra').' '.$purchaseOrder->reference }}</h1>
            </div>
            <a href="{{ route('purchasing.orders.show', $purchaseOrder) }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a la orden') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">{{ __('Revisa los datos de la orden') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-900">{{ __('Observaciones de rechazo') }}</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-amber-800">{{ $purchaseOrder->rejection_reason }}</p>
            <p class="mt-3 text-sm text-amber-900">{{ __('El origen de cotización adjudicada se conserva para trazabilidad. Los cambios se registran en auditoría y requerirán una nueva aprobación.') }}</p>
        </section>

        <form method="POST" action="{{ route('purchasing.orders.update', $purchaseOrder) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <section class="grid gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700">{{ __('Proveedor') }}</label>
                    <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $purchaseOrder->supplier_id) === (string) $supplier->id)>{{ $supplier->legal_name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quote_currency_id" class="block text-sm font-medium text-gray-700">{{ __('Moneda cotizada') }}</label>
                    <select id="quote_currency_id" name="quote_currency_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}" @selected((string) old('quote_currency_id', $purchaseOrder->quote_currency_id) === (string) $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                        @endforeach
                    </select>
                    @error('quote_currency_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quote_reference" class="block text-sm font-medium text-gray-700">{{ $purchaseOrder->supplier_quote_id ? __('Referencia de cotización') : __('Referencia de cotización (opcional)') }}</label>
                    <input id="quote_reference" name="quote_reference" value="{{ old('quote_reference', $purchaseOrder->quote_reference) }}" maxlength="100" @required($purchaseOrder->supplier_quote_id) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('quote_reference')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quote_date" class="block text-sm font-medium text-gray-700">{{ $purchaseOrder->supplier_quote_id ? __('Fecha de cotización') : __('Fecha de cotización (opcional)') }}</label>
                    <input id="quote_date" name="quote_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('quote_date', $purchaseOrder->quote_date?->toDateString()) }}" @required($purchaseOrder->supplier_quote_id) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('quote_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="exchange_rate" class="block text-sm font-medium text-gray-700">{{ __('Tasa de cambio') }}</label>
                    <input id="exchange_rate" name="exchange_rate" type="number" min="0.00000001" max="999999999.99999999" step="0.00000001" value="{{ old('exchange_rate', $purchaseOrder->exchange_rate) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('El costo base se recalculará en :currency.', ['currency' => $baseCurrency->code]) }}</p>
                    @error('exchange_rate')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-gray-900">{{ __('Términos por producto') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Los productos y su relación con la requisición no se pueden cambiar. La cantidad no puede superar el saldo pendiente de compra.') }}</p>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach ($purchaseOrder->items as $item)
                        <fieldset class="grid gap-4 p-5 md:grid-cols-2 md:p-6">
                            <legend class="mb-2 text-sm font-semibold text-gray-900">{{ $item->product_code }} — {{ $item->product_name }}</legend>
                            <div>
                                <label for="quantity-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Cantidad ordenada (:unit)', ['unit' => $item->unit_abbreviation]) }}</label>
                                <input id="quantity-{{ $item->id }}" name="items[{{ $item->id }}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" value="{{ old("items.{$item->id}.quantity", $item->quantity) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$item->id}.quantity")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="unit-cost-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Costo unitario cotizado') }}</label>
                                <input id="unit-cost-{{ $item->id }}" name="items[{{ $item->id }}][unit_cost]" type="number" min="0" max="99999999999.9999" step="0.0001" value="{{ old("items.{$item->id}.unit_cost", $item->unit_cost) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$item->id}.unit_cost")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tax-rate-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Impuesto (%)') }}</label>
                                <input id="tax-rate-{{ $item->id }}" name="items[{{ $item->id }}][tax_rate]" type="number" min="0" max="100" step="0.0001" value="{{ old("items.{$item->id}.tax_rate", $item->tax_rate) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$item->id}.tax_rate")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="brand-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Marca') }}</label>
                                <input id="brand-{{ $item->id }}" name="items[{{ $item->id }}][brand]" value="{{ old("items.{$item->id}.brand", $item->brand) }}" maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$item->id}.brand")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="delivery-days-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Plazo de entrega (días)') }}</label>
                                <input id="delivery-days-{{ $item->id }}" name="items[{{ $item->id }}][delivery_days]" type="number" min="0" max="3650" step="1" value="{{ old("items.{$item->id}.delivery_days", $item->delivery_days) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @error("items.{$item->id}.delivery_days")<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('purchasing.orders.show', $purchaseOrder) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2">{{ __('Cancelar') }}</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2">{{ __('Guardar correcciones') }}</button>
            </div>
        </form>
    </main>
</x-app-layout>
