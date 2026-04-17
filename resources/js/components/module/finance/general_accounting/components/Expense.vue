<template>
    <Datatable
        module="finanzas/general-accounting/expense"
        model="GeneralAccountingExpense"
        list="Listado"
        :buttons="getButtonDatatable()"
        :emitsRows="true"
        @emitsRows="calculateAmount"
    >
        <template v-slot:bottom-row>
            <q-tr>
                <q-td colspan="100%">
                    <div>
                        Total de Gastos Corriente:
                        {{ amountByExpenseManual }}
                    </div>

                    <div>
                        Total:
                        {{ amountTotal }}
                    </div>
                </q-td>
            </q-tr>
        </template>
    </Datatable>

    <AddExpense @cleanModal="closeModal()" />
</template>

<script>
import { onMounted, ref, watch, computed, reactive } from "vue";
import Datatable from "../../../../base/shared/Datatable.vue";
import Form from "../../../../../helpers/Form";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import AddExpense from "./AddExpense.vue";
import Modal from "../../../../../helpers/modal";
export default {
    name: "Expense",
    props: {
        configId: [String, Number],
        module: String,
        title: String,
    },
    components: {
        Datatable,
        AddExpense,
    },
    setup(props, { emit }) {
        const CATEGORY_EXPENSE_DEFAULT = "Gasto Manual";
        const amountByExpenseManual = ref(0);
        const amountTotal = ref(0);

        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const modal = ref();
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            initComponent();
            $(document).on("click", "#button_add_expense", function () {
                showModal();
            });
        });

        const initComponent = async () => {};

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };
        const getButtonDatatable = () => {
            let buttons = {};
            buttons.upload = {
                class: "btn btn-outline-info waves-effect waves-light",
                iclass: "fa fa-plus",
                href: "javascript:void(0)",
                id: "button_add_expense",
            };
            return buttons;
        };

        const showModal = async () => {
            modal.value = new Modal("modalexpense");
            modal.value.show();
        };

        const closeModal = async () => {
            modal.value.hide();
            reload();
        };

        const calculateAmount = (obj) => {
            // Reiniciar valores como números
            let amountExpenseManual = 0;

            for (let i = 0; i < obj.length; i++) {
                const amount = parseFloat(obj[i].amount) || 0;

                if (obj[i].category == CATEGORY_EXPENSE_DEFAULT) {
                    amountExpenseManual += amount;
                }
            }

            // Calcular el total ANTES de formatear
            const total = amountExpenseManual;

            // Formatear como strings solo al final
            amountByExpenseManual.value = `$${amountExpenseManual.toFixed(
                2
            )} MXN`;

            amountTotal.value = `$${total.toFixed(2)} MXN`;
        };

        return {
            datatable,
            dataForm,
            reload,
            table,
            getButtonDatatable,
            closeModal,
            showModal,
            calculateAmount,
            amountByExpenseManual,
            amountTotal,
        };
    },
};
</script>
