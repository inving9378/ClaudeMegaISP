<template>
    <div class="row q-col-gutter-sm">
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['olt_id'] ?? null"
                :filters="filters"
                option-label="olt_name"
                option-value="olt_id"
                name="olt_id"
                label="Olt"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['board'] ?? null"
                :filters="filters"
                option-label="board"
                option-value="board"
                name="board"
                label="Board"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['port'] ?? null"
                :filters="filters"
                option-label="port"
                option-value="port"
                name="port"
                label="Puerto"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['zone_id'] ?? null"
                :filters="filters"
                option-label="zone_name"
                option-value="zone_id"
                name="zone_id"
                label="Zona"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['odb_name'] ?? null"
                :filters="filters"
                option-label="odb_name"
                option-value="odb_name"
                name="odb_name"
                label="ODB"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('vlan')"
        >
            <select-component
                :model-value="filters['vlan'] ?? null"
                :filters="filters"
                option-label="vlan"
                option-value="vlan_id"
                name="vlan"
                label="VLAN"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['onu_type_id'] ?? null"
                :filters="filters"
                option-label="onu_type_name"
                option-value="onu_type_id"
                name="onu_type_id"
                label="Tipo"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('custom_template_name')"
        >
            <select-component
                :model-value="filters['custom_template_name'] ?? null"
                :filters="filters"
                option-label="custom_template_name"
                option-value="custom_template_name"
                name="custom_template_name"
                label="Perfil"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <select-component
                :model-value="filters['pon_type'] ?? null"
                :filters="filters"
                option-label="pon_type"
                option-value="pon_type"
                name="pon_type"
                label="PON"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
            <signal-toggle
                :model-value="filters['signal'] ?? null"
                @change="onChange"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-4 col-lg-3 col-xl-3">
            <status-toggle
                :model-value="filters['status'] ?? null"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-4 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('mode')"
        >
            <mode-toggle
                :model-value="filters['mode'] ?? null"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('mgmt_ip_mode')"
        >
            <select-component
                :model-value="filters['mgmt_ip_mode'] ?? null"
                :filters="filters"
                option-label="mgmt_ip_mode"
                option-value="mgmt_ip_mode"
                name="mgmt_ip_mode"
                label="Mgmt IP"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('tr069')"
        >
            <select-component
                :model-value="filters['tr069'] ?? null"
                :filters="filters"
                option-label="tr069"
                option-value="tr069"
                name="tr069"
                label="TR-069"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('voip_service')"
        >
            <select-component
                :model-value="filters['voip_service'] ?? null"
                :filters="filters"
                option-label="voip_service"
                option-value="voip_service"
                name="voip_service"
                label="VoIP"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('upload_speed')"
        >
            <select-component
                :model-value="filters['upload_speed'] ?? null"
                :filters="filters"
                option-label="upload_speed"
                option-value="upload_speed"
                name="upload_speed"
                label="Subida"
                @change="onChange"
            />
        </div>
        <div
            class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3"
            v-if="!exludeds.includes('download_speed')"
        >
            <select-component
                :model-value="filters['download_speed'] ?? null"
                :filters="filters"
                option-label="download_speed"
                option-value="download_speed"
                name="download_speed"
                label="Bajada"
                @change="onChange"
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import SelectComponent from "./form/SelectComponent.vue";
import SignalToggle from "./form/SignalToggle.vue";
import StatusToggle from "./form/StatusToggle.vue";
import ModeToggle from "./form/ModeToggle.vue";

const props = defineProps({
    currentFilters: {
        type: Object,
        default: {},
    },
    exludeds: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["change"]);

const filters = ref({});

onMounted(() => {
    filters.value = props.currentFilters;
});

watch(
    () => props.currentFilters,
    (n) => {
        filters.value = n;
    },
    { deep: true }
);

const onChange = (name, val) => {
    if (!val || (Array.isArray(val) && val.length === 0)) {
        delete filters.value[name];
    } else {
        filters.value[name] = val;
    }
    emits("change", Object.keys(filters.value).length > 0 ? filters.value : {});
};
</script>
