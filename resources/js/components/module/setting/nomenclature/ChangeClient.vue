<template>
    <ModalCentrado
        id="modaleChangeClientNomenclature"
        @submit="editId"
        @close-modal="cleanForm"
        modalTitle="Cambiar Cliente"
        labelledby="labelledby"
    >
        <div class="row">
            <div class="col-sm-12">
                <div
                    :class="`col-sm-12 row mb-2 ${
                        errorEditId && 'has-danger'
                    } `"
                >
                    <label
                        for="id_new_client"
                        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                    >
                        Cliente Nuevo:
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
    name: "ChangeClient",
    props: {
        action: String,
    },
    components: { ModalCentrado },
    emits: [],
    setup(props, { emit }) {
        const errorEditId = ref(false);
        const errorText = ref("");
        const idNew = ref(null);
        const idItem = ref(null);
        onMounted(async () => {
            idNew.value = null;
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        const initComponent = async (action) => {
            let id = getIdByAction(action);
            idItem.value = id;
        };

        const getIdByAction = (action) => {
            return _.trimStart(action, '/configuracion/nomenclature/update/')
        }

        const editId = async () => {
            let url = `/configuracion/nomenclature/change-client`;
            let data = {
                id_client_new: idNew.value,
                id: idItem.value,
            };
            try {
                showLoading("showTextDef");
                const response = await axios.post(url, data);
                $(`#modaleChangeClientNomenclature`).modal("hide");
                errorEditId.value = false; // Maneja la salida del comando
                toastr.success(`Cliente Cambiado correctamente`, "Cliente");
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
