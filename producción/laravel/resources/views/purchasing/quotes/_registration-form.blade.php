<section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Registrar o completar cotización') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('Registra precio antes de impuestos, impuesto por línea, plazo y marca. Las líneas seleccionadas quedan bloqueadas al adjudicarse; puedes añadir productos pendientes en etapas posteriores.') }}</p>
        </div>
        <a href="{{ route('purchasing.quotes.index', $requisition) }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Ver comparación') }}</a>
    </div>

    <form method="POST" action="{{ route('purchasing.quotes.store', $requisition) }}" class="mt-5 space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="supplier_id" class="block text-sm font-medium text-gray-700">{{ __('Proveedor') }}</label>
                <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">{{ __('Seleccionar proveedor') }}</option>
                    @foreach ($suppliers as $supplier)
                        @php($existingQuote = $quotes->firstWhere('supplier_id', $supplier->id))
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                            {{ $supplier->legal_name }}{{ $existingQuote ? ' · '.__('Cotización existente: :reference', ['reference' => $existingQuote->quote_reference]) : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="quote_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia de cotización') }}</label>
                <input id="quote_reference" name="quote_reference" value="{{ old('quote_reference') }}" required maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="quote_date" class="block text-sm font-medium text-gray-700">{{ __('Fecha de cotización') }}</label>
                <input id="quote_date" name="quote_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('quote_date', now()->toDateString()) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="quote_currency_id" class="block text-sm font-medium text-gray-700">{{ __('Moneda de cotización') }}</label>
                <select id="quote_currency_id" name="quote_currency_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected(old('quote_currency_id', $baseCurrency->id) == $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="exchange_rate" class="block text-sm font-medium text-gray-700">{{ __('Tasa de cambio a moneda base') }}</label>
                <input id="exchange_rate" name="exchange_rate" type="number" min="0.00000001" max="999999999.99999999" step="0.00000001" value="{{ old('exchange_rate', '1.00000000') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
        </div>

        <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Incluir producto') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Producto / cantidad solicitada') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Precio unitario antes de impuestos') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Impuesto (%)') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Marca') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Plazo de entrega (días)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requisition->items as $item)
                        @if (! $item->award && (float) $item->remaining_quantity > 0)
                            @php($row = old('items.'.$item->id, []))
                            <tr x-data="{ selected: {{ isset($row['requisition_item_id']) ? 'true' : 'false' }} }">
                                <td class="px-4 py-4">
                                    <input type="checkbox" name="items[{{ $item->id }}][requisition_item_id]" value="{{ $item->id }}" x-model="selected" aria-label="{{ __('Incluir producto :product en esta cotización', ['product' => $item->product_name]) }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="px-4 py-4 text-sm">{{ $item->product_code }} — {{ $item->product_name }}<span class="block text-gray-500">{{ __('Saldo pendiente de compra: :quantity :unit', ['quantity' => $regional->number($item->remaining_quantity, 3), 'unit' => $item->unit_abbreviation]) }}</span></td>
                                <td class="px-4 py-4"><input x-bind:disabled="!selected" name="items[{{ $item->id }}][unit_cost]" type="number" min="0" max="99999999999.9999" step="0.0001" value="{{ $row['unit_cost'] ?? '' }}" class="w-36 rounded-md border-gray-300 shadow-sm" aria-label="{{ __('Precio unitario para :product', ['product' => $item->product_name]) }}"></td>
                                <td class="px-4 py-4"><input x-bind:disabled="!selected" name="items[{{ $item->id }}][tax_rate]" type="number" min="0" max="100" step="0.0001" value="{{ $row['tax_rate'] ?? '0' }}" class="w-28 rounded-md border-gray-300 shadow-sm" aria-label="{{ __('Impuesto para :product', ['product' => $item->product_name]) }}"></td>
                                <td class="px-4 py-4"><input x-bind:disabled="!selected" name="items[{{ $item->id }}][brand]" maxlength="100" value="{{ $row['brand'] ?? '' }}" class="w-36 rounded-md border-gray-300 shadow-sm" aria-label="{{ __('Marca para :product', ['product' => $item->product_name]) }}"></td>
                                <td class="px-4 py-4"><input x-bind:disabled="!selected" name="items[{{ $item->id }}][delivery_days]" type="number" min="0" max="3650" step="1" value="{{ $row['delivery_days'] ?? '' }}" class="w-28 rounded-md border-gray-300 shadow-sm" aria-label="{{ __('Plazo de entrega para :product', ['product' => $item->product_name]) }}"></td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="quote-submit-bar">
            <x-primary-button class="quote-submit-button">{{ __('Guardar cotización') }}</x-primary-button>
        </div>
    </form>
</section>
