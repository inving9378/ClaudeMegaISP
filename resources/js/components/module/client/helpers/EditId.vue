<template>
    <ModalCentrado
        id="modaleditIdClient"
        @submit="editId"
        @close-modal="cleanForm"
        modalTitle="Editar Id"
        labelledby="labelledby"
    >
        <div class="row">
            <div class="col-sm-12">
                <div :class="`col-sm-12 mb-2`" style="text-align: center">
                    <label
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                        <span>Id Actual: {{ iDClient }}</span>
                    </label>
                </div>

                <div
                    :class="`col-sm-12 row mb-2 ${
                        errorEditId && 'has-danger'
                    } `"
                >
                    <label
                        for="id_new_client"
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                        Id Nuevo:
                    </label>
                    <div class="col-sm-12 col-md-9">
                        <input
                            type="text"
                            id="id_new_client"
                            placeholder="Nuevo Id"
                            :class="{
                                'form-control': true,
                            }"
                            v-model="idNew"
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
import ModalCentrado from "../../../../shared/ModalCentrado.vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";

export default {
    name: "EditId",
    props: {
        iDClient: String,
        module: String,
    },
    components: { ModalCentrado },
    emits: ["resetTable"],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref("");
        const idNew = ref(null);
        onMounted(async () => {
            idNew.value = null;
        });

        const editId = async () => {
            let url = `/${props.module}/edit_id`;
            let data = {
                id_actual: props.iDClient,
                id_new: idNew.value,
            };
            try {
                showLoading("showTextDef");
                const response = await axios.post(url, data);
                $(`#modaleditIdClient`).modal("hide");
                errorEditId.value = false; // Maneja la salida del comando
                toastr.success(`Id Cambiado correctamente`, props.module);
                emit("resetTable");
                cleanForm();
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
            idNew.value = null;
        };

        return {
            editId,
            idNew,
            errorEditId,
            errorText,
            cleanForm,
        };
    },
};
</script>

<style scoped></style>
