// «A quién se le llama» de Campañas/Blaster (27-nov): estados del cliente, zona y caja de su
// nomenclatura (D1Z54C7) y «solo los que deben». Lo comparten el alta, la revisión y el detalle.

export const ESTADOS_CLIENTE = { Activo: 'Activos', Bloqueado: 'Bloqueados', Inactivo: 'Inactivos' };
export const ESTADOS_POR_OMISION = ['Activo', 'Bloqueado'];

export function filtrosDe(campana) {
    const a = (campana && campana.audiencia) || {};
    const estados = Array.isArray(a.estados) ? a.estados.filter(e => e in ESTADOS_CLIENTE) : [...ESTADOS_POR_OMISION];
    return { estados, zona: a.zona || null, caja: a.caja || null };
}

// Lo que solo se puede leer con «Solo los que deben» (se cambia por los datos de cada cliente).
const VARIABLES = ['{nombre}', '{saldo}', '{fecha}', '{numero_cliente}'];
export const tieneVariables = texto => VARIABLES.some(v => String(texto || '').includes(v));

// «D1Z54C7» → { d: 1, l: 'Z', z: 54, c: 7 }. W = zona de WiFi (otra zona aunque tenga el mismo número).
export function leerClave(clave) {
    const m = /^D(\d+)([ZW])(\d+)(?:C(\d+))?$/.exec(String(clave || ''));
    return m ? { d: +m[1], l: m[2], z: +m[3], c: m[4] != null ? +m[4] : null } : null;
}

// Zonas cuyo número existe en más de un distrito: ahí la zona se nombra con su distrito.
export function zonasRepetidas(zonas) {
    const vistos = {};
    for (const z of zonas || []) {
        const k = `${z.letra || 'Z'}${z.zona}`;
        (vistos[k] = vistos[k] || new Set()).add(z.distrito);
    }
    return new Set(Object.keys(vistos).filter(k => vistos[k].size > 1));
}

// Sin la lista de zonas a la mano (detalle, Excel) no se sabe si el número se repite: va con distrito.
export function nombreZona(clave, repetidas) {
    const k = leerClave(clave);
    if (!k) return '';
    const nombre = k.l === 'W' ? `WiFi ${k.z}` : `Zona ${k.z}`;
    return !repetidas || repetidas.has(`${k.l}${k.z}`) ? `${nombre} · D${k.d}` : nombre;
}

export function nombreCaja(clave) {
    const k = leerClave(clave);
    return k && k.c != null ? `Caja ${k.c}` : '';
}

function lista(palabras) {
    if (palabras.length <= 1) return palabras.join('');
    return `${palabras.slice(0, -1).join(', ')} y ${palabras[palabras.length - 1]}`;
}

// «Activos y Bloqueados · Zona 54 · Caja 7 · solo los que deben (1 día o más)»
export function resumenAudiencia(filtros, soloDeudores, dias, repetidas) {
    const f = filtros || {};
    const estados = (f.estados || []).map(e => ESTADOS_CLIENTE[e]).filter(Boolean);
    const partes = [estados.length ? lista(estados) : 'Ningún estado'];
    if (f.zona) partes.push(nombreZona(f.zona, repetidas));
    if (f.caja) partes.push(nombreCaja(f.caja));
    if (!f.zona) partes.push('todas las zonas');
    if (soloDeudores) {
        const d = Number(dias || 0);
        partes.push(d > 0 ? `solo los que deben (${d} ${d === 1 ? 'día' : 'días'} o más)` : 'solo los que deben');
    }
    return partes.join(' · ');
}
