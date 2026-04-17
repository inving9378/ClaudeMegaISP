<template>
    <div class="row q-my-sm">
        <label class="col-12 col-sm-3 text-right col-form-label">
            Modo WAN</label
        >
        <div class="col-12 col-sm-9 object-field">
            <q-option-group
                v-model="formData.wan_mode"
                :options="modeOptions"
                color="primary"
                inline
            />
        </div>
    </div>
    <template v-if="formData.wan_mode !== 'Setup via ONU webpage'">
        <div class="row q-my-sm">
            <label class="col-12 col-sm-3 text-right col-form-label">
                Método de configuración</label
            >
            <div class="col-12 col-sm-9 object-field">
                <q-option-group
                    v-model="formData.configuration_method"
                    :options="methodOptions"
                    color="primary"
                    inline
                />
            </div>
        </div>
        <div class="row q-my-sm">
            <label class="col-12 col-sm-3 text-right col-form-label">
                Protocolo IP</label
            >
            <div class="col-12 col-sm-9 object-field">
                <q-option-group
                    v-model="formData.ip_protocol"
                    :options="protocolOptions"
                    color="primary"
                    inline
                />
            </div>
        </div>
        <template v-if="formData.ip_protocol === 'ipv4ipv6'">
            <div class="row q-my-sm">
                <label class="col-12 col-sm-3 text-right col-form-label">
                    Dirección IPV6</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-option-group
                        v-model="formData.ipv6_address_mode"
                        :options="ipv6AddressOptions"
                        color="primary"
                        inline
                    />
                </div>
            </div>
            <template v-if="formData.ipv6_address_mode === 'Static'">
                <div class="row q-my-sm">
                    <label class="col-12 col-sm-3 text-right col-form-label">
                    </label>
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.ipv6_address"
                            dense
                            outlined
                            clearable
                            :rules="[(val) => !!val || 'Requerido']"
                        />
                    </div>
                </div>
                <div class="row q-my-sm">
                    <label
                        class="col-12 col-sm-3 text-right col-form-label"
                        for="ipv6_gateway"
                    >
                        Puerta IPV6</label
                    >
                    <div class="col-12 col-sm-9 object-field">
                        <q-input
                            v-model="formData.ipv6_gateway"
                            dense
                            outlined
                            clearable
                            for="ipv6_gateway"
                            :rules="[(val) => !!val || 'Requerido']"
                        />
                    </div>
                </div>
            </template>
            <div class="row q-my-sm">
                <label class="col-12 col-sm-3 text-right col-form-label">
                    Prefijo IPV6</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-option-group
                        v-model="formData.ipv6_prefix_delegation_mode"
                        :options="ipv6PrefixOptions"
                        color="primary"
                        inline
                    />
                </div>
            </div>

            <div
                class="row q-my-sm"
                v-if="formData.ipv6_prefix_delegation_mode === 'Static'"
            >
                <label class="col-12 col-sm-3 text-right col-form-label">
                </label>
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.ipv6_prefix_address"
                        dense
                        outlined
                        clearable
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>
        </template>

        <template v-if="formData.wan_mode === 'Static IP'">
            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="ipv4_address"
                >
                    Dirección IPV4</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.ipv4_address"
                        dense
                        outlined
                        clearable
                        for="ipv4_address"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>

            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="subnet_mask"
                >
                    Máscara de subred</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.subnet_mask"
                        dense
                        outlined
                        clearable
                        for="subnet_mask"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>

            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="gateway"
                >
                    Puerta predeterminada</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.gateway"
                        dense
                        outlined
                        clearable
                        for="gateway"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>
            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="dns1"
                >
                    DNS1</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.dns1"
                        dense
                        outlined
                        clearable
                        for="dns1"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>
            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="dns2"
                >
                    DNS2</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.dns2"
                        dense
                        outlined
                        clearable
                        for="dns2"
                    />
                </div>
            </div>
        </template>

        <template v-if="formData.wan_mode === 'PPPoE'"
            ><div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="username"
                >
                    Usuario</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.username"
                        dense
                        outlined
                        clearable
                        for="username"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div>
            </div>
            <div class="row q-my-sm">
                <label
                    class="col-12 col-sm-3 text-right col-form-label"
                    for="password"
                >
                    Contraseña</label
                >
                <div class="col-12 col-sm-9 object-field">
                    <q-input
                        v-model="formData.password"
                        dense
                        outlined
                        clearable
                        for="password"
                        :rules="[(val) => !!val || 'Requerido']"
                    />
                </div></div
        ></template>
    </template>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "WanMode",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const formData = ref({});
const modeOptions = ref([
    {
        label: "Setup via ONU webpage",
        value: "Setup via ONU webpage",
        url: "setup_via_onu_webpage",
    },
    {
        label: "DHCP",
        value: "DHCP",
        url: "dhcp",
    },
    {
        label: "IP estático",
        value: "Static IP",
        url: "static_ip",
    },
    {
        label: "PPPoE",
        value: "PPPoE",
        url: "pppoe",
    },
]);

const methodOptions = ref([
    {
        label: "OMCI",
        value: "OMCI",
    },
    {
        label: "TR069",
        value: "TR069",
    },
]);

const protocolOptions = ref([
    {
        label: "IPv4",
        value: "ipv4",
    },
    {
        label: "Dual stack IPv4/IPv6",
        value: "ipv4ipv6",
    },
]);

const ipv6AddressOptions = ref([
    {
        label: "DHCPv6",
        value: "DHCPv6",
    },
    {
        label: "Auto",
        value: "Auto",
    },
    {
        label: "Estático",
        value: "Static",
    },
    {
        label: "Ninguno",
        value: "None",
    },
]);

const ipv6PrefixOptions = ref([
    {
        label: "DHCPv6-PD",
        value: "DHCPv6",
    },
    {
        label: "Estático",
        value: "Static",
    },
    {
        label: "Ninguno",
        value: "None",
    },
]);

onMounted(() => {
    const obj = props.onu;
    let {
        wan_mode,
        configuration_method,
        ipv6_address_mode,
        ipv6_address,
        ip_protocol,
        ipv6_gateway,
        ipv6_prefix_delegation_mode,
        ipv6_prefix_address,
        ip_address,
        subnet_mask,
        default_gateway,
        dns1,
        dns2,
        username,
        password,
    } = obj;

    formData.value = {
        wan_mode: wan_mode ?? "Setup via ONU webpage",
        configuration_method: configuration_method ?? "OMCI",
        ip_protocol: ip_protocol ?? "ipv4",
        ipv6_address_mode: ipv6_address_mode ?? "None",
        ipv6_prefix_delegation_mode: ipv6_prefix_delegation_mode ?? "None",
        ipv6_address,
        ipv6_gateway,
        ipv6_prefix_address,
        ipv4_address: ip_address,
        gateway: default_gateway,
        subnet_mask,
        dns1,
        dns2,
        username,
        password,
    };
});

watch(
    formData,
    (n) => {
        emits("update", {
            mode: modeOptions.value.find((m) => m.value === n.wan_mode).url,
            attr_to_server: getAttributes(),
        });
    },
    { deep: true }
);

const getAttributes = () => {
    let {
        configuration_method,
        ip_protocol,
        ipv6_address_mode,
        ipv6_address,
        ipv6_gateway,
        ipv6_prefix_delegation_mode,
        ipv6_prefix_address,
        ipv4_address,
        subnet_mask,
        gateway,
        dns1,
        dns2,
        username,
        password,
        wan_mode,
    } = formData.value;
    let def = {
            configuration_method,
            ip_protocol,
            ipv6_address_mode,
            ipv6_address,
            ipv6_gateway,
            ipv6_prefix_delegation_mode,
            ipv6_prefix_address,
        },
        data = {};

    switch (wan_mode) {
        case "DHCP":
            data = def;
            break;
        case "Static IP":
            data = {
                ipv4_address,
                subnet_mask,
                gateway,
                dns1,
                dns2,
                ...def,
            };
            break;
        case "PPPoE":
            data = {
                username,
                password,
                ...def,
            };
            break;
        default:
            data = null;
            break;
    }
    return data;
};
</script>
