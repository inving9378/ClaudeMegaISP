<template>
    <div class="row q-my-sm">
        <label class="col-12 col-sm-3 text-right col-form-label"
            >Modo ONU</label
        >
        <div class="col-12 col-sm-9 object-field">
            <q-option-group
                v-model="formData.onu_mode"
                :options="
                    onu.capabilities
                        ? onuModes.filter((m) =>
                              onu.capabilities.includes(m.value)
                          )
                        : onuModes
                "
                color="primary"
                inline
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "OnuMode",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const formData = ref({
    onu_mode: null,
});

const onuModes = [
    {
        label: "Enrutamiento",
        value: "Routing",
    },
    {
        label: "Puente",
        value: "Bridging",
    },
];

onMounted(() => {
    formData.value = {
        onu_mode: props.onu.mode,
    };
});

watch(
    formData,
    (n) => {
        emits("update", {
            attr_to_server: n,
        });
    },
    { deep: true }
);
</script>
