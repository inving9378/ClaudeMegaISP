<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
    >
        {{ onu.pon_type.toUpperCase() }}
    </q-item-label>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 700px; max-width: 80vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Actualizar canal {{ onu.onu_pon_type }}
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
                        <label class="col-12 col-sm-3 text-right col-form-label"
                            >Canal {{ onu.onu_pon_type }}</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-option-group
                                v-model="formData.pon_channel"
                                :options="gponChannels"
                                color="primary"
                                inline
                                v-if="onu.onu_pon_type === 'GPON'"
                            />
                            <q-option-group
                                v-model="formData.pon_channel"
                                :options="eponChannels"
                                color="primary"
                                inline
                                v-else
                            />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col q-mx-md">
                            <p>
                                SmartOLT asigna ID de ONU del rango 0-127 para
                                el canal GPON y del rango 128-255 para los
                                canales XG/XGS-PON.
                            </p>
                            <p>
                                Una vez agotado el rango 128-255, asigna del
                                rango 0-127 tanto para los canales GPON como
                                XG/XGS-PON.
                            </p>
                            <p>
                                Consejo: Las ONU compatibles con XGPON que
                                previamente estaban autorizadas en el canal GPON
                                deben cambiarse al canal XGPON para liberar ID
                                para las ONU GPON.
                            </p>
                        </div>
                    </div>
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

defineOptions({
    name: "FormChannel",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({
    pon_channel: null,
});

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

const saving = ref(false);

const beforeShow = async () => {
    formData.value = {
        pon_channel: props.onu.pon_type,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/onus/update-channel/${props.onu.id}`,
                    formData.value
                )
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("Canal modificado correctamente", "success");
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
