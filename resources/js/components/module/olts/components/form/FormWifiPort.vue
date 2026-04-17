<template>
    <q-item dense clickable @click="showDialog = true">
        <q-item-section avatar>
            <q-icon name="mdi-plus-circle" color="primary" />
        </q-item-section>
        <q-item-section class="text-primary"> Configurar </q-item-section>
    </q-item>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 700px; max-width: 60vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Configurar puerto wifi {{ object.port }}
                        </div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-form ref="form" greedy>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        >
                            Estado</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-option-group
                                v-model="formData.status"
                                :options="statusOptions"
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                    <template v-if="formData.status === 'Enabled'">
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                            >
                                Modo</label
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
                        <div
                            class="row q-my-sm"
                            v-if="formData.mode === 'Access'"
                        >
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                                for="vlan"
                                >VLAN-ID</label
                            >
                            <div class="col-12 col-sm-9 object-field">
                                <select-form-component
                                    name="vlan"
                                    :model-value="formData.vlan"
                                    :options="vlansOptions"
                                    :required="true"
                                    option-value="vlan"
                                    @change="onChangeVlan"
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                                for="ssid"
                            >
                                SSID</label
                            >
                            <div class="col-12 col-sm-9 object-field">
                                <q-input
                                    v-model="formData.ssid"
                                    dense
                                    outlined
                                    clearable
                                    for="ssid"
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm" v-if="hasSsid">
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                                for="authentication_mode"
                            >
                                Modo autenticacion</label
                            >
                            <div class="col-12 col-sm-9 object-field">
                                <select-form-component
                                    name="authentication_mode"
                                    :model-value="formData.authentication_mode"
                                    :options="authModeOptions"
                                    :filterable="false"
                                    @change="
                                        (name, val) => {
                                            formData[name] = val;
                                        }
                                    "
                                />
                            </div>
                        </div>
                        <template
                            v-if="formData.authentication_mode === 'wpa2'"
                        >
                            <div class="row q-py-sm">
                                <label
                                    class="col-12 col-sm-3 text-right"
                                    for="encrypt"
                                >
                                    Encriptado</label
                                >
                                <div class="col-12 col-sm-9 object-field">
                                    <q-item-label>AES</q-item-label>
                                </div>
                            </div>

                            <div class="row q-pb-sm">
                                <label class="col-12 col-sm-3 text-right">
                                </label>
                                <div class="col-12 col-sm-9 object-field">
                                    <q-item-label
                                        class="text-primary cursor-pointer"
                                        @click="hasPsw = !hasPsw"
                                    >
                                        Cambiar contraseña
                                    </q-item-label>
                                </div>
                            </div>

                            <div class="row q-pb-md q-pt-sm" v-if="hasPsw">
                                <label
                                    class="col-12 col-sm-3 text-right col-form-label"
                                    for="password"
                                >
                                    Nueva contraseña</label
                                >
                                <div class="col-12 col-sm-9 object-field">
                                    <q-input
                                        v-model="formData.password"
                                        dense
                                        outlined
                                        clearable
                                        for="password"
                                    />
                                </div>
                            </div>
                        </template>

                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                            >
                                DHCP</label
                            >
                            <div class="col-12 col-sm-9 object-field">
                                <select-form-component
                                    name="dhcp"
                                    :model-value="formData.dhcp"
                                    :options="dhcpOptions"
                                    :required="true"
                                    :filterable="false"
                                    @change="
                                        (name, val) => {
                                            formData[name] = val;
                                        }
                                    "
                                />
                            </div></div
                    ></template>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <div class="row no-gutter-x">
                    <div class="col col-auto">
                        <q-btn
                            label="Limpiar configuración"
                            no-caps
                            color="danger"
                            @click="clearPort"
                        />
                    </div>
                    <div class="col text-right">
                        <q-btn
                            label="Guardar"
                            no-caps
                            color="primary"
                            class="q-mr-sm"
                            @click="save"
                        />
                        <q-btn
                            label="Cerrar"
                            no-caps
                            @click="showDialog = false"
                            color="grey-7"
                        />
                    </div>
                </div>
            </q-card-actions>
            <q-inner-loading
                :showing="saving"
                label="Procesando, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </q-card>
    </q-dialog>
</template>

<script setup>
import { computed, ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import SelectFormComponent from "./SelectFormComponent.vue";
import { getNomenclatures } from "../../helper/request";

defineOptions({
    name: "FormWifiPort",
});

const props = defineProps({
    object: Object,
    onu: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({});
const vlansOptions = ref([]);
const statusOptions = ref([
    {
        label: "Habilitado",
        value: "Enabled",
    },
    {
        label: "Apagar puerto",
        value: "Disabled",
    },
]);
const modeOptions = ref([
    {
        label: "LAN",
        value: "LAN",
    },
    {
        label: "Access",
        value: "Access",
    },
    // {
    //     label: "Hybrid",
    //     value: "Hybrid",
    // },
    // {
    //     label: "Trunk",
    //     value: "Trunk",
    // },
    // {
    //     label: "Transparent",
    //     value: "Transparent",
    // },
]);

const dhcpOptions = ref([
    {
        label: "No control",
        value: "No control",
    },
    // {
    //     label: "From ISP",
    //     value: "From ISP",
    // },
    // {
    //     label: "From ONU",
    //     value: "From ONU",
    // },
    // {
    //     label: "Forbidden",
    //     value: "Forbidden",
    // },
]);

const authModeOptions = ref([
    {
        label: "WPA2",
        value: "wpa2",
        send: "WPA2",
    },
    {
        label: "Open-system",
        value: "open_system",
        send: "Open-system",
    },
]);

const loading = ref(false);
const saving = ref(false);
const hasPsw = ref(false);

const beforeShow = async () => {
    const obj = props.object;
    let { port, dhcp, ssid, password, auth_mode, vlan, admin_state, mode } =
        obj;
    formData.value = {
        dhcp,
        ssid,
        password,
        vlan,
        mode,
        wifi_port: port,
        authentication_mode: auth_mode,
        status: admin_state,
    };
    loadNomenclatures();
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
                    (v) =>
                        selectedVlans.includes(v.vlan) &&
                        v.olt_id === props.onu.olt_id
                );
            }
        }
    }
};

const getAttributes = (mode) => {
    let { dhcp, ssid, password, vlan, wifi_port, authentication_mode } =
        formData.value;
    let send = authModeOptions.value.find(
        (a) => a.value === authentication_mode
    );
    let data = {
        dhcp,
        ssid,
        password,
        vlan,
        wifi_port,
        authentication_mode: hasSsid.value ? send.send : "wpa2",
    };
    switch (mode) {
        case "access":
            data["vlan"] = formData.value.vlan;
            break;
        default:
            break;
    }
    return data;
};

const onChangeVlan = (name, val) => {
    formData.value[name] = val;
    let selectedAllowed = formData.value.allowed_vlans;
    if (selectedAllowed && selectedAllowed.trim() !== "") {
        selectedAllowed = selectedAllowed.split(",").filter((s) => s !== val);
        formData.value.allowed_vlans = selectedAllowed?.join(",") ?? null;
    }
};

const hasSsid = computed(() => {
    return formData.value.ssid && formData.value.ssid?.trim() !== "";
});

const clearPort = () => {
    formData.value.password = null;
    formData.value.ssid = null;
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            let mode = formData.value.mode.toLowerCase();
            await axios
                .post(`/olts/onus/configure-wifi-port/${props.onu.id}`, {
                    mode,
                    status: formData.value.status,
                    attr_to_server: getAttributes(mode),
                })
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("Puerto configurado correctamente", "success");
                        showDialog.value = false;
                    }
                })
                .catch((err) => {
                    message(
                        "Ha ocurrido un error al procesar la solicitud",
                        "error"
                    );
                })
                .finally(() => {
                    saving.value = false;
                });
        } else {
            errorValidation();
        }
    });
};
</script>
