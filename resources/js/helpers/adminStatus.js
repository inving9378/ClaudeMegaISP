const STATUS_MAP = {
    activo:    { color: 'positive', label: 'Activo',    rowClass: 'Activo'    },
    bloqueado: { color: 'negative', label: 'Bloqueado', rowClass: 'Bloqueado' },
    inactivo:  { color: 'grey-7',   label: 'Inactivo',  rowClass: 'Inactivo'  },
};

export function adminStatusMeta(estado) {
    return STATUS_MAP[estado] ?? { color: 'grey-5', label: estado ?? '—', rowClass: '' };
}
