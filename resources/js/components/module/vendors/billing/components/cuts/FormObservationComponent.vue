<template>
    <modal
        :show="showModal"
        size="lg"
        @update:show="showModal = $event"
        :title="`${object ? 'Editar' : 'Agregar'} observación`"
    >
        <template #body>
            <q-card flat style="margin: -15px">
                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-form greedy>
                        <textarea :id="currentUid" v-model="model"></textarea>
                        <span class="text-negative" v-if="required"
                            >Requerido</span
                        >
                    </q-form>
                </q-card-section>
            </q-card>
        </template>
        <template #footer>
            <q-btn color="primary" label="Guardar" no-caps @click="save" />
        </template>
        <template #loading>
            <q-inner-loading
                :showing="loading"
                label="Guardando datos, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </template>
    </modal>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import Modal from "../../../../../../shared/ModalSimple.vue";
import {
    error500,
    errorValidation,
    message,
} from "../../../../../../helpers/toastMsg";
import {
    storeObservation,
    updateObservation,
} from "../../helper/cutObservations";
import { convertToCkeditor } from "../../../../../../helpers/Transform";
import { uid } from "../../../../../../../../public/plugins/quasar/js/quasar.umd.prod";

const props = defineProps({
    object: Object,
    boxId: Number,
    show: Boolean,
});

const emits = defineEmits(["created", "updated", "hide"]);

let currentUid = "text-area" + uid();

const showModal = ref(false);
const model = ref("");
const loading = ref(false);
const required = ref(false);
let ckEditor = null;

watch(
    () => props.show,
    (n) => {
        showModal.value = n;
    }
);

watch(showModal, (n) => {
    loading.value = false;
    required.value = false;
    if (n) {
        model.value = props.object?.comment ?? "";
        nextTick(() => {
            initEditor();
        });
    } else {
        emits("hide");
        if (ckEditor) {
            ckEditor.destroy().then(() => {
                ckEditor = null;
                model.value = "";
            });
        }
    }
});

const initEditor = async () => {
    ckEditor = await convertToCkeditor(currentUid, model);
    if (ckEditor) {
        ckEditor.model.document.on("change:data", () => {
            model.value = ckEditor.getData();
        });
    }
    ckEditor.setData(model.value);
};

const save = () => {
    if (model.value) {
        if (props.object) {
            update();
        } else {
            store();
        }
    } else {
        required.value = true;
        errorValidation();
    }
};

const store = async () => {
    loading.value = true;
    const result = await storeObservation({
        comment: model.value,
        box_id: props.boxId,
    });
    if (result) {
        emits("created", result);
        showModal.value = false;
        message("Observación agregada correctamente");
    } else {
        error500();
    }
    loading.value = false;
};

const update = async () => {
    loading.value = true;
    const result = await updateObservation(props.object.id, {
        comment: model.value,
    });
    if (result) {
        emits("updated", result);
        showModal.value = false;
        message("Observación modificada correctamente");
    } else {
        error500();
    }
    loading.value = false;
};
</script>
