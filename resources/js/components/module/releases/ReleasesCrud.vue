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

                        <!-- Panel de resumen IA — solo en creación -->
                        <div v-if="!id" class="px-1 pb-2">
                            <hr class="my-2" />
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label mb-0 fw-semibold text-muted small">
                                    <i class="bi bi-stars me-1"></i> Mejoras generadas por IA
                                    <span class="fw-normal">(editable · se publicará en "Ver más")</span>
                                </label>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="generateChangelog"
                                    :disabled="aiLoading"
                                >
                                    <span v-if="aiLoading" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-arrow-repeat me-1"></i>
                                    {{ aiLoading ? 'Generando...' : 'Generar automáticamente' }}
                                </button>
                            </div>
                            <textarea
                                v-model="aiDescription"
                                class="form-control form-control-sm"
                                rows="5"
                                placeholder="Haz clic en «Generar automáticamente» para que la IA analice los commits desde la versión anterior…"
                                style="font-size:0.85rem;resize:vertical;"
                            ></textarea>
                        </div>

                        <div class="form-group text-center mt-3">
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
    emits: ["save", "deploy-started"],
    components: { ComponentFormDefault },
    props: {
        id: {
            type: [String, Number],
            default: null,
        },
    },
    setup(props, { emit }) {
        const fieldsJson    = ref({});
        const dataForm      = reactive({ data: new Form({}) });
        const loading       = ref(false);
        const aiDescription = ref('');
        const aiLoading     = ref(false);

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
            loading.value   = false;
            aiDescription.value = '';
            nextTick(() => {
                $("#releaseModal").modal("hide");
            });
        };

        const generateChangelog = async () => {
            aiLoading.value = true;
            try {
                const version = dataForm.data['version'] || '';
                const { data } = await axios.post('/releases/generate-changelog', { version });
                if (data.success) {
                    aiDescription.value = data.description;
                } else {
                    Swal.fire('Error', data.message || 'No se pudo generar el resumen.', 'error');
                }
            } catch (e) {
                Swal.fire('Error', e.response?.data?.message || 'No se pudo generar el resumen.', 'error');
            } finally {
                aiLoading.value = false;
            }
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

            // Adjuntar el resumen IA si fue generado (solo en creación)
            if (!props.id && aiDescription.value.trim()) {
                dataForm.data['ai_description'] = aiDescription.value.trim();
            }

            dataForm.data
                .submit("post", url, "reset")
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        closeModal();
                        emit("save", response.model);
                        // Si la respuesta trae deployment_id es una release nueva → abrir modal deploy
                        if (response.deployment_id) {
                            emit("deploy-started", {
                                deploymentId: response.deployment_id,
                                version: response.model?.version,
                            });
                        } else {
                            Swal.fire("Éxito", message, "success");
                        }
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
            aiDescription,
            aiLoading,
            generateChangelog,
        };
    },
};
</script>

<style scoped>
.ck-editor__editable_inline {
    min-height: 150px;
}
</style>
