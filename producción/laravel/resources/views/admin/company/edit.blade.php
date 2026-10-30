<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Datos de la empresa') }}</h1></x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        <x-input-error :messages="$errors->get('organization')" class="rounded-md bg-red-50 p-4" />
        <div class="rounded-lg bg-white p-6 shadow">
            <p class="mb-6 text-sm text-gray-600">{{ __('El MVP administra una empresa. La estructura de sedes y bodegas se configurará en el módulo organizacional.') }}</p>
            <form method="POST" action="{{ route('admin.company.update') }}">
                @csrf
                @method('PUT')
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-input-label for="base_currency_id" :value="__('Moneda base de la empresa')" />
                        <select id="base_currency_id" name="base_currency_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">{{ __('Selecciona una moneda') }}</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" @selected((string) old('base_currency_id', $company->base_currency_id) === (string) $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Esta moneda se utiliza para convertir y conservar el costo base de las compras. No podrá cambiarse después de crear órdenes.') }}</p>
                        <x-input-error :messages="$errors->get('base_currency_id')" class="mt-2" />
                    </div>
                    @foreach ([
                        'tax_id' => 'NIT / identificación tributaria',
                        'legal_name' => 'Razón social',
                        'address' => 'Dirección principal',
                        'phone' => 'Teléfono',
                        'email' => 'Correo corporativo',
                        'legal_representative_name' => 'Representante legal',
                        'country' => 'País',
                        'administrative_division' => 'Departamento / estado',
                        'city' => 'Ciudad / municipio',
                    ] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="__($label)" />
                            <x-text-input :id="$field" :name="$field" :type="$field === 'email' ? 'email' : 'text'" class="mt-1 block w-full" :value="old($field, $company->$field)" :required="! in_array($field, ['email', 'legal_representative_name'])" />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
                <div class="mt-6"><x-primary-button>{{ __('Guardar empresa') }}</x-primary-button></div>
            </form>
        </div>
    </main>
</x-app-layout>
