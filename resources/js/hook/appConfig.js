import { ref } from "vue";
import { dom } from "../../../public/plugins/quasar/js/quasar.umd.prod";

export const darkMode = ref(false);

export const setActiveTab = (component, tab) => {
    localStorage.setItem(component, tab);
};

const { ready } = dom;

ready(function () {
    darkMode.value =
        document.querySelector("body").getAttribute("data-layout-mode") !==
        "light";
});
