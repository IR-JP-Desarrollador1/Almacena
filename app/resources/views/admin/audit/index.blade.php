<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Auditoría administrativa') }}</h1></x-slot>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-lg bg-white shadow">
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
        </div>
    </main>
</x-app-layout>
