const STATUS_MAP = {
    'Activo':    { color: 'positive', bsClass: 'bg-success',   icon: 'check_circle',  textClass: 'text-positive' },
    'Bloqueado': { color: 'negative', bsClass: 'bg-danger',    icon: 'block',         textClass: 'text-negative' },
    'Inactivo':  { color: 'grey-7',   bsClass: 'bg-secondary', icon: 'remove_circle', textClass: 'text-secondary' },
    'Cancelado': { color: 'grey-9',   bsClass: 'bg-dark',      icon: 'cancel',        textClass: 'text-dark'     },
    'Nuevo':     { color: 'info',     bsClass: 'bg-info',      icon: 'fiber_new',     textClass: 'text-info'     },
};

const FALLBACK = { color: 'grey', bsClass: 'bg-secondary', icon: 'help', textClass: 'text-secondary' };

export function clientStatusMeta(estado) {
    return STATUS_MAP[estado] || FALLBACK;
}
