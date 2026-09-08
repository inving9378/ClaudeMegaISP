// MR-24e Fase 3b (item roadmap #9990582) — catálogo de tipos de cable para el diálogo de
// vista previa del modo dibujo. Mismo patrón que nap-alta-request.js.

export const getTiposCable = async () => {
    let data = [];
    await axios
        .get(`/mapa-red/api/catalogos/tipo-cable`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};

// MR-24e Fase 4 (item roadmap #9990583) — persiste el cable/troncal dibujado contra
// CableAltaRapidaController::store. Mismo patrón que crearNapRapida (nap-alta-request.js):
// distingue éxito de error 422 (zona no resoluble) para mostrar el mensaje real del backend.
export const crearCableRapido = async (payload) => {
    try {
        const response = await axios.post(`/mapa-red/api/elementos/cable`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message:
                e?.response?.data?.message ||
                "Ocurrió un error al crear el cable/troncal",
        };
    }
};
