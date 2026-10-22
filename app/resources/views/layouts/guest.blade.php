<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Almacena') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-layout">
            <section class="brand-panel" aria-label="Almacena">
                <a class="brand-lockup" href="{{ route('login') }}" aria-label="Almacena, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                            <path d="m20 11.5 9 4.8-9 4.8-9-4.8 9-4.8Z" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M11 16.3v9.2l9 4.8 9-4.8v-9.2M20 21.1v9.2" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>Almacena<span class="brand-period">.</span></span>
                </a>

                <div class="brand-message">
                    <span class="eyebrow eyebrow-light">GESTIÓN DE INVENTARIOS</span>
                    <h1>Todo tu inventario, bajo control.</h1>
                    <p>Administra productos, ubicaciones, compras y usuarios desde un espacio centralizado.</p>
                </div>

                <div class="brand-footer">
                    <span class="status-dot" aria-hidden="true"></span>
                    Plataforma central
                </div>
                <div class="brand-orbit brand-orbit-one" aria-hidden="true"></div>
                <div class="brand-orbit brand-orbit-two" aria-hidden="true"></div>
            </section>

            <section class="auth-panel">
                <div class="auth-card">
                    <div class="mobile-brand brand-lockup" aria-hidden="true">
                        <span class="brand-mark">
                            <svg viewBox="0 0 40 40" fill="none">
                                <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                                <path d="m20 11.5 9 4.8-9 4.8-9-4.8 9-4.8Z" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M11 16.3v9.2l9 4.8 9-4.8v-9.2M20 21.1v9.2" stroke="#183A33" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>Almacena<span class="brand-period">.</span></span>
                    </div>
                    {{ $slot }}
                </div>
                <footer class="auth-footer">
                    <span>© {{ date('Y') }} {{ config('app.name', 'Almacena') }}</span>
                    <span>Acceso centralizado</span>
                </footer>
            </section>
        </main>
    </body>
</html>
