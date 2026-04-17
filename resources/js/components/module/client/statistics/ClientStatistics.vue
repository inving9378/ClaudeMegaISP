<template>
    <div class="q-gutter-y-md">
        <!-- Sección de Conexión Actual -->
        <div class="row">
            <div class="col-12">
                <ClientActiveConnections :clientId="id"/>
            </div>
        </div>

        <div class="col-12">
            <ClientPingStatus :clientId="id" />
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
import {onMounted, reactive, ref} from "vue";
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
        onMounted(() => {
        });

        return {};
    },
};
</script>
