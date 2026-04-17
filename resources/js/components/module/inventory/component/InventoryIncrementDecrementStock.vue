<template>
    <div class="modal-body m-0" style="min-height: 500px">
        <form
            id="form-inventory-change-stock"
            class="row"
            method="POST"
            @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)"
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
        </form>
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
            form="form-inventory-change-stock"
            type="submit"
            :disabled="dataForm.data.errors.any()"
        >
            Guardar
        </button>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import ComponentFormDefault from "../../../ComponentFormDefault";
import Swal from "sweetalert2";
import Form from "../../../../helpers/Form";
import { idItem } from "../comun_variables";

export default {
    name: "InventoryIncrementDecrementStock",
    props: {},
    emits: ["close-modal"],
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const dataForm = reactive({
            data: new Form({}),
        });

        const fieldsJson = ref({
            type_change_stock: {
                field: `type_change_stock`,
                type: "select-component",
                label: "Aumentar O Disminuir?",
                include: 1,
                placeholder: "Seleccione",
                position: 2,
                class_label:
                    "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                class_field: "col-sm-12 col-md-9",
                class_col: "full",
                options: {
                    increment: "Aumentar",
                    decrement: "Disminuir",
                },
            },
            quantity_change_stock: {
                field: `quantity_change_stock`,
                type: "input-number",
                label: "Cantidad",
                include: 1,
                placeholder: "Ingrese Cantidad",
                position: 2,
                class_label:
                    "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                class_field: "col-sm-12 col-md-9",
                class_col: "full",
            },
            id_item: {
                field: `id_item`,
                type: "hidden",
                include: 1,
            },
        });
        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsJson();
        };

        const getfieldsJson = async () => {
            dataForm.data = new Form(fieldsJson.value);
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = () => {
            dataForm.data["id_item"] = idItem.value;
            dataForm.data
                .submit("post", `/inventory/inventory_item_stock/change_stock`)
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        closeModal();
                    }
                })
                .catch((error) => {
                    let fieldsErrors = error.response?.data?.errors;
                    if (fieldsErrors) {
                        // Construir un mensaje que incluya todos los errores
                        let errorMessage = "";
                        for (const field in fieldsErrors) {
                            if (fieldsErrors.hasOwnProperty(field)) {
                                errorMessage += `${fieldsErrors[field].join(
                                    ", "
                                )}\n`;
                            }
                        }

                        // Mostrar el mensaje de error con todos los errores
                        Swal.fire("Error", errorMessage, "error");
                    } else {
                        // Si no hay errores específicos, mostrar el mensaje general
                        Swal.fire(
                            "Error",
                            error.response?.data?.message ||
                                "Ocurrió un error inesperado.",
                            "error"
                        );
                    }
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            closeModal,
        };
    },
};
</script>

<style scoped></style>
