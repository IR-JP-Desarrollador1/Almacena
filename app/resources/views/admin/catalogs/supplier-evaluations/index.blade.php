<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Calificación de proveedores') }}</h1>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->all()[0] }}</div>
        @endif
        @include('admin.catalogs._tabs')

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Registrar evaluación') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('Califica cada criterio de 1 (peor) a 5 (mejor). El promedio simple será la calificación vigente del proveedor.') }}</p>
            <form method="POST" action="{{ route('admin.catalogs.supplier-evaluations.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700">{{ __('Proveedor') }}</label>
                    <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">{{ __('Seleccionar proveedor') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->legal_name }}{{ $supplier->latestEvaluation ? ' · '.__('Vigente: :score', ['score' => $supplier->latestEvaluation->average_score]) : '' }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ([
                    'distance_score' => __('Distancia'),
                    'delivery_time_score' => __('Tiempo de entrega'),
                    'fulfillment_score' => __('Cumplimiento'),
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-gray-700">{{ $label }} (1–5)</label>
                        <select id="{{ $field }}" name="{{ $field }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @for ($score = 1; $score <= 5; $score++)
                                <option value="{{ $score }}" @selected(old($field) == $score)>{{ $score }}</option>
                            @endfor
                        </select>
                    </div>
                @endforeach
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('Guardar evaluación') }}</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Calificación vigente por proveedor') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Proveedor') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Distancia') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Tiempo de entrega') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Cumplimiento') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Promedio') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($suppliers as $supplier)
                            <tr><td class="px-5 py-3 text-sm font-medium">{{ $supplier->legal_name }}</td><td class="px-5 py-3 text-sm">{{ $supplier->latestEvaluation?->distance_score ?? '—' }}</td><td class="px-5 py-3 text-sm">{{ $supplier->latestEvaluation?->delivery_time_score ?? '—' }}</td><td class="px-5 py-3 text-sm">{{ $supplier->latestEvaluation?->fulfillment_score ?? '—' }}</td><td class="px-5 py-3 text-sm font-semibold">{{ $supplier->latestEvaluation?->average_score ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">{{ __('No hay proveedores activos para evaluar.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $suppliers->links() }}</div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Historial de evaluaciones') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Fecha') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Proveedor') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Distancia') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Tiempo de entrega') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Cumplimiento') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Promedio') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Evaluado por') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($evaluations as $evaluation)
                            <tr><td class="px-5 py-3 text-sm">{{ $regional->date($evaluation->created_at) }}</td><td class="px-5 py-3 text-sm">{{ $evaluation->supplier->legal_name }}</td><td class="px-5 py-3 text-sm">{{ $evaluation->distance_score }}</td><td class="px-5 py-3 text-sm">{{ $evaluation->delivery_time_score }}</td><td class="px-5 py-3 text-sm">{{ $evaluation->fulfillment_score }}</td><td class="px-5 py-3 text-sm font-semibold">{{ $evaluation->average_score }}</td><td class="px-5 py-3 text-sm">{{ $evaluation->evaluatedBy->name }} {{ $evaluation->evaluatedBy->last_name }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">{{ __('Aún no hay evaluaciones registradas.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $evaluations->links() }}</div>
        </section>
    </main>
</x-app-layout>
