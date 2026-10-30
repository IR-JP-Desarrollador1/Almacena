<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Administrar usuarios') }}</h1>
            @if (Auth::user()->hasPermission('users.manage'))
                <a data-admin-form-modal href="{{ route('admin.users.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('Crear usuario') }}</a>
            @endif
        </div>
    </x-slot>

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">{{ session('warning') }}</div>
        @endif

        <form method="GET" class="flex gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="{{ __('Buscar por nombre o correo') }}" class="w-full max-w-md rounded-md border-gray-300 shadow-sm">
            <button class="rounded-md border border-gray-300 px-4 py-2 text-sm">{{ __('Buscar') }}</button>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-5 py-3">{{ __('Usuario') }}</th>
                            <th class="px-5 py-3">{{ __('Área') }}</th>
                            <th class="px-5 py-3">{{ __('Perfiles') }}</th>
                            <th class="px-5 py-3">{{ __('Estado') }}</th>
                            <th class="px-5 py-3">{{ __('Último acceso') }}</th>
                            <th class="px-5 py-3">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-900">{{ $user->name }} {{ $user->last_name }}</div>
                                    <div class="text-gray-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-5 py-4">{{ $user->department?->name ?? '—' }}</td>
                                <td class="px-5 py-4">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                                <td class="px-5 py-4">{{ ['active' => 'Activo', 'pending' => 'Pendiente', 'blocked' => 'Bloqueado', 'inactive' => 'Inactivo'][$user->status] ?? $user->status }}</td>
                                <td class="px-5 py-4">{{ $regional->dateTime($user->last_login_at) }}</td>
                                <td class="px-5 py-4">
                                    @if (Auth::user()->hasPermission('users.manage'))
                                        <div class="flex flex-wrap gap-3">
                                            <a data-admin-form-modal class="text-indigo-700 underline" href="{{ route('admin.users.edit', $user) }}">{{ __('Editar') }}</a>
                                            @if ($user->status === \App\Models\User::STATUS_PENDING)
                                                <form method="POST" action="{{ route('admin.users.invitation', $user) }}">
                                                    @csrf
                                                    <button class="text-indigo-700 underline">{{ __('Reenviar invitación') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">{{ __('No hay usuarios para mostrar.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4">{{ $users->links() }}</div>
        </div>
    </main>
</x-app-layout>
