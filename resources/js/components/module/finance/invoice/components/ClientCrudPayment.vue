<template>
    <div
        class="modal fade"
        id="modalpayment"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
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
                            v-if="costAllService"
                        >
                            <span
                                >Este pago pertenece al periodo:
                                <b>{{ period }}</b></span
                            >
                            <br />

                            <strong>Costo de todos los Servicios: </strong>
                            <span
                                >{{ costAllService }}
                                <span v-if="promotions"
                                    >(Con promoción incluida)</span
                                ></span
                            >
                            <template v-if="promotions">
                                <br />
                                <b class="customer-name-wrapper me-1">
                                    Servicios con Promociones Activas:
                                </b>
                                <span class="customer-billing-balance-title">
                                    <template
                                        v-for="(
                                            [url, index], i
                                        ) in Object.entries(promotions)"
                                        :key="index"
                                    >
                                        <a :href="url" target="_blank">{{
                                            index
                                        }}</a>
                                        <span
                                            v-if="
                                                i <
                                                Object.entries(promotions)
                                                    .length -
                                                    1
                                            "
                                            >,
                                        </span>
                                        <span v-else>.</span>
                                    </template>
                                </span>
                            </template>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>

                        <div
                            class="alert alert-info alert-dismissible fade show mb-2"
                            role="alert"
                        >
                            <strong>Vencimiento del Servicio</strong> -
                            {{ activeServiceExpiration }}
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
                                :id="clientId"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="form-group text-center">
                            <a
                                class="btn btn-secondary me-3"
                                href="javascript:void(0)"
                                @click="closeModal"
                            >
                                Cerrar
                            </a>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="
                                    dataForm.data.errors.any() || disabledButton
                                "
                            >
                                Aplicar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted, onBeforeMount } from "vue";
import { getPromotions } from "../../../client/billing/helpers/request";
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import { requestFieldsByModule } from "../../../../../helpers/Request";
import Form from "../../../../../helpers/Form";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { clientId } from "./comun_variables";
import Swal from "sweetalert2";

export default {
    name: "ClientCrudPayment",
    props: {
        module: {
            type: String,
            default: null,
        },
    },
    components: { ComponentFormDefault },
    emits: ["cleanModal"],
    setup(props, { emit }) {
        const title = ref("Crear Pago");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const costAllServiceActive = ref(0);
        const costAllService = ref(0);
        const activeServiceExpiration = ref(0);
        const showImage = ref(false);
        const getShowImage = ref("");
        const disabledButton = ref(false);
        const activePromisePayment = ref(true);
        const period = ref(null);

        const promotions = ref(null);

        const showButtonPeriod = ref(false);

        const invoicesPending = ref([]);

        watch(clientId, () => {
            if (clientId.value) {
                initComponent();
            }
        });

        watch(dataForm, () => {
            if (dataForm.data.file) {
                if (
                    dataForm.data.file.type == "png" ||
                    dataForm.data.file.type == "jpg" ||
                    dataForm.data.file.type == "jpeg"
                ) {
                    showImage.value = true;
                    getShowImage.value = dataForm.data.file.path;
                } else if ($('input[name="file"]').length) {
                    let type = $('input[name="file"]')[0].files[0].type;
                    if (
                        type.includes("jpg") ||
                        type.includes("png") ||
                        type.includes("jpeg")
                    ) {
                        showImage.value = true;
                        getShowImage.value = URL.createObjectURL(
                            $('input[name="file"]')[0].files[0]
                        );
                    } else {
                        showImage.value = false;
                    }
                }
            } else {
                showImage.value = false;
            }

            if (amountIsGreaterThanCostAllServiceActive()) {
                showButtonPeriod.value = true;
            } else {
                showButtonPeriod.value = false;
            }
        });

        const amountIsGreaterThanCostAllServiceActive = () => {
            return dataForm.data.amount >= costAllServiceActive.value;
        };

        const initComponent = async () => {
            if (clientId.value == null) {
                return;
            }
            await getInvoicesPending(clientId.value);
            await getfieldsJson("ClientPayment");
            if (invoicesPending.value.length > 0) {
                costAllServiceActive.value = invoicesPending.value[0].total;
                activeServiceExpiration.value =
                    invoicesPending.value[0].due_date;

                costAllService.value = invoicesPending.value[0].total;
                promotions.value = await getPromotions(clientId.value);
                updateThisField({
                    field: "amount",
                    value: invoicesPending.value[0].pending_balance,
                });
                fieldsJson.value.amount.disabled = true;
                updateThisField({
                    field: "receipt",
                    value: invoicesPending.value[0].number,
                });
                updateThisField({
                    field: "payment_period",
                    value: invoicesPending.value[0].period,
                });
                period.value = invoicesPending.value[0].period;
            }
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            fieldsJson.value.enabled_payment_promise.include = false;
            dataForm.data = new Form(fieldsJson.value);
            title.value = "Crear Gasto";
        };

        const getInvoicesPending = async (id) => {
            showLoading();
            await axios
                .post(`/finanzas/invoices/get-pending-by-client/${id}`)
                .then((response) => {
                    const { success, message } = response.data;
                    if (success == true) {
                        invoicesPending.value = response.data.invoices;
                        if (invoicesPending.value.length == 0) {
                            Swal.fire(
                                "Error",
                                "Este cliente no tiene facturas pendientes, aplique el pago en la Sección de Pagos",
                                "error"
                            );
                            emit("cleanModal");
                        }
                    } else {
                        Swal.fire("Error", message, "error");
                    }
                    hideLoading();
                })
                .catch((error) => {
                    emit("cleanModal");
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrido un error inesperado.",
                        "error"
                    );
                    hideLoading();
                });
        };

        const onSubmit = () => {
            if (dataForm.data.amount < costAllService.value) {
                if (
                    !confirm(
                        "El monto ingresado es menor al costo de todos los servicios, ¿Desea continuar?"
                    )
                ) {
                    return;
                }
            }

            disabledButton.value = true;
            dataForm.data
                .uploadFile(
                    `/cliente/billing/payment/crear/${clientId.value}`,
                    title.value == "Crear Gasto" ? "reset" : "editar"
                )

                .then((response) => {
                    const { success, message } = response.data;
                    if (success == true) {
                        Swal.fire("Exito", message, "success");
                        emit("cleanModal");
                    } else {
                        Swal.fire("Error", message, "error");
                    }
                })
                .catch((error) => {
                    console.log(error);
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrido un error inesperado.",
                        "error"
                    );
                    emit("cleanModal");
                })
                .finally(() => {
                    hideLoading();
                    emit("cleanModal");
                });
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
            disabledButton.value = false;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            updateThisField,
            clearError,
            closeModal,
            title,
            costAllServiceActive,
            activeServiceExpiration,
            disabledButton,
            costAllService,
            activePromisePayment,
            promotions,
            showButtonPeriod,
            clientId,
            period,
        };
    },
};
</script>

<style scoped></style>
