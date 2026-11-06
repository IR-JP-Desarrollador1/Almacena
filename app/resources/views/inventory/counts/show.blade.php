<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Conteo :reference', ['reference' => $count->reference]) }}</h1>
            <a href="{{ route('inventory.counts.index') }}" class="text-sm text-indigo-700 underline">{{ __('Volver a conteos') }}</a>
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
            <p class="text-sm text-gray-600">{{ __('Bodega') }}: <strong>{{ $count->warehouse->branch->name }} / {{ $count->warehouse->name }}</strong></p>
            <p class="text-sm text-gray-600">{{ __('Estado') }}: <strong>{{ __('inventory_count_status.'.$count->status) }}</strong></p>
            <p class="text-sm text-gray-600">{{ __('Creado por') }}: <strong>{{ $count->creator->name }} {{ $count->creator->last_name }}</strong></p>
            @if ($count->approved_at)
                <p class="text-sm text-gray-600">{{ __('Aprobado por') }}: <strong>{{ $count->approver->name }} {{ $count->approver->last_name }}</strong></p>
            @endif
            @if ($count->return_reason)
                <p class="text-sm text-amber-900 md:col-span-2">{{ __('Devuelto para corrección') }}: <strong>{{ $count->return_reason }}</strong></p>
            @endif
        </section>

        @if ($count->status === \App\Models\InventoryCount::STATUS_DRAFT)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
                {{ __('Al enviar el conteo se guardarán las existencias teóricas actuales. Un aprobador distinto confirmará las diferencias antes de registrar entradas o salidas.') }}
            </div>
        @elseif ($count->status === \App\Models\InventoryCount::STATUS_PENDING_APPROVAL)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
                {{ __('Revisa las diferencias. Los sobrantes generan entradas y los faltantes generan salidas de los lotes actuales al aprobar.') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('Producto') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('Existencia teórica') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('Cantidad contada') }}</th>
                            @if ($count->status !== \App\Models\InventoryCount::STATUS_DRAFT)
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('Diferencia') }}</th>
                            @endif
                            @if ($count->status === \App\Models\InventoryCount::STATUS_PENDING_APPROVAL)
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('Valoración del sobrante') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($count->items as $item)
                            @php($differenceMilli = $item->difference_quantity === null ? 0 : (int) round((float) $item->difference_quantity * 1000))
                            <tr>
                                <td class="px-5 py-4 text-sm text-gray-800">{{ $item->product_code }} — {{ $item->product_name }} ({{ $item->unit_abbreviation }})</td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700">{{ $item->system_quantity === null ? '—' : $regional->number($item->system_quantity, 3) }}</td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700">{{ $regional->number($item->counted_quantity, 3) }}</td>
                                @if ($count->status !== \App\Models\InventoryCount::STATUS_DRAFT)
                                    <td class="px-5 py-4 text-right text-sm font-semibold {{ $differenceMilli > 0 ? 'text-green-700' : ($differenceMilli < 0 ? 'text-red-700' : 'text-gray-700') }}">
                                        {{ $differenceMilli > 0 ? '+' : '' }}{{ $regional->number($item->difference_quantity, 3) }}
                                        @if ($differenceMilli > 0) <span class="block text-xs font-normal">{{ __('Sobrante: entrada') }}</span>
                                        @elseif ($differenceMilli < 0) <span class="block text-xs font-normal">{{ __('Faltante: salida') }}
                                        @else <span class="block text-xs font-normal">{{ __('Sin diferencia') }}
                                        @endif
                                        </span>
                                    </td>
                                @endif
                                @if ($count->status === \App\Models\InventoryCount::STATUS_PENDING_APPROVAL)
                                    <td class="px-5 py-4 text-sm text-gray-700">
                                        @if ($differenceMilli > 0)
                                            @if ($item->requires_manual_cost)
                                                <label for="surplus-cost-{{ $item->id }}" class="block text-xs font-medium text-gray-700">{{ __('Costo unitario base') }}</label>
                                                <input form="approve-count-form" id="surplus-cost-{{ $item->id }}" name="items[{{ $item->id }}][surplus_unit_cost]" type="number" min="0.0001" max="99999999999.9999" step="0.0001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            @else
                                                <span>{{ __('Costo unitario') }}: {{ $regional->number($item->suggested_surplus_cost, 4) }}</span>
                                            @endif
                                            @if ($item->product->tracks_expiration)
                                                <label for="surplus-expires-{{ $item->id }}" class="mt-2 block text-xs font-medium text-gray-700">{{ __('Vencimiento obligatorio') }}</label>
                                                <input form="approve-count-form" id="surplus-expires-{{ $item->id }}" name="items[{{ $item->id }}][expires_on]" type="date" min="{{ today()->toDateString() }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canEdit || $canApprove)
                <div class="flex flex-col gap-4 border-t border-gray-200 p-5 sm:p-6">
                    @if ($canEdit)
                        <div class="flex flex-wrap justify-end gap-3">
                            <a data-admin-form-modal href="{{ route('inventory.counts.edit', $count) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Editar borrador') }}</a>
                            <form method="POST" action="{{ route('inventory.counts.submit', $count) }}">
                                @csrf
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('Enviar a aprobación') }}</button>
                            </form>
                        </div>
                    @endif

                    @if ($canApprove)
                        <form id="approve-count-form" method="POST" action="{{ route('inventory.counts.approve', $count) }}" class="flex justify-end">
                            @csrf
                            <x-primary-button>{{ __('Aprobar y aplicar ajustes') }}</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('inventory.counts.return', $count) }}" class="grid gap-3 border-t border-gray-200 pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            @csrf
                            <div>
                                <label for="return_reason" class="block text-sm font-medium text-gray-700">{{ __('Motivo para devolver al borrador') }}</label>
                                <input id="return_reason" name="return_reason" maxlength="500" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('Devolver al borrador') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>
    </main>
</x-app-layout>
