<template>
    <ModalCentrado
        id="modaleditUpdatePayment"
        @submit="editDatePayment"
        @close-modal="cleanForm"
        modalTitle="Editar Fecha de Pago"
        labelledby="labelledby"
    >
        <div class="row">
            <div class="col-sm-12">
                <div :class="`col-sm-12 mb-2`">
                    <label :class="`col-sm-12`">
                        <span>Fecha actual: {{ actual }}</span>
                    </label>
                </div>

                <div
                    :class="`col-sm-12 row mb-2 ${
                        errorEditId && 'has-danger'
                    } `"
                >
                    <label
                        for="new_court_date"
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                        Fecha de Pago nueva:
                    </label>
                    <div class="col-sm-12 col-md-9">
                        <VueDatePicker
                            v-model="date"
                            position="left"
                            :teleport="true"
                            format="yyyy-MM-dd HH:mm:ss"
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
import VueDatePicker from "@vuepic/vue-datepicker";

export default {
    name: "UpdateDatePayment",
    props: {
        iDClient: String,
        actual: String,
    },
    components: { ModalCentrado, VueDatePicker },
    emits: ["updateInformation"],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref("");
        const date = ref(new Date());

        onMounted(async () => {
            if (props.actual) {
                date.value = props.actual;
            }
        });

        const editDatePayment = async () => {
            let url = `/cliente/edit_date_payment`;
            let data = {
                id_client: props.iDClient,
                new_date: date.value,
            };
            try {
                showLoading("showTextDef");
                const response = await axios.post(url, data);
                errorEditId.value = false; // Maneja la salida del comando
                toastr.success(`Fecha Cambiada correctamente`, props.module);
                emit("updateInformation");
                cleanForm();
                $(`#modaleditUpdatePayment`).modal("hide");
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
            date.value = null;
        };

        return {
            editDatePayment,
            date,
            errorEditId,
            errorText,
            cleanForm,
        };
    },
};
</script>

<style scoped></style>
