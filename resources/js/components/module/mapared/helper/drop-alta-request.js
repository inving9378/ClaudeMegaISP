// MR-22 — Drop (acometida NAP->cliente): alta rápida, mismo patrón que cable-alta-request.js.
export const crearDropRapido = async (payload) => {
    try {
        const response = await axios.post(`/mapa-red/api/elementos/drop`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message:
                e?.response?.data?.message ||
                "Ocurrió un error al crear el drop",
        };
    }
};
