<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $warehouse->exists ? __('Editar bodega') : __('Crear bodega') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.organization._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $warehouse->exists ? route('admin.organization.warehouses.update', $warehouse) : route('admin.organization.warehouses.store') }}">
                @csrf
                @if ($warehouse->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div><x-input-label for="branch_id" :value="__('Sede')" /><select id="branch_id" name="branch_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $warehouse->branch_id) === (string) $branch->id)>{{ $branch->name }}{{ $branch->is_active ? '' : ' ('.__('Inactiva').')' }}</option>@endforeach</select><x-input-error :messages="$errors->get('branch_id')" class="mt-2" /></div>
                    @foreach (['code' => __('Código'), 'name' => __('Nombre de la bodega'), 'address' => __('Dirección')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $warehouse->$field)" :required="in_array($field, ['code', 'name'])" /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                </div>
                @if ($warehouse->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Bodega activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar bodega') }}</x-primary-button><a href="{{ route('admin.organization.warehouses.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
