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
    if (d?.mensaje) return d.mensaje;
    if (d?.errors) {
        const primero = Object.values(d.errors)[0];
        if (Array.isArray(primero) && primero[0]) return primero[0];
    }
    if (e?.response?.status === 403) return "No tienes permiso para esta acción.";
    if (e?.response?.status === 404) return "No se encontró el registro (o no pertenece a tu empresa).";
    if (!e?.response) return "No hay conexión con el servidor. Revisa tu red e intenta de nuevo.";
    return porDefecto;
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

export default {
    formatoMXN, formatoNumero, formatoPorcentaje, formatoFecha, formatear, mensajeDeError,
    ROLES_EMPRESA, etiquetaRol, tonoRol,
};
