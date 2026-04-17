import { Plugin } from "ckeditor5";
export default class HTMLCodePlugin extends Plugin {
    constructor(editor) {
        this.editor = editor;
    }

    init() {
        const editor = this.editor;
        // Agregar el botón a la toolbar
        editor.ui.componentFactory.add("htmlCode", () => {
            const button = document.createElement("button");
            button.type = "button";
            button.classList.add("ck-button");
            button.innerHTML = '<span class="ck-button__label"></>';

            // Manejar el clic
            button.addEventListener("click", () => {});

            // Devolver el botón como un objeto con "render()" (requerido por CKEditor)
            return {
                render: () => button,
            };
        });
    }
}
