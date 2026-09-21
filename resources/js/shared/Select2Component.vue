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
                :data="`${property.field}, ${property.module_id}`"
                v-model="val"
                :val="val ?? null"
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
</template>

<script>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from "vue";
import {
    selectTransform,
    getOptions,
    convertToSelect2,
} from "../helpers/Transform";
import {
    setDefaultValue,
    uncheck,
    isEdit,
    setDefaultValueSelect,
} from "../hook/comunValues";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

export default {
    name: "Select2Component",
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
        const isInitialized = ref(false);
        let syncingFromProp = false;

        watch(val, (newValue, oldValue) => {
            if (syncingFromProp) {
                return;
            }
            if (newValue != oldValue) {
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
            }
        });

        watch(
            () => props.idModel,
            (actual, actionBefore) => {
                idMod.value = actual;
            }
        );

        const destroyChoice = () => {
            if (!choice.value) {
                return;
            }
            try {
                choice.value.destroy();
            } catch (error) {
                console.error(error);
            }
            choice.value = null;
        };

        const renderChoice = async (value) => {
            destroyChoice();
            choice.value = await convertToSelect2(
                props.property.field,
                options,
                value,
                props.property.placeholder
            );
        };

        // fieldsJson (hook/crudHook.js) es un singleton compartido entre pantallas
        // del SPA: al cambiar de registro dentro del mismo módulo, Vue reutiliza
        // esta misma instancia del componente en vez de desmontarla/remontarla, así
        // que "modelValue" cambia pero onMounted no vuelve a correr. Antes este watch
        // solo reasignaba la selección (removeActiveItems + setChoiceByValue) sin
        // cerrar el dropdown de Choices.js — si el usuario lo había dejado abierto
        // (o cualquier otro estado visual interno) al navegar a otro cliente, quedaba
        // "abierto" mostrando ya las opciones del registro nuevo hasta recargar (F5).
        // Reconstruir el widget completo (mismo patrón que SelectComponent.vue) lo
        // cierra y arranca limpio en cada cambio de registro.
        watch(
            () => props.modelValue,
            async (actual) => {
                if (!isInitialized.value || !choice.value) {
                    return;
                }
                const nextVal = actual ?? null;
                if (nextVal === val.value) {
                    return;
                }
                syncingFromProp = true;
                val.value = nextVal;
                syncingFromProp = false;
                await renderChoice(nextVal);
            }
        );

        onMounted(async () => {
            options.value = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search, idMod.value);

            $(document).ready(async () => {
                await renderChoice(props.modelValue);
            });

            isInitialized.value = true;
        });

        onBeforeUnmount(() => {
            destroyChoice();
        });

        return {
            val,
            opts,
            setDefaultValue,
            isEdit,
        };
    },
};
</script>

<style scoped></style>
