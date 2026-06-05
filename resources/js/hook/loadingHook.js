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
    const m = window.bootstrap?.Modal?.getInstance(el);
    if (m) {
        m.hide();
    } else {
        // Instancia Bootstrap perdida: limpiar manualmente para no bloquear la UI
        el.classList.remove('show');
        el.style.display = 'none';
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }
};
