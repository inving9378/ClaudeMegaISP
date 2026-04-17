<template>
    <div class="row">
        <template v-for="val in stats">
            <tarjet-ticket
                :key="val.permission"
                v-if="hasPermission.data.canView(`${val.permission}`)"
                :icon="val.icon"
                :label="val.estado"
                :value="val.total"
                :link="val.link"
                :porcent="val.porcent"
                :labelPorcent="val.time_human"
            ></tarjet-ticket>
        </template>
    </div>

    <div class="row mb-3">
        <card-text-dashboard
            v-if="
                hasPermission.data.canView(
                    `dashboard_view_info_invoice_transaction`
                )
            "
        ></card-text-dashboard>
    </div>

    <div class="row">
        <card-stats
            v-if="hasPermission.data.canView(`dashboard_view_block_client`)"
            tableName="Clientes"
            :dataStat="dataStatClient"
        ></card-stats>
        <card-stats
            v-if="hasPermission.data.canView(`dashboard_view_block_ticket`)"
            tableName="Tickets"
            :dataStat="dataStatTicket"
        ></card-stats>
        <card-stats
            v-if="hasPermission.data.canView(`dashboard_view_block_finance`)"
            tableName="Finanzas"
            :dataStat="dataStatFinance"
        ></card-stats>
        <card-stats
            v-if="hasPermission.data.canView(`dashboard_view_block_finance`)"
            tableName="Servidores"
            :dataStat="dataStatServer"
        ></card-stats>
    </div>
</template>

<script>
import { onMounted, ref, reactive, watch } from "vue";
import TarjetTicket from "../tickets/component/TarjetTicket";
import CardTextDashboard from "./CardTextDashboard.vue";
import CardStats from "./CardStats";
import { debounce } from "lodash";
import {
    requestHomeStatisticsForTarjetsByStatus,
    requestStatsClientCardInDashBoard,
    requestStatsFinanceCardInDashBoard,
    requestStatsServerCardInDashBoard,
    requestStatsTicketCardInDashBoard,
} from "./helper/request";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

export default {
    name: "Dashboard",
    components: {
        TarjetTicket,
        CardTextDashboard,
        CardStats,
    },
    props: {},
    setup() {
        const stats = ref({});
        const dataStatClient = ref({});
        const dataStatTicket = ref({});
        const dataStatFinance = ref({});
        const dataStatServer = ref({});
        const hasPermission = reactive({
            data: new Permission({}),
        });

        const count = ref(0);

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            getStatisticsForTarjetsByStatus();
            getStatsClientCardInDashBoard();
            getStatsTicketCardInDashBoard();
            if (hasPermission.data.canView(`dashboard_view_block_finance`)) {
                getStatsServerCardInDashBoard();
                getStatsFinanceCardInDashBoard();
            }
        });

        const getStatisticsForTarjetsByStatus = async () => {
            stats.value = await requestHomeStatisticsForTarjetsByStatus();
        };

        const getStatsClientCardInDashBoard = async () => {
            dataStatClient.value = await requestStatsClientCardInDashBoard();
        };

        const getStatsTicketCardInDashBoard = async () => {
            dataStatTicket.value = await requestStatsTicketCardInDashBoard();
        };

        const getStatsFinanceCardInDashBoard = async () => {
            dataStatFinance.value = await requestStatsFinanceCardInDashBoard();
        };

        const getStatsServerCardInDashBoard = async () => {
            dataStatServer.value = await requestStatsServerCardInDashBoard();
            count.value = count.value + 1;
            if (count.value == 500) {
                location.reload();
            }
        };

        watch(
            count,
            debounce(() => {
                getStatsServerCardInDashBoard();
            }, 3000)
        );

        return {
            stats,
            dataStatClient,
            dataStatTicket,
            dataStatFinance,
            hasPermission,
            dataStatServer,
        };
    },
};
</script>

<style scoped></style>
