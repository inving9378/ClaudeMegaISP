/**
 * Campañas 360 — catálogos, etiquetas y límites (espejo de docs/marketing/fase-2-paquetes.md §2.4–§2.5).
 * El backend (CatalogosCampana360 / ValidadorPiezas) es la fuente de verdad: aquí solo se repite lo necesario
 * para ayudar en pantalla (contadores, avisos en vivo). Cualquier 422 del backend gana sobre esto.
 */

// ── §2.5.1 Objetivos ─────────────────────────────────────────────────────────
export const OBJETIVOS = Object.freeze([
    { value: "nuevos_clientes", label: "Conseguir clientes nuevos", icono: "person_add", ayuda: "Llevar a más personas a contratar." },
    { value: "reconocimiento", label: "Dar a conocer la marca", icono: "campaign", ayuda: "Que más gente nos ubique y recuerde." },
    { value: "cobertura_nueva", label: "Anunciar cobertura nueva", icono: "map", ayuda: "Avisar que ya llegamos a una zona." },
    { value: "promocion", label: "Impulsar una promoción", icono: "sell", ayuda: "Mover una oferta con fecha límite." },
    { value: "retencion", label: "Retener clientes", icono: "loyalty", ayuda: "Que los clientes actuales se queden." },
    { value: "mejora_plan", label: "Subir de plan a clientes", icono: "upgrade", ayuda: "Ofrecer más velocidad a quien ya es cliente." },
]);

// ── §2.5.2 Canales y §2.5.3 piezas por canal ─────────────────────────────────
export const CANALES = Object.freeze([
    { value: "reels", label: "Reels (9:16)", corto: "Reels", icono: "smart_display", pieza: "reel_guion", variantes: ["A", "B"],
      genera: "2 guiones de Reel por escenas (A y B), listos para producir en video." },
    { value: "video", label: "Video (16:9)", corto: "Video", icono: "movie", pieza: "video_guion", variantes: ["A"],
      genera: "1 guion de video horizontal por escenas, listo para producir." },
    { value: "facebook", label: "Publicaciones de Facebook", corto: "Facebook", icono: "thumb_up", pieza: "copy_facebook", variantes: ["A", "B"],
      genera: "2 textos para publicar (A y B) con hashtags y llamada a la acción." },
    { value: "instagram", label: "Publicaciones de Instagram", corto: "Instagram", icono: "photo_camera", pieza: "copy_instagram", variantes: ["A", "B"],
      genera: "2 textos para publicar (A y B) con hashtags y llamada a la acción." },
    { value: "anuncios_meta", label: "Anuncios de Meta", corto: "Anuncios", icono: "ads_click", pieza: "variante_anuncio", variantes: ["A", "B", "C"],
      genera: "3 variantes de anuncio (titular, texto, botón e idea visual)." },
    { value: "whatsapp", label: "Mensajes de WhatsApp (solo texto, no se envían)", corto: "WhatsApp", icono: "chat", pieza: "mensaje_whatsapp", variantes: ["A", "B", "C"],
      genera: "3 mensajes de texto: difusión, seguimiento y respuesta. No se envían desde aquí." },
]);

export const ETIQUETAS_PIEZA = Object.freeze({
    reel_guion: "Guion de Reel",
    video_guion: "Guion de Video",
    copy_facebook: "Texto de Facebook",
    copy_instagram: "Texto de Instagram",
    variante_anuncio: "Variante de anuncio",
    mensaje_whatsapp: "Mensaje de WhatsApp",
    reel: "Reel producido",
    video: "Video producido",
});

/** Orden de las claves de `piezas` en la ficha (§2.4.3). */
export const TIPOS_PIEZA = Object.freeze(["reel_guion", "video_guion", "copy_facebook", "copy_instagram", "variante_anuncio", "mensaje_whatsapp"]);

// ── Estados ──────────────────────────────────────────────────────────────────
export const ESTADOS_CAMPANA = Object.freeze([
    { value: "draft", label: "Borrador", tono: "neutro" },
    { value: "active", label: "Activa", tono: "exito" },
    { value: "paused", label: "Pausada", tono: "aviso" },
    { value: "finished", label: "Terminada", tono: "info" },
    { value: "canceled", label: "Cancelada", tono: "peligro" },
]);

/** Transiciones válidas (§2.4.6) → etiqueta del botón, ícono y si pide confirmación. */
export const TRANSICIONES = Object.freeze({
    draft: [
        { a: "active", label: "Activar", icono: "play_arrow", principal: true },
        { a: "canceled", label: "Cancelar campaña", icono: "block", confirmar: true },
    ],
    active: [
        { a: "paused", label: "Pausar", icono: "pause" },
        { a: "finished", label: "Terminar", icono: "flag", confirmar: true },
    ],
    paused: [
        { a: "active", label: "Reanudar", icono: "play_arrow", principal: true },
        { a: "finished", label: "Terminar", icono: "flag", confirmar: true },
        { a: "canceled", label: "Cancelar campaña", icono: "block", confirmar: true },
    ],
    finished: [],
    canceled: [],
});

export const ESTADOS_GENERACION = Object.freeze({
    sin_generar: { label: "Sin generar", tono: "neutro" },
    generando: { label: "Generando", tono: "info" },
    lista: { label: "Lista", tono: "exito" },
    con_errores: { label: "Con errores", tono: "peligro" },
});

export const ESTADOS_PIEZA = Object.freeze({
    pendiente: { label: "En espera", tono: "neutro" },
    generando: { label: "Generando", tono: "info" },
    lista: { label: "Lista", tono: "exito" },
    error: { label: "Error", tono: "peligro" },
});

export const ESTADOS_VIDEO = Object.freeze({
    en_cola: { label: "En cola", tono: "neutro", icono: "hourglass_top" },
    produciendo: { label: "Produciendo", tono: "info", icono: "autorenew" },
    listo: { label: "Listo", tono: "exito", icono: "check_circle" },
    error: { label: "Error", tono: "peligro", icono: "error" },
});

export const AVISO_DEMORADO = "Sigue en cola: el procesador de videos no está corriendo. Avísale a Irving.";
export const AVISO_WHATSAPP = "Solo texto: estos mensajes no se envían desde LM. Cópialos y úsalos donde corresponda.";

// ── §2.5.5 Guiones ───────────────────────────────────────────────────────────
export const TIPOS_VISUAL = Object.freeze([
    { value: "broll", label: "Video de apoyo (clips de fondo)", tope: 60 },
    { value: "texto", label: "Texto grande", tope: 18 },
    { value: "cta", label: "Cierre (llamada a la acción)", tope: 24 },
    { value: "logo", label: "Logo", tope: null },
]);

export const LIMITES_GUION = Object.freeze({
    reel_guion: { formato: "9:16", minEscenas: 3, maxEscenas: 8, minTotal: 10, maxTotal: 45, nombre: "Reel" },
    video_guion: { formato: "16:9", minEscenas: 3, maxEscenas: 8, minTotal: 15, maxTotal: 75, nombre: "Video" },
});

export const LIMITES_ESCENA = Object.freeze({
    minDuracion: 2, maxDuracion: 15, toma: 200, vozEnOff: 300, musicaSonido: 100, ctaFinal: 24,
    titulo: 120, gancho: 200, palabrasPorSegundo: 2.5,
});

// ── §2.5.6 Catálogos cerrados ────────────────────────────────────────────────
export const VOCES = Object.freeze([
    { value: "alloy", label: "Alloy — neutra" },
    { value: "echo", label: "Echo — masculina joven" },
    { value: "fable", label: "Fable — masculina narrativa" },
    { value: "onyx", label: "Onyx — masculina grave" },
    { value: "nova", label: "Nova — femenina cálida" },
    { value: "shimmer", label: "Shimmer — femenina suave" },
]);

export const MUSICAS = Object.freeze([
    { value: "corporate_clean", label: "Corporativa limpia" },
    { value: "corporate_confident", label: "Corporativa segura" },
    { value: "epic_electronic", label: "Electrónica épica" },
    { value: "modern_creative", label: "Moderna creativa" },
    { value: "uplifting_family", label: "Familiar optimista" },
    { value: "warm_acoustic", label: "Acústica cálida" },
    { value: "sin_musica", label: "Sin música" },
]);

// ── Copies, anuncios y WhatsApp ──────────────────────────────────────────────
export const LIMITES_COPY = Object.freeze({
    copy_facebook: { texto: 1500, hashtags: 5, hashtag: 30, cta: 40 },
    copy_instagram: { texto: 2000, hashtags: 15, hashtag: 30, cta: 40 },
});

export const LIMITES_ANUNCIO = Object.freeze({ titular: 40, texto_principal: 125, descripcion: 30, idea_visual: 200 });

export const BOTONES_ANUNCIO = Object.freeze([
    { value: "MAS_INFORMACION", label: "Más información" },
    { value: "ENVIAR_MENSAJE", label: "Enviar mensaje" },
    { value: "LLAMAR", label: "Llamar" },
    { value: "REGISTRARTE", label: "Registrarte" },
    { value: "OBTENER_OFERTA", label: "Obtener oferta" },
    { value: "CONTACTANOS", label: "Contáctanos" },
]);

export const FORMATOS_ANUNCIO = Object.freeze([
    { value: "imagen", label: "Imagen" },
    { value: "video", label: "Video" },
    { value: "carrusel", label: "Carrusel" },
]);

export const USOS_WHATSAPP = Object.freeze([
    { value: "difusion", label: "Difusión" },
    { value: "seguimiento", label: "Seguimiento" },
    { value: "respuesta", label: "Respuesta a quien pregunta" },
]);

export const LIMITE_WHATSAPP = 700;
export const LIMITE_INDICACIONES = 500;

// ── Límites del asistente (§2.4.2) ───────────────────────────────────────────
export const LIMITES_CAMPANA = Object.freeze({
    nombre: [3, 150], publicoDescripcion: [3, 500], zonas: 20, zona: 80, edad: [13, 99], intereses: 10, interes: 40,
    ofertaDescripcion: [3, 500], plan: 100, condiciones: 500, vigenciaDias: 366,
});

export const RE_PRECIO = /^\d{1,8}(\.\d{1,2})?$/;
export const RE_PRESUPUESTO = /^\d{1,10}(\.\d{1,2})?$/;

// ── Ayudantes ────────────────────────────────────────────────────────────────
const buscar = (lista, v) => lista.find((x) => x.value === v);

export const etiquetaObjetivo = (v) => buscar(OBJETIVOS, v)?.label || v || "—";
export const etiquetaCanal = (v) => buscar(CANALES, v)?.corto || v || "—";
export const iconoCanal = (v) => buscar(CANALES, v)?.icono || "campaign";
export const etiquetaEstadoCampana = (v) => buscar(ESTADOS_CAMPANA, v)?.label || v || "—";
export const tonoEstadoCampana = (v) => buscar(ESTADOS_CAMPANA, v)?.tono || "neutro";
export const etiquetaVoz = (v) => buscar(VOCES, v)?.label || v || "—";
export const etiquetaMusica = (v) => buscar(MUSICAS, v)?.label || v || "—";
export const etiquetaBoton = (v) => buscar(BOTONES_ANUNCIO, v)?.label || v || "—";
export const etiquetaUsoWhatsApp = (v) => buscar(USOS_WHATSAPP, v)?.label || v || "—";
export const etiquetaVisual = (v) => buscar(TIPOS_VISUAL, v)?.label || v || "—";
export const topeTextoPantalla = (tipo) => buscar(TIPOS_VISUAL, tipo)?.tope ?? null;

/** Cuántas piezas generará la campaña con estos canales (para el paso Revisar). */
export function piezasEsperadas(canales = []) {
    return CANALES.filter((c) => canales.includes(c.value)).map((c) => ({
        canal: c.value, pieza: c.pieza, etiqueta: ETIQUETAS_PIEZA[c.pieza], variantes: c.variantes,
    }));
}

/** Hoy (YYYY-MM-DD) en America/Mexico_City. */
export function hoyMexico(ahora = new Date()) {
    return new Intl.DateTimeFormat("en-CA", { timeZone: "America/Mexico_City", year: "numeric", month: "2-digit", day: "2-digit" })
        .format(ahora);
}

/** Días entre dos fechas YYYY-MM-DD (fin − inicio). null si alguna falta. */
export function diasEntre(inicio, fin) {
    const a = /^(\d{4})-(\d{2})-(\d{2})$/.exec(inicio || "");
    const b = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fin || "");
    if (!a || !b) return null;
    const ta = Date.UTC(+a[1], +a[2] - 1, +a[3]);
    const tb = Date.UTC(+b[1], +b[2] - 1, +b[3]);
    return Math.round((tb - ta) / 86400000);
}

/** ¿El texto tiene emojis? (no se permiten en el texto en pantalla de un video). */
export const tieneEmoji = (t) => /\p{Extended_Pictographic}/u.test(String(t || ""));

/** Cuenta palabras. */
export const palabras = (t) => String(t || "").trim().split(/\s+/).filter(Boolean).length;

/** Escena vacía para el editor. */
export function escenaNueva(tipo = "texto") {
    return {
        numero: 0, duracion_s: 4, toma: "", visual: { tipo, categoria_broll: null },
        texto_pantalla: "", voz_en_off: "", musica_sonido: "",
    };
}

/** Copia profunda segura de contenido JSON. */
export const clonar = (v) => (v === null || v === undefined ? v : JSON.parse(JSON.stringify(v)));

/** Suma de duraciones (entera). */
export const duracionTotal = (escenas = []) => escenas.reduce((s, e) => s + (Number.parseInt(e?.duracion_s, 10) || 0), 0);

/**
 * Revisión en vivo de un guion (mismas reglas que ValidadorPiezas, §2.5.5).
 * Devuelve { errores: [texto], porEscena: { idx: [texto] }, avisos: { idx: [texto] } }.
 * Los avisos no impiden guardar (p. ej. voz en off larga para la duración).
 */
export function revisarGuion(contenido, pieza = "reel_guion") {
    const lim = LIMITES_GUION[pieza] || LIMITES_GUION.reel_guion;
    const errores = [];
    const porEscena = {};
    const avisos = {};
    const escenas = Array.isArray(contenido?.escenas) ? contenido.escenas : [];
    const agrega = (mapa, i, t) => { (mapa[i] = mapa[i] || []).push(t); };

    if (!String(contenido?.titulo || "").trim()) errores.push("Falta el título del guion.");
    if (escenas.length < lim.minEscenas || escenas.length > lim.maxEscenas) {
        errores.push(`Un ${lim.nombre} lleva de ${lim.minEscenas} a ${lim.maxEscenas} escenas (hay ${escenas.length}).`);
    }
    const total = duracionTotal(escenas);
    if (total < lim.minTotal || total > lim.maxTotal) {
        errores.push(`La duración total de un ${lim.nombre} va de ${lim.minTotal} a ${lim.maxTotal} s (suma ${total} s).`);
    }
    if (escenas.length && escenas[escenas.length - 1]?.visual?.tipo !== "cta") {
        errores.push("La última escena debe ser de cierre (llamada a la acción).");
    }
    const cta = String(contenido?.cta_final?.texto || "");
    if (!cta.trim()) errores.push("Falta el texto de la llamada a la acción final.");
    else if (cta.length > LIMITES_ESCENA.ctaFinal) errores.push(`La llamada a la acción final pasa de ${LIMITES_ESCENA.ctaFinal} caracteres.`);

    escenas.forEach((e, i) => {
        const d = Number(e?.duracion_s);
        if (!Number.isInteger(d) || d < LIMITES_ESCENA.minDuracion || d > LIMITES_ESCENA.maxDuracion) {
            agrega(porEscena, i, `Duración de ${LIMITES_ESCENA.minDuracion} a ${LIMITES_ESCENA.maxDuracion} s, en segundos enteros.`);
        }
        const tipo = e?.visual?.tipo;
        if (!TIPOS_VISUAL.some((t) => t.value === tipo)) agrega(porEscena, i, "Elige el tipo de visual.");
        if (tipo === "broll" && !e?.visual?.categoria_broll) agrega(porEscena, i, "Elige la categoría del video de apoyo.");
        const tope = topeTextoPantalla(tipo);
        const texto = String(e?.texto_pantalla || "");
        if (tope !== null && texto.length > tope) agrega(porEscena, i, `El texto en pantalla pasa de ${tope} caracteres.`);
        if (tipo !== "logo" && tieneEmoji(texto)) agrega(porEscena, i, "El texto en pantalla no puede llevar emojis.");
        if (/\{\{|\}\}/.test(texto + (e?.voz_en_off || "") + (e?.toma || ""))) agrega(porEscena, i, "Quita las llaves {{ }} del texto.");
        if (String(e?.toma || "").length > LIMITES_ESCENA.toma) agrega(porEscena, i, `La toma pasa de ${LIMITES_ESCENA.toma} caracteres.`);
        if (String(e?.voz_en_off || "").length > LIMITES_ESCENA.vozEnOff) agrega(porEscena, i, `La voz en off pasa de ${LIMITES_ESCENA.vozEnOff} caracteres.`);
        if (String(e?.musica_sonido || "").length > LIMITES_ESCENA.musicaSonido) agrega(porEscena, i, `Música/sonido pasa de ${LIMITES_ESCENA.musicaSonido} caracteres.`);
        if (Number.isFinite(d) && d > 0 && palabras(e?.voz_en_off) > d * LIMITES_ESCENA.palabrasPorSegundo) {
            agrega(avisos, i, `La voz en off es larga para ${d} s: puede no alcanzar a decirse completa.`);
        }
    });
    if (Object.keys(porEscena).length) errores.push("Revisa las escenas marcadas.");
    return { errores, porEscena, avisos, total };
}

/** "#Fibra" o "Fibra" → "#Fibra". */
export const conGato = (h) => {
    const t = String(h || "").trim().replace(/\s+/g, "");
    return t ? (t.startsWith("#") ? t : `#${t}`) : "";
};

/** Copia texto al portapapeles (con respaldo para http sin clipboard API). Devuelve true si se copió. */
export async function copiarTexto(texto) {
    const t = String(texto ?? "");
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(t);
            return true;
        }
    } catch (e) { /* cae al respaldo */ }
    try {
        const area = document.createElement("textarea");
        area.value = t;
        area.setAttribute("readonly", "");
        area.style.position = "fixed";
        area.style.opacity = "0";
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand("copy");
        document.body.removeChild(area);
        return ok;
    } catch (e) {
        return false;
    }
}

export default {
    OBJETIVOS, CANALES, ETIQUETAS_PIEZA, TIPOS_PIEZA, ESTADOS_CAMPANA, TRANSICIONES, ESTADOS_GENERACION, ESTADOS_PIEZA,
    ESTADOS_VIDEO, AVISO_DEMORADO, AVISO_WHATSAPP, TIPOS_VISUAL, LIMITES_GUION, LIMITES_ESCENA, VOCES, MUSICAS,
    LIMITES_COPY, LIMITES_ANUNCIO, BOTONES_ANUNCIO, FORMATOS_ANUNCIO, USOS_WHATSAPP, LIMITE_WHATSAPP, LIMITE_INDICACIONES,
    LIMITES_CAMPANA, RE_PRECIO, RE_PRESUPUESTO,
};
