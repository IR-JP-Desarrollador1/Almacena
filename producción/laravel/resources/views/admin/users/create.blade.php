<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Crear usuario') }}</h1></x-slot>
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="mb-6 text-sm text-gray-600">{{ __('Se creará una cuenta pendiente y se enviará un enlace para establecer la contraseña y activar el acceso.') }}</p>
            <form method="POST" action="{{ route('admin.users.store') }}">
                @include('admin.users._form')
            </form>
        </div>
    </main>
</x-app-layout>
