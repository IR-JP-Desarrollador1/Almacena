<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $unit->exists ? __('Editar unidad') : __('Crear unidad') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.catalogs._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $unit->exists ? route('admin.catalogs.units.update', $unit) : route('admin.catalogs.units.store') }}">
                @csrf
                @if ($unit->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (['code' => __('Código'), 'name' => __('Nombre de la unidad'), 'abbreviation' => __('Abreviatura')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $unit->$field)" required /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                    <div class="md:col-span-2"><x-input-label for="description" :value="__('Descripción')" /><textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $unit->description) }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                </div>
                @if ($unit->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $unit->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Unidad activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar unidad') }}</x-primary-button><a href="{{ route('admin.catalogs.units.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
