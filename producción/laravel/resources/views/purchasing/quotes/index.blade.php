<x-app-layout>
    @php($canAwardQuotes = Auth::user()->hasPermission('purchases.quotes.award'))
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">{{ __('Comparación de cotizaciones') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('Requisición :reference · Moneda base: :currency', ['reference' => $requisition->reference, 'currency' => $baseCurrency->code]) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                @if (Auth::user()->hasPermission('purchases.quotes.manage'))
                    <a href="{{ route('purchasing.quotes.register', $requisition) }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Registrar cotización') }}</a>
                @endif
                <a href="{{ route('purchasing.requisitions.show', $requisition) }}" class="text-sm text-indigo-700 underline">{{ __('Volver a la requisición') }}</a>
            </div>
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>
        @endif

        @if ($quotes->isNotEmpty())
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Cotizaciones registradas') }}</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach ($quotes as $quote)
                        @php($quoteLocked = $quote->purchaseOrders->isNotEmpty() || $quote->items->contains(fn ($quoteItem) => $quoteItem->award))
                        <article class="rounded-md border border-gray-200 p-4">
                            <h3 class="font-semibold text-gray-900">{{ $quote->supplier->legal_name }} · {{ $quote->quote_reference }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Fecha: :date · Moneda: :currency · Tasa: :rate', ['date' => $regional->date($quote->quote_date), 'currency' => $quote->quoteCurrency->code, 'rate' => $regional->number($quote->exchange_rate, 8)]) }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __(':count productos cotizados', ['count' => $quote->items->count()]) }} · {{ $quoteLocked ? __('Datos de cabecera bloqueados; agrega solo productos pendientes.') : __('Cotización editable mientras no tenga productos adjudicados.') }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Ofertas por producto') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('Compara el costo total con impuestos en moneda base. El costo que se transfiere a inventario continúa siendo antes de impuestos.') }}</p>
            </div>
            @if ($canAwardQuotes)
                <form method="POST" action="{{ route('purchasing.quotes.select', $requisition) }}" class="space-y-4">
                    @csrf
            @else
                <div class="space-y-4">
            @endif
                @foreach ($requisition->items as $item)
                    @php($offers = $offersByItem->get($item->id, collect()))
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $item->product_code }} — {{ $item->product_name }}</h3>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Cantidad solicitada: :quantity :unit', ['quantity' => $regional->number($item->quantity, 3), 'unit' => $item->unit_abbreviation]) }}</p>
                                @if ((float) $item->issued_quantity > 0)
                                    <p class="mt-1 text-sm text-gray-600">{{ __('Ya despachado: :quantity :unit · Saldo para comprar: :remaining :unit', ['quantity' => $regional->number($item->issued_quantity, 3), 'remaining' => $regional->number($item->remaining_quantity, 3), 'unit' => $item->unit_abbreviation]) }}</p>
                                @else
                                    <p class="mt-1 text-sm text-gray-600">{{ __('Saldo para comprar: :quantity :unit', ['quantity' => $regional->number($item->remaining_quantity, 3), 'unit' => $item->unit_abbreviation]) }}</p>
                                @endif
                            </div>
                            @if ($item->award)
                                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">{{ __('Adjudicado a :supplier', ['supplier' => $item->award->quoteItem->quote->supplier->legal_name]) }}</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">{{ __(':count proveedores cotizaron', ['count' => $offers->pluck('supplier_quote_id')->unique()->count()]) }}</span>
                            @endif
                        </div>
                        @if ($offers->isNotEmpty())
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50"><tr>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Seleccionar') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Proveedor / calificación vigente') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Precio neto unitario') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Impuesto') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Total con impuestos (:currency)', ['currency' => $baseCurrency->code]) }}</th>
                                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Marca / plazo') }}</th>
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($offers as $offer)
                                            <tr>
                                                <td class="px-3 py-3">
                                                    @if ($canAwardQuotes && ! $item->award && (float) $item->remaining_quantity > 0 && $offers->pluck('supplier_quote_id')->unique()->count() >= 2)
                                                        <input type="radio" name="awards[{{ $item->id }}]" value="{{ $offer->id }}" @checked(old('awards.'.$item->id) == $offer->id) aria-label="{{ __('Seleccionar oferta de :supplier', ['supplier' => $offer->quote->supplier->legal_name]) }}" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    @else
                                                        <span aria-hidden="true">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">{{ $offer->quote->supplier->legal_name }}<span class="block text-xs text-gray-500">{{ __('Promedio vigente: :score', ['score' => $offer->quote->supplier->latestEvaluation?->average_score ?? __('Sin calificación')]) }}</span></td>
                                                <td class="px-3 py-3">{{ $offer->quote->quoteCurrency->code }} {{ $regional->number($offer->unit_cost, 4) }}</td>
                                                <td class="px-3 py-3">{{ $regional->number($offer->tax_rate, 4) }}%</td>
                                                <td class="px-3 py-3 font-semibold">{{ $baseCurrency->code }} {{ $regional->number($offer->gross_total_base, 4) }}</td>
                                                <td class="px-3 py-3">{{ $offer->brand }}<span class="block text-xs text-gray-500">{{ __(':days días', ['days' => $offer->delivery_days]) }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600">{{ __('Aún no hay ofertas para este producto.') }}</p>
                        @endif
                        @if ($canAwardQuotes && ! $item->award && $offers->pluck('supplier_quote_id')->unique()->count() >= 2)
                            <div class="mt-4 max-w-2xl">
                                <label for="justification-{{ $item->id }}" class="block text-sm font-medium text-gray-700">{{ __('Justificación si no se selecciona el menor costo total') }}</label>
                                <textarea id="justification-{{ $item->id }}" name="justifications[{{ $item->id }}]" rows="2" maxlength="2000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('justifications.'.$item->id) }}</textarea>
                            </div>
                        @endif
                    </section>
                @endforeach
                @php($hasComparableItems = $requisition->items->contains(fn ($item) => ! $item->award && (float) $item->remaining_quantity > 0 && $offersByItem->get($item->id, collect())->pluck('supplier_quote_id')->unique()->count() >= 2))
                @if ($canAwardQuotes)
                    <button type="submit" @disabled(! $hasComparableItems) aria-describedby="{{ $hasComparableItems ? '' : 'comparison-save-hint' }}" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">{{ __('Guardar comparación') }}</button>
                    @unless ($hasComparableItems)
                        <p id="comparison-save-hint" class="text-sm text-gray-600">{{ __('Registra ofertas de al menos dos proveedores distintos para un producto antes de guardar la comparación.') }}</p>
                    @endunless
                @endif
                </div>
            @if ($canAwardQuotes)
                </form>
            @else
                </div>
            @endif
            @if ($hasPendingAwards && Auth::user()->hasPermission('purchases.quotes.generate_orders'))
                <form method="POST" action="{{ route('purchasing.quotes.generate-orders', $requisition) }}">
                    @csrf
                    <x-primary-button class="min-h-[46px]">{{ __('Generar órdenes borrador para la comparación guardada') }}</x-primary-button>
                </form>
            @endif
        </section>
    </main>
</x-app-layout>
