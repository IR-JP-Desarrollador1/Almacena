<!DOCTYPE html>
<html lang="es" data-app-timezone="{{ config('app.timezone') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#183a33">
        <meta name="description" content="Registra tu asistencia en Controla con selfie y ubicación.">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="manifest" href="../mobile-pwa.webmanifest">
        <link rel="icon" href="../mobile-pwa-icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="../mobile-pwa-icon.svg">
        <title>Controla — Asistencia personal</title>
        @vite(['resources/css/app.css', 'resources/js/mobile-pwa.js'])
    </head>
    <body>
        <main class="person-portal mobile-pwa" id="pwa-login-view">
            <section class="person-portal-card mobile-pwa-card">
                <a class="brand-lockup person-portal-brand" href="./" aria-label="Controla, asistencia personal">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                            <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>controla<span class="brand-period">.</span></span>
                </a>
                <span class="eyebrow">ASISTENCIA PERSONAL</span>
                <h1>Inicia sesión</h1>
                <p class="auth-description">Usa las credenciales de tu cuenta personal para registrar asistencia desde este dispositivo.</p>
                <p class="auth-description">Este acceso es solo para personas. Los usuarios del panel deben ingresar desde <a href="/login">/login</a>.</p>
                <form class="mobile-pwa-login" id="pwa-login-form">
                    <label for="pwa-email">Correo electrónico</label>
                    <input id="pwa-email" name="email" type="email" autocomplete="username" maxlength="255" required>
                    <label for="pwa-password">Contraseña</label>
                    <input id="pwa-password" name="password" type="password" autocomplete="current-password" required>
                    <button class="button button-primary button-wide" id="pwa-login-button" type="submit">Iniciar sesión</button>
                </form>
                <p class="form-message" id="pwa-login-message" role="alert" hidden></p>
                <p class="mobile-pwa-security">Instálala desde el menú del navegador o usa el botón cuando aparezca. La cámara y la ubicación requieren HTTPS y se solicitan al marcar.</p>
                <button class="button button-secondary button-wide mobile-pwa-install" id="pwa-install-button" type="button" hidden>Instalar aplicación</button>
            </section>
        </main>

        <main class="person-portal mobile-pwa" id="pwa-attendance-view" hidden>
            <section class="person-portal-card mobile-pwa-card">
                <header class="mobile-pwa-header">
                    <a class="brand-lockup person-portal-brand" href="./" aria-label="Controla, asistencia personal">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 40 40" fill="none">
                                <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                                <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>controla<span class="brand-period">.</span></span>
                    </a>
                    <button class="button button-secondary mobile-pwa-logout" id="pwa-logout-button" type="button">Cerrar sesión</button>
                </header>
                <span class="eyebrow">ASISTENCIA</span>
                <h1>Hola, <span id="pwa-person-name"></span></h1>
                <p class="auth-description" id="pwa-person-email"></p>
                <section class="person-attendance" aria-labelledby="pwa-device-title">
                    <div class="person-attendance-heading">
                        <span class="eyebrow">DISPOSITIVO MÓVIL</span>
                        <h2 id="pwa-device-title">Este equipo</h2>
                        <p>Vincula este dispositivo a tu cuenta para que quede registrado en Controla.</p>
                    </div>
                    <button class="button button-secondary button-wide" id="pwa-device-register-button" type="button">Vincular este dispositivo</button>
                    <p class="attendance-message" id="pwa-device-message" role="status" aria-live="polite" hidden></p>
                </section>
                <section class="person-attendance" aria-labelledby="pwa-attendance-title">
                    <div class="person-attendance-heading">
                        <span class="eyebrow">PORTAL PERSONAL</span>
                        <h2 id="pwa-attendance-title">Mis marcaciones</h2>
                        <p>La marcación incluye hora oficial, sede, ubicación y selfie. El navegador solicitará los permisos necesarios al continuar.</p>
                    </div>
                    <label class="person-site-field" for="pwa-site-select">
                        Sede autorizada
                        <select id="pwa-site-select"></select>
                    </label>
                    <label class="person-site-field" for="pwa-zone-select">
                        Zona (opcional)
                        <select id="pwa-zone-select"></select>
                    </label>
                    <p class="attendance-location-distance" id="pwa-location-distance" aria-live="polite"></p>
                    <p class="attendance-current-state" id="pwa-current-state" aria-live="polite"></p>
                    <p class="attendance-schedule" id="pwa-schedule"></p>
                    <label class="attendance-novelty-field" id="pwa-novelty-field" for="pwa-novelty" hidden>
                        Novedad obligatoria
                        <textarea id="pwa-novelty" rows="3" maxlength="2000" placeholder="Describe el motivo de la llegada tarde o del tiempo extra"></textarea>
                    </label>
                    <div class="attendance-actions">
                        <button class="button button-primary" id="pwa-entry-button" type="button">Registrar entrada</button>
                        <button class="button button-secondary" id="pwa-exit-button" type="button">Registrar salida</button>
                    </div>
                    <div class="attendance-informational">
                        <label for="pwa-informational-type">Evento informativo</label>
                        <div class="attendance-informational-controls">
                            <select id="pwa-informational-type"></select>
                            <button class="button button-secondary" id="pwa-informational-button" type="button">Registrar</button>
                        </div>
                    </div>
                    <p class="attendance-message" id="pwa-attendance-message" role="status" aria-live="polite" hidden></p>
                    <button class="button button-secondary button-wide mobile-pwa-retry" id="pwa-retry-button" type="button" hidden>Reintentar último envío</button>
                    <div class="attendance-history">
                        <div class="mobile-pwa-history-heading">
                            <h3>Historial reciente</h3>
                            <button class="button button-secondary" id="pwa-refresh-button" type="button">Actualizar</button>
                        </div>
                        <div id="pwa-attendance-history"></div>
                    </div>
                    <button class="button button-secondary button-wide mobile-pwa-install" id="pwa-install-dashboard-button" type="button" hidden>Instalar aplicación</button>
                </section>
            </section>
        </main>

        <dialog class="person-camera-dialog" id="pwa-camera-dialog" aria-labelledby="pwa-camera-title">
            <div class="person-camera-content">
                <h2 id="pwa-camera-title">Toma tu selfie</h2>
                <video id="pwa-camera-preview" autoplay playsinline></video>
                <div class="attendance-actions">
                    <button class="button button-secondary" id="pwa-cancel-capture" type="button">Cancelar</button>
                    <button class="button button-primary" id="pwa-capture-selfie" type="button">Tomar selfie y registrar</button>
                </div>
            </div>
        </dialog>
    </body>
</html>
