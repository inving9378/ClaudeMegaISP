<template>
    <q-dialog full-width v-model="showDialog" persistent @hide="emits('hide')">
        <q-card>
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">ONUs desconfiguradas</div>
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
                <olt-unconfigured-onus
                    :has-permission="hasPermission"
                    :client="client"
                    @created="onCreatedOnu"
                />
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";

import OltUnconfiguredOnus from "../OltUnconfiguredOnus.vue";

defineOptions({
    name: "DialogUnconfiguredOnus",
});

const props = defineProps({
    hasPermission: Object,
    client: Object,
    show: Boolean,
});

const emits = defineEmits(["created", "hide"]);

const showDialog = ref(false);

watch(
    () => props.show,
    (n) => {
        showDialog.value = n;
    }
);

const onCreatedOnu = (onu) => {
    emits("created", onu);
    showDialog.value = false;
};
</script>
