<template>
    <q-btn icon="add" color="primary" @click="showDialog = true">
        <q-tooltip>Adicionar</q-tooltip>
    </q-btn>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 700px; max-width: 80vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Adicionar VLAN</div>
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
                            for="vlan"
                            >VLAN</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model.number="formData.vlan"
                                type="number"
                                dense
                                outlined
                                for="vlan"
                                clearable
                                hint="Requerido"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="description"
                            >Descripción</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.description"
                                dense
                                outlined
                                for="description"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox
                                v-model="for_iptv"
                                label="VLAN de multidifusión, utilizada para IPTV"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox
                                v-model="for_mgmt_voip"
                                label="VLAN de administración VoIP"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox
                                v-model="dhcp_snooping"
                                label="VLAN de inspección de DHCP"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 object-field">
                            <q-checkbox
                                v-model="lan_to_lan"
                                label="VLAN de comunicación directa entre ONU (conocida como LAN a LAN)"
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
import { ref, watch } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";

defineOptions({
    name: "FormVlan",
});

const props = defineProps({
    object: Object,
    olt: Object,
});

const emits = defineEmits(["reload"]);

const showDialog = ref(false);
const for_iptv = ref(false);
const for_mgmt_voip = ref(true);
const dhcp_snooping = ref(false);
const lan_to_lan = ref(false);

const form = ref("form");
const formData = ref({
    vlan: null,
    description: null,
    for_iptv: 0,
    for_mgmt_voip: 1,
    dhcp_snooping: 0,
    lan_to_lan: 0,
});

const saving = ref(false);

watch(for_iptv, (n) => {
    if (n) {
        for_mgmt_voip.value = false;
        formData.value.for_iptv = 1;
    } else {
        formData.value.for_iptv = 0;
    }
});

watch(for_mgmt_voip, (n) => {
    if (n) {
        for_iptv.value = false;
        formData.value.for_mgmt_voip = 1;
    } else {
        formData.value.for_mgmt_voip = 0;
    }
});

watch(dhcp_snooping, (n) => {
    formData.value.dhcp_snooping = n ? 1 : 0;
});

watch(lan_to_lan, (n) => {
    formData.value.lan_to_lan = n ? 1 : 0;
});

const beforeShow = async () => {
    for_iptv.value = false;
    for_mgmt_voip.value = false;
    dhcp_snooping.value = false;
    lan_to_lan.value = false;
    formData.value = {
        vlan: null,
        description: null,
        for_iptv: 0,
        for_mgmt_voip: 1,
        dhcp_snooping: 0,
        lan_to_lan: 0,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/settings/olts/${props.olt}/vlans/store`,
                    formData.value
                )
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("reload", true);
                        message("VLAN agregada correctamente", "success");
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
