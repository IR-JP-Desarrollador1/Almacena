<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $department->exists ? __('Editar área solicitante') : __('Crear área solicitante') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.organization._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="mb-5 text-sm text-gray-600">{{ __('El jefe asignado recibirá y decidirá las requisiciones enviadas por los usuarios de esta área. Asígnale el perfil Compras o el permiso específico de decisión de requisiciones.') }}</p>
            <form method="POST" action="{{ $department->exists ? route('admin.organization.departments.update', $department) : route('admin.organization.departments.store') }}">
                @csrf
                @if ($department->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div><x-input-label for="code" :value="__('Código')" /><x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $department->code)" required /><x-input-error :messages="$errors->get('code')" class="mt-2" /></div>
                    <div><x-input-label for="name" :value="__('Nombre del área')" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $department->name)" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                    <div class="md:col-span-2"><x-input-label for="manager_user_id" :value="__('Jefe aprobador')" /><select id="manager_user_id" name="manager_user_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($managers as $manager)<option value="{{ $manager->id }}" @selected((string) old('manager_user_id', $department->manager_user_id) === (string) $manager->id)>{{ trim($manager->name.' '.$manager->last_name) }} — {{ $manager->email }}</option>@endforeach</select><x-input-error :messages="$errors->get('manager_user_id')" class="mt-2" /><p class="mt-1 text-xs text-gray-500">{{ __('Solo se muestran usuarios activos con permiso para decidir requisiciones.') }}</p></div>
                </div>
                @if ($department->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Área activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar área') }}</x-primary-button><a href="{{ route('admin.organization.departments.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
