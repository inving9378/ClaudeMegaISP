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
            <button
                type="button"
                class="btn btn-secondary"
                @click="$emit('close-modal')"
            >
                Cerrar
            </button>
            <button
                type="submit"
                class="btn btn-primary"
                :disabled="loadingToogle || dataForm.data.errors.any()"
            >
                <span
                    v-if="loadingToogle"
                    class="spinner-border spinner-border-sm me-1"
                ></span>
                Guardar
            </button>
        </div>
    </form>
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
import {
    loadingToogle,
    enableLoading,
    disabledLoading,
} from "../../../../../hook/loadingHook";
import Swal from "sweetalert2";

export default {
    name: "SupplierVendorsCrud",
    components: { ComponentFormDefault },
    props: {
        action: {
            type: String,
            required: true,
        },
        module: {
            type: String,
            default: "SupplierVendor",
        },
        id: {
            type: String,
            default: null,
        },
        supplierId: {
            type: [String, Number],
            required: true,
        },
    },
    emits: ["close-modal", "supplier-vendor-saved"],
    setup(props, { emit }) {
        const isEdit = computed(() => {
            return (
                props.id !== null && props.id !== undefined && props.id !== ""
            );
        });

        const idModel = computed(() =>
            isEdit.value ? String(props.id) : null
        );

        const loadFields = async () => {
            disabledLoading();

            if (isEdit.value) {
                await getfieldsEdited(props.module, props.id);
            } else {
                await getfieldsJson(props.module);
                if (!dataForm.data.supplier_id) {
                    dataForm.data.supplier_id = props.supplierId;
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

        const onSubmit = async () => {
            enableLoading();
            dataForm.data.supplier_id = props.supplierId;
            try {
                const url = props.action;

                const method = "post";

                await dataForm.data.submit(
                    method,
                    url,
                    isEdit.value ? "update" : "create"
                );

                Swal.fire(
                    "Éxito",
                    isEdit.value ? "Vendedor actualizado" : "Vendedor creado",
                    "success"
                );
                emit("supplier-vendor-saved");
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
            fieldsList,
        };
    },
};
</script>