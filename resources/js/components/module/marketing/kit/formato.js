/**
 * Marketing IA — formatos de presentación (es-MX). Nulo/indefinido/no numérico → "—".
 */

const VACIO = "—";

const esNumero = (v) => v !== null && v !== undefined && v !== "" && Number.isFinite(Number(v));

const fmtMXN = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const fmtNumero = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 2 });
const fmtPorcentaje = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 1 });

/** 1234.5 → "$1,234.50 MXN" */
export function formatoMXN(v) {
    return esNumero(v) ? `${fmtMXN.format(Number(v))} MXN` : VACIO;
}

/** 12345 → "12,345" */
export function formatoNumero(v) {
    return esNumero(v) ? fmtNumero.format(Number(v)) : VACIO;
}

/** 12.345 → "12.3 %" (recibe el porcentaje ya calculado, no la fracción). */
export function formatoPorcentaje(v) {
    return esNumero(v) ? `${fmtPorcentaje.format(Number(v))} %` : VACIO;
}

/**
 * ISO-8601 → "12 nov 2026" (o con hora: "12 nov 2026, 10:00").
 * Se muestra en la zona del sistema (America/Mexico_City).
 */
export function formatoFecha(v, { conHora = false } = {}) {
    if (!v) return VACIO;
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return VACIO;
    const opciones = { day: "numeric", month: "short", year: "numeric", timeZone: "America/Mexico_City" };
    if (conHora) Object.assign(opciones, { hour: "2-digit", minute: "2-digit" });
    return new Intl.DateTimeFormat("es-MX", opciones).format(d).replace(/\./g, "");
}

/** Aplica un formato por nombre ('numero' | 'mxn' | 'porcentaje'). */
export function formatear(v, formato = "numero") {
    if (formato === "mxn") return formatoMXN(v);
    if (formato === "porcentaje") return formatoPorcentaje(v);
    return formatoNumero(v);
}

/**
 * Extrae el mensaje en español de un error de axios con el formato del contrato:
 *   {ok:false, error, mensaje} | {message, errors:{campo:[…]}}.
 */
export function mensajeDeError(e, porDefecto = "No se pudo completar la operación. Intenta de nuevo.") {
    const d = e?.response?.data;
    // Ningún texto en inglés de la API llega a la pantalla (p. ej. Spatie: «User does not have the right permissions.»).
    if (d?.mensaje && !pareceIngles(d.mensaje)) return d.mensaje;
    if (d?.errors) {
        const primero = Object.values(d.errors)[0];
        if (Array.isArray(primero) && primero[0] && !pareceIngles(primero[0])) return primero[0];
    }
    if (e?.response?.status === 403) return "No tienes permiso para esta acción.";
    if (e?.response?.status === 404) return "No se encontró el registro (o no pertenece a tu empresa).";
    if (!e?.response) return "No hay conexión con el servidor. Revisa tu red e intenta de nuevo.";
    return porDefecto;
}

/**
 * ¿El texto parece un mensaje en inglés de Laravel / Spatie / el servidor? Se usa para no mostrarlo.
 * Heurística corta a propósito: frases típicas de los paquetes, no una detección de idioma.
 */
export function pareceIngles(texto) {
    const t = String(texto || "").trim();
    if (!t) return false;
    return /^(User does not|User is not|The given data|The .+ (field|must|is required)|This action is unauthorized|Unauthenticated|Unauthorized|Forbidden|Not Found|Server Error|Too Many|Request failed|Network Error|CSRF token|Page Expired|Method Not Allowed|Service Unavailable)/i.test(t);
}

/** Roles por empresa (marketing_empresa_usuario.rol) → etiqueta y tono de insignia. */
export const ROLES_EMPRESA = Object.freeze([
    { value: "admin", label: "Administrador", tono: "primario", descripcion: "Todo Marketing + Empresas y equipo" },
    { value: "marketing", label: "Marketing", tono: "info", descripcion: "Campañas, contenido, video, publicación, leads" },
    { value: "ventas", label: "Ventas", tono: "exito", descripcion: "Leads y conversaciones" },
]);

export function etiquetaRol(rol) {
    return ROLES_EMPRESA.find((r) => r.value === rol)?.label || rol || VACIO;
}

export function tonoRol(rol) {
    return ROLES_EMPRESA.find((r) => r.value === rol)?.tono || "neutro";
}

// ── Fase 1 (solo se agrega; lo de arriba no cambia) ──────────────────────────

/**
 * Monto con su moneda, sin convertir: "MXN" → formatoMXN; otra → "$1,234.50 USD".
 * Recibe la cadena del backend ("1200.50"); Number() solo para formatear.
 */
export function formatoMoneda(v, moneda = "MXN") {
    const m = String(moneda || "MXN").toUpperCase();
    if (m === "MXN") return formatoMXN(v);
    if (!esNumero(v)) return VACIO;
    const fmt = new Intl.NumberFormat("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `$${fmt.format(Number(v))} ${m}`;
}

/** Estados de lead (marketing_leads.status) → etiqueta y tono de insignia. */
export const ESTADOS_LEAD = Object.freeze([
    { value: "new", label: "Nuevo", tono: "neutro" },
    { value: "contacted", label: "Contactado", tono: "info" },
    { value: "qualified", label: "Calificado", tono: "aviso" },
    { value: "negotiating", label: "En negociación", tono: "primario" },
    { value: "won", label: "Ganado", tono: "exito" },
    { value: "lost", label: "Perdido", tono: "peligro" },
    { value: "archived", label: "Archivado", tono: "neutro" },
]);

export function etiquetaEstadoLead(v) {
    return ESTADOS_LEAD.find((e) => e.value === v)?.label || v || VACIO;
}

export function tonoEstadoLead(v) {
    return ESTADOS_LEAD.find((e) => e.value === v)?.tono || "neutro";
}

/**
 * Mes actual y los `n - 1` anteriores (zona America/Mexico_City), del más reciente al más viejo:
 * [{ value: "2026-11", label: "Noviembre 2026" }, …]. Para el selector de mes del Dashboard.
 */
export function mesesRecientes(n = 12, hoy = new Date()) {
    const partes = new Intl.DateTimeFormat("en-CA", { timeZone: "America/Mexico_City", year: "numeric", month: "2-digit" })
        .formatToParts(hoy);
    let anio = Number(partes.find((p) => p.type === "year").value);
    let mes = Number(partes.find((p) => p.type === "month").value);
    const nombre = new Intl.DateTimeFormat("es-MX", { month: "long", timeZone: "UTC" });
    const lista = [];
    for (let i = 0; i < n; i++) {
        const value = `${anio}-${String(mes).padStart(2, "0")}`;
        const texto = nombre.format(new Date(Date.UTC(anio, mes - 1, 15)));
        lista.push({ value, label: `${texto.charAt(0).toUpperCase()}${texto.slice(1)} ${anio}` });
        mes -= 1;
        if (mes === 0) { mes = 12; anio -= 1; }
    }
    return lista;
}

/** Iniciales para avatar: "Ana López" → "AL"; vacío → "?". */
export function iniciales(texto) {
    const p = String(texto || "").trim().split(/\s+/).filter(Boolean);
    if (!p.length) return "?";
    return (p[0][0] + (p.length > 1 ? p[p.length - 1][0] : "")).toUpperCase();
}

/** ¿El error de axios es 403 / 404? */
export const esSinPermiso = (e) => e?.response?.status === 403;
export const esNoEncontrado = (e) => e?.response?.status === 404;

/** Errores 422 por campo → { campo: "mensaje" } (para :error-message de q-input). */
export function erroresPorCampo(e) {
    const errs = e?.response?.status === 422 ? e.response.data?.errors : null;
    if (!errs || typeof errs !== "object") return {};
    return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)]));
}

/**
 * ¿El usuario tiene el permiso Spatie `p`? Lee el store global (GET /permissions-auth, cargado al arrancar).
 * Mientras el store no ha cargado devuelve true (el backend sigue exigiendo el permiso en cada acción).
 */
export function puedePermiso(store, p) {
    const lista = store?.state?.permissions;
    if (!p || !Array.isArray(lista) || lista.length === 0) return true;
    return typeof store.getters?.hasPermission === "function" ? store.getters.hasPermission(p) : lista.includes(p);
}

/**
 * Fecha SIN hora ("2026-11-01", columnas date) → "1 nov 2026", sin corrimiento de zona.
 * (formatoFecha interpreta "2026-11-01" como medianoche UTC y en México mostraría el día anterior.)
 */
export function formatoDia(v) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v || "").slice(0, 10));
    if (!m) return formatoFecha(v);
    const d = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), 12));
    return new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" })
        .format(d).replace(/\./g, "");
}

// ── Fase 2 (solo se agrega) ───────────────────────────────────────────────────

/**
 * Segundos → "27 s", "27.6 s", "1 min 15 s". Acepta la cadena del backend ("27.6"). Nulo → "—".
 */
export function formatoDuracion(seg) {
    if (!esNumero(seg)) return VACIO;
    const n = Math.max(0, Number(seg));
    if (n < 60) {
        const fmt = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 1 });
        return `${fmt.format(n)} s`;
    }
    const total = Math.round(n);
    const min = Math.floor(total / 60);
    const s = total % 60;
    return s ? `${min} min ${s} s` : `${min} min`;
}

export default {
    formatoMXN, formatoNumero, formatoPorcentaje, formatoFecha, formatear, mensajeDeError,
    ROLES_EMPRESA, etiquetaRol, tonoRol,
    formatoMoneda, ESTADOS_LEAD, etiquetaEstadoLead, tonoEstadoLead, mesesRecientes, iniciales,
    esSinPermiso, esNoEncontrado, erroresPorCampo, puedePermiso, formatoDia,
    formatoDuracion,
};
