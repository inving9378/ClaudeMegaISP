<template>
    <div class="row q-py-sm">
        <label class="col-12 col-sm-3 text-right col-form-label">
            IP de gestión</label
        >
        <div class="col-12 col-sm-9 object-field">
            <q-option-group
                v-model="formData.mode"
                :options="modeOptions"
                color="primary"
                inline
            />
        </div>
    </div>
    <div class="row q-py-sm">
        <label class="col-12 col-sm-3 text-right col-form-label">
            ID puerto servicio</label
        >
        <div class="col-12 col-sm-9 object-field q-pt-sm">
            {{ onu.mgmt_ip_service_port }}
        </div>
    </div>
    <template v-if="formData.mode !== 'Inactive'">
        <use-vlans-component
            :vlans="vlansOptions"
            :loading="loading"
            :data="{
                svlan: formData.svlan,
                cvlan: formData.cvlan,
                tag_transform_mode: formData.tag_transform_mode,
            }"
            @change="
                (data) => {
                    formData.svlan = data.svlan;
                    formData.cvlan = data.cvlan;
                    formData.tag_transform_mode = data.tag_transform_mode;
                }
            "
        />
        <div class="row q-py-sm">
            <label class="col-12 col-sm-3 text-right col-form-label" for="vlan"
                >VLAN-ID</label
            >
            <div class="col-12 col-sm-9 object-field">
                <select-form-component
                    name="vlan"
                    :model-value="formData.vlan"
                    :options="
                        vlansOptions.filter((v) => v.scope === 'mgmt/voip')
                    "
                    :required="true"
                    option-value="vlan"
                    @change="
                        (name, val) => {
                            formData[name] = val;
                        }
                    "
                />
            </div>
        </div>
        <template v-if="formData.mode === 'Static IP'">
            <div class="row">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="ipv4_address"
                    >IP de administración</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.ipv4_address"
                        dense
                        outlined
                        for="ipv4_address"
                        clearable
                        hint="Requerido"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>
            <div class="row q-py-sm">
                <div class="col text-right">
                    <span
                        class="cursor-pointer text-primary"
                        @click="ipDetails = !ipDetails"
                        >{{ ipDetails ? "Ocultar" : "Mostrar" }} detalles de del
                        IP de administración</span
                    >
                </div>
            </div>
            <template v-if="ipDetails">
                <div class="row q-py-sm">
                    <label
                        class="col-12 col-sm-3 text-right col-form-label"
                        for="subnet_mask"
                        >Máscara de subred</label
                    >
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.subnet_mask"
                            dense
                            outlined
                            for="subnet_mask"
                            clearable
                            hint="Requerido"
                            :rules="[(val) => !!val || 'Requerido']"
                        />
                    </div>
                </div>
                <div class="row q-py-sm">
                    <label
                        class="col-12 col-sm-3 text-right col-form-label"
                        for="gateway"
                        >Puerta de enlace</label
                    >
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.gateway"
                            dense
                            outlined
                            for="gateway"
                            clearable
                            hint="Requerido"
                            :rules="[(val) => !!val || 'Requerido']"
                        />
                    </div>
                </div>
                <div class="row q-py-sm">
                    <label
                        class="col-12 col-sm-3 text-right col-form-label"
                        for="dns1"
                        >DNS 1</label
                    >
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.dns1"
                            dense
                            outlined
                            for="dns1"
                            clearable
                            hint="Requerido"
                            :rules="[(val) => !!val || 'Requerido']"
                        />
                    </div>
                </div>
                <div class="row q-py-sm">
                    <label
                        class="col-12 col-sm-3 text-right col-form-label"
                        for="dns2"
                        >DNS 2</label
                    >
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.dns2"
                            dense
                            outlined
                            for="dns2"
                            clearable
                        />
                    </div>
                </div>
            </template>
        </template>
    </template>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { getNomenclatures } from "../../../helper/request";
import SelectFormComponent from "../SelectFormComponent.vue";
import UseVlansComponent from "../UseVlansComponent.vue";

defineOptions({
    name: "MgmtIp",
});

const props = defineProps({
    onu: Object,
});

const emits = defineEmits(["update"]);

const formData = ref({});
const vlansOptions = ref([]);
const modeOptions = ref([
    {
        label: "Deshabilitado",
        value: "Inactive",
        url: "inactive",
    },
    {
        label: "IP estático",
        value: "Static IP",
        url: "static_ip",
    },
    {
        label: "DHCP",
        value: "DHCP",
        url: "dhcp",
    },
]);

const loading = ref(false);
const ipDetails = ref(false);

onMounted(() => {
    loadNomenclatures();
    const obj = props.onu;
    let {
        mgmt_ip_mode,
        mgmt_ip_vlan,
        mgmt_ip_svlan,
        mgmt_ip_cvlan,
        mgmt_ip_address,
        mgmt_ip_tag_transform_mode,
        mgmt_ip_subnet_mask,
        mgmt_ip_default_gateway,
        mgmt_ip_dns1,
        mgmt_ip_dns2,
    } = obj;
    formData.value = {
        mode: mgmt_ip_mode ?? "Inactive",
        vlan: mgmt_ip_vlan?.trim() !== "" ? mgmt_ip_vlan : null,
        cvlan: mgmt_ip_cvlan?.trim() !== "" ? mgmt_ip_cvlan : null,
        svlan: mgmt_ip_svlan?.trim() !== "" ? mgmt_ip_svlan : null,
        tag_transform_mode:
            mgmt_ip_tag_transform_mode?.trim() !== ""
                ? mgmt_ip_tag_transform_mode
                : null,
        ipv4_address: mgmt_ip_address?.trim() !== "" ? mgmt_ip_address : null,
        subnet_mask:
            mgmt_ip_subnet_mask?.trim() !== "" ? mgmt_ip_subnet_mask : null,
        gateway:
            mgmt_ip_default_gateway?.trim() !== ""
                ? mgmt_ip_default_gateway
                : null,
        dns1: mgmt_ip_dns1?.trim() !== "" ? mgmt_ip_dns1 : null,
        dns2: mgmt_ip_dns2?.trim() !== "" ? mgmt_ip_dns2 : null,
    };
});

watch(
    formData,
    () => {
        emits("update", {
            mode: modeOptions.value.find((m) => m.value === formData.value.mode)
                .url,
            attr_to_server: getAttributes(),
        });
    },
    { deep: true }
);

const getAttributes = () => {
    let {
        vlan,
        cvlan,
        svlan,
        tag_transform_mode,
        ipv4_address,
        subnet_mask,
        gateway,
        dns1,
        dns2,
        mode,
    } = formData.value;
    if (mode === "Inactive") {
        return null;
    }
    let data = {
        vlan,
        cvlan,
        svlan,
        tag_transform_mode,
    };
    if (mode === "Static IP") {
        data["ipv4_address"] = ipv4_address;
        data["subnet_mask"] = subnet_mask;
        data["gateway"] = gateway;
        data["dns1"] = dns1;
        data["dns2"] = dns2;
    }
    return data;
};

const loadNomenclatures = async () => {
    if (vlansOptions.value.length === 0) {
        loading.value = true;
        const result = await getNomenclatures(["vlans"]);
        loading.value = false;
        if (result) {
            let ports = props.onu.service_ports;
            let selectedVlans = ports.map((p) => p.vlan);
            if (selectedVlans.length > 0) {
                vlansOptions.value = result.vlans.filter(
                    (v) => v.olt_id === props.onu.olt_id
                );
            }
        }
    }
};
</script>
