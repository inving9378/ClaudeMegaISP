<template>
    <div class="row no-padding">
        <q-tabs
            v-model="activeTab"
            dense
            no-caps
            inline-label
            align="justify"
            :dark="darkMode"
            content-class="no-gutter-x"
            :class="!darkMode ? 'bg-grey-3 text-grey-7' : null"
            active-color="primary"
            indicator-color="primary"
            style="flex-shrink: inherit !important"
            @update:model-value="(tab) => setActiveTab('activeFactureTab', tab)"
        >
            <q-tab label="Lista de clientes" name="tab-client" />
            <q-tab label="Información general" name="tab-general-information" />
            <q-tab label="Facturas" name="tab-facture" />
            <q-tab label="Penalizaciones" name="tab-debt" />
            <q-tab label="Pagos" name="tab-payments" />
            <q-tab label="Comisiones" name="tab-commissions" />
        </q-tabs>
        <q-tab-panels
            v-model="activeTab"
            animated
            :dark="darkMode"
            style="padding: 0px !important"
        >
            <q-tab-panel
                name="tab-client"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <list-customers :user_id="user_id" />
            </q-tab-panel>
            <q-tab-panel
                name="tab-general-information"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <general-information
                    :user_id="user_id"
                    :seller_id="seller_id"
                    :has-edit="hasPermission.data.canView('seller_add_payment')"
                />
            </q-tab-panel>
            <q-tab-panel name="tab-facture"> </q-tab-panel>
            <q-tab-panel
                name="tab-debt"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <debt-component
                    :user="user_id"
                    :seller_id="seller_id"
                    :has-edit="hasPermission.data.canView('seller_add_payment')"
                />
            </q-tab-panel>
            <q-tab-panel
                name="tab-payments"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <payments-component
                    :user="user_id"
                    :seller_id="seller_id"
                    :has-edit="hasPermission.data.canView('seller_add_payment')"
                />
            </q-tab-panel>
            <q-tab-panel
                name="tab-commissions"
                style="padding: 5px 2px; --bs-gutter-x: 0px !important"
            >
                <commissions-list
                    :user_id="user_id"
                    :seller_id="seller_id"
                    :has-edit="hasPermission.data.canView('seller_add_payment')"
                />
            </q-tab-panel>
        </q-tab-panels>
    </div>
    <!-- <div class="col-md-12">
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="row" style="width: 100% !important">
                    <div class="col">
                        <h3>Datos de los Vendedores</h3>
                    </div>
                    <div class="col text-right" v-if="paymentEnable">
                        <button
                            v-if="
                                hasPermission.data.canView('seller_add_payment')
                            "
                            class="btn btn-primary"
                            @click="openModal"
                        >
                            Crear pago
                        </button>
                    </div>
                    <DataSeller
                        :seller_id="seller_id"
                        :user_id="user_id"
                        :reload-data="reloadData"
                        :has-edit="
                            hasPermission.data.canView('seller_add_payment')
                        "
                        @reloaded-data="reloadData = null"
                        @change-montly-bonus="
                            (name, val) => (bonus[name] = val)
                        "
                        @enable-disable-payment="(val) => (paymentEnable = val)"
                    />
                </div>
            </q-card-section>
        </q-card>

        <AddPayment
            :seller_id="seller_id"
            v-model:showModal="showModalPayment"
            :bonus="bonus"
            @created="(reload) => (reloadData = reload)"
            v-if="paymentEnable"
        />
    </div> -->
</template>

<script setup>
import { ref, onMounted, reactive, watch, onBeforeMount } from "vue";
import Permission from "../../../../helpers/Permission.js";
import { allViewHasPermission } from "../../../../helpers/Request.js";
import { darkMode, setActiveTab } from "../../../../hook/appConfig.js";

import moment from "moment";

import ListCustomers from "./ListCustomers.vue";
import DebtComponent from "./components/invoice/DebtComponent.vue";
import GeneralInformation from "./components/invoice/GeneralInformation.vue";
import CommissionsList from "./components/invoice/CommissionsList.vue";
import PaymentsComponent from "./components/invoice/PaymentsComponent.vue";

import { date, from, to, errorMessage } from "./helper/helper.js";
import { useDatePicker } from "../../../../composables/useDatePicker.js";

const props = defineProps({
    user_id: Number,
    seller_id: Number,
});

const { customFormat } = useDatePicker();

const showModalPayment = ref(false);
const bonus = ref({
    general: null,
    monthly: null,
});
const hasPermission = reactive({
    data: new Permission({}),
});
const paymentEnable = ref(false);
const reloadData = ref(null);

const activeTab = ref(localStorage.getItem("activeFactureTab") || "tab-client");

onBeforeMount(() => {
    const { start, end } = getLastWeekCompleted();
    date.value = [start.toDate(), end.toDate()];
});

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
});

watch(date, (n) => {
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
            }
        }
    }
};

const openModal = () => {
    showModalPayment.value = true;
};

function getLastWeekCompleted() {
    const toDay = moment();
    let start = null;
    let end = null;
    if (toDay.day() === 6) {
        start = toDay.clone().startOf("week");
        end = toDay;
    } else {
        const lastSaturday = toDay.clone().day(6).subtract(1, "week");
        start = lastSaturday.clone().startOf("week");
        end = lastSaturday;
    }
    date.value = [start.toDate(), end.toDate()];
    return {
        start,
        end,
    };
}
</script>

<style scoped>
.text-gray {
    color: #6c757d;
    font-weight: 500;
}

.text-red {
    color: red;
}

.nav-link {
    border: none;
    padding-bottom: 5px;
    border-radius: 10px;
    background-color: #ffff;
}

.nav-link.active {
    border-bottom: 2px solid #007bff;
}

.nav-link:hover {
    border-bottom: 2px solid rgba(0, 123, 255, 0.5);
}
.q-tabs__content--align-left .q-tab {
    width: auto !important;
}
.row.no-gutter-x,
.q-toolbar.row,
.q-item.row,
.q-tabs.row,
.object-field .q-checkbox.row.disabled,
.q-table__top.row {
    --bs-gutter-x: 0px !important;
}
.object-field .row {
    --bs-gutter-x: 0 !important;
    --bs-gutter-y: 0;
    -ms-flex-wrap: wrap !important;
    flex-wrap: inherit !important;
}
.q-field__after.row,
.q-field__prefix.row,
.q-btn__content.row,
.q-btn,
.q-item__section,
.q-checkbox__inner,
.q-checkbox__label,
.q-icon,
.q-tabs__content.row {
    width: auto !important;
}

.row.no-wrap {
    flex-wrap: nowrap !important;
}

.q-chip {
    padding: 0.5em 0.9em !important;
    margin: 4px !important;
}

.q-field__append {
    width: auto;
}

.q-field__bottom.row {
    padding-left: 0px !important;
}

.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip {
    width: auto !important;
    position: relative !important;
    right: 0px !important;
}

.q-icon.notranslate.material-icons.q-select__dropdown-icon.rotate-180,
.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip
    button {
    right: 0px !important;
}

.q-field__append span {
    width: 40px;
    background-color: #e9e9ef;
    text-align: center;
}

.q-field__native.d-flex {
    display: -webkit-box !important;
}
.q-field--auto-height .q-field__native,
.q-field--auto-height .q-field__prefix,
.q-field--auto-height .q-field__suffix {
    line-height: 26px !important;
}

.q-field--outlined .q-field__control {
    padding: 0 2px !important;
}
.q-field__control-container.row {
    margin-right: 10px !important;
}
#toast-container {
    z-index: 9999999 !important;
}
.q-field__control-container.row,
.q-field__control-container.row .q-field__native {
    padding-right: 0px !important;
}
</style>
