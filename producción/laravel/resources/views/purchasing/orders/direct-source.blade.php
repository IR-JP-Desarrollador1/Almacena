<x-app-layout>
    <x-slot name="header">
        <h1>{{ __('Crear orden directa') }}</h1>
    </x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-5 md:grid-cols-2">
            <a href="{{ route('purchasing.orders.direct.create', ['mode' => 'requisition']) }}" class="group flex min-h-56 flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-green-600 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Requisición') }}</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ __('Desde requisición sin cotización') }}</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Selecciona una requisición aprobada y registra proveedor, productos, cantidades y costos sin comparar cotizaciones.') }}</p>
            </a>
            <a href="{{ route('purchasing.orders.direct.create', ['mode' => 'standalone']) }}" class="group flex min-h-56 flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-green-600 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Orden directa') }}</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ __('Sin requisición ni cotización') }}</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ __('Crea una orden con bodega y productos del catálogo, conservando el flujo de aprobación antes de recibir.') }}</p>
            </a>
        </section>
        <div>
            <a href="{{ route('purchasing.orders.index') }}" class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-indigo-700 underline underline-offset-4 hover:text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2">{{ __('Volver a órdenes') }}</a>
        </div>
    </main>
</x-app-layout>
