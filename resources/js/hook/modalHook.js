import {ref} from "vue";

export const editModal = ref({
    key: null,
    toggleModal: null,
    value: false
});

export const showEditModal = (idItem, modal) => {
    editModal.value = {
        key: idItem,
        value: true,
        toggleModal: modal
    }
}
