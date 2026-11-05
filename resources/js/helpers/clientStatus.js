// Mapeo ÚNICO estado del cliente → presentación (item #488). Las llaves son el VALOR GUARDADO en
// client_main_information.estado (la opción "Activado" guarda 'Activo'). `tone` es el slug de los
// tokens de color de resources/sass/app.scss ($c3-filled-states): se usa como clase
// `estado-tono--{tone}` (ver clientStatusToneClass). bsClass se conserva para el listado.
const STATUS_MAP = {
    'Activo':       { tone: 'activo',       color: 'positive', bsClass: 'bg-success',   icon: 'check_circle',  textClass: 'text-positive' },
    'Bloqueado':    { tone: 'bloqueado',    color: 'negative', bsClass: 'bg-danger',    icon: 'block',         textClass: 'text-negative' },
    'Inactivo':     { tone: 'inactivo',     color: 'grey-7',   bsClass: 'bg-secondary', icon: 'remove_circle', textClass: 'text-secondary' },
    'Cancelado':    { tone: 'cancelado',    color: 'grey-9',   bsClass: 'bg-dark',      icon: 'cancel',        textClass: 'text-dark'     },
    'Nuevo':        { tone: 'nuevo',        color: 'info',     bsClass: 'bg-info',      icon: 'fiber_new',     textClass: 'text-info'     },
    'Desinstalado': { tone: 'desinstalado', color: 'grey-6',   bsClass: 'bg-secondary', icon: 'link_off',      textClass: 'text-secondary' },
};

const FALLBACK = { tone: null, color: 'grey', bsClass: 'bg-secondary', icon: 'help', textClass: 'text-secondary' };

export function clientStatusMeta(estado) {
    return STATUS_MAP[estado] || FALLBACK;
}

// Clase de tono para .estado-chip u otro elemento; '' si el estado no está mapeado (queda neutro).
export function clientStatusToneClass(estado) {
    const tone = clientStatusMeta(estado).tone;
    return tone ? `estado-tono--${tone}` : '';
}
