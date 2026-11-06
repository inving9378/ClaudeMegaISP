// MR-23 fase 4a (item roadmap #9990519) — persiste el enlace trazado (clic secuencial sobre 2
// nodos existentes) contra EnlacesController::store (#9990518). Mismo patrón que
// crearCableRapido/crearNapRapida (cable-alta-request.js / nap-alta-request.js).

export const crearEnlaceRapido = async (payload) => {
    try {
        const response = await axios.post(`/mapa-red/api/enlaces`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message:
                e?.response?.data?.message ||
                "Ocurrió un error al trazar el enlace",
        };
    }
};
