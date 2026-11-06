<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Actualizar base de datos') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <x-input-error :messages="$errors->get('database_update')" class="rounded-md bg-red-50 p-4" />

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Migraciones pendientes') }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Esta acción ejecuta las migraciones pendientes de Laravel. No ejecuta seeders ni revierte cambios.') }}
                </p>
            </div>

            @if ($pendingMigrations === [])
                <p class="mt-5 rounded-md bg-gray-50 p-4 text-sm text-gray-700" role="status">
                    {{ __('No hay migraciones pendientes para aplicar.') }}
                </p>
            @else
                <ul class="mt-5 space-y-2 rounded-md bg-gray-50 p-4 font-mono text-sm text-gray-700">
                    @foreach ($pendingMigrations as $migration)
                        <li class="break-all">{{ $migration }}</li>
                    @endforeach
                </ul>

                <p class="mt-5 text-sm text-amber-800">
                    {{ __('Realiza un respaldo reciente antes de cambiar el esquema de la base de datos.') }}
                </p>

                <form method="POST" action="{{ route('admin.database-update.run') }}" class="mt-5 border-t border-gray-100 pt-5">
                    @csrf
                    <label for="confirm_update" class="flex items-start gap-3 text-sm text-gray-700">
                        <input id="confirm_update" name="confirm_update" type="checkbox" value="1" required
                            class="mt-0.5 rounded border-gray-300 text-green-700 shadow-sm focus:ring-green-700">
                        <span>{{ __('Confirmo que deseo ejecutar las migraciones pendientes.') }}</span>
                    </label>
                    <x-input-error :messages="$errors->get('confirm_update')" class="mt-2" />
                    <div class="mt-5">
                        <x-primary-button>{{ __('Actualizar base de datos') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </section>

        @if (session('migration_output'))
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Resultado de la operación') }}</h2>
                <pre class="mt-3 overflow-x-auto whitespace-pre-wrap rounded-md bg-gray-50 p-4 text-sm text-gray-700">{{ session('migration_output') }}</pre>
            </section>
        @endif
    </main>
</x-app-layout>
