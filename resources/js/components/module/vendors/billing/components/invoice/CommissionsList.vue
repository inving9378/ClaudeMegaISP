<template>
    <VueDatePicker
        v-model="date"
        position="right"
        locale="es"
        :teleport="true"
        placeholder="Fecha"
        range
        week-start="0"
        :format="customFormat"
        :clearable="false"
        :dark="darkMode"
        :disabled-dates="diableDatesFromWeek"
        :enable-time-picker="false"
        class="q-mt-md"
    >
    </VueDatePicker>
    <div class="d-flex justify-content-between text-danger" v-if="errorMessage">
        <label> {{ errorMessage }}</label>
    </div>
    <q-splitter
        v-model="splitterModel"
        :dark="darkMode"
        class="no-gutter-x q-pt-sm"
        style="min-height: 400px"
    >
        <template v-slot:before>
            <div class="q-pa-md">
                <div class="text-h6 q-mb-md">Ventas de la Casa</div>
                <q-list dense v-if="additional_sales_commissions">
                    <q-item
                        class="no-padding"
                        v-for="(
                            client, indexClient
                        ) in additional_sales_commissions['LVC']"
                        :key="`key-${indexClient}`"
                    >
                        <q-item-section>
                            {{ client }}
                        </q-item-section>
                    </q-item>
                </q-list>
            </div>
        </template>

        <template v-slot:separator>
            <q-btn flat color="primary" round icon="drag_indicator" />
        </template>

        <template v-slot:after>
            <div class="q-pa-md">
                <div class="row no-gutter-x">
                    <div class="col-8">
                        <div class="text-h6 q-mb-md">Ventas adicionales</div>
                    </div>
                    <div class="col-4 text-right">
                        <q-btn
                            label="Crear pago"
                            color="primary"
                            no-caps
                            :disable="disablePayment"
                            @click="showModalPayment = true"
                            v-if="hasEdit"
                        />
                    </div>
                </div>

                <q-list dense v-if="additional_sales_commissions">
                    <q-item
                        class="no-padding"
                        v-for="(
                            client, indexClient
                        ) in additional_sales_commissions['LVA']"
                        :key="`key-${indexClient}`"
                    >
                        <q-item-section>
                            {{ client.label }}
                        </q-item-section>
                        <q-item-section avatar>
                            <span
                                class="text-primary"
                                v-if="client.state === 'pagada'"
                                >PAGADA</span
                            >
                            <span
                                class="text-danger"
                                v-else-if="client.state === 'descontada'"
                                >DESCONTADA</span
                            >
                            <span class="text-success" v-else>PENDIENTE</span>
                        </q-item-section>
                    </q-item>
                </q-list>
            </div>
        </template>
    </q-splitter>

    <q-inner-loading
        :showing="loading"
        label="Actualizando..."
        label-style="font-size: 1.1em"
        :dark="darkMode"
    />

    <commissions-payment
        :seller_id="seller_id"
        :sales="
            !disablePayment
                ? additional_sales_commissions['LVA'].filter(
                      (a) => a.state === 'pendiente'
                  )
                : []
        "
        v-model:showModal="showModalPayment"
        @created="onCreatedPayment"
    />
</template>

<script setup>
import { watch, ref, onMounted, computed } from "vue";
import { darkMode } from "../../../../../../hook/appConfig";
import VueDatePicker from "@vuepic/vue-datepicker";
import CommissionsPayment from "./CommissionsPayment.vue";
import {
    date,
    from,
    to,
    errorMessage,
    getRuleDataSeller,
    diableDatesFromWeek,
} from "../../helper/helper";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

const props = defineProps({
    user_id: String | Number,
    seller_id: String | Number,
    hasEdit: Boolean,
});

const emits = defineEmits(["hide"]);

const { customFormat } = useDatePicker();

const splitterModel = ref(40);
const loading = ref(false);
const additional_sales_commissions = ref(null);
const showModalPayment = ref(false);

onMounted(() => {
    loadData();
});

watch(date, (n) => {
    loadData();
});

const loadData = async () => {
    if (from.value && to.value) {
        loading.value = true;
        let data = await getRuleDataSeller(props.user_id, from.value, to.value);
        loading.value = false;
        if (data !== null) {
            additional_sales_commissions.value =
                data?.result?.applied_rules?.additional_sales_commissions ??
                null;
        }
    }
};

const disablePayment = computed(() => {
    if (
        !additional_sales_commissions.value ||
        additional_sales_commissions.value["LVA"].length === 0 ||
        additional_sales_commissions.value["LVA"].filter(
            (a) => a.state === "pendiente"
        ).length === 0
    ) {
        return true;
    }
    return false;
});

const onCreatedPayment = (sales) => {
    console.log(sales);

    additional_sales_commissions.value["LVA"].forEach((s) => {
        if (sales.includes(s.id)) {
            s.state = "pagada";
        }
    });
    showModalPayment.value = false;
};
</script>
