<template>
    <!--
        #988 Fase 4: panel de los servicios DEDICADOS en Estadísticas → Internet. Para dedicados ya
        no se muestra el bloque viejo «Ping / Latencia» (ping_statistics mide la IP del servicio,
        que puede ser la del router, #999): todo sale de las tablas nuevas por servicio. Debajo del
        estado en tiempo real va el «Histórico del servicio» (periodo, indicadores, gráficas,
        eventos y origen de los datos).
    -->
    <div v-if="dedicado && dedicado.is_dedicated && servicios.length" class="cps q-mt-md">
        <q-tabs
            v-if="servicios.length >= 2"
            v-model="servicioId"
            dense
            no-caps
            align="left"
            active-color="primary"
            indicator-color="primary"
            class="cps-tabs q-mb-sm"
            :dark="darkMode"
        >
            <q-tab v-for="s in servicios" :key="s.id" :name="s.id" :label="s.etiqueta" />
        </q-tabs>

        <q-banner v-if="simulado" dense rounded class="cps-simulado q-mb-sm">
            <template #avatar><q-icon name="science" /></template>
            Datos simulados (entorno de desarrollo)
        </q-banner>

        <q-card v-if="servicioActual && !servicioActual.configurado" flat bordered class="cps-vacio" :dark="darkMode">
            <q-card-section class="cps-vacio-cuerpo">
                <q-icon name="lan" size="28px" color="grey-6" />
                <div>
                    <div class="text-body1">Este servicio dedicado aún no tiene datos de red configurados.</div>
                    <div class="text-caption cps-tenue">{{ servicioActual.etiqueta }}</div>
                </div>
                <q-space />
                <q-btn
                    v-if="dedicado.puede_editar_red"
                    color="primary"
                    no-caps
                    label="Configurar datos de red"
                    @click="ventanaRed = true"
                />
            </q-card-section>
        </q-card>

        <template v-else-if="servicioActual">
            <DedicadoEstadoTiempoReal :servicio-id="servicioActual.id" @cargado="(e) => (simulado = !!e.simulado)" />
            <DedicadoHistorico
                :servicio-id="servicioActual.id"
                :puede-editar="!!dedicado.puede_editar_red"
                @configurar="ventanaRed = true"
            />
        </template>

        <DatosRedDedicado v-model="ventanaRed" :servicio-id="servicioActual?.id" @guardado="$emit('actualizar')" />
    </div>
</template>

<script>
import { computed, ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig.js";
import DedicadoEstadoTiempoReal from "./DedicadoEstadoTiempoReal.vue";
import DedicadoHistorico from "./dedicado/DedicadoHistorico.vue";
import DatosRedDedicado from "../service/internet/DatosRedDedicado.vue";

export default {
    name: "ClientPingStatus",
    components: { DedicadoEstadoTiempoReal, DedicadoHistorico, DatosRedDedicado },
    props: {
        clientId: { type: String, default: null },
        // Resumen de /cliente/statistics/dedicado/{id}, lo trae ClientStatistics.vue.
        dedicado: { type: Object, default: null },
    },
    emits: ["actualizar"],
    setup(props) {
        const servicios = computed(() => props.dedicado?.servicios || []);
        const servicioId = ref(null);
        const simulado = ref(false);
        const ventanaRed = ref(false);

        // Por default el primer servicio con datos de red; si ninguno tiene, el primero.
        watch(servicios, (lista) => {
            if (!lista.some((s) => s.id === servicioId.value)) {
                servicioId.value = (lista.find((s) => s.configurado) || lista[0])?.id ?? null;
            }
        }, { immediate: true });
        watch(servicioId, () => (simulado.value = false));

        const servicioActual = computed(() => servicios.value.find((s) => s.id === servicioId.value) || null);

        return { darkMode, servicios, servicioId, servicioActual, simulado, ventanaRed };
    },
};
</script>

<style scoped>
.cps-simulado {
    background: rgba(217, 119, 6, 0.12);
    border: 1px solid rgba(217, 119, 6, 0.35);
}
.cps-vacio-cuerpo {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.cps-tenue {
    opacity: 0.7;
}
/* q-tabs usa .row por dentro y el Bootstrap global estira cada pestaña a todo el ancho. */
.cps-tabs :deep(.row) {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
}
.cps-tabs :deep(.row > *),
.cps-tabs :deep(.q-tab) {
    width: auto;
    max-width: none;
}
.cps-tabs :deep(.q-tab) {
    padding: 0 16px;
}
</style>
