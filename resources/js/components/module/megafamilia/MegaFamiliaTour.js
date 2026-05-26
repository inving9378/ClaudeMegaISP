// Tours guiados para MegaFamilia (Driver.js v1).
// Definición por pantalla — cada entrada apunta a un selector ya presente
// en el DOM. Si el elemento no existe el paso se omite en runtime.
const tours = {
    dashboard: [
        { element: '#mf-kpi-cuentas',      popover: { title: '👥 Cuentas activas',     description: 'Total de padres con el servicio MegaFamilia activo.' }},
        { element: '#mf-kpi-dispositivos', popover: { title: '📱 Dispositivos',         description: 'Dispositivos de hijos vinculados y conectados ahora.' }},
        { element: '#mf-kpi-alertas',      popover: { title: '🔔 Alertas',              description: 'Alertas sin leer que requieren tu atención.' }},
        { element: '#mf-kpi-planes',       popover: { title: '📦 Planes activos',       description: 'Planes comerciales disponibles para contratar.' }},
        { element: '#mf-chart-clientes',   popover: { title: '📊 Clientes por plan',    description: 'Distribución de clientes según el plan contratado.' }},
        { element: '#mf-ingresos-mes',     popover: { title: '💰 Ingresos estimados',   description: 'MRR estimado basado en cuentas activas × precio del plan.' }},
    ],
    planes: [
        { element: '.mf-planes-grid',  popover: { title: '📦 Catálogo de planes',  description: 'Aquí defines qué ofreces: precio, límites y funciones incluidas.' }},
        { element: '#btn-nuevo-plan',  popover: { title: '➕ Nuevo plan',          description: 'Crea un plan nuevo. Define precio, máximo de hijos y dispositivos.' }},
        { element: '.mf-plan-toggle',  popover: { title: '🔛 Activar/Desactivar',  description: 'Desactiva un plan para que no esté disponible sin eliminarlo.' }},
    ],
    clientes: [
        { element: '#mf-clientes-search',  popover: { title: '🔍 Buscar cliente',   description: 'Busca por nombre, email o filtra por plan y estado.' }},
        { element: '#mf-clientes-tabla',   popover: { title: '📋 Lista de clientes', description: 'Todos los padres con cuenta MegaFamilia. Click en una fila para ver el detalle.' }},
        { element: '.mf-cliente-acciones', popover: { title: '⚡ Acciones rápidas',  description: 'Activa, suspende o cancela el servicio de un cliente.' }},
    ],
    licencias: [
        { element: '.mf-lic-kpis',       popover: { title: '📊 Estado de licencias', description: 'Resumen rápido: activas, por vencer en 30 días y expiradas.' }},
        { element: '#btn-nueva-licencia',popover: { title: '➕ Nueva licencia',      description: 'Asigna una licencia manualmente a un cliente.' }},
        { element: '.mf-lic-vencer',     popover: { title: '⚠️ Por vencer',          description: 'Filas en amarillo vencen en menos de 30 días. En rojo ya expiraron.' }},
    ],
    perfiles: [
        { element: '.mf-perfiles-grid', popover: { title: '👦 Perfiles de hijos',  description: 'Cada tarjeta es un perfil de menor registrado por el padre.' }},
        { element: '.mf-perfil-tabs',   popover: { title: '📑 Detalle del perfil', description: 'Cada perfil tiene 5 tabs: Info, Dispositivos, Reglas, Tareas y Alertas.' }},
    ],
    dispositivos: [
        { element: '.mf-dev-kpis',    popover: { title: '📱 Estado de dispositivos', description: 'Total vinculados, online ahora y sin conexión más de 7 días.' }},
        { element: '.mf-dev-battery', popover: { title: '🔋 Batería',                description: 'Verde >50%, amarillo >20%, rojo <20%. Monitorea el dispositivo del menor.' }},
        { element: '.mf-dev-acciones',popover: { title: '⚡ Acciones',               description: 'Ping: verifica conexión. Forzar logout: cierra sesión remota. Desvincular: elimina el dispositivo.' }},
    ],
    solicitudes: [
        { element: '.mf-sol-tabs',    popover: { title: '📬 Bandeja de solicitudes', description: 'Los hijos envían solicitudes de más tiempo o desbloqueo de apps.' }},
        { element: '.mf-sol-aprobar', popover: { title: '✅ Aprobar',                description: 'Al aprobar se envía push automático al dispositivo del hijo.' }},
        { element: '.mf-sol-rechazar',popover: { title: '❌ Rechazar',               description: 'Puedes agregar un motivo que verá el menor en su app.' }},
    ],
    tareas: [
        { element: '.mf-kanban',          popover: { title: '📋 Tablero Kanban', description: '4 columnas: Pendiente → Por aprobar → Aprobada → Rechazada.' }},
        { element: '#btn-nueva-tarea',    popover: { title: '➕ Nueva tarea',     description: 'Crea una tarea para un hijo. Define la recompensa: tiempo extra, desbloqueo de app o puntos.' }},
        { element: '.mf-tarea-recompensa',popover: { title: '🎁 Recompensa',      description: 'Al aprobar la tarea se libera automáticamente la recompensa al menor.' }},
    ],
    alertas: [
        { element: '.mf-alert-kpis',    popover: { title: '🚨 Panel de alertas',    description: 'Hoy, sin leer y críticas (intentos de desinstalación y botón SOS).' }},
        { element: '.mf-alert-critica', popover: { title: '🔴 Alertas críticas',    description: 'Requieren atención inmediata: intento de desinstalación o botón de emergencia.' }},
        { element: '#btn-mark-all-read',popover: { title: '✓ Marcar todas leídas', description: 'Marca todas las alertas como revisadas de un solo click.' }},
    ],
    ubicaciones: [
        { element: '.mf-ubi-perfiles',      popover: { title: '👦 Panel de perfiles',     description: 'Selecciona un perfil para centrar el mapa en su última ubicación.' }},
        { element: '#mf-mapa',              popover: { title: '🗺️ Mapa en tiempo real',   description: 'Markers con las ubicaciones actuales. Click para ver historial de movimientos.' }},
        { element: '#btn-toggle-geofences', popover: { title: '📍 Geofences',             description: 'Activa esta capa para ver las zonas seguras definidas para cada perfil.' }},
    ],
    geofences: [
        { element: '.mf-geo-lista',       popover: { title: '🔵 Zonas seguras',   description: 'Lista de zonas definidas. Activa/desactiva cada una con el toggle.' }},
        { element: '#btn-nueva-geofence', popover: { title: '➕ Nueva zona',      description: 'Click en el mapa para definir el centro, ajusta el radio con el slider.' }},
        { element: '.mf-geo-trigger',     popover: { title: '🔔 Tipo de alerta',  description: 'Entrada: alerta cuando el menor llega. Salida: alerta cuando se va. Ambas: siempre.' }},
    ],
};

function getDriverFactory() {
    // Driver.js v1 IIFE expone window.driver.js.driver
    return window.driver?.js?.driver || null;
}

export function startTour(page) {
    const factory = getDriverFactory();
    if (!factory) {
        console.warn('[MF Tour] Driver.js no está cargado.');
        return;
    }
    const steps = tours[page];
    if (!steps || steps.length === 0) return;

    // Filtrar pasos cuyos elementos no existen para evitar errores
    const visible = steps.filter(s => document.querySelector(s.element));
    if (visible.length === 0) {
        console.warn(`[MF Tour] Ningún elemento del tour "${page}" está en el DOM.`);
        return;
    }

    const driverObj = factory({
        showProgress: true,
        showButtons: ['next', 'previous', 'close'],
        nextBtnText: 'Siguiente →',
        prevBtnText: '← Anterior',
        doneBtnText: '¡Listo! ✓',
        progressText: 'Paso {{current}} de {{total}}',
        onDestroyed: () => {
            try { localStorage.setItem('mf_tour_' + page, 'done'); } catch (e) { /* no-op */ }
        },
        steps: visible.map(s => ({
            element: s.element,
            popover: {
                title: s.popover.title,
                description: s.popover.description,
                side: 'bottom',
                align: 'start',
            },
        })),
    });

    driverObj.drive();
}

export function shouldShowTour(page) {
    try { return !localStorage.getItem('mf_tour_' + page); } catch (e) { return false; }
}

export function resetTour(page) {
    try { localStorage.removeItem('mf_tour_' + page); } catch (e) { /* no-op */ }
}
