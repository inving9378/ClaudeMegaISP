// MR-22 Fase 1b-i (item roadmap #9990535) — buscador global del mapa.
// Contrato del endpoint (Fase 1a, #9990509): GET /mapa-red/api/buscar?q=texto
// -> JSON agrupado por tipo: {nodos:[{id,tipo,label,lat,lng}], clientes:[...], onts:[...]}
export const buscarEnMapa = async (q) => {
    try {
        const response = await axios.get(`/mapa-red/api/buscar`, {
            params: { q },
        });
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e.response?.data?.message || "No se pudo completar la búsqueda.",
        };
    }
};
