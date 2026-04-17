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
                            Configurar puerto ethernet {{ object.port }}
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
                            v-if="['Access', 'Hybrid'].includes(formData.mode)"
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
                        <div
                            class="row q-my-sm"
                            v-if="['Trunk', 'Hybrid'].includes(formData.mode)"
                        >
                            <label
                                class="col-12 col-sm-3 text-right col-form-label"
                                for="vlan"
                                >VLANs permitidas</label
                            >
                            <div class="col-12 col-sm-9 object-field">
                                <select-form-component
                                    name="allowed_vlans"
                                    :model-value="
                                        formData.allowed_vlans &&
                                        formData.allowed_vlans.trim() !== ''
                                            ? formData.allowed_vlans
                                                  .trim()
                                                  .split(',')
                                            : null
                                    "
                                    :options="
                                        vlansOptions.filter(
                                            (v) => v.vlan !== formData.vlan
                                        )
                                    "
                                    :required="true"
                                    :multiple="true"
                                    option-value="vlan"
                                    @change="
                                        (name, val) => {
                                            formData[name] =
                                                val?.join(',') ?? null;
                                        }
                                    "
                                />
                            </div>
                        </div>
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
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormEthPort",
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
    {
        label: "Hybrid",
        value: "Hybrid",
    },
    {
        label: "Trunk",
        value: "Trunk",
    },
    {
        label: "Transparent",
        value: "Transparent",
    },
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

const saving = ref(false);
const loading = ref(false);

const beforeShow = async () => {
    loadNomenclatures();
    const obj = props.object;
    let { dhcp, mode, vlan, admin_state, port, allowed_vlans } = obj;
    formData.value = {
        dhcp,
        mode,
        vlan,
        allowed_vlans,
        ethernet_port: port,
        status: admin_state,
    };
};

const getAttributes = (mode) => {
    let { ethernet_port, dhcp } = formData.value;
    let data = {
        ethernet_port,
        dhcp,
    };
    switch (mode) {
        case "access":
            data["vlan"] = formData.value.vlan;
            break;
        case "hybrid":
            data["vlan"] = formData.value.vlan;
            data["allowed_vlans"] = formData.value.allowed_vlans;
            break;
        case "trunk":
            data["allowed_vlans"] = formData.value.allowed_vlans;
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

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(`/olts/onus/configure-ethernet-port/${props.onu.id}`, {
                    mode: formData.value.mode.toLowerCase(),
                    status: formData.value.status,
                    attr_to_server: getAttributes(
                        formData.value.mode.toLowerCase()
                    ),
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
