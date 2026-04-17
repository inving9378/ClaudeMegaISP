<template>
    <div class="q-gutter-xs q-mb-md">
        <q-btn
            v-for="btn in actions"
            :key="btn.label"
            color="primary"
            :label="btn.label"
            no-caps
            :loading="btn.loading"
            @click="onClick(btn)"
        />
    </div>
    <pre id="status" class="status_container text-wrap" v-if="fullText">
        {{ fullText }}
    </pre>
</template>

<script setup>
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";
import { getOLTData } from "../../helper/request";

defineOptions({
    name: "DefaultOnu",
});

const props = defineProps({
    onu: Object,
});

const fullText = ref(null);

const actions = ref([
    {
        label: "Obtener estado",
        action: "full-status",
        response: "full_status_info",
        loading: false,
    },
    {
        label: "Mostrar configuración en ejecución",
        action: "running-config",
        response: "running_config",
        loading: false,
    },
]);

const onClick = async (btn) => {
    btn.loading = true;
    const result = await getOLTData(`/olts/onus/${btn.action}/${props.onu.id}`);
    if (result.success) {
        fullText.value = result[btn.response];
    } else {
        message(
            result.error ?? result.message ?? "Error al procesar la solicitud",
            "error"
        );
    }
    btn.loading = false;
};
</script>
<style scoped>
pre {
    display: block;
    padding: 10px;
    margin: 0 0 10.5px;
    font-size: 14px;
    line-height: 1.42857143;
    word-break: break-all;
    word-wrap: break-word;
    color: #7b8a8b;
    background-color: #f9f9f9;
    border: 1px solid #e9ebec;
    border-radius: 4px;
}
.text-wrap {
    white-space: pre-wrap !important;
    word-break: break-word !important;
}
</style>
