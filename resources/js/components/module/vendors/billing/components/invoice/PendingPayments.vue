<template>
    <q-table
        v-table-resizable
        :columns="columns"
        :rows="rowsData"
        :loading="loading"
        :dark="darkMode"
        wrap-cells
        row-key="id"
        loading-label="Obteniendo pagos, por favor espere..."
        no-data-label="No existen pagos disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Pagos por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col">
                    <VueDatePicker
                        v-model="period"
                        position="right"
                        locale="es"
                        :teleport="true"
                        placeholder="Fecha"
                        range
                        week-start="0"
                        :format="customFormat"
                        :dark="darkMode"
                        :disabled-dates="diableDatesFromWeek"
                        :enable-time-picker="false"
                        class="q-mt-md"
                    >
                    </VueDatePicker>
                    <div
                        class="d-flex justify-content-between text-danger"
                        v-if="errorMessage"
                    >
                        <label> {{ errorMessage }}</label>
                    </div>
                </div>
                <div class="col self-end text-right no-padding">
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        icon="history"
                        :loading="loading"
                        @click="onRequest"
                    />
                </div>
            </div>
        </template>
        <template v-slot:body-cell-actions="props">
            <q-td class="text-center">
                <q-btn
                    icon="fas fa-money-bill"
                    flat
                    size="xs"
                    round
                    color="primary"
                    @click="
                        () => {
                            currentPayment = props.row;
                            showModalStorePayment = true;
                        }
                    "
                    v-if="hasEdit"
                />
            </q-td>
        </template>
    </q-table>

    <commissions-payment
        :show-modal="showModalStorePayment"
        :seller_id="seller"
        :object="currentPayment"
        :sales="
            currentPayment?.sales.filter((s) => s.state === 'pendiente') ?? []
        "
        @update:showModal="(val) => (showModalStorePayment = val)"
        @created="onCreatedPayment"
    />
</template>

<script setup>
import { ref, onMounted, computed, onBeforeMount, watch } from "vue";
import {
    diableDatesFromWeek,
    getPendingPaymentsBySeller,
} from "../../helper/helper";
import { darkMode } from "../../../../../../hook/appConfig";
import { date } from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

import CommissionsPayment from "./CommissionsPayment.vue";

const props = defineProps({
    seller: String | Number,
    hasEdit: Boolean,
});

const emits = defineEmits(["loaded"]);

const { customFormat } = useDatePicker();

const loading = ref(false);

const period = ref(null);

const errorMessage = ref(null);
const from = ref(null);
const to = ref(null);

const showModalStorePayment = ref(false);
const currentPayment = ref(null);

const columns = ref([
    {
        name: "period_str",
        field: "period_str",
        label: "Período",
        align: "left",
        sortable: true,
    },
    {
        name: "type",
        field: "type",
        label: "Tipo de pago",
        align: "left",
        sortable: false,
    },
    {
        name: "amount",
        field: "amount",
        label: "Cantidad",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "actions",
        field: "actions",
        label: "Acciones",
        align: "center",
        sortable: false,
    },
]);

const rows = ref([]);

onBeforeMount(() => {
    let data = date.value;
    period.value = data;
});

onMounted(() => {
    onRequest();
});

watch(period, (n) => {
    validRange(customFormat(n));
});

const validRange = async (range) => {
    errorMessage.value = null;
    from.value = null;
    to.value = null;
    if (range !== "") {
        let dates = range.split("-");
        let f = moment(dates[0].trim(), "DD/MM/YYYY"),
            t = moment(dates[1].trim(), "DD/MM/YYYY"),
            start = moment("02/06/2024", "DD/MM/YYYY"),
            current_date = moment.now();
        if (f.isBefore(start)) {
            errorMessage.value =
                "Período fuera de límite; la fecha de inicio no puede ser menor que 02/06/2024";
        } else if (t.isAfter(current_date)) {
            errorMessage.value = `Período fuera de límite; la fecha de terminación no puede ser mayor que la fecha actual ${moment(
                current_date
            ).format("DD/MM/YYYY")}`;
        } else {
            let diff = t.diff(f, "days");
            if (
                diff !== 6 ||
                f.get("weekday") !== 0 ||
                t.get("weekday") !== 6
            ) {
                errorMessage.value =
                    "Período no válido; debe empezar un domingo y terminar un sábado con diferencia semanal";
            } else {
                from.value = f.format("YYYY-MM-DD");
                to.value = t.format("YYYY-MM-DD");
                onRequest();
            }
        }
    } else {
        onRequest();
    }
};

const rowsData = computed(() => {
    return rows.value.filter((r) => r.amount > 0);
});

const onRequest = async () => {
    loading.value = true;
    let itemsOfPeriod = null;
    if (from.value && to.value) {
        itemsOfPeriod = [from.value, to.value];
    }
    let data = await getPendingPaymentsBySeller(props.seller, itemsOfPeriod);
    if (data !== null) {
        rows.value = data.data;
    } else {
        rows.value = [];
    }
    loading.value = false;
};

const onCreatedPayment = (sales) => {
    if (sales.length === 0) {
        currentPayment.value.amount = 0;
    } else {
        let cp = currentPayment.value;
        cp.sales.forEach((s) => {
            if (sales.includes(s.id)) {
                s.state = "pagada";
                cp.amount -= s.amount;
            }
        });
        currentPayment.value = cp;
    }
    showModalStorePayment.value = false;
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
