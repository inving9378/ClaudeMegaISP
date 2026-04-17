<template>
    <div class="table-responsive text-nowrap w-75 mx-auto">
        <div class="form-group mt-3 mb-3 w-25">
            <div>
                <h4>Seleccionar sector</h4>

                <select v-model="selectedZone" class="form-select">
                    <option value="A" @click="selectAZone('A')">Zona A</option>
                    <option value="B" @click="selectAZone('B')">Zona B</option>
                    <option value="C" @click="selectAZone('C')">Zona C</option>
                </select>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th class="text-center">Insignia</th>
                    <th class="text-center">Rango</th>
                    <th class="text-center">Prospectos</th>
                    <th class="text-center">Ventas</th>
                    <th class="text-center">Periodo</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                <tr v-for="zone in agroupedZones[selectedZone]">
                    <td class="d-flex justify-content-center">
                        <i
                            class="fas fa-star yellow"
                            v-for="(item, index) in calculateStarts(
                                zone.range.toUpperCase()
                            )"
                            :key="index"
                        ></i>
                        <i
                            class="far fa-star yellow"
                            v-for="(item, index) in 5 -
                            calculateStarts(zone.range.toUpperCase())"
                            :key="index"
                        ></i>
                    </td>
                    <td class="text-center">{{ zone.range.toUpperCase() }}</td>
                    <td class="text-center">{{ zone.number_of_prospects }}</td>
                    <td class="text-center">{{ zone.number_of_sales }}</td>
                    <td class="text-center">SEMANAL</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    zones: Array,
});

const agroupedZones = computed(() => {
    return props.zones.reduce((acc, zone) => {
        if (!acc[zone.sector]) {
            acc[zone.sector] = [];
        }
        acc[zone.sector].push(zone);
        return acc;
    }, {});
});

const selectedZone = ref("A");

function selectAZone(zone) {
    selectedZone.value = zone;
}

function calculateStarts(zone) {
    if (zone === "COBRE") {
        return 1;
    }
    if (zone === "BRONCE") {
        return 2;
    }
    if (zone === "PLATA") {
        return 3;
    }
    if (zone === "ORO") {
        return 4;
    }
    if (zone === "DIAMANTE") {
        return 5;
    }
}
</script>

<style scoped>
.yellow {
    color: #f1c40f;
}
</style>
