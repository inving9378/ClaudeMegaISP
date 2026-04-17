<template>
    <q-btn
        :color="object.note ? 'primary' : 'grey'"
        :size="size"
        padding="5px"
        icon="sticky_note_2"
        @click.stop="dialog = true"
        ><q-tooltip :class="object.note ? 'bg-primary' : 'bg-grey'"
            >Administrar nota</q-tooltip
        ></q-btn
    >
    <q-dialog v-model="dialog" persistent @before-show="onShowDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object.note ? "Editar" : "Adicionar" }}
                            nota
                        </h6></q-item-section
                    >
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="dialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />
            <q-card-section>
                <q-form ref="form" greedy>
                    <div class="row">
                        <div class="col">
                            <q-input
                                v-model="note"
                                type="textarea"
                                outlined
                                dense
                                :rows="6"
                                :rules="[(val) => !!val || 'Requerido']"
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    no-caps
                    color="primary"
                    label="Guardar"
                    :disable="loading"
                    @click="onSave"
                    v-if="hasEdit"
                />
                <q-btn
                    no-caps
                    color="red"
                    label="Eliminar"
                    :disable="loading"
                    @click="save(null)"
                    v-if="object.note && hasEdit"
                />
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    label="Cancelar"
                    @click="dialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import { savePortDevice } from "../helper/devices-request";

defineOptions({
    name: "PortNoteComponent",
});

const props = defineProps({
    object: Object,
    hasEdit: Boolean,
    size: {
        type: String,
        default: "sm",
    },
});

const emits = defineEmits(["save"]);

const dialog = ref(false);
const loading = ref(false);
const note = ref(null);
const form = ref(null);

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const onShowDialog = () => {
    loading.value = false;
    note.value = props?.object?.note ?? null;
};

const onSave = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            save(note.value);
        } else {
            errorValidation();
        }
    });
};

const save = async (n = null) => {
    loading.value = true;
    const object = await savePortDevice({
        id: props.object.id,
        note: n,
    });
    loading.value = false;
    if (object !== null) {
        emits("save", n);
        message(`Nota ${n ? "guardada" : "eliminada"} correctamente`);
        dialog.value = false;
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};
</script>

<style scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
</style>
