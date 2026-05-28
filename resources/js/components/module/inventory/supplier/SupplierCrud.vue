<template>
    <form method="POST" @submit.prevent="onSubmit">
        <div class="modal-body m-0 row">
            <template v-for="val in fieldsList" :key="val">
                <ComponentFormDefault
                    v-if="val.include"
                    :json="val"
                    :errors="dataForm.data.errors"
                    :id="idModel"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </template>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="$emit('close-modal')">
                Cerrar
            </button>
            <button 
                type="submit" 
                class="btn btn-primary" 
                :disabled="loadingToogle || dataForm.data.errors.any()"
            >
                <span v-if="loadingToogle" class="spinner-border spinner-border-sm me-1"></span>
                Guardar
            </button>
        </div>
    </form>
</template>

<script>
import { onMounted, watch, computed } from "vue";
import Swal from "sweetalert2";
import ComponentFormDefault from "../../../ComponentFormDefault.vue";
import {
    fieldsJson,
    dataForm,
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
} from "../../../../hook/crudHook.js";
import { loadingToogle, enableLoading, disabledLoading } from "../../../../hook/loadingHook";

export default {
    name: "SupplierCrud",
    props: {
        action: String,
        module: {
            type: String,
            default: "Supplier"
        },
        id: {
            type: String,
            default: null
        }
    },
    emits: ["close-modal", "supplier-saved"],
    components: { ComponentFormDefault },
    setup(props, { emit }) {
        
        const isEdit = computed(() => {
            return props.id !== null && props.id !== undefined && props.id !== "";
        });

        const idModel = computed(() => isEdit.value ? String(props.id) : null);

        const loadFields = async () => {
            disabledLoading();
            
            if (isEdit.value) {
                await getfieldsEdited(props.module, props.id);
            } else {

                await getfieldsJson(props.module);
            }
        };

        onMounted(loadFields);
        
        const fieldsList = computed(() => {
            const fj = fieldsJson.value;
            if (!fj) return [];
            
            if (Array.isArray(fj)) return fj;
            
            return Object.values(fj);
        });

        watch(() => props.id, (newId, oldId) => {
            if (newId !== oldId) {
                loadFields();
            }
        });


        const onSubmit = async () => {
            enableLoading();
            
            try {
                const url = isEdit.value
                    ? `/inventory/supplier/update/${props.id}`
                    : "/inventory/supplier/add";
                
                const method = "post"; 

                await dataForm.data.submit(method, url, isEdit.value ? "update" : "create");
                
                // Éxito
                Swal.fire("Éxito", isEdit.value ? "Proveedor actualizado" : "Proveedor creado", "success");
                emit("supplier-saved");
                emit("close-modal");
                
            } catch (error) {

                if (!dataForm.data.errors.any()) {
                    Swal.fire("Error", "Ocurrió un error inesperado.", "error");
                }
            } finally {
                disabledLoading();
            }
        };

        return {
            dataForm,
            updateThisField,
            clearError,
            onSubmit,
            loadingToogle,
            idModel,
            fieldsList
        };
    },
};
</script>