<template>
    <div>
        <div class="row mt-4">
            <!-- CARD 1 -->
            <tarjet-card
                link="/crm/listar"
                icon="fa-users"
                label="Prospectos"
                :value="totalProspects"
            >
            </tarjet-card>
            <!--     -->

            <!-- CARD 2 -->
            <tarjet-card
                link="/cliente/listar"
                icon="fa-chart-bar"
                label="Ventas realizadas"
                :value="totalSales"
            >
            </tarjet-card>
            <!--     -->

            <!-- CARD 3 -->
            <tarjet-card
                link="/cliente/listar"
                icon="fa-money-check-alt"
                label="Ventas sin seguimiento"
                :value="totalLostSales"
            >
            </tarjet-card>
            <!--     -->

            <!-- CARD 3 -->
            <tarjet-card
                link="#"
                icon="fa-hand-holding-usd"
                label="Descomisiones"
                :value="totalDecommissions"
            >
            </tarjet-card>
            <!--     -->
        </div>

        <div class="row">
            <div class="mt-3 col-12 col-md-6 col-lg-6 col-sm-12">
                <div>
                    <Sales />
                </div>

                <div>
                    <MediumSale />
                </div>

                <div class="mt-3">
                    <SalesByMonth />
                </div>
            </div>

            <!-- Parte 2 -->
            <div class="mt-3 col-md-6 col-sm-12">
                <div>
                    <log-activities />
                </div>
                <!--  -->
                <div class="mt-3">
                    <RankingSale />
                </div>
                <!--  -->
                <div class="mt-3">
                    <StatusProspects />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MediumSale from "./statistics/MediumSale.vue";
import StatusProspects from "./statistics/StatusProspects.vue";
import SalesByMonth from "./statistics/SalesByMonth.vue";
import Sales from "./statistics/Sales.vue";
import RankingSale from "./statistics/RankingSale";
import TarjetCard from "./components/TarjetCard.vue";
import LogActivities from "./components/LogActivities.vue";
import {
    getTotalProspects,
    getTotalSales,
    getLostSales,
} from "./statistics/helper/request";

const totalProspects = ref(0);
const totalSales = ref(0);
const totalLostSales = ref(0);
const totalDecommissions = ref(0);

onMounted(async () => {
    totalProspects.value = await getTotalProspects();
    totalSales.value = await getTotalSales();
    totalLostSales.value = await getLostSales();
});
</script>
