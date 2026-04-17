<template>
    <div v-if="show" class="modal-backdrop fade show"></div>
    <div
        class="modal fade"
        :class="{ show: show, 'd-block': show }"
        id="exampleModal"
        tabindex="-1"
        aria-labelledby=""
        aria-hidden="true"
    >
        <div class="modal-dialog" :class="`modal-${size}`">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">
                        {{ title }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        @click="closeModal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body">
                    <slot name="body" />
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <slot name="footer" />
                </div>
            </div>

            <slot name="loading" />
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from "vue";

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "<<Titulo>>",
    },
    size: {
        type: String,
        default: "md",
    },
});

const emit = defineEmits(["update:show"]);

const closeModal = () => {
    emit("update:show", false);
};
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 1050;
}
</style>
