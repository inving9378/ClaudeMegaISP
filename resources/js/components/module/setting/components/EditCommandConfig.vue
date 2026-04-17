<template>
    <ModalCentrado
        :id="idModal"
        :modalTitle="modalTitle"
        labelledby="labelledby"
        @submit="onSubmit"
    >
        <form
            method="POST"
            @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)"
        >
            <div v-for="val in fieldsJson">
                <Component-Form-Default
                    v-if="val.include"
                    :json="val"
                    :id="`${idCommand}`"
                    :errors="dataForm.data.errors"
                    :key="val"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </div>
        </form>
    </ModalCentrado>
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
import ModalCentrado from "../../../../shared/ModalCentrado";

export default {
    name: "EditCommandConfig",
    props: {
        frequency_has_time: String,
    },
    components: {
        ComponentFormDefault,
        ModalCentrado,
    },
    setup(props, { emit }) {
        const idModal = ref(`modalEditCommandConfig`);
        const modalTitle = ref("Editar Comando");
        const frequenciesTime = ref(JSON.parse(props.frequency_has_time));
        const idCommand = ref("");

        onMounted(async () => {
            $(document).on("click", ".btnEditCommandConfig", function () {
                let dataId = JSON.parse($(this).attr("data-id"));
                openModal(idModal.value, dataId);
            });
        });

        const openModal = async (idModal, dataId) => {
            idCommand.value = dataId;
            await getfieldsEdited("CommandConfig", dataId);
            //TODO Quitar despues que se arreglen todos los comandos
            fieldsJson.value.status.include = false;
            setIncludeTrueOrFalseToExecutionTime(dataForm.data["frequency_id"]);
            $(`#${idModal}`).modal("show");
        };

        const updateThisField = ({ field, value }) => {
            if (field == "frequency_id") {
                setIncludeTrueOrFalseToExecutionTime(value);
            }
            dataForm.data[field] = value;
        };

        const setIncludeTrueOrFalseToExecutionTime = (val) => {
            let valueFrequency = typeof val == "object" ? val.value : val;
            const matchingKey = Object.keys(frequenciesTime.value).find(
                (key) => key == valueFrequency
            );
            if (matchingKey) {
                fieldsJson.value.execution_time.include =
                    frequenciesTime.value[matchingKey];
                updateFieldExcecutionTimeIfFalse(
                    frequenciesTime.value[matchingKey]
                );
            }
        };

        const updateFieldExcecutionTimeIfFalse = (val) => {
            if (!val)
                return updateThisField({
                    value: null,
                    field: "execution_time",
                });
        };
        const onSubmit = async () => {
            if (confirm("¿Estas seguro de cambiar esta configuracion?")) {
                dataForm.data
                    .submit(
                        "post",
                        `/configuracion/command/update/${idCommand.value}`,
                        "update"
                    )
                    .then((response) => {
                        $(`#${idModal.value}`).modal("hide");
                    });
            }
        };

        return {
            idModal,
            modalTitle,
            onSubmit,
            fieldsJson,
            dataForm,
            updateThisField,
            clearError,
            idCommand,
        };
    },
};
</script>

<style scoped></style>
