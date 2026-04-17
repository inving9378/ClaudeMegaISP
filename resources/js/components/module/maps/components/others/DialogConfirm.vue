<template>
    <q-dialog
        v-model="showDialog"
        persistent
        transition-show="scale"
        transition-hide="scale"
    >
        <q-card class="q-pa-lg" style="width: 400px; max-width: 80vw">
            <q-card-section class="text-center">
                <q-icon
                    name="img:data:image/png;base64,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"
                    color="primary"
                    size="5rem"
                />
                <div class="text-h5 q-mt-md">{{ title }}</div>
                <div class="text-subtitle1 text-grey-7 q-mt-sm">
                    {{ message }}
                </div>
            </q-card-section>
            <q-card-actions align="center" class="q-mt-lg">
                <q-btn
                    no-caps
                    label="Si"
                    color="blue-6"
                    @click="emits('yes')"
                /><q-btn
                    no-caps
                    label="No"
                    color="negative"
                    @click="emits('no')"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";

defineOptions({
    name: "DialogConfirm",
});

const props = defineProps({
    title: {
        type: String,
        default: "Confirmación!",
    },
    message: String,
    show: Boolean,
});

const emits = defineEmits(["yes", "no"]);

const showDialog = ref(false);

watch(
    () => props.show,
    (n) => {
        showDialog.value = n;
    }
);
</script>
