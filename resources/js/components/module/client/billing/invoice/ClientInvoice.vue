<template>
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
        <div class="col-12 col-md-6 col-lg-3">
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
        :persistentFilters="persistentFilters"
        :buttons="getButtonDatatable()"
    ></Datatable>

    <ClientCrudPayment
        :module="'cliente/billing/payment'"
        @cleanModal="cleanModal"
    />
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Swal from "sweetalert2";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import { filters } from "../../../../../helpers/filters";
import Form from "../../../../../helpers/Form";
import Datatable from "../../../../base/shared/Datatable.vue";
import InputVuePickerMultiple from "../../../../../shared/InputVuePickerMultiple.vue";
import SelectLongOptions from "../../../../../shared/SelectLongOptions.vue";
import Modal from "../../../../../helpers/modal";
import ClientCrudPayment from "../../../finance/invoice/components/ClientCrudPayment.vue";
import { clientId } from "../../../finance/invoice/components/comun_variables";
import SelectComponentWithCheckbox from "../../../../../shared/SelectComponentWithCheckbox.vue";

export default {
    name: "ClientInvoice",
    components: {
        Datatable,
        InputVuePickerMultiple,
        SelectLongOptions,
        ClientCrudPayment,
        SelectComponentWithCheckbox,
    },
    props: {
        id: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const due_date = ref("");
        const payment_date = ref("");
        const dataForm = reactive({
            data: new Form({}),
        });
        const getClientIdByUrl = () => {
            const url = window.location.href;
            const match = url.match(/\/cliente\/editar\/(\d+)/);
            return match ? match[1] : null;
        };
        const persistentFilters = {
            client_id: getClientIdByUrl(),
        };

        const clearError = (field) => {
            dataForm.data.errors[field] = null;
        };

        onMounted(() => {
            clientId.value = getClientIdByUrl();
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

            $(document).on("click", ".mark-as-paid", function () {
                let idItem = $(this).parent().attr("id-item");
                markAsPaid(idItem);
            });

            $(document).on("click", "#showCreateInvoiceModal", function () {
                showCreateInvoiceModal();
            });

            $(document).on("click", ".edit-period", function () {
                let periodoAactual = $(this).parent().attr("id-period");
                showEditPeriodInvoiceModal(periodoAactual);
            });
        });

        const markAsPaid = (id) => {
            Swal.fire({
                title: "¿Estás seguro de marcar esta Factura como pagada?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                input: "number",
                inputLabel: "Especifica ID del pago",
                inputPlaceholder: "Ej: 12345",
                inputAttributes: {
                    min: 1,
                    step: 1
                },
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
                inputValidator: (value) => {
                    if (!value) {
                        return "Debes especificar el ID del pago";
                    }
                    if (Number(value) <= 0) {
                        return "El ID del pago debe ser válido";
                    }
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    showLoading("showTextDef");

                    await axios
                        .post(`/finanzas/invoices/mark-as-paid/${id}`, {
                            payment_id: result.value
                        })
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

        const showCreateInvoiceModal = () => {
            Swal.fire({
                title: "¿Estás seguro de crear una nueva Factura para este Cliente?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
                html: `
            <div style="margin-bottom: 15px;">
                <label for="swal-input-cutoff-date" style="display: block; margin-bottom: 5px; font-weight: bold;">Seleccion la fecha de vencimiento, el periodo se extrae de ella *</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="date" id="swal-input-cutoff-date" class="swal2-input" style="flex: 1;" required>
                    <button type="button" id="clear-date-btn" class="swal2-cancel swal2-styled" style="padding: 8px 12px; font-size: 12px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `,
                didOpen: () => {
                    const cutoffDateInput = document.getElementById(
                        "swal-input-cutoff-date"
                    );
                    const confirmButton = Swal.getConfirmButton();
                    const clearDateBtn =
                        document.getElementById("clear-date-btn");

                    // Deshabilitar el botón de confirmación inicialmente
                    confirmButton.disabled = true;

                    // Escuchar cambios en el input de fecha
                    cutoffDateInput.addEventListener("change", () => {
                        if (!cutoffDateInput.value) {
                            confirmButton.disabled = true; // Deshabilitar si no hay fecha
                        } else {
                            confirmButton.disabled = false; // Habilitar si hay fecha
                        }
                    });

                    // Función para limpiar la fecha
                    clearDateBtn.addEventListener("click", () => {
                        cutoffDateInput.value = "";
                        confirmButton.disabled = true; // Deshabilitar el botón al limpiar
                    });
                },
                preConfirm: () => {
                    const cutoffDate = document.getElementById(
                        "swal-input-cutoff-date"
                    ).value;

                    if (!cutoffDate) {
                        Swal.showValidationMessage(
                            "Por favor, seleccione una fecha de corte"
                        );
                        return false;
                    }

                    return {
                        fecha_corte: cutoffDate,
                    };
                },
            }).then(async (result) => {
                if (result.isConfirmed) {
                    showLoading("showTextDef");
                    await axios
                        .post(
                            `/finanzas/invoices/create-for-client/${props.id}`,
                            result.value
                        )
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success) {
                                Swal.fire("Éxito", message, "success");
                            } else {
                                Swal.fire("Error", message, "error");
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

        const showEditPeriodInvoiceModal = (periodo_actual) => {
            Swal.fire({
                title: "¿Estás seguro de editar el periodo de esta Factura?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
                html: `
            <div style="margin-bottom: 15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">
                    Seleccione el nuevo periodo (Mes/Año) *
                </label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="month" id="swal-input-cutoff-date" class="swal2-input" style="flex:1;">
                    <button type="button" id="clear-date-btn" class="swal2-cancel swal2-styled" style="padding:8px 12px;font-size:12px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `,
                didOpen: () => {
                    const input = document.getElementById(
                        "swal-input-cutoff-date"
                    );
                    const confirm = Swal.getConfirmButton();
                    const clear = document.getElementById("clear-date-btn");

                    confirm.disabled = true;

                    input.addEventListener("change", () => {
                        confirm.disabled = !input.value;
                    });

                    clear.addEventListener("click", () => {
                        input.value = "";
                        confirm.disabled = true;
                    });
                },
                preConfirm: () => {
                    const newPeriod = document.getElementById(
                        "swal-input-cutoff-date"
                    ).value;

                    if (!newPeriod) {
                        Swal.showValidationMessage(
                            "Debe seleccionar un periodo válido."
                        );
                        return false;
                    }

                    return {
                        periodo_actual: periodo_actual, // ← MANDANDO VALOR DESDE EL BOTÓN
                        periodo_nuevo: newPeriod, // ← VALOR NUEVO SELECCIONADO
                    };
                },
            }).then(async (result) => {
                if (result.isConfirmed) {
                    showLoading("showTextDef");

                    await axios
                        .post(
                            `/finanzas/invoices/edit-period/${props.id}`,
                            result.value
                        )
                        .then(({ data }) => {
                            data.success
                                ? Swal.fire("Éxito", data.message, "success")
                                : Swal.fire("Error", data.message, "error");
                            reload();
                        })
                        .catch((error) => {
                            Swal.fire(
                                "Error",
                                error.response?.data?.message ??
                                    "Ocurrió un error inesperado.",
                                "error"
                            );
                        })
                        .finally(() => hideLoading());
                }
            });
        };

        const getButtonDatatable = () => {
            let buttons = {};
            buttons.contract = {
                class: "btn btn-outline-info waves-effect waves-light",
                iclass: "fa fa-plus",
                href: `javascript:void(0)`,
                id: "showCreateInvoiceModal",
            };
            return buttons;
        };

        return {
            table,
            reload,
            due_date,
            setFilter,
            dataForm,
            payment_date,
            clientId,
            modal,
            paidInvoice,
            cleanModal,
            filters,
            persistentFilters,
            clearError,
            getClientIdByUrl,
            showCreateInvoiceModal,
            getButtonDatatable,
        };
    },
};
</script>

<style scoped></style>
