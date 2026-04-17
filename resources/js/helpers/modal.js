class Modal {
    /**
     * Create a new Modal instance.
     *
     * @param id
     */
    constructor(id) {
        this.id = id;
    }

    show(){
        $(`#${this.id}`).modal('show');
    }

    hide(){
        $(`#${this.id}`).modal('hide');
    }
}

export default Modal
