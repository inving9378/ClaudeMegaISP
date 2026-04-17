<template>
    <div
        class="modal fade"
        id="releaseModal"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Version</h6>
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
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
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
                                >Cerrar</a
                            >

                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any()"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, nextTick, onMounted } from "vue";
import ComponentFormDefault from "../../ComponentFormDefault.vue";
import { requestFieldsByModule } from "../../../helpers/Request";
import Form from "../../../helpers/Form";
import { showLoading, hideLoading } from "../../../helpers/loading";
import Swal from "sweetalert2";

export default {
    name: "ReleasesCrud",
    emits: ["save"],
    components: { ComponentFormDefault },
    props: {
        id: {
            type: [String, Number],
            default: null,
        },
    },
    setup(props, { emit }) {
        const fieldsJson = ref({});
        const dataForm = reactive({ data: new Form({}) });
        const loading = ref(false);

        const requestEditedFieldsById = async (module, id) => {
            let fields = {};
            await axios
                .post(`/fields-by-module/${id}`, { module })
                .then((response) => {
                    fields = response.data;
                });
            return fields;
        };
        const load = async (id = null) => {
            try {
                showLoading("Cargando versión...");
                if (id) {
                    // modo edición
                    fieldsJson.value = await requestEditedFieldsById(
                        "Release",
                        id
                    );
                    fieldsJson.value.version.include = false;
                    dataForm.data = new Form(fieldsJson.value);

                } else {
                    fieldsJson.value = await requestFieldsByModule("Release");
                    dataForm.data = new Form(fieldsJson.value);
                }
            } catch (e) {
                Swal.fire(
                    "Error",
                    "No se pudo cargar la versión seleccionada",
                    "error"
                );
            } finally {
                hideLoading();
            }
        };

        const closeModal = () => {
            dataForm.data.reset();
            loading.value = false;
            nextTick(() => {
                $("#releaseModal").modal("hide");
            });
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            showLoading("Guardando...");
            loading.value = true;
            const url = props.id
                ? `/releases/update/${props.id}`
                : `/releases/store`;
            dataForm.data
                .submit("post", url, "reset")
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        closeModal();
                        emit("save", response.model);
                    } else {
                        Swal.fire("Error", message, "error");
                        loading.value = false;
                        hideLoading();
                    }
                })
                .catch((error) => {
                    console.log(error);
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                    loading.value = false;
                    hideLoading();
                })
                .finally(() => {
                    hideLoading();
                    loading.value = false;
                });
        };

        return {
            load,
            fieldsJson,
            dataForm,
            closeModal,
            updateThisField,
            clearError,
            onSubmit,
            loading,
        };
    },
};
</script>

<style scoped>
.ck-editor__editable_inline {
    min-height: 150px;
}
</style>
