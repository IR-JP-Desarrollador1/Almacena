<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Perfiles y permisos') }}</h1>
            <a data-admin-form-modal href="{{ route('admin.roles.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear perfil') }}</a>
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($roles as $role)
                <article class="rounded-lg bg-white p-5 shadow">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $role->name }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $role->description }}</p>
                        </div>
                        <a data-admin-form-modal href="{{ route('admin.roles.edit', $role) }}" class="text-sm text-indigo-700 underline">{{ __('Editar') }}</a>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">{{ __('Usuarios: :count', ['count' => $role->users_count]) }} · {{ $role->is_active ? __('Activo') : __('Inactivo') }}</p>
                    <p class="mt-3 text-sm text-gray-700">{{ $role->permissions->pluck('name')->join(', ') ?: __('Sin permisos asignados') }}</p>
                </article>
            @endforeach
        </div>
    </main>
</x-app-layout>
