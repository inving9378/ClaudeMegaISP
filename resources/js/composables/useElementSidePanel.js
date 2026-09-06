import { ref } from "vue";

// Estado compartido de la ficha lateral del elemento (MR-23, item #959).
// Un solo panel para todo el módulo Mapa de Red: cualquier vista que
// seleccione un elemento (árbol, mapa) llama a openElementSidePanel().
export const sidePanelNode = ref(null);
export const sidePanelOpen = ref(false);

export const openElementSidePanel = (node) => {
    if (!node) return;
    sidePanelNode.value = node;
    sidePanelOpen.value = true;
};

export const closeElementSidePanel = () => {
    sidePanelOpen.value = false;
};
