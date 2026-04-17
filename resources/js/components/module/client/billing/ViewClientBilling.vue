<template>
    <div class="p-5">
        <div class="row mt-4">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row justify-between">
                        <ClientInfoAccountBalance
                            :client_id="id"
                            :authuserid="authuserid"
                        ></ClientInfoAccountBalance>
                        <div
                            class="col-12 col-md-6 col-lg-3 text-center"
                            v-if="data.balance < 0"
                        >
                            <button
                                class="btn btn-primary"
                                @click="showAddModal"
                                type="button"
                            >
                                Negociaciones
                            </button>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3 text-center">
                            <button
                                class="btn btn-primary"
                                type="button"
                                @click="showCreateInvoiceModal"
                            >
                                Crear Factura Manual
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 p-4 ml-xl-5 border h-fix-content shadow-low">
                <ClientBillingConfiguration
                    :id="id"
                    :typeOfBilling="typeOfBilling"
                />
            </div>

            <div class="col-xl-6 mt-md-3 mt-xl-0">
                <div
                    class="d-flex flex-wrap justify-between align-self-center p-4 ml-xl-3 border shadow-low"
                >
                    <div class="">
                        <b class="customer-name-wrapper me-1"
                            >Costo por mes de servicio:</b
                        >
                        <span class="customer-billing-balance-title"
                            >$ {{ data.cost_for_month }}</span
                        >
                        <span v-if="data.promotions"
                            >(Con promoción incluida)</span
                        ><br />
                        <b class="customer-name-wrapper me-1">
                            <span
                                style="margin-right: 5px"
                                v-if="
                                    hasPermission.data.canView(
                                        'client_edit_fecha_corte'
                                    )
                                "
                                ><i
                                    class="fas fa-question cursor-pointer border-1 radio mr-2"
                                    @click="
                                        showModalEdit('modaleditUpdaeCourt')
                                    "
                                ></i
                            ></span>
                            <span>Fecha de corte:</span>
                        </b>
                        <span
                            class="customer-billing-balance-title"
                            v-if="!data.expired"
                            >{{ data.expiration_date }}</span
                        >
                        <span
                            class="customer-billing-balance-title bg-danger fw-bold text-white"
                            :key="data.lastHistoryExpirationDate"
                            v-else
                            >{{ data.lastHistoryExpirationDate }}</span
                        >
                        <br />
                        <b class="customer-name-wrapper me-1"
                            >Meganet suspendera:</b
                        >
                        <span
                            class="customer-billing-balance-title"
                            v-if="data.hora_planificada"
                        >
                            {{ data.hora_planificada }}</span
                        >
                        <br />
                        <b class="customer-name-wrapper me-1">
                            <span
                                style="margin-right: 5px"
                                v-if="
                                    hasPermission.data.canView(
                                        'client_edit_fecha_pago'
                                    )
                                "
                                ><i
                                    class="fas fa-question cursor-pointer"
                                    @click="
                                        showModalEdit('modaleditUpdatePayment')
                                    "
                                ></i
                            ></span>
                        </b>
                        <span>Meganet cobrará:</span>
                        <span
                            class="customer-billing-balance-title"
                            v-if="data.fecha_pago"
                        >
                            {{ data.fecha_pago }}</span
                        >
                    </div>
                    <div class="">
                        <b class="customer-name-wrapper me-1"
                            >Último pago realizado:</b
                        >
                        <span class="customer-billing-balance-title">
                            {{ data.ultimo_pago }}</span
                        >
                        <br />
                        <b class="customer-name-wrapper me-1"
                            >Monto del ultimo pago:</b
                        >
                        <span class="customer-billing-balance-title">
                            {{ data.amount_last_payment }} $</span
                        >

                        <template
                            v-if="data.has_services_with_installation_cost"
                        >
                            <br />
                            <b class="customer-name-wrapper me-1">
                                <span
                                    @click="costInstalationPayment"
                                    style="margin-right: 5px"
                                    v-if="
                                        data.has_services_with_unpaid_installation_costs
                                    "
                                    ><i
                                        class="fas fa-question cursor-pointer"
                                    ></i></span
                                ><span>Costo de Instalación:</span></b
                            >
                            <span
                                class="customer-billing-balance-title bg-danger fw-bold text-white"
                                v-if="
                                    data.has_services_with_unpaid_installation_costs
                                "
                            >
                                {{ data.amount_instalation_cost }} $</span
                            >
                            <span class="customer-billing-balance-title" v-else>
                                Pagado</span
                            >
                        </template>
                        <br />
                        <b class="customer-name-wrapper me-1">
                            <span
                                v-if="data.has_unpaid_activation_cost"
                                @click="costActivationPayment"
                                style="margin-right: 5px"
                                ><i
                                    class="fas fa-question cursor-pointer"
                                ></i></span
                            ><span>Costo de Activación:</span></b
                        >
                        <span
                            class="customer-billing-balance-title bg-danger fw-bold text-white"
                            v-if="data.has_unpaid_activation_cost"
                        >
                            {{ data.amount_activation_cost }} $</span
                        >
                        <span class="customer-billing-balance-title" v-else>
                            Pagado</span
                        >

                        <template v-if="data.promotions">
                            <br />
                            <b class="customer-name-wrapper me-1">
                                Servicios con Promociones Activas:
                            </b>
                            <span class="customer-billing-balance-title">
                                <template
                                    v-for="([url, index], i) in Object.entries(
                                        data.promotions
                                    )"
                                    :key="index"
                                >
                                    <a :href="url" target="_blank">{{
                                        index
                                    }}</a>
                                    <span
                                        v-if="
                                            i <
                                            Object.entries(data.promotions)
                                                .length -
                                                1
                                        "
                                        >,
                                    </span>
                                    <span v-else>.</span>
                                </template>
                            </span>
                        </template>
                    </div>
                </div>

                <ClientPaymentAccount :id="id" />
                <br />
                <ClientBillingAddress :id="id" />
                <br />
                <ClientRemindersConfiguration
                    v-if="isRecurrentTypeOfBilling"
                    :id="id"
                />
            </div>
        </div>
    </div>
    <div
        class="modal fade"
        id="modalAgreement"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ modalTitle }}</h6>
                </div>
                <div class="modal-body m-0">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <div
                            class="alert alert-info alert-dismissible fade show mb-2"
                            role="alert"
                            v-if="clientDebit"
                        >
                            <strong>Débito : </strong> {{ clientDebit }}
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>

                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="id"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div
                            class="alert alert-info alert-dismissible fade show mb-2"
                            role="alert"
                            v-if="clientDebit"
                        >
                            <strong
                                >Descuendo del {{ porcent }}% a pagar:
                            </strong>
                            {{ clientPay }}
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>

                        <div class="form-group text-center">
                            <button
                                type="button"
                                class="btn btn-light waves-effect me-2"
                                data-bs-dismiss="modal"
                            >
                                Cerrar
                            </button>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="disableSubmit"
                            >
                                Aplicar
                            </button>
                        </div>
                    </form>
                </div>
                <br />
            </div>
        </div>
    </div>

    <UpdateCourt
        :iDClient="id"
        :actual="fechaCorte"
        @updateInformation="getBillingInformationBlock"
    >
    </UpdateCourt>
    <UpdateDatePayment
        :iDClient="id"
        :actual="fechaPago"
        @updateInformation="getBillingInformationBlock"
    >
    </UpdateDatePayment>
    <UpdateBalance
        :actual="data.balance"
        :iDClient="id"
        :module="'Client'"
        @updateInformation="changeBalance = true"
    >
    </UpdateBalance>
</template>

<script>
import ClientBillingConfiguration from "./ClientBillingConfiguration";
import ClientPaymentAccount from "./ClientPaymentAccount";
import ClientBillingAddress from "./ClientBillingAddress";
import ClientRemindersConfiguration from "./ClientRemindersConfiguration";
import ClientInfoAccountBalance from "../info/ClientInfoAccountBalance.vue";
import ComponentFormDefault from "../../../../components/ComponentFormDefault";
import UpdateCourt from "./helpers/UpdateCourt.vue";
import UpdateDatePayment from "./helpers/UpdateDatePayment.vue";

import { onMounted, reactive, ref, watch } from "vue";
import {
    requestBillingInformationBlock,
    getClientDebit,
    requestPaymentMethod,
} from "./helpers/request";
import Form from "../../../../helpers/Form";
import Modal from "../../../../helpers/modal";
import {
    allViewHasPermission,
    requestFieldsByModule,
} from "../../../../helpers/Request";
import { changeBalance } from "../info/comun_variable";
import UpdateBalance from "./helpers/UpdateBalance.vue";
import Permission from "../../../../helpers/Permission";
import Swal from "sweetalert2";
import { hideLoading, showLoading } from "../../../../helpers/loading";

export default {
    name: "ViewClientBilling",
    components: {
        ClientRemindersConfiguration,
        ClientBillingAddress,
        ClientPaymentAccount,
        ClientBillingConfiguration,
        ClientInfoAccountBalance,
        ComponentFormDefault,
        requestPaymentMethod,
        UpdateCourt,
        UpdateDatePayment,
        UpdateBalance,
    },
    props: {
        id: String,
        typeOfBilling: String,
        authuserid: Number | String,
    },
    setup(props) {
        const isRecurrentTypeOfBilling = ref(props.typeOfBilling == 1);
        const data = ref({});
        const modal = ref();
        const modalTitle = ref("Acuerdo de Rectificación de Débito");
        const clientDebit = ref(0);
        const clientDebitCalc = ref(0);
        const disableSubmit = ref(true);
        const clientPay = ref(0);
        const payment_method = ref({});
        const porcent = ref(0);
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        const fechaCorte = ref();
        const fechaPago = ref();

        const hasPermission = reactive({
            data: new Permission({}),
        });

        onMounted(async () => {
            await getBillingInformationBlock();
            hasPermission.data = new Permission(await allViewHasPermission());
            clientDebit.value = await getClientDebit(props.id);
            modal.value = new Modal("modalAgreement");
            fieldsJson.value = await requestFieldsByModule(
                "ClientDebitRectificationAgreement"
            );
            dataForm.data = new Form(fieldsJson.value);
            disableSubmit.value = true;
            porcent.value = 0;
            clientDebitCalc.value = 0;
        });

        const showAddModal = async () => {
            modal.value.show();
        };

        const showModalEdit = async (modal) => {
            $(`#${modal}`).modal("show");
        };

        const getBillingInformationBlock = async () => {
            data.value = await requestBillingInformationBlock(props.id);
            fechaCorte.value = data.value.expiration_date;
            fechaPago.value = data.value.fecha_pago;
        };

        watch(
            () => props.typeOfBilling,
            (typeOfBilling, typeBefore) => {
                isRecurrentTypeOfBilling.value = typeOfBilling == 1;
            }
        );

        watch(changeBalance, async () => {
            if (changeBalance.value == true) {
                data.value = await requestBillingInformationBlock(props.id);
            }
            changeBalance.value = false;
        });

        const updateThisField = async ({ field, value }) => {
            if (field == "apply_group_of_months") {
                porcent.value = value.value;
                porcent.value = "0." + porcent.value;
                clientDebitCalc.value = clientDebit.value * -1;
                if (porcent.value) {
                    clientPay.value = (
                        clientDebitCalc.value -
                        clientDebitCalc.value * porcent.value
                    ).toFixed(2);
                }
            }

            if (field == "payment_method_id") {
                payment_method.value = await requestPaymentMethod(value.value);
                if (payment_method.value.type == "Acuerdo de Pago") {
                    disableSubmit.value = false;
                } else {
                    disableSubmit.value = true;
                }
            }
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/billing/client-debit-rectification-agreement/${props.id}`,
                    "update"
                )
                .then((response) => {});

            modal.value.hide();
        };

        const costActivationPayment = () => {
            Swal.fire({
                title: "¿Estás seguro de ejecutar el pago?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
            }).then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(`/cliente/payment_activation_cost`, {
                            id_client: props.id,
                        })
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success) {
                                Swal.fire("Éxito", message, "success");
                                getBillingInformationBlock();
                            } else {
                                Swal.fire("Error", message, "error");
                            }
                        })
                        .catch((error) => {
                            Swal.fire(
                                "Error",
                                error.response?.data?.message ||
                                    "Ocurrió un error inesperado.",
                                "error"
                            );
                        });
                }
            });
        };

        const costInstalationPayment = () => {
            Swal.fire({
                title: "¿Estás seguro de ejecutar el pago?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
            }).then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(`/cliente/payment_instalation_cost/services`, {
                            id_client: props.id,
                        })
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success) {
                                Swal.fire("Éxito", message, "success");
                                getBillingInformationBlock();
                            } else {
                                Swal.fire("Error", message, "error");
                            }
                        })
                        .catch((error) => {
                            Swal.fire(
                                "Error",
                                error.response?.data?.message ||
                                    "Ocurrió un error inesperado.",
                                "error"
                            );
                        });
                }
            });
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

        return {
            isRecurrentTypeOfBilling,
            clientDebit,
            porcent,
            clientPay,
            disableSubmit,
            data,
            showAddModal,
            modalTitle,
            fieldsJson,
            dataForm,
            updateThisField,
            clearError,
            onSubmit,
            showModalEdit,
            fechaCorte,
            getBillingInformationBlock,
            fechaPago,
            changeBalance,
            hasPermission,
            costActivationPayment,
            costInstalationPayment,
            showCreateInvoiceModal,
        };
    },
};
</script>

<style scoped></style>
