<template>
    <ModalCentrado
        id="modaleditPaymentInstalationCost"
        @submit="paymentInstalationCost"
        @close-modal="cleanForm"
        modalTitle="Pagar Costo de Instalación"
        labelledby="labelledby"
    >
        <div class="row">
            <div class="col-sm-12">
                <div :class="`col-sm-12 mb-2`">
                    <label :class="`col-sm-12`">
                        <span>Costo Total: {{ costInstalation }}</span>
                    </label>
                </div>
                <div
                    :class="`col-sm-12 row mb-2 ${
                        errorEditId && 'has-danger'
                    } `"
                >
                    <label
                        for="amount_to_paid"
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                    Cantidad:
                    </label>
                    <div class="col-sm-12 col-md-9">
                        <input
                            type="number"
                            id="amount_to_paid"
                            placeholder=""
                            :class="{
                                'form-control': true,
                            }"
                            v-model="amount"
                            min="0"
                        />
                        <div
                            v-if="errorEditId"
                            class="pristine-error text-help"
                        >
                            {{ errorText }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ModalCentrado>
</template>

<script>
import toastr from "toastr";
import { onMounted, reactive, ref, watch } from "vue";
import ModalCentrado from "../../../../../shared/ModalCentrado.vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";

export default {
    name: "InstalationPayment",
    props: {
        iDClient: String,
        module: String,
        costInstalation: Number | String
    },
    components: { ModalCentrado },
    emits: ["updateInformation"],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref("");
        const amount = ref(0);

        const paymentInstalationCost = async () => {
            if (amount.value <= 0 || amount.value < props.costInstalation) {
                errorEditId.value = true;
                errorText.value = "Debe ingresar una cantidad válida o mayor al costo total.";
                return;
            }

            let url = `/cliente/payment_instalation_cost/services`;
            let data = {
                id_client: props.iDClient,
                amount_to_paid: amount.value
            };
            try {
                showLoading("showTextDef");
                const response = await axios.post(url, data);
                errorEditId.value = false; // Maneja la salida del comando
                toastr.success(`Costo de Instalación Pagado Correctamente`, props.module);
                emit("updateInformation");
                cleanForm();
                $(`#modaleditPaymentInstalationCost`).modal("hide");
                hideLoading();
            } catch (error) {
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.message
                ) {
                    errorText.value = error.response.data.message;
                } else {
                    errorText.value =
                        "Ocurrió un error al realizar la solicitud.";
                }
                errorEditId.value = true;
                hideLoading();
            }
        };

        const cleanForm = () => {

        };

        return {
            paymentInstalationCost,
            errorEditId,
            errorText,
            cleanForm,
            amount
        };
    },
};
</script>

<style scoped></style>
