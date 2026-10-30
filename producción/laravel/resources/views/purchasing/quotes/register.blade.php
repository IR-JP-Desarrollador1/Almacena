<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Registrar cotización') }}</h1>
            <a href="{{ route('purchasing.quotes.queue') }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a requisiciones para cotizar') }}</a>
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">{{ __('Requisición :reference', ['reference' => $requisition->reference]) }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $requisition->warehouse->branch->name }} / {{ $requisition->warehouse->name }}</p>
        </section>

        @if ($requisition->items->contains(fn ($item) => ! $item->award && (float) $item->remaining_quantity > 0))
            @include('purchasing.quotes._registration-form')
        @else
            <p role="status" class="rounded-md border border-gray-200 bg-white p-5 text-sm text-gray-700">{{ __('Todos los productos de esta requisición ya tienen una oferta seleccionada.') }}</p>
        @endif
    </main>
</x-app-layout>
