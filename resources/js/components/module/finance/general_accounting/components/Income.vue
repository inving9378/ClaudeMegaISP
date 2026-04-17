<template>
    <Datatable
        module="finanzas/general-accounting/income"
        model="GeneralAccountingIncome"
        list="Listado"
        :buttons="getButtonDatatable()"
        :emitsRows="true"
        @emitsRows="calculateAmount"
    >
        <template v-slot:bottom-row>
            <q-tr>
                <q-td colspan="100%">
                    <div>
                        Total de Ingresos por Pagos:
                        {{ amountByPaymentService }}
                    </div>
                    <div>
                        Total de Ingresos por Pagos de Costo de Instalación:
                        {{ amountByCostInstallation }}
                    </div>
                    <div>
                        Total de Ingresos por Pagos de Costo de Activación:
                        {{ amountByCostActivation }}
                    </div>

                    <div>
                        Total de Ingresos por Otros Pagos:
                        {{ amountByIngresoDefault }}
                    </div>

                    <div>
                        Total:
                        {{ amountTotal }}
                    </div>
                </q-td>
            </q-tr>
        </template>
    </Datatable>

    <AddIncome @cleanModal="closeModal()" />
</template>

<script>
import { onMounted, ref, watch, computed, reactive } from "vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import { darkMode } from "../../../../../hook/appConfig";
import Swal from "sweetalert2";
import Datatable from "../../../../base/shared/Datatable.vue";
import Form from "../../../../../helpers/Form";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import AddIncome from "./AddIncome.vue";
import Modal from "../../../../../helpers/modal";
export default {
    name: "Income",
    props: {
        configId: [String, Number],
        module: String,
        title: String,
    },
    components: {
        Datatable,
        AddIncome,
    },
    setup(props, { emit }) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const CATEGORY_INCOME_DEFAULT = "Ingreso Manual";
        const CATEGORY_PAYMENT_SERVICE = "Pago";
        const CATEGORY_COST_INSTALLATION = "Costo de Instalación";
        const CATEGORY_COST_ACTIVATION = "Costo de Activación";
        const amountByIngresoDefault = ref(0);
        const amountByCostInstallation = ref(0);
        const amountByCostActivation = ref(0);
        const amountByPaymentService = ref(0);
        const amountTotal = ref(0);

        const modal = ref();
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            initComponent();
            $(document).on("click", "#button_add_income", function () {
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

        const setFilter = (obj) => {
            const value = obj.value?._value ?? obj.value ?? "";
            filters.value = {
                ...filters.value,
                [obj.field]: value,
            };
        };
        const getButtonDatatable = () => {
            let buttons = {};
            buttons.upload = {
                class: "btn btn-outline-info waves-effect waves-light",
                iclass: "fa fa-plus",
                href: "javascript:void(0)",
                id: "button_add_income",
            };
            return buttons;
        };

        const showModal = async () => {
            modal.value = new Modal("modalincome");
            modal.value.show();
        };

        const closeModal = async () => {
            modal.value.hide();
            reload();
        };

        const calculateAmount = (obj) => {
            // Reiniciar valores como números
            let incomeDefault = 0;
            let costInstallation = 0;
            let costActivation = 0;
            let paymentService = 0;

            for (let i = 0; i < obj.length; i++) {
                const amount = parseFloat(obj[i].amount) || 0;

                if (obj[i].category == CATEGORY_INCOME_DEFAULT) {
                    incomeDefault += amount;
                } else if (obj[i].category == CATEGORY_COST_INSTALLATION) {
                    costInstallation += amount;
                } else if (obj[i].category == CATEGORY_COST_ACTIVATION) {
                    costActivation += amount;
                } else if (obj[i].category == CATEGORY_PAYMENT_SERVICE) {
                    paymentService += amount;
                }
            }

            // Calcular el total ANTES de formatear
            const total =
                incomeDefault +
                costInstallation +
                costActivation +
                paymentService;

            // Formatear como strings solo al final
            amountByIngresoDefault.value = `$${incomeDefault.toFixed(2)} MXN`;
            amountByCostInstallation.value = `$${costInstallation.toFixed(
                2
            )} MXN`;
            amountByCostActivation.value = `$${costActivation.toFixed(2)} MXN`;
            amountByPaymentService.value = `$${paymentService.toFixed(2)} MXN`;
            amountTotal.value = `$${total.toFixed(2)} MXN`;
        };

        return {
            datatable,
            dataForm,
            reload,
            table,
            setFilter,
            getButtonDatatable,
            closeModal,
            calculateAmount,
            amountByIngresoDefault,
            amountByCostInstallation,
            amountByCostActivation,
            amountByPaymentService,
            amountTotal,
        };
    },
};
</script>
