<template>
    <ModalCentrado
        id="modalDeleteClient"
        @submit="deleteClient"
        @close-modal="cleanForm"
        modalTitle="Eliminar Cliente"
        labelledby="labelledby"
    >
        <div class="row">
            <div v-if="errorText">
                <p class="text-danger">
                    {{ errorText }}
                </p>
            </div>
            <Input-Text
                :property="{
                    class_col: 'full',
                    field: 'id_client',
                    class_label:
                        'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                    class_field: 'col-sm-12 col-md-9',
                    placeholder: 'Introduzca Id',
                    disabled: false,
                }"
                :errors="errors"
                @update-field="updateThisField"
            >
            </Input-Text>

            <div>
                <p class="text-danger">
                    Tenga en Cuenta que al eliminar el cliente no se podran
                    recuperar los datos del mismo.
                </p>
            </div>
        </div>
    </ModalCentrado>
</template>

<script>
import toastr from 'toastr';
import { onMounted, reactive, ref, watch } from "vue";
import ModalCentrado from "../../../../../shared/ModalCentrado.vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import InputText from "../../../../../shared/InputText.vue";
import Errors from "../../../../../helpers/Errors";

export default {
    name: "RemoveClient",
    props: {},
    components: { ModalCentrado, InputText },
    emits: [""],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref(null);
        const idClient = ref(null);

        const errors = new Errors();
        onMounted(async () => {
            idClient.value = null;
        });

        const deleteClient = async () => {
            await axios.post('/cliente/force_delete', {id_client: idClient.value}).then((response) => {
                if (response.data.success) {
                    $(`#modalDeleteClient`).modal("hide");
                    errorEditId.value = false; // Maneja la salida del comando
                    toastr.success(`Cliente eliminado correctamente`, "Client", 5000);
                    cleanForm();
                } else {
                    errorText.value = response.data.error;
                }
            }).catch((response) => {
                errorText.value =
                    "Ocurrió un error al realizar la solicitud.";
            });
            hideLoading();
        };

        const updateThisField = (value) => {
            idClient.value = value.value.value;
            errorText.value = null;
        };

        const cleanForm = () => {
            idClient.value = null;
        };

        return {
            deleteClient,
            idClient,
            errorEditId,
            errorText,
            cleanForm,
            errors,
            updateThisField,
        };
    },
};
</script>

<style scoped></style>
