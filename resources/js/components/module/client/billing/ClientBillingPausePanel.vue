<template>
    <div class="pausa-panel">
        <div class="pausa-panel-titulo">PAUSA DE FACTURACIÓN</div>

        <div v-if="pausaViva" class="pausa-banner">
            <div class="pausa-banner-titulo">
                {{ pausaViva.estado === 'en_curso' ? `Facturación en pausa hasta el ${formatFecha(pausaViva.fecha_fin)}` : 'Pausa programada' }}
            </div>
            <div class="pausa-banner-detalle">
                Servicio {{ pausaViva.estado === 'esperando_pago' ? 'se suspenderá al iniciar' : 'suspendido' }} ·
                {{ pausaViva.estado === 'en_curso' ? `Reanuda automáticamente el ${formatFecha(diaSiguiente(pausaViva.fecha_fin))}` : `Inicia el ${formatFecha(pausaViva.fecha_inicio)}` }}
            </div>
        </div>
        <div v-else class="pausa-estado-libre">
            <span class="pausa-badge" :class="elegibilidad?.elegible ? 'pausa-badge-ok' : 'pausa-badge-bloqueado'">
                {{ elegibilidad?.elegible ? 'Disponible' : 'No disponible' }}
            </span>
            <span class="pausa-estado-texto">
                {{ elegibilidad?.elegible
                    ? `Hasta ${elegibilidad.maxMesesSinCuota} meses sin cuota, o hasta ${elegibilidad.maxMesesConCuota} con cuota de conservación ($99/mes).`
                    : elegibilidad?.motivo }}
            </span>
        </div>

        <div class="pausa-metricas">
            <div class="pausa-metrica-card">
                <div class="pausa-metrica-label">Última pausa</div>
                <div class="pausa-metrica-valor">{{ ultimaPausaTexto }}</div>
            </div>
            <div class="pausa-metrica-card">
                <div class="pausa-metrica-label">Pausas en 12 meses</div>
                <div class="pausa-metrica-valor">{{ elegibilidad?.pausasEn12Meses ?? 0 }} de 2</div>
            </div>
            <div class="pausa-metrica-card">
                <div class="pausa-metrica-label">Meses pausados (12m)</div>
                <div class="pausa-metrica-valor">{{ elegibilidad?.mesesPausadosEn12Meses ?? 0 }} de 6</div>
            </div>
        </div>

        <div v-if="historial.length" class="pausa-historial">
            <div class="pausa-subtitulo">HISTORIAL DE PAUSAS</div>
            <table class="pausa-tabla">
                <thead>
                    <tr>
                        <th>Periodo</th>
                        <th>Meses</th>
                        <th>Estado</th>
                        <th>Canal</th>
                        <th>Autorizó</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in historial" :key="p.id">
                        <td>{{ formatFecha(p.fecha_inicio) }} – {{ formatFecha(p.fecha_fin) }}</td>
                        <td>{{ p.meses }}</td>
                        <td><span class="pausa-tag" :class="claseEstado(p.estado)">{{ textoEstado(p.estado) }}</span></td>
                        <td>{{ p.canal || '-' }}</td>
                        <td>{{ p.creador ? p.creador.name : '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="bitacora.length" class="pausa-bitacora">
            <div class="pausa-subtitulo">BITÁCORA</div>
            <div v-for="b in bitacora" :key="b.id" class="pausa-bitacora-item">
                {{ formatFechaHora(b.created_at) }} · {{ b.description }}
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import { requestEstadoPausa, pausaRefreshTrigger } from "./helpers/request";

const ESTADO_LABELS = {
    esperando_pago: "Esperando pago",
    programada: "Programada",
    en_curso: "En curso",
    concluida: "Concluida",
    cancelada: "Cancelada",
    reanudada_anticipada: "Reanudada",
};

export default {
    name: "ClientBillingPausePanel",
    props: {
        clientId: [String, Number],
    },
    setup(props) {
        const elegibilidad = ref(null);
        const pausaViva = ref(null);
        const historial = ref([]);
        const bitacora = ref([]);

        const cargar = async () => {
            try {
                const data = await requestEstadoPausa(props.clientId);
                elegibilidad.value = data.elegibilidad;
                pausaViva.value = data.pausa_viva;
                historial.value = data.historial || [];
                bitacora.value = data.bitacora || [];
            } catch (e) {
                console.error("No se pudo cargar el panel de pausa de facturación", e);
            }
        };

        onMounted(cargar);
        watch(pausaRefreshTrigger, cargar);

        const ultimaPausaTexto = ref("Ninguna");
        watch(historial, (val) => {
            const cerrada = val.find((p) => ["concluida", "reanudada_anticipada", "cancelada"].includes(p.estado));
            ultimaPausaTexto.value = cerrada ? formatFecha(cerrada.fecha_fin_real || cerrada.fecha_fin) : "Ninguna";
        });

        const formatFecha = (iso) => {
            if (!iso) return "-";
            const d = new Date(iso);
            return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
        };

        const formatFechaHora = (iso) => {
            if (!iso) return "-";
            const d = new Date(iso);
            return `${formatFecha(iso)} ${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
        };

        const diaSiguiente = (iso) => {
            if (!iso) return null;
            const d = new Date(iso);
            d.setDate(d.getDate() + 1);
            return d;
        };

        const textoEstado = (estado) => ESTADO_LABELS[estado] || estado;
        const claseEstado = (estado) => {
            if (estado === "en_curso" || estado === "esperando_pago") return "pausa-tag-ambar";
            if (estado === "cancelada") return "pausa-tag-gris";
            return "pausa-tag-neutro";
        };

        return {
            elegibilidad,
            pausaViva,
            historial,
            bitacora,
            ultimaPausaTexto,
            formatFecha,
            formatFechaHora,
            diaSiguiente,
            textoEstado,
            claseEstado,
        };
    },
};
</script>

<style scoped>
.pausa-panel {
    background: #ffffff;
    border: 1px solid #e4e2ec;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 16px;
}
.pausa-panel-titulo {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #5e5b6e;
}
.pausa-banner {
    background: #fffbeb;
    border: 1px solid #f5c77e;
    border-radius: 10px;
    padding: 12px 16px;
}
.pausa-banner-titulo {
    font-weight: 700;
    color: #78350f;
}
.pausa-banner-detalle {
    font-size: 13px;
    color: #78350f;
}
.pausa-estado-libre {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.pausa-badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
}
.pausa-badge-ok {
    background: #dcfce7;
    color: #166534;
}
.pausa-badge-bloqueado {
    background: #fee2e2;
    color: #991b1b;
}
.pausa-estado-texto {
    font-size: 14px;
    color: #3d3a4d;
}
.pausa-metricas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
}
.pausa-metrica-card {
    background: #f7f6fb;
    border-radius: 8px;
    padding: 12px;
}
.pausa-metrica-label {
    font-size: 12px;
    color: #5e5b6e;
}
.pausa-metrica-valor {
    font-size: 16px;
    font-weight: 700;
}
.pausa-subtitulo {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #5e5b6e;
    margin-bottom: 8px;
}
.pausa-tabla {
    width: 100%;
    font-size: 13px;
    border-collapse: collapse;
}
.pausa-tabla th {
    text-align: left;
    color: #5e5b6e;
    font-weight: 700;
    border-bottom: 1px solid #e4e2ec;
    padding-bottom: 6px;
}
.pausa-tabla td {
    padding: 6px 0;
    border-bottom: 1px solid #eeedf3;
}
.pausa-tag {
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.pausa-tag-ambar {
    background: #fef3c7;
    color: #92400e;
}
.pausa-tag-gris {
    background: #eeedf3;
    color: #3d3a4d;
}
.pausa-tag-neutro {
    background: #ede9fe;
    color: #4b36c4;
}
.pausa-bitacora-item {
    font-size: 13px;
    color: #3d3a4d;
    padding: 4px 0;
}
</style>
