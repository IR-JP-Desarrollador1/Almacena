<!DOCTYPE html>
<html lang="es" data-app-timezone="{{ config('app.timezone') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f4f6f3">
        <title>Controla — Control de ingreso</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { min-height: 100vh; margin: 0; color: #172b28; background: #f4f6f3; font-family: system-ui, sans-serif; }
                .auth-layout { min-height: 100vh; display: grid; place-items: center; }
                .brand-panel { display: none; }
                .auth-panel { padding: 24px; }
                .auth-card { width: min(100%, 484px); margin: auto; padding: 32px; background: #fff; border: 1px solid #e7ebe7; border-radius: 18px; }
                .brand-lockup { display: inline-flex; width: fit-content; align-items: center; gap: 10px; color: #183a33; font-size: 23px; font-weight: 720; text-decoration: none; }
                .brand-mark { display: grid; width: 38px; height: 38px; flex: 0 0 38px; place-items: center; color: #a9dd68; }
                .brand-mark svg { display: block; width: 100%; height: 100%; }
                .brand-period { color: #a9dd68; }
                .mobile-brand { margin-bottom: 28px; }
                .input-wrap { display: flex; min-height: 50px; align-items: center; gap: 10px; padding: 0 14px; border: 1px solid #dce3dd; border-radius: 9px; }
                .input-wrap svg, .button svg, .secure-note svg { display: block; width: 18px; height: 18px; flex: 0 0 18px; }
                .input-wrap input { width: 100%; min-width: 0; border: 0; }
                .login-form { display: grid; gap: 12px; margin-top: 24px; }
                .login-form input, .login-form button { min-height: 44px; padding: 0 12px; border: 1px solid #dce3dd; border-radius: 8px; }
                .login-form button { color: #fff; background: #183a33; cursor: pointer; }
                .auth-footer { display: flex; justify-content: space-between; gap: 16px; margin-top: 32px; color: #74817d; font-size: 11px; }
            </style>
        @endif
    </head>
    <body>
        <main class="auth-layout" id="auth-view">
            <section class="brand-panel" aria-label="ControlaLite, control de ingreso">
                <a class="brand-lockup" href="/" aria-label="ControlaLite, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                            <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>controlaLite<span class="brand-period">.</span></span>
                </a>

                <div class="brand-message">
                    <span class="eyebrow eyebrow-light">CONTROL DE INGRESO</span>
                    <h1>Un acceso seguro empieza con una buena decisión.</h1>
                    <p>Administra autorizaciones y consulta la actividad de tus puntos de acceso desde un solo lugar.</p>
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
                                <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>controlaLite<span class="brand-period">.</span></span>
                    </div>
                    <span class="eyebrow">ACCESO CENTRALIZADO</span>
                    <h2>Bienvenido de nuevo</h2>
                    <p class="auth-description">Ingresa con tu cuenta de usuario para administrar ControlaLite. Las personas ingresan desde <a href="/mobile/">/mobile</a>.</p>

                    <form class="login-form" id="login-form">
                        <label class="field-label" for="email">Correo electrónico</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <rect x="2.5" y="4" width="15" height="12" rx="2" stroke="currentColor" stroke-width="1.5" />
                                <path d="m3.5 5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <input id="email" name="email" type="email" autocomplete="username" placeholder="nombre@empresa.com" required maxlength="255">
                        </div>

                        <div class="field-heading">
                            <label class="field-label" for="password">Contraseña</label>
                        </div>
                        <div class="input-wrap">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <rect x="3" y="8.5" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5" />
                                <path d="M6.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 12v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required maxlength="255">
                        </div>

                        <p class="form-message" id="login-message" role="alert" hidden></p>
                        <button class="button button-primary button-wide" id="login-button" type="submit">
                            <span>Iniciar sesión</span>
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
                        <span>Acceso protegido para personal autorizado</span>
                    </div>
                </div>
                <footer class="auth-footer">
                    <span>© {{ date('Y') }} ControlaLite</span>
                    <span>Acceso centralizado</span>
                </footer>
            </section>
        </main>

        <main class="person-portal" id="person-view" hidden>
            <section class="person-portal-card">
                <a class="brand-lockup person-portal-brand" href="/" aria-label="ControlaLite, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                            <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>controlaLite<span class="brand-period">.</span></span>
                </a>
                <span class="eyebrow">CUENTA PERSONAL</span>
                <h1>Bienvenido, <span id="person-name"></span></h1>
                <p class="auth-description">Has iniciado sesión en tu cuenta de ControlaLite.</p>
                <dl class="person-profile">
                    <div>
                        <dt>Correo electrónico</dt>
                        <dd id="person-email"></dd>
                    </div>
                    <div>
                        <dt>Tipo de persona</dt>
                        <dd id="person-type"></dd>
                    </div>
                </dl>
                <section class="person-attendance" aria-labelledby="person-attendance-title">
                    <div class="person-attendance-heading">
                        <span class="eyebrow">ASISTENCIA</span>
                        <h2 id="person-attendance-title">Mis marcaciones</h2>
                        <p>Entrada y salida registran la hora oficial, ubicación y selfie. El navegador solicitará acceso a cámara y ubicación al marcar.</p>
                    </div>
                    <label class="person-site-field" for="person-site-select">
                        Sede autorizada
                        <select id="person-site-select"></select>
                    </label>
                    <label class="person-site-field" for="person-zone-select">
                        Zona (opcional)
                        <select id="person-zone-select"></select>
                    </label>
                    <p class="attendance-current-state" id="attendance-current-state" aria-live="polite"></p>
                    <p class="attendance-schedule" id="attendance-schedule"></p>
                    <label class="attendance-novelty-field" id="attendance-novelty-field" for="attendance-novelty" hidden>
                        Novedad obligatoria
                        <textarea id="attendance-novelty" rows="3" maxlength="2000" placeholder="Describe el motivo de la llegada tarde o del tiempo extra"></textarea>
                    </label>
                    <div class="attendance-actions">
                        <button class="button button-primary" id="attendance-entry-button" type="button">Registrar entrada</button>
                        <button class="button button-secondary" id="attendance-exit-button" type="button">Registrar salida</button>
                    </div>
                    <div class="attendance-informational">
                        <label for="attendance-informational-type">Evento informativo</label>
                        <div class="attendance-informational-controls">
                            <select id="attendance-informational-type"></select>
                            <button class="button button-secondary" id="attendance-informational-button" type="button">Registrar</button>
                        </div>
                    </div>
                    <p class="attendance-message" id="attendance-message" role="status" aria-live="polite" hidden></p>
                    <div class="attendance-history">
                        <h3>Historial reciente</h3>
                        <div id="attendance-history"></div>
                    </div>
                </section>
                <dialog class="person-camera-dialog" id="person-camera-dialog" aria-labelledby="person-camera-title">
                    <div class="person-camera-content">
                        <h2 id="person-camera-title">Toma tu selfie</h2>
                        <video id="person-camera-preview" autoplay playsinline></video>
                        <div class="attendance-actions">
                            <button class="button button-secondary" id="cancel-attendance-capture" type="button">Cancelar</button>
                            <button class="button button-primary" id="capture-attendance-selfie" type="button">Tomar selfie y registrar</button>
                        </div>
                    </div>
                </dialog>
                <p class="form-message" id="person-message" role="alert" hidden></p>
                <button class="button button-primary button-wide" id="person-logout-button" type="button">
                    <span>Cerrar sesión</span>
                </button>
            </section>
        </main>

        <main class="workspace" id="workspace-view" hidden>
            <aside class="sidebar">
                <a class="brand-lockup sidebar-brand" href="/" aria-label="ControlaLite, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M20 3.5 34 9v9.8c0 8.4-5.7 14.6-14 17.7C11.7 33.4 6 27.2 6 18.8V9l14-5.5Z" fill="currentColor" />
                            <path d="m13.7 20.1 4.2 4.2 8.6-9.1" stroke="#183A33" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>controlaLite<span class="brand-period">.</span></span>
                </a>

                <span class="nav-caption">ESPACIO DE TRABAJO</span>
                <nav class="primary-nav" aria-label="Navegación principal">
                    <section class="nav-group" aria-label="Operación">
                        <button class="nav-group-label" type="button" data-nav-toggle aria-expanded="true" aria-controls="nav-group-operation" title="Contraer grupo Operación"><span>OPERACIÓN</span><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                        <div class="nav-group-items" id="nav-group-operation">
                        <a class="nav-link nav-link-active" href="#overview" data-module="dashboard" data-permission="presence.read" aria-current="page" aria-label="Resumen" title="Resumen"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" /><rect x="11.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" /><rect x="2.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" /><rect x="11.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" /></svg>Resumen</a>
                        <a class="nav-link" href="#presence" data-module="presence" data-permission="presence.read" aria-label="Presencia actual" title="Presencia actual"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.5" /><path d="M3.5 17v-1.2A4.8 4.8 0 0 1 8.3 11h3.4a4.8 4.8 0 0 1 4.8 4.8V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Presencia actual</a>
                        <a class="nav-link" href="#access-events" data-module="access_events" data-permission="access_events.read" aria-label="Eventos de acceso" title="Eventos de acceso"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 3h12v14H4zM7 7h6M7 10h6m-6 3h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Eventos de acceso</a>
                        <a class="nav-link" href="#reports" data-module="reports" data-permission="reports.read" aria-label="Reportes" title="Reportes"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 2.5h7l3 3v12H5zM12 2.5v3h3M7.5 9h5m-5 3h5m-5 3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Reportes</a>
                        <a class="nav-link" href="#overtime-reviews" data-module="overtime_reviews" data-permission="overtime_reviews.read" aria-label="Validación de horas extra" title="Validación de horas extra"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5" /><path d="M10 5.5v4.8l3.2 1.9M6.5 2.9 5 1.8m8.5 1.1L15 1.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Validación de horas extra</a>
                        </div>
                    </section>
                    <section class="nav-group" aria-label="Organización e infraestructura">
                        <button class="nav-group-label" type="button" data-nav-toggle aria-expanded="true" aria-controls="nav-group-organization" title="Contraer grupo Organización"><span>ORGANIZACIÓN</span><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                        <div class="nav-group-items" id="nav-group-organization">
                        <a class="nav-link" href="#sites" data-module="sites" data-permission="sites.read" aria-label="Sedes" title="Sedes"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 17.5h14M4.5 17.5v-11l5.5-3 5.5 3v11M7.5 9h1m3 0h1m-5 3h1m3 0h1m-2 5.5v-4h-2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Sedes</a>
                        <a class="nav-link" href="#zones" data-module="zones" data-permission="zones.read" aria-label="Zonas" title="Zonas"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2.5 7 4v7l-7 4-7-4v-7l7-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m3.5 6.5 6.5 4 6.5-4M10 10.5v7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /></svg>Zonas</a>
                        <a class="nav-link" href="#access-points" data-module="access_points" data-permission="access_points.read" aria-label="Puntos de acceso" title="Puntos de acceso"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 17.5h14M5 17.5V5h10v12.5M8 8h1m2 0h1m-4 3h1m2 0h1m-2 6.5v-4h-2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Puntos de acceso</a>
                        <a class="nav-link" href="#access-devices" data-module="devices" data-permission="devices.read" aria-label="Dispositivos de acceso" title="Dispositivos de acceso"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5" /><path d="M7 7h6v6H7zM1 7h2m14 0h2M1 13h2m14 0h2M7 1v2m6-2v2m-6 14v2m6-2v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Dispositivos de acceso</a>
                        </div>
                    </section>
                    <section class="nav-group" aria-label="Personas y autorizaciones">
                        <button class="nav-group-label" type="button" data-nav-toggle aria-expanded="true" aria-controls="nav-group-people" title="Contraer grupo Personas y accesos"><span>PERSONAS Y ACCESOS</span><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                        <div class="nav-group-items" id="nav-group-people">
                        <a class="nav-link" href="#people" data-module="people" data-permission="people.read" aria-label="Personas" title="Personas"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.5" /><path d="M3.5 17v-1.2A4.8 4.8 0 0 1 8.3 11h3.4a4.8 4.8 0 0 1 4.8 4.8V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Personas</a>
                        <a class="nav-link" href="#authorizations" data-module="authorizations" data-permission="authorizations.read" aria-label="Autorizaciones" title="Autorizaciones"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5v4.2c0 3.6-2.4 6.3-6 7.6-3.6-1.3-6-4-6-7.6V5l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m7.3 9.8 1.8 1.8 3.7-3.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Autorizaciones</a>
                        <a class="nav-link" href="#credentials" data-module="credentials" data-permission="credentials.read" aria-label="Credenciales" title="Credenciales"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="7" cy="8" r="4.5" stroke="currentColor" stroke-width="1.5" /><path d="m10.5 11.5 6 6m-2-4 2-2m-4 0 2-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Credenciales</a>
                        <a class="nav-link" href="#mobile-devices" data-module="mobile_devices" data-permission="people.manage_mobile" aria-label="Dispositivos móviles" title="Dispositivos móviles"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="5" y="2" width="10" height="16" rx="2" stroke="currentColor" stroke-width="1.5" /><path d="M8 5h4m-3 10h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Dispositivos móviles</a>
                        </div>
                    </section>
                    <section class="nav-group" aria-label="Administración">
                        <button class="nav-group-label" type="button" data-nav-toggle aria-expanded="true" aria-controls="nav-group-admin" title="Contraer grupo Administración"><span>ADMINISTRACIÓN</span><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                        <div class="nav-group-items" id="nav-group-admin">
                        <a class="nav-link" href="#users" data-module="users" data-permission="users.read" aria-label="Usuarios" title="Usuarios"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="7" cy="6" r="2.5" stroke="currentColor" stroke-width="1.5" /><path d="M2.5 16v-1a4.5 4.5 0 0 1 9 0v1m2-7.5a2.5 2.5 0 1 0 0-5m1 7.5a4 4 0 0 1 3 3.9v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Usuarios</a>
                        <a class="nav-link" href="#event-types" data-module="event_types" data-permission="event_types.read" aria-label="Tipos de eventos" title="Tipos de eventos"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5v15M2.5 10h15M4.7 4.7l10.6 10.6m0-10.6L4.7 15.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Tipos de eventos</a>
                        <a class="nav-link" href="#attendance-settings" data-module="attendance_settings" data-permission="attendance_settings.read" aria-label="Parámetros de asistencia" title="Parámetros de asistencia"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="1.5" /><path d="M10 2v2m0 12v2M2 10h2m12 0h2M4.3 4.3l1.4 1.4m8.6 8.6 1.4 1.4m0-11.4-1.4 1.4m-8.6 8.6-1.4 1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>Parámetros de asistencia</a>
                        <a class="nav-link" href="#profiles" data-module="profiles" data-permission="profiles.read" aria-label="Perfiles" title="Perfiles"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5v4.2c0 3.6-2.4 6.3-6 7.6-3.6-1.3-6-4-6-7.6V5l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m7.3 9.8 1.8 1.8 3.7-3.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Perfiles</a>
                        </div>
                    </section>
                </nav>

                <div class="sidebar-bottom">
                    <div class="connection-card">
                        <span class="connection-indicator"></span>
                        <div>
                            <strong>API central</strong>
                            <span>Conectada</span>
                        </div>
                    </div>
                    <button class="profile-button" id="logout-button" type="button" aria-label="Cerrar sesión" title="Cerrar sesión">
                        <span class="avatar" id="user-avatar">A</span>
                        <span class="profile-copy">
                            <strong id="user-name">Administrador</strong>
                            <span id="user-account-label">Administrador</span>
                        </span>
                        <svg class="logout-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M8 3H4.5A1.5 1.5 0 0 0 3 4.5v11A1.5 1.5 0 0 0 4.5 17H8m4-3 4-4-4-4m4 4H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </aside>

            <section class="main-content" id="overview">
                <header class="topbar">
                    <div class="breadcrumb"><span>Espacio de trabajo</span><span class="breadcrumb-separator">/</span><strong id="breadcrumb-current">Resumen</strong></div>
                    <span class="local-badge"><span class="status-dot"></span> Operación en Línea</span>
                </header>

                <div class="page-content" id="dashboard-page">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">VISTA GENERAL</span>
                            <h1>Buenos días<span class="heading-period">.</span></h1>
                            <p>Este es el estado actual de la operación de ingreso.</p>
                        </div>
                        <button class="button button-secondary" id="refresh-button" type="button">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M16.5 6.5V3m0 3.5H13M3.5 13.5V17m0-3.5H7m9.1-6.1A6.5 6.5 0 0 0 5 5m-1.1 9A6.5 6.5 0 0 0 15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Actualizar
                        </button>
                    </div>

                    <div class="summary-grid">
                        <article class="summary-card summary-card-highlight">
                            <div class="summary-card-top">
                                <span class="summary-icon summary-icon-green">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M3.5 17v-1.2A4.8 4.8 0 0 1 8.3 11h3.4a4.8 4.8 0 0 1 4.8 4.8V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <span class="live-label"><span class="status-dot"></span> EN VIVO</span>
                            </div>
                            <span class="summary-label">Presencias activas</span>
                            <strong class="summary-value" id="presence-count">—</strong>
                            <span class="summary-footnote">Registros por persona y sede</span>
                        </article>

                        <article class="summary-card">
                            <div class="summary-card-top">
                                <span class="summary-icon summary-icon-sand">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M3.5 10h13M10 3.5v13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                </span>
                                <span class="card-context">Ahora</span>
                            </div>
                            <span class="summary-label">Alcance de consulta</span>
                            <strong class="summary-value summary-value-status">Todas las sedes</strong>
                            <span class="summary-footnote">Presencia derivada del historial</span>
                        </article>

                        <article class="summary-card">
                            <div class="summary-card-top">
                                <span class="summary-icon summary-icon-blue">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M10 2.5 16 5v4.2c0 3.6-2.4 6.3-6 7.8-3.6-1.5-6-4.2-6-7.8V5l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                        <path d="m7.5 9.8 1.7 1.7 3.4-3.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <span class="card-context">Sistema</span>
                            </div>
                            <span class="summary-label">Conexión con API</span>
                            <strong class="summary-value summary-value-status">Operativa</strong>
                            <span class="summary-footnote">Servidor central disponible</span>
                        </article>
                    </div>

                    <section class="data-card database-maintenance-card" id="database-maintenance-card" hidden>
                        <div class="data-card-header">
                            <div>
                                <span class="eyebrow">MANTENIMIENTO</span>
                                <h2>Actualización de base de datos</h2>
                                <p id="database-migration-status" aria-live="polite">Consultando el estado de las migraciones...</p>
                            </div>
                            <div class="database-maintenance-actions">
                                <button class="button button-secondary" id="check-database-migrations" type="button" data-permission="database.migrations.read">Consultar estado</button>
                                <button class="button button-primary" id="open-database-migration" type="button" data-permission="database.migrations.apply" disabled>Actualizar base de datos</button>
                            </div>
                        </div>
                        <div class="database-migration-details">
                            <p id="database-migration-connection"></p>
                            <ul id="database-migration-list"></ul>
                            <p class="form-message" id="database-migration-error" role="alert" hidden></p>
                        </div>
                    </section>

                    <section class="data-card" id="presence">
                        <div class="data-card-header">
                            <div>
                                <span class="eyebrow">SEGUIMIENTO</span>
                                <h2>Presencia actual</h2>
                                <p>Personas cuya última marcación permitida fue una entrada.</p>
                            </div>
                            <label class="search-box" for="presence-search">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <circle cx="8.8" cy="8.8" r="5.8" stroke="currentColor" stroke-width="1.5" />
                                    <path d="m13 13 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                <input id="presence-search" type="search" placeholder="Filtrar esta página..." autocomplete="off">
                            </label>
                        </div>
                        <div class="table-scroll">
                            <table class="presence-table">
                                <thead>
                                    <tr>
                                        <th scope="col">PERSONA</th>
                                        <th scope="col">TIPO</th>
                                        <th scope="col">SEDE</th>
                                        <th scope="col">ÚLTIMO INGRESO</th>
                                        <th scope="col">ESTADO</th>
                                    </tr>
                                </thead>
                                <tbody id="presence-rows">
                                    <tr><td class="table-loading" colspan="5">Cargando información...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <span id="presence-summary">Cargando presencia actual</span>
                            <div class="pagination">
                                <span id="last-updated">—</span>
                                <button id="previous-page" type="button" disabled>Anterior</button>
                                <button id="next-page" type="button" disabled>Siguiente</button>
                            </div>
                        </div>
                    </section>

                    <p class="page-notice" id="dashboard-message" role="status" hidden></p>
                    <footer class="workspace-footer">
                        <span>ControlaLite<span class="brand-period">·</span> Plataforma de control de ingreso</span>
                        <span>Los datos se consultan desde la API central.</span>
                    </footer>
                </div>

                <section class="page-content management-page" id="management-page" hidden>
                    <div class="page-heading">
                        <div class="module-heading-content">
                            <span class="eyebrow" id="module-eyebrow">ADMINISTRACIÓN</span>
                            <div class="module-title-row">
                                <h1 id="module-title">Usuarios</h1>
                                <div class="module-heading-actions">
                                    <button class="button button-secondary module-refresh-button" id="module-refresh-button" type="button" aria-label="Actualizar eventos de acceso" hidden>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M16.5 6.5V3m0 3.5H13M3.5 13.5V17m0-3.5H7m9.1-6.1A6.5 6.5 0 0 0 5 5m-1.1 9A6.5 6.5 0 0 0 15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Actualizar
                                    </button>
                                    <button class="button button-primary" id="create-record-button" type="button">
                                        <span aria-hidden="true">+</span>
                                        <span id="create-record-label">Nuevo usuario</span>
                                    </button>
                                </div>
                            </div>
                            <p id="module-description">Administra las cuentas que pueden acceder a la plataforma.</p>
                        </div>
                    </div>

                    <section class="data-card" id="module-table-card">
                        <div class="data-card-header management-toolbar">
                            <div>
                                <span class="eyebrow">REGISTROS</span>
                                <h2 id="table-title">Usuarios</h2>
                                <p id="table-description">Consulta, crea y actualiza registros.</p>
                            </div>
                            <div class="management-filters" id="module-filters"></div>
                        </div>
                        <div class="table-scroll">
                            <table class="presence-table management-table">
                                <thead id="module-table-head"></thead>
                                <tbody id="module-table-rows">
                                    <tr><td class="table-loading" colspan="6">Cargando información...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <span id="module-summary">Cargando registros</span>
                            <div class="pagination">
                                <button id="module-previous-page" type="button" disabled>Anterior</button>
                                <button id="module-next-page" type="button" disabled>Siguiente</button>
                            </div>
                        </div>
                    </section>

                    <section class="data-card reports-card" id="reports-card" hidden>
                        <div class="data-card-header">
                            <div>
                                <span class="eyebrow">CENTRO DE INFORMES</span>
                                <h2>Genera un reporte</h2>
                                <p>Elige un informe y sus filtros. Puedes descargar los resultados en Excel, CSV o PDF.</p>
                            </div>
                        </div>
                        <form class="report-toolbar" id="report-form">
                            <div class="form-field">
                                <label class="field-label" for="report-type">Tipo de informe</label>
                                <select class="form-control" id="report-type" name="report_type" required></select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-from">Desde</label>
                                <input class="form-control" id="report-from" name="from" type="date">
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-to">Hasta</label>
                                <input class="form-control" id="report-to" name="to" type="date">
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-site">Sede</label>
                                <select class="form-control" id="report-site" name="site_id"></select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-access-point">Punto de acceso</label>
                                <select class="form-control" id="report-access-point" name="access_point_id"></select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-person">Persona</label>
                                <input class="form-control" id="report-person" name="q" type="search" placeholder="Nombre o apellido..." autocomplete="off">
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-decision">Decisión</label>
                                <select class="form-control" id="report-decision" name="decision">
                                    <option value="">Todas las decisiones</option>
                                    <option value="allowed">Permitidos</option>
                                    <option value="denied">Denegados</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-overtime-status">Estado de revisión</label>
                                <select class="form-control" id="report-overtime-status" name="overtime_status">
                                    <option value="all">Todos los estados</option>
                                    <option value="pending">Pendientes</option>
                                    <option value="approved">Aprobadas</option>
                                    <option value="rejected">Rechazadas</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="report-event-type">Tipo de evento</label>
                                <select class="form-control" id="report-event-type" name="event_type"></select>
                            </div>
                            <button class="button button-primary report-generate-button" id="generate-report-button" type="submit">Generar informe</button>
                        </form>
                        <p class="form-message report-message" id="report-message" role="alert" hidden></p>
                        <section class="report-results" id="report-results" hidden>
                            <div class="report-results-header">
                                <div>
                                    <span class="eyebrow">RESULTADOS</span>
                                    <h3 id="report-results-title">Informe</h3>
                                </div>
                                <div class="report-export-actions" aria-label="Exportar informe">
                                    <button class="button button-secondary" type="button" data-report-export="xlsx" disabled>Excel</button>
                                    <button class="button button-secondary" type="button" data-report-export="csv" disabled>CSV</button>
                                    <button class="button button-secondary" type="button" data-report-export="pdf" disabled>PDF</button>
                                </div>
                            </div>
                            <div class="table-scroll">
                                <table class="presence-table management-table">
                                    <thead id="report-head"></thead>
                                    <tbody id="report-rows"></tbody>
                                </table>
                            </div>
                            <div class="table-footer">
                                <span id="report-summary">Genera un informe para consultar los resultados.</span>
                                <div class="pagination">
                                    <button id="report-previous-page" type="button" disabled>Anterior</button>
                                    <button id="report-next-page" type="button" disabled>Siguiente</button>
                                </div>
                            </div>
                        </section>
                    </section>

                    <section class="data-card overtime-review-card" id="overtime-reviews-card" hidden>
                        <div class="data-card-header">
                            <div>
                                <span class="eyebrow">CONTROL DE ASISTENCIA</span>
                                <h2>Validación de horas extra</h2>
                                <p>Revisa primero los registros con más tiempo extra. Selecciona varios para aprobar o rechazar en una sola acción.</p>
                            </div>
                        </div>
                        <form class="overtime-review-filters" id="overtime-review-filter-form">
                            <div class="form-field">
                                <label class="field-label" for="overtime-search">Persona</label>
                                <input class="form-control" id="overtime-search" name="q" type="search" placeholder="Buscar nombre..." autocomplete="off">
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="overtime-site">Sede</label>
                                <select class="form-control" id="overtime-site" name="site_id"></select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="overtime-person-type">Tipo de persona</label>
                                <select class="form-control" id="overtime-person-type" name="person_type">
                                    <option value="">Todos los tipos</option>
                                    <option value="employee">Empleados</option>
                                    <option value="visitor">Visitantes</option>
                                    <option value="contractor">Contratistas</option>
                                    <option value="other">Otros</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="overtime-status">Clasificación</label>
                                <select class="form-control" id="overtime-status" name="status">
                                    <option value="pending">Pendientes</option>
                                    <option value="approved">Aprobadas</option>
                                    <option value="rejected">Rechazadas</option>
                                    <option value="all">Todas</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="overtime-from">Desde</label>
                                <input class="form-control" id="overtime-from" name="from" type="date">
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="overtime-to">Hasta</label>
                                <input class="form-control" id="overtime-to" name="to" type="date">
                            </div>
                            <button class="button button-primary" type="submit">Aplicar filtros</button>
                        </form>
                        <div class="overtime-review-actions">
                            <label class="overtime-select-all">
                                <input id="overtime-select-page" type="checkbox" aria-label="Seleccionar todos los pendientes de esta página">
                                Seleccionar página
                            </label>
                            <span id="overtime-selected-count" aria-live="polite">0 seleccionados</span>
                            <div class="overtime-action-buttons">
                                <button class="button button-primary" id="overtime-approve-selected" type="button" data-permission="overtime_reviews.decide" disabled>Aprobar seleccionados</button>
                                <button class="button button-secondary overtime-reject-button" id="overtime-reject-selected" type="button" data-permission="overtime_reviews.decide" disabled>Rechazar seleccionados</button>
                            </div>
                        </div>
                        <p class="form-message overtime-review-message" id="overtime-review-message" role="status" hidden></p>
                        <div class="table-scroll">
                            <table class="presence-table management-table overtime-review-table">
                                <thead>
                                    <tr>
                                        <th scope="col" aria-label="Selección"></th>
                                        <th scope="col">FECHA DE SALIDA</th>
                                        <th scope="col">PERSONA</th>
                                        <th scope="col">TIPO</th>
                                        <th scope="col">SEDE</th>
                                        <th scope="col">TURNO</th>
                                        <th scope="col">TIEMPO EXTRA</th>
                                        <th scope="col">NOVEDAD</th>
                                        <th scope="col">ESTADO</th>
                                        <th scope="col">REVISIÓN</th>
                                    </tr>
                                </thead>
                                <tbody id="overtime-review-rows">
                                    <tr><td class="table-loading" colspan="10">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <span id="overtime-review-summary">Cargando registros</span>
                            <div class="pagination">
                                <button id="overtime-review-previous" type="button" disabled>Anterior</button>
                                <button id="overtime-review-next" type="button" disabled>Siguiente</button>
                            </div>
                        </div>
                    </section>

                    <section class="data-card attendance-settings-card" id="attendance-settings-card" hidden>
                        <div class="data-card-header">
                            <div>
                                <span class="eyebrow">REGLAS DE MARCACIÓN</span>
                                <h2>Horarios y zona horaria</h2>
                                <p>Los cambios se aplican a las nuevas marcaciones y a todas las personas.</p>
                            </div>
                        </div>
                        <form id="attendance-settings-form">
                            <div class="attendance-settings-grid">
                                <div class="form-field">
                                    <label class="field-label" for="setting-timezone-mode">Aplicación de zona horaria</label>
                                    <select class="form-control" id="setting-timezone-mode" name="timezone_mode" required>
                                        <option value="global">Zona horaria general para toda la aplicación</option>
                                        <option value="per_site">Zona horaria configurada por sede</option>
                                    </select>
                                    <small class="field-help">En modo por sede, la zona general se usa como alternativa cuando una sede no tiene una configurada.</small>
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="setting-timezone">Zona horaria general / alternativa</label>
                                    <select class="form-control" id="setting-timezone" name="timezone" required></select>
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="setting-early-entry-minutes">Permitir ingreso antes del turno (minutos)</label>
                                    <input class="form-control" id="setting-early-entry-minutes" name="early_entry_minutes" type="number" min="0" max="720" required>
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="setting-stale-entry-hours">Vigencia de ingreso pendiente (horas)</label>
                                    <input class="form-control" id="setting-stale-entry-hours" name="stale_entry_hours" type="number" min="1" max="168" required>
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="setting-late-exit-grace-minutes">Margen de salida sin novedad (minutos)</label>
                                    <input class="form-control" id="setting-late-exit-grace-minutes" name="late_exit_grace_minutes" type="number" min="0" max="720" required>
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="setting-max-daily-overtime-minutes">Máximo diario después del fin del turno (minutos)</label>
                                    <input class="form-control" id="setting-max-daily-overtime-minutes" name="max_daily_overtime_minutes" type="number" min="0" max="1440" required>
                                </div>
                            </div>
                            <p class="field-help">Después del margen de salida se exige novedad. El máximo diario se cuenta desde el fin del turno e incluye el margen de salida.</p>
                            <p class="form-message" id="attendance-settings-message" role="alert" hidden></p>
                            <div class="dialog-actions">
                                <button class="button button-primary" id="save-attendance-settings" type="submit">Guardar parámetros</button>
                            </div>
                        </form>
                    </section>

                    <p class="page-notice" id="module-message" role="status" hidden></p>
                </section>
            </section>
        </main>

        <dialog class="record-dialog overtime-review-dialog" id="overtime-review-dialog" aria-labelledby="overtime-review-dialog-title">
            <form id="overtime-review-confirm-form">
                <header class="dialog-header">
                    <div>
                        <span class="eyebrow">DECISIÓN DE HORAS EXTRA</span>
                        <h2 id="overtime-review-dialog-title">Confirmar decisión</h2>
                    </div>
                    <button class="dialog-close" id="close-overtime-review-dialog" type="button" aria-label="Cerrar">×</button>
                </header>
                <div class="overtime-review-confirm-content">
                    <p id="overtime-review-confirmation"></p>
                    <div class="form-field" id="overtime-rejection-field" hidden>
                        <label class="field-label" for="overtime-review-note">Motivo del rechazo</label>
                        <textarea class="form-control" id="overtime-review-note" name="note" maxlength="1000" placeholder="Explica por qué se rechaza este tiempo extra..."></textarea>
                        <small class="field-help">El motivo se guardará junto con la decisión para consulta posterior.</small>
                    </div>
                </div>
                <p class="form-message dialog-message" id="overtime-review-dialog-message" role="alert" hidden></p>
                <footer class="dialog-actions">
                    <button class="button button-secondary" id="cancel-overtime-review" type="button">Cancelar</button>
                    <button class="button button-primary" id="confirm-overtime-review" type="submit">Confirmar</button>
                </footer>
            </form>
        </dialog>
        <dialog class="record-dialog" id="record-dialog" aria-labelledby="record-dialog-title">
            <form id="record-form">
                <header class="dialog-header">
                    <div>
                        <span class="eyebrow">FORMULARIO</span>
                        <h2 id="record-dialog-title">Nuevo registro</h2>
                    </div>
                    <button class="dialog-close" id="close-dialog-button" type="button" aria-label="Cerrar">×</button>
                </header>
                <div class="dialog-fields" id="record-fields"></div>
                <p class="form-message dialog-message" id="record-message" role="alert" hidden></p>
                <footer class="dialog-actions">
                    <button class="button button-secondary" id="cancel-dialog-button" type="button">Cancelar</button>
                    <button class="button button-primary" id="save-record-button" type="submit">Guardar</button>
                </footer>
            </form>
        </dialog>
        <dialog class="record-dialog" id="admin-detail-dialog" aria-labelledby="admin-detail-title">
            <section class="admin-detail-content">
                <header class="dialog-header">
                    <div>
                        <span class="eyebrow">DETALLE DE AUDITORÍA</span>
                        <h2 id="admin-detail-title">Evento de acceso</h2>
                    </div>
                    <button class="dialog-close" id="close-admin-detail" type="button" aria-label="Cerrar">×</button>
                </header>
                <div id="admin-detail-fields"></div>
                <p class="page-notice" id="admin-detail-message" role="status" hidden></p>
                <div class="admin-detail-media" id="admin-detail-media" hidden>
                    <figure class="admin-detail-map" id="admin-event-map" aria-label="Mapa de Google Maps con la ubicación del evento">
                        <div class="admin-detail-map-canvas" id="admin-event-map-canvas"></div>
                        <figcaption>
                            <span id="admin-event-map-coordinates"></span>
                            <a id="admin-event-map-link" target="_blank" rel="noopener noreferrer">Abrir en Google Maps</a>
                        </figcaption>
                    </figure>
                    <figure class="admin-detail-photo" id="admin-detail-photo" hidden>
                        <img id="admin-event-selfie" src="/images/user-placeholder.svg" alt="Icono de usuario" hidden>
                        <figcaption id="admin-event-selfie-caption">Sin selfie registrada</figcaption>
                    </figure>
                </div>
            </section>
        </dialog>
        <dialog class="record-dialog" id="one-time-secret-dialog" aria-labelledby="one-time-secret-title">
            <section class="admin-detail-content">
                <header class="dialog-header">
                    <div>
                        <span class="eyebrow">SE MUESTRA UNA SOLA VEZ</span>
                        <h2 id="one-time-secret-title">Guarda el código emitido</h2>
                    </div>
                    <button class="dialog-close" id="close-one-time-secret" type="button" aria-label="Cerrar">×</button>
                </header>
                <p id="one-time-secret-notice"></p>
                <label class="field-label" for="one-time-secret-value">Código o token</label>
                <textarea class="form-control one-time-secret-value" id="one-time-secret-value" rows="4" readonly></textarea>
                <footer class="dialog-actions">
                    <button class="button button-secondary" id="copy-one-time-secret" type="button">Copiar</button>
                    <button class="button button-primary" id="dismiss-one-time-secret" type="button">Listo</button>
                </footer>
            </section>
        </dialog>
        <dialog class="record-dialog database-migration-dialog" id="database-migration-dialog" aria-labelledby="database-migration-title">
            <form id="database-migration-form">
                <header class="dialog-header">
                    <div>
                        <span class="eyebrow">ACCIÓN DE ADMINISTRADOR</span>
                        <h2 id="database-migration-title">Actualizar la base de datos</h2>
                    </div>
                    <button class="dialog-close" id="close-database-migration" type="button" aria-label="Cerrar">×</button>
                </header>
                <div class="database-migration-warning">
                    <p><strong>Antes de continuar:</strong> realiza un respaldo de la base de datos. Las migraciones pueden modificar su estructura; no uses este proceso sin autorización y una copia de seguridad reciente.</p>
                    <p>Se ejecutarán únicamente las migraciones pendientes. Este proceso no borra ni reinicia los datos.</p>
                    <ul id="database-migration-confirm-list"></ul>
                    <label class="database-migration-confirm">
                        <input id="database-migration-confirmed" type="checkbox" required>
                        Confirmo que revisé la advertencia y autorizo aplicar las migraciones pendientes.
                    </label>
                    <p class="form-message" id="database-migration-confirm-error" role="alert" hidden></p>
                </div>
                <footer class="dialog-actions">
                    <button class="button button-secondary" id="cancel-database-migration" type="button">Cancelar</button>
                    <button class="button button-primary" id="confirm-database-migration" type="submit" disabled>Aplicar migraciones</button>
                </footer>
            </form>
        </dialog>
    </body>
</html>
