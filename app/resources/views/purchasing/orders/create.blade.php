<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Crear orden de compra') }}</h1>
            <a href="{{ route('purchasing.requisitions.show', $requisition) }}" class="text-sm text-indigo-700 underline">{{ __('Volver a la requisición') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>@endif
        <section class="rounded-lg bg-white p-6 shadow">
            <p class="text-sm text-gray-600">{{ __('Requisición aprobada') }}: <strong>{{ $requisition->reference }}</strong> · {{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</p>
        </section>
        <form method="POST" action="{{ route('purchasing.orders.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="requisition_id" value="{{ $requisition->id }}">
            <section class="grid gap-5 rounded-lg bg-white p-6 shadow md:grid-cols-2">
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
                    <label for="quote_reference" class="block text-sm font-medium text-gray-700">{{ __('Referencia de cotización') }}</label>
                    <input id="quote_reference" name="quote_reference" value="{{ old('quote_reference') }}" required maxlength="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('quote_reference')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quote_date" class="block text-sm font-medium text-gray-700">{{ __('Fecha de cotización') }}</label>
                    <input id="quote_date" name="quote_date" type="date" value="{{ old('quote_date', today()->toDateString()) }}" required max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('quote_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quote_currency_id" class="block text-sm font-medium text-gray-700">{{ __('Moneda cotizada') }}</label>
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
                    <p class="mt-1 text-xs text-gray-500">{{ __('Unidades de :base por cada unidad cotizada. Si ambas monedas coinciden, la tasa debe ser 1.', ['base' => $baseCurrency->code]) }}</p>
                    @error('exchange_rate')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4"><h2 class="font-semibold text-gray-800">{{ __('Productos y costos cotizados') }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('La orden incluye las cantidades aprobadas completas. Los costos se ingresan en la moneda cotizada y se convierten a :base para conservar el costo del lote.', ['base' => $baseCurrency->code]) }}</p></div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Producto') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Cantidad') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Costo unitario cotizado') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($requisition->items as $item)
                            <tr>
                                <td class="px-6 py-4 text-sm">{{ $item->product_code }} — {{ $item->product_name }}</td>
                                <td class="px-6 py-4 text-sm">{{ $item->quantity }} {{ $item->unit_abbreviation }}</td>
                                <td class="px-6 py-4"><input name="unit_costs[{{ $item->id }}]" type="number" min="0" max="99999999999.9999" step="0.0001" value="{{ old('unit_costs.'.$item->id) }}" required class="block w-full rounded-md border-gray-300 shadow-sm"><x-input-error :messages="$errors->get('unit_costs.'.$item->id)" class="mt-1" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-end p-6"><x-primary-button>{{ __('Guardar borrador de orden') }}</x-primary-button></div>
            </section>
        </form>
    </main>
</x-app-layout>
