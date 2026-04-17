<template>
    <div style="min-height: 300px">
        <q-list bordered>
            <q-expansion-item
                v-for="(p, index) in periods"
                :key="`period-${p.id}`"
                group="somegroup"
                icon="calendar_month"
                :label="`${moment(p.start).format('DD/MM/YYYY')} - ${moment(
                    p.end
                ).format('DD/MM/YYYY')}`"
                :default-opened="index === 0"
                header-class="text-primary"
                v-model="p.opened"
                @update:model-value="() => onChangePeriod(p)"
            >
                <q-card>
                    <q-tabs
                        :dark="darkMode"
                        v-model="tab"
                        dense
                        no-caps
                        ñ
                        active-color="primary"
                        indicator-color="primary"
                        align="justify"
                        content-class="no-gutter-x width-auto"
                    >
                        <q-tab
                            name="general-commissions"
                            label="Datos generales"
                        />
                        <q-tab
                            name="monthly-commissions"
                            label="Bono mensual"
                        />
                    </q-tabs>

                    <q-tab-panels
                        v-model="tab"
                        animated
                        :dark="darkMode"
                        class="q-pa-sm"
                    >
                        <q-tab-panel
                            name="general-commissions"
                            class="no-padding"
                        >
                            <VueDatePicker
                                v-model="p.period"
                                position="right"
                                locale="es"
                                :teleport="true"
                                placeholder="Fecha"
                                range
                                week-start="0"
                                :format="customFormat"
                                :enable-time-picker="false"
                                :dark="darkMode"
                                :disabled-dates="(date) => diableDates(date, p)"
                                class="q-mt-md"
                                @update:model-value="onChangePeriod(p, true)"
                            >
                            </VueDatePicker>
                            <div class="row q-py-md no-gutter-x">
                                <div
                                    class="col-xs-12 col-sm-12"
                                    :class="
                                        p.result?.applied_rules
                                            ? 'col-md-6 col-lg-6 col-xl-6'
                                            : 'col-md-12 col-lg-12 col-xl-12'
                                    "
                                    style="padding: 0x 6px !important"
                                >
                                    <q-toolbar
                                        class="shadow-2"
                                        :class="
                                            !darkMode
                                                ? 'text-black bg-green-2'
                                                : ''
                                        "
                                    >
                                        <q-toolbar-title
                                            >Datos generales</q-toolbar-title
                                        >
                                    </q-toolbar>
                                    <q-list dense bordered>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Regla aplicada
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{ p.data.name }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Tipo de vendedor
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{
                                                    sellersType.find((s) =>
                                                        s.value ===
                                                        (typeof p.data
                                                            .type_of_seller ===
                                                            "string")
                                                            ? parseInt(
                                                                  p.data
                                                                      .type_of_seller
                                                              )
                                                            : p.data
                                                                  .type_of_seller
                                                    )["label"]
                                                }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Ventas realizadas
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{ p.result?.sales ?? 0 }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Prospectos obtenidos
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{ p.result?.prospects ?? 0 }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                IVA
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{ p.data?.iva ?? "..." }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Zona
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{ p.data?.zone ?? "..." }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Costo de instalación
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{
                                                    p.data?.installation_cost ??
                                                    "..."
                                                }}
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section class="text-bold">
                                                Por pagar
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{
                                                    Math.round(
                                                        p.pending * 100
                                                    ) / 100
                                                }}
                                            </q-item-section> </q-item
                                        ><q-item>
                                            <q-item-section class="text-bold">
                                                Pagado
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{
                                                    Math.round(
                                                        p.payment * 100
                                                    ) / 100
                                                }}
                                            </q-item-section> </q-item
                                        ><q-item>
                                            <q-item-section class="text-bold">
                                                Salario total
                                            </q-item-section>
                                            <q-item-section avatar>
                                                {{
                                                    Math.round(
                                                        (p.pending +
                                                            p.payment) *
                                                            100
                                                    ) / 100
                                                }}
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </div>
                                <div
                                    class="col-xs-12 col-sm-12 col-md-6 col-lg-6 col-xl-6"
                                    style="padding: 0px 6px !important"
                                    v-if="p.result?.applied_rules"
                                >
                                    <q-toolbar
                                        class="shadow-2"
                                        :class="
                                            !darkMode
                                                ? 'text-black bg-green-2'
                                                : null
                                        "
                                    >
                                        <q-toolbar-title
                                            >Criterios
                                            aplicados</q-toolbar-title
                                        >
                                    </q-toolbar>
                                    <q-list bordered>
                                        <q-item
                                            v-for="r in Object.keys(
                                                p.result.applied_rules
                                            )"
                                            :key="`rule-${p.id}-${r}`"
                                        >
                                            <q-item-section>
                                                <q-item-label>
                                                    {{ getLabel(r) }}
                                                </q-item-label>
                                                <q-item-label caption>
                                                    {{
                                                        p.data[
                                                            fieldsRules[r].name
                                                        ]
                                                    }}
                                                </q-item-label>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </div>
                            </div>

                            <q-toolbar
                                class="shadow-2"
                                :class="
                                    !darkMode ? 'text-black bg-green-2' : null
                                "
                            >
                                <q-toolbar-title
                                    >Desglose por período</q-toolbar-title
                                >
                            </q-toolbar>
                            <general-payment-table
                                :columns="visibleColumns"
                                :rows="p.filtered_payments"
                                @change-data="
                                    (salary) => {
                                        p.pending = salary.pending;
                                        p.payment = salary.payment;
                                    }
                                "
                            />
                        </q-tab-panel>
                        <q-tab-panel
                            name="monthly-commissions"
                            class="no-padding"
                        >
                            <div
                                class="object-field q-mt-sm"
                                v-if="hasMonthlyRule"
                            >
                                <label for="object-monthly-commissions"
                                    >Seleccione el año</label
                                >
                                <q-select
                                    v-model="currentYearMonthlyBonus"
                                    dense
                                    outlined
                                    clearable
                                    options-dense
                                    :options="years()"
                                    emit-value
                                    map-options
                                    for="object-monthly-commissions"
                                    :dark="darkMode"
                                />
                            </div>
                            <div v-else>
                                <p class="text-red">
                                    No existe regla asociada al vendedor
                                </p>
                            </div>
                            <q-list dense class="q-mt-md">
                                <q-expansion-item
                                    v-for="(m, indexMonth) in months"
                                    :key="`month-${indexMonth}`"
                                    group="monthly-bonus"
                                    style="min-height: 28px !important"
                                    dense
                                >
                                    <template #header>
                                        <q-item-section>
                                            {{ m.label }}
                                        </q-item-section>
                                        <q-item-section avatar>
                                            ${{
                                                monthlyBonus
                                                    ? !isNaN(
                                                          monthlyBonus[m.name][
                                                              "Total a pagar"
                                                          ]
                                                      )
                                                        ? Math.round(
                                                              monthlyBonus[
                                                                  m.name
                                                              ][
                                                                  "Total a pagar"
                                                              ] * 100
                                                          ) / 100
                                                        : 0
                                                    : 0
                                            }}
                                        </q-item-section>
                                    </template>
                                    <q-card v-if="monthlyBonus">
                                        <q-card-section
                                            class="q-mx-sm q-py-none"
                                        >
                                            <q-list dense>
                                                <q-item
                                                    v-for="(
                                                        key, indexObject
                                                    ) in Object.keys(
                                                        monthlyBonus[m.name]
                                                    )"
                                                    :key="`key-${key}-${indexObject}`"
                                                    :style="{
                                                        display:
                                                            key === 'rules'
                                                                ? 'none'
                                                                : 'flex',
                                                    }"
                                                >
                                                    <q-item-section
                                                        class="text-bold"
                                                    >
                                                        {{
                                                            key ===
                                                            "Regla aplicada"
                                                                ? "Criterio aplicado"
                                                                : key
                                                        }}
                                                    </q-item-section>
                                                    <q-item-section avatar>
                                                        {{
                                                            key ===
                                                                "Regla aplicada" &&
                                                            monthlyBonus[
                                                                m.name
                                                            ]["rules"].length >
                                                                1
                                                                ? "..."
                                                                : monthlyBonus[
                                                                      m.name
                                                                  ][key]
                                                        }}
                                                    </q-item-section>
                                                </q-item>
                                            </q-list>
                                        </q-card-section>
                                    </q-card>
                                </q-expansion-item>
                                <q-item v-if="monthlyBonus">
                                    <q-item-section class="text-bold">
                                        Total
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <q-item-label
                                            class="text-bold"
                                            style="
                                                padding-right: 40px !important;
                                            "
                                        >
                                            ${{
                                                monthlyBonus["total"]
                                                    ? Math.round(
                                                          monthlyBonus[
                                                              "total"
                                                          ] * 100
                                                      ) / 100
                                                    : 0
                                            }}
                                        </q-item-label>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </q-tab-panel>
                    </q-tab-panels>
                    <q-inner-loading
                        :showing="p.loading"
                        label="Actualizando..."
                        label-style="font-size: 1.1em"
                        :dark="darkMode"
                    />
                </q-card>
            </q-expansion-item>
        </q-list>

        <p class="text-h6 text-danger" v-if="!hasRule">
            No exiten reglas asociadas a este vendedor
        </p>

        <q-inner-loading
            :showing="loading"
            label="Obteniendo reglas..."
            label-style="font-size: 1.1em"
            :dark="darkMode"
        />
    </div>

    <modal
        :show="showModal"
        :size="'xs'"
        @update:show="showModal = $event"
        title="Mostrar columnas/Ocultar columnas"
    >
        <template #body>
            <div class="my-3">
                <p>
                    Para mostrar los campos de la tabla, seleccione la casilla
                    de verificación correspondiente.
                </p>
            </div>
            <div
                class="form-check form-switch form-switch-md"
                v-for="(column, index) in columns"
                :key="index"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="column.visible"
                />
                <label class="form-check-label">{{ column.label }}</label>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="saveColumnsTable">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
import { computed, defineComponent, onMounted, ref, watch } from "vue";
import Modal from "../../../../../../shared/ModalSimple.vue";
import GeneralPaymentTable from "./GeneralPaymentTable.vue";
import {
    getPeriodsFromSeller,
    getRuleDataSeller,
    getMontlyCommissionsBySeller,
    date,
    from,
    to,
    currentYearMonthlyBonus,
    months,
    years,
    fieldsRules,
} from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../../../hook/appConfig";
import moment from "moment";
import { message } from "../../../../../../helpers/toastMsg";
import { useDataTable } from "../../../../../../composables/useDataTable";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

defineComponent({
    name: "DataSeller",
});

const emits = defineEmits([
    "change-montly-bonus",
    "reloaded-data",
    "enable-disable-payment",
]);

const { customFormat } = useDatePicker();

const props = defineProps({
    user_id: String | Number,
    seller_id: {
        type: Number,
        required: true,
    },
    hasEdit: {
        type: Boolean,
        default: false,
    },
});
const showModal = ref(false);
const tab = ref("general-commissions");
const result = ref(null);
const sellersType = ref([]);
const totalSalary = ref(0);

const monthlyBonus = ref(null);
const hasGeneralRule = ref(false);
const hasMonthlyRule = ref(false);

const periods = ref([]);

const tableIdentifier = ref("invoice-general-payment-seller");
const { getColumns, saveColumns } = useDataTable();

const loading = ref(false);
const hasRule = ref(true);

const columns = ref([
    {
        name: "period",
        field: "period",
        label: "Período",
        align: "left",
        sortable: true,
        format: (val, row) => {
            return `${moment(row.from).format("DD/MM/YYYY")} - ${moment(
                row.to
            ).format("DD/MM/YYYY")}`;
        },
        sort: (a, b, rowA, rowB) => {
            return parseInt(rowA.index, 10) - parseInt(rowB.index, 10);
        },
    },
    {
        name: "sales",
        field: "sales",
        label: "Ventas",
        align: "center",
        sortable: true,
    },
    {
        name: "prospects",
        field: "prospects",
        label: "Prospectos",
        align: "center",
        sortable: true,
    },
    {
        name: "fixed_salary",
        field: "fixed_salary",
        label: "Salario fijo",
        align: "left",
        sortable: false,
    },
    {
        name: "sales_commission",
        field: "sales_commission",
        label: "Comisión por venta",
        align: "left",
        sortable: false,
    },
    {
        name: "additional_sales_commissions",
        field: "additional_sales_commissions",
        label: "Comisión por venta adicional",
        align: "left",
        sortable: false,
    },
    {
        name: "distributors_commission",
        field: "distributors_commission",
        label: "Comisión distribuidores",
        align: "left",
        sortable: false,
    },
    {
        name: "salary",
        field: "salary",
        label: "Salario final",
        align: "left",
        sortable: true,
    },
]);

onMounted(async () => {
    getColumnsTable();
    loadPeriods();
    //loadMonthlyBonus();
});

watch(hasGeneralRule, (n) => {
    enableDisablePayment();
});

watch(hasMonthlyRule, (n) => {
    enableDisablePayment();
});

watch(currentYearMonthlyBonus, (n) => {
    loadMonthlyBonus();
});

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const getColumnsTable = async () => {
    try {
        const response = await getColumns(tableIdentifier.value);
        const storedColumns = response;
        if (storedColumns && storedColumns.length > 0) {
            columns.value.forEach((column) => {
                const storedColumn = storedColumns.find(
                    (col) => col.name === column.name
                );
                if (storedColumn) {
                    column.visible = storedColumn.visible;
                }
            });
        } else {
            columns.value.forEach((column) => {
                column.visible = true;
            });
        }
    } catch (error) {
        console.log(error);
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const diableDates = (date, period) => {
    let start = moment(period.start);
    let end = moment(period.end);
    if (start.day() !== 1) {
        start = start.subtract(6 - start.day(), "days");
    }
    if (end.day() !== 6) {
        end = end.subtract(7 - end.day(), "days");
    }
    date = moment(date);
    return (
        date.isAfter(end, "day") ||
        date.isBefore(start, "day") ||
        (date.day() !== 0 && date.day() !== 6)
    );
};

const onChangePeriod = async (p, force = false) => {
    const start = moment(p.period ? p.period[0] : p.start),
        end = moment(p.period ? p.period[1] : p.end);
    if ((start.day() !== 0 || end.day() !== 6) && p.period) {
        message(
            "Período incorrecto; debe empezar un domingo y terminar un sábado",
            "error"
        );
        return;
    } else if (p.opened && p.result === null) {
        p.loading = true;
        const data = await getRuleDataSeller(
            props.user_id,
            start.format("YYYY-MM-DD"),
            end.format("YYYY-MM-DD"),
            true
        );
        p.loading = false;
        if (data !== null) {
            p.result = data.result;
            p.filtered_payments = data.result.payments;
            p.all_payments = data.result.payments;
        } else {
            p.result = null;
            p.filtered_payments = [];
            p.all_payments = [];
        }
    } else if (force) {
        p.filtered_payments = p.all_payments.filter(
            (p) =>
                moment(p.from).isSameOrAfter(start, "day") &&
                moment(p.to).isSameOrBefore(end, "day")
        );
    }
};

const enableDisablePayment = () => {
    emits("enable-disable-payment", hasGeneralRule.value || hasMonthlyRule);
};

const calculateSalary = () => {
    let total = 0;
    if (result.value?.salary > 0) {
        total += result.value.salary;
    }
    if (monthlyBonus.value?.total > 0) {
        total += monthlyBonus.value.total;
    }
    totalSalary.value = Math.round(total * 100) / 100;
};

const loadPeriods = async () => {
    loading.value = true;
    let data = await getPeriodsFromSeller(props.seller_id);
    loading.value = false;
    if (data !== null) {
        const { rules, sellers_type } = data;
        sellersType.value = sellers_type;
        for (let i = 0; i < rules.length; i++) {
            periods.value.push({
                ...rules[i],
                result: null,
                opened: i === 0,
                loading: false,
                period: null,
                filtered_payments: [],
                all_payments: [],
                pending: 0,
                payment: 0,
            });
        }
        if (periods.value.length > 0) {
            onChangePeriod(periods.value[0]);
        } else {
            hasRule.value = false;
        }
    }
};

const loadMonthlyBonus = async () => {
    let data = await getMontlyCommissionsBySeller(
        props.user_id,
        currentYearMonthlyBonus.value,
        "monthly-bonus"
    );
    if (data !== null) {
        hasMonthlyRule.value = data.result.rule !== null;
        monthlyBonus.value = data.result;
        emits("change-montly-bonus", "monthly", data.result);
        calculateSalary();
    }
};

const getLabel = (prop) => {
    let { label } = fieldsRules[prop];
    let txt = "";
    if (result.value) {
        let val = result.value.applied_rules[prop]["Total a pagar"];
        if (!isNaN(val)) {
            txt = ` ($${Math.round(val * 100) / 100})`;
        }
    }
    return `${label}${txt}`;
};
</script>

<style>
.row.no-gutter-x,
.q-toolbar.row,
.q-item.row,
.q-tabs.row,
.object-field .q-checkbox.row.disabled {
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
