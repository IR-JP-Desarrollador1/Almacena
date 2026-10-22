<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $product->exists ? __('Editar producto') : __('Crear producto') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.catalogs._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $product->exists ? route('admin.catalogs.products.update', $product) : route('admin.catalogs.products.store') }}">
                @csrf
                @if ($product->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (['code' => __('Código'), 'name' => __('Nombre del producto')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $product->$field)" required /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                    <div><x-input-label for="unit_id" :value="__('Unidad de medida')" /><select id="unit_id" name="unit_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id', $product->unit_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }}){{ $unit->is_active ? '' : ' ('.__('Inactiva').')' }}</option>@endforeach</select><x-input-error :messages="$errors->get('unit_id')" class="mt-2" /></div>
                    <div class="md:col-span-2"><x-input-label for="description" :value="__('Descripción')" /><textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $product->description) }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                </div>
                <input type="hidden" name="tracks_expiration" value="0">
                <label class="mt-5 flex items-center gap-2"><input type="checkbox" name="tracks_expiration" value="1" @checked(old('tracks_expiration', $product->tracks_expiration)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Este producto maneja fecha de vencimiento') }}</span></label>
                <x-input-error :messages="$errors->get('tracks_expiration')" class="mt-2" />
                @if ($product->exists)
                    <label class="mt-4 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Producto activo') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar producto') }}</x-primary-button><a href="{{ route('admin.catalogs.products.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
