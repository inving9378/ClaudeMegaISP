<template>
    <ModalCentrado
        id="crudEditFieldModule"
        :modalTitle="title"
        labelledby="labelledby"
        @submit="onSubmit"
        @close-modal="closeModal"
    >
        <form
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
                <div v-if="getIfFieldIsTypeSelect(val.field)" class="row">
                    <OptionsSelect
                        :dataForm="dataForm"
                        @update-field="updateThisField"
                        :key="val"
                    >
                    </OptionsSelect>
                </div>
            </template>
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
import ComponentFormDefault from "../../../../components/ComponentFormDefault";
import ModalCentrado from "../../../../shared/ModalCentrado.vue";
import OptionsSelect from "./OptionsSelect.vue";

export default {
    name: "CrudFieldModule",
    props: {
        action: String,
        title: String,
        module: String,
        moduleId: String | Number,
    },
    components: {
        ComponentFormDefault,
        ModalCentrado,
        OptionsSelect,
    },
    setup(props, { emit }) {
        const id = ref(null);
        let submitButtonAction =
            props.action == "/configuracion/additional-fields/add"
                ? "Crear "
                : "Salvar";

        const showOptionsToSelect = ref(false);

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
            if (action == "/configuracion/additional-fields/add") {
                id.value = null;
                await getfieldsJson(props.module);
                dataForm.data["module_id"] = props.moduleId;
            } else {
                id.value = partnerId;
                await getfieldsEdited(props.module, partnerId);
                delete fieldsJson.value.name;
                if (dataForm.data['additional_field'] == 0) {
                    delete fieldsJson.value.type;
                    delete fieldsJson.value.partition;
                    delete fieldsJson.value.include;
                }
                await getRequiredValue();
            }
        };

        const getRequiredValue = async () => {
            let required = {};
            axios["post"](
                `/configuracion/additional-fields/get-required-value/${id.value}`,
                { module }
            ).then((response) => {
                required = response.data.required;
                updateThisField({ field: "required", value: required });
            });
        };

        const getIdByAction = (action) => {
            return _.trimStart(
                action,
                "/configuracion/additional-fields/update/"
            );
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const updateThisField = ({ field, value }) => {
            if (field == "type") {
                verifyIsTypeSelectAndSetTrueShowOptionsOrSearch(value);
            }
            dataForm.data[field] = value;
        };

        const verifyIsTypeSelectAndSetTrueShowOptionsOrSearch = (type) => {
            let array = [22];
            if (
                type.value !== undefined &&
                array.includes(parseInt(type.value))
            ) {
                showOptionsToSelect.value = true;
            } else {
                deleteOptionsToDataFormIfExist();
                showOptionsToSelect.value = false;
            }
        };

        const deleteOptionsToDataFormIfExist = () => {
            const filteredOptions = Object.keys(dataForm.data).filter((key) =>
                /^option\d+$/.test(key)
            );

            filteredOptions.forEach((optionKey) => {
                delete dataForm.data[optionKey];
            });
        };

        const getIfFieldIsTypeSelect = (field) => {
            if (
                field == "type" &&
                showOptionsToSelect.value &&
                dataForm.data[field] == 22
            ) {
                return true;
            }
            return false;
        };

        const assignValuesToOptions = () => {
            const filteredOptions = Object.keys(dataForm.data).filter((key) =>
                /^option\d+$/.test(key)
            );
            const filteredValues = filteredOptions.map(
                (key) => dataForm.data[key]
            );
            const combinedArray = [].concat(...filteredValues);
            dataForm.data.options = combinedArray;
        };

        const onSubmit = () => {
            assignValuesToOptions();
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    emit("close-modal");
                })
                .catch((error) => {

                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            id,
            closeModal,
            showOptionsToSelect,
            getIfFieldIsTypeSelect,
        };
    },
};
</script>

<style scoped></style>
