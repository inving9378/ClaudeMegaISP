<template>
    <div>
        <!--     Section of target   -->
        <div class="row">
            <tarjet-ticket
                v-for="val in stats"
                :icon="val.icon"
                :label="val.estado"
                :value="val.total"
                :link="val.link"
                :porcent="val.porcent"
                :labelPorcent="val.time_human"
            ></tarjet-ticket>
        </div>
        <!--     End Section of target   -->

        <!--    Section of Recient activities -->
        <!-- <div class="row">
      <recent-activity lista=""></recent-activity>
    </div> -->

        <!--     End Section of Recient activities  -->

        <div class="row">
            <!--     Section of  Assined ticket -->
            <assigned-ticket></assigned-ticket>
            <!--     End Section of Assined ticket  -->

            <!--     Section of  Assined ticket to admin-->
            <list-assigned-ticket></list-assigned-ticket>
        </div>
        <!--     End Section of Assined ticket  -->

        <div class="row">
            <chart-card title="Tickets Abiertos">
                <template #chart>
                    <div id="ticketNew-chart" style="height: 430px">
                        <div class="mb-3 col-sm-12 col-md-6">
                            <label class="form-label my-1"
                                >Filtrar por rango de fecha</label
                            >
                            <div class="d-flex gap-3">
                                <VueDatePicker
                                    v-model="date"
                                    position="left"
                                    locale="es"
                                    :max-date="new Date()"
                                    :teleport="true"
                                    placeholder="Selecciona un rango de fecha"
                                    range
                                    multi-calendars
                                >
                                </VueDatePicker>

                                <div>
                                    <label class="form-label my-1"
                                        >Mostrar
                                        <select
                                            name="DataTables_Table_0_length"
                                            @change="
                                                selectStatusTicket(
                                                    $event.target.value
                                                )
                                            "
                                            aria-controls="DataTables_Table_0"
                                            class="custom-select custom-select-sm form-control form-control-sm"
                                        >
                                            <option value="Todo">Todos</option>
                                            <option value="Nuevo">Nuevo</option>
                                            <option value="Trabajo en curso">
                                                Trabajo en curso
                                            </option>
                                            <option value="Resueltos">
                                                Resueltos
                                            </option>
                                            <option value="Esperando al agente">
                                                En espara del Agente
                                            </option>
                                            <option
                                                value="Esperando al cliente"
                                            >
                                                En espera del Cliente
                                            </option>
                                            <option value="Reciclado">
                                                Reciclado
                                            </option>
                                            <option value="Cerrado">
                                                Cerrado
                                            </option>
                                        </select>
                                    </label>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="getData"
                                >
                                    Buscar
                                </button>
                            </div>

                            <apexchart
                                type="pie"
                                height="300"
                                :options="chartOptions"
                                :series="series"
                            ></apexchart>
                        </div>
                    </div>
                </template>
            </chart-card>
        </div>
    </div>
</template>

<script>
import { onMounted, ref } from "vue";
import TarjetTicket from "./component/TarjetTicket";
import RecentActivity from "./component/RecentActivity";
import AssignedTicket from "./component/AssignedTicket";
import ListAssignedTicket from "./component/ListAssignedTicket";
import {
    requestStatisticsForTarjetsByStatus,
    getTicketsByDateAndStatus,
} from "./helper/request";

import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../components/base/card/chart/ChartCard.vue";

export default {
    name: "DashboardTicket",
    components: {
        TarjetTicket,
        RecentActivity,
        AssignedTicket,
        ListAssignedTicket,
        VueDatePicker,
        ChartCard,
    },
    props: {},
    setup() {
        const stats = ref({});
        const date = ref();
        const series = ref([]);
        const ticketAssignedTo = ref("Todo");
        const chartOptions = ref({
            labels: [],
            fill: {
                type: "gradient",
            },
            responsive: [
                {
                    breakpoint: 200,
                    options: {
                        chart: {
                            width: 200,
                        },
                        legend: {
                            position: "bottom",
                        },
                    },
                },
            ],
        });

        onMounted(() => {
            getStatisticsForTarjetsByStatus();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);
            const endDate = new Date();
            date.value = [startDate, endDate];
            getData();
        });

        const getStatisticsForTarjetsByStatus = async () => {
            stats.value = await requestStatisticsForTarjetsByStatus();
        };

        const selectStatusTicket = (val) => {
            ticketAssignedTo.value = val;
        };

        const getData = async () => {
            try {
                const response = await getTicketsByDateAndStatus(
                    date.value[0].toISOString().slice(0, 10),
                    date.value[1].toISOString().slice(0, 10),
                    ticketAssignedTo.value
                );
                if (response && response.length > 0) {
                    const newLabels = [];
                    const newSeries = [];
                    response.forEach((item) => {
                        newLabels.push(item.asignado);
                        newSeries.push(item.cantidad);
                    });
                    series.value = newSeries;
                    chartOptions.value = {
                        ...chartOptions.value,
                        labels: newLabels,
                    };
                }
            } catch (error) {
                console.log(error);
            }
        };

        return {
            stats,
            date,
            chartOptions,
            series,
            getData,
            selectStatusTicket,
        };
    },
};
</script>

<style scoped></style>
