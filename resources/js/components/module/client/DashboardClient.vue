<template>
    <div class="row">
        <card-stats tableName="Clientes" :dataStat="dataStatClient"></card-stats>
    </div>
</template>

<script>
import {onMounted, ref} from "vue";
import CardStats from "./CardStats";
import {
    requestHomeStatisticsForTarjetsByStatus,
    requestStatsClientCardInDashBoard,
} from "./helpers/helper";

export default {
    name: "DashboardClients",
    components: {
        CardStats,
    },
    props: {},
    setup() {
        const stats = ref({});
        const dataStatClient = ref({});

        onMounted(() => {
            getStatisticsForTarjetsByStatus();
            getStatsClientCardInDashBoard();
        });

        const getStatisticsForTarjetsByStatus = async () => {
            stats.value = await requestHomeStatisticsForTarjetsByStatus();
        };

        const getStatsClientCardInDashBoard = async () => {
            dataStatClient.value = await requestStatsClientCardInDashBoard();
        };



        return {
            stats,
            dataStatClient,
        };
    },
};
</script>

<style scoped>
</style>
