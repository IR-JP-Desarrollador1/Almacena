<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $branch->exists ? __('Editar sede') : __('Crear sede') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.organization._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $branch->exists ? route('admin.organization.branches.update', $branch) : route('admin.organization.branches.store') }}">
                @csrf
                @if ($branch->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (['code' => __('Código'), 'name' => __('Nombre de la sede'), 'address' => __('Dirección'), 'phone' => __('Teléfono')] as $field => $label)
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $branch->$field)" :required="in_array($field, ['code', 'name'])" /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                </div>
                @if ($branch->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Sede activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <x-input-error :messages="$errors->get('organization')" class="mt-3" />
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar sede') }}</x-primary-button><a href="{{ route('admin.organization.branches.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
