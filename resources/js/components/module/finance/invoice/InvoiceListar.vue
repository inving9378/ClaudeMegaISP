<template>
    <div class="row align-items-end g-3 mb-3">
        <div class="col-md-6">
            <SelectComponentClient
                :property="{
                    field: 'client_id',
                    label: 'Cliente',
                    class_field: '',
                    class_label: '',
                    placeholder: '',
                    search: {
                        model: 'App\\Models\\ClientMainInformation',
                        id: 'client_id',
                        text: 'name',
                    },
                }"
                @update-field="setFilter"
                :errors="dataForm.data.errors"
                :modelValue="client_id"
                @change="clearError('client_id')"
            >
            </SelectComponentClient>
        </div>
    </div>
    <div class="row align-items-end g-3 mb-3">
        <div class="col-12 col-md-6 col-lg-3">
            <SelectLongOptions
                :property="{
                    field: 'period',
                    label: 'Periodo',
                    class_col: 'full',
                    search: {
                        model: 'App\\Models\\Invoice',
                        id: `period`,
                        text: 'period',
                    },
                }"
                @change="clearError('period')"
                :modelValue="[]"
                :errors="dataForm.data.errors"
                @update-field="setFilter"
            />
        </div>
        <div class="col-12 col-md-6 col-lg-3" style="top: 19px">
            <SelectComponentWithCheckbox
                :property="{
                    field: 'status',
                    label: 'Estado',
                    class_col: 'full',
                    options: {
                        draft: 'Emitida',
                        issued: 'Enviada',
                        partially_paid: 'Parcialmente Pagada',
                        paid: 'Pagada',
                        overdue: 'Vencida',
                        cancelled: 'Cancelada',
                    },
                }"
                @change="clearError('period')"
                :modelValue="[]"
                :errors="dataForm.data.errors"
                @update-field="setFilter"
            />
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <InputVuePickerMultiple
                :property="{
                    field: 'due_date',
                    label: 'Fecha de Vencimiento',
                    class_field: '',
                    class_label: '',
                    placeholder: 'Fecha de Vencimiento',
                }"
                @update-field="setFilter"
                :modelValue="due_date"
                :errors="dataForm.data.errors"
                @change="clearError('due_date')"
            >
            </InputVuePickerMultiple>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <InputVuePickerMultiple
                :property="{
                    field: 'payment_date',
                    label: 'Fecha de Pago',
                    class_field: '',
                    class_label: '',
                    placeholder: 'Fecha de Pago',
                }"
                @update-field="setFilter"
                :modelValue="payment_date"
                :errors="dataForm.data.errors"
                @change="clearError('payment_date')"
            >
            </InputVuePickerMultiple>
        </div>
    </div>

    <Datatable
        module="finanzas/invoices"
        model="Invoice"
        list="Listado"
    ></Datatable>

    <ClientCrudPayment
        :module="'cliente/billing/payment'"
        @cleanModal="cleanModal"
    />
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import Swal from "sweetalert2";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import { filters } from "../../../../helpers/filters";
import Form from "../../../../helpers/Form";
import Datatable from "../../../base/shared/Datatable.vue";
import InputVuePickerMultiple from "../../../../shared/InputVuePickerMultiple.vue";
import SelectComponentClient from "../../../../shared/SelectComponentClient.vue";
import SelectLongOptions from "../../../../shared/SelectLongOptions.vue";
import Modal from "../../../../helpers/modal";
import ClientCrudPayment from "./components/ClientCrudPayment.vue";
import { clientId } from "./components/comun_variables";
import SelectComponentWithCheckbox from "../../../../shared/SelectComponentWithCheckbox.vue";

export default {
    name: "InvoiceListar",
    components: {
        Datatable,
        InputVuePickerMultiple,
        SelectComponentClient,
        SelectLongOptions,
        ClientCrudPayment,
        SelectComponentWithCheckbox,
    },
    props: {},
    setup(props) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const client_id = ref(null);

        const due_date = ref("");
        const payment_date = ref("");
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            $(document).on("click", ".invoice-send", function () {
                let idItem = $(this).parent().attr("id-item");
                sendEmail(idItem);
            });
            $(document).on("click", ".invoice-print", function () {
                let idItem = $(this).parent().attr("id-item");
                printInvoice(idItem);
            });
            $(document).on("click", ".invoice-paid", function () {
                clientId.value = $(this).attr("client-id");
                paidInvoice();
            });
        });

        const sendEmail = async (id) => {
            Swal.fire({
                title: "¿Estás seguro de enviar esta Factura?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    showLoading("showTextDef");
                    await axios
                        .post(`/finanzas/invoices/send/${id}`)
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success) {
                                Swal.fire("Éxito", message, "success");
                                reload();
                            }
                        })
                        .catch((error) => {
                            Swal.fire(
                                "Error",
                                error.response?.data?.message ||
                                    "Ocurrido un error inesperado.",
                                "error"
                            );
                        })
                        .finally(() => {
                            hideLoading();
                        });
                }
            });
        };

        const printInvoice = async (id) => {
            window.open(`/finanzas/invoices/print/${id}`, "_blank");
        };

        const modal = ref();
        const paidInvoice = async () => {
            modal.value = new Modal("modalpayment");
            modal.value.show();
        };

        const cleanModal = () => {
            clientId.value = null;
            modal.value.hide();
        };

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

        return {
            table,
            reload,
            due_date,
            setFilter,
            dataForm,
            payment_date,
            client_id,
            clientId,
            modal,
            paidInvoice,
            cleanModal,
        };
    },
};
</script>

<style scoped></style>
