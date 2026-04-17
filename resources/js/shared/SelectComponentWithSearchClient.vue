<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
        :key="opts"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
            <a v-if="clientId" target="_blank" :href="'/cliente/editar/' + clientId">{{
                clientId
            }}</a>
        </label>
        <div :class="`${property.class_field}`">
            <select
                :class="{ 'form-control': true }"
                :name="property.field"
                :id="property.field"
                :disabled="property.disabled"
                v-model="val"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`${property.field}, ${property.module_id}`"
                :val="val ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
            >
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>

    <Select2Component
        :property="{
            field: 'client_service_id',
            label: 'Servicio relacionado',
            class_col: 'full',
            search: {
                model: 'App\\Models\\SpecialModelClientServices',
                id: `${clientMainInformationId}`,
                text: 'name',
            },
        }"
        :errors="errors"
        :modelValue="serviceId"
        :key="clientMainInformationId"
        @update-field="updateThisField"
    />
</template>

<script>
import { reactive, ref, watch, onMounted,nextTick } from "vue";
import {
    selectTransform,
    getOptions,
    convertToSelect2WithSearch,
} from "../helpers/Transform";
import { setDefaultValue, uncheck, isEdit } from "../hook/comunValues";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";
import Select2Component from "./Select2Component.vue";
import { position } from "../helpers/googleMapsVariables";
import { getServicesByClientMainInformationId } from "../helpers/Request";
import { clientMainInformationId } from "../components/module/client/info/comun_variable";

export default {
    name: "SelectComponentWithSearchClient",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        idModel: {
            type: String,
            default: null,
        },
        modelValue: String | Number,
        id: String | Number,
    },
    emits: ["update-field", "clearDependErrors", "change"],
    components: {
        InputCheckboxDefaultVal,
        Select2Component,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const options = ref([]);
        const opts = reactive(options);
        const choice = ref();
        const idMod = ref(props.idModel);
        const serviceId = ref(null);
        const clientId = ref(null);

        let lat = parseFloat(process.env.MIX_VUE_APP_CENTER_MAP_LATITUDE);
        let lng = parseFloat(process.env.MIX_VUE_APP_CENTER_MAP_LONGITUDE);

        watch(val, async (newValue, oldValue) => {
            if (newValue != oldValue) {
                emit("update-field", {
                    value: val,
                    field: props.property.field,
                });
                uncheck(props.property.field, props.property.module_id);
                clientMainInformationId.value = val.value;
            }
            if (val.value == null) {
                updateThisField({ value: null, field: "client_service_id" });
                position.value = `${lat},${lng}`;
                serviceId.value = null;
            }
        });

        watch(clientMainInformationId, async (newValue, oldValue) => {
            getServicesByClient();
            getClientIdAndUpdateLabelToSelect(newValue);
        });

        watch(
            () => props.idModel,
            (actual, actionBefore) => {
                idMod.value = actual;
            }
        );

        watch(
            () => props.modelValue,
            (actual, actionBefore) => {
                if (choice.value) {
                    choice.value.setChoiceByValue(actual);
                }
            }
        );

        const updateThisField = (value) => {
            emit("update-field", value);
        };

        onMounted(async () => {
            if (clientMainInformationId.value != null) {
                val.value = clientMainInformationId.value;
            }
            options.value = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search, idMod.value);

            $(document).ready(async () => {
                choice.value = await convertToSelect2WithSearch(
                    props.property.field,
                    options,
                    props.modelValue,
                    props.property.placeholder
                );
            });
            getServicesByClient();
            await nextTick(() => {
                getClientIdAndUpdateLabelToSelect(props.modelValue);
            });
        });

        const getServicesByClient = async () => {
            if (
                clientMainInformationId.value != null &&
                clientMainInformationId.value != "null"
            ) {
                const response = await getServicesByClientMainInformationId(
                    clientMainInformationId.value
                );
                serviceId.value = response;
            }
        };

        const getClientIdAndUpdateLabelToSelect = async (id) => {
            if (!id) return;
            await axios["post"](
                `/cliente/get-client-id-by-client-main-information-id/${id}`
            ).then((response) => {
                clientId.value = response.data;
            });
        };

        return {
            val,
            opts,
            setDefaultValue,
            isEdit,
            updateThisField,
            serviceId,
            clientMainInformationId,
            clientId,
        };
    },
};
</script>

<style scoped></style>
