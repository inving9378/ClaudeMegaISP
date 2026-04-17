<template>
    <q-item dense clickable @click="showDialog = true">
        <q-item-section avatar>
            <q-icon name="mdi-plus-circle" color="primary" />
        </q-item-section>
        <q-item-section class="text-primary"> Configurar </q-item-section>
    </q-item>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 600px; max-width: 70vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Configurar servicio puerto {{ object.service_port }}
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
                            class="col-12 col-sm-4 text-right self-end"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <q-checkbox
                                v-model="useSvlan"
                                label="Usar SVLAN-ID"
                                @update:model-value="
                                    () => {
                                        formData.svlan = null;
                                    }
                                "
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm" v-if="useSvlan">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="svlan"
                            >SVLAN-ID</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="svlan"
                                :model-value="formData.svlan"
                                :options="vlans"
                                :required="true"
                                :loading="loading"
                                option-value="vlan"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <q-checkbox
                                v-model="useCvlan"
                                label="Usar CVLAN-ID"
                                @update:model-value="formData.cvlan = null"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm" v-if="useCvlan">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="cvlan"
                            >CVLAN-ID</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="cvlan"
                                :model-value="formData.cvlan"
                                :options="vlans"
                                :required="true"
                                :loading="loading"
                                option-value="vlan"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="vlan"
                            >ID de VLAN del usuario</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="vlan"
                                :model-value="formData.vlan"
                                :options="vlans"
                                :required="true"
                                :loading="loading"
                                option-value="vlan"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="tag_transform_mode"
                            >Modo transformación</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="tag_transform_mode"
                                :model-value="formData.tag_transform_mode"
                                :options="tagTransformModes"
                                :required="true"
                                :filterable="false"
                                :loading="loading"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="download_speed_profile_name"
                            >Velocidad de descarga</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="download_speed_profile_name"
                                option-label="name"
                                option-value="name"
                                :loading="loading"
                                :model-value="
                                    formData.download_speed_profile_name
                                "
                                :options="
                                    speedProfiles.filter(
                                        (sp) => sp.direction === 'download'
                                    )
                                "
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="upload_speed_profile_name"
                            >Velocidad de subida</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="upload_speed_profile_name"
                                option-label="name"
                                option-value="name"
                                :loading="loading"
                                :model-value="
                                    formData.upload_speed_profile_name
                                "
                                :options="
                                    speedProfiles.filter(
                                        (sp) => sp.direction === 'upload'
                                    )
                                "
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <div class="row no-gutter-x">
                    <div class="col col-auto">
                        <q-btn
                            label="Eliminar"
                            no-caps
                            color="negative"
                            class="q-mr-sm"
                            @click="remove"
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
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";
import Swal from "sweetalert2";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormSpeedProfile",
});

const props = defineProps({
    object: Object,
    onu: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({});

const useSvlan = ref(false);
const useCvlan = ref(false);
const vlans = ref([]);
const speedProfiles = ref([]);

const saving = ref(false);
const loading = ref(false);

const tagTransformModes = [
    {
        label: "default",
        value: "default",
    },
    {
        label: "translate",
        value: "translate",
    },
    {
        label: "translate-and-add",
        value: "translate-and-add",
    },
    {
        label: "transparent",
        value: "transparent",
    },
];

const beforeShow = async () => {
    if (vlans.value.length === 0) {
        loading.value = true;
        const result = await getNomenclatures(["vlans", "speed_profiles"]);
        loading.value = false;
        if (result) {
            vlans.value = result.vlans.filter(
                (v) => v.olt_id === props.onu.olt_id
            );
            speedProfiles.value = result.speed_profiles;
        }
    }
    const obj = props.object;
    if (obj.cvlan) {
        useCvlan.value = true;
    }
    if (obj.svlan) {
        useSvlan.value = true;
    }
    formData.value = {
        service_port: obj.service_port ?? null,
        cvlan: obj.cvlan ?? null,
        svlan: obj.svlan ?? null,
        tag_transform_mode: obj.tag_transform_mode ?? null,
        vlan: obj.vlan ?? null,
        upload_speed_profile_name: obj.upload_speed ?? null,
        download_speed_profile_name: obj.download_speed ?? null,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/onus/update-service-port/${props.onu.id}`,
                    formData.value
                )
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("ONU actualizada correctamente", "success");
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

const remove = () => {
    Swal.fire({
        title: "¿Seguro que deseas eliminar este servicio?",
        text: "No podrás deshacer esta acción.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            saving.value = true;
            await axios
                .post(`/olts/onus/update-attached-vlans/${props.onu.id}`, {
                    add_vlans: [],
                    remove_vlans: [props.object.vlan],
                })
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("Servicio eliminado correctamente", "success");
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
    });
};
</script>
