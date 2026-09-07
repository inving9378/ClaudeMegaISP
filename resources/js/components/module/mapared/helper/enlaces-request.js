// MR-18 (item roadmap #954, UI seguimiento #9990440) — enlaces de servicio de un NAP y su
// presupuesto óptico. Mismo patrón de request que naps-request.js.
export const getEnlacesPorNap = async (puertableType, puertableId) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/enlaces-servicio/por-nap`, {
            params: { puertable_type: puertableType, puertable_id: puertableId },
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getPresupuestoOptico = async (enlaceId, ventana = null) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/enlaces-servicio/${enlaceId}/presupuesto-optico`, {
            params: ventana ? { ventana } : {},
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
