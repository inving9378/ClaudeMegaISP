// MR-23 fase 4d-C (item roadmap #9990561) — historial de cambios de un nodo (MapaRedLayer).
// Consume el endpoint real ya en main (LayersController::historial, item #9990456/#9990429):
// GET /mapa-red/api/layers/{id}/historial, paginado (Laravel paginate() estándar), gateado por
// el permiso 'mapa_red_historial_ver'. NO es el endpoint genérico /historial/{tipo}/{id} que
// describían los sub-items #9990559/#9990560 — esos nunca se mergearon tal cual; lo que
// realmente existe en main es esta ruta específica de layers.
export const getHistorialLayer = async (layerId, page = 1) => {
    try {
        const response = await axios.get(`/mapa-red/api/layers/${layerId}/historial`, {
            params: { page },
        });
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e?.response?.data?.message || "No se pudo cargar el historial.",
        };
    }
};
