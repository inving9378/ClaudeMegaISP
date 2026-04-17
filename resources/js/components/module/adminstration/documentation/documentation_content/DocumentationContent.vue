<template>
    <div class="container my-5">
        <h2 class="fw-bold mb-3">
            {{ submenu.title }}
        </h2>
        <p class="text-muted mb-4">{{ submenu.description }}</p>

        <hr />

        <!-- Botón agregar y selector de orden -->
        <div
            class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"
            v-if="hasPermission.data.canView('documentation_add_documentation')"
        >
            <h5 class="fw-bold">Contenidos de documentación</h5>

            <div class="d-flex align-items-center gap-2">
                <!-- Selector de orden -->
                <select
                    v-model="orderDirection"
                    @change="changeOrder"
                    class="form-select form-select-sm"
                    style="width: auto;"
                    title="Ordenar contenidos"
                >
                    <option value="asc">↑ Más antiguo primero</option>
                    <option value="desc">↓ Más reciente primero</option>
                </select>
                <!-- Fin selector de orden -->

                <button class="btn btn-primary btn-sm" @click="addContent">
                    + Agregar contenido
                </button>
            </div>
        </div>

        <!-- Formulario de NUEVO contenido -->
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

        <!-- Lista de contenidos -->
        <div v-if="contents.length">
            <div
                v-for="content in contents"
                :key="content.id"
                class="card border-0 border-start border-4 border-info mb-3 shadow-sm"
                :ref="(el) => setContentRef(content.id, el)"
            >
                <div class="card-body">
                    <div class="card-text" v-html="content.content"></div>

                    <div class="text-end mt-3">
                        <button
                            class="btn btn-sm btn-outline-secondary me-2"
                            @click="editContent(content)"
                            v-if="hasPermission.data.canView('documentation_edit_documentation')"
                        >
                            Editar
                        </button>
                        <button
                            class="btn btn-sm btn-outline-danger"
                            @click="remove(content)"
                            v-if="hasPermission.data.canView('documentation_delete_documentation')"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- Formulario de EDICIÓN inline -->
                <div
                    v-if="showForm && currentId === content.id"
                    class="card-body border-top mt-3 bg-light"
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
                                <span v-if="!loading">Actualizar</span>
                                <span v-else>
                                    <i class="spinner-border spinner-border-sm"></i>
                                    Guardando...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Mensaje cuando no hay contenidos -->
        <div v-else class="text-muted fst-italic mt-4">
            No hay contenidos registrados para este submenú.
        </div>
    </div>
</template>

<script>
console.log('>>> DocumentationContent.vue cargado');
console.log('>>> ClassicEditor disponible al inicio:', typeof window !== 'undefined' ? typeof window.ClassicEditor : 'N/A');

import { ref, reactive, nextTick, onMounted } from "vue";
import Swal from "sweetalert2";
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Form from "../../../../../helpers/Form";
import {
    allViewHasPermission,
    requestEditedFieldsById,
    requestFieldsByModule,
} from "../../../../../helpers/Request";
import Permission from "../../../../../helpers/Permission";

export default {
    name: "DocumentationContent",
    components: { ComponentFormDefault },
    props: {
        submenu: { type: String, required: true },
        contents: { type: String, required: true },
    },
    setup(props) {
        const submenu = JSON.parse(props.submenu);
        const contents = ref(JSON.parse(props.contents));

        const formKey = ref(0);
        const loading = ref(false);
        const showForm = ref(false);
        const currentId = ref(null);
        const fieldsJson = ref({});
        const dataForm = reactive({ data: new Form({}) });
        const contentRefs = ref({});

        // NUEVO: Variable para controlar la dirección del orden
        const orderDirection = ref('asc'); // 'asc' por defecto (más antiguo primero)

        const hasPermission = reactive({
            data: new Permission({}),
        });

        const setContentRef = (id, el) => {
            if (el) contentRefs.value[id] = el;
        };

        const clearError = ({ field }) => dataForm.data.errors.clear(field);

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const addContent = async () => {
            showForm.value = true;
            currentId.value = null;
            await getFieldsJson();
            dataForm.data.reset();
            dataForm.data["documentation_submenu_id"] = submenu.id;
            await nextTick();
            window.scrollTo({ top: 0, behavior: "smooth" });
        };

        const editContent = async (content) => {
            showForm.value = true;
            currentId.value = content.id;
            await getFieldsEdited(content.id);
            dataForm.data["documentation_submenu_id"] = submenu.id;
            await nextTick();

            const el = contentRefs.value[content.id];
            if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
        };

        const cancelForm = () => {
            showForm.value = false;
            dataForm.data.reset();
            currentId.value = null;
        };

        const getFieldsJson = async () => {
            fieldsJson.value = await requestFieldsByModule("DocumentationContent");
            Object.assign(dataForm.data, new Form(fieldsJson.value));
            formKey.value++;
        };

        const getFieldsEdited = async (id) => {
            fieldsJson.value = await requestEditedFieldsById(
                "DocumentationContent",
                id
            );
            Object.assign(dataForm.data, new Form(fieldsJson.value));
            formKey.value++;
        };

        // onMounted(async () => {
        //     hasPermission.data = new Permission(await allViewHasPermission());
        // });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            // Cargar contenidos ordenados por defecto (ascendente)
            await reloadContents();
        });

        // NUEVO: Método para cambiar el orden
        const changeOrder = async () => {
            await reloadContents();
        };

        const onSubmit = () => {
            showLoading("Guardando...");
            loading.value = true;

            const url = currentId.value
                ? `/administracion/documentation/documentation_content/update/${currentId.value}`
                : `/administracion/documentation/documentation_content/add`;

            dataForm.data
                .submit("post", url, "reset")
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        reloadContents();
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

        // const reloadContents = async () => {
        //     const res = await axios.get(
        //         `/administracion/documentation/documentation_content/${submenu.id}/contents`
        //     );
        //     // Version original
        //      contents.value = res.data;

        //     // Ordenar por created_at ascendente (más viejo primero)
        //     contents.value = res.data.sort((a, b) => {
        //         return new Date(a.created_at) - new Date(b.created_at);
        //     });

        //     // Alternativa: ordenar por ID ascendente
        //     // contents.value = res.data.sort((a, b) => a.id - b.id);
        // };

        // MODIFICADO: Acepta parámetro de orden
        const reloadContents = async (order = null) => {
            const currentOrder = order || orderDirection.value;

            try {
                const res = await axios.get(
                    `/administracion/documentation/documentation_content/${submenu.id}/contents`,
                    {
                        params: {
                            order: currentOrder // 'asc' o 'desc'
                        }
                    }
                );
                contents.value = res.data;
            } catch (error) {
                console.error('Error al cargar contenidos:', error);
                Swal.fire(
                    "Error",
                    "No se pudieron cargar los contenidos.",
                    "error"
                );
            }
        };

        const remove = async (content) => {
            const confirm = await Swal.fire({
                title: "¿Eliminar contenido?",
                text: "Esta acción no se puede deshacer",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#dc3545",
            });

            if (!confirm.isConfirmed) return;

            try {
                await axios.delete(
                    `/administracion/documentation/documentation_content/delete/${content.id}`
                );
                Swal.fire("Eliminado", "El contenido fue eliminado.", "success");
                await reloadContents();
            } catch (error) {
                Swal.fire(
                    "Error",
                    "No se pudo eliminar el contenido.",
                    "error"
                );
            }
        };

        return {
            submenu,
            contents,
            showForm,
            currentId,
            loading,
            fieldsJson,
            dataForm,
            formKey,
            orderDirection, // NUEVO: Exponer al template
            addContent,
            editContent,
            cancelForm,
            onSubmit,
            remove,
            updateThisField,
            clearError,
            setContentRef,
            hasPermission,
            changeOrder, // NUEVO: Exponer al template
        };
    },
};
</script>

<style scoped>
.card-text {
    max-width: 100%;
    overflow-x: hidden;
    word-wrap: break-word;
}

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

.card-text figure.image figcaption {
    font-size: 0.9rem;
    color: var(--bs-secondary-color, #6b7280);
    margin-top: 0.5rem;
}

.card-text ul,
.card-text ol {
    padding-left: 1.5rem;
    overflow-wrap: break-word;
}

.card-text p {
    margin-bottom: 1rem;
    line-height: 1.6;
}

/* NUEVO: Estilos para el selector de orden */
.form-select-sm {
    min-width: 180px;
}
</style>
