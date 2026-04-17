<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        v-if="hasPermission?.data.canView('onu_edit')"
        >{{ defaultValue(object[field]) }}</q-item-label
    >
    <q-item-label v-else>{{ defaultValue(object[field]) }}</q-item-label>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 600px; max-width: 70vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Actualizar detalles de ubicación
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
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="zone"
                            >Zona</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="zone"
                                option-label="name"
                                option-value="name"
                                :model-value="formData.zone"
                                :required="true"
                                :options="zoneOptions"
                                :loading="loading"
                                @change="
                                    (name, val) => {
                                        formData[name] = val;
                                        formData.odb = null;
                                        formData.odb_port = null;
                                    }
                                "
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label class="col-12 col-sm-4 text-right col-form-label"
                            >ODB (Divisor)</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="odb"
                                option-value="name"
                                :model-value="formData.odb"
                                :options="
                                    formData.zone
                                        ? odbsOptions.filter(
                                              (o) =>
                                                  o.zone_name === formData.zone
                                          )
                                        : []
                                "
                                :set-default="true"
                                :loading="loading"
                                @change="
                                    (name, val) => {
                                        formData[name] = val;
                                        formData.odb_port = null;
                                    }
                                "
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="odb"
                            >Puerto ODB</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="odb_port"
                                :model-value="formData.odb_port"
                                :options="formData.odb ? odbsPorts : []"
                                :set-default="true"
                                :loading="loading"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="name"
                            >Nombre</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.name"
                                dense
                                outlined
                                for="name"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="address_or_comment"
                            >Dirección o comentario</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.address_or_comment"
                                dense
                                outlined
                                for="address_or_comment"
                                clearable
                                autogrow
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="contact"
                            >Contacto</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.contact"
                                dense
                                outlined
                                for="contact"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="latitude"
                            >Latitud</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.latitude"
                                dense
                                outlined
                                for="latitude"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="longitude"
                            >Longitud</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.longitude"
                                dense
                                outlined
                                for="longitude"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <q-checkbox
                                v-model="useGps"
                                label="Usar mapa"
                                style="width: 90%"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm" v-if="useGps">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <location-component
                                :coordinates="{
                                    lat: formData.latitude,
                                    lng: formData.longitude,
                                }"
                                @change-location="
                                    (l) => {
                                        formData.latitude = l.lat;
                                        formData.longitude = l.lng;
                                    }
                                "
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
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
import { computed, onMounted, ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { useUtils } from "../../../../../composables/useUtils";

import LocationComponent from "./LocationComponent.vue";
import { getNomenclatures } from "../../helper/request";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormChangeLocation",
});

const props = defineProps({
    object: Object,
    hasPermission: Object,
    field: {
        type: String,
        default: "olt_name",
    },
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);
const form = ref("form");
const formData = ref({});
const useGps = ref(false);
const saving = ref(false);
const loading = ref(false);

const zoneOptions = ref([]);
const odbsPorts = ref([]);
const odbsOptions = ref([]);

const { defaultValue } = useUtils();

onMounted(() => {
    for (let i = 1; i <= 16; i++) {
        odbsPorts.value.push({
            label: i,
            value: i,
        });
    }
});

const beforeShow = async () => {
    let obj = props.object;
    formData.value = {
        zone: obj.zone_name,
        odb: obj.odb_name,
        name: obj.name,
        address_or_comment: obj.address,
        contact: obj.contact,
        latitude: obj.latitude,
        longitude: obj.longitude,
    };
    if (zoneOptions.value.length === 0) {
        loading.value = true;
        let result = await getNomenclatures(["zones", "odbs"]);
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
                .post(
                    `/olts/onus/update-location/${props.object.id}`,
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
</script>
