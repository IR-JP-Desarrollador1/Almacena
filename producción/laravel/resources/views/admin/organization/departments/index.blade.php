<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">{{ __('Áreas solicitantes') }}</h1>
            @if (Auth::user()->hasPermission('departments.manage'))
                <a data-admin-form-modal href="{{ route('admin.organization.departments.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('Crear área') }}</a>
            @endif
        </div>
    </x-slot>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @include('admin.organization._tabs')
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Código') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Área') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Jefe aprobador') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Requisiciones') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Estado') }}</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($departments as $department)
                        <tr><td class="px-6 py-4 text-sm">{{ $department->code }}</td><td class="px-6 py-4 text-sm">{{ $department->name }}</td><td class="px-6 py-4 text-sm">{{ trim($department->manager->name.' '.$department->manager->last_name) }}</td><td class="px-6 py-4 text-sm">{{ $department->requisitions_count }}</td><td class="px-6 py-4 text-sm">{{ $department->is_active ? __('Activa') : __('Inactiva') }}</td><td class="px-6 py-4 text-right text-sm">@if (Auth::user()->hasPermission('departments.manage'))<a data-admin-form-modal class="text-indigo-700 underline" href="{{ route('admin.organization.departments.edit', $department) }}">{{ __('Editar') }}</a>@endif</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No hay áreas solicitantes configuradas.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $departments->links() }}
    </main>
</x-app-layout>
