<template>
    <q-btn icon="add" color="primary" @click="showDialog = true">
        <q-tooltip>Adicionar</q-tooltip>
    </q-btn>

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 400px; max-width: 50vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Adicionar zona</div>
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
                            for="sn"
                            >Nombre</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                v-model="formData.zone"
                                dense
                                outlined
                                for="zone"
                                clearable
                                :rules="[(val) => !!val || 'Requerido']"
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
import axios from "axios";

defineOptions({
    name: "FormZone",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["reload"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({
    zone: null,
});

const saving = ref(false);

const beforeShow = async () => {
    formData.value = {
        zone: props.object?.zone ?? null,
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post("/olts/settings/zones/store", formData.value)
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("reload", true);
                        message("Zona agregada correctamente", "success");
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
