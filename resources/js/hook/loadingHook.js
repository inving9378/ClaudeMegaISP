import { ref } from "vue";

export const loadingToogle = ref(false);
export const enableLoading = () => {
    loadingToogle.value = true;
};
export const disabledLoading = () => {
    loadingToogle.value = false;
};

export const enableLoadingModal = () => {
    $('#loadingComponentModal').modal('show');
};
export const disabledLoadingModal = () => {
    $('#loadingComponentModal').modal('hide');
};
