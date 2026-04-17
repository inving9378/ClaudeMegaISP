<template>
    <div class="container my-5">
        <h2 class="fw-bold mb-3">
            {{ release.version }} – {{ formatDate(release.release_date) }}
        </h2>
        <p class="text-muted mb-4">{{ release.summary }}</p>

        <hr />

        <div
            class="d-flex justify-content-between align-items-center mb-3"
            v-if="hasPermission.data.canView('release_add_description')"
        >
            <h5 class="fw-bold">Mejoras de esta versión</h5>
            <button class="btn btn-primary btn-sm" @click="addDescription">
                + Agregar mejora
            </button>
        </div>

        <!-- 🔹 FORMULARIO DE NUEVA MEJORA (solo cuando no se está editando) -->
        <div
            v-if="showForm && !currentId"
            class="card p-3 mb-4 shadow-sm bg-light"
        >
            <form
                :key="formKey"
                method="POST"
                @submit.prevent="onSubmit"
                @change="dataForm.data.errors.clear($event.target.name)"
                @keydown="dataForm.data.errors.clear($event.target.name)"
            >
                <template v-for="val in fieldsJson" :key="val.field">
                    <ComponentFormDefault
                        v-if="val.include"
                        :json="val"
                        :errors="dataForm.data.errors"
                        v-model="dataForm.data[val.field]"
                        @update-field="updateThisField"
                        @clear-error="clearError"
                    />
                </template>

                <div class="form-group text-center mt-3">
                    <button
                        type="button"
                        class="btn btn-secondary me-3"
                        @click="cancelForm"
                    >
                        Cancelar
                    </button>
                    <button
                        class="btn btn-primary"
                        type="submit"
                        :disabled="dataForm.data.errors.any() || loading"
                    >
                        <span v-if="!loading">Guardar</span>
                        <span v-else>
                            <i class="spinner-border spinner-border-sm"></i>
                            Guardando...
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 🔹 LISTA DE MEJORAS -->
        <div v-if="descriptions.length">
            <div
                v-for="desc in descriptions"
                :key="desc.id"
                class="card border-0 border-start border-4 border-primary mb-3 shadow-sm"
                :ref="(el) => setDescRef(desc.id, el)"
            >
                <div class="card-body">
                    <h6 class="card-title fw-bold">
                        {{ desc.title || "Sin título" }}
                    </h6>
                    <div class="card-text" v-html="desc.description"></div>

                    <div class="text-end mt-3">
                        <button
                            class="btn btn-sm btn-outline-secondary me-2"
                            @click="editDescription(desc)"
                            v-if="
                                hasPermission.data.canView(
                                    'release_edit_description'
                                )
                            "
                        >
                            Editar
                        </button>
                        <button
                            class="btn btn-sm btn-outline-danger"
                            @click="remove(desc)"
                            v-if="
                                hasPermission.data.canView(
                                    'release_delete_description'
                                )
                            "
                        >
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- 🔹 FORMULARIO DE EDICIÓN INLINE -->
                <div
                    v-if="showForm && currentId === desc.id"
                    class="card-body border-top mt-3 bg-light"
                >
                    <form
                        :key="formKey"
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <template v-for="val in fieldsJson" :key="val.field">
                            <ComponentFormDefault
                                v-if="val.include"
                                :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="form-group text-center mt-3">
                            <button
                                type="button"
                                class="btn btn-secondary me-3"
                                @click="cancelForm"
                            >
                                Cancelar
                            </button>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="
                                    dataForm.data.errors.any() || loading
                                "
                            >
                                <span v-if="!loading">Guardar</span>
                                <span v-else>
                                    <i
                                        class="spinner-border spinner-border-sm"
                                    ></i>
                                    Guardando...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 🔹 Texto cuando no hay descripciones -->
        <div v-else class="text-muted fst-italic mt-4">
            No hay mejoras registradas para esta versión.
        </div>
    </div>
</template>

<script>
import { ref, reactive, nextTick, onMounted } from "vue";
import Swal from "sweetalert2";
import ComponentFormDefault from "../../ComponentFormDefault.vue";
import { showLoading, hideLoading } from "../../../helpers/loading";
import Form from "../../../helpers/Form";
import {
    allViewHasPermission,
    requestEditedFieldsById,
    requestFieldsByModule,
} from "../../../helpers/Request";
import Permission from "../../../helpers/Permission";

export default {
    name: "ReleasesDescription",
    components: { ComponentFormDefault },
    props: {
        release: { type: String },
        descriptions: { type: String },
    },
    setup(props) {
        const release = JSON.parse(props.release);
        const descriptions = ref(JSON.parse(props.descriptions));

        const formKey = ref(0);
        const loading = ref(false);
        const showForm = ref(false);
        const currentId = ref(null);
        const fieldsJson = ref({});
        const dataForm = reactive({ data: new Form({}) });
        const descRefs = ref({}); // 🔹 para mantener refs por descripción

        const hasPermission = reactive({
            data: new Permission({}),
        });

        const setDescRef = (id, el) => {
            if (el) descRefs.value[id] = el;
        };

        const formatDate = (date) =>
            new Date(date).toLocaleDateString("es-ES", {
                year: "numeric",
                month: "long",
                day: "numeric",
            });

        const clearError = ({ field }) => dataForm.data.errors.clear(field);
        const updateThisField = ({ field, value }) =>
            (dataForm.data[field] = value);

        const addDescription = async () => {
            showForm.value = true;
            currentId.value = null;
            await getFieldsJson();
            dataForm.data.reset();
            dataForm.data["release_id"] = release.id;
            await nextTick();
            window.scrollTo({ top: 0, behavior: "smooth" });
        };

        const editDescription = async (desc) => {
            showForm.value = true;
            currentId.value = desc.id;
            await getFieldsEdited(desc.id);
            dataForm.data["release_id"] = release.id;
            await nextTick();

            // 🔹 Enfocar el formulario de edición inline
            const el = descRefs.value[desc.id];
            if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
        };

        const cancelForm = () => {
            showForm.value = false;
            dataForm.data.reset();
            currentId.value = null;
        };

        const getFieldsJson = async () => {
            fieldsJson.value = await requestFieldsByModule(
                "ReleaseDescription"
            );
            Object.assign(dataForm.data, new Form(fieldsJson.value));
            formKey.value++;
        };

        const getFieldsEdited = async (id) => {
            fieldsJson.value = await requestEditedFieldsById(
                "ReleaseDescription",
                id
            );
            Object.assign(dataForm.data, new Form(fieldsJson.value));
            formKey.value++;
        };

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
        });

        const onSubmit = () => {
            showLoading("Guardando...");
            loading.value = true;
            const url = currentId.value
                ? `/releases/description/update/${currentId.value}`
                : `/releases/description/store`;

            dataForm.data
                .submit("post", url, "reset")
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        reloadDescriptions();
                        showForm.value = false;
                        currentId.value = null;
                    } else {
                        Swal.fire("Error", message, "error");
                    }
                })
                .catch((error) => {
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                })
                .finally(() => {
                    hideLoading();
                    loading.value = false;
                });
        };

        const reloadDescriptions = async () => {
            const res = await axios.get(`/releases/${release.id}/descriptions`);
            descriptions.value = res.data;
        };

        const remove = async (desc) => {
            const confirm = await Swal.fire({
                title: "¿Eliminar mejora?",
                text: desc.title || desc.description,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Eliminar",
            });

            if (!confirm.isConfirmed) return;

            await axios.delete(`/releases/description/delete/${desc.id}`);
            Swal.fire("Eliminado", "La mejora fue eliminada.", "success");
            await reloadDescriptions();
        };

        return {
            release,
            descriptions,
            showForm,
            currentId,
            loading,
            fieldsJson,
            dataForm,
            formKey,
            addDescription,
            editDescription,
            cancelForm,
            onSubmit,
            remove,
            formatDate,
            updateThisField,
            clearError,
            setDescRef,
            hasPermission,
        };
    },
};
</script>

<style>
/* --- Contenedor principal del contenido del CKEditor --- */
.card-text {
    max-width: 100%;
    overflow-x: hidden;
    word-wrap: break-word;
}

/* --- Las figuras del CKEditor (imágenes, captions, etc.) --- */
.card-text figure.image {
    display: block;
    max-width: 100%;
    margin: 1rem auto;
    text-align: center;
}

.card-text figure.image img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    display: inline-block;
}

/* --- Si usás captions debajo de las imágenes --- */
.card-text figure.image figcaption {
    font-size: 0.9rem;
    color: var(--bs-secondary-color, #6b7280);
    margin-top: 0.5rem;
}

/* --- En caso de listas o bloques grandes --- */
.card-text ul,
.card-text ol {
    padding-left: 1.5rem;
    overflow-wrap: break-word;
}

.card-text p {
    margin-bottom: 1rem;
    line-height: 1.6;
}
</style>
