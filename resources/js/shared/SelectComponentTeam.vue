<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : property.class_col === 'partial'
                ? 'col-6'
                : 'col'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
        :key="sortedOpts"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <select
                class="form-control"
                v-model="val"
                multiple
                :id="property.field"
            >
                <optgroup
                    v-for="(group, groupName) in sortedOpts"
                    :label="groupName"
                    :key="groupName"
                >
                    <option
                        v-for="(text, value) in group"
                        :value="value"
                        :key="value"
                    >
                        {{ text }}
                    </option>
                </optgroup>
            </select>
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
</template>

<script>
import { onMounted, ref, computed, nextTick, watch } from "vue";
import axios from "axios";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";
import { uncheck, isEdit, setDefaultValueSelect } from "../hook/comunValues";
import { convertToBoostrapSelectTeam } from "../helpers/Transform";

export default {
    name: "SelectComponentTeam",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Array | Number,
    },
    components: {
        InputCheckboxDefaultVal,
    },
    setup(props, { emit }) {
        const val = ref();
        const options = ref({});
        const isInitialized = ref(false);
        const sortedOpts = computed(() => {
            const sortedOpts = {};

            // Mantener el orden original de las claves en el objeto options.value
            Object.keys(options.value).forEach((groupName) => {
                sortedOpts[groupName] = options.value[groupName];
            });

            return sortedOpts;
        });

        watch(val, () => {
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
            uncheck(props.property.field, props.property.module_id);
            if (isInitialized.value) {
                setDefaultValueSelect(
                    val.value,
                    props.property.field,
                    props.property.module_id
                );
            }
        });

        onMounted(async () => {
            let modelValue = props.modelValue;
            if (modelValue == null) {
                modelValue = [];
            }
            val.value =
                modelValue.length > 0
                    ? modelValue
                    : await getDefaultValueIfExist();
            options.value = await getOptionTeam();

            if (Object.keys(options.value).length) {
                // Espera a que el DOM esté completamente actualizado
                await nextTick();

                $(document).ready(function () {
                    // Una vez actualizado el DOM, inicializamos el multiselect
                    convertToBoostrapSelectTeam(
                        props.property.field,
                        val,
                        options.value
                    );
                });
            }

            isInitialized.value = true;
        });

        const getDefaultValueIfExist = async () => {
            let data = [];
            await axios["post"]("/get-default-fields-value", {
                module_id: props.property.module_id,
                field: props.property.field,
            }).then((response) => {
                data = response.data.value;
                if (data != undefined) {
                    data = convertToArray(data);
                } else {
                    data = [];
                }
            });
            return data;
        };

        const convertToArray = (valor) => {
            if (valor != undefined && valor != null) {
                return valor.split(",");
            }
        };

        const getOptionTeam = async () => {
            let opts = {};
            await axios.post("/get-options-team").then((response) => {
                opts = response.data;
            });
            return opts;
        };

        return {
            val,
            options,
            sortedOpts,
            isEdit,
        };
    },
};
</script>

<style scoped></style>
