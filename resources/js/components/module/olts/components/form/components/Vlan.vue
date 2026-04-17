<template>
    <div class="row q-my-sm">
        <label class="col-12 col-sm-3 text-right col-form-label" for="vlan">{{
            label
        }}</label>
        <div class="col-12 col-sm-9 object-field">
            <select-form-component
                name="vlan"
                :model-value="formData.vlan"
                :options="vlans"
                :required="true"
                :loading="loading"
                option-value="vlan"
                @change="
                    (name, val) => {
                        formData[name] = val;
                    }
                "
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import SelectFormComponent from "../SelectFormComponent.vue";

defineOptions({
    name: "OnuMode",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
    vlans: {
        type: Array,
        default: [],
    },
    loading: Boolean,
    label: {
        type: String,
        default: "VLAN-ID",
    },
});

const emits = defineEmits(["update"]);

const formData = ref({
    vlan: null,
});

onMounted(() => {
    formData.value = {
        vlan: props.onu.vlan,
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
