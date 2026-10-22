<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Configuración regional') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.regional-settings.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('País e idioma') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Estos ajustes se aplican a todos los usuarios de :company.', ['company' => $company->legal_name]) }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="regional_country_code" :value="__('País de operación')" />
                        <select id="regional_country_code" name="regional_country_code" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($countries as $code => $country)
                                <option value="{{ $code }}" @selected(old('regional_country_code', $company->regional_country_code) === $code)>{{ __($country['name']) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('regional_country_code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="language" :value="__('Idioma de la interfaz')" />
                        <select id="language" name="language" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($languages as $code => $label)
                                <option value="{{ $code }}" @selected(old('language', $company->language) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('language')" class="mt-2" />
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Formatos regionales') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Puedes conservar los valores sugeridos para el país o ajustarlos a las convenciones de tu empresa.') }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="decimal_separator" :value="__('Separador decimal')" />
                        <select id="decimal_separator" name="decimal_separator" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($decimalSeparators as $value => $label)
                                <option value="{{ $value }}" @selected(old('decimal_separator', $company->decimal_separator) === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('decimal_separator')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="thousands_separator" :value="__('Separador de miles')" />
                        <select id="thousands_separator" name="thousands_separator" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($thousandsSeparators as $value => $label)
                                <option value="{{ $value }}" @selected(old('thousands_separator', $company->thousands_separator) === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('thousands_separator')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="date_format" :value="__('Formato de fecha')" />
                        <select id="date_format" name="date_format" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($dateFormats as $value => $label)
                                <option value="{{ $value }}" @selected(old('date_format', $company->date_format) === $value)>{{ __($label) }} — {{ \Carbon\Carbon::create(2026, 8, 17)->format($value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('date_format')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="time_format" :value="__('Formato de hora')" />
                        <select id="time_format" name="time_format" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring-green-700">
                            @foreach ($timeFormats as $value => $label)
                                <option value="{{ $value }}" @selected(old('time_format', $company->time_format) === $value)>{{ __($label) }} — {{ \Carbon\Carbon::createFromTime(17, 45)->format($value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('time_format')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5">
                    <p class="text-sm text-gray-600">{{ __('Ejemplo') }}: <span id="regional-number-preview" class="font-medium text-gray-900"></span></p>
                    <x-primary-button>{{ __('Guardar configuración') }}</x-primary-button>
                </div>
            </section>
        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const countries = @json($countries);
            const country = document.getElementById('regional_country_code');
            const language = document.getElementById('language');
            const decimal = document.getElementById('decimal_separator');
            const thousands = document.getElementById('thousands_separator');
            const preview = document.getElementById('regional-number-preview');

            const updatePreview = () => {
                const decimalMark = decimal.value;
                const thousandsMark = thousands.value === ' ' ? '\u00a0' : thousands.value;
                preview.textContent = `1${thousandsMark}234${thousandsMark}567${decimalMark}89`;
            };

            country.addEventListener('change', () => {
                const preset = countries[country.value];
                language.value = preset.language;
                decimal.value = preset.decimal;
                thousands.value = preset.thousands;
                document.getElementById('date_format').value = preset.date;
                document.getElementById('time_format').value = preset.time;
                updatePreview();
            });
            decimal.addEventListener('change', updatePreview);
            thousands.addEventListener('change', updatePreview);
            updatePreview();
        });
    </script>
</x-app-layout>
