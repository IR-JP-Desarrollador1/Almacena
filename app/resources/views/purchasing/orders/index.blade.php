<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="mt-1 text-xl font-semibold text-gray-800">{{ __('Órdenes de compra') }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if (Auth::user()->hasPermission('purchases.orders.create_direct'))
                    <a href="{{ route('purchasing.orders.direct.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">{{ __('Crear orden directa') }}</a>
                @endif
                <a href="{{ route('purchasing.requisitions.index') }}" class="rounded-md px-2 py-2 text-sm font-medium text-indigo-700 underline underline-offset-4 hover:text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2">{{ __('Ir a requisiciones aprobadas') }}</a>
            </div>
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <form method="GET" action="{{ route('purchasing.orders.index') }}" class="flex w-full flex-col items-stretch gap-4 sm:w-auto sm:flex-1 sm:flex-row sm:items-end sm:flex-nowrap">
                    <div class="w-full sm:w-64">
                        <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Filtrar por estado') }}</label>
                        <select id="status" name="status" class="mt-1 block min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            <option value="">{{ __('Todos los estados') }}</option>
                            @foreach (['draft', 'pending_approval', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($statusFilter === $status)>{{ __('order_status.'.$status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">{{ __('Aplicar filtro') }}</button>
                </form>
                @if (Auth::user()->hasPermission('purchases.orders.approve') || Auth::user()->hasPermission('purchases.orders.approve_any') || Auth::user()->hasPermission('purchases.orders.reject'))
                    <a href="{{ route('purchasing.orders.index', ['status' => 'pending_approval']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2">
                        {{ __('Pendientes de aprobación (:count)', ['count' => $pendingCount]) }}
                    </a>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Orden') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Requisición') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Proveedor') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Bodega') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Moneda cotizada') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Estado') }}</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Fecha') }}</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($orders as $order)
                            <tr class="transition-colors hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">{{ $order->reference }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ $order->requisition?->reference ?? __('Orden directa') }}</td>
                                <td class="max-w-64 px-5 py-4 text-sm text-gray-700">{{ $order->supplier->legal_name }}</td>
                                <td class="min-w-52 px-5 py-4 text-sm text-gray-700">
                                    <span class="block">{{ $order->warehouse->branch->name }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ $order->warehouse->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ $order->quoteCurrency->code }} <span class="text-gray-400">→</span> {{ $order->baseCurrency->code }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    @php($statusColor = ['pending_approval' => 'bg-amber-100 text-amber-900', 'approved' => 'bg-green-100 text-green-900', 'rejected' => 'bg-red-100 text-red-900'][$order->status] ?? 'bg-gray-100 text-gray-800')
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">
                                        <span class="mr-1.5 size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ __('order_status.'.$order->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ $regional->date($order->created_at) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <a class="inline-flex min-h-9 items-center justify-center rounded-md border border-indigo-200 px-3 py-1.5 font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2" href="{{ route('purchasing.orders.show', $order) }}">{{ __('Ver') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-gray-500">{{ __('No hay órdenes de compra para mostrar.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $orders->links() }}</div>
        </section>
    </main>
</x-app-layout>
