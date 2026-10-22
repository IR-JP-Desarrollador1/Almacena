<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Importar productos') }}</h1>
            <a href="{{ route('admin.catalogs.products.index') }}" class="text-sm font-medium text-indigo-700 underline">{{ __('Volver a productos') }}</a>
        </div>
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @include('admin.catalogs._tabs')

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="import-file-heading">
            <h2 id="import-file-heading" class="text-lg font-semibold text-gray-900">{{ __('Archivo de productos') }}</h2>
            <p class="mt-2 text-sm text-gray-600">{{ __('Se importan las filas válidas y se omiten los duplicados. El resultado descargable incluye el estado y el detalle de cada fila.') }}</p>

            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.catalogs.products.import') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="file" class="block text-sm font-medium text-gray-700">{{ __('Archivo CSV o XLSX') }}</label>
                    <div class="relative mt-1 flex min-h-11 items-center overflow-hidden rounded-md border border-gray-300 bg-white text-sm text-gray-700 focus-within:ring-2 focus-within:ring-indigo-500">
                        <input id="file" name="file" type="file" accept=".csv,.txt,.xlsx" required aria-describedby="file-name file-hint" data-empty-label="{{ __('Ningún archivo seleccionado') }}" class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0">
                        <span aria-hidden="true" class="border-r border-gray-300 bg-gray-100 px-4 py-2 font-medium text-gray-700">{{ __('Seleccionar archivo') }}</span>
                        <span id="file-name" role="status" aria-live="polite" aria-atomic="true" class="min-w-0 truncate px-3 py-2 text-gray-600">{{ __('Ningún archivo seleccionado') }}</span>
                    </div>
                    <p id="file-hint" class="mt-2 text-sm text-gray-500">{{ __('Máximo: 5 MB y 1000 filas de datos.') }}</p>
                </div>

                <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                    <h3 class="font-medium text-gray-900">{{ __('Formato requerido') }}</h3>
                    <p class="mt-1">{{ __('La primera fila debe usar exactamente estos encabezados en este orden:') }}</p>
                    <code class="mt-2 block break-all rounded bg-white px-3 py-2 text-xs">code, name, description, unit_code, tracks_expiration, is_active</code>
                    <ul class="mt-3 list-inside list-disc space-y-1">
                        <li>{{ __('La unidad se identifica por su código, no por su nombre.') }}</li>
                        <li>{{ __('Para control de vencimiento: sí/no, true/false o 1/0.') }}</li>
                        <li>{{ __('Para el estado: activo/inactivo, true/false o 1/0.') }}</li>
                        <li>{{ __('Los productos activos requieren una unidad activa.') }}</li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('Importar y descargar resultado') }}</button>
                    <a href="{{ route('admin.catalogs.products.import.template', 'csv') }}" class="text-sm text-indigo-700 underline">{{ __('Plantilla CSV') }}</a>
                    <a href="{{ route('admin.catalogs.products.import.template', 'xlsx') }}" class="text-sm text-indigo-700 underline">{{ __('Plantilla Excel') }}</a>
                </div>
            </form>
        </section>
    </main>
    <script>
        document.getElementById('file').addEventListener('change', (event) => {
            const input = event.currentTarget;
            document.getElementById('file-name').textContent = input.files.length
                ? input.files[0].name
                : input.dataset.emptyLabel;
        });
    </script>
</x-app-layout>
