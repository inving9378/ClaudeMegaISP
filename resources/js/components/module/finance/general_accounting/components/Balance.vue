<template>
    <div class="container-fluid my-3">
        <div class="row align-items-center">
            <!-- Intervalo de fechas -->
            <div class="col-auto">
                <label class="form-label mb-0 me-2">Intervalo</label>
            </div>

            <!-- Botones de acción -->
            <div class="col-auto ms-auto">
                <button
                    class="btn btn-outline-info me-2"
                    id="button_add_operation"
                >
                    + Operación
                </button>
                <button class="btn btn-outline-info" id="button_add_category">
                    + Categoría
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid my-4">
        <!-- Balance general arriba -->
        <div class="row mb-3">
            <div class="col text-end">
                <h4>
                    Balance general:
                    <span class="text-danger fw-bold">
                        {{ formatCurrency(generalData.balance) }}
                    </span>
                </h4>
            </div>
        </div>

        <div class="row">
            <!-- Debe -->
            <div class="col-md-6">
                <h5 class="mb-3">Debe</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr
                            v-for="(valor, key) in generalData.egresos"
                            :key="`egreso-${key}`"
                        >
                            <!-- Radio -->
                            <td
                                class="text-center align-middle"
                                style="width: 40px"
                            >
                                <input
                                    type="radio"
                                    name="balanceOption"
                                    :value="`egreso-${key}`"
                                    v-model="selectedKey"
                                />
                            </td>

                            <!-- Nombre categoría -->
                            <td
                                :class="{
                                    'fw-bold text-danger':
                                        key.includes('total'),
                                }"
                            >
                                {{ formatKey(key) }}
                            </td>

                            <!-- Valor -->
                            <td
                                class="text-end"
                                :class="{
                                    'fw-bold text-danger':
                                        key.includes('total'),
                                }"
                            >
                                {{ formatCurrency(valor) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Haber -->
            <div class="col-md-6">
                <h5 class="mb-3">Haber</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr
                            v-for="(valor, key) in generalData.ingresos"
                            :key="`ingreso-${key}`"
                        >
                            <!-- Radio -->
                            <td
                                class="text-center align-middle"
                                style="width: 40px"
                            >
                                <input
                                    type="radio"
                                    name="balanceOption"
                                    :value="`ingreso-${key}`"
                                    v-model="selectedKey"
                                />
                            </td>

                            <!-- Nombre categoría -->
                            <td
                                :class="{
                                    'fw-bold text-danger':
                                        key.includes('total'),
                                }"
                            >
                                {{ formatKey(key) }}
                            </td>

                            <!-- Valor -->
                            <td
                                class="text-end"
                                :class="{
                                    'fw-bold text-danger':
                                        key.includes('total'),
                                }"
                            >
                                {{ formatCurrency(valor) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Datatable
        v-if="tableName"
        :key="persistentFilters"
        :module="`finanzas/general-accounting/${tableName}`"
        :model="`GeneralAccounting${toUpperCaseFirstLetter(tableName)}`"
        :list="`Listado de ${getTitleTableName(tableName)}`"
        :persistentFilters="persistentFilters"
    >
    </Datatable>

    <AddOperation
        ref="addOperationRef"
        :editId="editOperationId"
        @cleanModal="closeModal()"
    />
    <AddCategory @cleanModal="closeModal()" />
</template>

<script>
import { onMounted, ref, watch, computed, reactive } from "vue";
import Swal from "sweetalert2";
import Datatable from "../../../../base/shared/Datatable.vue";
import Form from "../../../../../helpers/Form";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Modal from "../../../../../helpers/modal";
import AddOperation from "./AddOperation.vue";
import AddCategory from "./AddCategory.vue";

export default {
    name: "Balance",
    props: {
        configId: [String, Number],
        module: String,
        title: String,
    },
    components: {
        Datatable,
        AddOperation,
        AddCategory,
    },
    setup(props, { emit }) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const tableName = ref(null);

        const modal = ref();
        const dataForm = reactive({
            data: new Form({}),
        });
        const selectedKey = ref(null);

        const generalData = ref({ ingresos: {}, egresos: {}, balance: 0 });
        const persistentFilters = ref([]);

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            $(document).on("click", "#button_add_operation", function () {
                showModal("modaloperation");
            });
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                showModal("modaloperation", idItem);
            });
            $(document).on("click", "#button_add_category", function () {
                showModal("modalcategory");
            });
            await getGeneralData();
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const getGeneralData = async () => {
            try {
                const { data } = await axios.get(
                    "/finanzas/general-accounting/get-data"
                );
                if (data.success) {
                    generalData.value = data;
                }
            } catch (error) {
                Swal.fire(
                    "Error",
                    error.response?.data?.message || "Error inesperado",
                    "error"
                );
            }
        };

        const addOperationRef = ref(null);
        const editOperationId = ref(null);

        const showModal = (modalId, id = null) => {
            modal.value = new Modal(`${modalId}`);
            modal.value.show();

            if (modalId === "modaloperation") {
                editOperationId.value = id;
            }
        };

        const closeModal = async () => {
            modal.value.hide();
            reload();
            await getGeneralData();
        };

        const formatKey = (key) => {
            if (key === "total_gastos") return "Total de Gastos";
            if (key === "total_ingresos") return "Total de Ingresos";

            // Reemplazar underscores por espacios y poner primera mayúscula
            return key
                .replace(/_/g, " ")
                .replace(/\b\w/g, (l) => l.toUpperCase());
        };

        const formatCurrency = (value) => {
            const number = Number(value) || 0;
            return number.toLocaleString("es-ES", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        };

        watch(selectedKey, (newVal) => {
            if (newVal) {
                const table = getSlugTable(newVal);
                setFilter(getSlugFilter(newVal));
                if (table) {
                    tableName.value = table;
                }
            }
        });

        const toUpperCaseFirstLetter = (string) => {
            return string.charAt(0).toUpperCase() + string.slice(1);
        };

        const getSlugTable = (key) => {
            const parts = key.split("-");
            if (parts.length < 1) return null;

            if (parts[0] === "egreso") return "expense";
            if (parts[0] === "ingreso") return "income";

            return null;
        };

        const getSlugFilter = (key) => {
            const parts = key.split("-");
            if (parts.length < 1) return null;

            return parts[1];
        };

        const getTitleTableName = (table) => {
            let tableName = table;
            if (table === "expense") tableName = "Gastos";
            if (table === "income") tableName = "Ingresos";

            return tableName
                .replace(/_/g, " ")
                .replace(/\b\w/g, (l) => l.toUpperCase());
        };

        const setFilter = (value) => {
            persistentFilters.value = {
                category: value,
            };
        };

        return {
            datatable,
            dataForm,
            reload,
            table,
            closeModal,
            showModal,
            generalData,
            formatKey,
            formatCurrency,
            selectedKey,
            tableName,
            toUpperCaseFirstLetter,
            setFilter,
            persistentFilters,
            getTitleTableName,
            addOperationRef,
            editOperationId,
        };
    },
};
</script>
