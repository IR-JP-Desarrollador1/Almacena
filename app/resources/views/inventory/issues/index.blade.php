<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Salidas de inventario') }}</h1></x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Salida') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Requisición') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Bodega') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Productos') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Entregada por') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Fecha') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($issues as $issue)
                        <tr><td class="px-6 py-4 text-sm font-medium"><a class="text-indigo-700 underline" href="{{ route('inventory.issues.show', $issue) }}">{{ $issue->reference }}</a></td><td class="px-6 py-4 text-sm">{{ $issue->requisition->reference }}</td><td class="px-6 py-4 text-sm">{{ $issue->warehouse->branch->name }} / {{ $issue->warehouse->name }}</td><td class="px-6 py-4 text-sm">{{ $issue->items->count() }}</td><td class="px-6 py-4 text-sm">{{ $issue->issuer->name }} {{ $issue->issuer->last_name }}</td><td class="px-6 py-4 text-sm">{{ $regional->dateTime($issue->issued_at) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay salidas para mostrar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $issues->links() }}
    </main>
</x-app-layout>
