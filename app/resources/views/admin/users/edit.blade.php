<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">{{ __('Editar usuario') }} — {{ $user->name }} {{ $user->last_name }}</h1></x-slot>
    <main class="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
        @if (session('warning'))<div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">{{ session('warning') }}</div>@endif
        <div class="rounded-lg bg-white p-6 shadow">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @method('PUT')
                @include('admin.users._form')
            </form>
        </div>
        @if ($user->status === \App\Models\User::STATUS_PENDING)
            <form method="POST" action="{{ route('admin.users.invitation', $user) }}" class="rounded-lg bg-white p-6 shadow">
                @csrf
                <p class="mb-4 text-sm text-gray-600">{{ __('La cuenta está pendiente de activación.') }}</p>
                <x-primary-button>{{ __('Reenviar invitación') }}</x-primary-button>
            </form>
        @endif
    </main>
</x-app-layout>
