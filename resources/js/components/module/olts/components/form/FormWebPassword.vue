<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        v-if="hasPermission?.data.canView('onu_edit')"
    >
        Cambiar</q-item-label
    >
    <q-item-label v-else>Cambiar</q-item-label>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 600px; max-width: 70vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Cambiar contraseña de usuario web
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
                    <p class="text-primary text-justify q-px-md q-my-none">
                        Nota: Esto habilitará y modificará el nombre de usuario
                        y la contraseña del Superusuario (también denominado
                        telecomadmin o simplemente admin). No se realizará
                        ningún cambio en las cuentas de usuario habituales.
                    </p>
                    <p class="text-primary text-justify q-px-md">
                        Para permitir también el acceso web remoto, debe
                        habilitarlo explícitamente desde la configuración de IP
                        de gestión (recomendado) o desde la configuración de la
                        WAN.
                    </p>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="web_user"
                            >Nuevo usuario web</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.web_user"
                                dense
                                outlined
                                for="web_user"
                                clearable
                                :rules="[
                                    (val) =>
                                        val.length >= 5 ||
                                        'Por favor no introduzca menos de 5 caracteres',
                                    (val) =>
                                        val.length <= 16 ||
                                        'Por favor no introduzca más de 16 caracteres',
                                ]"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="web_pass"
                            >Nueva contraseña web</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.web_pass"
                                dense
                                outlined
                                for="web_pass"
                                clearable
                                :rules="[
                                    (val) =>
                                        val.length >= 8 ||
                                        'Por favor no introduzca menos de 8 caracteres',
                                    (val) =>
                                        val.length <= 16 ||
                                        'Por favor no introduzca más de 16 caracteres',
                                ]"
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
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";

defineOptions({
    name: "FormWebPassword",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);
const form = ref("form");
const formData = ref({
    web_user: null,
    web_pass: null,
});
const saving = ref(false);

const beforeShow = async () => {};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/onus/change-web-user-pass/${props.onu.id}`,
                    formData.value
                )
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        //emits("update", response.onu);
                        message(
                            "Contraseña actualizada correctamente",
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
