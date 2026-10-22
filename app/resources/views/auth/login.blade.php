<x-guest-layout>
    <span class="eyebrow">{{ __('ACCESO CENTRALIZADO') }}</span>
    <h2>{{ __('Bienvenido de nuevo') }}</h2>
    <p class="auth-description">{{ __('Ingresa con la cuenta que te asignó el administrador.') }}</p>

    <x-auth-session-status class="form-message session-message" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

        <label class="field-label" for="email">{{ __('Correo electrónico') }}</label>
        <div class="input-wrap">
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nombre@empresa.com" />
        </div>
        <x-input-error :messages="$errors->get('email')" class="form-message" />

        <div class="field-heading">
            <label class="field-label" for="password">{{ __('Contraseña') }}</label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">{{ __('¿Olvidaste tu contraseña?') }}</a>
            @endif
        </div>
        <div class="input-wrap">
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña" />
        </div>
        <x-input-error :messages="$errors->get('password')" class="form-message" />

        <label class="remember-option">
            <input id="remember_me" type="checkbox" name="remember">
            <span>{{ __('Recordarme') }}</span>
        </label>

        <button class="button button-primary button-wide" type="submit">
            <span>{{ __('Iniciar sesión') }}</span>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </form>

    <div class="secure-note">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 2.5 16 5v4.2c0 3.6-2.4 6.3-6 7.8-3.6-1.5-6-4.2-6-7.8V5l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            <path d="m7.5 9.8 1.7 1.7 3.4-3.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>{{ __('Acceso protegido para personal autorizado') }}</span>
    </div>
</x-guest-layout>
