<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $supplier->exists ? __('Editar proveedor') : __('Crear proveedor') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.catalogs._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $supplier->exists ? route('admin.catalogs.suppliers.update', $supplier) : route('admin.catalogs.suppliers.store') }}">
                @csrf
                @if ($supplier->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (['tax_id' => __('Identificación tributaria'), 'legal_name' => __('Razón social'), 'trade_name' => __('Nombre comercial'), 'contact_name' => __('Persona de contacto'), 'email' => __('Correo electrónico'), 'phone' => __('Teléfono'), 'address' => __('Dirección')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" :type="$field === 'email' ? 'email' : 'text'" class="mt-1 block w-full" :value="old($field, $supplier->$field)" :required="$field === 'legal_name'" /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                </div>
                @if ($supplier->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Proveedor activo') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar proveedor') }}</x-primary-button><a href="{{ route('admin.catalogs.suppliers.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
