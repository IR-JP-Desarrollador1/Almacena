<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $location->exists ? __('Editar ubicación') : __('Crear ubicación') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.organization._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $location->exists ? route('admin.organization.locations.update', $location) : route('admin.organization.locations.store') }}">
                @csrf
                @if ($location->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div><x-input-label for="warehouse_id" :value="__('Bodega')" /><select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $location->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->branch->name }} / {{ $warehouse->name }}{{ $warehouse->is_active ? '' : ' ('.__('Inactiva').')' }}</option>@endforeach</select><x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" /></div>
                    @foreach (['code' => __('Código'), 'name' => __('Nombre de la ubicación')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $location->$field)" required /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                    <div class="md:col-span-2"><x-input-label for="description" :value="__('Descripción')" /><textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $location->description) }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                </div>
                @if ($location->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Ubicación activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar ubicación') }}</x-primary-button><a href="{{ route('admin.organization.locations.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
