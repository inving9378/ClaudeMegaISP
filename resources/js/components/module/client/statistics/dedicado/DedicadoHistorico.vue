<template>
    <div class="dh">
        <q-card flat bordered class="dh-tarjeta" :dark="darkMode">
            <q-card-section class="dh-barra">
                <div>
                    <h3 class="dh-titulo">Histórico del servicio</h3>
                    <div class="dh-subtitulo">{{ subtituloTexto }}</div>
                </div>
                <div class="dh-periodos" role="group" aria-label="Periodo del histórico">
                    <q-btn
                        v-for="p in PERIODOS"
                        :key="p.valor"
                        no-caps
                        :unelevated="periodo === p.valor"
                        :outline="periodo !== p.valor"
                        :color="periodo === p.valor ? 'primary' : darkMode ? 'grey-5' : 'grey-8'"
                        :label="p.texto"
                        :aria-pressed="periodo === p.valor ? 'true' : 'false'"
                        class="dh-periodo"
                        @click="periodo = p.valor"
                    />
                </div>
            </q-card-section>
            <q-linear-progress v-if="cargando" indeterminate color="primary" size="2px" />
        </q-card>

        <q-banner v-if="error" dense rounded class="dh-error">{{ error }}</q-banner>

        <template v-if="datos">
            <div class="dh-indicadores">
                <q-card flat bordered class="dh-tarjeta dh-indicador" :dark="darkMode">
                    <div class="dh-etiqueta">Disponibilidad</div>
                    <div class="dh-valor">{{ disponibilidad }}</div>
                    <div class="dh-sub">
                        {{ ind.minutos_sin_respuesta.toLocaleString("es-MX") }} min sin respuesta<template v-if="ind.minutos_degradado"> · {{ ind.minutos_degradado.toLocaleString("es-MX") }} min degradado</template><template v-if="ind.minutos_pausado"> · no cuenta {{ ind.minutos_pausado.toLocaleString("es-MX") }} min en pausa</template>
                    </div>
                </q-card>
                <q-card flat bordered class="dh-tarjeta dh-indicador" :dark="darkMode">
                    <div class="dh-etiqueta">Latencia promedio</div>
                    <div class="dh-valor">{{ ms(ind.latencia_prom_ms) }}</div>
                    <div class="dh-sub">Máxima {{ ms(ind.latencia_max_ms) }}</div>
                </q-card>
                <q-card flat bordered class="dh-tarjeta dh-indicador" :dark="darkMode">
                    <div class="dh-etiqueta">Pérdida de paquetes promedio</div>
                    <div class="dh-valor">{{ pct(ind.perdida_prom_pct, 2) }}</div>
                    <div class="dh-sub">Máxima {{ pct(ind.perdida_max_pct) }}</div>
                </q-card>
                <q-card flat bordered class="dh-tarjeta dh-indicador" :dark="darkMode">
                    <div class="dh-etiqueta">Pico de tráfico (bajada / subida)</div>
                    <div class="dh-valor">
                        <span class="dh-bajada">{{ numeroMbps(ind.pico_bajada_bps) }}</span>
                        <span class="dh-separador">/</span>
                        <span class="dh-subida">{{ numeroMbps(ind.pico_subida_bps) }}</span>
                        <span class="dh-unidad">Mbps</span>
                    </div>
                    <div class="dh-sub">Total {{ gb(ind.total_bajada_bytes) }} GB ↓ · {{ gb(ind.total_subida_bytes) }} GB ↑</div>
                </q-card>
            </div>

            <DedicadoGraficaLatencia :datos="datos" />
            <DedicadoGraficaTrafico :datos="datos" />

            <div class="dh-pie">
                <DedicadoEventos :eventos="datos.eventos" :zona="datos.zona" />
                <DedicadoOrigen
                    :origen="datos.origen"
                    :simulado="datos.simulado"
                    :puede-editar="puedeEditar"
                    @configurar="$emit('configurar')"
                />
            </div>
        </template>

        <q-card v-else-if="!error" flat bordered class="dh-tarjeta dh-centro" :dark="darkMode">
            <q-spinner size="28px" color="primary" />
        </q-card>
    </div>
</template>

<script>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { darkMode } from "../../../../../hook/appConfig.js";
import DedicadoGraficaLatencia from "./DedicadoGraficaLatencia.vue";
import DedicadoGraficaTrafico from "./DedicadoGraficaTrafico.vue";
import DedicadoEventos from "./DedicadoEventos.vue";
import DedicadoOrigen from "./DedicadoOrigen.vue";
import { PERIODOS, gb, ms, pct, subtitulo } from "./formato.js";

// #988 Fase 4, secciones 4 a 9: «Histórico del servicio» de un servicio dedicado. Una petición por
// periodo, ya agregada en el servidor (tablas nuevas por servicio, nunca ping_statistics).
export default {
    name: "DedicadoHistorico",
    components: { DedicadoGraficaLatencia, DedicadoGraficaTrafico, DedicadoEventos, DedicadoOrigen },
    props: {
        servicioId: { type: Number, required: true },
        puedeEditar: { type: Boolean, default: false },
    },
    emits: ["configurar"],
    setup(props) {
        const periodo = ref("hoy");
        const datos = ref(null);
        const cargando = ref(false);
        const error = ref(null);
        let pedido = 0;

        const cargar = async () => {
            const este = ++pedido;
            cargando.value = true;
            try {
                const { data } = await axios.get(`/cliente/statistics/dedicado/servicio/${props.servicioId}/historico`, {
                    params: { periodo: periodo.value },
                });
                if (este !== pedido) return;
                datos.value = data;
                error.value = null;
            } catch (e) {
                if (este !== pedido) return;
                error.value = "No se pudo leer el histórico del servicio.";
            } finally {
                if (este === pedido) cargando.value = false;
            }
        };

        watch(() => props.servicioId, () => {
            datos.value = null;
            cargar();
        });
        watch(periodo, cargar);
        cargar();

        const ind = computed(() => datos.value?.indicadores || {});
        const disponibilidad = computed(() =>
            ind.value.disponibilidad_pct === null || ind.value.disponibilidad_pct === undefined
                ? "—"
                : `${Number(ind.value.disponibilidad_pct).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`
        );
        const numeroMbps = (bps) =>
            bps === null || bps === undefined ? "—" : (bps / 1e6).toLocaleString("es-MX", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        const subtituloTexto = computed(() => (datos.value ? subtitulo(datos.value) : PERIODOS.find((p) => p.valor === periodo.value)?.texto));

        return { darkMode, PERIODOS, periodo, datos, cargando, error, ind, disponibilidad, numeroMbps, subtituloTexto, ms, pct, gb };
    },
};
</script>

<style scoped>
.dh {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 12px;
    --dh-azul: #1d4ed8;
    --dh-naranja: #c2410c;
}
.dh-tarjeta {
    border-radius: 10px;
}
.dh-tarjeta.q-dark {
    --dh-azul: #60a5fa;
    --dh-naranja: #fb923c;
}
.dh-barra {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    flex-wrap: wrap;
}
.dh-titulo {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.4;
    margin: 0;
}
.dh-subtitulo {
    font-size: 0.85rem;
    opacity: 0.8;
}
.dh-periodos {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.dh-periodo {
    min-height: 44px;
    min-width: 64px;
}
.dh-error {
    background: rgba(220, 38, 38, 0.1);
    border: 1px solid rgba(220, 38, 38, 0.35);
}
.dh-indicadores {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.dh-indicador {
    padding: 12px 14px;
}
.dh-etiqueta {
    font-size: 0.7rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-weight: 600;
    opacity: 0.75;
}
.dh-valor {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.3;
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex-wrap: wrap;
}
.dh-unidad {
    font-size: 0.9rem;
    font-weight: 500;
}
.dh-separador {
    opacity: 0.6;
}
.dh-bajada {
    color: var(--dh-azul);
}
.dh-subida {
    color: var(--dh-naranja);
}
.dh-sub {
    font-size: 0.8rem;
    opacity: 0.8;
}
.dh-pie {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
    gap: 12px;
    align-items: start;
}
.dh-centro {
    display: flex;
    justify-content: center;
    padding: 24px;
}
@media (max-width: 1100px) {
    .dh-pie {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
