<template>
    <div class="optical-budget">
        <div v-if="loading" class="text-caption text-grey">Calculando…</div>

        <template v-else-if="presupuesto">
            <div v-if="!presupuesto.ruta_completa" class="optical-budget__notice">
                Ruta incompleta ({{ presupuesto.motivo_corte || "sin motivo registrado" }}) — el
                desglose solo cubre el tramo que se pudo trazar.
            </div>

            <table v-if="presupuesto.desglose.length" class="optical-budget__table">
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Pérdida (dB)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in presupuesto.desglose" :key="idx">
                        <td>{{ elementoLabel(item) }}</td>
                        <td>{{ item.perdida_db.toFixed(3) }}</td>
                    </tr>
                    <tr class="optical-budget__total-row">
                        <td>Total</td>
                        <td>{{ presupuesto.total_perdida_db.toFixed(3) }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="text-caption text-grey">
                Sin segmentos trazados desde este enlace.
            </div>

            <div class="optical-budget__badges">
                <span v-if="!presupuesto.tx_olt_es_real" class="optical-budget__badge">
                    TX de OLT: valor por defecto (GPON Clase B+ genérico, no medido — MR-08/#944
                    pendiente)
                </span>
                <span v-if="presupuesto.rx_real_dbm === null" class="optical-budget__badge">
                    Sin lectura real de MultiOLT para esta ONT — solo estimado
                </span>
            </div>

            <div
                class="optical-budget__result"
                :class="
                    presupuesto.cierra
                        ? 'optical-budget__result--ok'
                        : 'optical-budget__result--bad'
                "
            >
                RX estimado: {{ presupuesto.rx_estimado_dbm.toFixed(3) }} dBm (rango ONT
                {{ presupuesto.sensibilidad_min_dbm }} a
                {{ presupuesto.sensibilidad_max_dbm }} dBm) —
                {{ presupuesto.cierra ? "Cierra" : "NO CIERRA" }}
            </div>

            <div
                v-if="presupuesto.rx_real_dbm !== null"
                class="optical-budget__result"
                :class="{
                    'optical-budget__result--bad': presupuesto.dentro_de_tolerancia === false,
                    'optical-budget__result--ok': presupuesto.dentro_de_tolerancia === true,
                }"
            >
                RX real (MultiOLT): {{ presupuesto.rx_real_dbm.toFixed(3) }} dBm · diferencia
                {{ presupuesto.diferencia_db.toFixed(3) }} dB ({{
                    presupuesto.dentro_de_tolerancia ? "dentro de tolerancia" : "fuera de tolerancia"
                }})
            </div>
        </template>

        <div v-else class="text-caption text-grey">
            No se pudo calcular el presupuesto óptico de este enlace.
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { getPresupuestoOptico } from "../../helper/enlaces-request";

defineOptions({
    name: "OpticalBudgetPanel",
});

const props = defineProps({
    enlaceId: {
        type: [Number, String],
        required: true,
    },
});

const loading = ref(true);
const presupuesto = ref(null);

const ELEMENTO_LABELS = {
    cable: "Cable",
    empalme: "Empalme",
    splitter: "Splitter",
};

const elementoLabel = (item) => ELEMENTO_LABELS[item.elemento] ?? item.elemento;

onMounted(async () => {
    loading.value = true;
    presupuesto.value = await getPresupuestoOptico(props.enlaceId);
    loading.value = false;
});
</script>

<style scoped>
.optical-budget {
    padding: 8px 0 4px;
    font-size: 12.5px;
}

.optical-budget__notice {
    color: #b26a00;
    font-size: 11.5px;
    margin-bottom: 6px;
}

.optical-budget__table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}

.optical-budget__table th,
.optical-budget__table td {
    text-align: left;
    padding: 2px 4px;
    border-bottom: 1px solid rgba(128, 128, 128, 0.2);
}

.optical-budget__table th:last-child,
.optical-budget__table td:last-child {
    text-align: right;
}

.optical-budget__total-row td {
    font-weight: 600;
    border-top: 1px solid rgba(128, 128, 128, 0.4);
    border-bottom: none;
}

.optical-budget__badges {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
}

.optical-budget__badge {
    font-size: 11px;
    color: #9e9e9e;
    background: rgba(128, 128, 128, 0.12);
    border-radius: 4px;
    padding: 2px 6px;
}

.optical-budget__result {
    padding: 4px 6px;
    border-radius: 4px;
    margin-bottom: 4px;
    font-weight: 500;
}

.optical-budget__result--ok {
    background: rgba(76, 175, 80, 0.15);
    color: #2e7d32;
}

.optical-budget__result--bad {
    background: rgba(244, 67, 54, 0.15);
    color: #c62828;
}
</style>
