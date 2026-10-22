<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $role->exists ? __('Editar perfil') : __('Crear perfil') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
                @csrf
                @if ($role->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Nombre del perfil')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $role->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="description" :value="__('Descripción')" />
                        <x-text-input id="description" name="description" class="mt-1 block w-full" :value="old('description', $role->description)" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                </div>
                @if ($role->exists)
                    <div class="mt-5">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $role->is_active)) class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">{{ __('Perfil activo') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                    </div>
                @endif

                <fieldset class="mt-7">
                    <legend class="text-sm font-medium text-gray-900">{{ __('Permisos') }}</legend>
                    @foreach ($permissions as $module => $modulePermissions)
                        <div class="mt-4 rounded-md border border-gray-200 p-4">
                            <h3 class="mb-3 text-sm font-semibold capitalize text-gray-700">{{ $module }}</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($modulePermissions as $permission)
                                    <label class="flex items-start gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array((string) $permission->id, array_map('strval', old('permissions', $role->exists ? $role->permissions->pluck('id')->all() : [])), true)) class="mt-1 rounded border-gray-300 text-indigo-600">
                                        <span><span class="block font-medium text-gray-800">{{ $permission->name }}</span><span class="text-xs text-gray-500">{{ $permission->code }}</span>@if ($permission->description)<span class="mt-1 block text-xs text-gray-500">{{ $permission->description }}</span>@endif</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                </fieldset>
                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>{{ __('Guardar perfil') }}</x-primary-button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
