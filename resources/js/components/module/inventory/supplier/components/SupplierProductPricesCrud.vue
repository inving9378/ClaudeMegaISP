<template>
    <div class="modal-body p-4">
        <form @submit.prevent="onSubmit" @change="dataForm.data.errors.clear($event.target.name)">
            <template v-for="field in fieldsList" :key="field.field">
                <ComponentFormDefault
                    v-if="field.include"
                    :json="field"
                    :errors="dataForm.data.errors"
                    v-model="dataForm.data[field.field]"
                    :id="idModel"
                    @update-field="onUpdateField"
                    @clear-error="clearError"
                />
            </template>

            <div class="text-center mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary" :disabled="loadingToogle || dataForm.data.errors.any()">
                    <span v-if="loadingToogle" class="spinner-border spinner-border-sm me-1"></span>
                    {{ isEdit ? 'Actualizar Precio' : 'Agregar al Catálogo' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import {
    fieldsJson,
    dataForm,
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
} from "../../../../../hook/crudHook";
import { onMounted, watch, computed } from "vue";
import { loadingToogle, enableLoading, disabledLoading } from "../../../../../hook/loadingHook";
import Swal from "sweetalert2";

export default {
    name: "SupplierProductPricesCrud",
    components: { ComponentFormDefault },
    props: {
        action: {
            type: String,
            required: true,
        },
        module: {
            type: String,
            default: "SupplierProductPrice",
        },
        id: {
            type: [String, Number],
            default: null,
        },
        supplierId: {
            type: [String, Number],
            required: true,
        },
    },
    emits: ["close-modal", "supplier-product-price-saved"],
    setup(props, { emit }) {
        const isEdit = computed(() => {
            return props.id !== null && props.id !== undefined && props.id !== "";
        });

        const idModel = computed(() => (isEdit.value ? String(props.id) : null));

        const loadFields = async () => {
            disabledLoading();

            if (isEdit.value) {
                await getfieldsEdited(props.module, props.id);
            } else {
                await getfieldsJson(props.module);
                if (!dataForm.data.is_active) {
                    dataForm.data.is_active = true;
                }
            }
        };

        onMounted(loadFields);

        const fieldsList = computed(() => {
            const fj = fieldsJson.value;
            if (!fj) return [];
            if (Array.isArray(fj)) return fj;
            return Object.values(fj);
        });

        watch(
            () => props.id,
            (newId, oldId) => {
                if (newId !== oldId) {
                    loadFields();
                }
            }
        );

        const onUpdateField = (payload) => {
            updateThisField(payload);
        };

        const onSubmit = async () => {
            enableLoading();
            dataForm.data.supplier_id = props.supplierId;
            try {
                const url = props.action;
                const method = "post";

                await dataForm.data.submit(method, url, isEdit.value ? "update" : "create");

                Swal.fire("Éxito", isEdit.value ? "Precio actualizado" : "Producto agregado al catálogo", "success");
                emit("supplier-product-price-saved");
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
            onUpdateField,
            clearError,
            onSubmit,
            loadingToogle,
            idModel,
            fieldsList,
            isEdit,
        };
    },
};
</script>
