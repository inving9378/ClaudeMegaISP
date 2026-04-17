<template>
    <div class="row">
        <div class="col col-auto">
            <q-item-label
                class="cursor-pointer text-primary"
                @click="showDialog = true"
                v-if="hasPermission?.data.canView('onu_edit')"
            >
                {{ onu[field] }}
            </q-item-label>
            <q-item-label v-else>
                {{ onu[field] }}
            </q-item-label>
        </div>
        <template v-if="field === 'wan_mode'">
            <div class="col col-auto" v-if="loadingIp">
                <q-spinner-ios color="primary" size="xs" />
            </div>
            <template
                v-if="
                    onu.ip_address && onu.wan_mode !== 'Static IP' && !loadingIp
                "
                ><div class="col col-auto" style="padding: 0">-</div>
                <div class="col col-auto">
                    <ip-label :ip="onu.ip_address" /></div
            ></template>
        </template>
    </div>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 750px; max-width: 85vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Actualizar modo ONU</div>
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
                    <vlan
                        :onu="onu"
                        :label="routing ? 'WAN VLAN-ID' : 'VLAN-ID'"
                        :vlans="vlans"
                        :loading="loading"
                        @update="
                            (val) => {
                                formData.vlan = val;
                            }
                        "
                    />
                    <p class="q-px-md text-primary" v-if="routing">
                        Tras cambiar el ID de la VLAN WAN, compruebe la
                        configuración de los puertos Ethernet y actualice las
                        VLAN según sea necesario.
                    </p>
                    <p class="q-px-md text-primary" v-else>
                        Tras cambiar el ID de la VLAN, compruebe la
                        configuración de los puertos Ethernet y actualice las
                        VLAN según sea necesario.
                    </p>
                    <onu-mode
                        :onu="onu"
                        @update="
                            (val) => {
                                formData.onuMode = val;
                            }
                        "
                    />
                    <wan-mode
                        :onu="onu"
                        @update="
                            (val) => {
                                formData['wanMode'] = val;
                            }
                        "
                        v-if="routing"
                    />
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
import { computed, onMounted, onUnmounted, ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import IpLabel from "./IpLabel.vue";
import axios from "axios";
import { getNomenclatures, getOLTData } from "../../helper/request";

import Vlan from "./components/Vlan.vue";
import WanMode from "./components/WanMode.vue";
import OnuMode from "./components/OnuMode.vue";
import Swal from "sweetalert2";

defineOptions({
    name: "FormUpdateMode",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
    field: {
        type: String,
        default: "wan_mode",
    },
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({
    wanMode: null,
    onuMode: null,
    vlan: null,
});

const saving = ref(false);
const loadingIp = ref(false);

let timer = null;

const vlans = ref([]);
const loading = ref(false);

onMounted(() => {
    getIpAddress();

    timer = setInterval(() => {
        getIpAddress();
    }, 30000);
});

onUnmounted(() => {
    clearInterval(timer);
});

const beforeShow = async () => {
    if (vlans.value.length === 0) {
        loading.value = true;
        let result = await getNomenclatures(["vlans"]);
        loading.value = false;
        if (result) {
            vlans.value = result.vlans.filter(
                (v) => v.olt_id === props.onu.olt_id
            );
        }
    }
};

const routing = computed(() => {
    let temp = formData.value?.onuMode?.attr_to_server?.onu_mode === "Routing";
    if (!temp) {
        formData.value.wanMode = null;
    }
    return temp;
});

const getIpAddress = async () => {
    if (!props.onu.ip_address) {
        loadingIp.value = true;
        const result = await getOLTData(
            `/olts/onus/get-ip-address/${props.onu.id}`
        );
        loadingIp.value = false;
        if (result && result.success) {
            let { id, ip_address } = result.onu;
            emits("update", {
                id,
                ip_address,
            });
        }
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            let currentMode = props.onu.mode,
                newMode = formData.value.onuMode.attr_to_server.onu_mode;
            if (
                (currentMode === "Bridging" && newMode === "Routing") ||
                (currentMode === "Routing" && newMode === "Bridging")
            ) {
                confirm(newMode);
            } else {
                setMode();
            }
        } else {
            errorValidation();
        }
    });
};

const confirm = (mode) => {
    let vlan = vlans.value.find(
        (v) => v.vlan === formData.value.vlan.attr_to_server.vlan
    );
    Swal.fire({
        title: "¿Seguro que deseas cambiar el modo de esta onu?",
        text:
            mode === "Routing"
                ? "Al cambiar el modo de la ONU a Enrutamiento, los puertos Ethernet se configurarán en modo LAN. Los dispositivos conectados a esta ONU recibirán direcciones IP privadas de la misma. ¿Está seguro de que desea continuar?"
                : `Cambiar el modo ONU a Puente restablecerá la configuración de los puertos Ethernet. Todos los puertos Ethernet usarán la VLAN-ID ${vlan?.label} y se permitirán los paquetes DHCP desde el lado del ISP. ¿Está seguro de que desea continuar?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            setMode();
        }
    });
};

const setMode = async () => {
    saving.value = true;
    await axios
        .post(`/olts/onus/update-mode/${props.onu.id}`, formData.value)
        .then((res) => {
            let response = res.data;
            if (!response.success) {
                message(response.error ?? response.message, "error");
            } else {
                emits("update", response.onu);
                message("Modo WAN configurado correctamente", "success");
                showDialog.value = false;
            }
        })
        .catch((err) => {
            message("Ha ocurrido un error al procesar la solicitud", "error");
        })
        .finally(() => {
            saving.value = false;
        });
};
</script>
