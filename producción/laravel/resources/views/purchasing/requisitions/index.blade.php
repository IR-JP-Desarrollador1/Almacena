<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ $inventoryContext ? __('Requisiciones de inventario') : __('Requisiciones') }}</h1>
            @if ($inventoryContext && Auth::user()->hasPermission('inventory.requisitions.manage'))
                <a href="{{ route('inventory.requisitions.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Nueva requisición') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Folio') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Bodega') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Área') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Solicitante') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Jefe aprobador') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Estado') }}</th><th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Fecha') }}</th><th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Acciones') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($requisitions as $requisition)
                        <tr class="transition-colors hover:bg-gray-50/80"><td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">{{ $requisition->reference }}</td><td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->warehouse ? $requisition->warehouse->branch->name.' / '.$requisition->warehouse->name : '—' }}</td><td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->department->name }}</td><td class="px-5 py-4 text-sm text-gray-700">{{ trim($requisition->requester->name.' '.$requisition->requester->last_name) }}</td><td class="px-5 py-4 text-sm text-gray-700">{{ $requisition->approver ? trim($requisition->approver->name.' '.$requisition->approver->last_name) : '—' }}</td><td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ __('requisition_status.'.$requisition->status) }}</td><td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ $regional->date($requisition->created_at) }}</td><td class="whitespace-nowrap px-5 py-4 text-right text-sm"><a class="inline-flex min-h-9 items-center justify-center rounded-md border border-indigo-200 px-3 py-1.5 font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2" href="{{ route($inventoryContext ? 'inventory.requisitions.show' : 'purchasing.requisitions.show', $requisition) }}">{{ __('Ver') }}</a></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-gray-500">{{ __('No hay requisiciones para mostrar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">{{ $requisitions->links() }}</div>
        </section>
    </main>
</x-app-layout>
