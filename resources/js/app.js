import './bootstrap';

const api = window.axios;
const tokenKey = 'controlaLite.admin.token';
const accountTypeKey = 'controlaLite.account.type';
const authView = document.querySelector('#auth-view');
const workspaceView = document.querySelector('#workspace-view');
const personView = document.querySelector('#person-view');
const loginForm = document.querySelector('#login-form');
const loginButton = document.querySelector('#login-button');
const loginMessage = document.querySelector('#login-message');
const rowsContainer = document.querySelector('#presence-rows');
const dashboardMessage = document.querySelector('#dashboard-message');
const searchInput = document.querySelector('#presence-search');
const pageSize = 15;

let currentPage = 1;
let lastPage = 1;
let currentRows = [];
let currentModule = 'dashboard';
let modulePage = 1;
let moduleLastPage = 1;
let moduleTimer;
let moduleRequest = 0;
let currentModuleRows = [];
let siteMap = new Map();
let profileOptions = [];
let permissionOptions = [];
let moduleOptions = { people: [], sites: [], zones: [], access_points: [], timezones: [] };
let adminEventSelfieUrl = null;
let adminPeople = [];
let adminZones = [];
let adminAccessPoints = [];
let currentUser = null;
let reportPage = 1;
let reportLastPage = 1;
let reportTitle = '';
let reportAccessPoints = [];
let overtimeReviewPage = 1;
let overtimeReviewLastPage = 1;
let overtimeReviewSelections = new Set();
let personAttendanceState = null;
let personSelectedSiteId = null;
let personSelectedZoneId = null;
let personAttendanceZones = [];
let personZoneRequired = false;
let personEventTypes = [];
let personCameraStream = null;
let personSubmitting = false;
let personAttendanceSelfieUrls = [];
const personTypeOptions = [
    ['employee', 'Empleado'],
    ['visitor', 'Visitante'],
    ['contractor', 'Contratista'],
    ['other', 'Otro'],
];

const moduleConfig = {
    sites: {
        title: 'Sedes',
        singular: 'sede',
        createLabel: 'Nueva sede',
        description: 'Administra las sedes, su ubicación y los parámetros de geocerca.',
        endpoint: '/api/v1/admin/sites',
        readPermission: 'sites.read',
        createPermission: 'sites.create',
        updatePermission: 'sites.update',
        columns: ['Sede', 'Código', 'Dirección', 'Ubicación', 'Zona horaria', 'Geocerca', 'Estado', 'Acciones'],
        fields: [
            { name: 'code', label: 'Código', type: 'text', required: true, maxLength: 32 },
            { name: 'name', label: 'Nombre', type: 'text', required: true, maxLength: 150 },
            { name: 'address', label: 'Dirección', type: 'text', maxLength: 255 },
            { name: 'timezone', label: 'Zona horaria', type: 'select', required: true, options: 'timezones', help: 'Se usa cuando la parametrización general está configurada por sede.' },
            { name: 'status', label: 'Estado', type: 'select', required: true, options: [['active', 'Activa'], ['inactive', 'Inactiva']] },
            { name: 'location', label: 'Ubicación en Maps', type: 'map-location' },
            { name: 'geofence_radius_m', label: 'Radio de geocerca (m)', type: 'number', min: 1, max: 100000, help: 'Configura junto con latitud y longitud, o deja todos vacíos.' },
            { name: 'max_location_accuracy_m', label: 'Precisión máxima de ubicación (m)', type: 'number', required: true, min: 1, max: 5000, help: 'Se aplica cuando la sede usa geocerca.' },
        ],
        filters: [
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activas'], ['inactive', 'Inactivas']] },
        ],
    },
    users: {
        title: 'Usuarios',
        singular: 'usuario',
        createLabel: 'Nuevo usuario',
        description: 'Administra cuentas, permisos administrativos y estado de acceso.',
        endpoint: '/api/v1/admin/users',
        readPermission: 'users.read',
        createPermission: 'users.create',
        updatePermission: 'users.update',
        columns: ['Usuario', 'Identificación', 'Correo electrónico', 'Perfiles', 'Estado', 'Acciones'],
        fields: [
            { name: 'name', label: 'Nombre completo', type: 'text', required: true, maxLength: 255 },
            { name: 'identification_type', label: 'Tipo de identificación', type: 'select', required: true, options: [['CC', 'CC'], ['TI', 'TI'], ['RC', 'RC'], ['MS', 'MS'], ['AS', 'AS'], ['PA', 'PA'], ['CE', 'CE'], ['CD', 'CD'], ['PPT', 'PPT'], ['PEP', 'PEP']] },
            { name: 'identification_number', label: 'Número de identificación', type: 'text', required: true, maxLength: 64, help: 'La combinación de tipo y número debe ser única entre usuarios y personas.' },
            { name: 'email', label: 'Correo electrónico', type: 'email', required: true, maxLength: 255 },
            { name: 'password', label: 'Contraseña', type: 'password', required: true, help: 'Mínimo 12 caracteres. Déjala vacía al editar para conservarla.' },
            { name: 'profile_ids', label: 'Perfiles', type: 'multiselect', options: 'profiles', requiresPermission: 'users.manage_profiles', help: 'Los permisos efectivos se heredan de los perfiles asignados.' },
            { name: 'is_active', label: 'Estado', type: 'select', required: true, options: [['1', 'Activo'], ['0', 'Inactivo']] },
        ],
        filters: [
            { name: 'is_admin', label: 'Todos los perfiles', options: [['1', 'Administrador'], ['0', 'Usuario']] },
            { name: 'is_active', label: 'Todos los estados', options: [['1', 'Activo'], ['0', 'Inactivo']] },
        ],
    },
    zones: {
        title: 'Zonas',
        singular: 'zona',
        createLabel: 'Nueva zona',
        description: 'Organiza las áreas de acceso dentro de cada sede.',
        endpoint: '/api/v1/admin/zones',
        readPermission: 'zones.read',
        createPermission: 'zones.create',
        updatePermission: 'zones.update',
        columns: ['Zona', 'Código', 'Sede', 'Descripción', 'Ubicación', 'Geocerca', 'Estado', 'Acciones'],
        fields: [
            { name: 'site_id', label: 'Sede', type: 'select', required: true, options: 'sites', help: 'Debe existir una sede antes de registrar zonas.' },
            { name: 'code', label: 'Código', type: 'text', required: true, maxLength: 32 },
            { name: 'name', label: 'Nombre de zona', type: 'text', required: true, maxLength: 150 },
            { name: 'description', label: 'Descripción', type: 'textarea', maxLength: 2000 },
            { name: 'location', label: 'Ubicación en Maps', type: 'map-location' },
            { name: 'geofence_radius_m', label: 'Radio de geocerca (m)', type: 'number', min: 1, max: 100000, help: 'Si seleccionas esta zona al marcar, configura la ubicación y el radio.' },
            { name: 'max_location_accuracy_m', label: 'Precisión máxima de ubicación (m)', type: 'number', required: true, min: 1, max: 5000, help: 'Precisión máxima aceptada para esta zona.' },
            { name: 'status', label: 'Estado', type: 'select', required: true, options: [['active', 'Activa'], ['inactive', 'Inactiva']] },
        ],
        filters: [
            { name: 'site_id', label: 'Todas las sedes', options: 'sites' },
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activa'], ['inactive', 'Inactiva']] },
        ],
    },
    people: {
        title: 'Personas',
        singular: 'persona',
        createLabel: 'Nueva persona',
        description: 'Administra empleados, visitantes y contratistas registrados.',
        endpoint: '/api/v1/admin/people',
        readPermission: 'people.read',
        createPermission: 'people.create',
        updatePermission: 'people.update',
        columns: ['Persona', 'Identificación', 'Tipo', 'Horario', 'Correo electrónico', 'Teléfono', 'Estado', 'Acciones'],
        fields: [
            { name: 'first_name', label: 'Nombres', type: 'text', required: true, maxLength: 100 },
            { name: 'last_name', label: 'Apellidos', type: 'text', required: true, maxLength: 100 },
            { name: 'person_type', label: 'Tipo de persona', type: 'select', required: true, options: [['employee', 'Empleado'], ['visitor', 'Visitante'], ['contractor', 'Contratista'], ['other', 'Otro']] },
            { name: 'identification_type', label: 'Tipo de identificación', type: 'select', required: true, options: [['CC', 'CC'], ['TI', 'TI'], ['RC', 'RC'], ['MS', 'MS'], ['AS', 'AS'], ['PA', 'PA'], ['CE', 'CE'], ['CD', 'CD'], ['PPT', 'PPT'], ['PEP', 'PEP']] },
            { name: 'identification_number', label: 'Número de identificación', type: 'text', required: true, maxLength: 64, help: 'La combinación de tipo y número debe ser única entre usuarios y personas.' },
            { name: 'email', label: 'Correo electrónico', type: 'email', maxLength: 255 },
            { name: 'password', label: 'Contraseña de acceso', type: 'password', help: 'Opcional. Mínimo 12 caracteres para permitir el inicio de sesión.' },
            { name: 'profile_ids', label: 'Perfiles', type: 'multiselect', options: 'profiles', requiresPermission: 'people.manage_profiles', help: 'Los permisos efectivos se heredan de los perfiles asignados.' },
            { name: 'phone', label: 'Teléfono', type: 'tel', maxLength: 40 },
            { name: 'status', label: 'Estado', type: 'select', required: true, options: [['active', 'Activa'], ['inactive', 'Inactiva']] },
            { name: 'work_schedule', label: 'Horario laboral', type: 'work-schedule', requiresEmployee: true, help: 'El horario se agrega como un nuevo periodo. Los horarios anteriores se conservan como historial.' },
        ],
        filters: [
            { name: 'person_type', label: 'Todos los tipos', options: [['employee', 'Empleado'], ['visitor', 'Visitante'], ['contractor', 'Contratista'], ['other', 'Otro']] },
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activas'], ['inactive', 'Inactivas']] },
        ],
    },
    event_types: {
        title: 'Tipos de eventos',
        singular: 'tipo de evento',
        createLabel: 'Nuevo tipo de evento',
        description: 'Configura qué marcaciones pueden registrar las personas y cómo afectan su presencia.',
        endpoint: '/api/v1/admin/event-types',
        readPermission: 'event_types.read',
        createPermission: 'event_types.create',
        updatePermission: 'event_types.update',
        columns: ['Tipo de evento', 'Código', 'Efecto en presencia', 'Tipos de persona', 'Estado', 'Acciones'],
        fields: [
            { name: 'name', label: 'Nombre del evento', type: 'text', required: true, maxLength: 100 },
            { name: 'behavior', label: 'Efecto en presencia', type: 'select', required: true, readOnlyForSystem: true, help: 'Ingreso y Salida son tipos base del control de acceso y no pueden cambiar su efecto.', options: [['entry', 'Ingreso'], ['exit', 'Salida'], ['informational', 'Informativo · No cambia la presencia']] },
            { name: 'person_types', label: 'Disponible para tipos de persona', type: 'person-type-grid', options: personTypeOptions, help: 'Selecciona uno o varios tipos. Cada tipo de persona puede tener habilitados varios eventos.' },
            { name: 'status', label: 'Estado', type: 'select', required: true, readOnlyForSystem: true, options: [['active', 'Activo'], ['inactive', 'Inactivo']] },
        ],
        filters: [
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activos'], ['inactive', 'Inactivos']] },
        ],
    },
    attendance_settings: {
        title: 'Parámetros de asistencia',
        description: 'Configura los intervalos de marcación y las zonas horarias que utiliza ControlaLite.',
        endpoint: '/api/v1/admin/attendance-settings',
        readPermission: 'attendance_settings.read',
        updatePermission: 'attendance_settings.update',
        customSettings: true,
    },
    profiles: {
        title: 'Perfiles',
        singular: 'perfil',
        createLabel: 'Nuevo perfil',
        description: 'Define grupos de permisos y asígnalos a usuarios o personas.',
        endpoint: '/api/v1/admin/profiles',
        readPermission: 'profiles.read',
        createPermission: 'profiles.create',
        updatePermission: 'profiles.update',
        columns: ['Perfil', 'Identificador', 'Permisos', 'Descripción', 'Acciones'],
        fields: [
            { name: 'name', label: 'Nombre', type: 'text', required: true, maxLength: 100 },
            { name: 'slug', label: 'Identificador', type: 'text', required: true, maxLength: 120, help: 'Usa letras, números y guiones.' },
            { name: 'description', label: 'Descripción', type: 'textarea', maxLength: 1000 },
            { name: 'permission_ids', label: 'Permisos habilitados', type: 'permission-grid', options: 'permissions', requiresPermission: 'profiles.manage_permissions', help: 'Activa los permisos necesarios por módulo. Solo puedes delegar permisos que ya tiene tu cuenta.' },
        ],
        filters: [],
    },
    authorizations: {
        title: 'Autorizaciones',
        singular: 'autorización',
        createLabel: 'Nueva autorización',
        description: 'Administra el acceso autorizado de personas a sedes y zonas.',
        endpoint: '/api/v1/admin/authorizations',
        optionsEndpoint: '/api/v1/admin/authorizations/options',
        readPermission: 'authorizations.read',
        createPermission: 'authorizations.create',
        updatePermission: 'authorizations.update',
        columns: ['Persona', 'Sede / zona', 'Propósito', 'Vigencia', 'Estado', 'Acciones'],
        fields: [
            { name: 'person_id', label: 'Persona', type: 'select', required: true, options: 'people' },
            { name: 'site_id', label: 'Sede', type: 'select', required: true, options: 'sites' },
            { name: 'zone_id', label: 'Zona (opcional)', type: 'select', options: 'zones', help: 'Al seleccionar una zona, se asignará automáticamente su sede.' },
            { name: 'purpose', label: 'Propósito', type: 'text', required: true, maxLength: 255 },
            { name: 'valid_from', label: 'Vigente desde', type: 'datetime-local', required: true },
            { name: 'valid_until', label: 'Vigente hasta', type: 'datetime-local', required: true },
        ],
        filters: [
            { name: 'status', label: 'Todos los estados', options: [['pending', 'Pendientes'], ['approved', 'Aprobadas'], ['rejected', 'Rechazadas'], ['revoked', 'Revocadas']] },
            { name: 'site_id', label: 'Todas las sedes', options: 'sites' },
        ],
    },
    access_points: {
        title: 'Puntos de acceso',
        singular: 'punto de acceso',
        createLabel: 'Nuevo punto de acceso',
        description: 'Configura los puntos de entrada y salida asociados a sedes y zonas.',
        endpoint: '/api/v1/admin/access-points',
        optionsEndpoint: '/api/v1/admin/access-points/options',
        readPermission: 'access_points.read',
        createPermission: 'access_points.create',
        updatePermission: 'access_points.update',
        columns: ['Punto', 'Código', 'Sede', 'Zona', 'Dirección', 'Estado', 'Acciones'],
        fields: [
            { name: 'site_id', label: 'Sede', type: 'select', required: true, options: 'sites' },
            { name: 'zone_id', label: 'Zona (opcional)', type: 'select', options: 'zones' },
            { name: 'code', label: 'Código', type: 'text', required: true, maxLength: 32 },
            { name: 'name', label: 'Nombre', type: 'text', required: true, maxLength: 150 },
            { name: 'direction', label: 'Dirección permitida', type: 'select', required: true, options: [['entry', 'Solo entrada'], ['exit', 'Solo salida'], ['bidirectional', 'Bidireccional']] },
            { name: 'status', label: 'Estado', type: 'select', required: true, options: [['active', 'Activo'], ['inactive', 'Inactivo']] },
        ],
        filters: [
            { name: 'site_id', label: 'Todas las sedes', options: 'sites' },
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activos'], ['inactive', 'Inactivos']] },
        ],
    },
    devices: {
        title: 'Dispositivos de acceso',
        singular: 'dispositivo de acceso',
        createLabel: 'Registrar dispositivo',
        description: 'Registra equipos, configura su conexión y administra sus tokens.',
        endpoint: '/api/v1/admin/devices',
        optionsEndpoint: '/api/v1/admin/devices/options',
        readPermission: 'devices.read',
        createPermission: 'devices.create',
        updatePermission: 'devices.configure',
        columns: ['Dispositivo', 'Punto de acceso', 'Host', 'Puerto', 'Clave', 'Estado', 'Acciones'],
        fields: [
            { name: 'access_point_id', label: 'Punto de acceso', type: 'select', required: true, options: 'access_points', createOnly: true },
            { name: 'name', label: 'Nombre', type: 'text', required: true, maxLength: 120, createOnly: true },
            { name: 'hardware_host', label: 'Dirección IPv4', type: 'text', required: true, maxLength: 45 },
            { name: 'hardware_port', label: 'Puerto UDP', type: 'number', min: 1, max: 65535 },
            { name: 'communication_key', label: 'Clave de comunicación (opcional)', type: 'number', min: 0, max: 2147483647, help: 'Se almacena cifrada. Déjala vacía para conservarla al editar.' },
        ],
        filters: [
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activos'], ['revoked', 'Revocados']] },
        ],
    },
    credentials: {
        title: 'Credenciales',
        singular: 'credencial',
        createLabel: 'Emitir credencial',
        description: 'Emite credenciales QR o RFID y revócalas cuando dejen de ser válidas.',
        endpoint: '/api/v1/admin/credentials',
        optionsEndpoint: '/api/v1/admin/credentials/options',
        readPermission: 'credentials.read',
        createPermission: 'credentials.create',
        updatePermission: 'credentials.revoke',
        columns: ['Persona', 'Tipo', 'Etiqueta', 'Vigencia', 'Estado', 'Acciones'],
        fields: [
            { name: 'person_id', label: 'Persona', type: 'select', required: true, options: 'people' },
            { name: 'credential_type', label: 'Tipo', type: 'select', required: true, options: [['qr', 'QR generado'], ['rfid', 'RFID']] },
            { name: 'token', label: 'Identificador RFID', type: 'text', maxLength: 255 },
            { name: 'label', label: 'Etiqueta', type: 'text', maxLength: 100 },
            { name: 'valid_from', label: 'Vigente desde (opcional)', type: 'datetime-local' },
            { name: 'valid_until', label: 'Vigente hasta (opcional)', type: 'datetime-local' },
        ],
        filters: [
            { name: 'credential_type', label: 'Todos los tipos', options: [['qr', 'QR'], ['rfid', 'RFID']] },
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activas'], ['revoked', 'Revocadas']] },
        ],
    },
    mobile_devices: {
        title: 'Dispositivos móviles',
        singular: 'código de vinculación',
        createLabel: 'Emitir código de vinculación',
        description: 'Consulta los equipos móviles vinculados y emite códigos de un solo uso.',
        endpoint: '/api/v1/admin/mobile-devices',
        optionsEndpoint: '/api/v1/admin/mobile-devices/options',
        createPermission: 'people.manage_mobile',
        readPermission: 'people.manage_mobile',
        updatePermission: 'people.manage_mobile',
        createMode: 'mobile-enrollment',
        columns: ['Persona', 'Dispositivo', 'Plataforma', 'Último uso', 'Estado', 'Acciones'],
        fields: [
            { name: 'person_id', label: 'Persona activa', type: 'select', required: true, options: 'people' },
            { name: 'expires_in_minutes', label: 'Expiración del código (minutos)', type: 'number', min: 5, max: 1440 },
        ],
        filters: [
            { name: 'status', label: 'Todos los estados', options: [['active', 'Activos'], ['revoked', 'Revocados']] },
            { name: 'person_id', label: 'Todas las personas', options: 'people' },
        ],
    },
    access_events: {
        title: 'Eventos de acceso',
        singular: 'evento',
        description: 'Consulta el historial auditable de decisiones, ubicación y marcaciones.',
        endpoint: '/api/v1/admin/access-events',
        optionsEndpoint: '/api/v1/admin/access-events/options',
        readPermission: 'access_events.read',
        createPermission: null,
        updatePermission: null,
        columns: ['Fecha y hora', 'Persona', 'Sede', 'Evento', 'Desviación', 'Tiempo trabajado', 'Tiempo extra', 'Canal', 'Decisión', 'Acciones'],
        fields: [],
        filters: [
            { name: 'site_id', label: 'Todas las sedes', options: 'sites' },
            { name: 'decision', label: 'Todas las decisiones', options: [['allowed', 'Permitidos'], ['denied', 'Denegados']] },
            { name: 'event_type', label: 'Todos los tipos', options: [['entry', 'Entrada'], ['exit', 'Salida'], ['lunch', 'Informativo']] },
        ],
    },
    reports: {
        title: 'Reportes',
        singular: 'reporte',
        description: 'Genera y exporta informes de actividad, asistencia y autorizaciones.',
        readPermission: 'reports.read',
        customReports: true,
    },
    overtime_reviews: {
        title: 'Validación de horas extra',
        singular: 'registro de horas extra',
        description: 'Revisa, clasifica y decide sobre el tiempo extra registrado.',
        readPermission: 'overtime_reviews.read',
        customOvertime: true,
    },
};

function createElement(tag, className, text = '') {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    function localDateTimeValue(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value).slice(0, 16);
        }
        const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

        return localDate.toISOString().slice(0, 16);
    }

    if (text) {
        element.textContent = text;
    }

    return element;
}

function coordinatesFromText(value) {
    const text = value.trim();
    if (!text) {
        return null;
    }

    const parameterMatch = text.match(/[?&](?:q|query|ll|center)=([^&#]+)/i);
    if (parameterMatch) {
        try {
            const parameterCoordinates = decodeURIComponent(parameterMatch[1].replaceAll('+', ' ')).match(
                /^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/,
            );
            if (parameterCoordinates) {
                return [parameterCoordinates[1], parameterCoordinates[2]];
            }
        } catch {
            return null;
        }
    }

    const mapsCoordinates = text.match(/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/)
        || text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
    if (mapsCoordinates) {
        return [mapsCoordinates[1], mapsCoordinates[2]];
    }

    const directCoordinates = text.match(/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/);
    return directCoordinates ? [directCoordinates[1], directCoordinates[2]] : null;
}

function buildMapLocationEditor(record) {
    const editor = createElement('div', 'map-location-editor');
    editor.dataset.mapLocation = 'true';

    const mapsField = createElement('label', 'map-url-field');
    const mapsLabel = createElement('span', 'field-label', 'Enlace o coordenadas de Google Maps');
    const mapsInput = createElement('input', 'form-control');
    mapsInput.type = 'text';
    mapsInput.placeholder = 'Pega el enlace de Google Maps o 4.7110, -74.0721';
    mapsInput.autocomplete = 'off';
    mapsInput.className += ' map-url-input';
    mapsField.append(mapsLabel, mapsInput);

    const coordinates = createElement('div', 'map-coordinate-grid');
    const latitude = createElement('label', 'map-coordinate-field');
    latitude.append(createElement('span', 'field-label', 'Latitud'));
    const latitudeInput = createElement('input', 'form-control map-latitude');
    latitudeInput.type = 'number';
    latitudeInput.step = 'any';
    latitudeInput.min = '-90';
    latitudeInput.max = '90';
    latitudeInput.placeholder = '-90 a 90';
    latitude.append(latitudeInput);

    const longitude = createElement('label', 'map-coordinate-field');
    longitude.append(createElement('span', 'field-label', 'Longitud'));
    const longitudeInput = createElement('input', 'form-control map-longitude');
    longitudeInput.type = 'number';
    longitudeInput.step = 'any';
    longitudeInput.min = '-180';
    longitudeInput.max = '180';
    longitudeInput.placeholder = '-180 a 180';
    longitude.append(longitudeInput);

    const preview = createElement('a', 'map-preview-link', 'Ver ubicación en Google Maps');
    preview.target = '_blank';
    preview.rel = 'noopener noreferrer';
    preview.hidden = true;
    const help = createElement('span', 'field-help map-location-help', 'La ubicación es opcional y se guarda en el registro actual.');
    coordinates.append(latitude, longitude);
    editor.append(mapsField, coordinates, preview, help);

    if (record?.latitude !== null && record?.latitude !== undefined
        && record?.longitude !== null && record?.longitude !== undefined) {
        latitudeInput.value = record.latitude;
        longitudeInput.value = record.longitude;
    }

    const updatePreview = () => {
        const lat = latitudeInput.value.trim();
        const lon = longitudeInput.value.trim();
        const valid = lat !== '' && lon !== ''
            && Number(lat) >= -90 && Number(lat) <= 90
            && Number(lon) >= -180 && Number(lon) <= 180;
        preview.hidden = !valid;
        if (valid) {
            preview.href = `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lon}`)}`;
        }
    };

    mapsInput.addEventListener('input', () => {
        const values = coordinatesFromText(mapsInput.value);
        if (values) {
            latitudeInput.value = values[0];
            longitudeInput.value = values[1];
            help.textContent = 'Coordenadas detectadas. Confírmalas y guarda la zona.';
        } else if (mapsInput.value.trim()) {
            latitudeInput.value = '';
            longitudeInput.value = '';
            help.textContent = 'No se detectaron coordenadas. Pega un enlace completo de Maps o escribe latitud y longitud abajo.';
        } else {
            help.textContent = 'La ubicación es opcional y se guarda en el registro actual.';
        }
        updatePreview();
    });
    [latitudeInput, longitudeInput].forEach((input) => input.addEventListener('input', updatePreview));
    updatePreview();

    return editor;
}

function getToken() {
    return sessionStorage.getItem(tokenKey);
}

function hasPermission(permission) {
    return Boolean(currentUser?.is_admin || currentUser?.permissions?.includes(permission));
}

function showLogin(message = '') {
    stopPersonCamera();
    personAttendanceState = null;
    personSelectedSiteId = null;
    personSelectedZoneId = null;
    personAttendanceZones = [];
    personZoneRequired = false;
    personEventTypes = [];
    clearPersonAttendanceSelfieUrls();
    document.querySelector('#attendance-history').replaceChildren();
    document.querySelector('#database-maintenance-card').hidden = true;
    currentUser = null;
    workspaceView.hidden = true;
    personView.hidden = true;
    authView.hidden = false;
    loginMessage.textContent = message;
    loginMessage.hidden = !message;
}

function showWorkspace(user) {
    if (user.account_type === 'person') {
        sessionStorage.removeItem(tokenKey);
        sessionStorage.removeItem(accountTypeKey);
        showLogin('El acceso de personas está disponible únicamente desde /mobile/.');
        return;
    }

    authView.hidden = true;
    workspaceView.hidden = false;
    personView.hidden = true;
    currentUser = user;
    const requestedModule = window.location.hash.slice(1);

    const name = user.name || user.email || 'Administrador';
    document.querySelector('#user-name').textContent = name;
    document.querySelector('#user-avatar').textContent = name.trim().charAt(0).toUpperCase() || 'A';
    document.querySelector('#user-account-label').textContent = user.is_admin ? 'Administrador' : 'Acceso por rol';
    const databaseMaintenanceCard = document.querySelector('#database-maintenance-card');
    const canReadMigrations = hasPermission('database.migrations.read');
    const canApplyMigrations = hasPermission('database.migrations.apply');
    document.querySelector('#check-database-migrations').hidden = !canReadMigrations;
    document.querySelector('#open-database-migration').hidden = !canApplyMigrations;
    databaseMaintenanceCard.hidden = !canReadMigrations && !canApplyMigrations;
    if (canReadMigrations) {
        void loadDatabaseMigrationStatus();
    } else if (canApplyMigrations) {
        document.querySelector('#database-migration-status').textContent = 'No tienes permiso para consultar el estado de las migraciones.';
        document.querySelector('#open-database-migration').disabled = false;
    }
    document.querySelectorAll('.nav-link[data-permission]').forEach((link) => {
        link.hidden = !hasPermission(link.dataset.permission)
            || (link.dataset.module === 'zones' && !hasPermission('sites.read'));
    });
    document.querySelectorAll('.nav-group').forEach((group) => {
        group.hidden = !group.querySelector('.nav-link:not([hidden])');
    });
    dashboardMessage.hidden = true;
    currentPage = 1;
    const firstModule = [...document.querySelectorAll('.nav-link[data-module]')]
        .find((link) => !link.hidden)?.dataset.module;
    if (requestedModule && moduleConfig[requestedModule]
        && hasPermission(moduleConfig[requestedModule].readPermission)) {
        void setCurrentModule(requestedModule);
        return;
    }
    if (firstModule) {
        void setCurrentModule(firstModule);
        if (firstModule === 'dashboard' || firstModule === 'presence') {
            loadPresence();
        }
    } else {
        document.querySelector('#module-message').textContent = 'Tu cuenta no tiene módulos habilitados. Contacta a un administrador.';
        document.querySelector('#module-message').hidden = false;
        document.querySelector('#management-page').hidden = false;
        document.querySelector('#dashboard-page').hidden = true;
    }
}

function renderDatabaseMigrationStatus(status, message = '') {
    const statusElement = document.querySelector('#database-migration-status');
    const connectionElement = document.querySelector('#database-migration-connection');
    const migrationList = document.querySelector('#database-migration-list');
    const updateButton = document.querySelector('#open-database-migration');
    const errorElement = document.querySelector('#database-migration-error');

    statusElement.textContent = message || (status.is_up_to_date
        ? `Base de datos actualizada: ${status.ran_count} migraciones aplicadas.`
        : `Hay ${status.pending_count} migraciones pendientes${status.missing_count ? ` y ${status.missing_count} migraciones registradas sin archivo` : ''}.`);
    connectionElement.textContent = `Conexión: ${status.connection} · Base de datos: ${status.database || 'no identificada'}`;
    migrationList.replaceChildren();
    status.pending_migrations.forEach((migration) => {
        const item = document.createElement('li');
        item.textContent = `Pendiente: ${migration}`;
        migrationList.append(item);
    });
    status.missing_migrations.forEach((migration) => {
        const item = document.createElement('li');
        item.textContent = `Sin archivo local: ${migration}`;
        migrationList.append(item);
    });
    updateButton.disabled = status.pending_count === 0;
    errorElement.hidden = true;
}

async function loadDatabaseMigrationStatus() {
    const statusElement = document.querySelector('#database-migration-status');
    const errorElement = document.querySelector('#database-migration-error');
    const updateButton = document.querySelector('#open-database-migration');
    const refreshButton = document.querySelector('#check-database-migrations');

    statusElement.textContent = 'Consultando el estado de las migraciones...';
    errorElement.hidden = true;
    updateButton.disabled = true;
    refreshButton.disabled = true;

    try {
        const response = await api.get('/api/v1/admin/database/migrations', { headers: authHeaders() });
        renderDatabaseMigrationStatus(response.data.data);
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        statusElement.textContent = 'No fue posible consultar el estado de la base de datos.';
        errorElement.textContent = error.response?.data?.message || 'Verifica tu conexión e inténtalo de nuevo.';
        errorElement.hidden = false;
    } finally {
        refreshButton.disabled = false;
    }
}

function openDatabaseMigrationDialog() {
    const pending = document.querySelector('#database-migration-list').querySelectorAll('li');
    const confirmationList = document.querySelector('#database-migration-confirm-list');
    confirmationList.replaceChildren();
    [...pending]
        .filter((item) => item.textContent.startsWith('Pendiente: '))
        .forEach((item) => {
            const entry = document.createElement('li');
            entry.textContent = item.textContent.replace('Pendiente: ', '');
            confirmationList.append(entry);
        });

    if (confirmationList.childElementCount === 0) {
        if (hasPermission('database.migrations.read')) {
            void loadDatabaseMigrationStatus();
            return;
        }
        const entry = document.createElement('li');
        entry.textContent = 'No se puede mostrar la lista; se solicitará al servidor aplicar las migraciones pendientes.';
        confirmationList.append(entry);
    }

    document.querySelector('#database-migration-confirmed').checked = false;
    document.querySelector('#confirm-database-migration').disabled = true;
    document.querySelector('#database-migration-confirm-error').hidden = true;
    document.querySelector('#database-migration-dialog').showModal();
}

async function applyDatabaseMigrations(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submitButton = document.querySelector('#confirm-database-migration');
    const errorElement = document.querySelector('#database-migration-confirm-error');

    if (!form.reportValidity()) {
        return;
    }

    submitButton.disabled = true;
    submitButton.textContent = 'Actualizando...';
    errorElement.hidden = true;

    try {
        const response = await api.post('/api/v1/admin/database/migrate', { confirmed: true }, {
            headers: authHeaders(),
        });
        document.querySelector('#database-migration-dialog').close();
        renderDatabaseMigrationStatus(response.data.data, response.data.message);
    } catch (error) {
        errorElement.textContent = error.response?.data?.message || 'No se pudo completar la actualización. Revisa el registro de Laravel.';
        errorElement.hidden = false;
    } finally {
        submitButton.textContent = 'Aplicar migraciones';
        submitButton.disabled = !document.querySelector('#database-migration-confirmed').checked;
    }
}

function showPersonPortal(person) {
    authView.hidden = true;
    workspaceView.hidden = true;
    personView.hidden = false;
    document.querySelector('#person-name').textContent = person.name || person.email || 'Persona';
    document.querySelector('#person-email').textContent = person.email || 'Sin correo registrado';
    document.querySelector('#person-type').textContent = formatPersonType(person.person_type);
    personAttendanceState = null;
    personSelectedSiteId = null;
    personSelectedZoneId = null;
    personAttendanceZones = [];
    personZoneRequired = false;
    void loadPersonAttendance();
}

function showAttendanceMessage(message, isError = false) {
    const element = document.querySelector('#attendance-message');
    element.textContent = message;
    element.hidden = !message;
    element.classList.toggle('is-error', isError);
}

function formatPortalDateTime(value, timeZone) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timeZone || undefined,
    }).format(new Date(value));
}

function formatPortalTime(value, timeZone) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timeZone || undefined,
    }).format(new Date(value));
}

function formatWorkedMinutes(minutes) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return [hours ? `${hours} h` : '', remainingMinutes ? `${remainingMinutes} min` : '']
        .filter(Boolean)
        .join(' ') || 'menos de un minuto';
}

function formatScheduleDeviation(minutes, behavior = 'entry') {
    if (minutes === null || minutes === undefined || minutes === '') {
        return '—';
    }

    const deviation = Number(minutes);
    if (!Number.isFinite(deviation)) {
        return '—';
    }
    if (deviation === 0) {
        return 'A tiempo';
    }

    const amount = formatWorkedMinutes(Math.abs(deviation));
    if (behavior === 'exit') {
        return `${amount} ${deviation > 0 ? 'después de la hora de salida' : 'antes de la hora de salida'}`;
    }

    return `${amount} ${deviation > 0 ? 'tarde' : 'antes de la hora de entrada'}`;
}

function renderPersonAttendance(data) {
    const siteSelect = document.querySelector('#person-site-select');
    const zoneSelect = document.querySelector('#person-zone-select');
    const previousSiteId = personSelectedSiteId;
    const previousZoneId = personSelectedZoneId;
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
    siteSelect.disabled = data.sites.length < 2;

    personAttendanceZones = data.zones || [];
    personZoneRequired = Boolean(data.zone_required);
    personSelectedZoneId = personAttendanceZones.some((zone) => String(zone.id) === String(previousZoneId))
        ? previousZoneId
        : data.selected_zone_id;
    zoneSelect.replaceChildren(new Option(
        personZoneRequired ? 'Selecciona una zona autorizada' : 'Sin zona específica',
        '',
    ));
    personAttendanceZones.forEach((zone) => {
        zoneSelect.add(new Option(zone.name, String(zone.id)));
    });
    zoneSelect.value = personSelectedZoneId ? String(personSelectedZoneId) : '';
    zoneSelect.disabled = personSubmitting || Boolean(personCameraStream)
        || (!personZoneRequired && personAttendanceZones.length === 0);

    personEventTypes = data.event_types;
    const informationalSelect = document.querySelector('#attendance-informational-type');
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
    const currentState = document.querySelector('#attendance-current-state');
    currentState.textContent = data.attendance.open_entry
        ? `Tienes una entrada pendiente desde ${formatPortalDateTime(data.attendance.open_entry.event_at)}. Registra la salida para cerrar el ciclo.`
        : data.attendance.missing_exit
            ? 'La entrada pendiente superó su vigencia y quedó marcada como sin registro de salida. La próxima marcación será un nuevo ingreso.'
            : 'No hay una entrada pendiente. La próxima marcación de asistencia será una entrada.';

    const site = data.sites.find((item) => String(item.id) === String(personSelectedSiteId));
    const schedule = document.querySelector('#attendance-schedule');
    schedule.textContent = data.attendance.scheduled_start_at && data.attendance.scheduled_end_at
        ? `Horario vigente: ${formatPortalTime(data.attendance.scheduled_start_at, site?.timezone)}–${formatPortalTime(data.attendance.scheduled_end_at, site?.timezone)}${data.attendance.next_behavior === 'entry' ? ` · Entrada desde ${formatPortalTime(data.attendance.allowed_entry_from, site?.timezone)} (hasta ${data.attendance.early_entry_minutes} min antes).` : ` · Salida hasta ${formatPortalTime(data.attendance.allowed_exit_until, site?.timezone)} (${data.attendance.max_daily_overtime_minutes} min máximo después del fin; margen sin novedad: ${data.attendance.late_exit_grace_minutes} min).`}${data.attendance.reason === 'late_entry' ? ' Se requiere novedad por llegada tarde.' : ''}${data.attendance.reason === 'overtime' ? ' Se requiere novedad por tiempo extra.' : ''}${data.attendance.blocking_reason === 'entry_too_early' ? ' Aún no se permite registrar la entrada.' : ''}${data.attendance.blocking_reason === 'entry_window_closed' ? ' Ya pasó la hora límite para registrar la entrada.' : ''}${data.attendance.blocking_reason === 'exit_window_closed' ? ' Se superó el máximo diario de tiempo extra para registrar la salida.' : ''}`
        : data.attendance.blocking_reason === 'no_schedule'
            ? 'No hay un horario laboral aplicable; no se permite registrar asistencia.'
            : data.attendance.blocking_reason === 'no_shift_today'
                ? 'No hay un turno programado para hoy; no se permite registrar asistencia.'
                : 'Sin horario vigente para esta marcación.';

    const hasSite = Boolean(personSelectedSiteId);
    const hasRequiredZone = !personZoneRequired || Boolean(personSelectedZoneId);
    const entryAvailable = personEventTypes.some((type) => type.behavior === 'entry');
    const exitAvailable = personEventTypes.some((type) => type.behavior === 'exit');
    const entryButton = document.querySelector('#attendance-entry-button');
    const exitButton = document.querySelector('#attendance-exit-button');
    const entryIsNext = data.attendance.next_behavior === 'entry';
    entryButton.classList.toggle('button-primary', entryIsNext);
    entryButton.classList.toggle('button-secondary', !entryIsNext);
    exitButton.classList.toggle('button-primary', !entryIsNext);
    exitButton.classList.toggle('button-secondary', entryIsNext);
    entryButton.disabled =
        personSubmitting || Boolean(personCameraStream) || !hasSite || !hasRequiredZone || !entryIsNext || !entryAvailable || data.attendance.marking_allowed === false;
    exitButton.disabled =
        personSubmitting || Boolean(personCameraStream) || !hasSite || !hasRequiredZone || entryIsNext || !exitAvailable || data.attendance.marking_allowed === false;
    document.querySelector('#attendance-informational-button').disabled =
        personSubmitting || Boolean(personCameraStream) || !hasSite || informationalSelect.options.length === 0
        || !hasRequiredZone || !informationalSelect.value;
    siteSelect.disabled = personSubmitting || personCameraStream !== null || data.sites.length < 2;

    const noveltyField = document.querySelector('#attendance-novelty-field');
    const novelty = document.querySelector('#attendance-novelty');
    noveltyField.hidden = !data.attendance.novelty_required;
    if (!data.attendance.novelty_required) {
        novelty.value = '';
    }

    renderAttendanceHistory(data.events);
}

function renderAttendanceHistory(events) {
    const container = document.querySelector('#attendance-history');
    clearPersonAttendanceSelfieUrls();
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
        const selfie = document.createElement('figure');
        selfie.className = 'attendance-history-selfie';
        const image = document.createElement('img');
        image.src = '/images/user-placeholder.svg';
        image.alt = 'Icono de usuario';
        const caption = document.createElement('figcaption');
        caption.textContent = event.has_selfie ? 'Cargando selfie…' : 'Sin selfie';
        selfie.append(image, caption);

        const content = document.createElement('div');
        content.className = 'attendance-history-content';
        const title = document.createElement('strong');
        title.textContent = `${event.event_type_name || event.event_type} · ${event.status === 'accepted' ? 'Aceptada' : 'Rechazada'}`;
        const details = [
            formatPortalDateTime(event.event_at),
            event.schedule_deviation_minutes !== null
                ? `Desviación: ${formatScheduleDeviation(event.schedule_deviation_minutes, event.event_type_behavior)}`
                : null,
            event.worked_minutes !== null ? `Tiempo trabajado: ${formatWorkedMinutes(event.worked_minutes)}` : null,
            event.overtime_minutes > 0 ? `Tiempo extra: ${formatWorkedMinutes(event.overtime_minutes)}` : null,
            event.novelty ? `Novedad: ${event.novelty}` : null,
            event.status === 'rejected' ? `Motivo: ${event.reason_code}` : null,
        ].filter(Boolean);
        const detailText = document.createElement('p');
        detailText.textContent = details.join(' · ');
        content.append(title, detailText);
        item.append(selfie, content);
        container.append(item);

        if (event.has_selfie) {
            void api.get(`/api/v1/person/auth/attendance/events/${event.id}/selfie`, {
                headers: authHeaders(),
                responseType: 'blob',
            }).then((response) => {
                if (!image.isConnected) {
                    return;
                }

                const selfieUrl = URL.createObjectURL(response.data);
                personAttendanceSelfieUrls.push(selfieUrl);
                image.src = selfieUrl;
                image.alt = 'Selfie de la marcación';
                caption.textContent = 'Selfie guardada';
            }).catch(() => {
                if (image.isConnected) {
                    image.title = 'No se pudo cargar la selfie guardada.';
                    caption.textContent = 'Selfie no disponible';
                }
            });
        }
    });
}

function clearPersonAttendanceSelfieUrls() {
    personAttendanceSelfieUrls.forEach((url) => URL.revokeObjectURL(url));
    personAttendanceSelfieUrls = [];
}

async function loadPersonAttendance() {
    const query = new URLSearchParams();
    if (personSelectedSiteId) query.set('site_id', personSelectedSiteId);
    if (personSelectedZoneId) query.set('zone_id', personSelectedZoneId);
    const queryString = query.size ? `?${query}` : '';
    try {
        const response = await api.get(`/api/v1/person/auth/attendance${queryString}`, { headers: authHeaders() });
        renderPersonAttendance(response.data.data);
        if (response.data.data.sites.length === 0) {
            showAttendanceMessage('No tienes una autorización aprobada y vigente para registrar asistencia en una sede.', true);
        } else {
            showAttendanceMessage('');
        }
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            sessionStorage.removeItem(accountTypeKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        showAttendanceMessage(error.response?.data?.message || 'No se pudo cargar la información de asistencia.', true);
    }
}

function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Este navegador no permite consultar la ubicación.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve(position),
            (error) => reject(new Error(error.message || 'No se pudo obtener la ubicación.')),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
        );
    });
}

function stopPersonCamera() {
    personCameraStream?.getTracks().forEach((track) => track.stop());
    personCameraStream = null;
    document.querySelector('#person-camera-preview').srcObject = null;
    const dialog = document.querySelector('#person-camera-dialog');
    if (dialog.open) {
        dialog.close();
    }
}

async function startPersonAttendance(eventType) {
    const siteId = personSelectedSiteId;
    if (!siteId) {
        showAttendanceMessage('No hay una sede autorizada seleccionada.', true);
        return;
    }
    if (personZoneRequired && !personSelectedZoneId) {
        showAttendanceMessage('Selecciona la zona autorizada para registrar la marcación.', true);
        document.querySelector('#person-zone-select').focus();
        return;
    }

    const type = personEventTypes.find((item) => item.code === eventType);
    if (!type) {
        showAttendanceMessage('El tipo de evento ya no está disponible. Actualiza el portal e inténtalo de nuevo.', true);
        return;
    }
    if (personSubmitting || personCameraStream) {
        return;
    }
    if (type.behavior !== 'informational' && type.behavior !== personAttendanceState?.next_behavior) {
        showAttendanceMessage('El estado de asistencia cambió. Actualiza el portal antes de marcar.', true);
        await loadPersonAttendance();
        return;
    }

    const novelty = document.querySelector('#attendance-novelty').value.trim();
    if (type.behavior !== 'informational' && personAttendanceState?.novelty_required && !novelty) {
        showAttendanceMessage('Escribe la novedad obligatoria antes de registrar.', true);
        document.querySelector('#attendance-novelty').focus();
        return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        showAttendanceMessage('La cámara requiere HTTPS o una dirección local segura y un navegador compatible.', true);
        return;
    }

    showAttendanceMessage('');
    try {
        personCameraStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: 'user' },
        });
        document.querySelector('#capture-attendance-selfie').dataset.eventType = eventType;
        document.querySelector('#person-site-select').disabled = true;
        document.querySelector('#person-zone-select').disabled = true;
        document.querySelector('#person-camera-preview').srcObject = personCameraStream;
        document.querySelector('#person-camera-dialog').showModal();
    } catch (error) {
        stopPersonCamera();
        await loadPersonAttendance();
        showAttendanceMessage(error.message || 'No se pudo acceder a la cámara. Revisa los permisos del navegador.', true);
    }
}

async function captureAndSubmitPersonAttendance() {
    if (personSubmitting) {
        return;
    }
    const video = document.querySelector('#person-camera-preview');
    if (!video.videoWidth || !video.videoHeight) {
        showAttendanceMessage('La cámara todavía no está lista. Espera un momento e inténtalo de nuevo.', true);
        return;
    }

    personSubmitting = true;
    const captureButton = document.querySelector('#capture-attendance-selfie');
    captureButton.disabled = true;
    let finalMessage = '';
    let finalError = false;
    try {
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const selfie = await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('No se pudo preparar la selfie.'));
                }
            }, 'image/jpeg', 0.85);
        });
        stopPersonCamera();

        showAttendanceMessage('Obteniendo ubicación precisa…');
        const position = await getCurrentLocation();
        const eventType = captureButton.dataset.eventType;
        const form = new FormData();
        form.append('event_uuid', crypto.randomUUID());
        form.append('site_id', String(personSelectedSiteId));
        if (personSelectedZoneId) {
            form.append('zone_id', personSelectedZoneId);
        }
        form.append('event_type', eventType);
        form.append('client_event_at', new Date().toISOString());
        form.append('latitude', String(position.coords.latitude));
        form.append('longitude', String(position.coords.longitude));
        form.append('location_accuracy_m', String(position.coords.accuracy));
        form.append('novelty', document.querySelector('#attendance-novelty').value.trim());
        form.append('selfie', selfie, 'selfie.jpg');

        showAttendanceMessage('Enviando marcación con selfie y ubicación…');
        const response = await api.post(
            '/api/v1/person/auth/attendance/events',
            form,
            { headers: authHeaders() },
        );
        const event = response.data.data;
        finalMessage = event.status === 'accepted'
            ? `${event.event_type_name} aceptada por el servidor.`
            : `Marcación rechazada: ${event.reason_code}.`;
        finalError = event.status !== 'accepted';
        document.querySelector('#attendance-novelty').value = '';
    } catch (error) {
        finalMessage = Object.values(error.response?.data?.errors || {}).flat()[0]
            || error.response?.data?.message
            || error.message
            || 'No se pudo registrar la marcación.';
        finalError = true;
    } finally {
        personSubmitting = false;
        captureButton.disabled = false;
        stopPersonCamera();
        await loadPersonAttendance();
        if (finalMessage) {
            showAttendanceMessage(finalMessage, finalError);
        }
    }
}

function authHeaders() {
    return { Authorization: `Bearer ${getToken()}` };
}

function setLoading(message) {
    rowsContainer.replaceChildren();
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 5;
    cell.className = 'table-loading';
    cell.textContent = message;
    row.append(cell);
    rowsContainer.append(row);
}

function initialsFor(person) {
    return `${person.first_name?.[0] || ''}${person.last_name?.[0] || ''}`.toUpperCase() || '?';
}

function formatPersonType(type) {
    const labels = {
        employee: 'Empleado',
        visitor: 'Visitante',
        contractor: 'Contratista',
        other: 'Otro',
    };

    return labels[type] || type || 'Sin tipo';
}

function workScheduleSummary(schedules) {
    if (!schedules?.length) {
        return 'Sin horario';
    }

    const schedule = schedules[0];
    const today = localDateValue();
    if (schedule.valid_from > today) {
        return `Próximo desde ${schedule.valid_from}`;
    }
    if (schedule.valid_until && schedule.valid_until < today) {
        return `Venció ${schedule.valid_until}`;
    }

    return schedule.is_indefinite
        ? `Vigente · Indefinido`
        : `Vigente hasta ${schedule.valid_until}`;
}

function formatDate(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: document.documentElement.dataset.appTimezone || undefined,
    }).format(date);
}

function formatTime(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: document.documentElement.dataset.appTimezone || undefined,
    }).format(new Date(value));
}

async function setCurrentModule(moduleName) {
    const selectionId = ++moduleRequest;
    const requestedConfig = moduleConfig[moduleName];
    if (requestedConfig && (!hasPermission(requestedConfig.readPermission)
        || (moduleName === 'zones' && !hasPermission('sites.read')))) {
        const message = 'Tu rol no tiene permiso para consultar este módulo.';
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
        return;
    }
    if (!requestedConfig && !['dashboard', 'presence'].includes(moduleName)) {
        return;
    }
    if (!requestedConfig && !hasPermission('presence.read')) {
        return;
    }
    currentModule = moduleName;
    const managementPage = document.querySelector('#management-page');
    const dashboardPage = document.querySelector('#dashboard-page');
    const isManagement = Object.hasOwn(moduleConfig, moduleName);

    dashboardPage.hidden = isManagement;
    managementPage.hidden = !isManagement;
    document.querySelectorAll('.nav-link[data-module]').forEach((link) => {
        const active = link.dataset.module === moduleName;
        link.classList.toggle('nav-link-active', active);

        if (active) {
            link.setAttribute('aria-current', 'page');
            const group = link.closest('.nav-group');
            const toggle = group?.querySelector('[data-nav-toggle]');
            if (group && toggle?.getAttribute('aria-expanded') !== 'true') {
                const groupId = toggle.getAttribute('aria-controls');
                const label = toggle.querySelector('span').textContent.trim();
                group.classList.remove('nav-group-collapsed');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.title = `Contraer grupo ${label}`;
                sessionStorage.setItem(`controlaLite.nav.collapsed.${groupId}`, '0');
            }
        } else {
            link.removeAttribute('aria-current');
        }
    });

    if (!isManagement) {
        document.querySelector('#breadcrumb-current').textContent =
            moduleName === 'presence' ? 'Presencia actual' : 'Resumen';
        if (moduleName === 'presence') {
            document.querySelector('#presence').scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        return;
    }

    const config = moduleConfig[moduleName];
    document.querySelector('#breadcrumb-current').textContent = config.title;
    document.querySelector('#module-title').textContent = config.title;
    document.querySelector('#module-description').textContent = config.description;
    document.querySelector('#table-title').textContent = config.title;
    document.querySelector('#create-record-label').textContent = config.createLabel;
    document.querySelector('#create-record-button').hidden =
        !config.createPermission || !hasPermission(config.createPermission);
    document.querySelector('#module-refresh-button').hidden = moduleName !== 'access_events';
    document.querySelector('#table-description').textContent = `Consulta y administra ${config.title.toLocaleLowerCase('es-CO')}.`;
    document.querySelector('#module-table-card').hidden = Boolean(config.customSettings || config.customReports || config.customOvertime);
    document.querySelector('#attendance-settings-card').hidden = !config.customSettings;
    document.querySelector('#reports-card').hidden = !config.customReports;
    document.querySelector('#overtime-reviews-card').hidden = !config.customOvertime;
    document.querySelector('#save-attendance-settings').hidden =
        !hasPermission(config.updatePermission);
    modulePage = 1;
    document.querySelector('#module-message').hidden = true;

    try {
        if (moduleName === 'zones') {
            await loadSites();
        }
        if (moduleName === 'sites') {
            await loadTimezoneOptions();
        }
        if (moduleConfig[moduleName]?.optionsEndpoint) {
            await loadModuleOptions(moduleName);
        }
        if (moduleName === 'attendance_settings') {
            await loadAttendanceSettings();
        }
        if (moduleName === 'reports') {
            await loadReportOptions();
        }
        if (moduleName === 'overtime_reviews') {
            await loadOvertimeReviewOptions();
        }
        if (selectionId !== moduleRequest || moduleName !== currentModule) {
            return;
        }
        } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const message = error.response?.data?.message || 'No fue posible cargar las sedes.';
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
        setModuleTableMessage(message, 'table-error');
        return;
    }

    if (config.customSettings) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    if (config.customReports || config.customOvertime) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    buildModuleTableHead(config);
    buildModuleFilters(config);
    loadModuleRecords();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function buildModuleTableHead(config) {
    const head = document.querySelector('#module-table-head');
    const row = document.createElement('tr');

    config.columns.forEach((column) => {
        row.append(createElement('th', '', column.toLocaleUpperCase('es-CO')));
    });

    head.replaceChildren(row);
}

function applyResponsiveTableLabels(table, labels) {
    table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = [...row.cells];
        if (cells.length === 1 && cells[0].colSpan > 1) {
            row.classList.add('table-message-row');
            return;
        }

        cells.forEach((cell, index) => {
            cell.dataset.label = labels[index] || '';
        });
    });
}

function filterOptions(options) {
    if (options === 'sites') {
        if (moduleOptions.sites.length > 0) {
            return moduleOptions.sites.map((site) => [String(site.id), `${site.name}${site.code ? ` (${site.code})` : ''}`]);
        }
        return [...siteMap.entries()].map(([id, site]) => [String(id), `${site.name} (${site.code})`]);
    }
    if (options === 'people') {
        return moduleOptions.people.map((person) => [
            String(person.id),
            `${person.first_name} ${person.last_name} (ID ${person.id})`,
        ]);
    }
    if (options === 'access_points') {
        return moduleOptions.access_points.map((point) => [
            String(point.id),
            `${point.name} (${point.code})`,
        ]);
    }
    if (options === 'zones') {
        if (moduleOptions.zones.length > 0) {
            return moduleOptions.zones.map((zone) => [
                String(zone.id),
                `${zone.site_name || moduleOptions.sites.find((site) => Number(site.id) === Number(zone.site_id))?.name || `Sede ${zone.site_id}`} · ${zone.name}${zone.code ? ` (${zone.code})` : ''}`,
            ]);
        }
        return [];
    }
    if (options === 'timezones') {
        return moduleOptions.timezones.map((timezone) => [timezone, timezone]);
    }
    if (options === 'profiles') {
        return profileOptions
            .filter((profile) => currentModule === 'people'
                ? profile.permissions?.length > 0 && profile.permissions.every((permission) => permission.name.startsWith('person.'))
                : !profile.permissions?.some((permission) => permission.name.startsWith('person.'))
                    && (currentUser?.is_admin || profile.slug !== 'administrador'))
            .map((profile) => [String(profile.id), profile.name]);
    }
    if (options === 'permissions') {
        return permissionOptions.map((permission) => [String(permission.id), `${permission.label} (${permission.module})`]);
    }

    return options;
}

function permissionModuleLabel(module) {
    const labels = {
        dashboard: 'Resumen',
        presence: 'Presencia',
        users: 'Usuarios',
        people: 'Personas',
        sites: 'Sedes',
        zones: 'Zonas',
        access_points: 'Puntos de acceso',
        authorizations: 'Autorizaciones',
        credentials: 'Credenciales',
        devices: 'Dispositivos',
        access_events: 'Eventos de acceso',
        event_types: 'Tipos de eventos',
        reports: 'Reportes',
        overtime_reviews: 'Validación de horas extra',
        profiles: 'Perfiles',
        database: 'Actualización de base de datos',
        person: 'Portal personal',
    };

    return labels[module] || module.replaceAll('_', ' ');
}

function buildPersonTypeGrid(field, record) {
    const selectedTypes = new Set(record ? (record.person_types || []) : field.options.map(([value]) => value));
    const grid = createElement('div', 'permission-grid');
    grid.dataset.personTypeField = field.name;
    const card = createElement('section', 'permission-module-card');
    const items = createElement('div', 'permission-module-items');

    field.options.forEach(([value, label]) => {
        const row = createElement('label', 'permission-toggle-row');
        const text = createElement('span', 'permission-toggle-copy');
        text.append(
            createElement('span', 'permission-toggle-label', label),
            createElement('span', 'permission-toggle-code', value),
        );
        const input = createElement('input', 'permission-switch');
        input.type = 'checkbox';
        input.name = field.name;
        input.value = value;
        input.checked = selectedTypes.has(value);
        input.setAttribute('role', 'switch');
        input.setAttribute('aria-label', label);
        const track = createElement('span', 'permission-switch-track');
        track.setAttribute('aria-hidden', 'true');
        row.append(text, input, track);
        items.append(row);
    });

    card.append(items);
    grid.append(card);

    return grid;
}

function buildPermissionGrid(field, record) {
    const selectedIds = new Set((record?.[field.name] || []).map(Number));
    const groups = new Map();
    permissionOptions.forEach((permission) => {
        if (!groups.has(permission.module)) {
            groups.set(permission.module, []);
        }
        groups.get(permission.module).push(permission);
    });

    const grid = createElement('div', 'permission-grid');
    grid.dataset.permissionField = field.name;
    [...groups.entries()].forEach(([module, permissions]) => {
        const card = createElement('section', 'permission-module-card');
        const heading = createElement('h3', 'permission-module-title', permissionModuleLabel(module));
        const items = createElement('div', 'permission-module-items');
        permissions.forEach((permission) => {
            const id = `permission-${permission.id}`;
            const row = createElement('label', 'permission-toggle-row');
            const text = createElement('span', 'permission-toggle-copy');
            text.append(
                createElement('span', 'permission-toggle-label', permission.label),
                createElement('span', 'permission-toggle-code', permission.name),
            );
            const input = createElement('input', 'permission-switch');
            input.type = 'checkbox';
            input.id = id;
            input.name = field.name;
            input.value = String(permission.id);
            input.checked = selectedIds.has(Number(permission.id));
            input.setAttribute('role', 'switch');
            input.setAttribute('aria-label', permission.label);
            const track = createElement('span', 'permission-switch-track');
            track.setAttribute('aria-hidden', 'true');
            row.append(text, input, track);
            items.append(row);
        });
        card.append(heading, items);
        grid.append(card);
    });

    return grid;
}

function localDateValue() {
    const date = new Date();
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return localDate.toISOString().slice(0, 10);
}

function weekdayLabel(day) {
    return ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][day - 1];
}

function buildWorkScheduleEditor(record) {
    const wrapper = createElement('div', 'work-schedule-editor');
    const schedules = record?.work_schedules || [];
    if (schedules.length > 0) {
        const history = createElement('div', 'work-schedule-history');
        history.append(createElement('h3', 'work-schedule-section-title', 'Historial de horarios'));
        schedules.forEach((schedule) => {
            const period = createElement('article', 'work-schedule-period');
            const dateRange = schedule.is_indefinite
                ? `Desde ${schedule.valid_from} · Indefinido`
                : `${schedule.valid_from} a ${schedule.valid_until}`;
            period.append(createElement('strong', '', dateRange));
            const days = schedule.days.map((day) =>
                day.all_day
                    ? `${weekdayLabel(day.day_of_week)} · Todo el día`
                    : `${weekdayLabel(day.day_of_week)} ${day.start_time}–${day.end_time}`
            ).join(' · ');
            period.append(createElement('span', '', days));
            history.append(period);
        });
        wrapper.append(history);
    }

    const addLabel = createElement('label', 'schedule-add-label');
    const addInput = createElement('input');
    addInput.type = 'checkbox';
    addInput.className = 'schedule-add-toggle';
    const addCopy = createElement('span', '', schedules.length ? 'Agregar nuevo periodo de horario' : 'Configurar horario laboral');
    addLabel.append(addInput, addCopy);
    wrapper.append(addLabel);

    const fields = createElement('div', 'schedule-fields');
    fields.hidden = true;
    const validity = createElement('div', 'schedule-validity');
    validity.append(
        createScheduleField('Fecha de inicio', 'date', 'valid-from', localDateValue()).wrapper,
    );

    const indefiniteLabel = createElement('label', 'schedule-indefinite-label');
    const indefinite = createElement('input');
    indefinite.type = 'checkbox';
    indefinite.className = 'schedule-indefinite-toggle';
    indefinite.checked = true;
    indefiniteLabel.append(indefinite, createElement('span', '', 'Vigencia indefinida'));
    validity.append(indefiniteLabel);

    const endDate = createScheduleField('Fecha de término', 'date', 'valid-until', '');
    endDate.wrapper.hidden = true;
    validity.append(endDate.wrapper);
    fields.append(validity);

    const dayGrid = createElement('div', 'schedule-day-grid');
    for (let day = 1; day <= 7; day += 1) {
        const dayLabel = createElement('div', 'schedule-day-row');
        const checkbox = createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'schedule-day-toggle';
        checkbox.dataset.day = String(day);
        checkbox.checked = day <= 5;
        checkbox.setAttribute('aria-label', `Incluir ${weekdayLabel(day)}`);
        dayLabel.append(checkbox, createElement('span', 'schedule-day-name', weekdayLabel(day)));
        const start = createElement('input', 'form-control schedule-time');
        start.type = 'time';
        start.classList.add('schedule-start-time');
        start.value = '09:00';
        start.setAttribute('aria-label', `Hora de entrada, ${weekdayLabel(day)}`);
        const end = createElement('input', 'form-control schedule-time');
        end.type = 'time';
        end.classList.add('schedule-end-time');
        end.value = '17:00';
        end.setAttribute('aria-label', `Hora de salida, ${weekdayLabel(day)}`);
        dayLabel.append(start, end);
        dayGrid.append(dayLabel);
    }
    fields.append(dayGrid);
    wrapper.append(fields);

    addInput.addEventListener('change', () => {
        fields.hidden = !addInput.checked;
    });
    indefinite.addEventListener('change', () => {
        endDate.wrapper.hidden = indefinite.checked;
        endDate.input.required = !indefinite.checked;
    });

    return wrapper;
}

function createScheduleField(labelText, type, className, value) {
    const wrapper = createElement('label', 'schedule-date-field');
    wrapper.append(createElement('span', 'field-label', labelText));
    const input = createElement('input', 'form-control');
    input.type = type;
    input.classList.add(`schedule-${className}`);
    input.value = value;
    input.required = className === 'valid-from';
    wrapper.append(input);

    return { wrapper, input };
}

function buildModuleFilters(config) {
    const container = document.querySelector('#module-filters');
    container.replaceChildren();

    const search = createElement('label', 'search-box');
    search.htmlFor = 'module-search';
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 20 20');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('aria-hidden', 'true');
    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('cx', '8.8');
    circle.setAttribute('cy', '8.8');
    circle.setAttribute('r', '5.8');
    circle.setAttribute('stroke', 'currentColor');
    circle.setAttribute('stroke-width', '1.5');
    const handle = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    handle.setAttribute('d', 'm13 13 4 4');
    handle.setAttribute('stroke', 'currentColor');
    handle.setAttribute('stroke-width', '1.5');
    handle.setAttribute('stroke-linecap', 'round');
    icon.append(circle, handle);
    const input = createElement('input');
    input.id = 'module-search';
    input.type = 'search';
    input.placeholder = `Buscar ${config.title.toLocaleLowerCase('es-CO')}...`;
    input.autocomplete = 'off';
    search.append(icon, input);
    container.append(search);

    config.filters.forEach((filter) => {
        const select = createElement('select', 'filter-select');
        select.dataset.filter = filter.name;
        select.setAttribute('aria-label', filter.label);
        const placeholder = new Option(filter.label, '');
        select.add(placeholder);

        filterOptions(filter.options).forEach(([value, label]) => {
            select.add(new Option(label, value));
        });

        container.append(select);
    });

    input.addEventListener('input', scheduleModuleReload);
    container.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', () => {
            modulePage = 1;
            loadModuleRecords();
        });
    });
}

function moduleFilters() {
    const filters = { page: modulePage, per_page: pageSize };
    const query = document.querySelector('#module-search')?.value.trim();

    if (query) {
        filters.q = query;
    }

    document.querySelectorAll('#module-filters select').forEach((select) => {
        if (select.value !== '') {
            filters[select.dataset.filter] = select.value;
        }
    });

    return filters;
}

function scheduleModuleReload() {
    window.clearTimeout(moduleTimer);
    moduleTimer = window.setTimeout(() => {
        modulePage = 1;
        loadModuleRecords();
    }, 250);
}

function setModuleTableMessage(message, className = 'table-loading') {
    const row = document.createElement('tr');
    const cell = createElement('td', className, message);
    cell.colSpan = moduleConfig[currentModule].columns.length;
    row.append(cell);
    document.querySelector('#module-table-rows').replaceChildren(row);
}

function statusBadge(value, activeLabel = 'Activa', inactiveLabel = 'Inactiva') {
    const badge = createElement('span', `record-status ${value ? 'record-status-active' : 'record-status-inactive'}`);
    const dot = createElement('span', 'status-dot');
    badge.append(dot, document.createTextNode(value ? activeLabel : inactiveLabel));

    return badge;
}

function identificationSummary(record) {
    if (!record.identification_number) {
        return '—';
    }

    const temporaryLabel = record.identification_is_temporary ? ' · TEMPORAL' : '';
    return `${record.identification_type || '—'} · ${record.identification_number}${temporaryLabel}`;
}

function labeledStatusBadge(label, active = false) {
    const badge = createElement('span', `record-status ${active ? 'record-status-active' : 'record-status-inactive'}`);
    badge.append(createElement('span', 'status-dot'), document.createTextNode(label));

    return badge;
}

function actionButton(label, action, className = '') {
    const button = createElement('button', `table-action ${className}`, label);
    button.type = 'button';
    button.dataset.action = action;

    return button;
}

function mapLocationCell(record) {
    const locationCell = document.createElement('td');
    if (record.latitude !== null && record.latitude !== undefined
        && record.longitude !== null && record.longitude !== undefined) {
        const mapLink = createElement('a', 'map-table-link', 'Ver mapa');
        mapLink.href = `https://www.google.com/maps?q=${encodeURIComponent(`${record.latitude},${record.longitude}`)}`;
        mapLink.target = '_blank';
        mapLink.rel = 'noopener noreferrer';
        locationCell.append(mapLink);
    } else {
        locationCell.textContent = 'Sin ubicación';
    }

    return locationCell;
}

function buildManagementRow(record) {
    const config = moduleConfig[currentModule];
    const row = document.createElement('tr');
    row.dataset.id = record.id;

    if (currentModule === 'profiles') {
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', 'code-cell', record.slug),
            createElement('td', '', String(record.permissions?.length || 0)),
            createElement('td', 'description-cell', record.description || '—'),
        );
    } else if (currentModule === 'users') {
        const userCell = document.createElement('td');
        const userWrap = createElement('div', 'person-cell');
        userWrap.append(createElement('span', 'person-avatar', initialsFor({ first_name: record.name, last_name: '' })));
        const copy = createElement('span', 'person-copy');
        copy.append(createElement('span', 'person-name', record.name), createElement('span', 'person-id', `ID ${record.id}`));
        userWrap.append(copy);
        userCell.append(userWrap);
        row.append(
            userCell,
            createElement('td', 'code-cell', identificationSummary(record)),
            createElement('td', '', record.email),
        );

        const roleCell = document.createElement('td');
        const names = record.is_admin ? ['Administrador'] : (record.profiles || []).map((profile) => profile.name);
        roleCell.append(createElement('span', `type-badge ${record.is_admin ? 'role-admin' : ''}`, names.join(', ') || 'Sin perfil'));
        row.append(roleCell);

        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.is_active, 'Activo', 'Inactivo'));
        row.append(stateCell);
    } else if (currentModule === 'sites') {
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', 'code-cell', record.code),
            createElement('td', 'description-cell', record.address || '—'),
            mapLocationCell(record),
            createElement('td', '', record.timezone),
            createElement('td', '', record.geofence_radius_m
                ? `${record.geofence_radius_m} m · ±${record.max_location_accuracy_m} m`
                : 'Sin configurar'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activa', 'Inactiva'));
        row.append(stateCell);
    } else if (currentModule === 'zones') {
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', 'code-cell', record.code),
            createElement('td', '', siteMap.get(Number(record.site_id))?.name || `Sede ${record.site_id}`),
            createElement('td', 'description-cell', record.description || '—'),
        );
        row.append(mapLocationCell(record));
        row.append(createElement('td', '', record.geofence_radius_m
            ? `${record.geofence_radius_m} m · ±${record.max_location_accuracy_m} m`
            : 'Sin configurar'));
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active'));
        row.append(stateCell);
    } else if (currentModule === 'event_types') {
        const behaviorLabels = {
            entry: 'Ingreso',
            exit: 'Salida',
            informational: 'Informativo · No cambia la presencia',
        };
        const personTypeLabels = Object.fromEntries(personTypeOptions);
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', 'code-cell', record.code),
            createElement('td', '', behaviorLabels[record.behavior] || '—'),
            createElement('td', '', (record.person_types || []).map((type) => personTypeLabels[type] || type).join(', ') || 'Ninguno'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activo', 'Inactivo'));
        row.append(stateCell);
    } else if (currentModule === 'authorizations') {
        row.append(
            createElement('td', 'person-name', record.person_name || `Persona ${record.person_id}`),
            createElement('td', '', `${record.site_name || `Sede ${record.site_id}`}${record.zone_name ? ` · ${record.zone_name}` : ''}`),
            createElement('td', 'description-cell', record.purpose),
            createElement('td', '', `${formatDate(record.valid_from)} – ${formatDate(record.valid_until)}`),
        );
        const stateCell = document.createElement('td');
        stateCell.append(labeledStatusBadge({
            pending: 'Pendiente',
            approved: 'Aprobada',
            rejected: 'Rechazada',
            revoked: 'Revocada',
        }[record.status] || record.status, record.status === 'approved'));
        row.append(stateCell);
    } else if (currentModule === 'access_points') {
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', 'code-cell', record.code),
            createElement('td', '', record.site_name || `Sede ${record.site_id}`),
            createElement('td', '', record.zone_name || 'Todas las zonas de la sede'),
            createElement('td', '', {
                entry: 'Solo entrada',
                exit: 'Solo salida',
                bidirectional: 'Bidireccional',
            }[record.direction] || record.direction),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activo', 'Inactivo'));
        row.append(stateCell);
    } else if (currentModule === 'devices') {
        row.append(
            createElement('td', 'person-name', record.name),
            createElement('td', '', record.access_point_name || `Punto ${record.access_point_id}`),
            createElement('td', 'code-cell', record.hardware_host || 'Sin configurar'),
            createElement('td', '', record.hardware_port || '—'),
            createElement('td', '', record.has_communication_key ? 'Configurada' : 'Sin configurar'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activo', 'Revocado'));
        row.append(stateCell);
    } else if (currentModule === 'credentials') {
        row.append(
            createElement('td', 'person-name', record.person_name || `Persona ${record.person_id}`),
            createElement('td', '', record.credential_type.toUpperCase()),
            createElement('td', '', record.label || '—'),
            createElement('td', '', record.valid_until ? `${formatDate(record.valid_from)} – ${formatDate(record.valid_until)}` : 'Sin vencimiento'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activa', 'Revocada'));
        row.append(stateCell);
    } else if (currentModule === 'mobile_devices') {
        row.append(
            createElement('td', 'person-name', record.person_name || `Persona ${record.person_id}`),
            createElement('td', '', record.device_name || record.device_uuid || 'Dispositivo'),
            createElement('td', '', record.platform || '—'),
            createElement('td', '', record.last_used_at ? formatDate(record.last_used_at) : 'Sin uso'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active', 'Activo', 'Revocado'));
        row.append(stateCell);
    } else if (currentModule === 'access_events') {
        row.append(
            createElement('td', '', formatDate(record.event_at)),
            createElement('td', 'person-name', record.person_name || 'Sin persona asociada'),
            createElement('td', '', record.site_name || `Sede ${record.site_id}`),
            createElement('td', '', record.event_type_name || record.event_type),
            createElement('td', '', formatScheduleDeviation(record.schedule_deviation_minutes, record.event_type_behavior)),
            createElement('td', '', record.worked_minutes === null || record.worked_minutes === undefined
                ? '—'
                : formatWorkedMinutes(Number(record.worked_minutes))),
            createElement('td', '', record.overtime_minutes === null || record.overtime_minutes === undefined
                ? '—'
                : formatWorkedMinutes(Number(record.overtime_minutes))),
            createElement('td', '', record.source_channel || '—'),
        );
        const decisionCell = document.createElement('td');
        decisionCell.append(statusBadge(record.decision === 'allowed', record.decision === 'allowed' ? 'Permitido' : 'Denegado'));
        row.append(decisionCell);
    } else {
        const personCell = document.createElement('td');
        const personWrap = createElement('div', 'person-cell');
        personWrap.append(createElement('span', 'person-avatar', initialsFor(record)));
        const copy = createElement('span', 'person-copy');
        copy.append(
            createElement('span', 'person-name', `${record.first_name} ${record.last_name}`.trim()),
            createElement('span', 'person-id', `ID ${record.id}`),
        );
        personWrap.append(copy);
        personCell.append(personWrap);
        row.append(personCell);

        const typeCell = document.createElement('td');
        typeCell.append(createElement('span', 'type-badge', formatPersonType(record.person_type)));
        row.append(
            createElement('td', 'code-cell', identificationSummary(record)),
            typeCell,
            createElement('td', 'schedule-summary-cell', record.person_type === 'employee'
                ? workScheduleSummary(record.work_schedules)
                : '—'),
            createElement('td', '', record.email || '—'),
            createElement('td', '', record.phone || '—'),
        );
        const stateCell = document.createElement('td');
        stateCell.append(statusBadge(record.status === 'active'));
        row.append(stateCell);
    }

    const actions = createElement('td', 'actions-cell');
    if (currentModule === 'access_events') {
        actions.append(actionButton('Detalle', 'detail'));
    } else if (currentModule === 'authorizations') {
        if (record.status === 'pending' && hasPermission('authorizations.update')) {
            actions.append(actionButton('Editar', 'edit'));
        }
        if (record.status === 'pending' && hasPermission('authorizations.approve')) {
            actions.append(actionButton('Aprobar', 'approve'));
        }
        if (record.status === 'pending' && hasPermission('authorizations.reject')) {
            actions.append(actionButton('Rechazar', 'reject', 'table-action-muted'));
        }
        if (record.status === 'approved' && hasPermission('authorizations.revoke')) {
            actions.append(actionButton('Revocar', 'revoke', 'table-action-muted'));
        }
    } else if (['devices', 'credentials', 'mobile_devices'].includes(currentModule)) {
        if (currentModule === 'devices') {
            if (record.status === 'active' && hasPermission('devices.configure')) actions.append(actionButton('Configurar', 'configure'));
            if (record.status === 'active' && hasPermission('devices.test')) actions.append(actionButton('Probar', 'test'));
            if (record.status === 'active' && hasPermission('devices.rotate_token')) actions.append(actionButton('Rotar token', 'rotate'));
            if (record.status === 'active' && hasPermission('devices.revoke')) actions.append(actionButton('Revocar', 'revoke', 'table-action-muted'));
        } else {
            if (record.status === 'active' && hasPermission(config.updatePermission)) {
                actions.append(actionButton('Revocar', 'revoke', 'table-action-muted'));
            }
        }
    } else {
        if (hasPermission(config.updatePermission)) {
            actions.append(actionButton('Editar', 'edit'));
        }
        if (currentModule !== 'profiles' && hasPermission(config.updatePermission) && !record.is_system) {
            const isActive = currentModule === 'users' ? record.is_active : record.status === 'active';
            actions.append(actionButton(isActive ? 'Desactivar' : 'Activar', isActive ? 'deactivate' : 'activate', 'table-action-muted'));
        }
    }
    row.append(actions);

    return row;
}

async function loadModuleRecords() {
    if (!Object.hasOwn(moduleConfig, currentModule)) {
        return;
    }

    const requestedModule = currentModule;
    const requestId = ++moduleRequest;
    setModuleTableMessage('Cargando registros...');
    document.querySelector('#module-message').hidden = true;

    try {
        const config = moduleConfig[requestedModule];
        const response = await api.get(config.endpoint, {
            headers: authHeaders(),
            params: moduleFilters(),
        });

        if (requestId !== moduleRequest || requestedModule !== currentModule) {
            return;
        }

        const data = response.data;
        currentModuleRows = data.data;
        modulePage = Number(data.meta.current_page) || 1;
        moduleLastPage = Number(data.meta.last_page) || 1;
        const tbody = document.querySelector('#module-table-rows');

        if (currentModuleRows.length === 0) {
            setModuleTableMessage(`No hay ${config.title.toLocaleLowerCase('es-CO')} que coincidan con la búsqueda.`, 'table-empty');
        } else {
            tbody.replaceChildren(...currentModuleRows.map(buildManagementRow));
        }
        applyResponsiveTableLabels(
            document.querySelector('.management-table'),
            config.columns,
        );

        const total = Number(data.meta.total) || 0;
        const first = total === 0 ? 0 : ((modulePage - 1) * Number(data.meta.per_page)) + 1;
        const last = Math.min(modulePage * Number(data.meta.per_page), total);
        document.querySelector('#module-summary').textContent = `Mostrando ${first}–${last} de ${total} registros`;
        document.querySelector('#module-previous-page').disabled = modulePage <= 1;
        document.querySelector('#module-next-page').disabled = modulePage >= moduleLastPage;
    } catch (error) {
        if (requestId !== moduleRequest || requestedModule !== currentModule) {
            return;
        }

        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const message = error.response?.data?.message || `No fue posible consultar ${moduleConfig[currentModule].title.toLocaleLowerCase('es-CO')}.`;
        setModuleTableMessage(message, 'table-error');
        document.querySelector('#module-summary').textContent = 'No se pudieron cargar los registros';
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
    }
}

async function loadSites() {
    const sites = [];
    let page = 1;
    let lastPage = 1;

    do {
        const response = await api.get('/api/v1/admin/sites', {
            headers: authHeaders(),
            params: { page, per_page: 100 },
        });
        sites.push(...response.data.data);
        lastPage = Number(response.data.meta.last_page) || 1;
        page += 1;
    } while (page <= lastPage);

    siteMap = new Map(sites.map((site) => [
        Number(site.id),
        { ...site, name: site.status === 'active' ? site.name : `${site.name} (inactiva)` },
    ]));
}

async function loadTimezoneOptions() {
    const response = await api.get('/api/v1/admin/timezones', { headers: authHeaders() });
    moduleOptions.timezones = response.data.data.timezones;
}

async function loadAttendanceSettings() {
    const response = await api.get('/api/v1/admin/attendance-settings', { headers: authHeaders() });
    const settings = response.data.data;
    moduleOptions.timezones = response.data.timezones;
    const timezoneSelect = document.querySelector('#setting-timezone');
    timezoneSelect.replaceChildren(...moduleOptions.timezones.map((timezone) => new Option(timezone, timezone)));
    document.querySelector('#setting-timezone-mode').value = settings.timezone_mode;
    timezoneSelect.value = settings.timezone;
    document.querySelector('#setting-early-entry-minutes').value = settings.early_entry_minutes;
    document.querySelector('#setting-stale-entry-hours').value = settings.stale_entry_hours;
    document.querySelector('#setting-late-exit-grace-minutes').value = settings.late_exit_grace_minutes;
    document.querySelector('#setting-max-daily-overtime-minutes').value = settings.max_daily_overtime_minutes;
    document.querySelector('#attendance-settings-message').hidden = true;
}

async function saveAttendanceSettings(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = document.querySelector('#save-attendance-settings');
    const message = document.querySelector('#attendance-settings-message');
    message.hidden = true;
    button.disabled = true;

    try {
        const response = await api.patch('/api/v1/admin/attendance-settings', {
            timezone_mode: form.elements.timezone_mode.value,
            timezone: form.elements.timezone.value,
            early_entry_minutes: Number(form.elements.early_entry_minutes.value),
            stale_entry_hours: Number(form.elements.stale_entry_hours.value),
            late_exit_grace_minutes: Number(form.elements.late_exit_grace_minutes.value),
            max_daily_overtime_minutes: Number(form.elements.max_daily_overtime_minutes.value),
        }, { headers: authHeaders() });
        document.documentElement.dataset.appTimezone = response.data.data.timezone;
        message.textContent = 'Parámetros guardados correctamente.';
        message.classList.remove('is-error');
        message.hidden = false;
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        const validation = error.response?.data?.errors;
        message.textContent = validation
            ? Object.values(validation).flat().join(' ')
            : error.response?.data?.message || 'No fue posible guardar los parámetros.';
        message.classList.add('is-error');
        message.hidden = false;
    } finally {
        button.disabled = false;
    }
}

async function loadModuleOptions(moduleName) {
    const response = await api.get(moduleConfig[moduleName].optionsEndpoint, {
        headers: authHeaders(),
    });
    const data = response.data.data;

    moduleOptions = {
        people: data.people || (Array.isArray(data) ? data : []),
        sites: data.sites || [],
        zones: data.zones || [],
        access_points: Array.isArray(data) ? data : (data.access_points || []),
    };
}

async function loadReportOptions() {
    const response = await api.get('/api/v1/admin/reports/options', { headers: authHeaders() });
    const data = response.data.data;
    const reportSelect = document.querySelector('#report-type');
    const siteSelect = document.querySelector('#report-site');
    const pointSelect = document.querySelector('#report-access-point');
    const eventTypeSelect = document.querySelector('#report-event-type');

    reportSelect.replaceChildren(...data.reports.map((report) => new Option(report.title, report.code)));
    siteSelect.replaceChildren(new Option('Todas las sedes', ''));
    siteSelect.append(...data.sites.map((site) => new Option(site.name, String(site.id))));
    reportAccessPoints = data.access_points;
    updateReportAccessPointOptions();
    eventTypeSelect.replaceChildren(new Option('Todos los eventos', ''));
    eventTypeSelect.append(...data.event_types.map((eventType) => new Option(eventType.name, eventType.code)));
    const today = localDateValue();
    const monthStart = `${today.slice(0, 7)}-01`;
    document.querySelector('#report-from').value ||= monthStart;
    document.querySelector('#report-to').value ||= today;
    updateReportDateRequirements();
}

function updateReportAccessPointOptions() {
    const siteId = document.querySelector('#report-site').value;
    const pointSelect = document.querySelector('#report-access-point');
    const previousPointId = pointSelect.value;
    const points = reportAccessPoints.filter((point) => !siteId || String(point.site_id) === siteId);
    pointSelect.replaceChildren(new Option('Todos los puntos', ''));
    pointSelect.append(...points.map((point) => new Option(
        `${point.site?.name || `Sede ${point.site_id}`} · ${point.name}`,
        String(point.id),
    )));
    pointSelect.value = points.some((point) => String(point.id) === previousPointId) ? previousPointId : '';
}

function updateReportDateRequirements() {
    const reportType = document.querySelector('#report-type').value;
    const isPresence = reportType === 'presence';
    const from = document.querySelector('#report-from');
    const to = document.querySelector('#report-to');
    from.required = !isPresence;
    to.required = !isPresence;
    from.disabled = isPresence;
    to.disabled = isPresence;
    document.querySelector('#report-access-point').closest('.form-field').hidden = reportType === 'visitors';
    document.querySelector('#report-decision').closest('.form-field').hidden =
        ['visitors', 'attendance', 'presence', 'denied'].includes(reportType);
    document.querySelector('#report-event-type').closest('.form-field').hidden =
        ['presence', 'overtime'].includes(reportType);
    document.querySelector('#report-overtime-status').closest('.form-field').hidden = reportType !== 'overtime';
}

function reportFilters() {
    const type = document.querySelector('#report-type').value;
    return {
        type,
        from: document.querySelector('#report-from').value,
        to: document.querySelector('#report-to').value,
        site_id: document.querySelector('#report-site').value,
        access_point_id: document.querySelector('#report-access-point').value,
        q: document.querySelector('#report-person').value.trim(),
        decision: document.querySelector('#report-decision').value,
        event_type: document.querySelector('#report-event-type').value,
        overtime_status: document.querySelector('#report-overtime-status').value,
    };
}

async function loadReportPage(page = 1) {
    const filters = reportFilters();
    const tbody = document.querySelector('#report-rows');
    const message = document.querySelector('#report-message');
    const generateButton = document.querySelector('#generate-report-button');
    document.querySelectorAll('[data-report-export]').forEach((button) => {
        button.disabled = true;
    });
    message.hidden = true;
    generateButton.disabled = true;
    document.querySelector('#report-results').hidden = true;
    tbody.replaceChildren();
    const loadingRow = document.createElement('tr');
    const loadingCell = createElement('td', 'table-loading', 'Generando informe...');
    loadingCell.colSpan = 1;
    loadingRow.append(loadingCell);
    tbody.append(loadingRow);

    try {
        const response = await api.get(`/api/v1/admin/reports/generate/${filters.type}`, {
            headers: authHeaders(),
            params: {
                from: filters.from || undefined,
                to: filters.to || undefined,
                site_id: filters.site_id || undefined,
                access_point_id: filters.access_point_id || undefined,
                q: filters.q || undefined,
                decision: filters.decision || undefined,
                event_type: filters.event_type || undefined,
                overtime_status: filters.type === 'overtime' ? filters.overtime_status : undefined,
                format: 'json',
                page,
                per_page: pageSize,
            },
        });
        const { data, meta, report } = response.data;
        reportTitle = report.title;
        reportPage = Number(meta.current_page) || 1;
        reportLastPage = Number(meta.last_page) || 1;
        const headRow = document.createElement('tr');
        report.columns.forEach((column) => headRow.append(createElement('th', '', column.toLocaleUpperCase('es-CO'))));
        document.querySelector('#report-head').replaceChildren(headRow);
        applyResponsiveTableLabels(document.querySelector('.report-results .management-table'), report.columns);

        if (data.length === 0) {
            const row = document.createElement('tr');
            const cell = createElement('td', 'table-empty', 'No hay registros para los filtros seleccionados.');
            cell.colSpan = report.columns.length;
            row.append(cell);
            tbody.replaceChildren(row);
        } else {
            tbody.replaceChildren(...data.map((item) => {
                const row = document.createElement('tr');
                report.columns.forEach((column) => row.append(createElement('td', '', String(item[column] ?? ''))));
                return row;
            }));
        }
        const total = Number(meta.total) || 0;
        const first = total === 0 ? 0 : ((reportPage - 1) * Number(meta.per_page)) + 1;
        const last = Math.min(reportPage * Number(meta.per_page), total);
        document.querySelector('#report-summary').textContent = `Mostrando ${first}–${last} de ${total} registros`;
        document.querySelector('#report-previous-page').disabled = reportPage <= 1;
        document.querySelector('#report-next-page').disabled = reportPage >= reportLastPage;
        document.querySelectorAll('[data-report-export]').forEach((button) => {
            button.disabled = false;
        });
        document.querySelector('#report-results-title').textContent = reportTitle;
        document.querySelector('#report-results').hidden = false;
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        const validation = error.response?.data?.errors;
        message.textContent = validation
            ? Object.values(validation).flat().join(' ')
            : error.response?.data?.message || 'No fue posible generar el informe.';
        message.classList.add('is-error');
        message.hidden = false;
        tbody.replaceChildren();
        document.querySelector('#report-summary').textContent = 'No se pudo generar el informe';
    } finally {
        generateButton.disabled = false;
    }
}

function invalidateReportResults() {
    document.querySelector('#report-results').hidden = true;
    document.querySelectorAll('[data-report-export]').forEach((button) => {
        button.disabled = true;
    });
}

async function exportReport(format) {
    const filters = reportFilters();
    const button = document.querySelector(`[data-report-export="${format}"]`);
    button.disabled = true;

    try {
        const response = await api.get(`/api/v1/admin/reports/generate/${filters.type}`, {
            headers: authHeaders(),
            params: {
                from: filters.from || undefined,
                to: filters.to || undefined,
                site_id: filters.site_id || undefined,
                access_point_id: filters.access_point_id || undefined,
                q: filters.q || undefined,
                decision: filters.decision || undefined,
                event_type: filters.event_type || undefined,
                overtime_status: filters.type === 'overtime' ? filters.overtime_status : undefined,
                format,
            },
            responseType: 'blob',
        });
        const extension = format === 'xlsx' ? 'xlsx' : format;
        const fileName = `controlaLite-${filters.type}-${localDateValue()}.${extension}`;
        const objectUrl = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = fileName;
        link.click();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        document.querySelector('#report-message').textContent =
            'No fue posible exportar el informe. Verifica los filtros e inténtalo de nuevo.';
        document.querySelector('#report-message').classList.add('is-error');
        document.querySelector('#report-message').hidden = false;
    } finally {
        button.disabled = false;
    }
}

async function loadOvertimeReviewOptions() {
    const response = await api.get('/api/v1/admin/overtime-reviews/options', { headers: authHeaders() });
    const siteSelect = document.querySelector('#overtime-site');
    siteSelect.replaceChildren(new Option('Todas las sedes', ''));
    siteSelect.append(...response.data.data.sites.map((site) => new Option(site.name, String(site.id))));
    const today = localDateValue();
    document.querySelector('#overtime-from').value ||= `${today.slice(0, 7)}-01`;
    document.querySelector('#overtime-to').value ||= today;
    void loadOvertimeReviews();
}

async function loadOvertimeReviews(page = 1) {
    const filters = {
        q: document.querySelector('#overtime-search').value.trim(),
        site_id: document.querySelector('#overtime-site').value,
        person_type: document.querySelector('#overtime-person-type').value,
        status: document.querySelector('#overtime-status').value,
        from: document.querySelector('#overtime-from').value,
        to: document.querySelector('#overtime-to').value,
    };
    const tbody = document.querySelector('#overtime-review-rows');
    const message = document.querySelector('#overtime-review-message');
    tbody.replaceChildren();
    tbody.append(createElement('tr', '', ''));
    const loadingCell = createElement('td', 'table-loading', 'Cargando horas extra...');
    loadingCell.colSpan = 10;
    tbody.querySelector('tr').append(loadingCell);
    message.hidden = true;

    try {
        const response = await api.get('/api/v1/admin/overtime-reviews', {
            headers: authHeaders(),
            params: {
                ...filters,
                q: filters.q || undefined,
                site_id: filters.site_id || undefined,
                person_type: filters.person_type || undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
                page,
                per_page: pageSize,
            },
        });
        const { data } = response.data;
        const meta = response.data.meta || response.data;
        overtimeReviewPage = Number(meta.current_page) || 1;
        overtimeReviewLastPage = Number(meta.last_page) || 1;
        renderOvertimeReviewRows(data);
        const total = Number(meta.total) || 0;
        const first = total === 0 ? 0 : ((overtimeReviewPage - 1) * Number(meta.per_page)) + 1;
        const last = Math.min(overtimeReviewPage * Number(meta.per_page), total);
        document.querySelector('#overtime-review-summary').textContent = `Mostrando ${first}–${last} de ${total} registros`;
        document.querySelector('#overtime-review-previous').disabled = overtimeReviewPage <= 1;
        document.querySelector('#overtime-review-next').disabled = overtimeReviewPage >= overtimeReviewLastPage;
        updateOvertimeSelectionUi(data);
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        message.textContent = error.response?.data?.message || 'No fue posible cargar los registros de horas extra.';
        message.classList.add('is-error');
        message.hidden = false;
        tbody.replaceChildren();
        document.querySelector('#overtime-review-summary').textContent = 'No se pudieron cargar los registros';
    }
}

function renderOvertimeReviewRows(events) {
    const tbody = document.querySelector('#overtime-review-rows');
    const selectAll = document.querySelector('#overtime-select-page');
    selectAll.checked = false;
    selectAll.indeterminate = false;

    if (events.length === 0) {
        const row = document.createElement('tr');
        const cell = createElement('td', 'table-empty', 'No hay registros con los filtros seleccionados.');
        cell.colSpan = 10;
        row.append(cell);
        tbody.replaceChildren(row);
        return;
    }

    tbody.replaceChildren(...events.map((event) => {
        const row = document.createElement('tr');
        const selectCell = document.createElement('td');
        if (event.status === 'pending') {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'overtime-row-select';
            checkbox.value = String(event.id);
            checkbox.checked = overtimeReviewSelections.has(event.id);
            checkbox.setAttribute('aria-label', `Seleccionar horas extra de ${event.person_name}`);
            selectCell.append(checkbox);
        } else {
            selectCell.textContent = '—';
        }

        const typeLabels = {
            employee: 'Empleado',
            visitor: 'Visitante',
            contractor: 'Contratista',
            other: 'Otro',
        };
        const statusLabels = {
            pending: 'Pendiente',
            approved: 'Aprobada',
            rejected: 'Rechazada',
        };
        const statusBadge = createElement('span', `overtime-status overtime-status-${event.status}`, statusLabels[event.status] || event.status);
        const hours = `${Math.floor(Number(event.overtime_minutes) / 60)} h ${String(Number(event.overtime_minutes) % 60).padStart(2, '0')} min`;
        const values = [
            formatDate(event.event_at),
            event.person_name,
            typeLabels[event.person_type] || event.person_type || '—',
            event.site_name || '—',
            `${formatTime(event.scheduled_start_at)} – ${formatTime(event.scheduled_end_at)}`,
            hours,
            event.novelty || 'Sin novedad registrada',
        ];
        row.append(selectCell);
        values.forEach((value, index) => {
            const cell = createElement('td', index === 5 ? 'overtime-duration' : '', String(value));
            if (index === 6) {
                cell.classList.add('overtime-novelty');
            }
            row.append(cell);
        });
        const statusCell = document.createElement('td');
        statusCell.append(statusBadge);
        row.append(statusCell);
        const reviewCell = createElement('td', 'overtime-review-note', event.status === 'pending'
            ? 'Requiere revisión'
            : `${event.reviewer_name || 'Usuario'} · ${event.reviewed_at ? formatDate(event.reviewed_at) : 'Fecha no disponible'}${event.review_note ? ` · ${event.review_note}` : ''}`);
        row.append(reviewCell);
        return row;
    }));
}

function updateOvertimeSelectionUi() {
    const selectedCount = overtimeReviewSelections.size;
    const canDecide = selectedCount > 0 && selectedCount <= 100;
    document.querySelector('#overtime-selected-count').textContent = selectedCount > 100
        ? `${selectedCount} seleccionados · máximo 100 por acción`
        : `${selectedCount} seleccionados`;
    document.querySelector('#overtime-approve-selected').disabled = !canDecide || !hasPermission('overtime_reviews.decide');
    document.querySelector('#overtime-reject-selected').disabled = !canDecide || !hasPermission('overtime_reviews.decide');
    const pageCheckboxes = [...document.querySelectorAll('.overtime-row-select')];
    const pageSelected = pageCheckboxes.filter((checkbox) => checkbox.checked).length;
    const selectAll = document.querySelector('#overtime-select-page');
    selectAll.checked = pageCheckboxes.length > 0 && pageSelected === pageCheckboxes.length;
    selectAll.indeterminate = pageSelected > 0 && pageSelected < pageCheckboxes.length;
}

function openOvertimeReviewDialog(decision) {
    if (overtimeReviewSelections.size === 0 || overtimeReviewSelections.size > 100) {
        return;
    }
    const dialog = document.querySelector('#overtime-review-dialog');
    const isRejection = decision === 'rejected';
    dialog.dataset.decision = decision;
    document.querySelector('#overtime-review-confirmation').textContent =
        `${isRejection ? 'Rechazar' : 'Aprobar'} ${overtimeReviewSelections.size} registro${overtimeReviewSelections.size === 1 ? '' : 's'} de horas extra.`;
    document.querySelector('#overtime-rejection-field').hidden = !isRejection;
    document.querySelector('#overtime-review-note').required = isRejection;
    document.querySelector('#overtime-review-note').value = '';
    document.querySelector('#overtime-review-dialog-message').hidden = true;
    document.querySelector('#confirm-overtime-review').textContent = isRejection ? 'Confirmar rechazo' : 'Confirmar aprobación';
    dialog.showModal();
}

async function submitOvertimeReview(event) {
    event.preventDefault();
    const dialog = document.querySelector('#overtime-review-dialog');
    const confirmButton = document.querySelector('#confirm-overtime-review');
    const message = document.querySelector('#overtime-review-dialog-message');
    const decision = dialog.dataset.decision;
    confirmButton.disabled = true;
    message.hidden = true;

    try {
        const response = await api.post('/api/v1/admin/overtime-reviews/bulk', {
            event_ids: [...overtimeReviewSelections],
            decision,
            note: document.querySelector('#overtime-review-note').value.trim() || undefined,
        }, { headers: authHeaders() });
        overtimeReviewSelections.clear();
        dialog.close();
        await loadOvertimeReviews(overtimeReviewPage);
        const pageMessage = document.querySelector('#overtime-review-message');
        if (pageMessage.hidden) {
            pageMessage.textContent = response.data.message;
            pageMessage.classList.remove('is-error');
            pageMessage.hidden = false;
        }
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }
        const validation = error.response?.data?.errors;
        message.textContent = validation
            ? Object.values(validation).flat().join(' ')
            : error.response?.data?.message || 'No fue posible guardar la decisión.';
        message.classList.add('is-error');
        message.hidden = false;
    } finally {
        confirmButton.disabled = false;
    }
}

async function loadRoleOptions() {
    const response = await api.get('/api/v1/admin/profiles', {
        headers: authHeaders(),
        params: { per_page: 100 },
    });
    profileOptions = response.data.data;
}

async function loadPermissionOptions() {
    const response = await api.get('/api/v1/admin/permissions', {
        headers: authHeaders(),
    });
    permissionOptions = response.data.data;
}

function buildRecordForm(config, record) {
    const fieldsContainer = document.querySelector('#record-fields');
    fieldsContainer.replaceChildren();

    config.fields.filter((field) => !(field.createOnly && record)
        && (!field.requiresPermission || hasPermission(field.requiresPermission))
        && (!field.requiresAdmin || currentUser?.is_admin)).forEach((field) => {
        const wrapper = createElement('div', `form-field ${['textarea', 'permission-grid', 'person-type-grid', 'map-location'].includes(field.type) ? 'form-field-wide' : ''}`);
        const label = createElement('label', 'field-label', field.label);
        const id = `record-${field.name}`;
        label.htmlFor = id;
        let control;

        if (field.type === 'permission-grid') {
            control = buildPermissionGrid(field, record);
        } else if (field.type === 'person-type-grid') {
            control = buildPersonTypeGrid(field, record);
        } else if (field.type === 'map-location') {
            control = buildMapLocationEditor(record);
        } else if (field.type === 'work-schedule') {
            control = buildWorkScheduleEditor(record);
        } else if (field.type === 'select' || field.type === 'multiselect') {
            control = createElement('select', 'form-control');
            control.multiple = field.type === 'multiselect';
            if (!control.multiple) {
                control.add(new Option('Selecciona una opción', ''));
            }
            const options = filterOptions(field.options);
            if (record && record[field.name] !== null && record[field.name] !== undefined
                && !options.some(([value]) => Number(value) === Number(record[field.name]))) {
                const fallbackLabels = {
                    person_id: record.person_name || `Persona ${record.person_id}`,
                    site_id: record.site_name || `Sede ${record.site_id}`,
                    zone_id: record.zone_name || `Zona ${record.zone_id}`,
                    access_point_id: record.access_point_name || `Punto ${record.access_point_id}`,
                };
                if (fallbackLabels[field.name]) {
                    options.push([String(record[field.name]), `${fallbackLabels[field.name]} (actual)`]);
                }
            }
            options.forEach(([value, text]) => {
                control.add(new Option(text, value));
            });
        } else if (field.type === 'textarea') {
            control = createElement('textarea', 'form-control');
            control.rows = 3;
        } else {
            control = createElement('input', 'form-control');
            control.type = field.type;
            if (field.type === 'password') {
                control.autocomplete = 'new-password';
            } else if (field.type === 'email') {
                control.autocomplete = 'email';
            }
        }

        if (!['permission-grid', 'person-type-grid', 'work-schedule', 'map-location'].includes(field.type)) {
            control.id = id;
            control.name = field.name;
            control.required = Boolean(field.required && !(field.name === 'password' && record));
            control.disabled = Boolean(field.readOnlyForSystem && record?.is_system);
            if (field.maxLength) {
                control.maxLength = field.maxLength;
            }
            if (field.min !== undefined) {
                control.min = field.min;
            }
            if (field.max !== undefined) {
                control.max = field.max;
            }
        } else if (field.type === 'permission-grid') {
            control.id = id;
            control.setAttribute('role', 'group');
            control.setAttribute('aria-labelledby', id);
        } else if (field.type === 'person-type-grid') {
            control.id = id;
            control.setAttribute('role', 'group');
            control.setAttribute('aria-labelledby', id);
        } else if (field.type === 'work-schedule') {
            control.id = id;
            control.dataset.workSchedule = 'true';
            wrapper.hidden = record ? record.person_type !== 'employee' : true;
        }

        if (field.name === 'password' && record) {
            control.placeholder = 'Sin cambios';
        } else if (record && record[field.name] !== null && record[field.name] !== undefined) {
            const value = ['is_admin', 'is_active'].includes(field.name)
                ? (record[field.name] ? '1' : '0')
                : field.type === 'datetime-local'
                    ? localDateTimeValue(record[field.name])
                    : String(record[field.name]);
            control.value = value;
        } else if (!record && field.name === 'is_active') {
            control.value = '1';
        } else if (!record && field.name === 'status') {
            control.value = 'active';
        } else if (!record && field.name === 'is_admin') {
            control.value = '0';
        } else if (!record && field.name === 'timezone') {
            control.value = 'America/Bogota';
        } else if (!record && field.name === 'max_location_accuracy_m') {
            control.value = '50';
        }
        if (record && field.name === 'timezone'
            && ![...control.options].some((option) => option.value === String(record.timezone))) {
            control.add(new Option(`${record.timezone} (actual; zona no disponible en la lista)`, String(record.timezone)));
        }
        if (control.multiple && record) {
            const selectedValues = record[field.name] || [];
            [...control.options].forEach((option) => {
                option.selected = selectedValues.map(String).includes(option.value);
            });
        }
        if (field.type === 'work-schedule') {
            control.querySelectorAll('.schedule-day-toggle').forEach((checkbox) => {
                const row = checkbox.closest('.schedule-day-row');
                const times = row.querySelectorAll('.schedule-time');
                times.forEach((input) => {
                    input.disabled = !checkbox.checked;
                    input.required = checkbox.checked;
                });
                checkbox.addEventListener('change', () => {
                    times.forEach((input) => {
                        input.disabled = !checkbox.checked;
                        input.required = checkbox.checked;
                    });
                });
            });
        }

        if (field.type === 'map-location') {
            wrapper.append(control);
        } else {
            wrapper.append(label, control);
        }
        const fieldHelp = record?.identification_is_temporary && field.name === 'identification_number'
            ? 'Este valor es temporal y no es un documento real. Sustitúyelo por el número correcto para completar el registro.'
            : field.help;
        if (fieldHelp && field.type !== 'map-location') {
            wrapper.append(createElement('span', 'field-help', fieldHelp));
        }
        fieldsContainer.append(wrapper);
        if (field.name === 'person_type') {
            control.addEventListener('change', () => {
                const scheduleField = fieldsContainer.querySelector('[data-work-schedule]');
                if (scheduleField) {
                    scheduleField.closest('.form-field').hidden = control.value !== 'employee';
                }
            });
        }
    });

    const siteSelect = fieldsContainer.querySelector('[name="site_id"]');
    const zoneSelect = fieldsContainer.querySelector('[name="zone_id"]');
    if (siteSelect && zoneSelect && moduleOptions.zones.length > 0) {
        const refreshZones = () => {
            const previousZone = zoneSelect.value;
            const availableZones = moduleOptions.zones.filter((zone) => String(zone.site_id) === siteSelect.value);
            zoneSelect.replaceChildren(new Option('Sin zona específica', ''));
            availableZones.forEach((zone) => {
                zoneSelect.add(new Option(`${zone.name}${zone.code ? ` (${zone.code})` : ''}`, String(zone.id)));
            });
            if (record?.zone_id && String(record.site_id) === siteSelect.value
                && !availableZones.some((zone) => String(zone.id) === String(record.zone_id))) {
                zoneSelect.add(new Option(`${record.zone_name || `Zona ${record.zone_id}`} (actual)`, String(record.zone_id)));
            }
            if (availableZones.some((zone) => String(zone.id) === previousZone)) {
                zoneSelect.value = previousZone;
            } else if (record?.zone_id && String(record.site_id) === siteSelect.value) {
                zoneSelect.value = String(record.zone_id);
            }
        };
        siteSelect.addEventListener('change', refreshZones);
        refreshZones();
        if (record?.zone_id) {
            zoneSelect.value = String(record.zone_id);
        }
        if (currentModule === 'authorizations') {
            zoneSelect.addEventListener('change', () => {
                const zone = moduleOptions.zones.find((item) => String(item.id) === zoneSelect.value);
                if (zone && siteSelect.value !== String(zone.site_id)) {
                    siteSelect.value = String(zone.site_id);
                    siteSelect.dispatchEvent(new Event('change'));
                    zoneSelect.value = String(zone.id);
                }
            });
        }
    }

    const credentialType = fieldsContainer.querySelector('[name="credential_type"]');
    const credentialToken = fieldsContainer.querySelector('[name="token"]');
    if (credentialType && credentialToken) {
        const tokenWrapper = credentialToken.closest('.form-field');
        const updateCredentialType = () => {
            const isRfid = credentialType.value === 'rfid';
            tokenWrapper.hidden = !isRfid;
            credentialToken.required = isRfid;
            if (!isRfid) credentialToken.value = '';
        };
        credentialType.addEventListener('change', updateCredentialType);
        updateCredentialType();
    }
}

async function openRecordDialog(record = null) {
    const config = moduleConfig[currentModule];
    try {
        if (['users', 'people'].includes(currentModule) && hasPermission(`${currentModule}.manage_profiles`)) {
            await loadRoleOptions();
        }
        if (currentModule === 'profiles' && hasPermission('profiles.manage_permissions')) {
            await loadPermissionOptions();
        }
    } catch (error) {
        const message = error.response?.data?.message || 'No se pudieron cargar perfiles y permisos.';
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
        return;
    }
    document.querySelector('#record-dialog-title').textContent = record
        ? `Editar ${config.singular}`
        : config.createLabel;
    document.querySelector('#record-dialog').classList.toggle('permission-dialog', currentModule === 'profiles');
    document.querySelector('#record-message').hidden = true;
    document.querySelector('#save-record-button').disabled = false;
    document.querySelector('#save-record-button').hidden = Boolean(record && currentModule === 'authorizations' && record.status !== 'pending');
    buildRecordForm(config, record);
    document.querySelector('#record-form').dataset.recordId = record?.id || '';
    document.querySelector('#record-dialog').showModal();
    document.querySelector('#record-fields input, #record-fields select, #record-fields textarea')?.focus();
}

function showOneTimeSecret(secret, notice) {
    if (!secret) return;
    document.querySelector('#one-time-secret-value').value = secret;
    document.querySelector('#one-time-secret-notice').textContent = notice || 'Este valor solo se mostrará ahora. Guárdalo de forma segura.';
    document.querySelector('#one-time-secret-dialog').showModal();
}

function clearOneTimeSecret() {
    document.querySelector('#one-time-secret-value').value = '';
    document.querySelector('#one-time-secret-notice').textContent = '';
}

function appendDetailField(container, label, value) {
    const item = createElement('div', 'admin-detail-item');
    item.append(createElement('span', 'admin-detail-label', label), createElement('span', 'admin-detail-value', String(value ?? '—')));
    container.append(item);
}

function formatEventLocationDistance(record) {
    if (record.latitude === null || record.latitude === undefined
        || record.longitude === null || record.longitude === undefined) {
        return 'No se registró ubicación; distancia no disponible.';
    }
    if (record.distance_from_reference_m === null || record.distance_from_reference_m === undefined) {
        return `No hay coordenadas configuradas para la zona o sede de referencia.`;
    }

    const distance = Number(record.distance_from_reference_m);
    const reference = record.distance_reference_name || 'la zona o sede';
    const radius = record.distance_reference_radius_m;
    const distanceText = `${distance} m respecto a ${reference}`;
    if (radius === null || radius === undefined) {
        return `${distanceText}; radio de geocerca sin configurar.`;
    }

    const insideGeofence = distance <= Number(radius);
    return `${distanceText} (${insideGeofence ? 'dentro' : 'fuera'} del radio de ${radius} m).`;
}

async function showAccessEventDetails(record) {
    const dialog = document.querySelector('#admin-detail-dialog');
    const fields = document.querySelector('#admin-detail-fields');
    const message = document.querySelector('#admin-detail-message');
    const media = document.querySelector('#admin-detail-media');
    const map = document.querySelector('#admin-event-map');
    const mapCanvas = document.querySelector('#admin-event-map-canvas');
    const mapCoordinates = document.querySelector('#admin-event-map-coordinates');
    const mapLink = document.querySelector('#admin-event-map-link');
    const photo = document.querySelector('#admin-detail-photo');
    const image = document.querySelector('#admin-event-selfie');
    const photoCaption = document.querySelector('#admin-event-selfie-caption');
    fields.replaceChildren();
    message.hidden = true;
    media.hidden = true;
    map.hidden = true;
    mapCanvas.replaceChildren();
    photo.hidden = false;
    media.hidden = false;
    photoCaption.textContent = 'Sin selfie registrada';
    image.src = '/images/user-placeholder.svg';
    image.alt = 'Icono de usuario';
    image.classList.add('is-placeholder');
    image.hidden = false;
    if (adminEventSelfieUrl) {
        URL.revokeObjectURL(adminEventSelfieUrl);
        adminEventSelfieUrl = null;
    }

    [
        ['UUID', record.event_uuid],
        ['Fecha y hora', formatDate(record.event_at)],
        ['Persona', record.person_name || 'Sin persona asociada'],
        ['Sede', record.site_name],
        ['Punto de acceso', record.access_point_name],
        ['Evento', record.event_type_name || record.event_type],
        ['Canal', record.source_channel],
        ['Decisión', record.decision],
        ['Motivo', record.reason_code],
        ['Coordenadas', record.latitude !== null && record.longitude !== null ? `${record.latitude}, ${record.longitude}` : 'Sin ubicación'],
        ['Distancia a zona/sede', formatEventLocationDistance(record)],
        ['Precisión', record.location_accuracy_m === null ? '—' : `${record.location_accuracy_m} m`],
        ['Desviación del horario', formatScheduleDeviation(record.schedule_deviation_minutes, record.event_type_behavior)],
        ['Tiempo trabajado', record.worked_minutes === null ? '—' : formatWorkedMinutes(Number(record.worked_minutes))],
        ['Tiempo extra', record.overtime_minutes === null ? '—' : formatWorkedMinutes(Number(record.overtime_minutes))],
        ['Novedad', record.novelty],
    ].forEach(([label, value]) => appendDetailField(fields, label, value));

    const latitude = Number(record.latitude);
    const longitude = Number(record.longitude);
    const hasLocation = record.latitude !== null && record.latitude !== undefined && record.latitude !== ''
        && record.longitude !== null && record.longitude !== undefined && record.longitude !== ''
        && Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
        && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
    if (hasLocation) {
        const coordinates = `${latitude},${longitude}`;
        const iframe = createElement('iframe');
        iframe.title = 'Google Maps con la ubicación registrada';
        iframe.src = `https://www.google.com/maps?q=${encodeURIComponent(coordinates)}&z=17&output=embed`;
        iframe.loading = 'eager';
        iframe.allowFullscreen = true;
        mapCanvas.append(iframe);
        mapCoordinates.textContent = `${latitude}, ${longitude}`;
        mapLink.href = `https://www.google.com/maps?q=${encodeURIComponent(coordinates)}`;
        map.hidden = false;
        media.hidden = false;
    }

    document.querySelector('#admin-detail-title').textContent = `Evento ${record.event_uuid || `#${record.id}`}`;
    dialog.showModal();
    if (record.has_selfie && !hasPermission('access_events.read_selfie')) {
        photoCaption.textContent = 'No tienes permiso para ver esta selfie';
    } else if (record.has_selfie) {
        photoCaption.textContent = 'Cargando selfie…';
        try {
            const response = await api.get(`/api/v1/admin/access-events/${record.id}/selfie`, {
                headers: authHeaders(),
                responseType: 'blob',
            });
            adminEventSelfieUrl = URL.createObjectURL(response.data);
            image.src = adminEventSelfieUrl;
            image.alt = 'Selfie de la marcación';
            image.classList.remove('is-placeholder');
            photoCaption.textContent = 'Selfie de la marcación';
            image.hidden = false;
        } catch (error) {
            if (error.response?.status === 401) {
                sessionStorage.removeItem(tokenKey);
                showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
                return;
            }
            photoCaption.textContent = 'Selfie no disponible';
            message.textContent = error.response?.data?.message || 'No se pudo cargar la selfie del evento.';
            message.hidden = false;
        }
    }
}

function recordFormPayload(form, recordId) {
    const payload = Object.fromEntries(new FormData(form).entries());
    const data = new FormData(form);

    if (payload.password === '') {
        delete payload.password;
    }
    ['profile_ids', 'permission_ids'].forEach((field) => {
        if (form.elements.namedItem(field)) {
            const values = data.getAll(field).map(Number);
            if (field !== 'profile_ids' || values.length > 0 || recordId) {
                payload[field] = values;
            }
        }
    });
    if (currentModule === 'event_types') {
        payload.person_types = data.getAll('person_types');
    }
    const scheduleEditor = form.querySelector('[data-work-schedule]');
    if (scheduleEditor && form.elements.namedItem('person_type')?.value === 'employee') {
        const addSchedule = scheduleEditor.querySelector('.schedule-add-toggle');
        if (addSchedule.checked) {
            payload.work_schedule = {
                valid_from: scheduleEditor.querySelector('.schedule-valid-from').value,
                is_indefinite: scheduleEditor.querySelector('.schedule-indefinite-toggle').checked,
                valid_until: scheduleEditor.querySelector('.schedule-indefinite-toggle').checked
                    ? null
                    : scheduleEditor.querySelector('.schedule-valid-until').value,
                days: [...scheduleEditor.querySelectorAll('.schedule-day-toggle:checked')].map((checkbox) => {
                    const row = checkbox.closest('.schedule-day-row');
                    return {
                        day_of_week: Number(checkbox.dataset.day),
                        start_time: row.querySelector('.schedule-start-time').value,
                        end_time: row.querySelector('.schedule-end-time').value,
                    };
                }),
            };
        }
    }
    const mapLocationEditor = form.querySelector('[data-map-location]');
    if (mapLocationEditor && ['zones', 'sites'].includes(currentModule)) {
        payload.latitude = mapLocationEditor.querySelector('.map-latitude').value || null;
        payload.longitude = mapLocationEditor.querySelector('.map-longitude').value || null;
    }
    if (Object.hasOwn(payload, 'site_id')) {
        payload.site_id = Number(payload.site_id);
    }
    ['person_id', 'zone_id'].forEach((field) => {
        if (Object.hasOwn(payload, field)) {
            payload[field] = Number(payload[field]);
        }
    });
    ['access_point_id', 'hardware_port', 'communication_key', 'expires_in_minutes'].forEach((field) => {
        if (Object.hasOwn(payload, field)) {
            payload[field] = payload[field] === '' ? null : Number(payload[field]);
        }
    });
    if (['sites', 'zones'].includes(currentModule)) {
        ['geofence_radius_m', 'max_location_accuracy_m'].forEach((field) => {
            if (Object.hasOwn(payload, field)) {
                payload[field] = payload[field] === '' ? null : Number(payload[field]);
            }
        });
    }
    ['is_admin', 'is_active'].forEach((field) => {
        if (Object.hasOwn(payload, field)) {
            payload[field] = payload[field] === '1';
        }
    });

    if (currentModule === 'devices' && payload.communication_key === '') {
        delete payload.communication_key;
    }
    if (currentModule === 'devices' && payload.communication_key === null) {
        delete payload.communication_key;
    }
    if (currentModule === 'mobile_devices' && payload.expires_in_minutes === null) {
        delete payload.expires_in_minutes;
    }
    if (currentModule === 'credentials' || currentModule === 'authorizations') {
        if (currentModule === 'credentials' && payload.credential_type === 'qr') {
            delete payload.token;
        }
        if (payload.valid_from) payload.valid_from = new Date(payload.valid_from).toISOString();
        if (payload.valid_until) payload.valid_until = new Date(payload.valid_until).toISOString();
    }

    if (recordId && ['users', 'people'].includes(currentModule) && !payload.password) {
        delete payload.password;
    }

    return payload;
}

async function saveModuleRecord(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const recordId = form.dataset.recordId;
    const config = moduleConfig[currentModule];
    const saveButton = document.querySelector('#save-record-button');
    const message = document.querySelector('#record-message');
    saveButton.disabled = true;
    saveButton.textContent = 'Guardando...';
    message.hidden = true;

    try {
        const payload = recordFormPayload(form, recordId);
        const url = recordId ? `${config.endpoint}/${recordId}` : config.endpoint;
        let response;
        if (recordId) {
            const updateUrl = currentModule === 'devices' ? `${url}/connection` : url;
            response = await api.patch(updateUrl, payload, { headers: authHeaders() });
        } else if (config.createMode === 'mobile-enrollment') {
            const { person_id: personId, ...enrollmentPayload } = payload;
            response = await api.post(`/api/v1/admin/people/${personId}/mobile-enrollment-code`, enrollmentPayload, {
                headers: authHeaders(),
            });
        } else {
            response = await api.post(url, payload, { headers: authHeaders() });
        }

        document.querySelector('#record-dialog').close();
        const secret = response.data.issued_token || response.data.data?.enrollment_code;
        if (secret) {
            showOneTimeSecret(secret, response.data.token_notice || response.data.notice);
        }
        await loadModuleRecords();
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const validation = error.response?.data?.errors;
        message.textContent = validation
            ? Object.values(validation).flat().join(' ')
            : error.response?.data?.message || 'No se pudo guardar el registro.';
        message.hidden = false;
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar';
    }
}

async function updateRecordStatus(record, active) {
    const isUser = currentModule === 'users';
    const field = isUser ? 'is_active' : 'status';
    const value = isUser ? active : active ? 'active' : 'inactive';
    const labels = {
        sites: 'sede',
        people: 'persona',
        zones: 'zona',
        event_types: 'tipo de evento',
        users: 'usuario',
    };
    const label = labels[currentModule] || 'registro';
    const action = active ? 'activar' : 'desactivar';

    if (!window.confirm(`¿Deseas ${action} este ${label}?`)) {
        return;
    }

    try {
        await api.patch(`${moduleConfig[currentModule].endpoint}/${record.id}`, {
            [field]: value,
        }, { headers: authHeaders() });
        await loadModuleRecords();
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const message = error.response?.data?.message || `No se pudo ${action} el ${label}.`;
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
    }
}

async function executeAdminAction(record, action) {
    const id = record.id;
    const endpoint = moduleConfig[currentModule].endpoint;
    let url;
    let payload = {};

    if (currentModule === 'authorizations') {
        if (action === 'approve' && hasPermission('authorizations.approve')) {
            url = `${endpoint}/${id}/approve`;
        } else if (action === 'reject' && hasPermission('authorizations.reject')) {
            payload.reason = window.prompt('Indica el motivo del rechazo (mínimo 5 caracteres):') || '';
            if (payload.reason.length < 5) return;
            url = `${endpoint}/${id}/reject`;
        } else if (action === 'revoke' && hasPermission('authorizations.revoke')) {
            payload.reason = window.prompt('Indica el motivo de la revocación (mínimo 5 caracteres):') || '';
            if (payload.reason.length < 5) return;
            url = `${endpoint}/${id}/revoke`;
        }
    } else if (currentModule === 'devices') {
        if (action === 'test' && hasPermission('devices.test')) {
            const response = await api.post(`${endpoint}/${id}/test-connection`, {}, { headers: authHeaders() });
            window.alert(response.data.message || 'Conexión probada correctamente.');
            return;
        }
        if (action === 'rotate' && hasPermission('devices.rotate_token')) {
            const response = await api.post(`${endpoint}/${id}/rotate-token`, {}, { headers: authHeaders() });
            showOneTimeSecret(response.data.issued_token, response.data.token_notice);
            await loadModuleRecords();
            return;
        }
        if (action === 'revoke' && hasPermission('devices.revoke')) {
            if (!window.confirm('¿Revocar este dispositivo? Su token dejará de ser válido.')) return;
            url = `${endpoint}/${id}/revoke`;
        }
    } else if (currentModule === 'credentials' && action === 'revoke' && hasPermission('credentials.revoke')) {
        if (!window.confirm('¿Revocar esta credencial? No podrá volver a utilizarse.')) return;
        url = `${endpoint}/${id}/revoke`;
    } else if (currentModule === 'mobile_devices' && action === 'revoke' && hasPermission('people.manage_mobile')) {
        if (!window.confirm('¿Revocar este dispositivo móvil?')) return;
        url = `${endpoint}/${id}/revoke`;
    }

    if (!url) return;
    await api.post(url, payload, { headers: authHeaders() });
    await loadModuleRecords();
}

async function handleManagementAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
        return;
    }

    const row = button.closest('tr');
    const record = currentModuleRows.find((item) => String(item.id) === row?.dataset.id);
    if (!record) {
        return;
    }

    if (button.dataset.action === 'edit') {
        await openRecordDialog(record);
        return;
    }

    if (button.dataset.action === 'configure') {
        await openRecordDialog(record);
        return;
    }

    if (button.dataset.action === 'detail' && currentModule === 'access_events') {
        await showAccessEventDetails(record);
        return;
    }

    try {
        if (['approve', 'reject', 'revoke', 'test', 'rotate'].includes(button.dataset.action)
            && ['authorizations', 'devices', 'credentials', 'mobile_devices'].includes(currentModule)) {
            await executeAdminAction(record, button.dataset.action);
            return;
        }
        await updateRecordStatus(record, button.dataset.action === 'activate');
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const message = error.response?.data?.message || 'No fue posible completar la acción.';
        document.querySelector('#module-message').textContent = message;
        document.querySelector('#module-message').hidden = false;
    }
}

function renderPresence(rows) {
    rowsContainer.replaceChildren();

    if (rows.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        row.className = 'table-message-row';
        cell.colSpan = 5;
        cell.className = 'table-empty';
        cell.textContent = currentRows.length === 0
            ? 'No hay personas presentes en este momento.'
            : 'No hay coincidencias en esta página.';
        row.append(cell);
        rowsContainer.append(row);
        return;
    }

    rows.forEach((person) => {
        const row = document.createElement('tr');
        const personCell = document.createElement('td');
        const personWrap = document.createElement('div');
        const avatar = document.createElement('span');
        const personCopy = document.createElement('span');
        const name = document.createElement('span');
        const personId = document.createElement('span');
        const typeCell = document.createElement('td');
        const typeBadge = document.createElement('span');
        const siteCell = document.createElement('td');
        const entryCell = document.createElement('td');
        const statusCell = document.createElement('td');
        const statusBadge = document.createElement('span');
        const statusDot = document.createElement('span');

        personWrap.className = 'person-cell';
        avatar.className = 'person-avatar';
        avatar.textContent = initialsFor(person);
        personCopy.className = 'person-copy';
        name.className = 'person-name';
        name.textContent = `${person.first_name || ''} ${person.last_name || ''}`.trim() || 'Persona sin nombre';
        personId.className = 'person-id';
        personId.textContent = `ID ${person.person_id}`;
        personCopy.append(name, personId);
        personWrap.append(avatar, personCopy);
        personCell.append(personWrap);

        typeBadge.className = 'type-badge';
        typeBadge.textContent = formatPersonType(person.person_type);
        typeCell.append(typeBadge);

        siteCell.textContent = `Sede ${person.site_id}`;
        entryCell.textContent = formatDate(person.last_entry_at);
        statusBadge.className = 'presence-badge';
        statusDot.className = 'status-dot';
        statusBadge.append(statusDot, document.createTextNode('Presente'));
        statusCell.append(statusBadge);

        row.append(personCell, typeCell, siteCell, entryCell, statusCell);
        rowsContainer.append(row);
    });
    applyResponsiveTableLabels(
        document.querySelector('.presence-table:not(.management-table)'),
        ['Persona', 'Tipo', 'Sede', 'Último ingreso', 'Estado'],
    );
}

function renderPagination(meta) {
    const previousButton = document.querySelector('#previous-page');
    const nextButton = document.querySelector('#next-page');
    const total = Number(meta.total || 0);
    const perPage = Number(meta.per_page || pageSize);
    const firstItem = total === 0 ? 0 : ((Number(meta.current_page) - 1) * perPage) + 1;
    const lastItem = Math.min(Number(meta.current_page) * perPage, total);

    currentPage = Number(meta.current_page) || 1;
    lastPage = Number(meta.last_page) || 1;
    previousButton.disabled = currentPage <= 1;
    nextButton.disabled = currentPage >= lastPage;
    document.querySelector('#presence-count').textContent = String(total);
    document.querySelector('#presence-summary').textContent =
        `Mostrando ${firstItem}–${lastItem} de ${total} personas`;
    document.querySelector('#last-updated').textContent =
        `Actualizado ${new Intl.DateTimeFormat('es-CO', { timeStyle: 'short' }).format(new Date())}`;
}

function filterCurrentPage() {
    const query = searchInput.value.trim().toLocaleLowerCase('es-CO');
    const filtered = currentRows.filter((person) => (
        `${person.first_name} ${person.last_name} ${person.person_type} ${person.site_id}`
            .toLocaleLowerCase('es-CO')
            .includes(query)
    ));

    renderPresence(filtered);
}

async function loadPresence() {
    setLoading('Cargando información...');
    dashboardMessage.hidden = true;

    try {
        const response = await api.get('/api/v1/admin/reports/current-presence', {
            headers: authHeaders(),
            params: { page: currentPage, per_page: pageSize },
        });

        currentRows = response.data.data;
        renderPagination(response.data.meta);
        filterCurrentPage();
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            showLogin('Tu sesión terminó. Vuelve a iniciar sesión.');
            return;
        }

        const message = error.response?.data?.message || 'No fue posible consultar la presencia actual.';
        setLoading(message);
        rowsContainer.querySelector('td').className = 'table-error';
        dashboardMessage.textContent = message;
        dashboardMessage.hidden = false;
    }
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginMessage.hidden = true;
    loginButton.disabled = true;
    loginButton.querySelector('span').textContent = 'Verificando...';

    try {
        const response = await api.post('/api/v1/admin/auth/login', {
            email: loginForm.elements.email.value.trim(),
            password: loginForm.elements.password.value,
        });
        const user = response.data.data.user;
        const accountType = response.data.data.account_type || user.account_type || 'admin';

        sessionStorage.setItem(tokenKey, response.data.data.token);
        sessionStorage.setItem(accountTypeKey, accountType);
        loginForm.elements.password.value = '';
        showWorkspace(user);
    } catch (error) {
        loginMessage.textContent = error.response?.data?.message
            || 'No se pudo iniciar sesión. Verifica tu conexión e inténtalo de nuevo.';
        loginMessage.hidden = false;
    } finally {
        loginButton.disabled = false;
        loginButton.querySelector('span').textContent = 'Iniciar sesión';
    }
});

document.querySelector('#refresh-button').addEventListener('click', loadPresence);
document.querySelector('#check-database-migrations').addEventListener('click', () => {
    void loadDatabaseMigrationStatus();
});
document.querySelector('#open-database-migration').addEventListener('click', openDatabaseMigrationDialog);
document.querySelector('#database-migration-form').addEventListener('submit', applyDatabaseMigrations);
document.querySelector('#database-migration-confirmed').addEventListener('change', (event) => {
    document.querySelector('#confirm-database-migration').disabled = !event.currentTarget.checked;
});
document.querySelector('#close-database-migration').addEventListener('click', () => {
    document.querySelector('#database-migration-dialog').close();
});
document.querySelector('#cancel-database-migration').addEventListener('click', () => {
    document.querySelector('#database-migration-dialog').close();
});
document.querySelector('#module-refresh-button').addEventListener('click', () => {
    void loadModuleRecords();
});
document.querySelector('#attendance-settings-form').addEventListener('submit', saveAttendanceSettings);
document.querySelector('#report-type').addEventListener('change', updateReportDateRequirements);
document.querySelector('#report-site').addEventListener('change', updateReportAccessPointOptions);
document.querySelector('#report-form').addEventListener('input', invalidateReportResults);
document.querySelector('#report-form').addEventListener('change', invalidateReportResults);
document.querySelector('#report-form').addEventListener('submit', (event) => {
    event.preventDefault();
    reportPage = 1;
    void loadReportPage();
});
document.querySelector('#report-previous-page').addEventListener('click', () => {
    if (reportPage > 1) {
        void loadReportPage(reportPage - 1);
    }
});
document.querySelector('#report-next-page').addEventListener('click', () => {
    if (reportPage < reportLastPage) {
        void loadReportPage(reportPage + 1);
    }
});
document.querySelectorAll('[data-report-export]').forEach((button) => {
    button.addEventListener('click', () => {
        void exportReport(button.dataset.reportExport);
    });
});
document.querySelector('#overtime-review-filter-form').addEventListener('submit', (event) => {
    event.preventDefault();
    overtimeReviewSelections.clear();
    overtimeReviewPage = 1;
    void loadOvertimeReviews();
});
document.querySelector('#overtime-select-page').addEventListener('change', (event) => {
    document.querySelectorAll('.overtime-row-select').forEach((checkbox) => {
        checkbox.checked = event.currentTarget.checked;
        if (checkbox.checked) {
            overtimeReviewSelections.add(Number(checkbox.value));
        } else {
            overtimeReviewSelections.delete(Number(checkbox.value));
        }
    });
    updateOvertimeSelectionUi();
});
document.querySelector('#overtime-review-rows').addEventListener('change', (event) => {
    if (event.target.matches('.overtime-row-select')) {
        const eventId = Number(event.target.value);
        if (event.target.checked) {
            overtimeReviewSelections.add(eventId);
        } else {
            overtimeReviewSelections.delete(eventId);
        }
    }
    updateOvertimeSelectionUi();
});
document.querySelector('#overtime-approve-selected').addEventListener('click', () => openOvertimeReviewDialog('approved'));
document.querySelector('#overtime-reject-selected').addEventListener('click', () => openOvertimeReviewDialog('rejected'));
document.querySelector('#overtime-review-confirm-form').addEventListener('submit', submitOvertimeReview);
document.querySelector('#close-overtime-review-dialog').addEventListener('click', () => {
    document.querySelector('#overtime-review-dialog').close();
});
document.querySelector('#cancel-overtime-review').addEventListener('click', () => {
    document.querySelector('#overtime-review-dialog').close();
});
document.querySelector('#overtime-review-previous').addEventListener('click', () => {
    if (overtimeReviewPage > 1) {
        void loadOvertimeReviews(overtimeReviewPage - 1);
    }
});
document.querySelector('#overtime-review-next').addEventListener('click', () => {
    if (overtimeReviewPage < overtimeReviewLastPage) {
        void loadOvertimeReviews(overtimeReviewPage + 1);
    }
});
document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const group = button.closest('.nav-group');
    const groupId = button.getAttribute('aria-controls');
    const storageKey = `controlaLite.nav.collapsed.${groupId}`;
    const label = button.querySelector('span').textContent.trim();
    const isCollapsed = sessionStorage.getItem(storageKey) === '1';
    group.classList.toggle('nav-group-collapsed', isCollapsed);
    button.setAttribute('aria-expanded', String(!isCollapsed));
    button.title = `${isCollapsed ? 'Mostrar' : 'Contraer'} grupo ${label}`;
    button.addEventListener('click', () => {
        const collapsed = button.getAttribute('aria-expanded') === 'true';
        group.classList.toggle('nav-group-collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.title = `${collapsed ? 'Mostrar' : 'Contraer'} grupo ${label}`;
        sessionStorage.setItem(storageKey, collapsed ? '1' : '0');
    });
});
document.querySelectorAll('.nav-link[data-module]').forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        const moduleName = link.dataset.module;
        window.history.replaceState(null, '', `#${moduleName}`);
        void setCurrentModule(moduleName);

        if (moduleName === 'dashboard' || moduleName === 'presence') {
            loadPresence();
        }
    });
});
window.addEventListener('hashchange', () => {
    const moduleName = window.location.hash.slice(1);
    if (moduleName && moduleConfig[moduleName]) {
        void setCurrentModule(moduleName);
    }
});
document.querySelector('#create-record-button').addEventListener('click', () => {
    void openRecordDialog();
});
document.querySelector('#record-form').addEventListener('submit', saveModuleRecord);
document.querySelector('#module-table-rows').addEventListener('click', handleManagementAction);
document.querySelector('#module-previous-page').addEventListener('click', () => {
    if (modulePage > 1) {
        modulePage -= 1;
        loadModuleRecords();
    }
});
document.querySelector('#module-next-page').addEventListener('click', () => {
    if (modulePage < moduleLastPage) {
        modulePage += 1;
        loadModuleRecords();
    }
});
document.querySelector('#close-dialog-button').addEventListener('click', () => {
    document.querySelector('#record-dialog').close();
});
document.querySelector('#cancel-dialog-button').addEventListener('click', () => {
    document.querySelector('#record-dialog').close();
});
document.querySelector('#record-dialog').addEventListener('click', (event) => {
    if (event.target === event.currentTarget) {
        event.currentTarget.close();
    }
});
document.querySelector('#one-time-secret-dialog').addEventListener('close', clearOneTimeSecret);
document.querySelector('#admin-detail-dialog').addEventListener('close', () => {
    const image = document.querySelector('#admin-event-selfie');
    image.removeAttribute('src');
    image.hidden = true;
    if (adminEventSelfieUrl) {
        URL.revokeObjectURL(adminEventSelfieUrl);
        adminEventSelfieUrl = null;
    }
});
document.querySelector('#close-admin-detail').addEventListener('click', () => {
    document.querySelector('#admin-detail-dialog').close();
});
document.querySelector('#close-one-time-secret').addEventListener('click', () => {
    document.querySelector('#one-time-secret-dialog').close();
});
document.querySelector('#dismiss-one-time-secret').addEventListener('click', () => {
    document.querySelector('#one-time-secret-dialog').close();
});
document.querySelector('#copy-one-time-secret').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(document.querySelector('#one-time-secret-value').value);
        document.querySelector('#one-time-secret-notice').textContent = 'Copiado. Guarda este valor antes de cerrar el diálogo.';
    } catch {
        document.querySelector('#one-time-secret-notice').textContent = 'No se pudo copiar automáticamente. Selecciona y copia el código manualmente.';
    }
});
document.querySelector('#previous-page').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage -= 1;
        loadPresence();
    }
});
document.querySelector('#next-page').addEventListener('click', () => {
    if (currentPage < lastPage) {
        currentPage += 1;
        loadPresence();
    }
});
searchInput.addEventListener('input', filterCurrentPage);

document.querySelector('#logout-button').addEventListener('click', async () => {
    try {
        await api.post('/api/v1/admin/auth/logout', {}, { headers: authHeaders() });
        sessionStorage.removeItem(tokenKey);
        sessionStorage.removeItem(accountTypeKey);
        showLogin('Sesión cerrada correctamente.');
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            sessionStorage.removeItem(accountTypeKey);
            showLogin('La sesión ya había expirado.');
            return;
        }

        dashboardMessage.textContent = error.response?.data?.message
            || 'No fue posible cerrar la sesión con el servidor. Inténtalo de nuevo.';
        dashboardMessage.hidden = false;
    }
});

document.querySelector('#person-logout-button').addEventListener('click', async () => {
    stopPersonCamera();
    try {
        await api.post('/api/v1/person/auth/logout', {}, { headers: authHeaders() });
        sessionStorage.removeItem(tokenKey);
        sessionStorage.removeItem(accountTypeKey);
        showLogin('Sesión cerrada correctamente.');
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem(tokenKey);
            sessionStorage.removeItem(accountTypeKey);
            showLogin('La sesión ya había expirado.');
            return;
        }

        document.querySelector('#person-message').textContent = error.response?.data?.message
            || 'No fue posible cerrar la sesión. Inténtalo de nuevo.';
        document.querySelector('#person-message').hidden = false;
    }
});

document.querySelector('#person-site-select').addEventListener('change', (event) => {
    personSelectedSiteId = event.currentTarget.value || null;
    personSelectedZoneId = null;
    void loadPersonAttendance();
});
document.querySelector('#person-zone-select').addEventListener('change', (event) => {
    personSelectedZoneId = event.currentTarget.value || null;
    void loadPersonAttendance();
});
document.querySelector('#attendance-entry-button').addEventListener('click', () => {
    const type = personEventTypes.find((item) => item.behavior === 'entry');
    if (type) {
        void startPersonAttendance(type.code);
    }
});
document.querySelector('#attendance-exit-button').addEventListener('click', () => {
    const type = personEventTypes.find((item) => item.behavior === 'exit');
    if (type) {
        void startPersonAttendance(type.code);
    }
});
document.querySelector('#attendance-informational-button').addEventListener('click', () => {
    const type = document.querySelector('#attendance-informational-type').value;
    if (type) {
        void startPersonAttendance(type);
    }
});
document.querySelector('#capture-attendance-selfie').addEventListener('click', captureAndSubmitPersonAttendance);
document.querySelector('#cancel-attendance-capture').addEventListener('click', () => {
    stopPersonCamera();
    void loadPersonAttendance();
});
document.querySelector('#person-camera-dialog').addEventListener('cancel', (event) => {
    event.preventDefault();
    stopPersonCamera();
    void loadPersonAttendance();
});
document.querySelector('#person-camera-dialog').addEventListener('click', (event) => {
    if (event.target === event.currentTarget) {
        stopPersonCamera();
        void loadPersonAttendance();
    }
});

async function restoreSession() {
    if (!getToken()) {
        return;
    }

    if (sessionStorage.getItem(accountTypeKey) === 'person') {
        sessionStorage.removeItem(tokenKey);
        sessionStorage.removeItem(accountTypeKey);
        showLogin('El acceso de personas está disponible únicamente desde /mobile/.');
        return;
    }

    try {
        const response = await api.get('/api/v1/admin/auth/me', { headers: authHeaders() });
        showWorkspace(response.data.data);
    } catch (error) {
        sessionStorage.removeItem(tokenKey);
        sessionStorage.removeItem(accountTypeKey);

        if (error.response?.status !== 401) {
            showLogin('No se pudo validar la sesión. Comprueba la conexión con el servidor.');
        }
    }
}

restoreSession();
