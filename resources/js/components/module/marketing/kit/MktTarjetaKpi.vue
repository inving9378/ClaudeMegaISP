<template>
    <div class="mkt-kpi" :aria-busy="cargando ? 'true' : 'false'">
        <div class="mkt-kpi__cabeza">
            <span class="mkt-kpi__etiqueta">{{ etiqueta }}</span>
            <span v-if="icono" class="mkt-kpi__icono" aria-hidden="true">
                <q-icon :name="icono" size="20px" />
            </span>
        </div>
        <template v-if="cargando">
            <q-skeleton type="text" width="60%" height="36px" />
            <q-skeleton type="text" width="40%" />
        </template>
        <template v-else>
            <div class="mkt-kpi__valor">{{ valorFormateado }}</div>
            <div v-if="variacion !== null && variacion !== undefined" class="mkt-kpi__variacion" :class="claseVariacion">
                <q-icon :name="iconoVariacion" size="16px" aria-hidden="true" />
                <span>{{ textoVariacion }}</span>
                <span class="mkt-kpi__periodo">vs. periodo anterior</span>
            </div>
        </template>
    </div>
</template>

<script>
import { formatear, formatoPorcentaje } from "./formato";

export default {
    name: "MktTarjetaKpi",
    props: {
        etiqueta: { type: String, required: true },
        valor: { type: Number, default: null },
        formato: {
            type: String,
            default: "numero",
            validator: (v) => ["numero", "mxn", "porcentaje"].includes(v),
        },
        variacion: { type: Number, default: null },
        icono: { type: String, default: "" },
        cargando: { type: Boolean, default: false },
    },
    computed: {
        valorFormateado() {
            return formatear(this.valor, this.formato);
        },
        claseVariacion() {
            if (this.variacion > 0) return "mkt-kpi__variacion--sube";
            if (this.variacion < 0) return "mkt-kpi__variacion--baja";
            return "mkt-kpi__variacion--igual";
        },
        iconoVariacion() {
            if (this.variacion > 0) return "trending_up";
            if (this.variacion < 0) return "trending_down";
            return "trending_flat";
        },
        textoVariacion() {
            const signo = this.variacion > 0 ? "+" : "";
            return signo + formatoPorcentaje(this.variacion);
        },
    },
};
</script>

<style scoped>
.mkt-kpi {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-1, 8px);
    /* Mismo recuadro que las cifras de la ficha de Cliente (`.cic-stat`). */
    padding: 10px 12px;
    background: var(--mkt-kpi-fondo, #f8fafc);
    border: 1px solid var(--mkt-kpi-borde, #eef0f3);
    border-radius: 10px;
    min-width: 0;
}
.mkt-kpi__cabeza {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--mkt-esp-1, 8px);
}
.mkt-kpi__etiqueta {
    font-size: 0.72rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-kpi__icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    color: #94a3b8;
}
.mkt-kpi__valor {
    font-size: 1.35rem;
    line-height: 1.3;
    font-weight: 700;
    color: var(--mkt-kpi-valor, #334155);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mkt-kpi__variacion {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    font-size: 13px;
    font-weight: 500;
}
.mkt-kpi__variacion--sube { color: var(--mkt-exito, #2ab57d); }
.mkt-kpi__variacion--baja { color: var(--mkt-peligro, #fd625e); }
.mkt-kpi__variacion--igual { color: var(--mkt-texto-suave, #74788d); }
@media (max-width: 599px) {
    .mkt-kpi__cabeza { align-items: flex-start; }
    .mkt-kpi__etiqueta { white-space: normal; line-height: 1.3; }
    .mkt-kpi__valor { font-size: 1.15rem; }
}
.mkt-kpi__periodo {
    font-weight: 400;
    color: var(--mkt-texto-suave, #74788d);
}
</style>
