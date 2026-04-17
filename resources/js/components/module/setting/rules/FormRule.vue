<template class="q-mx-lg">
    <table-example :zones="dataZones"></table-example>
    <q-form ref="formRef" greedy class="w-75 mx-auto">
        <q-card bordered class="q-pa-md">
            <q-card-section class="q-gutter-md">
                <div class="object-field">
                    <label for="object_name">Nombre de la regla</label>
                    <q-input
                        v-model="formData.name"
                        dense
                        outlined
                        placeholder="Nombre"
                        for="object_name"
                        clearable
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>

                <div class="object-field">
                    <label for="object_type_of_seller">Tipo de vendedor</label>
                    <q-select
                        ref="sellerRef"
                        v-model="formData.type_of_seller"
                        :options="sellersOptions"
                        emit-value
                        map-options
                        for="object_type_of_seller"
                        clearable
                        dense
                        options-dense
                        outlined
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                        @update:model-value="getSellers"
                    >
                    </q-select>
                </div>

                <div class="object-field">
                    <label for="object_period">Período</label>
                    <q-select
                        v-model="formData.period"
                        :options="period"
                        emit-value
                        map-options
                        for="object_period"
                        clearable
                        dense
                        options-dense
                        outlined
                        placeholder="Seleccione el periodo"
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                    >
                    </q-select>
                </div>

                <div class="object-field">
                    <label for="object_fields">Campos disponibles</label>
                    <q-select
                        v-model="formData.selected_fields"
                        dense
                        options-dense
                        outlined
                        :options="fields"
                        multiple
                        use-chips
                        emit-value
                        map-options
                        clearable
                        for="object_fields"
                        hint="Requerido"
                        :rules="[(val) => val.length > 0 || 'Requerido']"
                        @update:model-value="onChangeFields"
                        @clear="formData.selected_fields = []"
                    >
                        <template v-slot:option="scope">
                            <q-item v-bind="scope.itemProps">
                                <q-item dense>
                                    <q-item-section
                                        >{{ scope.opt.label }}
                                    </q-item-section>
                                    <q-item-section avatar
                                        ><q-checkbox
                                            v-model="formData.selected_fields"
                                            :val="scope.opt.value"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-item>
                        </template>
                    </q-select>
                </div>
                <div
                    class="object-field"
                    v-if="formData.selected_fields.includes('fixed_salary')"
                >
                    <div class="row">
                        <div class="col">
                            <label for="object_fixed_salary">Sueldo</label>
                            <q-input
                                v-model.number="formData.fixed_salary"
                                dense
                                options-dense
                                outlined
                                placeholder="0.00"
                                for="object_fixed_salary"
                                clearable
                                class="full-width"
                                type="number"
                                hint="Requerido"
                                prefix="$"
                                :rules="[
                                    (val) => !!val || 'Requerido',
                                    (val) =>
                                        val >= 0 ||
                                        'Introduzca un valor mayor o igual a cero',
                                ]"
                            />
                        </div>
                        <div
                            class="col self-center q-ml-md"
                            style="padding-top: 15px"
                        >
                            <input
                                type="checkbox"
                                v-model="formData.is_fixed_salary"
                                id="fixed-salary-check"
                                style="
                                    width: 20px;
                                    height: 20px;
                                    margin-right: 10px;
                                "
                            />
                            <label
                                for="fixed-salary-check"
                                style="position: absolute"
                                >Este sueldo va a ser fijo</label
                            >
                        </div>
                    </div>
                </div>

                <div
                    class="object-field"
                    v-if="
                        formData.selected_fields.includes('number_of_prospects')
                    "
                >
                    <label for="object_number_of_prospects"
                        >Número de prospectos</label
                    >
                    <q-input
                        v-model="formData.number_of_prospects"
                        dense
                        outlined
                        placeholder="Número de prospectos con el que debe cumplir"
                        for="object_number_of_prospects"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                    </q-input>
                </div>

                <div
                    class="object-field"
                    v-if="formData.selected_fields.includes('minimum_sales')"
                >
                    <label for="object_minimum_sales"
                        >Número de ventas solicitadas</label
                    >
                    <q-input
                        v-model="formData.minimum_sales"
                        dense
                        outlined
                        placeholder="Mínimo de ventas esperado"
                        for="object_minimum_sales"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                    </q-input>
                </div>

                <div
                    class="object-field"
                    v-if="formData.selected_fields.includes('sales_commission')"
                >
                    <label for="object_sales_commission"
                        >Comisión por venta</label
                    >
                    <q-input
                        v-model="formData.sales_commission"
                        dense
                        outlined
                        placeholder="0.00"
                        for="object_sales_commission"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                        <template v-slot:append>
                            <span>{{ formData.sales_commission_type }}</span>
                        </template>
                        <template #after>
                            <q-select
                                v-model="formData.sales_commission_type"
                                emit-value
                                map-options
                                :options="commission_type"
                                dense
                                options-dense
                                outlined
                                style="width: 100px"
                            />
                        </template>
                    </q-input>
                </div>

                <additional-sales-commissions
                    name="additional_sales_commissions"
                    :bonus="object?.additional_sales_commissions"
                    :commission-type="commission_type"
                    @update="(name, val) => (formData[name] = val)"
                    v-if="formData.selected_fields.includes('minimum_sales')"
                />

                <div
                    class="object-field"
                    v-if="formData.selected_fields.includes('zone')"
                >
                    <label for="object_zone">Zona</label>
                    <q-select
                        v-model="formData.zone"
                        emit-value
                        map-options
                        :options="zones"
                        for="object_zone"
                        clearable
                        dense
                        options-dense
                        outlined
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                    >
                    </q-select>
                </div>

                <div
                    class="object-field"
                    v-if="formData.selected_fields.includes('iva')"
                >
                    <label for="object_iva">IVA</label>
                    <q-input
                        v-model="formData.iva"
                        dense
                        outlined
                        placeholder="0%"
                        for="object_iva"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                        <template v-slot:append>
                            <span>%</span>
                        </template>
                    </q-input>
                </div>

                <div
                    class="object-field"
                    v-if="
                        formData.selected_fields.includes('installation_cost')
                    "
                >
                    <label for="object_installation_cost"
                        >Costo de instalación</label
                    >
                    <q-input
                        v-model.number="formData.installation_cost"
                        dense
                        outlined
                        placeholder="0.00"
                        for="object_installation_cost"
                        clearable
                        class="full-width"
                        type="number"
                        hint="Requerido"
                        prefix="$"
                        :rules="[
                            (val) => !!val || 'Requerido',
                            (val) =>
                                val >= 0 ||
                                'Introduzca un valor mayor o igual a cero',
                        ]"
                    >
                    </q-input>
                </div>

                <div class="object-field">
                    <label for="object_sellers">Vendedores</label>
                    <q-select
                        v-model="formData.sellers"
                        dense
                        clearable
                        options-dense
                        outlined
                        :options="sellers"
                        option-label="full_name"
                        option-value="id"
                        multiple
                        use-chips
                        emit-value
                        map-options
                        for="object_sellers"
                        hint="Requerido"
                        :rules="[validateSelectionSeller]"
                        style="width: 100%"
                        @clear="formData.sellers = []"
                    >
                        <template v-slot:option="scope">
                            <q-item v-bind="scope.itemProps">
                                <q-item dense>
                                    <q-item-section
                                        >{{ scope.opt.full_name }}
                                    </q-item-section>
                                    <q-item-section avatar
                                        ><q-checkbox
                                            v-model="formData.sellers"
                                            :val="scope.opt.id"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-item>
                        </template>
                    </q-select>
                </div>
                <monthly-bonus
                    name="monthly_bonus"
                    :bonus="object?.monthly_bonus"
                    @update="(name, val) => (formData[name] = val)"
                    v-if="formData.selected_fields.includes('monthly_bonus')"
                />
                <distributors-commission
                    name="distributors_commission"
                    :commission="object?.distributors_commission"
                    :contracts-durations="contractsDurations"
                    @update="(name, val) => (formData[name] = val)"
                    v-if="
                        formData.selected_fields.includes(
                            'distributors_commission'
                        )
                    "
                />
            </q-card-section>
            <q-card-actions align="center" class="q-gutter-md">
                <q-btn
                    label="Atrás"
                    no-caps
                    color="grey-8"
                    href="/configuracion/rules/"
                />
                <q-btn
                    :label="object ? 'Guardar' : 'Crear regla de comisión'"
                    no-caps
                    color="indigo-7"
                    @click="save"
                />
            </q-card-actions>
        </q-card>
    </q-form>
</template>

<script setup>
import { onMounted, ref } from "vue";
import TableExample from "../../vendors/commissions/Table.vue";
import { getAllRangesOfSales } from "../../vendors/commissions/helper/helper";
import MonthlyBonus from "./components/MonthlyBonus.vue";
import DistributorsCommission from "./components/DistributorsCommission.vue";
import AdditionalSalesCommissions from "./components/AdditionalSalesCommissions";
import Swal from "sweetalert2";
import axios from "axios";

defineOptions({
    name: "FormRule",
});

const props = defineProps({
    object: Object,
    sellersOptions: {
        type: Array,
        default: [],
    },
    sellersList: {
        type: Array,
        default: [],
    },
    sellersList: {
        type: Array,
        default: [],
    },
    contractsDurations: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits([]);

const dataZones = ref([]);
const formRef = ref(null);
const formData = ref({
    name: null,
    type_of_seller: null,
    fixed_salary: 0,
    is_fixed_salary: false,
    sales_commission: 0,
    sales_commission_type: "$",
    additional_sales_commissions: 0,
    number_of_prospects: 0,
    minimum_sales: 0,
    zone: null,
    iva: 0,
    installation_cost: 0,
    period: null,
    sellers: null,
    selected_fields: [],
    monthly_bonus: null,
    distributors_commission: null,
});
const sellerRef = ref(null);
const sellers = ref([]);

const defaultValues = {
    name: null,
    type_of_seller: null,
    fixed_salary: 0,
    is_fixed_salary: false,
    sales_commission: 0,
    sales_commission_type: "$",
    additional_sales_commissions: null,
    number_of_prospects: 0,
    minimum_sales: 0,
    zone: null,
    iva: 0,
    installation_cost: 0,
    period: null,
    sellers: null,
    monthly_bonus: null,
    distributors_commission: null,
};

const fields = [
    { label: "Sueldo", value: "fixed_salary" },
    { label: "Comisión por venta", value: "sales_commission" },
    { label: "Número de prospectos", value: "number_of_prospects" },
    { label: "Número de ventas", value: "minimum_sales" },
    { label: "Zona", value: "zone" },
    { label: "IVA", value: "iva" },
    { label: "Bono mensual", value: "monthly_bonus" },
    { label: "Costo de instalacion", value: "installation_cost" },
    { label: "Comisión Distribuidores", value: "distributors_commission" },
];

const commission_type = [
    {
        label: "Fija",
        value: "$",
    },
    {
        label: "Porciento",
        value: "%",
    },
];

const period = [
    { value: "Diario", label: "Diario" },
    { value: "Semanal", label: "Semanal" },
    { value: "Mensual", label: "Mensual" },
    { value: "6 meses", label: "6 Meses" },
    { value: "12 meses", label: "12 Meses" },
    { value: "18 meses", label: "18 Meses" },
    { value: "24 meses", label: "24 Meses" },
    { value: "36 meses", label: "36 Meses" },
];

const zones = [
    { value: "A", label: "Zona A" },
    { value: "B", label: "Zona B" },
    { value: "C", label: "Zona C" },
];

onMounted(() => {
    getListRanges();
    sellers.value = props.sellersList;
    const object = props.object;
    if (object) {
        formData.value = {
            name: object.name,
            type_of_seller: parseInt(object.type_of_seller),
            fixed_salary: object.fixed_salary,
            is_fixed_salary: object.is_fixed_salary,
            sales_commission: object.sales_commission,
            sales_commission_type: object.sales_commission_type
                ? object.sales_commission_type
                : "$",
            additional_sales_commissions: object.additional_sales_commissions,
            number_of_prospects: object.number_of_prospects,
            minimum_sales: object.minimum_sales,
            zone: object.zone,
            iva: object.iva,
            installation_cost: object.installation_cost,
            period: object.period,
            sellers: object.sellers_id,
            selected_fields:
                object.selected_fields && Array.isArray(object.selected_fields)
                    ? object.selected_fields
                    : [],
            monthly_bonus: null,
            distributors_commission: object.distributors_commission,
        };
    }
});

const getListRanges = async () => {
    try {
        dataZones.value = await getAllRangesOfSales();
    } catch (error) {
        console.log(error);
    }
};

const getSellers = async (val) => {
    await axios
        .get(`/configuracion/rules/get-sellers-by-type/${val}`)
        .then((response) => {
            sellers.value = response.data;
            formData.value.sellers = [];
        });
};

const onChangeFields = (val) => {
    fields.forEach((f) => {
        if (!val.includes(f.value)) {
            formData.value[f.value] = defaultValues[f.value];
        }
    });
    if (!val.includes("fixed_salary")) {
        formData.value["is_fixed_salary"] = defaultValues["is_fixed_salary"];
    }
    if (!val.includes("minimum_sales")) {
        formData.value["additional_sales_commissions"] =
            defaultValues["additional_sales_commissions"];
    }
};

const validateSelectionSeller = () => {
    return formData.value.sellers.length === 0 ? "Requerido" : true;
};

const save = () => {
    formRef.value.validate().then((success) => {
        if (success) {
            let url = props.object ? `update/${props.object.id}` : "store";
            axios
                .post(`/configuracion/rules/${url}`, formData.value)
                .then((res) => {
                    if (res.status === 200) {
                        toastr.success(
                            `Regla ${
                                props.object ? "modificada" : "creada"
                            } correctamente`,
                            "Info!!!"
                        );
                        window.location.href = "/configuracion/rules";
                    }
                })
                .catch((e) => {
                    console.log(e);
                })
                .finally((f) => {
                    console.log(f);
                });
        } else {
            const Toast = Swal.mixin({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                },
            });
            Toast.fire({
                icon: "error",
                title: "Rectifique los errores",
            });
        }
    });
};
</script>
<style>
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
.row.auto-width * {
    width: auto !important;
}

.row.auto-width .q-checkbox__inner {
    min-width: 1em !important;
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
</style>
