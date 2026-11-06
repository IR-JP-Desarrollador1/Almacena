<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('Auditoría administrativa') }}</h1>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if (Auth::user()->hasPermission('audit.manage'))
            <section class="grid gap-6 lg:grid-cols-2" aria-label="{{ __('Mantenimiento de auditoría') }}">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Borrar por rango de fechas') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Selecciona las fechas de inicio y fin. Ambas fechas se incluyen en la búsqueda.') }}</p>

                    <form method="GET" action="{{ route('admin.audit.index') }}" class="mt-5 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="from_date" :value="__('Desde')" />
                                <x-text-input id="from_date" name="from_date" type="date" class="mt-1 block w-full"
                                    :value="request('from_date')" required />
                                <x-input-error :messages="$errors->get('from_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="to_date" :value="__('Hasta')" />
                                <x-text-input id="to_date" name="to_date" type="date" class="mt-1 block w-full"
                                    :value="request('to_date')" required />
                                <x-input-error :messages="$errors->get('to_date')" class="mt-2" />
                            </div>
                        </div>
                        <x-primary-button>{{ __('Consultar registros') }}</x-primary-button>
                    </form>

                    @if ($rangeCount !== null)
                        <p class="mt-5 rounded-md bg-gray-50 p-4 text-sm text-gray-700" role="status">
                            {{ __('Registros encontrados en el rango: :count', ['count' => $rangeCount]) }}
                        </p>

                        @if ($rangeCount > 0)
                            <form method="POST" action="{{ route('admin.audit.maintenance.range') }}" class="mt-4 border-t border-gray-100 pt-4">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="from_date" value="{{ request('from_date') }}">
                                <input type="hidden" name="to_date" value="{{ request('to_date') }}">
                                <label for="confirm_range_deletion" class="flex items-start gap-3 text-sm text-gray-700">
                                    <input id="confirm_range_deletion" name="confirm_deletion" type="checkbox" value="1" required
                                        class="mt-0.5 rounded border-gray-300 text-red-700 shadow-sm focus:ring-red-700">
                                    <span>{{ __('Confirmo la eliminación permanente de estos :count registros.', ['count' => $rangeCount]) }}</span>
                                </label>
                                <x-input-error :messages="$errors->get('confirm_deletion')" class="mt-2" />
                                <button type="submit" class="mt-4 inline-flex items-center rounded-md border border-red-700 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">
                                    {{ __('Borrar registros del rango') }}
                                </button>
                            </form>
                        @endif
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Borrar registros antiguos') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Elimina registros con más de 30 días de antigüedad. Los más recientes se conservan.') }}</p>
                    <p class="mt-5 rounded-md bg-gray-50 p-4 text-sm text-gray-700" role="status">
                        {{ __('Registros que cumplen este criterio: :count', ['count' => $expiredCount]) }}
                    </p>

                    @if ($expiredCount > 0)
                        <form method="POST" action="{{ route('admin.audit.maintenance.expired') }}" class="mt-4 border-t border-gray-100 pt-4">
                            @csrf
                            @method('DELETE')
                            <label for="confirm_expired_deletion" class="flex items-start gap-3 text-sm text-gray-700">
                                <input id="confirm_expired_deletion" name="confirm_deletion" type="checkbox" value="1" required
                                    class="mt-0.5 rounded border-gray-300 text-red-700 shadow-sm focus:ring-red-700">
                                <span>{{ __('Confirmo la eliminación permanente de los registros con más de 30 días.') }}</span>
                            </label>
                            <x-input-error :messages="$errors->get('confirm_deletion')" class="mt-2" />
                            <button type="submit" class="mt-4 inline-flex items-center rounded-md border border-red-700 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">
                                {{ __('Borrar registros de más de 30 días') }}
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg bg-white shadow" aria-label="{{ __('Registros de auditoría') }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr><th class="px-4 py-3">{{ __('Fecha') }}</th><th class="px-4 py-3">{{ __('Usuario') }}</th><th class="px-4 py-3">{{ __('Acción') }}</th><th class="px-4 py-3">{{ __('Entidad') }}</th><th class="px-4 py-3">{{ __('Cambios') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $regional->dateTime($log->created_at) }}</td>
                                <td class="px-4 py-3">{{ $log->user?->email ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $log->action }}</td>
                                <td class="px-4 py-3">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                                <td class="max-w-xl px-4 py-3"><pre class="whitespace-pre-wrap break-words text-xs">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('Aún no hay acciones administrativas registradas.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4">{{ $logs->links() }}</div>
        </section>
    </main>
</x-app-layout>
