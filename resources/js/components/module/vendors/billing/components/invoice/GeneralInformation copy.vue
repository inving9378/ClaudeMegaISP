<template>
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
                    <q-tab name="general-commissions" label="Datos generales" />
                    <q-tab name="monthly-commissions" label="Bono mensual" />
                </q-tabs>

                <q-tab-panels
                    v-model="tab"
                    animated
                    :dark="darkMode"
                    class="q-pa-sm"
                >
                    <q-tab-panel name="general-commissions" class="no-padding">
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
                            :clearable="false"
                            :dark="darkMode"
                            :disabled-dates="(date) => diableDates(date, p)"
                            class="q-mt-md"
                            @update:model-value="onChangePeriod(p, true)"
                        >
                        </VueDatePicker>

                        <div class="row q-pt-md no-gutter-x">
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
                                        !darkMode ? 'text-black bg-green-2' : ''
                                    "
                                >
                                    <q-toolbar-title
                                        >Datos generales</q-toolbar-title
                                    >
                                </q-toolbar>
                                <q-list dense>
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
                                                        : p.data.type_of_seller
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
                                            Salario a pagar
                                        </q-item-section>
                                        <q-item-section avatar>
                                            {{
                                                Math.round(
                                                    (p.result?.salary ?? 0) *
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
                                        >Desglose por
                                        comisiones</q-toolbar-title
                                    >
                                </q-toolbar>
                                <q-list dense>
                                    <template
                                        v-for="r in Object.keys(
                                            p.result.applied_rules
                                        )"
                                        :key="`rule-${p.id}-${r}`"
                                    >
                                        <q-expansion-item
                                            :label="getLabel(r)"
                                            :caption="
                                                p.data[fieldsRules[r].name]
                                            "
                                            :dark="darkMode"
                                            group="rules-desglose"
                                            v-if="p.result.applied_rules[r]"
                                        >
                                            <q-card>
                                                <q-card-section
                                                    class="q-mx-sm q-py-none"
                                                >
                                                    <q-list dense>
                                                        <q-item
                                                            v-for="key in Object.keys(
                                                                p.result
                                                                    .applied_rules[
                                                                    r
                                                                ]
                                                            )"
                                                            :key="`rule-${p.id}-${r}-${key}`"
                                                            :style="{
                                                                padding:
                                                                    key ===
                                                                    'Regla(s) aplicada(s)'
                                                                        ? '0px'
                                                                        : null,
                                                            }"
                                                        >
                                                            <template
                                                                v-if="
                                                                    key ===
                                                                    'LVA'
                                                                "
                                                            >
                                                                <q-list dense>
                                                                    <q-item
                                                                        v-for="client in p
                                                                            .result
                                                                            .applied_rules[
                                                                            r
                                                                        ][key]"
                                                                        :key="`client-${client.label}`"
                                                                    >
                                                                        <q-item-section>
                                                                            {{
                                                                                client.label
                                                                            }}
                                                                        </q-item-section>
                                                                        <q-item-section
                                                                            avatar
                                                                        >
                                                                            <span
                                                                                class="text-primary"
                                                                                v-if="
                                                                                    client.state ===
                                                                                    'pagada'
                                                                                "
                                                                                >PAGADA</span
                                                                            >
                                                                            <span
                                                                                class="text-danger"
                                                                                v-else-if="
                                                                                    client.state ===
                                                                                    'descontada'
                                                                                "
                                                                                >DESCONTADA</span
                                                                            >
                                                                            <span
                                                                                class="text-success"
                                                                                v-else
                                                                                >PENDIENTE</span
                                                                            >
                                                                        </q-item-section>
                                                                    </q-item>
                                                                </q-list>
                                                            </template>
                                                            <template
                                                                v-else-if="
                                                                    key ===
                                                                    'LVC'
                                                                "
                                                            >
                                                                <q-list dense>
                                                                    <q-item
                                                                        v-for="client in p
                                                                            .result
                                                                            .applied_rules[
                                                                            r
                                                                        ][key]"
                                                                        :key="`client-${client}`"
                                                                    >
                                                                        <q-item-section>
                                                                            {{
                                                                                client
                                                                            }}
                                                                        </q-item-section>
                                                                    </q-item>
                                                                </q-list>
                                                            </template>
                                                            <template
                                                                v-else-if="
                                                                    key ===
                                                                    'Regla(s) aplicada(s)'
                                                                "
                                                            >
                                                                <q-list dense>
                                                                    <q-item>
                                                                        <q-item-section
                                                                            class="text-bold"
                                                                        >
                                                                            Criterios
                                                                            aplicados
                                                                        </q-item-section>
                                                                        <q-item-section
                                                                            avatar
                                                                        >
                                                                            {{
                                                                                p
                                                                                    .result
                                                                                    .applied_rules[
                                                                                    r
                                                                                ][
                                                                                    key
                                                                                ]
                                                                                    .length
                                                                            }}
                                                                        </q-item-section>
                                                                    </q-item>
                                                                    <q-item
                                                                        v-for="(
                                                                            ruleApplied,
                                                                            indexRuleApplied
                                                                        ) in p
                                                                            .result
                                                                            .applied_rules[
                                                                            r
                                                                        ][key]"
                                                                        :key="`key-${key}-${indexRuleApplied}`"
                                                                    >
                                                                        <q-item-section
                                                                            class="q-ml-md"
                                                                        >
                                                                            {{
                                                                                ruleApplied.name
                                                                            }}
                                                                        </q-item-section>
                                                                        <q-item-section
                                                                            avatar
                                                                        >
                                                                            {{
                                                                                ruleApplied.sales
                                                                            }}
                                                                            venta(s)
                                                                        </q-item-section>
                                                                    </q-item>
                                                                </q-list>
                                                            </template>
                                                            <template
                                                                v-else-if="
                                                                    key !==
                                                                    'sales_amount'
                                                                "
                                                            >
                                                                <q-item-section
                                                                    class="text-bold"
                                                                >
                                                                    {{ key }}
                                                                </q-item-section>
                                                                <q-item-section
                                                                    avatar
                                                                >
                                                                    {{
                                                                        getRoundValue(
                                                                            p.result,
                                                                            r,
                                                                            key
                                                                        )
                                                                    }}
                                                                </q-item-section>
                                                            </template>
                                                        </q-item>
                                                    </q-list>
                                                </q-card-section>
                                            </q-card>
                                        </q-expansion-item>
                                    </template>
                                </q-list>
                            </div>
                        </div>
                    </q-tab-panel>
                    <q-tab-panel name="monthly-commissions" class="no-padding">
                        <div class="object-field q-mt-sm" v-if="hasMonthlyRule">
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
                                                          monthlyBonus[m.name][
                                                              "Total a pagar"
                                                          ] * 100
                                                      ) / 100
                                                    : 0
                                                : 0
                                        }}
                                    </q-item-section>
                                </template>
                                <q-card v-if="monthlyBonus">
                                    <q-card-section class="q-mx-sm q-py-none">
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
                                                        key === "Regla aplicada"
                                                            ? "Criterio aplicado"
                                                            : key
                                                    }}
                                                </q-item-section>
                                                <q-item-section avatar>
                                                    {{
                                                        key ===
                                                            "Regla aplicada" &&
                                                        monthlyBonus[m.name][
                                                            "rules"
                                                        ].length > 1
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
                                        style="padding-right: 40px !important"
                                    >
                                        ${{
                                            monthlyBonus["total"]
                                                ? Math.round(
                                                      monthlyBonus["total"] *
                                                          100
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
</template>

<script setup>
import { defineComponent, onMounted, ref, watch } from "vue";
import {
    getPeriodsFromSeller,
    getRuleDataSeller,
    getMontlyCommissionsBySeller,
    date,
    from,
    to,
    errorMessage,
    currentYearMonthlyBonus,
    months,
    years,
    fieldsRules,
    diableDatesFromWeek,
} from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../../../hook/appConfig";
import moment from "moment";
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
const loadingGeneralInformation = ref(false);
const tab = ref("general-commissions");
const result = ref(null);
const defaultResult = ref(null);
const rule = ref(null);
const sellersType = ref([]);
const totalSalary = ref(0);

const monthlyBonus = ref(null);
const hasGeneralRule = ref(false);
const hasMonthlyRule = ref(false);

const periods = ref([]);

onMounted(async () => {
    loadPeriods();
    //loadData();
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

watch(
    () => periods.value,
    (n) => {
        loadData();
    }
);

const diableDates = (date, period) => {
    let start = moment(period.start);
    let end = moment(period.end);
    if (start.day() !== 1) {
        start = start.subtract(6 - start.day(), "days");
    }
    if (end.day() !== 6) {
        end = end.subtract(7 - end.day(), "days");
    }
    return date.isAfter(end, "day") || date.isBefore(start, "day");
};

const onChangePeriod = async (p, force = false) => {
    if ((p.opened && p.result === null) || force) {
        p.loading = true;
        const data = await getRuleDataSeller(
            props.user_id,
            moment(p.period[0]).format("YYYY-MM-DD"),
            moment(p.period[1]).format("YYYY-MM-DD"),
            p
        );
        p.loading = false;
        if (data !== null) {
            p.result = data.result;
        }
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
    loadingGeneralInformation.value = true;
    let data = await getPeriodsFromSeller(props.seller_id);
    loadingGeneralInformation.value = false;
    if (data !== null) {
        const { rules, sellers_type } = data;
        sellersType.value = sellers_type;
        for (let i = 0; i < rules.length; i++) {
            periods.value.push({
                ...rules[i],
                start: rules[i].created_at,
                end: rules[i + 1] ? rules[i + 1].created_at : moment(),
                result: null,
                opened: i === 0,
                loading: false,
                period: [
                    rules[i].created_at,
                    rules[i + 1] ? rules[i + 1].created_at : moment(),
                ],
            });
        }
        onChangePeriod(periods.value[0]);
    }
};

const loadData = async () => {
    if (from.value && to.value) {
        loadingGeneralInformation.value = true;
        let data = await getRuleDataSeller(props.user_id, from.value, to.value);
        loadingGeneralInformation.value = false;
        if (data !== null) {
            hasGeneralRule.value = data.result.rule !== null;
            result.value = data.result;
            defaultResult.value = data.result;
            rule.value = Array.isArray(data.result.rule)
                ? null
                : data.result.rule;
            sellersType.value = data.sellers_type;
            calculateSalary();
            emits("change-montly-bonus", "general", data.result);
        }
    } else {
        resetValues();
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

const resetValues = () => {
    let applied = result.value.applied_rules;
    if (applied) {
        Object.keys(applied).forEach((r) => {
            if (applied[r]) {
                Object.keys(applied[r]).forEach((r1) => {
                    applied[r][r1] = !isNaN(applied[r][r1]) ? 0 : "...";
                });
            }
        });
    }
    result.value = {
        sales: 0,
        prospects: 0,
        salary: 0,
        applied_rules: applied,
    };
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

const getRoundValue = (result, name, key) => {
    if (!isNaN(result?.applied_rules[name][key])) {
        return getRound(result?.applied_rules[name][key]);
    }
    return result?.applied_rules[name][key];
};

const getRound = (val) => {
    if (!isNaN(val)) {
        return Math.round(val * 100) / 100;
    }
    return parseFloat("0");
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
