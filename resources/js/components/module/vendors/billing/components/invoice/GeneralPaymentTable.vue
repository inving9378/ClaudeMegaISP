<template>
    <q-table
        v-table-resizable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        wrap-cells
        row-key="id"
        loading-label="Obteniendo datos, por favor espere..."
        no-data-label="No existen datos disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Filas por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[5, 10, 20, 30, 50, 100]"
    >
        <template v-slot:body-cell-additional_sales_commissions="props">
            <q-td>
                <a
                    class="cursor-pointer"
                    v-if="
                        props.row['additional_sales_commissions'] &&
                        (props.row['additional_sales_commissions']['LVA']
                            .length > 0 ||
                            props.row['additional_sales_commissions']['LVC']
                                .length > 0)
                    "
                >
                    Pagado:
                    {{
                        props.row["additional_sales_commissions"]["LVA"]
                            .filter((s) => s.state === "pagada")
                            .reduce((t, s) => t + parseFloat(s.amount), 0)
                    }}<br />
                    Por pagar:
                    {{
                        props.row["additional_sales_commissions"]["LVA"]
                            .filter((s) => s.state === "pendiente")
                            .reduce((t, s) => t + parseFloat(s.amount), 0)
                    }}<br />
                    Total:
                    {{
                        props.row["additional_sales_commissions"]["LVA"].reduce(
                            (t, s) => t + parseFloat(s.amount),
                            0
                        )
                    }}
                    <menu-general-information-table
                        :row="props.row"
                        commission="additional_sales_commissions"
                    />
                </a>
                <span v-else> - </span>
            </q-td>
        </template>

        <template v-slot:body-cell-fixed_salary="props">
            <q-td>
                <a
                    class="cursor-pointer"
                    v-if="
                        props.row['fixed_salary'] &&
                        props.row['fixed_salary']['Total a pagar'] > 0
                    "
                >
                    A pagar:
                    {{ props.row["fixed_salary"]["Total a pagar"] }}<br />
                    Pagado:
                    {{ props.row["fixed_salary"]["Pagado"] }}
                    <menu-general-information-table
                        :row="props.row"
                        commission="fixed_salary"
                    />
                </a>
                <span v-else> - </span>
            </q-td>
        </template>

        <template v-slot:body-cell-sales_commission="props">
            <q-td>
                <a
                    class="cursor-pointer"
                    v-if="
                        props.row['sales_commission'] &&
                        props.row['sales_commission']['Total a pagar'] > 0
                    "
                >
                    A pagar:
                    {{ props.row["sales_commission"]["Total a pagar"] }}<br />
                    Pagado:
                    {{ props.row["sales_commission"]["Pagado"] }}
                    <menu-general-information-table
                        :row="props.row"
                        commission="sales_commission"
                    />
                </a>
                <span v-else> - </span>
            </q-td>
        </template>

        <template v-slot:body-cell-salary="props">
            <q-td>
                Pagado: {{ getPagado(props.row, "pagado") }} <br />
                Por pagar: {{ getPagado(props.row, "pendiente") }}<br />
                Total: {{ getPagado(props.row) }}
            </q-td>
        </template>
    </q-table>

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
import { defineComponent, onMounted, ref, watch } from "vue";
import Modal from "../../../../../../shared/ModalSimple.vue";
import MenuGeneralInformationTable from "./MenuGeneralInformationTable.vue";
import { darkMode } from "../../../../../../hook/appConfig";

defineComponent({
    name: "GeneralPaymentTable",
});

const props = defineProps({
    columns: {
        type: Array,
        default: [],
    },
    rows: {
        type: Array,
        default: [],
    },
    loading: Boolean,
    tableIdentifier: {
        type: String,
        default: "invoice-general-payment-seller",
    },
});

const emits = defineEmits(["change-data"]);

const showModal = ref(false);

onMounted(() => {
    calculateTotalSalary();
});

watch(
    () => props.rows,
    () => {
        calculateTotalSalary();
    },
    {
        deep: true,
    }
);

const calculateTotalSalary = () => {
    let payment = 0,
        pending = 0;
    props.rows.forEach((row) => {
        const { fixed_salary, additional_sales_commissions, sales_commission } =
            row;
        if (fixed_salary && fixed_salary["Total a pagar"] > 0) {
            if (fixed_salary["Pagado"] === "No") {
                pending += fixed_salary["Total a pagar"];
            } else {
                payment += fixed_salary["Total a pagar"];
            }
        }
        if (
            additional_sales_commissions &&
            additional_sales_commissions["LVA"].length > 0
        ) {
            pending += additional_sales_commissions["LVA"]
                .filter((s) => s.state === "pendiente")
                .reduce((t, s) => t + parseFloat(s.amount), 0);
            payment += additional_sales_commissions["LVA"]
                .filter((s) => s.state === "pagada")
                .reduce((t, s) => t + parseFloat(s.amount), 0);
        }
        if (sales_commission && sales_commission["Total a pagar"] > 0) {
            if (sales_commission["Pagado"] === "No") {
                pending += sales_commission["Total a pagar"];
            } else {
                payment += sales_commission["Total a pagar"];
            }
        }
    });

    emits("change-data", {
        payment,
        pending,
    });
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(props.tableIdentifier, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const getPagado = (row, type = "total") => {
    const { fixed_salary, additional_sales_commissions, sales_commission } =
        row;
    let total = 0;
    if (fixed_salary && fixed_salary["Total a pagar"] > 0) {
        if (type === "pendiente") {
            if (fixed_salary["Pagado"] === "No") {
                total += fixed_salary["Total a pagar"];
            }
        } else if (type === "pagado") {
            if (fixed_salary["Pagado"] === "Si") {
                total += fixed_salary["Total a pagar"];
            }
        } else {
            total += fixed_salary["Total a pagar"];
        }
    }
    if (sales_commission && sales_commission["Total a pagar"] > 0) {
        if (type === "pendiente") {
            if (sales_commission["Pagado"] === "No") {
                total += sales_commission["Total a pagar"];
            }
        } else if (type === "pagado") {
            if (sales_commission["Pagado"] === "Si") {
                total += sales_commission["Total a pagar"];
            }
        } else {
            total += sales_commission["Total a pagar"];
        }
    }
    if (
        additional_sales_commissions &&
        additional_sales_commissions["LVA"].length > 0
    ) {
        if (type === "pendiente") {
            total += additional_sales_commissions["LVA"]
                .filter((s) => s.state === "pendiente")
                .reduce((t, s) => t + parseFloat(s.amount), 0);
        } else if (type === "pagado") {
            total += additional_sales_commissions["LVA"]
                .filter((s) => s.state === "pagada")
                .reduce((t, s) => t + parseFloat(s.amount), 0);
        } else {
            total += additional_sales_commissions["LVA"].reduce(
                (t, s) => t + parseFloat(s.amount),
                0
            );
        }
    }
    return total;
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
