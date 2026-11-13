/**
 * Marketing IA — tokens de color de la guía visual (docs/marketing/guia-visual.md).
 *
 * Origen de cada valor (verificado con grep en el CSS SERVIDO, 2026-11-12):
 *   - Sistema LM: public/assets/css/bootstrap.min.css (--bs-primary, --bs-success, …).
 *   - Modo oscuro: public/assets/css/app.min.css (body[data-layout-mode=dark]) y
 *     bootstrap.min.css (body[data-layout-mode=dark] .card → fondo #313533, borde #3b403d).
 *   - Marca Meganet: Brand Kit (marketing_settings, grupo brand) y marketing_empresas id 1.
 *
 * Estos valores son los MISMOS que MktRaiz.vue define como variables --mkt-*.
 * Se exportan aquí para lo que no puede leer variables CSS (ApexCharts, Chart.js).
 */

export const COLORES = Object.freeze({
    primario: "#5156be",
    exito: "#2ab57d",
    info: "#4ba6ef",
    aviso: "#ffbf53",
    peligro: "#fd625e",
    neutro: "#74788d",
    borde: "#e9e9ef",
    texto: "#343a40",
    textoSuave: "#74788d",
    fondoTarjeta: "#ffffff",
});

export const COLORES_OSCURO = Object.freeze({
    borde: "#3b403d",
    texto: "#ced4da",
    textoSuave: "#858d98",
    fondoTarjeta: "#313533",
});

export const MARCA_MEGANET = Object.freeze({
    primario: "#0066CC",
    secundario: "#FF6600",
});

/** Serie de colores para gráficas (orden fijo: primero la marca, luego la paleta LM). */
export const SERIE_GRAFICAS = Object.freeze([
    COLORES.primario,
    COLORES.exito,
    COLORES.info,
    COLORES.aviso,
    COLORES.peligro,
    COLORES.neutro,
]);

export const FUENTE = '"IBM Plex Sans", sans-serif';

/** Valida "#RRGGBB" (mismo patrón que el backend). */
export const esColorHex = (v) => typeof v === "string" && /^#[0-9A-Fa-f]{6}$/.test(v);

export default { COLORES, COLORES_OSCURO, MARCA_MEGANET, SERIE_GRAFICAS, FUENTE, esColorHex };
