<template>
    <div>
        <!-- <h3 class="mb-10">Estadisticas del vendedor</h3> -->

        <div class="row">
            <div class="mt-3 col-12 col-md-6 col-lg-6 col-sm-12">
                <div>
                    <Sales :id="id" />
                </div>

                <div>
                    <MediumSale :id="id" />
                </div>
            </div>

            <!-- Parte 2 -->
            <div class="mt-3 col-md-6 col-sm-12">
                <div>
                    <log-activities :user="id"> </log-activities>
                </div>

                <div class="mt-3">
                    <SalesByMonth :id="id" />
                </div>
            </div>

            <!-- Parte 3 -->
            <div class="mt-2 col-lg-12 col-md-12 col-sm-12">
                <StatusProspects :id="id" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import Sales from "./Sales.vue";
import MediumSale from "./MediumSale.vue";
import SalesByMonth from "./SalesByMonth";
import StatusProspects from "./StatusProspects.vue";
import LogActivities from "../components/LogActivities.vue";

defineOptions({
    name: "Dashboard",
});

const props = defineProps({
    id: Number,
});
</script>
