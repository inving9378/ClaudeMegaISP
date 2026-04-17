<template>
    <q-btn icon="add" color="primary" @click="showDialog = true">
        <q-tooltip>Adicionar</q-tooltip>
    </q-btn>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 600px; max-width: 70vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Adicionar odb</div>
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
                            for="zone"
                            >Zona</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <select-form-component
                                name="zone"
                                option-label="name"
                                option-value="name"
                                class="input-select"
                                :model-value="formData.name"
                                :options="zoneOptions"
                                :loading="loading"
                                :required="true"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="sn"
                            >Nombre</label
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
                            for="sn"
                            >Puertos</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model.number="formData.nr_of_ports"
                                type="number"
                                dense
                                outlined
                                for="nr_of_ports"
                                clearable
                                hint="Requerido"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="sn"
                            >Latitud</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.latitude"
                                dense
                                outlined
                                for="latitude"
                                clearable
                            />
                        </div>
                    </div>
                    <div class="row q-pt-md">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="sn"
                            >Longitud</label
                        >
                        <div class="col-12 col-sm-9 object-field">
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
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 text-right">
                            <q-btn
                                :label="
                                    showMap ? 'Ocultar mapa >>' : 'Usar mapa >>'
                                "
                                color="primary"
                                no-caps
                                flat
                                @click="showMap = !showMap"
                            />
                        </div>
                    </div>
                    <div class="row" v-if="showMap">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-9 text-right">
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
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import LocationComponent from "../form/LocationComponent.vue";
import SelectFormComponent from "../form/SelectFormComponent.vue";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";

defineOptions({
    name: "FormOdb",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["reload"]);

const showDialog = ref(false);
const showMap = ref(false);

const form = ref("form");
const formData = ref({
    zone: null,
    name: null,
    nr_of_ports: null,
    latitude: null,
    longitude: null,
});
const zoneOptions = ref([]);
const loading = ref(false);
const saving = ref(false);

const beforeShow = async () => {
    formData.value = {
        zone: props.object?.zone ?? null,
        name: props.object?.name ?? null,
        nr_of_ports: props.object?.nr_of_ports ?? null,
        latitude: props.object?.latitude ?? null,
        longitude: props.object?.longitude ?? null,
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
                .post("/olts/settings/odbs/store", formData.value)
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("reload", true);
                        message("ODB agregado correctamente", "success");
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
