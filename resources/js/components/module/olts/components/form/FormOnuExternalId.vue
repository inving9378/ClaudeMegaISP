<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        >{{ defaultValue(object[field]) }}</q-item-label
    >

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 400px; max-width: 500vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Actualizar ID externo</div>
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
                            for="onu_external_id"
                            >Nuevo ID</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.onu_external_id"
                                dense
                                outlined
                                for="onu_external_id"
                                clearable
                                :rules="[(val) => !!val || 'Requerido']"
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
import { useUtils } from "../../../../../composables/useUtils";

defineOptions({
    name: "FormOnuExternalId",
});

const props = defineProps({
    object: Object,
    field: {
        type: String,
        default: "olt_name",
    },
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({});

const saving = ref(false);

const { defaultValue } = useUtils();

const beforeShow = async () => {
    formData.value = {
        onu_external_id: props.object?.unique_external_id,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/onus/update-external-id/${props.object.id}`,
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
