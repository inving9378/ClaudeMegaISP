<template>
    <q-card class="impacto-panel">
        <q-card-section class="impacto-panel__header">
            <div class="text-subtitle1">¿Quién depende de esto?</div>
            <q-space />
            <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator />

        <q-card-section v-if="loading" class="text-caption text-grey">
            Calculando…
        </q-card-section>

        <template v-else-if="impacto">
            <q-card-section>
                <div
                    v-if="impacto.advertencias && impacto.advertencias.length"
                    class="impacto-panel__advertencias"
                >
                    <div
                        v-for="(adv, idx) in impacto.advertencias"
                        :key="idx"
                        class="text-caption text-warning"
                    >
                        {{ adv }}
                    </div>
                </div>

                <div class="impacto-panel__kpis">
                    <q-card flat bordered class="impacto-panel__kpi">
                        <q-card-section>
                            <div class="text-caption text-grey">Clientes</div>
                            <div class="text-h6">
                                {{ impacto.total_clientes ?? 0 }}
                            </div>
                        </q-card-section>
                    </q-card>
                    <q-card flat bordered class="impacto-panel__kpi">
                        <q-card-section>
                            <div class="text-caption text-grey">MRR total</div>
                            <div class="text-h6">{{ mrrFormateado }}</div>
                        </q-card-section>
                    </q-card>
                    <q-card flat bordered class="impacto-panel__kpi">
                        <q-card-section>
                            <div class="text-caption text-grey">
                                Suspendidos
                            </div>
                            <div class="text-h6">
                                {{ impacto.total_suspendidos ?? 0 }}
                            </div>
                        </q-card-section>
                    </q-card>
                </div>
            </q-card-section>

            <q-card-section>
                <q-table
                    :rows="impacto.detalle ?? []"
                    :columns="columnas"
                    row-key="enlace_id"
                    dense
                    flat
                    bordered
                    :pagination="{ rowsPerPage: 10 }"
                >
                    <template v-slot:body-cell-cliente_nombre="propRow">
                        <q-td :props="propRow">
                            {{ propRow.row.cliente_nombre || "—" }}
                            <q-badge
                                v-if="!propRow.row.vinculado"
                                color="warning"
                                outline
                                class="q-ml-xs"
                            >
                                sin vincular
                            </q-badge>
                        </q-td>
                    </template>
                    <template v-slot:body-cell-monto="propRow">
                        <q-td :props="propRow">
                            {{ formatoMoneda(propRow.row.monto) }}
                        </q-td>
                    </template>
                </q-table>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    icon="download"
                    label="Exportar CSV"
                    :disable="!(impacto.detalle && impacto.detalle.length)"
                    @click="exportarCsv"
                />
            </q-card-actions>
        </template>

        <q-card-section v-else class="text-caption text-grey">
            No se pudo calcular el impacto de este elemento.
        </q-card-section>
    </q-card>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { getImpacto } from "../../helper/impacto-request";

defineOptions({
    name: "ImpactoPanel",
});

const props = defineProps({
    tipo: {
        type: String,
        required: true,
    },
    id: {
        type: [Number, String],
        required: true,
    },
});

const loading = ref(true);
const impacto = ref(null);

const moneyFmt = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});
const formatoMoneda = (v) => moneyFmt.format(Number(v || 0));
const mrrFormateado = computed(() => formatoMoneda(impacto.value?.mrr_total));

const columnas = [
    { name: "enlace_id", label: "Enlace", field: "enlace_id", align: "left", sortable: true },
    { name: "cliente_nombre", label: "Cliente", field: "cliente_nombre", align: "left", sortable: true },
    { name: "plan", label: "Plan", field: "plan", align: "left" },
    { name: "monto", label: "Monto", field: "monto", align: "right", sortable: true },
    { name: "nap_puerto", label: "Puerto NAP", field: "nap_puerto", align: "left" },
];

const cargar = async () => {
    loading.value = true;
    impacto.value = await getImpacto(props.tipo, props.id);
    loading.value = false;
};

watch(() => [props.tipo, props.id], cargar, { immediate: true });

const exportarCsv = () => {
    const detalle = impacto.value?.detalle ?? [];
    const rows = [["Enlace", "Cliente", "Plan", "Monto", "Puerto NAP", "Vinculado"]];
    detalle.forEach((d) =>
        rows.push([
            d.enlace_id ?? "",
            d.cliente_nombre ?? "",
            d.plan ?? "",
            d.monto ?? 0,
            d.nap_puerto ?? "",
            d.vinculado ? "Sí" : "No",
        ])
    );
    const csv = rows
        .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
        .join("\n");
    const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8;" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `impacto_${props.tipo}_${props.id}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
};
</script>

<style scoped>
.impacto-panel {
    width: 640px;
    max-width: 92vw;
}

.impacto-panel__header {
    display: flex;
    align-items: center;
}

.impacto-panel__advertencias {
    margin-bottom: 8px;
}

.impacto-panel__kpis {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.impacto-panel__kpi {
    flex: 1 1 140px;
}
</style>
