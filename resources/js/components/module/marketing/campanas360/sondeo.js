/**
 * Campañas 360 — sondeo del progreso (contrato §2.9).
 *
 * Llama `tarea()` cada `intervalo` ms mientras `tarea` devuelva `true` (= sigue habiendo algo generando,
 * pendiente, en cola o produciendo). Se detiene:
 *   - cuando `tarea` devuelve `false`;
 *   - al ocultar la pestaña (visibilitychange) — al volver a verla hace una consulta y sigue si hace falta;
 *   - a los `tope` ms desde que arrancó (30 min por defecto): entonces llama `alAgotarse()`;
 *   - con `detener()` (al desmontar la pantalla).
 * Nunca hay dos consultas encimadas: la siguiente se programa al terminar la anterior.
 */
export function crearSondeo({ tarea, intervalo = 5000, tope = 30 * 60 * 1000, alAgotarse = null } = {}) {
    let temporizador = null;
    let activo = false;
    let enCurso = false;
    let inicio = 0;

    const limpiar = () => {
        if (temporizador) clearTimeout(temporizador);
        temporizador = null;
    };

    const programar = () => {
        limpiar();
        if (!activo || document.hidden) return;
        if (Date.now() - inicio >= tope) {
            activo = false;
            if (typeof alAgotarse === "function") alAgotarse();
            return;
        }
        temporizador = setTimeout(paso, intervalo);
    };

    async function paso() {
        temporizador = null;
        if (!activo || enCurso || document.hidden) return;
        enCurso = true;
        let seguir = false;
        try {
            seguir = (await tarea()) === true;
        } catch (e) {
            seguir = true; // un tropiezo de red no detiene el sondeo; el tope de 30 min lo acota
        } finally {
            enCurso = false;
        }
        if (!seguir) {
            activo = false;
            return;
        }
        programar();
    }

    const alCambiarVisibilidad = () => {
        if (!activo) return;
        if (document.hidden) {
            limpiar();
        } else {
            paso();
        }
    };

    return {
        /** Arranca (o reinicia el tope) si no estaba corriendo. */
        iniciar() {
            if (activo) return;
            activo = true;
            inicio = Date.now();
            document.addEventListener("visibilitychange", alCambiarVisibilidad);
            programar();
        },
        detener() {
            activo = false;
            limpiar();
            document.removeEventListener("visibilitychange", alCambiarVisibilidad);
        },
        get activo() {
            return activo;
        },
    };
}

/** ¿Hay algo en proceso en una respuesta de progreso o ficha (§2.4.8)? */
export function hayAlgoEnProceso(datos) {
    if (!datos) return false;
    if (datos.estrategia_estado === "generando") return true;
    const piezas = Array.isArray(datos.piezas)
        ? datos.piezas
        : Object.values(datos.piezas || {}).flat();
    if (piezas.some((p) => p && (p.estado === "generando" || p.estado === "pendiente"))) return true;
    const videos = Array.isArray(datos.videos) ? datos.videos : [];
    return videos.some((v) => v && (v.estado === "en_cola" || v.estado === "produciendo"));
}

export default { crearSondeo, hayAlgoEnProceso };
