<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has('type') && 'has-danger'}`"
    >
        <label
            for="type"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Tipo de Documento
        </label>
        <div class="col-sm-12 col-md-9">
            <select
                :class="{ 'form-control': true }"
                name="type"
                id="type"
                v-model="valType"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`type, ${property.module_id}`"
                :val="valType ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div v-if="errors.has('type')" class="pristine-error text-help">
                {{ errors.get("type") }}
            </div>
        </div>
    </div>

    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has('template') && 'has-danger'}`"
    >
        <label
            for="template"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Plantilla
        </label>
        <div class="col-sm-12 col-md-9">
            <select
                :class="{ 'form-control': true }"
                name="template"
                id="template"
                v-model="valTemplate"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`template, ${property.module_id}`"
                :val="valTemplate ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div v-if="errors.has('template')" class="pristine-error text-help">
                {{ errors.get("template") }}
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { convertToSelect2, getOptions } from "../helpers/Transform";
import { setDefaultValue, uncheck, isEdit } from "../hook/comunValues";
import { getValueDB } from "../helpers/Request";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";
import { template } from "lodash";

export default {
    name: "Select2TypeTemplateSelectComponent",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Number,
    },
    components: {
        InputCheckboxDefaultVal,
    },
    emits: ["update-field", "change", "clear-error"],
    setup(props, { emit }) {
        const select2 = ref({
            type: {},
            template: {},
        });

        const valuesDb = ref({
            type: "",
            template: "",
        });

        const defaultValue =
            typeof props.property.default_value === "string"
                ? JSON.parse(props.property.default_value)
                : props.property.default_value;

        const valType = ref();
        const valTemplate = ref();

        const optionsType = ref([]);
        const optsType = reactive(optionsType);

        const optionsTemplate = ref([]);
        const optsTemplate = reactive(optionsTemplate);

        watch(valType, () => {
            emit("update-field", { value: valType, field: "type" });
            uncheck("type", props.property.module_id);
            uncheck("template", props.property.module_id);
            changeTemplate(valType.value);
        });
        watch(valTemplate, () => {
            emit("update-field", {
                value: valTemplate,
                field: "template",
            });
            uncheck("template", props.property.module_id);
        });

        onMounted(async () => {
            optionsType.value = await getOptions({
                model: "App\\Models\\DocumentTypeTemplate",
                id: "id",
                text: "name",
            });
            let defaultTypeValue = "";
            let defaultTemplateValue = "";

            if (props.modelValue) {
                let response = await getValueDB(props.property.label, [
                    "type",
                    "template",
                ]);
                valuesDb.value.type = response.type;
                valuesDb.value.template = _.toInteger(response.template);

                if (valuesDb.value.type) {
                    await changeTemplate(valuesDb.value.type);
                    defaultTypeValue = valuesDb.value.type;
                }
                if (valuesDb.value.template) {
                    defaultTemplateValue = valuesDb.value.template;
                }
            } else {
                if (typeof defaultValue === "object" && defaultValue) {
                    if (defaultValue.type) {
                        const defTypeValue = ref(defaultValue.type);
                        emit("update-field", {
                            value: defTypeValue,
                            field: "type",
                        });
                        await changeTemplate(defaultValue.type);
                        defaultTypeValue = defaultValue.type;
                    }
                    if (defaultValue.template) {
                        const defMunicipioValue = ref(defaultValue.template);
                        emit("update-field", {
                            value: defMunicipioValue,
                            field: "template",
                        });
                        defaultTemplateValue = defaultValue.template;
                    }
                }
            }

            $(document).ready(async () => {
                select2.value.type = await convertToSelect2(
                    "type",
                    optionsType,
                    defaultTypeValue,
                    "Seleccionar Tipo de Documento",
                    true
                );
                select2.value.template = await convertToSelect2(
                    "template",
                    optionsTemplate,
                    defaultTemplateValue,
                    "Seleccionar Plantilla",
                    true
                );
            });
        });

        const changeTemplate = async (type) => {
            optionsTemplate.value = type
                ? await getOptions({
                      model: "App\\Models\\DocumentTemplate",
                      id: "id",
                      text: "name",
                      filter: [
                          {
                              field_relation: "type",
                              field: "id",
                              value: type || null,
                          },
                      ],
                  })
                : await getOptions({
                      model: "App\\Models\\DocumentTemplate",
                      id: "id",
                      text: "name",
                      alfabetic: true,
                  });

            if (_.size(select2.value.template)) {
                select2.value.template.destroy();
                select2.value.template = await convertToSelect2(
                    "template",
                    optionsTemplate,
                    valuesDb.value.template,
                    "Seleccionar Plantilla",
                    true
                );
                valuesDb.value.template = "";
            }
        };

        return {
            valType,
            valTemplate,
            optsType,
            optsTemplate,
            setDefaultValue,
            isEdit,
        };
    },
};
</script>

<style scoped></style>
