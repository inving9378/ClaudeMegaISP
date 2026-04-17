<template>
    <div
        :aria-labelledby="labelledby"
        aria-hidden="true"
        class="modal fade"
        :id="id"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        role="dialog"
    >
        <div :class="`modal-dialog modal-${classModalWith}`">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ modalTitle }}</h6>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="closeModal"
                    ></button>
                </div>
                <div class="modal-body m-0" style="height: 450px; overflow-y: scroll;">
                    <slot></slot>
                </div>
                <div class="form-group text-center modal-footer">
                    <button
                        type="button"
                        class="btn btn-light waves-effect me-2"
                        data-bs-dismiss="modal"
                        @click="closeModal"
                    >
                        Cerrar
                    </button>
                    <button class="btn btn-primary" type="submit" @click="emitSubimt">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import Modal from "../helpers/modal";
export default {
    name: "ModalCentrado",
    props: {
        id: String,
        labelledby: String,
        modalTitle: String,
        classModalWith: {
            type: String,
            default: "",
        },
        descriptionModal: String,
    },
    emits: ["submit","close-modal"],
    setup(props, { emit }) {
        const modal = ref();

        const showAddModal = () => {
            modal.value.show();
        };

        const emitSubimt = () => {
            emit("submit");
        };
        const closeModal = () => {
            emit("close-modal");
        };

        onMounted(() => {
            modal.value = new Modal(`#${props.id}`);
        });

        return {
            showAddModal,
            modal,
            emitSubimt,
            closeModal
        };
    },
};
</script>

<style scoped></style>
