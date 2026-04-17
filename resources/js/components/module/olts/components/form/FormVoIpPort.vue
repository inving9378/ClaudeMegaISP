<template>
    <q-item dense clickable @click="showDialog = true">
        <q-item-section avatar>
            <q-icon name="mdi-plus-circle" color="primary" />
        </q-item-section>
        <q-item-section class="text-primary">
            {{ object?.admin_state === "Enabled" ? "Configurar" : "Habilitar" }}
        </q-item-section>
    </q-item>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 400px; max-width: 50vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Habilitar VoIP</div>
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
                    <div class="row">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                        >
                            Puerto</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.voip_port"
                                dense
                                outlined
                                disable
                                readonly
                                for="voip_port"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                    <div class="row">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="voip_profile_name"
                        >
                            Perfil VoIP</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.voip_profile_name"
                                dense
                                outlined
                                for="voip_profile_name"
                            />
                        </div>
                    </div>
                    <div class="row q-pt-md">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="phone_number"
                        >
                            Número</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.phone_number"
                                dense
                                outlined
                                for="phone_number"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                    <div class="row">
                        <label
                            class="col-12 col-sm-3 text-right col-form-label"
                            for="password"
                        >
                            Contraseña</label
                        >
                        <div class="col-12 col-sm-9 object-field">
                            <q-input
                                v-model="formData.password"
                                dense
                                outlined
                                for="password"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
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
import { ref, watch } from "vue";
import axios from "axios";
import { errorValidation, message } from "../../../../../helpers/toastMsg";

defineOptions({
    name: "FormVoIpPort",
});

const props = defineProps({
    onu: Object,
    object: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({
    voip_port: null,
    voip_profile_name: "servnet",
    phone_number: null,
    sip_userid: null,
    password: null,
});

const saving = ref(false);

const beforeShow = async () => {
    const obj = props.object;
    let { port, user_id, password, sip_profile, phone_number } = obj;
    formData.value = {
        voip_port: port,
        voip_profile_name:
            sip_profile === null || sip_profile.trim() === ""
                ? "servnet"
                : sip_profile,
        phone_number,
        password,
        sip_userid: user_id,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(`/olts/onus/set-onu-voip-port/${props.onu.id}`, {
                    status: "enable",
                    attr_to_server: formData.value,
                })
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message(`Puerto habilitado correctamente`, "success");
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
