<template>
    <modal
        :show="props.showModal"
        :size="'lg'"
        @update:show="updateShow"
        :title="'Realizar pago'"
    >
        <template #body>
            <form>
                <div class="row q-mb-md">
                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <label for="payment_method_id" class="form-label"
                            >Método de pago</label
                        >
                        <select
                            class="form-select"
                            id="payment_method_id"
                            v-model="formData.payment_method_id"
                        >
                            <option
                                v-for="methodPayment in methodsPayments"
                                :key="methodPayment.id"
                                :value="methodPayment.id"
                            >
                                {{ methodPayment.type }}
                            </option>
                        </select>
                        <span
                            class="text-danger"
                            v-if="errors.includes('payment_method_id')"
                            >Requerido</span
                        >
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <label for="payment_date" class="form-label"
                            >Fecha de pago</label
                        >
                        <input
                            id="payment_date"
                            type="date"
                            class="form-control"
                            v-model="formData.payment_date"
                        />
                        <span
                            class="text-danger"
                            v-if="errors.includes('payment_date')"
                            >Requerido</span
                        >
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <label for="email" class="form-label"
                            >Número de recibo</label
                        >
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control"
                                v-model="formData.invoice_number"
                                readonly
                                disable
                            />
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                @click="createReceiptNumber"
                            >
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                        <span
                            class="text-danger"
                            v-if="errors.includes('invoice_number')"
                            >Requerido</span
                        >
                    </div>
                    <div
                        class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12"
                    >
                        <label for="comments" class="form-label"
                            >Comentarios</label
                        >
                        <textarea
                            v-model="formData.comments"
                            id="comments"
                            class="form-control"
                        ></textarea>
                    </div>
                </div>
                <q-tabs
                    v-model="tab"
                    dense
                    no-caps
                    class="bg-grey-3 text-grey-7 q-mt-md"
                    active-color="primary"
                    indicator-color="primary"
                    align="justify"
                    content-class="no-gutter-x width-auto"
                >
                    <q-tab
                        name="general-commissions"
                        :label="`Comisiones generales ${
                            totalGeneralBonus > 0
                                ? ` ($${totalGeneralBonus})`
                                : ''
                        }`"
                    />
                    <q-tab
                        name="monthly-bonus"
                        :label="`Bono mensual ${
                            totalMonthlyBonus > 0
                                ? ` ($${totalMonthlyBonus})`
                                : ''
                        }`"
                    />
                </q-tabs>

                <q-tab-panels v-model="tab" animated>
                    <q-tab-panel name="general-commissions">
                        <div class="object-field">
                            <label for="object-general-bonus-period"
                                >Período a pagar</label
                            >
                            <VueDatePicker
                                v-model="date"
                                position="right"
                                locale="es"
                                :teleport="true"
                                placeholder="Fecha"
                                range
                                week-start="0"
                                id="object-general-bonus-period"
                                :format="customFormat"
                            >
                            </VueDatePicker>
                            <div
                                class="d-flex justify-content-between text-danger"
                                v-if="errorMessage"
                            >
                                <label> {{ errorMessage }}</label>
                            </div>
                        </div>
                        <div class="object-field q-mt-md">
                            <label for="object-general-bonus"
                                >Comisiones a pagar</label
                            >
                            <q-select
                                v-model="formData.general_bonus"
                                dense
                                options-dense
                                outlined
                                :options="generalBonusOptions"
                                :option-disable="
                                    (opt) =>
                                        Object(opt) === opt
                                            ? opt.value ===
                                              'additional_sales_commissions'
                                            : true
                                "
                                multiple
                                use-chips
                                emit-value
                                map-options
                                clearable
                                for="object-general-bonus"
                                @clear="formData.general_bonus = []"
                            >
                                <template v-slot:selected-item="scope">
                                    <q-chip
                                        removable
                                        dense
                                        @remove="
                                            scope.removeAtIndex(scope.index)
                                        "
                                        :tabindex="scope.tabindex"
                                        class="q-ma-none"
                                    >
                                        {{
                                            scope.opt.value ===
                                                "additional_sales_commissions" &&
                                            formData.payment_sales.length > 0
                                                ? `Comisión por venta adicional (${amountFromSelectedSales})`
                                                : scope.opt.label
                                        }}
                                    </q-chip>
                                </template>
                                <template v-slot:option="scope">
                                    <q-item v-bind="scope.itemProps">
                                        <q-item dense>
                                            <q-item-section
                                                v-if="
                                                    scope.opt.value ===
                                                        'additional_sales_commissions' &&
                                                    formData.payment_sales
                                                        .length > 0
                                                "
                                                >Comisión por venta adicional
                                                (${{ amountFromSelectedSales }})
                                            </q-item-section>
                                            <q-item-section v-else
                                                >{{ scope.opt.label }}
                                            </q-item-section>
                                            <q-item-section avatar
                                                ><q-checkbox
                                                    v-model="
                                                        formData.general_bonus
                                                    "
                                                    :val="scope.opt.value"
                                                    v-if="
                                                        scope.opt.value !==
                                                        'additional_sales_commissions'
                                                    "
                                                />
                                            </q-item-section>
                                        </q-item>
                                    </q-item>
                                    <q-list
                                        dense
                                        v-if="
                                            scope.opt.value ===
                                            'additional_sales_commissions'
                                        "
                                    >
                                        <q-item>
                                            <q-item-section
                                                style="margin-left: 10px"
                                            >
                                                <q-btn
                                                    label="Seleccionar todas"
                                                    no-caps
                                                    class="full-width"
                                                    color="primary"
                                                    :disable="!selectAll"
                                                    @click="
                                                        selectAllSales(true)
                                                    "
                                                />
                                            </q-item-section>
                                            <q-item-section
                                                style="
                                                    margin-right: 40px !important;
                                                "
                                            >
                                                <q-btn
                                                    label="Desmarcar todas"
                                                    no-caps
                                                    class="full-width"
                                                    color="primary"
                                                    :disable="
                                                        formData.payment_sales
                                                            .length === 0
                                                    "
                                                    @click="
                                                        selectAllSales(false)
                                                    "
                                                />
                                            </q-item-section>
                                        </q-item>
                                        <q-item
                                            v-for="(
                                                item, indexitem
                                            ) in currentAdditionalSales"
                                            :key="`sales-${indexitem}`"
                                            :clickable="
                                                item.state === 'pendiente'
                                            "
                                            @click="
                                                item.selected = !item.selected
                                            "
                                        >
                                            <q-item-section
                                                style="margin-left: 10px"
                                                >{{ item.label }}
                                            </q-item-section>
                                            <q-item-section avatar>
                                                <span
                                                    class="text-primary"
                                                    v-if="
                                                        item.state === 'pagada'
                                                    "
                                                    >PAGADA</span
                                                >
                                                <span
                                                    class="text-danger"
                                                    v-else-if="
                                                        item.state ===
                                                        'descontada'
                                                    "
                                                    >DESCONTADA</span
                                                >
                                                <q-checkbox
                                                    v-model="item.selected"
                                                    :val="item.id"
                                                    v-else
                                                />
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </template>
                            </q-select>
                        </div>
                    </q-tab-panel>

                    <q-tab-panel name="monthly-bonus"
                        ><div class="object-field">
                            <label for="object-monthly-bonus"
                                >Período a pagar</label
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
                            />
                        </div>
                        <div class="object-field q-mt-md">
                            <label for="object-monthly-bonus"
                                >Comisiones a pagar</label
                            >
                            <q-select
                                v-model="formData.monthly_bonus"
                                dense
                                options-dense
                                outlined
                                :options="monthlyBonusOptions"
                                multiple
                                use-chips
                                emit-value
                                map-options
                                clearable
                                for="object-monthly-bonus"
                                @clear="formData.monthly_bonus = []"
                            >
                                <template v-slot:option="scope">
                                    <q-item v-bind="scope.itemProps">
                                        <q-item dense>
                                            <q-item-section
                                                >{{ scope.opt.label }}
                                            </q-item-section>
                                            <q-item-section avatar
                                                ><q-checkbox
                                                    v-model="
                                                        formData.monthly_bonus
                                                    "
                                                    :val="scope.opt.value"
                                                />
                                            </q-item-section>
                                        </q-item>
                                    </q-item>
                                </template>
                            </q-select>
                        </div>
                    </q-tab-panel>
                </q-tab-panels>
                <div
                    class="row"
                    v-if="
                        formData.monthly_bonus?.length > 0 ||
                        formData.general_bonus?.length > 0 ||
                        formData.payment_sales?.length > 0
                    "
                >
                    <h5 class="text-center my-3">Detalles del pago</h5>
                    <q-list
                        dense
                        class="q-mb-lg"
                        v-if="
                            formData.general_bonus?.length > 0 ||
                            formData.payment_sales.length > 0
                        "
                    >
                        <q-item class="bg-grey-2 text-bold">
                            <q-item-section> Bonos generales </q-item-section>
                            <q-item-section avatar>
                                <q-item-label
                                    style="padding-right: 40px !important"
                                >
                                    ${{ totalGeneralBonus }}
                                </q-item-label>
                            </q-item-section>
                        </q-item>
                        <template v-if="formData.general_bonus?.length > 0">
                            <template
                                v-for="(r, indexRule) in formData.general_bonus"
                                :key="`index-rule-${indexRule}`"
                            >
                                <q-expansion-item
                                    :label="getLabel(r)"
                                    :caption="
                                        generalBonusRule
                                            ? generalBonusRule[
                                                  fieldsRules[r].name
                                              ]
                                            : null
                                    "
                                    dense
                                    group="rules-desglose"
                                    v-if="
                                        generalBonus.applied_rules[r] !== null
                                    "
                                >
                                    <q-card>
                                        <q-card-section
                                            class="q-mx-sm q-py-none"
                                        >
                                            <q-list dense>
                                                <q-item
                                                    v-for="(
                                                        key, indexObject
                                                    ) in Object.keys(
                                                        generalBonus
                                                            .applied_rules[r]
                                                    )"
                                                    :key="`key-${key}-${indexObject}`"
                                                    :style="{
                                                        padding:
                                                            key ===
                                                            'Regla(s) aplicada(s)'
                                                                ? '0px'
                                                                : null,
                                                    }"
                                                >
                                                    <template
                                                        v-if="key === 'LVA'"
                                                    >
                                                        <q-list dense>
                                                            <q-item
                                                                v-for="(
                                                                    client,
                                                                    indexClient
                                                                ) in generalBonus
                                                                    .applied_rules[
                                                                    r
                                                                ][key]"
                                                                :key="`key-${key}-${indexClient}`"
                                                            >
                                                                <q-item-section>
                                                                    {{
                                                                        client.label
                                                                    }}
                                                                </q-item-section>
                                                                <q-item-section
                                                                    avatar
                                                                    v-if="
                                                                        formData.payment_sales.includes(
                                                                            client.id
                                                                        )
                                                                    "
                                                                >
                                                                    <i
                                                                        class="mdi mdi-check text-primary"
                                                                        style="
                                                                            font-size: 20px !important;
                                                                        "
                                                                    ></i>
                                                                </q-item-section>
                                                            </q-item>
                                                        </q-list> </template
                                                    ><template
                                                        v-else-if="
                                                            key === 'LVC'
                                                        "
                                                    >
                                                        <q-list dense>
                                                            <q-item
                                                                v-for="(
                                                                    client,
                                                                    indexClient
                                                                ) in generalBonus
                                                                    .applied_rules[
                                                                    r
                                                                ][key]"
                                                                :key="`key-${key}-${indexClient}`"
                                                            >
                                                                <q-item-section>
                                                                    {{ client }}
                                                                </q-item-section>
                                                            </q-item>
                                                        </q-list> </template
                                                    ><template
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
                                                                        generalBonus
                                                                            .applied_rules[
                                                                            r
                                                                        ][key]
                                                                            .length
                                                                    }}
                                                                </q-item-section>
                                                            </q-item>
                                                            <q-item
                                                                v-for="(
                                                                    ruleApplied,
                                                                    indexRuleApplied
                                                                ) in generalBonus
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
                                                        <q-item-section avatar>
                                                            {{
                                                                getRoundValue(
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
                        </template>
                    </q-list>

                    <q-list
                        dense
                        class="q-mb-lg"
                        v-if="formData.monthly_bonus?.length > 0"
                    >
                        <q-item class="bg-grey-2 text-bold">
                            <q-item-section> Bonos mensuales </q-item-section>
                            <q-item-section avatar>
                                <q-item-label
                                    style="padding-right: 40px !important"
                                >
                                    ${{ totalMonthlyBonus }}
                                </q-item-label>
                            </q-item-section>
                        </q-item>
                        <template
                            v-for="(m, indexMonth) in months"
                            :key="`month-${indexMonth}`"
                        >
                            <q-expansion-item
                                group="monthly-bonus"
                                style="min-height: 28px !important"
                                dense
                                v-if="formData.monthly_bonus.includes(m.name)"
                            >
                                <template #header>
                                    <q-item-section>
                                        {{ m.label }}
                                    </q-item-section>
                                    <q-item-section avatar>
                                        ${{
                                            !isNaN(
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
                                        }}
                                    </q-item-section>
                                </template>
                                <q-card>
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
                        </template>
                    </q-list>
                    <q-list>
                        <q-item class="bg-grey-2 text-bold">
                            <q-item-section> Total a pagar </q-item-section>
                            <q-item-section avatar>
                                <q-item-label
                                    style="padding-right: 40px !important"
                                >
                                    ${{ totalMonthlyBonus + totalGeneralBonus }}
                                </q-item-label>
                            </q-item-section>
                        </q-item>
                    </q-list>
                </div>
            </form>
        </template>
        <template #footer>
            <button class="btn btn-success" @click="showDetails">
                Vista previa
            </button>
            <button class="btn btn-primary" @click="registerPayment">
                Registrar pago
            </button>
        </template>
    </modal>
    <payment-view
        v-model:showModal="showDetailModal"
        :data="detailPayment"
        @save="registerPayment"
    />
</template>

<script setup>
import { ref, watch, computed, onMounted } from "vue";
import Swal from "sweetalert2";
import {
    getMethodsPayments,
    createPayment,
    showPaymentDetails,
    months,
    years,
    currentYearMonthlyBonus,
    errorMessage,
    fieldsRules,
    dateSearchData,
    date,
} from "./helper/helper.js";
import Modal from "../../../../shared/ModalSimple.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import moment from "moment";
import { showLoading, hideLoading } from "../../../../helpers/loading.js";
import PaymentView from "./PaymentView.vue";
import { useDatePicker } from "../../../../composables/useDatePicker.js";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },
    showModal: {
        type: Boolean,
        required: true,
    },
    bonus: {
        type: Object,
        default: null,
    },
});

const emits = defineEmits(["update:showModal", "created"]);

const { customFormat } = useDatePicker();

const methodsPayments = ref([]);
const tab = ref("general-commissions");
const formData = ref({
    seller_id: null,
    payment_method_id: null,
    payment_date: null,
    invoice_number: null,
    monthly_bonus: [],
    general_bonus: [],
    period_date: date,
    monthly_year: currentYearMonthlyBonus,
    comments: null,
    payment_sales: [],
});
const generalBonus = ref(null);
const generalBonusOptions = ref([]);
const generalBonusRule = ref(null);
const monthlyBonus = ref(null);
const monthlyBonusOptions = ref([]);
const monthlyBonusRule = ref(null);
const detailPayment = ref(null);
const showDetailModal = ref(false);
const errors = ref([]);
const currentAdditionalSales = ref([]);
const selectAll = ref(false);

onMounted(() => {
    formData.value.seller_id = props.seller_id;
});

watch(
    () => props.bonus,
    (n) => {
        monthlyBonus.value = n.monthly;
        generalBonus.value = n.general;
    },
    { deep: true }
);

watch(
    monthlyBonus,
    (n) => {
        monthlyBonusOptions.value = [];
        formData.value.monthly_bonus = [];
        monthlyBonusRule.value = n ? n.rule : null;
        if (n) {
            let temp = [];
            months.forEach((m) => {
                if (n[m.name]["Total a pagar"] > 0) {
                    temp.push({
                        label: `${m.label} ($${
                            Math.round(n[m.name]["Total a pagar"] * 100) / 100
                        })`,
                        value: `${m.name}`,
                        data: n[m.name],
                    });
                }
            });
            monthlyBonusOptions.value = temp;
        }
    },
    { deep: true }
);

watch(generalBonus, async (n) => {
    generalBonusOptions.value = [];
    formData.value.general_bonus = [];
    currentAdditionalSales.value = await getCurrentAdditionalSalesKey(n);
    if (n) {
        let temp = [];
        Object.keys(n.applied_rules).forEach((m) => {
            if (n.applied_rules[m] && n.applied_rules[m]["Total a pagar"] > 0) {
                temp.push({
                    label: `${fieldsRules[m].label} ($${
                        Math.round(n.applied_rules[m]["Total a pagar"] * 100) /
                        100
                    })`,
                    value: `${m}`,
                    data: n.applied_rules[m],
                });
            }
        });
        generalBonusOptions.value = temp;
        generalBonusRule.value = Array.isArray(n.rule) ? null : n.rule;
    }
});

watch(
    () => props.showModal,
    () => {
        if (props.showModal) {
            resetData();
            getMethodsOfPayments();
            getDate();
            createReceiptNumber();
        }
    }
);

watch(
    currentAdditionalSales,
    (n) => {
        let selected = n.filter((s) => s.selected);
        if (selected.length > 0) {
            formData.value.payment_sales = selected.map((s) => {
                return { id: s.id, amount: s.amount };
            });
            if (
                !formData.value.general_bonus.includes(
                    "additional_sales_commissions"
                )
            ) {
                formData.value.general_bonus.push(
                    "additional_sales_commissions"
                );
            }
        } else {
            formData.value.payment_sales = [];
            formData.value.general_bonus = formData.value.general_bonus.filter(
                (b) => b !== "additional_sales_commissions"
            );
        }
    },
    { deep: true }
);

const amountFromSelectedSales = computed(() => {
    let amount = 0;
    currentAdditionalSales.value
        .filter((s) => s.selected)
        .forEach((s) => {
            amount += parseFloat(s.amount);
        });
    return amount;
});

const getCurrentAdditionalSalesKey = (rule) => {
    let additional = rule.applied_rules["additional_sales_commissions"];
    let sales = [];
    if (additional && additional["Total a pagar"] > 0) {
        sales = additional["LVA"];
    }
    return sales;
};

const selectAllSales = (select) => {
    currentAdditionalSales.value.forEach((s) => {
        if (s.state === "pendiente") {
            s.selected = select;
        }
    });
    selectAll.value = select;
};

const resetData = () => {
    selectAll.value = false;
    currentAdditionalSales.value.forEach((s) => {
        s.selected = false;
    });
    formData.value = {
        seller_id: props.seller_id,
        payment_method_id: null,
        payment_date: null,
        invoice_number: null,
        monthly_bonus: [],
        general_bonus: [],
        period_date: date,
        monthly_year: currentYearMonthlyBonus,
        comments: null,
        payment_sales: [],
    };
    createReceiptNumber();
};

const totalMonthlyBonus = computed(() => {
    let total = 0;
    formData.value.monthly_bonus?.forEach((b) => {
        total += monthlyBonus.value[b]["Total a pagar"];
    });
    if (total > 0) {
        total = Math.round(total * 100) / 100;
    }
    return total;
});

const totalGeneralBonus = computed(() => {
    let total = 0;
    formData.value.general_bonus?.forEach((b) => {
        if (b !== "additional_sales_commissions") {
            total += generalBonus.value.applied_rules[b]["Total a pagar"];
        } else {
            total += amountFromSelectedSales.value;
        }
    });
    if (total > 0) {
        total = Math.round(total * 100) / 100;
    }
    return total;
});

const isValidForm = () => {
    errors.value = [];
    const fields = ["payment_method_id", "payment_date", "invoice_number"];
    for (let i = 0; i < fields.length; i++) {
        if (formData.value[fields[i]] === null) {
            errors.value.push(fields[i]);
        }
    }
    return errors.value.length === 0;
};

const getReloadedData = () => {
    if (
        formData.value.monthly_bonus.length > 0 &&
        formData.value.general_bonus.length > 0
    )
        return "all";
    if (
        formData.value.monthly_bonus.length > 0 &&
        formData.value.general_bonus.length === 0
    )
        return "monthly";
    if (
        formData.value.monthly_bonus.length === 0 &&
        formData.value.general_bonus.length > 0
    )
        return "commissions";
    return "all";
};

const store = async () => {
    try {
        showLoading("showTextDef");
        const reloaded = getReloadedData();
        const response = await createPayment(formData.value);
        if (response !== null && response.success) {
            Swal.fire({
                title: "¡Creado!",
                text: "Se ha creado el pago correctamente, desea generar su comprobante?",
                icon: "success",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Si",
                cancelButtonText: "No",
            }).then((result) => {
                showDetailModal.value = false;
                emits("created", reloaded);
                if (result.isConfirmed) {
                    window.open(
                        `/vendedores/payments-sellers/payment-receipt/${response.payment.id}`,
                        "_blank"
                    );
                }
            });
            resetData();
        } else {
            Swal.fire("¡Error!", "Hubo un error al agregar el pago", "error");
        }
        hideLoading();
    } catch (error) {
        Swal.fire("¡Error!", "Hubo un error al agregar el pago", "error");
        hideLoading();
    }
};

const registerPayment = async () => {
    if (isValidForm()) {
        if (
            formData.value.monthly_bonus.length === 0 &&
            formData.value.general_bonus.length === 0
        ) {
            setMsgError("Al menos debe especificar una comisión a pagar");
        } else {
            Swal.fire({
                title: "¡Confirmación!",
                text: "Seguro que desea registrar este pago?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Si",
                cancelButtonText: "No",
            }).then((result) => {
                if (result.isConfirmed) {
                    store();
                }
            });
        }
    } else {
        setMsgError("Rectifique los errores");
    }
};

const showDetails = async () => {
    if (isValidForm()) {
        if (
            formData.value.monthly_bonus.length === 0 &&
            formData.value.general_bonus.length === 0
        ) {
            setMsgError("Al menos debe especificar una comisión a pagar");
        } else {
            try {
                showLoading("showTextDef");
                const response = await showPaymentDetails(formData.value);
                detailPayment.value = response;
                showDetailModal.value = true;
                hideLoading();
            } catch (error) {
                Swal.fire(
                    "¡Error!",
                    "Hubo un error al tratar de obtener la vista previa del pago",
                    "error"
                );
                hideLoading();
            }
        }
    } else {
        setMsgError("Rectifique los errores");
    }
};

const getMethodsOfPayments = async () => {
    methodsPayments.value = await getMethodsPayments();
};

const getDate = () => {
    const today = new Date();
    today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    const formattedDate = today.toISOString().split("T")[0];
    payment_date.value = formattedDate;
};

const createReceiptNumber = () => {
    let date = moment.now();
    formData.value.invoice_number = `${moment(date).format(
        "YYYY-MM-DD"
    )}-${date}`;
};

const updateShow = (newValue) => {
    emits("update:showModal", newValue);
};

const getLabel = (prop) => {
    let { label } = fieldsRules[prop];
    let txt = "";
    if (generalBonus.value) {
        let val =
            prop === "additional_sales_commissions" ||
            (!formData.value.general_bonus.includes(
                "additional_sales_commissions"
            ) &&
                formData.value.payment_sales.length > 0)
                ? amountFromSelectedSales.value
                : generalBonus.value.applied_rules[prop]["Total a pagar"];
        if (!isNaN(val)) {
            txt = ` ($${Math.round(val * 100) / 100})`;
        }
    }
    return `${label}${txt}`;
};

const getRoundValue = (name, key) => {
    if (!isNaN(generalBonus.value?.applied_rules[name][key])) {
        return (
            Math.round(generalBonus.value?.applied_rules[name][key] * 100) / 100
        );
    }
    return generalBonus.value?.applied_rules[name][key];
};

const setMsgError = (error) => {
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        },
    });
    Toast.fire({
        icon: "error",
        text: error,
    });
};
</script>
