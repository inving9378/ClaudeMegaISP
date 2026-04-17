<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
            <template v-for="(val, key) in processedFieldsJson" :key="key">
                <!-- Campo normal (sin filtro o campo diferente a documentation_menu_id) -->
                <ComponentFormDefault
                    v-if="val.include && !val.is_hidden"
                    :id="id"
                    :json="val"
                    :errors="dataForm.data.errors"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
                <!-- Campo documentation_menu_id deshabilitado cuando hay filtro -->
                <div 
                    v-else-if="val.include && val.is_hidden && filterMenuId" 
                    class="col-12 mb-3"
                >
                    <label class="form-label col-12">{{ val.label }}</label>
                    <input 
                        type="text" 
                        class="form-control bg-light" 
                        :value="filterMenuTitle" 
                        disabled 
                        readonly
                    />
                </div>
            </template>
        </div>
        <div class="modal-footer">
            <a
                class="btn btn-secondary mr-3"
                href="javascript:void(0)"
                @click="closeModal"
            >
                Cerrar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
                :disabled="dataForm.data.errors.any()"
            >
                Guardar
            </button>
        </div>
    </form>
</template>

<script>
import { onMounted, ref, watch, computed } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../../hook/crudHook";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import Swal from "sweetalert2";

export default {
    name: "DocumentationSubmenuCrud",
    props: {
        action: String,
        filterMenuId: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const id = ref(null);
        const filterMenuTitle = ref("");
        
        let submitButtonAction =
            props.action == "/administracion/documentation/documentation_submenu/add"
                ? "Crear Submenú de Documentación"
                : "Salvar Submenú de Documentación";

        // CORREGIDO: Procesar objeto en lugar de array
        const processedFieldsJson = computed(() => {
            if (!fieldsJson.value || typeof fieldsJson.value !== 'object') {
                return {};
            }
            
            const processed = {};
            
            for (const [key, field] of Object.entries(fieldsJson.value)) {
                // Si es el campo de menú y hay filtro activo en modo creación
                if (field.field === 'documentation_menu_id' && props.filterMenuId && isCreateMode()) {
                    processed[key] = {
                        ...field,
                        is_hidden: true, // Marcar para ocultar el select original
                        include: true,   // Mantener incluido para procesamiento
                    };
                } else {
                    processed[key] = field;
                }
            }
            
            return processed;
        });

        onMounted(() => {
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        const initComponent = async (action) => {
            let menuId = getIdByAction(action);
            if (action == "/administracion/documentation/documentation_submenu/add") {
                id.value = null;
                await getfieldsJson("DocumentationSubmenu");
                
                // Si hay filtro, establecer valor por defecto después de cargar campos
                if (props.filterMenuId) {
                    dataForm.data.documentation_menu_id = props.filterMenuId;
                    await fetchMenuTitle(props.filterMenuId);
                }
            } else {
                id.value = menuId;
                await getfieldsEdited("DocumentationSubmenu", menuId);
                
                // En modo edición, obtener el título del menú asociado al registro
                if (dataForm.data.documentation_menu_id) {
                    await fetchMenuTitle(dataForm.data.documentation_menu_id);
                }
            }
        };

        const isCreateMode = () => {
            return props.action && props.action.includes('/add');
        };

        const fetchMenuTitle = async (menuId) => {
            try {
                const response = await axios.get(`/administracion/documentation/documentation_menu/get-title/${menuId}`);
                if (response.data && response.data.title) {
                    filterMenuTitle.value = response.data.title;
                } else {
                    filterMenuTitle.value = `Menú #${menuId}`;
                }
            } catch (error) {
                console.warn("No se pudo obtener el título del menú:", error);
                filterMenuTitle.value = `Menú #${menuId}`;
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(
                action,
                "/administracion/documentation/documentation_submenu/update/"
            );
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = () => {
            // Asegurar que documentation_menu_id está en los datos si hay filtro en modo creación
            if (props.filterMenuId && isCreateMode()) {
                dataForm.data.documentation_menu_id = props.filterMenuId;
            }
            
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        closeModal();
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
                });
        };

        return {
            fieldsJson,
            processedFieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal,
            id,
            filterMenuTitle,
        };
    },
};
</script>

<style scoped></style>