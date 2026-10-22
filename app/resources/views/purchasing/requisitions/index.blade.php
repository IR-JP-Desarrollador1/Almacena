<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Requisiciones') }}</h1>
            @if (Auth::user()->hasPermission('purchases.requisitions.create'))
                <a href="{{ route('purchasing.requisitions.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Nueva requisición') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Folio') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Bodega') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Área') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Solicitante') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Jefe aprobador') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Estado') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Fecha') }}</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($requisitions as $requisition)
                        <tr><td class="px-6 py-4 text-sm font-medium">{{ $requisition->reference }}</td><td class="px-6 py-4 text-sm">{{ $requisition->warehouse ? $requisition->warehouse->branch->name.' / '.$requisition->warehouse->name : '—' }}</td><td class="px-6 py-4 text-sm">{{ $requisition->department->name }}</td><td class="px-6 py-4 text-sm">{{ trim($requisition->requester->name.' '.$requisition->requester->last_name) }}</td><td class="px-6 py-4 text-sm">{{ $requisition->approver ? trim($requisition->approver->name.' '.$requisition->approver->last_name) : '—' }}</td><td class="px-6 py-4 text-sm">{{ __('requisition_status.'.$requisition->status) }}</td><td class="px-6 py-4 text-sm">{{ $regional->date($requisition->created_at) }}</td><td class="px-6 py-4 text-right text-sm"><a class="text-indigo-700 underline" href="{{ route('purchasing.requisitions.show', $requisition) }}">{{ __('Ver') }}</a></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay requisiciones para mostrar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requisitions->links() }}
    </main>
</x-app-layout>
