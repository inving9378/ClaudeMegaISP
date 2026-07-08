import { ref } from "vue";
import { dom } from "../../../public/plugins/quasar/js/quasar.umd.prod";

export const darkMode = ref(false);

/*
 * Estilo de fila por estado — preferencia POR USUARIO, centralizada.
 * 'underline' = línea inferior de color (default) · 'filled' = fondo relleno.
 * Se lee del atributo body[data-row-style] (lo pinta el master desde la BD) y se
 * persiste con el MISMO patrón que el modo oscuro (POST a un endpoint del layout).
 * Al ser global (atributo en body + CSS en app.scss), afecta TODAS las tablas.
 */
export const rowStatusStyle = ref("underline");

export const setRowStatusStyle = (style) => {
    const value = style === "filled" ? "filled" : "underline";
    rowStatusStyle.value = value;
    document.body.setAttribute("data-row-style", value);
    if (window.axios) {
        window.axios
            .post("/save-row-status-style", { row_status_style: value })
            .catch((error) => console.log(error));
    }
};

export const toggleRowStatusStyle = () => {
    setRowStatusStyle(rowStatusStyle.value === "filled" ? "underline" : "filled");
};

export const setActiveTab = (component, tab) => {
    localStorage.setItem(component, tab);
};

const { ready } = dom;

ready(function () {
    darkMode.value =
        document.querySelector("body").getAttribute("data-layout-mode") !==
        "light";
    rowStatusStyle.value =
        document.querySelector("body").getAttribute("data-row-style") ===
        "filled"
            ? "filled"
            : "underline";
});
