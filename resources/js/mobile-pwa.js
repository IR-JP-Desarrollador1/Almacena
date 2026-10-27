const tokenKey = 'controlaLite.person.pwa.token';
const loginView = document.querySelector('#pwa-login-view');
const attendanceView = document.querySelector('#pwa-attendance-view');
const loginForm = document.querySelector('#pwa-login-form');
const loginButton = document.querySelector('#pwa-login-button');
const loginMessage = document.querySelector('#pwa-login-message');
const attendanceMessage = document.querySelector('#pwa-attendance-message');
const siteSelect = document.querySelector('#pwa-site-select');
const zoneSelect = document.querySelector('#pwa-zone-select');
const informationalSelect = document.querySelector('#pwa-informational-type');
const noveltyInput = document.querySelector('#pwa-novelty');
const retryButton = document.querySelector('#pwa-retry-button');
const cameraDialog = document.querySelector('#pwa-camera-dialog');
const cameraPreview = document.querySelector('#pwa-camera-preview');
const captureButton = document.querySelector('#pwa-capture-selfie');
const deviceRegistrationButton = document.querySelector('#pwa-device-register-button');
const deviceRegistrationMessage = document.querySelector('#pwa-device-message');

let token = localStorage.getItem(tokenKey);
let personAttendanceState = null;
let personSelectedSiteId = null;
let personSelectedZoneId = null;
let personZoneRequired = false;
let personAttendanceSites = [];
let personAttendanceZones = [];
let lastKnownPosition = null;
let personEventTypes = [];
let personCameraStream = null;
let personSubmitting = false;
let pendingEvent = null;
let deferredInstallPrompt = null;
let authorizedSiteCount = 0;
const deviceUuidKey = 'controlaLite.person.pwa.device-uuid';

function authHeaders() {
    return token ? { Authorization: `Bearer ${token}` } : {};
}

async function apiRequest(path, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json', ...authHeaders() };
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        method,
        headers,
        body: body instanceof FormData ? body : body === null ? null : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validationMessage = Object.values(data.errors || {}).flat()[0];
        const error = new Error(validationMessage || data.message || `El servidor respondió con HTTP ${response.status}.`);
        error.status = response.status;
        throw error;
    }

    return data;
}

function setLoginMessage(message, isError = true) {
    loginMessage.textContent = message;
    loginMessage.hidden = !message;
    loginMessage.classList.toggle('is-error', isError);
}

function setAttendanceMessage(message, isError = false) {
    attendanceMessage.textContent = message;
    attendanceMessage.hidden = !message;
    attendanceMessage.classList.toggle('is-error', isError);
}

function getDeviceUuid() {
    let deviceUuid = localStorage.getItem(deviceUuidKey);
    if (!deviceUuid) {
        if (!crypto.randomUUID) {
            throw new Error('Este navegador no permite generar un identificador seguro para el dispositivo.');
        }
        deviceUuid = crypto.randomUUID();
        localStorage.setItem(deviceUuidKey, deviceUuid);
    }

    return deviceUuid;
}

function getDeviceName() {
    const platform = navigator.userAgentData?.platform || navigator.platform || 'Navegador';
    return `PWA - ${platform}`.slice(0, 120);
}

function setDeviceRegistrationMessage(message, isError = false) {
    deviceRegistrationMessage.textContent = message;
    deviceRegistrationMessage.hidden = !message;
    deviceRegistrationMessage.classList.toggle('is-error', isError);
}

function showLogin(message = '', isError = true) {
    stopCamera();
    loginView.hidden = false;
    attendanceView.hidden = true;
    loginForm.elements.password.value = '';
    setLoginMessage(message, isError);
}

function showAttendance(person) {
    loginView.hidden = true;
    attendanceView.hidden = false;
    deviceRegistrationButton.disabled = false;
    deviceRegistrationButton.textContent = 'Vincular este dispositivo';
    setDeviceRegistrationMessage('');
    document.querySelector('#pwa-person-name').textContent = person.name || person.first_name || 'Persona';
    document.querySelector('#pwa-person-email').textContent = person.email || '';
}

function formatDateTime(value, timeZone) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timeZone || document.documentElement.dataset.appTimezone || undefined,
    }).format(new Date(value));
}

function formatTime(value, timeZone) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timeZone || document.documentElement.dataset.appTimezone || undefined,
    }).format(new Date(value));
}

function formatWorkedMinutes(minutes) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return [hours ? `${hours} h` : '', remainingMinutes ? `${remainingMinutes} min` : '']
        .filter(Boolean)
        .join(' ') || 'menos de un minuto';
}

function renderHistory(events) {
    const container = document.querySelector('#pwa-attendance-history');
    container.replaceChildren();
    if (!events.length) {
        const empty = document.createElement('p');
        empty.className = 'attendance-history-item';
        empty.textContent = 'Aún no tienes marcaciones desde el portal personal.';
        container.append(empty);
        return;
    }

    events.forEach((event) => {
        const item = document.createElement('div');
        item.className = 'attendance-history-item';
        const title = document.createElement('strong');
        title.textContent = `${event.event_type_name || event.event_type} · ${event.status === 'accepted' ? 'Aceptada' : 'Rechazada'}`;
        item.append(title);
        const details = [
            formatDateTime(event.event_at),
            event.schedule_deviation_minutes > 0 ? `Tardanza: ${event.schedule_deviation_minutes} min` : null,
            event.schedule_deviation_minutes < 0 ? `${Math.abs(event.schedule_deviation_minutes)} min antes del horario` : null,
            event.worked_minutes !== null ? `Tiempo trabajado: ${formatWorkedMinutes(event.worked_minutes)}` : null,
            event.overtime_minutes > 0 ? `Tiempo extra: ${formatWorkedMinutes(event.overtime_minutes)}` : null,
            event.novelty ? `Novedad: ${event.novelty}` : null,
            event.status === 'rejected' ? `Motivo: ${event.reason_code}` : null,
        ].filter(Boolean);
        item.append(document.createTextNode(details.join(' · ')));
        container.append(item);
    });
}

function distanceInMeters(latitude, longitude, targetLatitude, targetLongitude) {
    const earthRadius = 6371000;
    const latitudeDelta = (targetLatitude - latitude) * Math.PI / 180;
    const longitudeDelta = (targetLongitude - longitude) * Math.PI / 180;
    const latitudeRadians = latitude * Math.PI / 180;
    const targetLatitudeRadians = targetLatitude * Math.PI / 180;
    const a = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(latitudeRadians) * Math.cos(targetLatitudeRadians) * Math.sin(longitudeDelta / 2) ** 2;

    return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function renderLocationDistance() {
    const distanceDisplay = document.querySelector('#pwa-location-distance');
    const target = personSelectedZoneId
        ? personAttendanceZones.find((zone) => String(zone.id) === String(personSelectedZoneId))
        : personAttendanceSites.find((site) => String(site.id) === String(personSelectedSiteId));
    const targetName = target?.name || (personSelectedZoneId ? 'la zona' : 'la sede');

    if (!lastKnownPosition) {
        distanceDisplay.textContent = 'La distancia se calculará al obtener tu ubicación durante la marcación.';
        return;
    }
    if (!target || target.latitude === null || target.latitude === undefined
        || target.longitude === null || target.longitude === undefined) {
        distanceDisplay.textContent = `No hay coordenadas configuradas para ${targetName}; no se puede calcular la distancia.`;
        return;
    }

    const distance = Math.round(distanceInMeters(
        lastKnownPosition.latitude,
        lastKnownPosition.longitude,
        Number(target.latitude),
        Number(target.longitude),
    ));
    if (target.geofence_radius_m === null || target.geofence_radius_m === undefined) {
        distanceDisplay.textContent = `Distancia a ${targetName}: ${distance} m. No hay un radio de geocerca configurado.`;
        return;
    }

    const radius = Number(target.geofence_radius_m);
    distanceDisplay.textContent = `Distancia a ${targetName}: ${distance} m · ${
        distance <= radius ? `dentro del radio de ${radius} m` : `fuera del radio de ${radius} m`
    }.`;
}

function renderAttendance(data) {
    const previousSiteId = personSelectedSiteId;
    const previousZoneId = personSelectedZoneId;
    personAttendanceSites = data.sites;
    authorizedSiteCount = data.sites.length;
    siteSelect.replaceChildren();
    data.sites.forEach((site) => {
        const option = document.createElement('option');
        option.value = String(site.id);
        option.textContent = site.name;
        siteSelect.append(option);
    });

    personSelectedSiteId = data.sites.some((site) => String(site.id) === String(previousSiteId))
        ? previousSiteId
        : data.selected_site_id;
    if (personSelectedSiteId) {
        siteSelect.value = String(personSelectedSiteId);
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Sin sede autorizada vigente';
        siteSelect.append(option);
        siteSelect.value = '';
    }
    siteSelect.disabled = personSubmitting || Boolean(personCameraStream) || Boolean(pendingEvent) || data.sites.length < 2;

    const zones = data.zones || [];
    personAttendanceZones = zones;
    personZoneRequired = Boolean(data.zone_required);
    personSelectedZoneId = zones.some((zone) => String(zone.id) === String(previousZoneId))
        ? previousZoneId
        : data.selected_zone_id;
    zoneSelect.replaceChildren(new Option(
        personZoneRequired ? 'Selecciona una zona autorizada' : 'Sin zona específica',
        '',
    ));
    zones.forEach((zone) => zoneSelect.add(new Option(zone.name, String(zone.id))));
    zoneSelect.value = personSelectedZoneId ? String(personSelectedZoneId) : '';
    zoneSelect.disabled = personSubmitting || Boolean(personCameraStream) || Boolean(pendingEvent)
        || (!personZoneRequired && zones.length === 0);
    renderLocationDistance();

    personEventTypes = data.event_types;
    informationalSelect.replaceChildren();
    personEventTypes.filter((type) => type.behavior === 'informational').forEach((type) => {
        const option = document.createElement('option');
        option.value = type.code;
        option.textContent = type.name;
        informationalSelect.append(option);
    });
    if (informationalSelect.options.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No hay eventos informativos disponibles';
        informationalSelect.append(option);
    }

    personAttendanceState = data.attendance;
    document.querySelector('#pwa-current-state').textContent = data.attendance.open_entry
        ? `Tienes una entrada pendiente desde ${formatDateTime(data.attendance.open_entry.event_at)}. Registra la salida para cerrar el ciclo.`
        : data.attendance.missing_exit
            ? 'La entrada pendiente superó su vigencia y quedó marcada como sin registro de salida. La próxima marcación será un nuevo ingreso.'
            : 'No hay una entrada pendiente. La próxima marcación de asistencia será una entrada.';

    const site = data.sites.find((item) => String(item.id) === String(personSelectedSiteId));
    document.querySelector('#pwa-schedule').textContent = data.attendance.scheduled_start_at && data.attendance.scheduled_end_at
        ? `Horario vigente: ${formatTime(data.attendance.scheduled_start_at, site?.timezone)}–${formatTime(data.attendance.scheduled_end_at, site?.timezone)}${data.attendance.next_behavior === 'entry' ? ` · Entrada desde ${formatTime(data.attendance.allowed_entry_from, site?.timezone)} (hasta ${data.attendance.early_entry_minutes} min antes).` : ` · Salida hasta ${formatTime(data.attendance.allowed_exit_until, site?.timezone)} (${data.attendance.max_daily_overtime_minutes} min máximo después del fin; margen sin novedad: ${data.attendance.late_exit_grace_minutes} min).`}${data.attendance.reason === 'late_entry' ? ' Se requiere novedad por llegada tarde.' : ''}${data.attendance.reason === 'overtime' ? ' Se requiere novedad por tiempo extra.' : ''}${data.attendance.blocking_reason === 'entry_too_early' ? ' Aún no se permite registrar la entrada.' : ''}${data.attendance.blocking_reason === 'entry_window_closed' ? ' Ya pasó la hora límite para registrar la entrada.' : ''}${data.attendance.blocking_reason === 'exit_window_closed' ? ' Se superó el máximo diario de tiempo extra para registrar la salida.' : ''}`
        : data.attendance.blocking_reason === 'no_schedule'
            ? 'No hay un horario laboral aplicable; no se permite registrar asistencia.'
            : data.attendance.blocking_reason === 'no_shift_today'
                ? 'No hay un turno programado para hoy; no se permite registrar asistencia.'
                : 'Sin horario vigente para esta marcación.';

    const hasSite = Boolean(personSelectedSiteId);
    const hasRequiredZone = !personZoneRequired || Boolean(personSelectedZoneId);
    const entryButton = document.querySelector('#pwa-entry-button');
    const exitButton = document.querySelector('#pwa-exit-button');
    const entryIsNext = data.attendance.next_behavior === 'entry';
    entryButton.classList.toggle('button-primary', entryIsNext);
    entryButton.classList.toggle('button-secondary', !entryIsNext);
    exitButton.classList.toggle('button-primary', !entryIsNext);
    exitButton.classList.toggle('button-secondary', entryIsNext);
    entryButton.disabled =
        personSubmitting || Boolean(personCameraStream) || Boolean(pendingEvent) || !hasSite
        || !hasRequiredZone || !entryIsNext
        || data.attendance.marking_allowed === false
        || !personEventTypes.some((type) => type.behavior === 'entry');
    exitButton.disabled =
        personSubmitting || Boolean(personCameraStream) || Boolean(pendingEvent) || !hasSite
        || !hasRequiredZone || entryIsNext
        || data.attendance.marking_allowed === false
        || !personEventTypes.some((type) => type.behavior === 'exit');
    document.querySelector('#pwa-informational-button').disabled =
        personSubmitting || Boolean(personCameraStream) || Boolean(pendingEvent) || !hasSite || !hasRequiredZone || !informationalSelect.value;

    const noveltyField = document.querySelector('#pwa-novelty-field');
    noveltyField.hidden = !data.attendance.novelty_required;
    if (!data.attendance.novelty_required) {
        noveltyInput.value = '';
    }

    renderHistory(data.events);
}

async function loadAttendance({ preserveMessage = false } = {}) {
    const query = new URLSearchParams();
    if (personSelectedSiteId) query.set('site_id', personSelectedSiteId);
    if (personSelectedZoneId) query.set('zone_id', personSelectedZoneId);
    const queryString = query.size ? `?${query}` : '';
    try {
        const response = await apiRequest(`/api/v1/person/auth/attendance${queryString}`);
        renderAttendance(response.data);
        if (response.data.sites.length === 0) {
            setAttendanceMessage('No tienes una autorización aprobada y vigente para registrar asistencia en una sede.', true);
        } else if (!preserveMessage) {
            setAttendanceMessage('');
        }
    } catch (error) {
        if (error.status === 401) {
            localStorage.removeItem(tokenKey);
            token = null;
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        setAttendanceMessage(error.message || 'No se pudo cargar la información de asistencia.', true);
    }
}

function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Este navegador no permite consultar la ubicación.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            resolve,
            (error) => reject(new Error(error.message || 'No se pudo obtener la ubicación.')),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
        );
    });
}

function stopCamera() {
    personCameraStream?.getTracks().forEach((track) => track.stop());
    personCameraStream = null;
    cameraPreview.srcObject = null;
    if (cameraDialog.open) {
        cameraDialog.close();
    }
}

async function startAttendance(eventType) {
    if (pendingEvent) {
        setAttendanceMessage('Hay un envío pendiente. Reinténtalo antes de iniciar otra marcación.', true);
        return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        setAttendanceMessage('La cámara requiere HTTPS o una dirección local segura y un navegador compatible.', true);
        return;
    }
    if (!personSelectedSiteId) {
        setAttendanceMessage('No hay una sede autorizada seleccionada.', true);
        return;
    }
    if (personZoneRequired && !personSelectedZoneId) {
        setAttendanceMessage('Selecciona la zona autorizada para registrar la marcación.', true);
        zoneSelect.focus();
        return;
    }

    const type = personEventTypes.find((item) => item.code === eventType);
    if (!type) {
        setAttendanceMessage('El tipo de evento ya no está disponible. Actualiza e inténtalo de nuevo.', true);
        return;
    }
    if (personSubmitting || personCameraStream) {
        return;
    }
    if (type.behavior !== 'informational' && type.behavior !== personAttendanceState?.next_behavior) {
        setAttendanceMessage('El estado de asistencia cambió. Actualiza antes de marcar.', true);
        await loadAttendance();
        return;
    }
    if (type.behavior !== 'informational' && personAttendanceState?.novelty_required && !noveltyInput.value.trim()) {
        setAttendanceMessage('Escribe la novedad obligatoria antes de registrar.', true);
        noveltyInput.focus();
        return;
    }

    setAttendanceMessage('');
    personSubmitting = true;
    try {
        personCameraStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: 'user' },
        });
        captureButton.dataset.eventType = eventType;
        siteSelect.disabled = true;
        cameraPreview.srcObject = personCameraStream;
        cameraDialog.showModal();
    } catch (error) {
        stopCamera();
        setAttendanceMessage(error.message || 'No se pudo acceder a la cámara. Revisa los permisos del navegador.', true);
    } finally {
        personSubmitting = false;
    }
}

async function captureSelfie() {
    if (personSubmitting) {
        return;
    }
    if (!cameraPreview.videoWidth || !cameraPreview.videoHeight) {
        setAttendanceMessage('La cámara todavía no está lista. Espera un momento e inténtalo de nuevo.', true);
        return;
    }

    personSubmitting = true;
    captureButton.disabled = true;
    let message = '';
    let submissionAttempted = false;
    try {
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1280 / Math.max(cameraPreview.videoWidth, cameraPreview.videoHeight));
        canvas.width = Math.round(cameraPreview.videoWidth * scale);
        canvas.height = Math.round(cameraPreview.videoHeight * scale);
        canvas.getContext('2d').drawImage(cameraPreview, 0, 0, canvas.width, canvas.height);
        const selfie = await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('No se pudo preparar la selfie.')), 'image/jpeg', 0.85);
        });
        stopCamera();

        setAttendanceMessage('Obteniendo ubicación precisa…');
        const position = await getCurrentLocation();
        lastKnownPosition = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
        };
        renderLocationDistance();
        pendingEvent = {
            eventUuid: crypto.randomUUID(),
            siteId: personSelectedSiteId,
            zoneId: personSelectedZoneId,
            eventType: captureButton.dataset.eventType,
            clientEventAt: new Date().toISOString(),
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            novelty: noveltyInput.value.trim(),
            selfie,
        };
        personSubmitting = false;
        await submitPendingEvent();
        submissionAttempted = true;
    } catch (error) {
        message = error.message || 'No se pudo preparar la selfie o consultar la ubicación.';
        setAttendanceMessage(message, true);
    } finally {
        personSubmitting = false;
        captureButton.disabled = false;
        stopCamera();
        siteSelect.disabled = authorizedSiteCount < 2 || Boolean(pendingEvent);
        if (!submissionAttempted && !pendingEvent) {
            await loadAttendance({ preserveMessage: Boolean(message) });
            if (message) {
                setAttendanceMessage(message, true);
            }
        }
    }
}

async function submitPendingEvent() {
    if (!pendingEvent || personSubmitting) {
        return;
    }
    personSubmitting = true;
    retryButton.hidden = true;
    const form = new FormData();
    form.append('event_uuid', pendingEvent.eventUuid);
    form.append('site_id', String(pendingEvent.siteId));
    if (pendingEvent.zoneId) {
        form.append('zone_id', String(pendingEvent.zoneId));
    }
    form.append('event_type', pendingEvent.eventType);
    form.append('client_event_at', pendingEvent.clientEventAt);
    form.append('latitude', String(pendingEvent.latitude));
    form.append('longitude', String(pendingEvent.longitude));
    form.append('location_accuracy_m', String(pendingEvent.accuracy));
    form.append('novelty', pendingEvent.novelty);
    form.append('selfie', pendingEvent.selfie, 'selfie.jpg');

    let finalMessage = '';
    let finalError = false;
    try {
        const response = await apiRequest('/api/v1/person/auth/attendance/events', {
            method: 'POST',
            body: form,
        });
        const event = response.data;
        finalMessage = event.status === 'accepted'
            ? `${event.event_type_name} aceptada por el servidor.`
            : `Marcación rechazada: ${event.reason_code}.`;
        finalError = event.status !== 'accepted';
        pendingEvent = null;
        noveltyInput.value = '';
    } catch (error) {
        finalMessage = error.message || 'No se pudo registrar la marcación.';
        finalError = true;
        if (!error.status || error.status >= 500) {
            retryButton.hidden = false;
            finalMessage += ' Puedes reintentar con la misma selfie y el mismo identificador.';
        } else {
            pendingEvent = null;
        }
    } finally {
        personSubmitting = false;
        setAttendanceMessage(finalMessage, finalError);
        await loadAttendance({ preserveMessage: true });
        setAttendanceMessage(finalMessage, finalError);
    }
}

async function restoreSession() {
    if (!token) {
        return;
    }
    try {
        const response = await apiRequest('/api/v1/person/auth/me');
        showAttendance(response.data);
        await loadAttendance();
    } catch (error) {
        localStorage.removeItem(tokenKey);
        token = null;
        showLogin(error.status === 401
            ? 'Tu sesión terminó. Vuelve a iniciar sesión.'
            : error.message || 'No se pudo validar la sesión.');
    }
}

function updateInstallButtons(visible) {
    document.querySelectorAll('.mobile-pwa-install').forEach((button) => {
        button.hidden = !visible;
    });
}

async function installPwa() {
    if (!deferredInstallPrompt) {
        return;
    }
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    updateInstallButtons(false);
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginButton.disabled = true;
    loginButton.textContent = 'Verificando…';
    setLoginMessage('');
    try {
        const response = await apiRequest('/api/v1/person/auth/login', {
            method: 'POST',
            body: {
                email: loginForm.elements.email.value.trim(),
                password: loginForm.elements.password.value,
            },
        });
        token = response.data.token;
        localStorage.setItem(tokenKey, token);
        loginForm.elements.password.value = '';
        const personResponse = await apiRequest('/api/v1/person/auth/me');
        showAttendance(personResponse.data);
        personSelectedSiteId = null;
        await loadAttendance();
    } catch (error) {
        localStorage.removeItem(tokenKey);
        token = null;
        setLoginMessage(error.message || 'No se pudo iniciar sesión.');
    } finally {
        loginButton.disabled = false;
        loginButton.textContent = 'Iniciar sesión';
    }
});

document.querySelector('#pwa-entry-button').addEventListener('click', () => {
    const type = personEventTypes.find((item) => item.behavior === 'entry');
    if (type) void startAttendance(type.code);
});
document.querySelector('#pwa-exit-button').addEventListener('click', () => {
    const type = personEventTypes.find((item) => item.behavior === 'exit');
    if (type) void startAttendance(type.code);
});
document.querySelector('#pwa-informational-button').addEventListener('click', () => {
    if (informationalSelect.value) void startAttendance(informationalSelect.value);
});
document.querySelector('#pwa-capture-selfie').addEventListener('click', captureSelfie);
document.querySelector('#pwa-cancel-capture').addEventListener('click', () => {
    stopCamera();
    void loadAttendance();
});
cameraDialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    stopCamera();
    void loadAttendance();
});
cameraDialog.addEventListener('click', (event) => {
    if (event.target === event.currentTarget) {
        stopCamera();
        void loadAttendance();
    }
});
siteSelect.addEventListener('change', () => {
    personSelectedSiteId = siteSelect.value || null;
    personSelectedZoneId = null;
    void loadAttendance();
});
zoneSelect.addEventListener('change', () => {
    personSelectedZoneId = zoneSelect.value || null;
    void loadAttendance();
});
document.querySelector('#pwa-refresh-button').addEventListener('click', () => void loadAttendance());
retryButton.addEventListener('click', () => void submitPendingEvent());
deviceRegistrationButton.addEventListener('click', async () => {
    deviceRegistrationButton.disabled = true;
    deviceRegistrationButton.textContent = 'Vinculando…';
    setDeviceRegistrationMessage('');

    try {
        const response = await apiRequest('/api/v1/person/auth/mobile-device', {
            method: 'POST',
            body: {
                device_uuid: getDeviceUuid(),
                device_name: getDeviceName(),
            },
        });
        deviceRegistrationButton.textContent = 'Equipo vinculado';
        setDeviceRegistrationMessage(response.message, false);
    } catch (error) {
        deviceRegistrationButton.disabled = false;
        deviceRegistrationButton.textContent = 'Vincular este dispositivo';
        setDeviceRegistrationMessage(error.message || 'No se pudo vincular este dispositivo.', true);
    }
});
document.querySelector('#pwa-logout-button').addEventListener('click', async () => {
    let message = 'Sesión cerrada.';
    try {
        await apiRequest('/api/v1/person/auth/logout', { method: 'POST', body: {} });
    } catch (error) {
        if (error.status !== 401) {
            message = `Se eliminó la sesión de este dispositivo, pero no se confirmó el cierre en el servidor: ${error.message}`;
        }
    }
    localStorage.removeItem(tokenKey);
    token = null;
    personSelectedSiteId = null;
    personSelectedZoneId = null;
    pendingEvent = null;
    showLogin(message, message !== 'Sesión cerrada.');
});
document.querySelectorAll('.mobile-pwa-install').forEach((button) => {
    button.addEventListener('click', installPwa);
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallButtons(true);
});
window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    updateInstallButtons(false);
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('../mobile-pwa-sw.js', { scope: './' })
        .catch((error) => console.error('No se pudo registrar el service worker de ControlaLite:', error));
}

void restoreSession();
