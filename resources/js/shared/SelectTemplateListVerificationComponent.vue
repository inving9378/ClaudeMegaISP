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

        <div
            v-if="checks.length > 0"
            v-for="(item, index) in checks"
            :key="item"
            :id="`checks-input-list-verification`"
        >
            <div :class="`row mb-2 item align-items-center col-12`">
                <div class="col-sm-12 col-md-3">
                    <input
                        type="checkbox"
                        :id="`check_${index}`"
                        switch="none"
                        @change="getDataToList(index, item)"
                    />
                    <label class="m-0" :for="`check_${index}`"></label>
                </div>
                <label
                    :for="`check_${index}`"
                    :class="`col-sm-12 col-md-9 col-form-label text-md-end pr-2 text-sm-center`"
                >
                    {{ item }}
                </label>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted } from "vue";
import {
    selectTransform,
    getOptions,
    convertToSelect2,
} from "../helpers/Transform";
import { setDefaultValue, uncheck, isEdit } from "../hook/comunValues";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";
import { getListTemplate } from "../components/module/client/info/comun_variable";

export default {
    name: "SelectTemplateListVerificationComponent",
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
    components: {
        InputCheckboxDefaultVal,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const options = ref([]);
        const opts = reactive(options);
        const choice = ref();
        const idMod = ref(props.idModel);

        const checks = ref([]);
        const arrayChecks = ref([]);

        watch(val, (newValue, oldValue) => {
            if (newValue != oldValue) {
                emit("update-field", {
                    value: val,
                    field: props.property.field,
                });
                uncheck(props.property.field, props.property.module_id);
            }
            getTemplateList(val.value);
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

        const getTemplateList = async (id) => {
            if (id) {
                await axios["post"](
                    `/configuracion/list-template-verification/get-check-list-template/${id}`
                ).then((response) => {
                    checks.value = JSON.parse(response.data);
                });
            }
        };

        onMounted(async () => {
            options.value = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search, idMod.value);

            $(document).ready(async () => {
                choice.value = await convertToSelect2(
                    props.property.field,
                    options,
                    props.modelValue,
                    props.property.placeholder
                );
            });
        });

        watch(getListTemplate, () => {
            val.value = getListTemplate.value;
        });

        const getDataToList = (index, text) => {
            // Obtén el checkbox correspondiente usando el ID
            const input = document.getElementById(`check_${index}`);

            // Verifica si el checkbox está marcado
            if (input.checked) {
                // Si está marcado, agrega el texto al array si no está ya presente
                if (!arrayChecks.value.includes(text)) {
                    arrayChecks.value.push(text);
                }
            } else {
                // Si no está marcado, elimina el texto del array si está presente
                const itemIndex = arrayChecks.value.indexOf(text);
                if (itemIndex !== -1) {
                    arrayChecks.value.splice(itemIndex, 1);
                }
            }
            emit("update-field", {
                value: arrayChecks.value,
                field: "checks",
            });
        };

        return {
            val,
            opts,
            setDefaultValue,
            isEdit,
            checks,
            getDataToList,
        };
    },
};
</script>

<style scoped></style>
