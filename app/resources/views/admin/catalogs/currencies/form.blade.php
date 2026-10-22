<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ $currency->exists ? __('Editar moneda') : __('Crear moneda') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.catalogs._tabs')
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ $currency->exists ? route('admin.catalogs.currencies.update', $currency) : route('admin.catalogs.currencies.store') }}">
                @csrf
                @if ($currency->exists) @method('PUT') @endif
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="code" :value="__('Código ISO 4217')" />
                        @if ($currency->exists)
                            <x-text-input id="code" class="mt-1 block w-full bg-gray-100" :value="$currency->code" readonly />
                            <p class="mt-1 text-xs text-gray-500">{{ __('El código queda fijo para conservar la trazabilidad histórica.') }}</p>
                        @else
                            <x-text-input id="code" name="code" class="mt-1 block w-full uppercase" :value="old('code')" required minlength="3" maxlength="3" pattern="[A-Z]{3}" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        @endif
                    </div>
                    <div>
                        <x-input-label for="name" :value="__('Nombre')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $currency->name)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="symbol" :value="__('Símbolo')" />
                        <x-text-input id="symbol" name="symbol" class="mt-1 block w-full" :value="old('symbol', $currency->symbol)" maxlength="10" />
                        <x-input-error :messages="$errors->get('symbol')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="decimal_places" :value="__('Decimales habituales')" />
                        <x-text-input id="decimal_places" name="decimal_places" type="number" min="0" max="4" class="mt-1 block w-full" :value="old('decimal_places', $currency->decimal_places ?? 2)" required />
                        <x-input-error :messages="$errors->get('decimal_places')" class="mt-2" />
                    </div>
                </div>
                @if ($currency->exists)
                    <label class="mt-5 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $currency->is_active)) class="rounded border-gray-300 text-indigo-600"><span class="text-sm">{{ __('Moneda activa') }}</span></label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                @endif
                <div class="mt-6 flex gap-3"><x-primary-button>{{ __('Guardar moneda') }}</x-primary-button><a href="{{ route('admin.catalogs.currencies.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a></div>
            </form>
        </div>
    </main>
</x-app-layout>
