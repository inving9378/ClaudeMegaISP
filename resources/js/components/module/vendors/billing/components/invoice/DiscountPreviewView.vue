<template>
    <q-dialog
        v-model="showDialog"
        persistent
        @show="updateShow(true)"
        @hide="updateShow(false)"
    >
        <q-card>
            <q-card-section>
                <div class="text-h6">Vista previa del descuento</div>
            </q-card-section>
            <q-separator />
            <q-card-section><div v-html="html"></div></q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Registar descuento"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="emits('save')"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { watch, ref } from "vue";

const props = defineProps({
    html: String,
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emits = defineEmits(["update:showModal", "save"]);

const showDialog = ref(false);

watch(
    () => props.showModal,
    (n) => {
        showDialog.value = n;
    }
);

const updateShow = (newValue) => {
    emits("update:showModal", newValue);
};

const save = () => {
    emits("save");
};
</script>
