<template>
    <q-card flat bordered class="detr" :dark="darkMode">
        <q-card-section class="detr-encabezado">
            <div class="text-subtitle1 text-weight-bold">Estado del servicio en tiempo real</div>
            <div class="detr-derecha">
                <span class="detr-etiqueta">Cliente dedicado · monitoreo cada {{ estado?.intervalo_seg ?? 60 }} s</span>
                <q-btn
                    round
                    flat
                    icon="refresh"
                    class="detr-refrescar"
                    aria-label="Actualizar estado"
                    :loading="cargando"
                    @click="cargar"
                >
                    <q-tooltip>Actualizar estado</q-tooltip>
                </q-btn>
            </div>
        </q-card-section>
        <q-separator :dark="darkMode" />

        <q-banner v-if="estado && estado.automatica && !soloConsulta" dense class="detr-aviso">
            <template #avatar><q-icon name="warning" color="warning" /></template>
            Configuración automática, sin revisar: se tomó del servicio y nadie la ha confirmado.
            <template v-if="puedeEditar" #action>
                <q-btn flat no-caps dense color="primary" label="Revisar datos de red" @click="$emit('configurar')" />
            </template>
        </q-banner>

        <q-card-section v-if="error" class="text-negative">{{ error }}</q-card-section>

        <q-card-section v-else-if="estado" class="detr-cuerpo">
            <div class="detr-celdas">
                <div class="detr-celda">
                    <div class="detr-titulo">Estado</div>
                    <div class="detr-valor">
                        <span class="detr-punto" :class="`detr-${estado.estado}`" aria-hidden="true"></span>
                        {{ textoEstado(estado.estado) }}
                    </div>
                    <div v-if="estado.motivo && !estado.causa" class="detr-nota">{{ estado.motivo }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">IP monitoreada</div>
                    <div class="detr-valor" :class="{ 'detr-mono': ipMonitoreada.includes('.') }">{{ ipMonitoreada }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">Latencia</div>
                    <div class="detr-valor">{{ ms(estado.latencia_ms) }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">Pérdida</div>
                    <div class="detr-valor">{{ pct(estado.perdida_pct) }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">Tráfico bajada</div>
                    <div class="detr-valor detr-bajada">{{ mbps(estado.bajada_bps) }}</div>
                    <div v-if="estado.trafico_motivo" class="detr-nota">{{ estado.trafico_motivo }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">Tráfico subida</div>
                    <div class="detr-valor detr-subida">{{ mbps(estado.subida_bps) }}</div>
                </div>
                <div class="detr-celda">
                    <div class="detr-titulo">{{ estado.ultima_es_intento ? "Último intento" : "Última lectura" }}</div>
                    <div class="detr-valor">{{ haceCuanto }}</div>
                </div>
            </div>

            <div v-if="estado.causa" class="detr-causa" role="status">
                <q-icon name="info" size="20px" />
                <div>
                    <div class="text-weight-medium">{{ estado.causa.titulo }}</div>
                    <div v-if="estado.causa.detalle" class="detr-nota">{{ estado.causa.detalle }}</div>
                </div>
            </div>

            <div v-if="estado.ips.length > 1" class="detr-ips">
                <div v-for="ip in estado.ips" :key="ip.ip" class="detr-ip">
                    <span class="detr-ip-nombre">{{ ip.etiqueta || "Equipo" }}</span>
                    <span class="detr-mono">{{ ip.ip }}</span>
                    <span class="detr-ip-estado">
                        <span class="detr-punto" :class="`detr-${ip.estado}`" aria-hidden="true"></span>
                        {{ textoEstado(ip.estado) }}
                    </span>
                    <span>Latencia {{ ms(ip.latencia_ms) }}</span>
                    <span>Pérdida {{ pct(ip.perdida_pct) }}</span>
                </div>
            </div>
        </q-card-section>

        <q-card-section v-else class="detr-centro">
            <q-spinner size="28px" color="primary" />
        </q-card-section>
    </q-card>
</template>

<script>
import { computed, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

// #988 Fase 4: tarjeta «Estado del servicio en tiempo real» de un servicio dedicado. Lee las
// tablas nuevas; se actualiza sola cada 60 s. Ningún estado se dice solo con color.
const TEXTOS = {
    en_linea: "En línea",
    degradado: "Degradado",
    sin_respuesta: "Sin respuesta",
    sin_datos: "Sin datos",
    pausado: "Monitoreo pausado",
    revisar: "Revisar",
};

export default {
    name: "DedicadoEstadoTiempoReal",
    props: {
        servicioId: { type: Number, required: true },
        puedeEditar: { type: Boolean, default: false },
        soloConsulta: { type: Boolean, default: false },
    },
    emits: ["cargado", "configurar"],
    setup(props, { emit }) {
        const estado = ref(null);
        const cargando = ref(false);
        const error = ref(null);
        const ahora = ref(Date.now());

        const cargar = async () => {
            if (cargando.value) return;
            cargando.value = true;
            try {
                const { data } = await axios.get(`/cliente/statistics/dedicado/servicio/${props.servicioId}/estado`);
                estado.value = data;
                error.value = null;
                emit("cargado", data);
            } catch (e) {
                error.value = "No se pudo leer el estado del servicio.";
            } finally {
                cargando.value = false;
                ahora.value = Date.now();
            }
        };

        watch(() => props.servicioId, () => {
            estado.value = null;
            cargar();
        }, { immediate: true });

        const refresco = setInterval(cargar, 60000);
        const reloj = setInterval(() => (ahora.value = Date.now()), 1000);
        onUnmounted(() => {
            clearInterval(refresco);
            clearInterval(reloj);
        });

        const ipMonitoreada = computed(() => {
            const ips = estado.value?.ips?.length ? estado.value.ips.map((i) => i.ip) : estado.value?.ips_configuradas || [];
            if (!ips.length) return "—";
            return ips.length === 1 ? ips[0] : `${ips.length} IPs`;
        });

        const haceCuanto = computed(() => {
            if (!estado.value?.ultima_lectura) return "—";
            const s = Math.max(0, Math.round((ahora.value - new Date(estado.value.ultima_lectura).getTime()) / 1000));
            if (s < 60) return `hace ${s} s`;
            if (s < 3600) return `hace ${Math.floor(s / 60)} min`;
            if (s < 86400) return `hace ${Math.floor(s / 3600)} h`;
            return new Date(estado.value.ultima_lectura).toLocaleString("es-MX");
        });

        const textoEstado = (e) => TEXTOS[e] || "Sin datos";
        const ms = (v) => (v === null || v === undefined ? "—" : `${Number(v).toFixed(2)} ms`);
        const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toLocaleString("es-MX", { maximumFractionDigits: 1 })} %`);
        const mbps = (bps) => (bps === null || bps === undefined ? "—" : `${(bps / 1e6).toFixed(1)} Mbps`);

        return { darkMode, estado, cargando, error, cargar, ipMonitoreada, haceCuanto, textoEstado, ms, pct, mbps };
    },
};
</script>

<style scoped>
.detr {
    border-radius: 10px;
    --detr-gris: #6b7280;
    --detr-verde: #16a34a;
    --detr-ambar: #d97706;
    --detr-rojo: #dc2626;
    --detr-azul: #1d4ed8;
    --detr-naranja: #c2410c;
    --detr-borde: rgba(0, 0, 0, 0.08);
}
.detr.q-dark {
    --detr-gris: #9ca3af;
    --detr-verde: #4ade80;
    --detr-ambar: #fbbf24;
    --detr-rojo: #f87171;
    --detr-azul: #60a5fa;
    --detr-naranja: #fb923c;
    --detr-borde: rgba(255, 255, 255, 0.12);
}
.detr-encabezado {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.detr-derecha {
    display: flex;
    align-items: center;
    gap: 8px;
}
.detr-etiqueta {
    font-size: 0.8rem;
    color: var(--detr-gris);
}
.detr-refrescar {
    width: 44px;
    height: 44px;
}
.detr-celdas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
}
.detr-celda {
    border: 1px solid var(--detr-borde);
    border-radius: 8px;
    padding: 10px 12px;
}
.detr-titulo {
    font-size: 0.7rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--detr-gris);
}
.detr-valor {
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}
.detr-nota {
    font-size: 0.75rem;
    color: var(--detr-gris);
    margin-top: 2px;
}
.detr-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.detr-bajada {
    color: var(--detr-azul);
}
.detr-subida {
    color: var(--detr-naranja);
}
.detr-punto {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    flex: none;
}
.detr-en_linea {
    background: var(--detr-verde);
}
.detr-degradado {
    background: var(--detr-ambar);
}
.detr-sin_respuesta {
    background: var(--detr-rojo);
}
.detr-sin_datos {
    background: var(--detr-gris);
}
.detr-ips {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.detr-ip {
    display: grid;
    grid-template-columns: minmax(140px, 1.2fr) minmax(120px, 1fr) minmax(120px, 1fr) minmax(110px, 1fr) minmax(100px, 1fr);
    gap: 8px;
    align-items: center;
    font-size: 0.875rem;
    padding: 6px 10px;
    border: 1px solid var(--detr-borde);
    border-radius: 6px;
}
.detr-ip-nombre {
    font-weight: 600;
}
.detr-ip-estado {
    display: flex;
    align-items: center;
    gap: 6px;
}
.detr-centro {
    display: flex;
    justify-content: center;
    padding: 24px;
}
@media (max-width: 700px) {
    .detr-ip {
        grid-template-columns: 1fr 1fr;
    }
}
.detr-pausado {
    background: #6b7280;
}
.detr-revisar {
    background: #d97706;
}
.detr-aviso {
    background: rgba(245, 158, 11, 0.12);
}
.detr-causa {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(107, 114, 128, 0.12);
}
.detr-valor.detr-mono {
    font-size: 1rem;
    overflow-wrap: anywhere;
}
</style>
