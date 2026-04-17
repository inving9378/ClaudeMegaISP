<template>
    <div class="row q-py-sm">
        <label class="col-12 col-sm-3 text-right col-form-label">
            Servicio VoIP</label
        >
        <div class="col-12 col-sm-9 object-field">
            <q-option-group
                v-model="formData.voip_service"
                :options="serviceOptions"
                color="primary"
                inline
            />
        </div>
    </div>
    <div class="row q-py-sm" v-if="formData.voip_service === 'Enabled'">
        <label class="col-12 col-sm-3 text-right col-form-label">
            Adjuntar a</label
        >
        <div class="col-12 col-sm-9 object-field">
            <q-option-group
                v-model="formData.voip_attach_to"
                :options="attachOptions"
                color="primary"
                inline
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "VoIpService",
});

const props = defineProps({
    onu: Object,
});

const emits = defineEmits(["update"]);

const formData = ref({});

const serviceOptions = ref([
    {
        label: "Deshabilitado",
        value: "Disabled",
    },
    {
        label: "Activado (interruptor general)",
        value: "Enabled",
    },
]);

const attachOptions = ref([
    {
        label: "Mgmt",
        value: "Mgmt",
    },
    {
        label: "WAN",
        value: "WAN",
    },
]);

onMounted(() => {
    const obj = props.onu;
    let { voip_service } = obj;
    formData.value = {
        voip_service,
        voip_attach_to: "WAN",
    };
});

watch(
    formData,
    (n) => {
        let { voip_service, voip_attach_to } = n;
        emits("update", {
            status: voip_service.toLowerCase(),
            attr_to_server:
                voip_service === "Enabled"
                    ? {
                          voip_attach_to,
                      }
                    : null,
        });
    },
    { deep: true }
);
</script>
