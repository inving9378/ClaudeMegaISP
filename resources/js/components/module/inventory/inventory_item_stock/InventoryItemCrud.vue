<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
            <template v-for="val in fieldsJson">
                <ComponentFormDefault
                    v-if="val.include"
                    :id="id"
                    :json="val"
                    :errors="dataForm.data.errors"
                    :key="val"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </template>

            <SelectStoreAndZone v-if="!isUpdate" :inventory_store_id="dataForm.data.inventory_store_id" :store_zone_id="dataForm.data.store_zone_id"></SelectStoreAndZone>
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
import { onMounted, ref, watch } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";
import Swal from "sweetalert2";
import SelectStoreAndZone from "../component/SelectStoreAndZone.vue";

export default {
    name: "InventoryItemCrud",
    props: {
        action: String,
    },
    components: {
        ComponentFormDefault,
        SelectStoreAndZone,
    },
    setup(props, { emit }) {
        const id = ref(null);
        const isUpdate = ref(false);
        let submitButtonAction =
            props.action == "/inventory/inventory_item/add"
                ? "Crear Articulo"
                : "Salvar Articulo";

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
            let inventoryItemId = getIdByAction(action);
            if (action == "/inventory/inventory_item/add") {
                id.value = null;
                await getfieldsJson("InventoryItem");
            } else {
                id.value = inventoryItemId;
                await getfieldsEdited("InventoryItem", inventoryItemId);
                fieldsJson.value.initial_stock.include = false;
                fieldsJson.value.image.include = false;
                isUpdate.value = true;
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(action, "/inventory/inventory_item/update/");
        };

        const closeModal = () => {
            dataForm.data.reset();
            emit("close-modal");
        };

        const onSubmit = () => {
            dataForm.data
                .uploadFile(`${props.action}`, "reset")
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
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal,
            id,
            isUpdate,
        };
    },
};
</script>

<style scoped></style>
