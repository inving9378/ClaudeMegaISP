<template>
    <section class="mkt-panel-kpis" aria-label="Indicadores del mes">
        <div class="mkt-panel-kpis__rejilla">
            <mkt-tarjeta-kpi
                etiqueta="Leads del mes"
                :valor="numero(kpis.leads_mes?.valor)"
                :variacion="numero(kpis.leads_mes?.variacion)"
                formato="numero"
                icono="person_add"
                :cargando="cargando"
            />
            <mkt-tarjeta-kpi
                etiqueta="Conversión"
                :valor="numero(kpis.conversion?.valor)"
                :variacion="numero(kpis.conversion?.variacion)"
                formato="porcentaje"
                icono="emoji_events"
                :cargando="cargando"
            />
            <mkt-tarjeta-kpi
                etiqueta="Gasto registrado"
                :valor="numero(kpis.gasto_mxn?.valor)"
                :variacion="numero(kpis.gasto_mxn?.variacion)"
                formato="mxn"
                icono="payments"
                :cargando="cargando"
            />
            <mkt-tarjeta-kpi
                etiqueta="Costo por lead"
                :valor="numero(kpis.costo_por_lead_mxn?.valor)"
                :variacion="numero(kpis.costo_por_lead_mxn?.variacion)"
                formato="mxn"
                icono="price_check"
                :cargando="cargando"
            />
        </div>
        <p v-if="!cargando && resumen" class="mkt-panel-kpis__nota">
            <q-icon name="info_outline" size="16px" aria-hidden="true" />
            <span>
                <template v-if="ganados !== null">{{ textoGanados }} · </template>
                Gasto registrado: suma de lo gastado en campañas vigentes este mes, en MXN.
                <template v-if="otraMoneda > 0"> {{ textoOtraMoneda }}</template>
            </span>
        </p>
    </section>
</template>

<script>
import MktTarjetaKpi from "../kit/MktTarjetaKpi.vue";

/**
 * KPIs del Dashboard (contrato §2.4 ruta 1 → `kpis`). Dinero llega como cadena: Number() solo para formatear.
 * Valor null → «—» (nunca un 0 inventado).
 */
export default {
    name: "PanelKpis",
    components: { MktTarjetaKpi },
    props: {
        resumen: { type: Object, default: null },
        cargando: { type: Boolean, default: false },
    },
    computed: {
        kpis() {
            return this.resumen?.kpis || {};
        },
        ganados() {
            const g = this.kpis.conversion?.ganados;
            return g === null || g === undefined ? null : Number(g);
        },
        textoGanados() {
            return this.ganados === 1 ? "1 lead ganado este mes" : `${this.ganados} leads ganados este mes`;
        },
        otraMoneda() {
            return Number(this.kpis.gasto_mxn?.campanas_otra_moneda) || 0;
        },
        textoOtraMoneda() {
            return this.otraMoneda === 1
                ? "1 campaña en otra moneda no se suma."
                : `${this.otraMoneda} campañas en otra moneda no se suman.`;
        },
    },
    methods: {
        numero(v) {
            if (v === null || v === undefined || v === "") return null;
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
        },
    },
};
</script>

<style scoped>
.mkt-panel-kpis { display: flex; flex-direction: column; gap: var(--mkt-esp-1); min-width: 0; }
.mkt-panel-kpis__rejilla {
    display: grid;
    gap: var(--mkt-esp-2);
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.mkt-panel-kpis__nota {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--mkt-texto-suave);
}
.mkt-panel-kpis__nota .q-icon { margin-top: 1px; flex: 0 0 auto; }
@media (max-width: 1023px) {
    .mkt-panel-kpis__rejilla { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 599px) {
    .mkt-panel-kpis__rejilla { gap: var(--mkt-esp-1); }
}
</style>
