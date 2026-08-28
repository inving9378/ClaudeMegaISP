<template>
    <div class="jv-wrap">
        <!-- LA BURBUJA. El icono NO cambia con el estado: el estado va en el anillo.
             Cambiar el dibujo según el humor obliga a aprenderse cuatro caras; un anillo de color
             sobre una cara constante se lee de reojo, que es como se mira una esquina. -->
        <button class="jv-burbuja" :class="'jv-' + estado" type="button"
                :title="titulo" @click="abierto = !abierto">
            <span class="jv-anillo" :class="'jv-anillo-' + estado"></span>

            <img v-if="iconoUrl" :src="iconoUrl" alt="JARVIS" class="jv-img">
            <i v-else class="fas fa-microchip jv-fallback"></i>

            <!-- El punto sólo aparece cuando hay algo que decir. Un indicador permanentemente
                 encendido deja de mirarse a los tres días. -->
            <span v-if="estado !== 'normal'" class="jv-punto" :class="'jv-punto-' + estado">
                <span v-if="estado === 'pregunta' && preguntas > 0" class="jv-n">{{ preguntas > 99 ? '99+' : preguntas }}</span>
                <i v-else-if="estado === 'sin_medir'" class="fas fa-heart-crack"></i>
                <i v-else class="fas fa-exclamation"></i>
            </span>
        </button>

        <!-- Panel corto: qué está pasando y a dónde ir. Todavía NO conversa — y lo dice. -->
        <transition name="jv-slide">
            <div v-if="abierto" class="jv-panel">
                <div class="jv-head">
                    <div class="jv-head-l">
                        <img v-if="iconoUrl" :src="iconoUrl" alt="" class="jv-head-img">
                        <i v-else class="fas fa-microchip jv-fallback"></i>
                        <div>
                            <div class="jv-nombre">JARVIS</div>
                            <div class="jv-sub" :class="'jv-txt-' + estado">{{ texto }}</div>
                        </div>
                    </div>
                    <button class="jv-x" @click="abierto = false" title="Cerrar">✕</button>
                </div>

                <div class="jv-cuerpo">
                    <p class="jv-medido">
                        <template v-if="medidoHace !== null && medidoHace !== undefined">
                            Medido hace <b>{{ humano(medidoHace) }}</b>.
                        </template>
                        <template v-else>
                            <b>Sin lectura del medidor.</b> No sé desde cuándo — que es peor que saber que hace mucho.
                        </template>
                    </p>

                    <p v-if="estado === 'sin_medir'" class="jv-alerta">
                        Llevo rato sin medir nada. Mientras eso siga así, <b>no me creas ningún número</b>:
                        lo que te diría sería de memoria, y de memoria no hablo.
                    </p>
                    <p v-else-if="estado === 'alerta'" class="jv-alerta">
                        No puedo leer la base. Estoy en <b>modo mínimo</b>: sólo veo lo que hay en disco.
                    </p>

                    <a class="jv-btn" href="/releases?tab=panorama" data-spa-skip>Ver la Torre</a>
                    <a class="jv-btn jv-btn-sec" href="/releases?tab=configuracion" data-spa-skip>Mi configuración</a>

                    <!-- Honestidad sobre lo que TODAVÍA no hace. Un asistente que aparenta poder
                         conversar y no contesta es peor que uno que dice qué le falta. -->
                    <p class="jv-pendiente">
                        Todavía no converso por aquí: eso llega con el hilo persistente. Por ahora
                        soy la cara del motor que mide — y lo que ves arriba es el estado real, no un
                        adorno.
                    </p>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";

/**
 * LA CARA DE JARVIS — presencia y estado, en cualquier pantalla del sistema.
 *
 * Un solo asistente: esto es la cara de lo que en el código es `JarvisService`, el mismo motor que
 * mide. No tiene cerebro propio ni contesta desde otro sitio — el widget que hacía eso quedó
 * apagado justamente porque dos asistentes con el mismo nombre se contradicen.
 *
 * EL ANILLO ES EL INTERRUPTOR DE HOMBRE MUERTO. Si el medidor deja de latir, la burbuja lo dice
 * sola, en la esquina, sin que nadie entre a buscarlo. Un asistente callado y uno muerto se ven
 * idénticos: el anillo los separa, y es la señal más barata y más útil de todas.
 */
export default {
    name: "JarvisBurbuja",
    props: {
        url: { type: String, default: "" },
    },
    setup() {
        const abierto = ref(false);
        const estado = ref("normal");
        const texto = ref("");
        const preguntas = ref(0);
        const medidoHace = ref(null);
        const iconoUrl = ref(null);

        // Vista previa EN SITIO: el selector de la pestaña Configuración vive en OTRA instancia de
        // Vue, así que el canal entre los dos es un evento del navegador. Al pasar el ratón por un
        // icono del selector, esta burbuja —la real, la de la esquina— cambia; al salir, vuelve.
        let iconoGuardado = null;

        function aplicarPreview(ev) {
            const url = ev?.detail?.url ?? null;
            iconoUrl.value = url || iconoGuardado;
        }
        function aplicarCambio(ev) {
            iconoGuardado = ev?.detail?.url ?? null;
            iconoUrl.value = iconoGuardado;
        }

        let timer = null;

        async function medir() {
            try {
                const { data } = await axios.get("/api/jarvis/estado");
                estado.value = data.estado || "normal";
                texto.value = data.texto || "";
                preguntas.value = data.preguntas || 0;
                medidoHace.value = data.medido_hace;
                iconoGuardado = data?.identidad?.urls?.[96] || data?.identidad?.urls?.[48] || null;
                iconoUrl.value = iconoGuardado;
            } catch (e) {
                // Si ni el endpoint de estado contesta, eso ES el estado. No se finge normalidad.
                estado.value = "alerta";
                texto.value = "No puedo consultar mi propio estado.";
                medidoHace.value = null;
            }
        }

        const titulo = computed(() => `JARVIS — ${texto.value || "midiendo…"}`);

        function humano(seg) {
            if (seg === null || seg === undefined) return "—";
            if (seg < 120) return `${seg} s`;
            if (seg < 7200) return `${Math.round(seg / 60)} min`;
            return `${Math.round(seg / 3600)} h`;
        }

        onMounted(() => {
            medir();
            // Cada 60 s: el snapshot del medidor se refresca cada minuto, así que sondear más
            // seguido sólo gasta. Y menos seguido volvería lenta justo la señal de «dejó de latir».
            timer = setInterval(medir, 60000);
            window.addEventListener("jarvis:preview-icono", aplicarPreview);
            window.addEventListener("jarvis:icono-cambiado", aplicarCambio);
        });

        onBeforeUnmount(() => {
            if (timer) clearInterval(timer);
            window.removeEventListener("jarvis:preview-icono", aplicarPreview);
            window.removeEventListener("jarvis:icono-cambiado", aplicarCambio);
        });

        return { abierto, estado, texto, preguntas, medidoHace, iconoUrl, titulo, humano };
    },
};
</script>

<style scoped>
.jv-wrap { position: fixed; right: 24px; bottom: 24px; z-index: 1040; }

.jv-burbuja {
    position: relative; width: 56px; height: 56px; border-radius: 50%;
    border: none; padding: 0; cursor: pointer;
    background: #0f172a; color: #e2e8f0;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .28);
    display: flex; align-items: center; justify-content: center;
    transition: transform .15s ease;
}
.jv-burbuja:hover { transform: translateY(-2px); }
.jv-img { width: 40px; height: 40px; object-fit: contain; border-radius: 50%; }
.jv-fallback { font-size: 22px; }

/* El anillo: el estado, sin cambiar la cara. */
.jv-anillo {
    position: absolute; inset: -3px; border-radius: 50%;
    border: 3px solid transparent; pointer-events: none;
}
.jv-anillo-normal    { border-color: rgba(45, 212, 191, .55); }
.jv-anillo-pregunta  { border-color: #f59e0b; }
.jv-anillo-alerta    { border-color: #dc2626; }
.jv-anillo-sin_medir { border-color: #94a3b8; border-style: dashed; animation: jv-latido 2.4s ease-in-out infinite; }

/* «Sin medir» respira despacio a propósito: no es una alarma, es un pulso que se apagó. */
@keyframes jv-latido { 0%, 100% { opacity: .35; } 50% { opacity: 1; } }

.jv-punto {
    position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px;
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: #fff; padding: 0 5px;
}
.jv-punto-pregunta  { background: #f59e0b; color: #1f2937; }
.jv-punto-alerta    { background: #dc2626; }
.jv-punto-sin_medir { background: #64748b; }

.jv-panel {
    position: absolute; right: 0; bottom: 68px; width: 320px;
    background: #fff; color: #0f172a; border-radius: 14px;
    box-shadow: 0 12px 34px rgba(0, 0, 0, .25); overflow: hidden;
}
.jv-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 12px 14px; background: #0f172a; color: #e2e8f0; }
.jv-head-l { display: flex; align-items: center; gap: 10px; }
.jv-head-img { width: 34px; height: 34px; object-fit: contain; border-radius: 50%; }
.jv-nombre { font-weight: 700; letter-spacing: .04em; }
.jv-sub { font-size: 11.5px; opacity: .85; }
.jv-txt-sin_medir { color: #cbd5e1; }
.jv-txt-alerta { color: #fca5a5; }
.jv-x { background: transparent; border: none; color: inherit; cursor: pointer; font-size: 14px; }
.jv-cuerpo { padding: 14px; font-size: 12.5px; line-height: 1.55; }
.jv-medido { margin: 0 0 8px; }
.jv-alerta { background: rgba(220, 38, 38, .08); border: 1px solid rgba(220, 38, 38, .3); border-radius: 8px; padding: 8px 10px; margin: 0 0 10px; }
.jv-btn { display: block; text-align: center; padding: 7px 10px; border-radius: 8px; background: #0f172a; color: #fff; text-decoration: none; margin-bottom: 6px; font-weight: 600; }
.jv-btn-sec { background: transparent; color: #0f172a; border: 1px solid #cbd5e1; }
.jv-pendiente { margin: 10px 0 0; color: #64748b; font-size: 11.5px; }

.jv-slide-enter-active, .jv-slide-leave-active { transition: opacity .16s ease, transform .16s ease; }
.jv-slide-enter-from, .jv-slide-leave-to { opacity: 0; transform: translateY(8px); }
</style>
