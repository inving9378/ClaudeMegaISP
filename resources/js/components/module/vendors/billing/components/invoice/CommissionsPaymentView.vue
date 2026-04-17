<template>
    <modal
        :show="props.showModal"
        :size="'lg'"
        title="Vista previa del pago"
        @update:show="updateShow"
    >
        <template #body>
            <div v-html="html"></div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="save">Registar pago</button>
        </template>
    </modal>
</template>

<script setup>
import { ref, computed } from "vue";
import Modal from "../../../../../../shared/ModalSimple.vue";
const props = defineProps({
    html: String,
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emits = defineEmits(["update:showModal", "save"]);

const updateShow = (newValue) => {
    emits("update:showModal", newValue);
};

const save = () => {
    emits("save");
};
</script>
