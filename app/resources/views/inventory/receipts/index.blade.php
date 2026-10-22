<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Entradas de inventario') }}</h1></x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Entrada') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Orden') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Bodega') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Productos') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Recibida por') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Fecha') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($receipts as $receipt)
                        <tr><td class="px-6 py-4 text-sm font-medium"><a class="text-indigo-700 underline" href="{{ route('inventory.receipts.show', $receipt) }}">{{ $receipt->reference }}</a></td><td class="px-6 py-4 text-sm">{{ $receipt->purchaseOrder->reference }}</td><td class="px-6 py-4 text-sm">{{ $receipt->warehouse->branch->name }} / {{ $receipt->warehouse->name }}</td><td class="px-6 py-4 text-sm">{{ $receipt->items->count() }}</td><td class="px-6 py-4 text-sm">{{ $receipt->receiver->name }} {{ $receipt->receiver->last_name }}</td><td class="px-6 py-4 text-sm">{{ $regional->dateTime($receipt->received_at) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay entradas para mostrar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $receipts->links() }}
    </main>
</x-app-layout>
