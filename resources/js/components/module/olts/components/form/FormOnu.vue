<template>
    <q-btn
        :label="label"
        :color="color"
        :icon="icon"
        :flat="flat"
        no-caps
        @click="showDialog = true"
    />

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 800px; max-width: 90vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            {{ object ? "Autorizar onu" : "Registrar ONU" }}
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
                            for="olt"
                            >OLT</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-field
                                outlined
                                readonly
                                filled
                                dense
                                disable
                                v-if="object"
                            >
                                <template v-slot:control
                                    >{{ object.olt_str }}
                                </template>
                            </q-field>
                            <select-form-component
                                name="olt_id"
                                option-label="name"
                                option-value="id"
                                :model-value="formData.olt_id"
                                :options="olts"
                                :required="true"
                                @change="(name, val) => onChangeOlt(val)"
                                v-else
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label class="col-12 col-sm-4 text-right col-form-label"
                            >Tipo PON</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-option-group
                                v-model="formData.pon_type"
                                :options="ponTypes"
                                color="primary"
                                inline
                                @update:model-value="onPonTypeChange"
                                v-if="object === null || object === undefined"
                            />
                            <q-option-group
                                v-model="formData.pon_type"
                                :options="ponTypes"
                                color="primary"
                                inline
                                disable
                                v-else
                            />
                        </div>
                    </div>
                    <div
                        class="row q-my-sm"
                        v-if="formData.pon_type === 'gpon'"
                    >
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="gpon_channel"
                            >Canal GPON</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-option-group
                                v-model="formData.gpon_channel"
                                :options="gponChannels"
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                    <div
                        class="row q-my-sm"
                        v-if="formData.pon_type === 'epon'"
                    >
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="epon_channel"
                            >Canal EPON</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-option-group
                                v-model="formData.epon_channel"
                                :options="eponChannels"
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="board"
                            >Board</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-field
                                outlined
                                readonly
                                filled
                                dense
                                disable
                                v-if="object"
                            >
                                <template v-slot:control
                                    >{{ object.board }}
                                </template>
                            </q-field>
                            <select-form-component
                                name="board"
                                option-value="slot"
                                :model-value="formData.board"
                                :options="cardsOptions"
                                @change="(name, val) => onChangeCard(val)"
                                v-else
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="port"
                            >Puerto</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-field
                                outlined
                                readonly
                                filled
                                dense
                                disable
                                v-if="object"
                            >
                                <template v-slot:control
                                    >{{ object.port }}
                                </template>
                            </q-field>
                            <select-form-component
                                name="port"
                                :model-value="formData.port"
                                :options="portOptions"
                                @change="(name, val) => (formData[name] = val)"
                                v-else
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="sn"
                            >Serie</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-field
                                outlined
                                readonly
                                filled
                                dense
                                disable
                                v-if="object"
                            >
                                <template v-slot:control
                                    >{{ object.sn }}
                                </template>
                            </q-field>
                            <q-input
                                v-model="formData.sn"
                                dense
                                outlined
                                for="sn"
                                clearable
                                hint="Requerido"
                                :rules="[(val) => !!val || 'Requerido']"
                                @update:model-value="
                                    (val) => {
                                        formData.onu_external_id = val;
                                    }
                                "
                                v-else
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="onu_type"
                            >Tipo ONU</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="onu_type"
                                :model-value="formData.onu_type"
                                :options="typeOnus"
                                :required="true"
                                option-label="name"
                                option-value="name"
                                @change="onChangeTypeONU"
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <q-checkbox
                                v-model="useCustomProfile"
                                label="Utilice un perfil personalizado (para una mejor compatibilidad con ONU genéricas)"
                                style="width: 90%"
                                @update:model-value="
                                    formData.custom_profile = null
                                "
                            />
                            <select-form-component
                                name="custom_profile"
                                :model-value="formData.custom_profile"
                                :options="profiles"
                                :required="true"
                                @change="(name, val) => (formData[name] = val)"
                                v-if="useCustomProfile"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right self-end"
                            for="onu_mode"
                            >Modo ONU</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-option-group
                                v-model="formData.onu_mode"
                                :options="
                                    onuModes.filter((m) =>
                                        capabilities.includes(m.value)
                                    )
                                "
                                color="primary"
                                inline
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right self-end"
                        ></label>
                        <div class="col-12 col-sm-8 object-field">
                            <q-checkbox
                                v-model="useSvlan"
                                label="Usar SVLAN-ID"
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
                                :options="vlansOptions"
                                :required="true"
                                option-value="vlan"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>

                    <div class="row q-my-sm" v-if="useSvlan">
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
                                :options="vlansOptions"
                                :required="true"
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
                                :options="vlansOptions"
                                :required="true"
                                option-value="vlan"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
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
                                :options="zones"
                                @change="(name, val) => onChangeZone(val)"
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
                                :options="odbsOptions"
                                :set-default="true"
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
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="name"
                            >Nombre</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-field
                                outlined
                                readonly
                                filled
                                dense
                                disable
                                v-if="client"
                            >
                                <template v-slot:control
                                    >{{ client.id }} - {{ client.name }}
                                </template>
                            </q-field>
                            <select-form-component
                                name="name"
                                option-label="name"
                                option-value="name"
                                class="input-select"
                                :model-value="formData.name"
                                :options="clients"
                                @change="(name, val) => onChangeClient(val)"
                                v-else
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
                            for="onu_external_id"
                            >ID externo de la ONU</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.onu_external_id"
                                dense
                                outlined
                                for="onu_external_id"
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
                                label="Usar GPS"
                                style="width: 90%"
                                @update:model-value="() => {}"
                            />
                        </div>
                    </div>
                    <template v-if="useGps">
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
                    </template>
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

    <panel-onu
        :onu="savedOnu"
        :show="showPanelOnu"
        :from-form="true"
        :has-permission="hasPermission"
        @hide="showPanelOnu = false"
        @removed="emits('removed', savedOnu.id ?? null)"
    />
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import { useUtils } from "../../../../../composables/useUtils";
import axios from "axios";

import PanelOnu from "../onus/PanelOnu.vue";
import LocationComponent from "./LocationComponent.vue";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormOnu",
});

const props = defineProps({
    currentOlt: Number,
    object: Object,
    client: Object,
    label: {
        type: String,
        default: "Autorizar",
    },
    color: {
        type: String,
        default: "primary",
    },
    flat: {
        type: Boolean,
        default: true,
    },
    icon: String,
    olts: {
        type: Array,
        default: [],
    },
    typeOnus: {
        type: Array,
        default: [],
    },
    zones: {
        type: Array,
        default: [],
    },
    clients: {
        type: Array,
        default: [],
    },
    speedProfiles: {
        type: Array,
        default: [],
    },
    odbs: {
        type: Array,
        default: [],
    },
    loading: Boolean,
    forAfter: Boolean,
    hasPermission: Object,
});

const emits = defineEmits(["created", "removed"]);

const showDialog = ref(false);
const showPanelOnu = ref(false);

const form = ref("form");
const formData = ref({});
const savedOnu = ref(null);

const useSvlan = ref(false);
const useCvlan = ref(false);
const useCustomProfile = ref(false);
const useGps = ref(false);

const saving = ref(false);

const { removeAccents } = useUtils();

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

const gponChannels = [
    {
        label: "GPON",
        value: "gpon",
    },
    {
        label: "XG-PON",
        value: "xgpon",
    },
    {
        label: "XGS-PON",
        value: "xgspon",
    },
];

const eponChannels = [
    {
        label: "EPON",
        value: "epon",
    },
    {
        label: "10GE-PON",
        value: "10gepon",
    },
];

const profiles = [
    {
        label: "Genérico 1",
        value: "Generic_1",
    },
    {
        label: "Genérico 2",
        value: "Generic_2",
    },
    {
        label: "Genérico 3",
        value: "Generic_3",
    },
    {
        label: "Genérico 4",
        value: "Generic_4",
    },
    {
        label: "Genérico 5 (utiliza tcont 4)",
        value: "Generic_5",
    },
    {
        label: "Genérico 6 (para intervalos ONUs)",
        value: "Generic_6",
    },
];

const onuModes = [
    {
        label: "Enrutamiento",
        value: "Routing",
    },
    {
        label: "Puente",
        value: "Bridging",
    },
];

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

const odbsOptions = ref([]);
const odbsPorts = ref([]);
const portOptions = ref([]);
const cardsOptions = ref([]);
const vlansOptions = ref([]);

onMounted(() => {
    for (let i = 1; i <= 16; i++) {
        odbsPorts.value.push({
            label: i,
            value: i,
        });
    }
});

watch(useSvlan, (n) => {
    formData.value.svlan = null;
    formData.value.tag_transform_mode = n ? "translate" : null;
});

const capabilities = computed(() => {
    let type = formData.value.onu_type;
    if (type) {
        return props.typeOnus
            .find((t) => t.name === type)
            .capability.split("/");
    }
    return ["Routing", "Bridging"];
});

const onChangeTypeONU = (name, val) => {
    formData.value[name] = val;
    formData.value.onu_mode = capabilities.value[0];
};

const beforeShow = () => {
    savedOnu.value = null;
    showPanelOnu.value = false;
    let olt_id = null,
        pon_type = "gpon",
        board = null,
        port = null,
        onu_type = null,
        epon_channel = null,
        gpon_channel = null,
        sn = null;
    if (props.object) {
        let obj = props.object;
        let found = props.olts.find((o) => o.id === obj.olt_id);
        if (found) {
            olt_id = found.id;
            onChangeOlt(olt_id);
        }
        board = obj.board;
        sn = obj.sn;
        port = obj.port;
        onu_type = obj.onu_type_name;
        pon_type = obj.pon_type;
    } else if (props.currentOlt) {
        let found = props.olts.find((o) => o.id === props.currentOlt);
        if (found) {
            olt_id = found.id;
            onChangeOlt(olt_id);
        }
    }
    if (pon_type === "gpon") {
        gpon_channel = "gpon";
    } else {
        epon_channel = "epon";
    }
    formData.value = {
        olt_id,
        pon_type,
        gpon_channel,
        epon_channel,
        board,
        port,
        sn,
        onu_type,
        custom_profile: null,
        onu_mode: "Routing",
        cvlan: null,
        svlan: null,
        tag_transform_mode: null,
        use_other_all_tls_vlan: 0,
        vlan: null,
        zone: null,
        odb: null,
        name: null,
        address_or_comment: null,
        onu_external_id: sn,
        upload_speed_profile_name: null,
        download_speed_profile_name: null,
        latitude: null,
        longitude: null,
        client_id: null,
    };
    if (props.client) {
        let { id, name, address } = props.client;
        formData.value.name = `${id} - ${name}`;
        formData.value.client_id = id;
        formData.value.address_or_comment = removeAccents(address);
    }
};

const onPonTypeChange = (val) => {
    if (val === "gpon") {
        formData.value.gpon_channel = "gpon";
        formData.value.epon_channel = null;
    } else {
        formData.value.gpon_channel = null;
        formData.value.epon_channel = "epon";
    }
};

const onChangeZone = (val) => {
    formData.value.zone = val;
    formData.value.odb = null;
    if (val !== null) {
        let zone = props.zones.find((z) => z.name === val) ?? null;
        odbsOptions.value = zone
            ? props.odbs.filter((o) => o.zone_id === zone.id)
            : [];
    } else {
        odbsOptions.value = [];
    }
};

const onChangeCard = (val) => {
    formData.value.board = val;
    formData.value.port = null;
    if (val !== null) {
        let selectedCard =
            cardsOptions.value.find((c) => c.slot === val) ?? null;
        let list = [];
        if (selectedCard) {
            for (let i = 0; i < selectedCard.ports; i++) {
                list.push(i);
            }
        }
        portOptions.value = list.map((p) => {
            return {
                label: p,
                value: p,
            };
        });
    } else {
        portOptions.value = [];
    }
};

const onChangeOlt = (val) => {
    formData.value.olt_id = val;
    formData.value.board = null;
    formData.value.port = null;
    formData.value.vlan = null;
    if (val !== null) {
        let foundOlt = props.olts.find((c) => c.id === val) ?? null;
        if (foundOlt) {
            cardsOptions.value = foundOlt.cards;
            vlansOptions.value = foundOlt.vlans;
        }
    } else {
        cardsOptions.value = [];
        portOptions.value = [];
        vlansOptions.value = [];
    }
};

const onChangeClient = (val) => {
    formData.value.name = val;
    if (val) {
        let client = props.clients.find((c) => c.name === val);
        if (client) {
            formData.value.client_id = client.id;
            formData.value.address_or_comment = removeAccents(client.address);
        }
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post("/olts/onus/create", formData.value)
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("created", response.onu);
                        message("ONU agregada correctamente", "success");
                        savedOnu.value = response.onu;
                        showDialog.value = false;
                        showPanelOnu.value = true;
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
