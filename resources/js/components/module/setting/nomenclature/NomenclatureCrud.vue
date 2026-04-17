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

            <div class="pristine-error text-help" v-if="showErrorMultiple">
                <p class="pristine-error text-help">
                    {{ errorMultipleNomenclature }}
                </p>
            </div>
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

export default {
    name: "NomenclatureCrud",
    props: {
        action: String,
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const id = ref(null);
        let submitButtonAction =
            props.action == "/configuracion/nomenclature/add"
                ? "Crear Nomenclatura"
                : "Salvar Nomenclatura";

        const showErrorMultiple = ref(false);
        const errorMultipleNomenclature = ref("");

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
            let partnerId = getIdByAction(action);
            if (action == "/configuracion/nomenclature/add") {
                id.value = null;
                await getfieldsJson("Nomenclature");
            } else {
                id.value = partnerId;
                await getfieldsEdited("Nomenclature", partnerId);
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(action, "/configuracion/nomenclature/update/");
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = async () => {
            if (verifyIfNotCombineMultipleWithSimpleFormData()) {
                dataForm.data
                    .submit("post", `${props.action}`, props.action)
                    .then((response) => {
                        emit("close-modal");
                        showErrorMultiple.value = false;
                    })
                    .catch((error) => {
                        showErrorMultiple.value = true;
                        // Accedemos correctamente al mensaje del campo 'multiple' que es un array
                        errorMultipleNomenclature.value =
                            error?.errors?.multiple?.[0] ?? "";
                    });
            }
        };

        const verifyIfNotCombineMultipleWithSimpleFormData = () => {
            const multiple = dataForm.data["multiple"];
            const district = dataForm.data["district"];
            const zone = dataForm.data["zone"];
            const client = dataForm.data["client"];

            // Verificamos si "multiple" tiene un valor (no es vacío ni nulo)
            if (multiple) {
                // Comprobar si alguno de los otros campos tiene un valor al mismo tiempo
                if (district || zone || client) {
                    showErrorMultiple.value = true;
                    errorMultipleNomenclature.value =
                        "No es posible añadir una Nomenclatura simple con una multiple";
                    return false;
                }
            }

            showErrorMultiple.value = false;
            errorMultipleNomenclature.value = ""; // Limpiar el error si todo es válido
            return true;
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
            showErrorMultiple,
            errorMultipleNomenclature,
        };
    },
};
</script>

<style scoped></style>
