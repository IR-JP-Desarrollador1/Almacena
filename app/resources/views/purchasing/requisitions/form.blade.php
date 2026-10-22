@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'code' => $product->code,
        'name' => $product->name,
        'unit' => $product->unit->abbreviation,
    ])->values();
    $initialItems = old('items', $requisition->exists
        ? $requisition->items->map(fn ($item) => ['product_id' => (string) $item->product_id, 'quantity' => $item->quantity, 'notes' => $item->notes])->values()->all()
        : [['product_id' => '', 'quantity' => '', 'notes' => '']]);
@endphp
<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $requisition->exists ? __('Editar requisición').' '.$requisition->reference : __('Nueva requisición') }}</h1></x-slot>
    <main class="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $requisition->exists ? route('purchasing.requisitions.update', $requisition) : route('purchasing.requisitions.store') }}" x-data="{ products: @js($productOptions), items: @js($initialItems) }">
                @csrf
                @if ($requisition->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div><x-input-label for="department_name" :value="__('Área solicitante')" /><x-text-input id="department_name" class="mt-1 block w-full bg-gray-50" :value="$department?->name ?? __('Sin área asignada')" readonly /><p class="mt-1 text-xs text-gray-500">{{ __('El área se obtiene del usuario y solo su jefe asignado podrá decidir la requisición.') }}</p><x-input-error :messages="$errors->get('department')" class="mt-2" /></div>
                    <div>
                        <x-input-label for="warehouse_id" :value="__('Bodega de atención')" />
                        <select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">{{ __('Selecciona una bodega autorizada') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $requisition->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                        @if ($warehouses->isEmpty())
                            <p class="mt-1 text-xs text-red-700">{{ __('No tienes bodegas activas asignadas. Solicita acceso al administrador antes de crear una requisición.') }}</p>
                        @endif
                    </div>
                    <div><x-input-label for="needed_by" :value="__('Fecha requerida (opcional)')" /><x-text-input id="needed_by" name="needed_by" type="date" class="mt-1 block w-full" :value="old('needed_by', $requisition->needed_by?->format('Y-m-d'))" /><p class="mt-1 text-xs text-gray-500">{{ __('Formato regional: :date', ['date' => $regional->date('2026-08-17')]) }}</p><x-input-error :messages="$errors->get('needed_by')" class="mt-2" /></div>
                    <div class="md:col-span-2"><x-input-label for="justification" :value="__('Justificación de la solicitud')" /><textarea id="justification" name="justification" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('justification', $requisition->justification) }}</textarea><x-input-error :messages="$errors->get('justification')" class="mt-2" /></div>
                </div>

                <fieldset class="mt-7">
                    <div class="flex items-center justify-between gap-3"><legend class="text-sm font-medium text-gray-900">{{ __('Productos solicitados') }}</legend><button type="button" @click="if(items.length < 50) items.push({product_id: '', quantity: '', notes: ''})" class="text-sm font-medium text-indigo-700 underline">{{ __('Agregar producto') }}</button></div>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                    <template x-for="(item, index) in items" :key="index">
                        <div class="mt-4 rounded-md border border-gray-200 p-4">
                            <div class="grid gap-4 md:grid-cols-12">
                                <div class="md:col-span-6"><x-input-label :value="__('Producto')" /><select x-model="item.product_id" :name="'items[' + index + '][product_id]'" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">{{ __('Seleccione producto') }}</option><template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.code + ' — ' + product.name + ' (' + product.unit + ')'"></option></template></select><x-input-error :messages="$errors->get('items.*.product_id')" class="mt-2" /></div>
                                <div class="md:col-span-2"><x-input-label :value="__('Cantidad')" /><x-text-input type="number" min="0.001" step="0.001" x-model="item.quantity" :name="'items[' + index + '][quantity]'" required class="mt-1 block w-full" /><x-input-error :messages="$errors->get('items.*.quantity')" class="mt-2" /></div>
                                <div class="md:col-span-3"><x-input-label :value="__('Nota (opcional)')" /><x-text-input x-model="item.notes" :name="'items[' + index + '][notes]'" class="mt-1 block w-full" /></div>
                                <div class="flex items-end md:col-span-1"><button type="button" x-show="items.length > 1" @click="items.splice(index, 1)" class="text-sm text-red-700 underline">{{ __('Quitar') }}</button></div>
                            </div>
                        </div>
                    </template>
                </fieldset>
                <div class="mt-6 flex flex-wrap gap-3">@if ($department?->is_active)<x-primary-button>{{ $requisition->exists ? __('Guardar borrador') : __('Crear borrador') }}</x-primary-button>@else<p class="text-sm text-red-700">{{ __('Solicita al administrador que te asigne a un área activa antes de crear requisiciones.') }}</p>@endif<a href="{{ route('purchasing.requisitions.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
