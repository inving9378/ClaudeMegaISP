<template>
    <div class="row">
        <table-example :zones="dataZones"></table-example>

        <div class="col-md-12 mt-3">
            <div class="card h-100 w-75 mx-auto">
                <div class="card-body">
                    <form @submit.prevent="createPaymentRule">
                        <div class="form-group">
                            <div class="mt-3">
                                <label for="name"> Tipo de vendedor </label>
                                <select
                                    class="form-select"
                                    v-model="type_id"
                                    @change="getSellers"
                                >
                                    <option
                                        v-for="typeS in typeSellers"
                                        :key="typeS.id"
                                        :value="typeS.id"
                                    >
                                        {{ typeS.name }}
                                    </option>
                                </select>
                            </div>

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

                            <div class="mt-3">
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
                                v-show="
                                    selectedFields.includes(3) && type_id != 3
                                "
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
                                v-show="selectedFields.includes(7)"
                            >
                                <label for="name"> Zona </label>

                                <div class="input-group">
                                    <select
                                        class="form-select"
                                        v-model="form.zone"
                                    >
                                        <option
                                            v-for="(
                                                option, index
                                            ) in categoryList"
                                            :key="index"
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
                                            Numero de ventas necesario
                                        </label>

                                        <div class="input-group">
                                            <input
                                                type="number"
                                                min="1"
                                                class="form-control"
                                                placeholder="Numero de clientes necesario para recibir el bono"
                                                v-model="
                                                    form.number_sales_required
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
                                            @click="createBonusCondition"
                                        >
                                            Añadir condiciciones
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
                                    Una parte del bono se otorgara si se cumple
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
                                                placeholder="Numero de personas necesario para recibir el bono"
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
                                class="col mt-3"
                                v-show="selectedFields.includes(11)"
                            >
                                <label for="name"> Costo de instalación </label>

                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="$0.00"
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
                                    Crear regla de comision
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
import { ref, onMounted, watch, nextTick, reactive } from "vue";
import TableExample from "./Table.vue";
import MultipleSelect from "../../../../shared/SelectMultipleOptions.vue";
import {
    getTypeSellers,
    getSellersByType,
    getAllRangesOfSales,
    createVendorRule,
} from "./helper/helper.js";
import Swal from "sweetalert2";

const typeSellers = ref([]);
const sellers = ref([]);
const type_id = ref(null);
const localMemory = ref([]);
const selectedFields = ref([]);
const selectedSellers = ref([]);
const dataZones = ref([]);

const form = reactive({
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

const categoryList = ref([
    { value: "", text: "Selecciona una categoria" },
    { value: "A", text: "Zona A" },
    { value: "B", text: "Zona B" },
    { value: "C", text: "Zona C" },
]);

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

const fields = ref([
    { name: "Nombre de la regla", value: 1 },
    { name: "Sueldo", value: 2 },
    { name: "Comisión por venta", value: 3 },
    { name: "Número de prospectos", value: 4 },
    { name: "Número de ventas", value: 5 },
    { name: "Zona", value: 7 },
    { name: "IVA", value: 8 },
    { name: "Comisión por venta adicional", value: 9 },
    { name: "Bono mensual", value: 10 },
    { name: "Costo de instalacion", value: 11 },
    { name: "Comisión Distribuidiores", value: 12 },
]);

onMounted(async () => {
    getListRanges();
    getTypesAllSellers();
});

const getListRanges = async () => {
    try {
        dataZones.value = await getAllRangesOfSales();
    } catch (error) {
        console.log(error);
    }
};

function createBonusComission() {
    form.bonusCommission.push({
        value: "",
        sales: "",
    });
}

// Corrige la referencia a form en otros métodos relacionados
function createBonusCondition() {
    form.bonusConditions.push({
        value: "",
        sales: "",
    });
}

function deleteBonusCondition(index) {
    form.bonusConditions.splice(index, 1);
}

function deleteBonusComission(index) {
    form.bonusCommission.splice(index, 1);
}

function constructLocalS() {
    //si no existe creamos el local storage con la firma
    if (
        !localStorage.getItem("selectedFields") &&
        typeSellers.value.length > 0
    ) {
        localMemory.value = typeSellers.value.map((seller) => {
            return {
                id: seller.id,
                fields: fields.value.map((field) => field.value),
            };
        });

        localStorage.setItem(
            "selectedFields",
            JSON.stringify(localMemory.value)
        );
    }
}

function updateSelectedOptions(newSelectedFields) {
    // Verifica si ambos valores, 3 y 12, están presentes
    if (newSelectedFields.includes(3) && newSelectedFields.includes(12)) {
        // Pregunta al usuario cuál mantener
        const keepThree = confirm(
            "Ambos valores Comisión Por Venta y Comisión Por Venta Alta (Distribuidores) están seleccionados. ¿Deseas mantener el Comisión Por Venta? (Cancelar mantendrá el Comisión Por Venta Alta (Distribuidores))"
        );

        if (keepThree) {
            // Si el usuario elige mantener el 3, elimina el 12
            newSelectedFields.splice(newSelectedFields.indexOf(12), 1);
        } else {
            // Si el usuario elige no mantener el 3, elimina el 3
            newSelectedFields.splice(newSelectedFields.indexOf(3), 1);
        }
    }
    selectedFields.value = newSelectedFields;

    constructLocalS();

    localMemory.value = localMemory.value.map((seller) => {
        if (seller.id === type_id.value) {
            return {
                id: seller.id,
                fields: newSelectedFields,
            };
        } else {
            return seller;
        }
    });

    localStorage.setItem("selectedFields", JSON.stringify(localMemory.value));
}

const getTypesAllSellers = async () => {
    typeSellers.value = await getTypeSellers();
};

const getSellers = async () => {
    // Obtener los vendedores por tipo
    sellers.value = await getSellersByType(type_id.value);

    // Construir localStorage si es necesario
    constructLocalS();

    // Obtener la memoria local del localStorage
    localMemory.value =
        JSON.parse(localStorage.getItem("selectedFields")) || [];

    // Buscar el vendedor por tipo_id
    const selectedSeller = localMemory.value.find(
        (seller) => seller.id === type_id.value
    );
    if (selectedSeller) {
        selectedFields.value = selectedSeller.fields;
    } else {
        selectedFields.value = []; // Asignar un array vacío o un valor predeterminado
        console.warn(`No se encontró un vendedor con id: ${type_id.value}`);
    }
};

const validateBodyData = (id) => {
    return selectedFields.value.includes(id);
};

const createBody = (id, value, type) => {
    if (type && type === "str") {
        return validateBodyData(id) ? value : "";
    } else {
        return validateBodyData(id) ? parseFloat(value) || 0 : 0;
    }
};

const createPaymentRule = async () => {
    if (form.seller_id.length === 0) {
        Swal.fire(
            "¡Error!",
            "Debes seleccionar al menos un vendedor al que aplicar la regla",
            "warning"
        );
        return;
    }

    const {
        name,
        amount,
        commission_percentage,
        fixed_sales_commission,
        fixed_sales_commission_distribuitors,
        fixed_sales_commission_distribuitors_percent,
        number_of_prospects,
        minimum_sales,
        period,
        zone,
        iva,
        commission_percentage_additional,
        fixed_sales_commission_additional,
        total_bonus,
        total_comission,
        number_sales_required,
        number_sales_bonus_commission_required,
        installation_cost,
        penalty,
        bonusConditions,
        bonusCommission,
        seller_id,
    } = form;

    const conditions = bonusConditions.filter(
        (condition) => condition.value && condition.sales
    );

    const conditions_comission = bonusCommission.filter(
        (condition) => condition.value && condition.sales
    );

    let body = {
        name: createBody(1, name, "str"),
        amount: createBody(2, amount),
        commission_percentage: createBody(3, commission_percentage),
        fixed_sales_commission: createBody(3, fixed_sales_commission),
        number_of_prospects: createBody(4, number_of_prospects),
        minimum_sales: createBody(5, minimum_sales),
        period: form.period,
        zone: createBody(7, zone, "str"),
        iva: createBody(8, iva),
        commission_percentage_additional: createBody(
            9,
            commission_percentage_additional
        ),
        fixed_sales_commission_additional: createBody(
            9,
            fixed_sales_commission_additional
        ),
        total_bonus: createBody(10, total_bonus),
        total_comission: createBody(12, total_comission),
        number_sales_required: createBody(10, number_sales_required),
        number_sales_bonus_commission_required: createBody(
            12,
            number_sales_bonus_commission_required
        ),
        installation_cost: createBody(11, installation_cost),
        penalty: createBody(12, penalty),
        conditions: conditions,
        conditions_comission: conditions_comission,
        sellers: seller_id,
        fixed_sales_commission_distribuitors: createBody(
            12,
            fixed_sales_commission_distribuitors
        ),
        fixed_sales_commission_distribuitors_percent: createBody(
            12,
            fixed_sales_commission_distribuitors_percent
        ),
    };

    try {
        const res = await createVendorRule(body);

        if (res.status === 200) {
            Swal.fire("Regla creada", res.message, "success").then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "/configuracion/reglas-comisiones";
                }
            });

            form = resetForm();
            selectedSellers.value = [];
        } else {
            Swal.fire(
                "¡Error!",
                "Sucedió un error al actualizar las reglas de los vendedores",
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

function resetForm() {
    return {
        name: "",
        amount: "",
        commission_percentage: null,
        fixed_sales_commission: "",
        number_of_prospects: "",
        minimum_sales: "",
        period: "",
        zone: "",
        iva: "16",
        commission_percentage_additional: "",
        fixed_sales_commission_additional: "",
        fixed_sales_commission_distribuitors,
        fixed_sales_commission_distribuitors_percent,
        total_bonus: "",
        total_comission: "",
        number_sales_required: "",
        number_sales_bonus_commission_required: "",
        installation_cost: "",
        penalty: "",
        bonusConditions: [],
        bonusCommission: [],
        seller_id: [],
    };
}

const goBack = () => {
    window.history.back();
};

watch(type_id, async () => {
    if (type_id.value === 3) {
        // Eliminar el campo si la condición se cumple
        fields.value = fields.value.filter((field) => field.value !== 3);
        period.value = period.value.filter(
            (p) => p.value !== "Diario" && p.value !== "Semanal"
        );
    } else {
        // Verificar si el campo no está y agregarlo en la tercera posición en fields
        const commissionFieldIndex = fields.value.findIndex(
            (field) => field.value === 3
        );
        if (commissionFieldIndex === -1) {
            fields.value.splice(2, 0, { name: "Comisión por venta", value: 3 });
        }
        // Verificar y agregar "Diario" y "Semanal" en las posiciones específicas en period
        const diarioIndex = period.value.findIndex((p) => p.value === "Diario");
        const semanalIndex = period.value.findIndex(
            (p) => p.value === "Semanal"
        );

        if (diarioIndex === -1) {
            period.value.splice(1, 0, { text: "Diario", value: "Diario" }); // Agrega "Diario" en la primera posición
        }

        if (semanalIndex === -1) {
            period.value.splice(2, 0, { text: "Semanal", value: "Semanal" }); // Agrega "Semanal" en la segunda posición
        }
    }

    form.period = "";
    await nextTick();
});
</script>
