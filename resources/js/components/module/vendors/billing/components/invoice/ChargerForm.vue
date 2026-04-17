<template>
    <q-btn
        label="Cobrar"
        no-caps
        color="success"
        :disabled="disabled"
        class="q-mr-sm"
        @click="showDialog = true"
    />
    <q-dialog
        v-model="showDialog"
        persistent
        allow-focus-outside
        @show="onShow"
    >
        <q-card style="width: 1200px; max-width: 1300vw">
            <q-card-section>
                <div class="text-h6">Cobrar deudas</div>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-form ref="formRef">
                    <div class="row q-mb-md">
                        <div
                            class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12"
                        >
                            <label for="date" class="form-label">Fecha</label>
                            <input
                                id="date"
                                type="date"
                                class="form-control"
                                v-model="formData.date"
                            />
                            <span
                                class="text-danger"
                                v-if="errors.includes('date')"
                                >Requerido</span
                            >
                        </div>
                        <div
                            class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12"
                        >
                            <label for="discount" class="form-label"
                                >A cobrar</label
                            >
                            <input
                                id="discount"
                                class="form-control"
                                v-model="formData.discount"
                                readonly
                                disable
                            />
                            <span
                                class="text-danger"
                                v-if="errors.includes('discount')"
                                >Requerido</span
                            >
                        </div>
                        <div
                            class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12"
                        >
                            <label for="email" class="form-label"
                                >Número de recibo</label
                            >
                            <div class="input-group">
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="formData.invoice_number"
                                    readonly
                                    disable
                                />
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    @click="createReceiptNumber"
                                >
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                            <span
                                class="text-danger"
                                v-if="errors.includes('invoice_number')"
                                >Requerido</span
                            >
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label for="comments" class="form-label"
                                >Comentarios</label
                            >
                            <textarea
                                v-model="formData.comments"
                                id="comments"
                                class="form-control"
                            ></textarea>
                        </div>
                    </div>
                    <q-table
                        v-table-resizable
                        :columns="columns"
                        :rows="formData.sales"
                        :dark="darkMode"
                        wrap-cells
                        row-key="id"
                        color="primary"
                        flat
                        hide-bottom
                        :pagination="{
                            rowsPerPage: 0,
                        }"
                    >
                        <template v-slot:body-cell-to_pay="props">
                            <q-td :props="props">
                                <q-input
                                    dense
                                    outlined
                                    type="number"
                                    v-model.number="props.row.to_pay"
                                    @update:model-value="onUpdateToPaid"
                                />
                            </q-td>
                        </template>
                        <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    icon="mdi-trash-can-outline"
                                    color="red-7"
                                    dense
                                    @click="deleteRow(props.row.id)"
                                />
                            </q-td>
                        </template>
                    </q-table>
                </q-form>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Vista previa"
                    no-caps
                    color="success"
                    class="q-mr-sm"
                    @click="showDetails"
                />
                <q-btn
                    label="Cobrar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="charger"
                    v-if="formData.sales.length > 0"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <discount-preview-view
        v-model:showModal="showDetailModal"
        :html="detailPayment"
        @save="charger"
    />
</template>

<script setup>
import { watch, ref, onMounted } from "vue";
import { collectDebt, showDetailsFromDiscountType } from "../../helper/helper";
import { showLoading, hideLoading } from "../../../../../../helpers/loading.js";
import Swal from "sweetalert2";
import moment from "moment";
import { darkMode } from "../../../../../../hook/appConfig.js";
import DiscountPreviewView from "./DiscountPreviewView.vue";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    chargerList: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["created", "hide"]);
const formRef = ref(null);
const formData = ref({
    seller_id: null,
    date: null,
    discount: 0,
    invoice_number: null,
    comments: null,
    sales: [],
});
const errors = ref([]);
const columns = [
    {
        name: "client_id",
        field: "client_id",
        label: "Id del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "client",
        field: "client",
        label: "Nombre del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "service",
        field: "service",
        label: "Servicio",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "date",
        field: "date",
        label: "Activación",
        align: "right",
        sortable: true,
    },
    {
        name: "installation_cost",
        field: "installation_cost",
        label: "Costo de instalación",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "amount_by_client",
        field: "amount_by_client",
        label: "Pagado por el cliente",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "discount_by_client",
        field: "discount_by_client",
        label: "Deuda por cliente",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "discount_by_additional_sale",
        field: "discount_by_additional_sale",
        label: "Deuda por venta adicional",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "total_discount",
        field: "total_discount",
        label: "Deuda total",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "amount_by_seller",
        field: "amount_by_seller",
        label: "Deuda pagada",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "current_debt",
        field: "current_debt",
        label: "Deuda restante",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "to_pay",
        field: "to_pay",
        label: "A cobrar",
        align: "center",
        style: "width: 120px",
        headerStyle: "width: 120px",
    },
    {
        name: "actions",
        field: "actions",
        label: "",
        align: "center",
        style: "width: 40px",
        headerStyle: "width: 40px",
    },
];

const showDialog = ref(false);
const maxToPay = ref(0);

const showDetailModal = ref(false);
const detailPayment = ref(null);

watch(
    () => formData.value.discount,
    () => {
        let max = 0;
        formData.value.sales.forEach((s) => {
            max += s.discount;
        });
        maxToPay.value = max;
    }
);

const showDetails = async () => {
    if (isValidForm()) {
        try {
            showLoading("showTextDef");
            const response = await showDetailsFromDiscountType(formData.value);
            detailPayment.value = response;
            showDetailModal.value = true;
            hideLoading();
        } catch (error) {
            Swal.fire(
                "¡Error!",
                "Hubo un error al tratar de obtener la vista previa del pago",
                "error"
            );
            hideLoading();
        }
    } else {
        setMsgError("Rectifique los errores");
    }
};

const setMsgError = (error) => {
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        },
    });
    Toast.fire({
        icon: "error",
        text: error,
    });
};

const onShow = () => {
    createReceiptNumber();
    let sales = props.chargerList;
    formData.value.seller_id = props.seller_id;
    formData.value.sales = sales;
    onUpdateToPaid();
};

const createReceiptNumber = () => {
    let date = moment.now();
    formData.value.invoice_number = `${moment(date).format(
        "YYYY-MM-DD"
    )}-${date}`;
};

const isValidForm = () => {
    errors.value = [];
    const fields = ["date", "discount", "invoice_number"];
    for (let i = 0; i < fields.length; i++) {
        if (formData.value[fields[i]] === null) {
            errors.value.push(fields[i]);
        }
    }
    return errors.value.length === 0;
};

const deleteRow = (id) => {
    let sales = formData.value.sales;
    formData.value.sales = sales.filter((s) => s.id !== id);
    onUpdateToPaid();
};

const onUpdateToPaid = () => {
    let discount = 0;
    formData.value.sales.forEach((s) => {
        if (s.to_pay > s.discount) {
            s.to_pay = s.discount;
        }
        discount += s.to_pay;
    });
    formData.value.discount = discount;
};

const charger = () => {
    if (isValidForm()) {
        Swal.fire({
            title: "¡Confirmación!",
            text: "Seguro que deseas cobrar esta(s) deuda(s)?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Si",
            cancelButtonText: "No",
        }).then((result) => {
            if (result.isConfirmed) {
                store();
            }
        });
    } else {
        setMsgError("Rectifique los errores");
    }
};

const store = async () => {
    try {
        showLoading("showTextDef");
        const response = await collectDebt(formData.value);
        if (response !== null && response.success) {
            Swal.fire({
                title: "¡Creado!",
                text: "Se ha cobrado la deuda correctamente, desea generar su comprobante?",
                icon: "success",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Si",
                cancelButtonText: "No",
            }).then((result) => {
                emits("created");
                showDialog.value = false;
                showDetailModal.value = false;
                if (result.isConfirmed) {
                    window.open(
                        `/vendedores/payments-sellers/discount-receipt/${response.discount}`,
                        "_blank"
                    );
                }
            });
        } else {
            Swal.fire(
                "¡Error!",
                "Hubo un error al tratar de cobrar la deuda",
                "error"
            );
        }
        hideLoading();
    } catch (error) {
        Swal.fire(
            "¡Error!",
            "Hubo un error al tratar de cobrar la deuda",
            "error"
        );
        hideLoading();
    }
};
</script>
<style>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
.swal2-container {
    z-index: 9999 !important;
}
</style>
