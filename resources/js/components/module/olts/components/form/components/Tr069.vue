<template>
    <div class="row q-py-sm">
        <label
            class="col-12 col-sm-3 text-right col-form-label"
            for="tr069_profile"
            >Perfil TR069</label
        >
        <div class="col-12 col-sm-9 object-field">
            <select-form-component
                name="tr069_profile"
                :model-value="formData.tr069_profile"
                :options="options"
                :filterable="false"
                :clearable="false"
                :checkedSelected="false"
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
    name: "Tr069",
});

const props = defineProps({
    onu: Object,
});

const emits = defineEmits(["update"]);

const options = [
    {
        label: "Deshabilitado",
        value: null,
    },
    {
        label: "SmartOLT",
        value: "SmartOLT",
    },
];

const formData = ref({
    tr069_profile: null,
});

onMounted(() => {
    formData.value = {
        tr069_profile: props.onu.tr069_profile,
    };
});

watch(
    formData,
    (n) => {
        let tr069_profile = n.tr069_profile;
        emits("update", {
            status: tr069_profile ? "enable" : "disable",
            attr_to_server: tr069_profile
                ? {
                      tr069_profile,
                  }
                : null,
        });
    },
    { deep: true }
);
</script>
