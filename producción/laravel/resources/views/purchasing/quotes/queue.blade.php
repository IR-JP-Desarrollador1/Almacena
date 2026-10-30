<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar cotización') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Requisiciones aprobadas para cotizar') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('Selecciona una requisición aprobada para capturar o completar las ofertas de sus proveedores.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Folio') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Bodega') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Área solicitante') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Productos pendientes') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Cotizaciones') }}</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($requisitions as $requisition)
                            <tr class="transition-colors hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">{{ $requisition->reference }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->department->name }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->items->filter(fn ($item) => ! $item->award)->count() }}</td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->supplier_quotes_count }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    @if (Auth::user()->hasPermission('purchases.quotes.manage'))
                                        <a class="inline-flex min-h-9 items-center justify-center rounded-md border border-indigo-200 px-3 py-1.5 font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2" href="{{ route('purchasing.quotes.register', $requisition) }}">{{ __('Registrar cotización') }}</a>
                                    @endif
                                    @if (Auth::user()->hasPermission('purchases.quotes.view') || Auth::user()->hasPermission('purchases.quotes.award') || Auth::user()->hasPermission('purchases.quotes.generate_orders'))
                                        <a class="inline-flex min-h-9 items-center justify-center rounded-md border border-indigo-200 px-3 py-1.5 font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2" href="{{ route('purchasing.quotes.index', $requisition) }}">{{ __('Ver comparación') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">{{ __('No hay requisiciones aprobadas con productos pendientes de cotización.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($requisitions->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">{{ $requisitions->links() }}</div>
            @endif
        </section>
    </main>
</x-app-layout>
