class Modal {
    /**
     * Create a new Modal instance.
     *
     * @param id
     */
    constructor(id) {
        this.id = id;
    }

    show() {
        const el = document.getElementById(this.id);
        if (!el) return;
        window.bootstrap.Modal.getOrCreateInstance(el).show();
    }

    hide() {
        const el = document.getElementById(this.id);
        if (!el) return;
        const m = window.bootstrap.Modal.getInstance(el);
        if (m) m.hide();
    }
}

export default Modal
