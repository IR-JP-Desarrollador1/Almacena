@csrf
@php
    $selectedWarehouseIds = array_map('strval', old(
        'warehouses',
        $user->exists ? $user->warehouses->pluck('id')->all() : [],
    ));
@endphp
<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Nombres')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="last_name" :value="__('Apellidos')" />
        <x-text-input id="last_name" name="last_name" class="mt-1 block w-full" :value="old('last_name', $user->last_name)" required />
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="email" :value="__('Correo electrónico')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="phone" :value="__('Teléfono')" />
        <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>
</div>

<div class="mt-5">
    <x-input-label for="department_id" :value="__('Área solicitante')" />
    <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        <option value="">{{ __('Sin área asignada') }}</option>
        @foreach ($departments as $department)
            <option value="{{ $department->id }}" @selected((string) old('department_id', $user->department_id) === (string) $department->id)>{{ $department->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
</div>

<fieldset class="mt-6">
    <legend class="text-sm font-medium text-gray-700">{{ __('Bodegas autorizadas') }}</legend>
    <input type="hidden" name="warehouse_assignments_submitted" value="1">
    <p class="mt-1 text-xs text-gray-500">{{ __('El usuario podrá operar únicamente en las bodegas seleccionadas. Las cuentas con permiso de administración de empresa tienen acceso global.') }}</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @forelse ($warehouses as $warehouse)
            <label class="flex items-start gap-3 rounded-md border border-gray-200 p-3">
                <input type="checkbox" name="warehouses[]" value="{{ $warehouse->id }}" @checked(in_array((string) $warehouse->id, $selectedWarehouseIds, true)) class="mt-1 rounded border-gray-300 text-indigo-600">
                <span>
                    <span class="block text-sm font-medium text-gray-900">{{ $warehouse->name }}{{ $warehouse->is_active ? '' : ' ('.__('Inactiva').')' }}</span>
                    <span class="block text-xs text-gray-500">{{ $warehouse->branch->name }} · {{ $warehouse->code }}</span>
                </span>
            </label>
        @empty
            <p class="text-sm text-gray-500">{{ __('No hay bodegas activas disponibles para asignar.') }}</p>
        @endforelse
    </div>
    <x-input-error :messages="$errors->get('warehouses')" class="mt-2" />
    <x-input-error :messages="$errors->get('warehouses.*')" class="mt-2" />
</fieldset>

@if ($user->exists)
    <fieldset class="mt-6 rounded-lg border border-gray-200 p-4">
        <legend class="px-2 text-sm font-semibold text-gray-800">{{ __('Cambiar contraseña') }}</legend>
        <p class="mb-4 text-sm text-gray-600">{{ __('Déjala en blanco para conservar la contraseña actual. El usuario tendrá que iniciar sesión nuevamente después del cambio.') }}</p>
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Nueva contraseña')" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirmar nueva contraseña')" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            </div>
        </div>
    </fieldset>
@endif

<fieldset class="mt-6">
    <legend class="text-sm font-medium text-gray-700">{{ __('Perfiles asignados') }}</legend>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach ($roles as $role)
            <label class="flex items-start gap-3 rounded-md border border-gray-200 p-3">
                <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array((string) $role->id, array_map('strval', old('roles', $user->exists ? $user->roles->pluck('id')->all() : [])), true)) class="mt-1 rounded border-gray-300 text-indigo-600">
                <span><span class="block text-sm font-medium text-gray-900">{{ $role->name }}</span><span class="block text-xs text-gray-500">{{ $role->description }}</span></span>
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('roles')" class="mt-2" />
    <x-input-error :messages="$errors->get('roles.*')" class="mt-2" />
</fieldset>

@if ($user->exists)
    <div class="mt-6">
        <x-input-label for="status" :value="__('Estado de la cuenta')" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @if ($user->status === \App\Models\User::STATUS_PENDING)
                <option value="pending" @selected(old('status', $user->status) === 'pending')>{{ __('Pendiente de activación') }}</option>
            @endif
            @foreach (['active' => 'Activo', 'blocked' => 'Bloqueado', 'inactive' => 'Inactivo'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $user->status) === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
@endif

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>{{ $user->exists ? __('Guardar cambios') : __('Crear e invitar') }}</x-primary-button>
    <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancelar') }}</a>
</div>
