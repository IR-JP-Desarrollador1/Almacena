<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Órdenes de compra') }}</h1>
            <a href="{{ route('purchasing.requisitions.index') }}" class="text-sm text-indigo-700 underline">{{ __('Ir a requisiciones aprobadas') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Orden') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Requisición') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Proveedor') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Bodega') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Moneda cotizada') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Estado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Fecha') }}</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium">{{ $order->reference }}</td>
                            <td class="px-6 py-4 text-sm">{{ $order->requisition->reference }}</td>
                            <td class="px-6 py-4 text-sm">{{ $order->supplier->legal_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ $order->requisition->warehouse->branch->name }} / {{ $order->requisition->warehouse->name }}</td>
                            <td class="px-6 py-4 text-sm">{{ $order->quoteCurrency->code }} → {{ $order->baseCurrency->code }}</td>
                            <td class="px-6 py-4 text-sm">{{ $order->status === 'approved' ? __('Aprobada') : __('Borrador') }}</td>
                            <td class="px-6 py-4 text-sm">{{ $regional->date($order->created_at) }}</td>
                            <td class="px-6 py-4 text-right text-sm"><a class="text-indigo-700 underline" href="{{ route('purchasing.orders.show', $order) }}">{{ __('Ver') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay órdenes de compra para mostrar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
        {{ $orders->links() }}
    </main>
</x-app-layout>
