<template>
    <div class="q-gutter-y-md">
        <!-- Sección de Conexión Actual: para dedicados con «Datos de red» la reemplaza el estado
             del servicio dedicado (#988), que va en ClientPingStatus. -->
        <div v-if="resumenListo && !dedicado?.reemplaza_pppoe" class="row">
            <div class="col-12">
                <ClientActiveConnections :clientId="id"/>
            </div>
        </div>

        <div class="col-12">
            <ClientPingStatus :clientId="id" :dedicado="dedicado" @actualizar="cargarResumen" />
        </div>

        <!-- Aquí irán los siguientes componentes: Consumo Mensual, Gráficas, etc. -->
        <div class="row q-mt-md">
            <div class="col-12">
                <ClientConsumptionSummary :clientId="id"/>
            </div>
            <div class="col-12">
                <ClientDailyUsage :clientId="id"/>
            </div>
            <div class="col-12">
                <ClientFupStatistics :clientId="id" />
            </div>
            <div class="col-12">
                <ClientConnectionHistory :clientId="id" />
            </div>
        </div>
    </div>

</template>

<script>
import {onMounted, ref} from "vue";
import axios from "axios";
import ClientActiveConnections from "./ClientActiveConnections.vue";
import ClientConsumptionSummary from "./ClientConsumptionSummary.vue";
import ClientDailyUsage from "./ClientDailyUsage.vue";
import ClientFupStatistics from "./ClientFupStatistics.vue";
import ClientConnectionHistory from "./ClientConnectionHistory.vue";
import ClientPingStatus from "./ClientPingStatus.vue";

export default {
    name: "ClientStatistics",
    props: {
        id: {
            type: String
        }
    },
    components: {
        ClientConnectionHistory,
        ClientActiveConnections,
        ClientConsumptionSummary,
        ClientDailyUsage,
        ClientFupStatistics,
        ClientPingStatus,
    },
    setup(props) {
        // #988: si el cliente es dedicado y qué servicios tienen «Datos de red». Si no se puede
        // leer, la pantalla queda como siempre (con el bloque PPPoE).
        const dedicado = ref(null);
        const resumenListo = ref(false);

        const cargarResumen = async () => {
            try {
                const { data } = await axios.get(`/cliente/statistics/dedicado/${props.id}`);
                dedicado.value = data;
            } catch (e) {
                dedicado.value = null;
            } finally {
                resumenListo.value = true;
            }
        };

        onMounted(cargarResumen);

        return { dedicado, resumenListo, cargarResumen };
    },
};
</script>
