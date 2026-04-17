<template>
    <div class="row">
        <div class="col col-auto">
            <q-item-label
                class="cursor-pointer text-primary"
                @click="showDialog = true"
                v-if="hasPermission?.data.canView('onu_edit')"
            >
                {{ translate(onu[field]) }}
            </q-item-label>
            <q-item-label v-else>
                {{ translate(onu[field]) }}
            </q-item-label>
        </div>
        <template v-if="field === 'onu_mgmt_ip'">
            <div class="col col-auto" v-if="loadingMgmIp">
                <q-spinner-ios color="primary" size="xs" />
            </div>
            <template v-if="onu.mgmt_ip_address && !loadingMgmIp"
                ><div class="col col-auto" style="padding: 0">-</div>
                <div class="col col-auto">
                    <ip-label :ip="onu.mgmt_ip_address" /></div
            ></template>
        </template>
    </div>

    <q-dialog v-model="showDialog" persistent>
        <q-card style="width: 700px; max-width: 80vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Actualizar Ip de gestión y VoIP
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
                    <tr-069
                        :onu="onu"
                        @update="
                            (val) => {
                                formData.tr069 = val;
                            }
                        "
                    />
                    <mgmt-ip
                        :onu="onu"
                        @update="
                            (val) => {
                                formData.mgmtIp = val;
                            }
                        "
                    />
                    <vo-ip-service
                        :onu="onu"
                        @update="
                            (val) => {
                                formData.voIp = val;
                            }
                        "
                    />
                </q-form>
                <p class="q-px-md">
                    Después de habilitar el servicio VoIP, vaya a la
                    configuración de puertos VoIP y asigne números de teléfono
                    VoIP como desee.
                </p>
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
import { onMounted, onUnmounted, ref } from "vue";
import { getOLTData } from "../../helper/request";
import IpLabel from "./IpLabel.vue";
import Tr069 from "./components/Tr069.vue";
import MgmtIp from "./components/MgmtIp.vue";
import VoIpService from "./components/VoIpService.vue";
import { message } from "../../../../../helpers/toastMsg";

import { useUtils } from "../../../../../composables/useUtils";

defineOptions({
    name: "FormMgmtIpAndVoIp",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
    field: {
        type: String,
        default: "onu_mgmt_ip",
    },
});

const emits = defineEmits(["update"]);

const { translate } = useUtils();

const showDialog = ref(false);

const form = ref("form");

const formData = ref({
    tr069: null,
    voIp: null,
    mgmtIp: null,
});

const saving = ref(false);
const loadingMgmIp = ref(false);

let timer = null;

onMounted(() => {
    getMgmTIp();
    timer = setInterval(() => {
        getMgmTIp();
    }, 30000);
});

onUnmounted(() => {
    clearInterval(timer);
});

const getMgmTIp = async () => {
    let { id, mgmt_ip_mode, mgmt_ip_address } = props.onu;
    if (
        mgmt_ip_mode !== "Inactive" &&
        !mgmt_ip_address &&
        props.field === "onu_mgmt_ip"
    ) {
        loadingMgmIp.value = true;
        const result = await getOLTData(`/olts/onus/get-mgmt-ip/${id}`);
        loadingMgmIp.value = false;
        if (result && result.success) {
            let { id, mgmt_ip_address } = result.onu;
            emits("update", {
                id,
                mgmt_ip_address,
            });
        }
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            if (
                formData.value.mgmtIp.mode === "inactive" &&
                formData.value.tr069.status === "enable"
            ) {
                message(
                    "Para que TR069 funcione, debe habilitar la IP de administración, preferiblemente con una IP estática",
                    "error"
                );
            } else {
                saving.value = true;
                await axios
                    .post(
                        `/olts/onus/update-mgmt-and-vo-ip/${props.onu.id}`,
                        formData.value
                    )
                    .then((res) => {
                        let response = res.data;
                        if (!response.success) {
                            message(
                                response.error ?? response.message,
                                "error"
                            );
                            if (response.onu) {
                                emits("update", response.onu);
                            }
                        } else {
                            emits("update", response.onu);
                            message(
                                "Servicio configurado correctamente",
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
            }
        } else {
            errorValidation();
        }
    });
};
</script>
