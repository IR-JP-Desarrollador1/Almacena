<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar salida') }}</h1>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-5 md:grid-cols-2">
            @if ($canCreateDirect)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Salida directa') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-800">{{ __('Seleccionar productos') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Registra consumos internos o pérdidas/daños autorizados seleccionando productos de una bodega, con motivo obligatorio y referencia opcional.') }}</p>
                    <a href="{{ route('inventory.issues.create', ['mode' => 'direct']) }}" class="mt-5 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear salida directa') }}</a>
                </article>
            @endif
            @if ($requisitions)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Requisición') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-800">{{ __('Salidas desde requisiciones') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Selecciona una requisición activa o aprobada con saldo pendiente para registrar un despacho parcial o completo.') }}</p>
                </article>
            @endif
        </section>

        @if ($requisitions)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ __('Requisiciones pendientes de despacho') }}">
                @if ($requisitions->isNotEmpty())
                    <div class="divide-y divide-gray-200">
                        @foreach ($requisitions as $requisition)
                            <article class="flex flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                                <div class="grid min-w-0 gap-2">
                                    <h2 class="font-semibold text-gray-800">{{ $requisition->reference }} · {{ __('requisition_status.'.$requisition->status) }}</h2>
                                    <p class="text-sm text-gray-600">{{ __('Bodega: :warehouse', ['warehouse' => $requisition->warehouse->branch->name.' / '.$requisition->warehouse->name]) }}</p>
                                    <ul class="grid gap-1 text-sm text-gray-500">
                                        @foreach ($requisition->items as $item)
                                            <li>{{ $item->product_code }} — {{ $item->product_name }} · {{ __('Pendiente') }}: {{ $regional->number($item->remaining_milli / 1000, 3) }} {{ $item->unit_abbreviation }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <a href="{{ route('inventory.issues.create', ['requisition_id' => $requisition->id]) }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Registrar salida') }}</a>
                            </article>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-200 p-4">{{ $requisitions->links() }}</div>
                @else
                    <p class="p-8 text-center text-sm text-gray-500">{{ __('No hay requisiciones activas o aprobadas con saldo pendiente de despacho.') }}</p>
                @endif
            </section>
        @endif

        <a href="{{ route('inventory.issues.index') }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a salidas') }}</a>
    </main>
</x-app-layout>
