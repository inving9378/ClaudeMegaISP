<template>
    <mkt-tarjeta-panel titulo="Embudo de leads" :subtitulo="subtitulo" :cargando="cargando">
        <mkt-estado-vacio
            v-if="!total"
            icono="filter_alt"
            titulo="Aún no hay leads este mes"
            texto="Cuando entren leads por formularios, WhatsApp o captura manual, aquí verás en qué etapa está cada uno."
        />
        <div v-else class="mkt-embudo">
            <div class="mkt-embudo__grafica" aria-hidden="true">
                <apexchart type="bar" :height="alto" :options="opciones" :series="series" />
            </div>
            <ul class="mkt-embudo__lista" aria-label="Leads por etapa">
                <li v-for="(e, i) in etapas" :key="e.clave" class="mkt-embudo__fila">
                    <span class="mkt-embudo__muestra" :style="{ background: colores[i] }" aria-hidden="true"></span>
                    <span class="mkt-embudo__etiqueta">{{ e.etiqueta }}</span>
                    <span class="mkt-embudo__total">{{ formatoNumero(e.total) }}</span>
                    <span class="mkt-embudo__pct">{{ porcentaje(e.total) }}</span>
                </li>
            </ul>
        </div>
        <template v-if="total" #pie>
            {{ formatoNumero(total) }} leads en el mes · {{ formatoNumero(perdidos) }} perdidos · {{ formatoNumero(archivados) }} archivados
        </template>
    </mkt-tarjeta-panel>
</template>

<script>
import MktTarjetaPanel from "../kit/MktTarjetaPanel.vue";
import MktEstadoVacio from "../kit/MktEstadoVacio.vue";
import { formatoNumero, formatoPorcentaje } from "../kit/formato";
import { COLORES_EMBUDO, opcionesBaseGrafica } from "../kit/tokens";

/**
 * Embudo del mes (contrato §2.4 ruta 1 → `embudo`): barra horizontal de ApexCharts con los colores del kit
 * y, al lado, la misma información en texto (lectura rápida y lectores de pantalla).
 */
export default {
    name: "PanelEmbudo",
    components: { MktTarjetaPanel, MktEstadoVacio },
    props: {
        embudo: { type: Object, default: null },
        cargando: { type: Boolean, default: false },
    },
    data() {
        return { oscuro: false, observador: null };
    },
    computed: {
        etapas() {
            return Array.isArray(this.embudo?.etapas) ? this.embudo.etapas : [];
        },
        total() {
            return Number(this.embudo?.total) || 0;
        },
        perdidos() {
            return Number(this.embudo?.perdidos) || 0;
        },
        archivados() {
            return Number(this.embudo?.archivados) || 0;
        },
        subtitulo() {
            return "Leads capturados en el mes, por etapa actual";
        },
        colores() {
            return this.etapas.map((_, i) => COLORES_EMBUDO[i % COLORES_EMBUDO.length]);
        },
        alto() {
            return Math.max(200, this.etapas.length * 44);
        },
        series() {
            return [{ name: "Leads", data: this.etapas.map((e) => Number(e.total) || 0) }];
        },
        opciones() {
            const base = opcionesBaseGrafica(this.oscuro);
            return {
                ...base,
                colors: this.colores,
                plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: "62%" } },
                xaxis: {
                    categories: this.etapas.map((e) => e.etiqueta),
                    labels: { formatter: (v) => formatoNumero(v) },
                    tickAmount: 4,
                },
                yaxis: { labels: { maxWidth: 140 } },
                tooltip: { ...base.tooltip, y: { formatter: (v) => `${formatoNumero(v)} leads` } },
                states: { hover: { filter: { type: "none" } } },
            };
        },
    },
    mounted() {
        this.leerModo();
        if (typeof MutationObserver !== "undefined") {
            this.observador = new MutationObserver(() => this.leerModo());
            this.observador.observe(document.body, { attributes: true, attributeFilter: ["data-layout-mode"] });
        }
    },
    beforeUnmount() {
        if (this.observador) this.observador.disconnect();
    },
    methods: {
        formatoNumero,
        leerModo() {
            this.oscuro = document.body.getAttribute("data-layout-mode") === "dark";
        },
        porcentaje(n) {
            return this.total ? formatoPorcentaje((Number(n) / this.total) * 100) : "—";
        },
    },
};
</script>

<style scoped>
.mkt-embudo {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(200px, 2fr);
    gap: var(--mkt-esp-3);
    align-items: center;
}
.mkt-embudo__grafica { min-width: 0; }
.mkt-embudo__lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.mkt-embudo__fila {
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr) auto 56px;
    align-items: center;
    gap: var(--mkt-esp-1);
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px solid var(--mkt-borde);
}
.mkt-embudo__fila:last-child { border-bottom: 0; }
.mkt-embudo__muestra { width: 10px; height: 10px; border-radius: 3px; }
.mkt-embudo__etiqueta { color: var(--mkt-texto); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mkt-embudo__total { font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; color: var(--mkt-texto); }
.mkt-embudo__pct { font-size: 13px; text-align: right; font-variant-numeric: tabular-nums; color: var(--mkt-texto-suave); }
@media (max-width: 1023px) {
    .mkt-embudo { grid-template-columns: minmax(0, 1fr); gap: var(--mkt-esp-2); }
}
</style>
