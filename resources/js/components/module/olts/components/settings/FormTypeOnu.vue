<template>
    <q-btn icon="add" color="primary" @click="showDialog = true">
        <q-tooltip>Adicionar</q-tooltip>
    </q-btn>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 500px; max-width: 60vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Adicionar tipo de ONU</div>
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
                            for="pon_type"
                            >Tipo PON</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-option-group
                                v-model="formData.pon_type"
                                :options="ponTypes"
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="name"
                            >Tipo ONU</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.name"
                                dense
                                outlined
                                for="name"
                                clearable
                                hint="Requerido"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="ethernet_ports_nr"
                            >Puertos Eth</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <select-form-component
                                name="ethernet_ports_nr"
                                class="input-select"
                                :model-value="formData.ethernet_ports_nr"
                                :options="ethernetPortsOptions"
                                :required="true"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="wifi_ssids_nr"
                            >WiFi SSIDs</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <select-form-component
                                name="wifi_ssids_nr"
                                class="input-select"
                                :model-value="formData.wifi_ssids_nr"
                                :options="wifiSsidsOptions"
                                :required="true"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="voip_ports_nr"
                            >Puertos VoIP</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <select-form-component
                                name="voip_ports_nr"
                                class="input-select"
                                :model-value="formData.voip_ports_nr"
                                :options="voipPortsOptions"
                                :required="true"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox v-model="catv" label="CATV" />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox
                                v-model="allowCustomProfiles"
                                label="Permitir perfil personalizado"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="capability"
                            >Capacidad</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-option-group
                                v-model="formData.capability"
                                :options="capabilityOptions"
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="save"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
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
import { onMounted, ref, watch } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import SelectFormComponent from "../form/SelectFormComponent.vue";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";

defineOptions({
    name: "FormTypeOnu",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["reload"]);

const showDialog = ref(false);
const catv = ref(false);
const allowCustomProfiles = ref(true);

const form = ref("form");
const formData = ref({
    name: null,
    pon_type: "gpon",
    ethernet_ports_nr: 4,
    wifi_ssids_nr: 0,
    voip_ports_nr: 0,
    catv: 0,
    allow_custom_profiles: 1,
    capability: null,
});
const zoneOptions = ref([]);
const loading = ref(false);
const saving = ref(false);

const ponTypes = [
    {
        label: "GPON",
        value: "gpon",
    },
    {
        label: "EPON",
        value: "epon",
    },
];

let ethernetPorts = [1, 2, 3, 4, 5, 8, 16, 24];
let wifiSsids = [0, 1, 2, 3, 4, 5, 6, 7, 8];
let voipPorts = [0, 1, 2];

const ethernetPortsOptions = ref([]);
const wifiSsidsOptions = ref([]);
const voipPortsOptions = ref([]);

const capabilityOptions = [
    {
        label: "Bridging",
        value: "Bridging",
    },
    {
        label: "Bridging/Routing",
        value: "Bridging/Routing",
    },
];

onMounted(() => {
    ethernetPorts.forEach((p) => {
        ethernetPortsOptions.value.push({
            label: p,
            value: p,
        });
    });
    wifiSsids.forEach((p) => {
        wifiSsidsOptions.value.push({
            label: p,
            value: p,
        });
    });
    voipPorts.forEach((p) => {
        voipPortsOptions.value.push({
            label: p,
            value: p,
        });
    });
});

watch(catv, (n) => {
    formData.value.catv = n ? 1 : 0;
});

watch(allowCustomProfiles, (n) => {
    formData.value.allow_custom_profiles = n ? 1 : 0;
});

const beforeShow = async () => {
    catv.value = false;
    allowCustomProfiles.value = true;
    formData.value = {
        name: null,
        pon_type: "gpon",
        ethernet_ports_nr: 4,
        wifi_ssids_nr: 0,
        voip_ports_nr: 0,
        catv: 0,
        allow_custom_profiles: 1,
        capability: "Bridging/Routing",
    };
    if (zoneOptions.value.length === 0) {
        loading.value = true;
        let result = await getNomenclatures(["zones"]);
        loading.value = false;
        if (result) {
            zoneOptions.value = result.zones;
            odbsOptions.value = result.odbs;
        }
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post("/olts/settings/type-onus/store", formData.value)
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("reload", true);
                        message(
                            "Tipo de ONU agregado correctamente",
                            "success"
                        );
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
