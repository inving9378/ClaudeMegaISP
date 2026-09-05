<template>
    <q-dialog v-model="dialog" persistent @hide="emits('cancel')">
        <q-card>
            <q-card-section>
                <q-avatar icon="question" />
            </q-card-section>

            <q-card-section>
                ¿Estás seguro de que deseas continuar?
            </q-card-section>

            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    flat
                    label="Cancelar"
                    color="negative"
                    @click="dialog = false"
                />
                <q-btn label="Aceptar" color="positive" class="q-ml-sm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "DialogComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(["cancel", "ok"]);

const dialog = ref(false);

onMounted(() => {});

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);
</script>
