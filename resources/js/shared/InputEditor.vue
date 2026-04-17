<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label || title }}
        </label>

        <div :class="`${property.class_field}`">
            <!-- contenedor CKEditor -->
            <div :id="property.field"></div>

            <!-- backup textarea -->
            <textarea
                :id="currentUid"
                class="form-control hidden"
                v-model="val"
            ></textarea>

            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
            >
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick, onBeforeUnmount } from "vue";
import { convertToCkeditor } from "../helpers/Transform";
import { getAjaxDefaultValue } from "../helpers/Request";
import { uid } from "../../../public/plugins/quasar/js/quasar.umd.prod";

defineOptions({
    name: "InputEditor",
});

const props = defineProps({
    title: String,
    errors: {
        type: Object,
        default: {},
    },
    property: Object,
    modelValue: String,
});

const emits = defineEmits(["update-field"]);

const val = ref(null);
const ckInstance = ref(null);
const ready = ref(false);

let currentUid = "text-area" + uid();

onMounted(async () => {
    val.value = props.modelValue ?? (await getValByDefaultValue());
    emitUpdate();
    await initEditor();
});

const getValByDefaultValue = async () => {
    return typeof props.property.default_value === "object" &&
        props.property.default_value &&
        props.property.default_value.request
        ? await getAjaxDefaultValue(props.property.default_value.request)
        : props.property.default_value ?? null;
};

watch(
    () => props.modelValue,
    async (newVal) => {
        if (!ready.value) return;
        if (newVal !== val.value) {
            val.value = newVal || "";
            if (ckInstance.value && ckInstance.value.setData) {
                ckInstance.value.setData(val.value);
            }
        }
    }
);

const emitUpdate = () => {
    emits("update-field", { value: val, field: props.property.field });
};

const initEditor = async () => {
    await nextTick();

    // destruir editor previo si existe
    if (ckInstance.value && ckInstance.value.destroy) {
        await ckInstance.value.destroy();
        ckInstance.value = null;
    }

    // crear instancia nueva
    const instance = await convertToCkeditor(currentUid, val);

    // Si el helper devuelve instancia válida
    if (instance && instance.setData) {
        ckInstance.value = instance;
        // Establecer valor inicial
        if (val.value) instance.setData(val.value);
    }

    // Escuchar cambios del editor (por seguridad)
    if (ckInstance.value) {
        ckInstance.value.model.document.on("change:data", () => {
            val.value = ckInstance.value.getData();
            emitUpdate();
        });
    }

    ready.value = true;
};

watch(val, emitUpdate);
onBeforeUnmount(async () => {
    if (ckInstance.value && ckInstance.value.destroy) {
        await ckInstance.value.destroy();
        ckInstance.value = null;
    }
});
</script>

<style scoped>
.ck-editor__editable_inline {
    min-height: 150px;
}
</style>
