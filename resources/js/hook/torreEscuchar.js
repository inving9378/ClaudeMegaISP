import { ref } from 'vue';

/**
 * Lógica compartida de 🔊 Escuchar para las tarjetas de la Torre de Control
 * (Integración + bandeja de Panorama). Fuente ÚNICA para no divergir.
 *
 * Narra `title + resumen` (el resumen corto: reporte_coloquial → descripción ~40
 * palabras, provisto por el backend), con la voz guardada por el administrador
 * (#424) → es-MX → cualquier es-* → default del navegador. Segundo clic = detener.
 *
 * `vozTts` es un ref que el componente carga desde su payload (voz_tts). Si el
 * componente no lo setea, cae a la selección automática (es-MX).
 */
export function useEscuchar() {
    const hablando = ref(null);   // id del item que se está narrando (null = ninguno)
    const voces = ref([]);        // voces es-* disponibles en el navegador
    const vozTts = ref(null);     // nombre de la voz guardada por el admin (null = automática)
    const rateTts = ref(1.0);     // velocidad guardada (#424); rango [0.5, 2.0], default 1.0

    // getVoices() carga async en algunos navegadores → se re-llama en onvoiceschanged.
    function cargarVoces() {
        const synth = window.speechSynthesis;
        if (!synth) return;
        voces.value = synth.getVoices().filter((v) => v.lang && v.lang.toLowerCase().startsWith('es'));
    }

    // Voz a usar: la guardada por el admin → es-MX → cualquier es-* → default.
    function seleccionarVoz() {
        if (!voces.value.length) return null;
        if (vozTts.value) {
            const guardada = voces.value.find((v) => v.name === vozTts.value);
            if (guardada) return guardada;
        }
        const mx = voces.value.find((v) => v.lang.toLowerCase().startsWith('es-mx'));
        return mx || voces.value[0];
    }

    function leer(item) {
        const synth = window.speechSynthesis;
        if (!synth) return;
        if (hablando.value === item.id) { synth.cancel(); hablando.value = null; return; }
        synth.cancel();   // corta cualquier locución en curso (incluida la de OTRA tarjeta)
        if (!voces.value.length) cargarVoces();

        // Texto a narrar: SIEMPRE el de ESTE item (el que se pasó, no un estado compartido).
        // Evitar el título dos veces: el resumen (reporte_coloquial) suele venir con el título
        // ya antepuesto desde el backend (RoadmapItem::generarReporteColoquial = title + '. ' +
        // description). Si el resumen ya empieza con el título → narrar SOLO el resumen; si no,
        // título + resumen. Nunca el título dos veces.
        const norm = (s) => (s == null ? '' : String(s)).toLowerCase().replace(/\s+/g, ' ').trim();
        const title = (item.title == null ? '' : String(item.title)).trim();
        const resumen = (item.resumen == null ? '' : String(item.resumen)).trim();
        const txt = (resumen && title && norm(resumen).startsWith(norm(title)))
            ? resumen
            : [title, resumen].filter(Boolean).join('. ');
        if (!txt) return;

        const u = new SpeechSynthesisUtterance(txt);
        u.lang = 'es-MX';
        const voz = seleccionarVoz();
        if (voz) u.voice = voz;
        u.rate = Math.max(0.5, Math.min(2.0, Number(rateTts.value) || 1.0));   // #424: velocidad guardada
        // Guard por id: si esta locución fue cancelada por una más nueva, su onend NO debe
        // limpiar el estado de la nueva (evita apagar el botón del item que sí está sonando).
        u.onend = () => { if (hablando.value === item.id) hablando.value = null; };
        hablando.value = item.id;

        // Chromium: cancel() no siempre purga al vuelo una locución en curso antes del speak()
        // inmediato → la locución vieja (de otra tarjeta) se cuela y se narra el item EQUIVOCADO.
        // Si algo estaba sonando, encolar el speak tras un tick deja que cancel() termine →
        // gana SIEMPRE el item recién pedido.
        const speakNow = () => window.speechSynthesis.speak(u);
        if (synth.speaking || synth.pending) {
            setTimeout(speakNow, 60);
        } else {
            speakNow();
        }
    }

    // Llamar en onMounted: carga inicial + suscripción a onvoiceschanged.
    function initVoces() {
        if (!window.speechSynthesis) return;
        cargarVoces();
        window.speechSynthesis.onvoiceschanged = cargarVoces;
    }

    return { hablando, voces, vozTts, rateTts, cargarVoces, seleccionarVoz, leer, initVoces };
}

/**
 * 🔎 Ver más: abre en pestaña nueva la pantalla del módulo que tocó el item (para
 * revisar/revertir). Fallback si el módulo es null/no mapeable → la página de detalle
 * del item (#426), que sí tiene contexto (antes caía a /releases sin relación con el item).
 */
export function verMas(item) {
    window.open(item.modulo_url || `/roadmap/item/${item.id}`, '_blank', 'noopener');
}
