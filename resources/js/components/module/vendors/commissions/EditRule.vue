<template>
    <div class="row">
        <table-example :zones="dataZones"></table-example>

        <div class="col-md-12 mt-3">
            <div class="card h-100 w-75 mx-auto">
                <div class="card-body">
                    <form @submit.prevent="updatePaymentRule" novalidate>
                        <div class="form-group">
                            <MultipleSelect
                                :options="fields"
                                :selectedOptions="selectedFields"
                                :label="'Campos disponibles'"
                                :text_value="'name'"
                                :select_value="'value'"
                                @update:selectedOptions="
                                    (newSelectedFields) =>
                                        updateSelectedOptions(newSelectedFields)
                                "
                            />

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(1)"
                            >
                                <label for="name">Nombre de la regla</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Nombre"
                                        v-model="form.name"
                                    />
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(2)"
                            >
                                <label for="name">Sueldo fijo</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="$0.00"
                                        v-model="form.amount"
                                    />
                                    <span class="input-group-text">$</span>
                                </div>
                            </div>

                            <div
                                class="mt-3 row"
                                v-show="selectedFields.includes(3)"
                            >
                                <div class="col">
                                    <label for="name">Comisión por venta</label>
                                    <div class="input-group">
                                        <input
                                            maxlength="2"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="form-control"
                                            placeholder="Porcentaje por venta %"
                                            v-model="form.commission_percentage"
                                            :disabled="
                                                form.fixed_sales_commission > 0
                                            "
                                        />
                                        <span class="input-group-text">
                                            %
                                        </span>
                                    </div>
                                </div>

                                <div class="col">
                                    <label for="name">
                                        Comisión fija por venta
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="$0.00"
                                            v-model="
                                                form.fixed_sales_commission
                                            "
                                            :disabled="
                                                form.commission_percentage > 0
                                            "
                                        />
                                        <span class="input-group-text">
                                            $
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(4)"
                            >
                                <label for="name">Número de prospectos</label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        min="1"
                                        class="form-control"
                                        placeholder="Número de prospectos con el que debe cumplir"
                                        v-model="form.number_of_prospects"
                                    />
                                    <span class="input-group-text">Total</span>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(5)"
                            >
                                <label for="name">Número de ventas</label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        min="1"
                                        class="form-control"
                                        placeholder="Minimo de ventas esperado"
                                        v-model="form.minimum_sales"
                                    />
                                    <span class="input-group-text">Total</span>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(6)"
                            >
                                <label for="name">Periodo</label>
                                <select
                                    id=""
                                    class="form-select"
                                    v-model="form.period"
                                    required
                                >
                                    <option
                                        :value="item.value"
                                        :key="item.value"
                                        v-for="item in period"
                                    >
                                        {{ item.text }}
                                    </option>
                                </select>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(7)"
                            >
                                <label for="name"> Zona </label>
                                <div class="input-group">
                                    <select
                                        class="form-select"
                                        v-model="form.zone"
                                    >
                                        <option
                                            v-for="option in categoryList"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.text }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(8)"
                            >
                                <label for="name">IVA</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Valor del IVA"
                                        v-model="form.iva"
                                    />
                                    <span class="input-group-text">
                                        % de IVA
                                    </span>
                                </div>
                            </div>

                            <div
                                class="mt-3 row"
                                v-show="selectedFields.includes(9)"
                            >
                                <div class="col">
                                    <label for="name"
                                        >Comisión por venta adicional</label
                                    >

                                    <div class="input-group">
                                        <input
                                            maxlength="2"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="form-control"
                                            placeholder="Porcentaje por venta %"
                                            v-model="
                                                form.commission_percentage_additional
                                            "
                                            :disabled="
                                                form.fixed_sales_commission_additional >
                                                0
                                            "
                                        />
                                        <span class="input-group-text">
                                            %
                                        </span>
                                    </div>
                                </div>

                                <div class="col">
                                    <label for="name">
                                        Comisión fija por venta adicional
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="$0.00"
                                            v-model="
                                                form.fixed_sales_commission_additional
                                            "
                                            :disabled="
                                                form.commission_percentage_additional >
                                                0
                                            "
                                        />
                                        <span class="input-group-text">
                                            $
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(10)"
                            >
                                <div class="row items-center">
                                    <div class="col">
                                        <label for="name">
                                            Bono que puede recibir mensualmente
                                        </label>
                                        <div class="input-group">
                                            <input
                                                type="text"
                                                class="form-control"
                                                placeholder="$0.00"
                                                v-model="form.total_bonus"
                                            />
                                            <span class="input-group-text">
                                                $
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col">
                                        <label for="name">
                                            Número de ventas necesario
                                        </label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="0"
                                                class="form-control"
                                                placeholder="Numero de personas necesario para recibir el bono"
                                                v-model="
                                                    form.number_sales_required
                                                "
                                            />
                                            <span class="input-group-text">
                                                Número
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col mt-auto col-3">
                                        <span
                                            type="button"
                                            class="btn btn-primary"
                                            @click="createBonusCondition"
                                        >
                                            Añadir condiciones
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="
                                    selectedFields.includes(10) &&
                                    form.bonusConditions.length > 0
                                "
                            >
                                <label for="new-bonuses">
                                    Una parte del bono se otorgará si se cumple
                                    con las siguientes condiciones
                                </label>
                                <div
                                    class="row items-center mt-3"
                                    v-for="(
                                        condition, index
                                    ) in form.bonusConditions"
                                    :key="index"
                                >
                                    <div class="col-1">
                                        <label for="name"> No. </label>
                                        <span class="input-group-text">
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <div class="col">
                                        <label for="name"> Monto </label>
                                        <div class="input-group">
                                            <input
                                                type="text"
                                                class="form-control"
                                                placeholder="Monto"
                                                v-model="condition.value"
                                            />
                                            <span class="input-group-text">
                                                $
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col">
                                        <label for="name">
                                            No. De ventas para recibir el bono
                                        </label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="1"
                                                class="form-control"
                                                placeholder="Número de ventas necesario para recibir el bono"
                                                v-model="condition.sales"
                                            />
                                            <span class="input-group-text">
                                                Número
                                            </span>
                                        </div>
                                    </div>

                                    <span
                                        type="button"
                                        class="btn btn-danger col-2 mt-auto"
                                        @click="deleteBonusCondition(index)"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </span>
                                </div>
                            </div>

                            <div
                                class="col"
                                v-show="selectedFields.includes(11)"
                            >
                                <label for="name"> Costo de instalación </label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Monto"
                                        v-model="form.installation_cost"
                                    />
                                    <span class="input-group-text"> $ </span>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="selectedFields.includes(12)"
                            >
                                <div class="row items-center">
                                    <div class="col mt-auto">
                                        <label for="name">
                                            Comisión inicial por venta
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                class="form-control"
                                                min="0"
                                                placeholder="$0.00"
                                                v-model="
                                                    form.fixed_sales_commission_distribuitors
                                                "
                                                :disabled="
                                                    form.fixed_sales_commission_distribuitors_percent >
                                                    0
                                                "
                                            />
                                            <span class="input-group-text">
                                                $
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col mt-auto">
                                        <label for="name">
                                            Comisión inicial por venta en %
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                class="form-control"
                                                min="0"
                                                placeholder="0%"
                                                v-model="
                                                    form.fixed_sales_commission_distribuitors_percent
                                                "
                                                :disabled="
                                                    form.fixed_sales_commission_distribuitors >
                                                    0
                                                "
                                            />
                                            <span class="input-group-text">
                                                %
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col mt-auto">
                                        <label for="name">
                                            Comisión por venta Total
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="0"
                                                class="form-control"
                                                placeholder="0%"
                                                v-model="form.total_comission"
                                            />
                                            <span class="input-group-text">
                                                %
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col mt-auto">
                                        <label for="name">
                                            Numero de ventas necesario
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="1"
                                                class="form-control"
                                                placeholder="Numero de clientes necesario para recibir el bono"
                                                v-model="
                                                    form.number_sales_bonus_commission_required
                                                "
                                            />
                                            <span class="input-group-text">
                                                Numero
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col mt-auto col-3">
                                        <span
                                            type="button"
                                            class="btn btn-primary"
                                            @click="createBonusComission"
                                        >
                                            Añadir condiciciones
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3"
                                v-show="
                                    selectedFields.includes(12) &&
                                    form.bonusCommission.length > 0
                                "
                            >
                                <label for="new-bonuses">
                                    Una parte del bono se otorgara si se cumple
                                    con las siguientes condiciones
                                </label>

                                <div
                                    class="row items-center mt-3"
                                    v-for="(
                                        condition, index
                                    ) in form.bonusCommission"
                                    :key="index"
                                >
                                    <div class="col-1">
                                        <label for="name"> No. </label>
                                        <span class="input-group-text">
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <div class="col">
                                        <label for="name">
                                            Comisión por venta Total Condición
                                            {{ index + 1 }}
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="text"
                                                class="form-control"
                                                placeholder="0%"
                                                v-model="condition.value"
                                            />

                                            <span class="input-group-text">
                                                %
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col">
                                        <label for="name">
                                            No. De ventas para necesarias
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="1"
                                                class="form-control"
                                                placeholder="Numero de personas necesario "
                                                v-model="condition.sales"
                                            />
                                            <span class="input-group-text">
                                                Número
                                            </span>
                                        </div>
                                    </div>

                                    <span
                                        type="button"
                                        class="btn btn-danger col-2 mt-auto"
                                        @click="deleteBonusComission(index)"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </span>
                                </div>
                            </div>

                            <div
                                class="col mt-3"
                                v-show="selectedFields.includes(12)"
                            >
                                <label for="name"> Penalización </label>

                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="$0.00"
                                        v-model="form.penalty"
                                    />

                                    <span class="input-group-text"> $ </span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <MultipleSelect
                                    :options="sellers"
                                    :selectedOptions="form.seller_id"
                                    :label="'Grupo de vendedores'"
                                    :text_value="'full_name'"
                                    :select_value="'id'"
                                    @update:selectedOptions="
                                        (newSelectedOptions) => {
                                            form.seller_id = newSelectedOptions;
                                        }
                                    "
                                />
                            </div>

                            <div
                                class="modal-footer justify-content-center gap-3"
                            >
                                <a
                                    type="button"
                                    class="btn btn-secondary"
                                    @click="goBack"
                                >
                                    Atrás
                                </a>

                                <button type="submit" class="btn btn-primary">
                                    Actualizar regla de comisión
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import {
    getAllRangesOfSales,
    getSellers,
    getRuleById,
    updateVendorRule,
} from "./helper/helper";
import TableExample from "./Table.vue";
import MultipleSelect from "../../../../shared/SelectMultipleOptions.vue";
import Swal from "sweetalert2";

const props = defineProps({
    id_rule: {
        type: Number,
        required: true,
    },
});

const sellers = ref([]);
const selectedFields = ref([]);
const dataZones = ref([]);

const period = ref([
    { value: "", text: "Selecciona período" },
    { value: "Diario", text: "Diario" },
    { value: "Semanal", text: "Semanal" },
    { value: "Mensual", text: "Mensual" },
    { value: "6", text: "6 Meses" },
    { value: "12", text: "12 Meses" },
    { value: "18", text: "18 Meses" },
    { value: "24", text: "24 Meses" },
    { value: "36", text: "36 Meses" },
]);

const categoryList = ref([
    { value: "", text: "Selecciona una categoría" },
    { value: "A", text: "Zona A" },
    { value: "B", text: "Zona B" },
    { value: "C", text: "Zona C" },
]);

const fields = ref([
    { name: "Nombre de la regla", value: 1 },
    { name: "Sueldo", value: 2 },
    { name: "Comisión por venta", value: 3 },
    { name: "Número de prospectos", value: 4 },
    { name: "Número de ventas", value: 5 },
    { name: "Periodo", value: 6 },
    { name: "Zona", value: 7 },
    { name: "IVA", value: 8 },
    { name: "Comisión por venta adicional", value: 9 },
    { name: "Bono mensual", value: 10 },
    { name: "Costo de instalacion", value: 11 },
]);

const form = ref({
    name: "",
    amount: "",
    commission_percentage: "",
    fixed_sales_commission: "",
    fixed_sales_commission_distribuitors: "",
    fixed_sales_commission_distribuitors_percent: "",
    number_of_prospects: "",
    minimum_sales: "",
    period: "",
    zone: "",
    iva: "16",
    commission_percentage_additional: "",
    fixed_sales_commission_additional: "",
    total_bonus: "",
    total_comission: "",
    number_sales_required: "",
    installation_cost: "",
    penalty: "",
    bonusConditions: [],
    bonusCommission: [],
    seller_id: [],
    number_sales_bonus_commission_required: "",
});

onMounted(() => {
    getRule();
    getDataSellers();
    getListRanges();
});

const getRule = async () => {
    try {
        const response = await getRuleById(props.id_rule);
        const ruleData = response.rule;
        form.value = {
            ...form.value,
            ...ruleData,
            seller_id: response.seller_ids,
            bonusConditions: JSON.parse(ruleData.conditions || "[]"),
            bonusCommission: JSON.parse(ruleData.conditions_comission || "[]"),
        };
        selectedFields.value = getSelectedFields(ruleData);
    } catch (error) {
        console.log(error);
    }
};

const getListRanges = async () => {
    try {
        dataZones.value = await getAllRangesOfSales();
    } catch (error) {
        console.log(error);
    }
};

const getDataSellers = async () => {
    try {
        const response = await getSellers();
        sellers.value = response.map((seller) => ({
            id: seller.seller_id,
            full_name: `${seller.name} ${seller.father_last_name} ${seller.mother_last_name}`,
        }));
    } catch (error) {
        console.log(error);
    }
};

const getSelectedFields = (ruleData) => {
    const fieldsSelected = [];
    if (ruleData.name) fieldsSelected.push(1);

    if (ruleData.amount !== null && parseFloat(ruleData.amount) > 0) {
        fieldsSelected.push(2);
    }

    if (
        (ruleData.commission_percentage !== null &&
            parseFloat(ruleData.commission_percentage) > 0) ||
        (ruleData.fixed_sales_commission !== null &&
            parseFloat(ruleData.fixed_sales_commission) > 0)
    ) {
        fieldsSelected.push(3);
    }

    if (
        ruleData.number_of_prospects !== null &&
        parseFloat(ruleData.number_of_prospects) > 0
    ) {
        fieldsSelected.push(4);
    }

    if (
        ruleData.minimum_sales !== null &&
        parseFloat(ruleData.minimum_sales) > 0
    ) {
        fieldsSelected.push(5);
    }

    if (ruleData.period !== null && ruleData.period !== "") {
        fieldsSelected.push(6);
    }

    if (ruleData.zone !== null && ruleData.zone !== "") {
        fieldsSelected.push(7);
    }

    if (ruleData.iva !== null && parseFloat(ruleData.iva) > 0) {
        fieldsSelected.push(8);
    }

    if (
        (ruleData.commission_percentage_additional !== null &&
            parseFloat(ruleData.commission_percentage_additional) > 0) ||
        (ruleData.fixed_sales_commission_additional !== null &&
            parseFloat(ruleData.fixed_sales_commission_additional) > 0)
    ) {
        fieldsSelected.push(9);
    }

    if (
        (ruleData.total_bonus !== null &&
            parseFloat(ruleData.total_bonus) > 0) ||
        (ruleData.number_sales_required !== null &&
            parseFloat(ruleData.number_sales_required) > 0)
    ) {
        fieldsSelected.push(10);
    }

    if (
        ruleData.installation_cost !== null &&
        parseFloat(ruleData.installation_cost) > 0
    ) {
        fieldsSelected.push(11);
    }

    if (
        ruleData.fixed_sales_commission_distribuitors !== null &&
        parseFloat(ruleData.fixed_sales_commission_distribuitors) > 0
    ) {
        fieldsSelected.push(12);
    }
    return fieldsSelected;
};

const updateSelectedOptions = (newSelectedFields) => {
    selectedFields.value = newSelectedFields;
};

const createBonusCondition = () => {
    form.value.bonusConditions.push({
        value: "",
        sales: "",
    });
};

const deleteBonusCondition = (index) => {
    form.value.bonusConditions.splice(index, 1);
};

function createBonusComission() {
    form.value.bonusCommission.push({
        value: "",
        sales: "",
    });
}

function deleteBonusComission(index) {
    form.value.bonusCommission.splice(index, 1);
}

const updatePaymentRule = async () => {
    if (form.value.seller_id.length === 0) {
        Swal.fire(
            "¡Error!",
            "Debes seleccionar al menos un vendedor al que aplicar la regla",
            "warning"
        );
        return;
    }

    const conditions = form.value.bonusConditions.filter(
        (condition) => condition.value && condition.sales
    );

    const conditions_comission = form.value.bonusCommission.filter(
        (condition) => condition.value && condition.sales
    );

    const body = {
        ...form.value,
        conditions: conditions,
        conditions_comission: conditions_comission,
    };

    try {
        const res = await updateVendorRule(props.id_rule, body);

        if (res.status === 200) {
            Swal.fire("Regla actualizada", res.message, "success").then(
                (result) => {
                    if (result.isConfirmed) {
                        window.location.href =
                            "/configuracion/reglas-comisiones";
                    }
                }
            );
        } else {
            Swal.fire(
                "¡Error!",
                "Sucedió un error al actualizar la regla de comisión",
                "error"
            );
        }
    } catch (error) {
        Swal.fire(
            "¡Error!",
            "Sucedió un error al procesar la solicitud",
            "error"
        );
    }
};

const goBack = () => {
    window.history.back();
};
</script>

<style lang="scss" scoped></style>
