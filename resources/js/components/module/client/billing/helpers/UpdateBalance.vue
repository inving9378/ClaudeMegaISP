<template>
    <ModalCentrado
        id="modaleditUpdateBalance"
        @submit="editDatePayment"
        @close-modal="cleanForm"
        modalTitle="Editar Balance"
        labelledby="labelledby"
    >
        <div class="row">
            <div class="col-sm-12">
                <div :class="`col-sm-12 mb-2`">
                    <label :class="`col-sm-12`">
                        <span>Balance actual: {{ actual }}</span>
                    </label>
                </div>
                <div
                    :class="`col-sm-12 row mb-2 ${
                        errorEditId && 'has-danger'
                    } `"
                >
                    <label
                        for="new_client_balance"
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                    Nuevo Balance:
                    </label>
                    <div class="col-sm-12 col-md-9">
                        <input
                            type="number"
                            id="new_client_balance"
                            placeholder="Nuevo balance"
                            :class="{
                                'form-control': true,
                            }"
                            v-model="balance"
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
    name: "UpdateBalance",
    props: {
        iDClient: String,
        actual: Number,
        module: String,
    },
    components: { ModalCentrado },
    emits: ["updateInformation"],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref("");
        const balance = ref();

        onMounted(async () => {
            if (props.actual) {
                balance.value = props.actual;
            }
        });

        const editDatePayment = async () => {
            let url = `/cliente/edit_balance`;
            let data = {
                id_client: props.iDClient,
                new_balance: balance.value,
            };
            try {
                showLoading("showTextDef");
                const response = await axios.post(url, data);
                errorEditId.value = false; // Maneja la salida del comando
                toastr.success(`Balance Cambiado Correctamente`, props.module);
                emit("updateInformation");
                cleanForm();
                $(`#modaleditUpdateBalance`).modal("hide");
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
            balance.value = null;
        };

        return {
            editDatePayment,
            balance,
            errorEditId,
            errorText,
            cleanForm,
        };
    },
};
</script>

<style scoped></style>
