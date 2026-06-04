import { ref } from "vue";

export const loadingToogle = ref(false);
export const enableLoading = () => {
    loadingToogle.value = true;
};
export const disabledLoading = () => {
    loadingToogle.value = false;
};

export const enableLoadingModal = () => {
    const el = document.getElementById('loadingComponentModal');
    if (el) window.bootstrap.Modal.getOrCreateInstance(el).show();
};
export const disabledLoadingModal = () => {
    const el = document.getElementById('loadingComponentModal');
    if (!el) return;
    const m = window.bootstrap.Modal.getInstance(el);
    if (m) m.hide();
};
