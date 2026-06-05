<template>
    <div v-if="show" class="modal-backdrop fade show"></div>
    <div
        class="modal fade"
        :class="{ show: show, 'd-block': show }"
        :id="modalId"
        tabindex="-1"
        role="dialog"
        :aria-labelledby="`${modalId}-label`"
        :aria-hidden="show ? 'false' : 'true'"
    >
        <div class="modal-dialog" :class="`modal-${size}`">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" :id="`${modalId}-label`">
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
import { defineProps, defineEmits, ref, watch, onUnmounted } from "vue";

const modalId = ref('modal-' + Math.random().toString(36).slice(2, 11));

const props = defineProps({
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

// Bloquear scroll del body mientras el modal está abierto (equivalente a body.modal-open de Bootstrap JS)
const lockBody = () => {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.classList.add('modal-open');
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = scrollbarWidth + 'px';
    }
};
const unlockBody = () => {
    // Solo desbloquear si no hay otro modal abierto
    const otherModals = document.querySelectorAll('.modal.show.d-block');
    if (otherModals.length <= 1) {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }
};

watch(() => props.show, (val) => {
    val ? lockBody() : unlockBody();
}, { immediate: true });

onUnmounted(() => unlockBody());
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5); /* estándar Bootstrap */
    z-index: 1040;                         /* debajo del modal (Bootstrap modal=1055) */
}
</style>
