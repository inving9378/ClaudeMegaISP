import { ref } from "vue";

export const changeBalance = ref(false);
export const errorTextKeys = ref(null);
export const cleanHtml = ref(false);
export const clientMainInformationId = ref(null);
export const getListTemplate = ref(false);

// Token anti-race COMPARTIDO entre instancias de la ficha de cliente: cada carga de
// cliente (loadClient) incrementa el contador y guarda su valor; si otra carga arranca
// después (p.ej. al cambiar de cliente rápido, incluso con la instancia previa aún
// resolviendo su promesa tras el unmount), las respuestas tardías detectan que su token
// quedó obsoleto y NO escriben el singleton dataForm. Debe ser module-level (no per-setup)
// para cubrir el solape entre la instancia vieja y la nueva durante la navegación SPA.
export const clientLoadToken = ref(0);
