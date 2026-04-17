<template>
    <modal
        :show="props.showModalEdit"
        :size="'lg'"
        @update:show="updateShow"
        :title="'Editar pago'"
    >
        <template #body>
            <form>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="mb-3">
                                <label for="name" class="form-label"
                                    >Métodos de pago</label
                                >
                                <select
                                    class="form-select"
                                    v-model="payment.method_of_payment"
                                >
                                    <option
                                        v-for="methodPayment in methodsPayments"
                                        :key="methodPayment.id"
                                        :value="methodPayment.id"
                                    >
                                        {{ methodPayment.type }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="father_last_name" class="form-label"
                                    >Fecha de pago</label
                                >
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="payment_day"
                                />
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label"
                                    >Número de recibo</label
                                >
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        v-model="payment.payment_number"
                                    />
                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        @click="createReceiptNumber"
                                    >
                                        <i class="fas fa-magic"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- <div class="mb-3">
                                <MultipleSelect
                                    label="Comisiones"

                                />
                            </div> -->
                            <!-- <div class="mb-3">
                                <label for="mother_last_name" class="form-label"
                                    >Ingresa una cantidad</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="totalAmountCommission"
                                />
                            </div> -->
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="mb-3">
                                <label for="code_postal" class="form-label"
                                    >Comentario</label
                                >
                                <textarea
                                    class="form-control"
                                    rows="9"
                                    v-model="payment.comment"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="update">
                Actualizar pago
            </button>
        </template>
    </modal>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from "vue";
import Swal from "sweetalert2";
import {
    getMethodsPayments,
    updatePayment,
    getPaymentById,
} from "./helper/helper";
import Modal from "../../../../shared/ModalSimple.vue";
// import MultipleSelect from "../../../../shared/SelectMultipleOptions.vue";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },

    showModalEdit: {
        type: Boolean,
        required: true,
    },

    payment_id: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(["update:showModalEdit", "updateListPayments"]);

const methodsPayments = ref([]);
const totalAmountCommission = ref(0.0);
const commissions = ref([]);
const payment_day = ref("");
const selectedCommissions = ref([]);

const payment = reactive({
    payment_number: "",
    method_of_payment: null,
    comment: "",
    payment_date: "",
    // seller_id: null
});

onMounted(async () => {
    getMethodsOfPayments();
    getDate();
    // payment.seller_id = props.seller_id;
});

watch(
    () => props.payment_id,
    (newPaymentId, oldPaymentId) => {
        if (newPaymentId !== oldPaymentId) {
            getPayment();
        }
    },
    { immediate: false }
);

const update = async () => {
    try {
        payment.payment_date = payment_day.value;

        const response = await updatePayment(props.payment_id, payment);
        Swal.fire("¡Actualizado!", response.message, "success");
        emit("updateListPayments");
        emit("update:showModalEdit", false);
    } catch (error) {
        Swal.fire("¡Error!", "Hubo un error al actualizar el pago", "error");
    }
};

const getPayment = async () => {
    try {
        const response = await getPaymentById(props.payment_id);
        (payment.payment_number = response.payment_number),
            (payment.payment_date = response.payment_date),
            (payment.method_of_payment = response.method_of_payment),
            (payment.comment = response.comment),
            (payment.seller_id = response.seller_id);
    } catch (error) {
        console.log(error);
    }
};

const getMethodsOfPayments = async () => {
    methodsPayments.value = await getMethodsPayments();
};

const getDate = () => {
    const today = new Date();
    today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    const formattedDate = today.toISOString().split("T")[0];
    payment_day.value = formattedDate;
};

const createReceiptNumber = () => {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    const randomNumber = Math.floor(Math.random() * 900) + 100;

    payment.payment_number = `${year}-${month}-${day}${randomNumber}`;
};

const updateShow = (newValue) => {
    emit("update:showModalEdit", newValue);
};
</script>
