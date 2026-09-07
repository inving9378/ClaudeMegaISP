// MR-24e Fase 1b (item roadmap #9990558) — alta rápida de NAP desde el modo dibujo del mapa.
// Mismo patrón de request que naps-request.js/cobertura-request.js.

export const getTiposSplitter = async () => {
    let data = [];
    await axios
        .get(`/mapa-red/api/catalogos/tipo-splitter`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};

// A diferencia de los helpers hermanos (que devuelven null en error), aquí sí necesitamos
// distinguir éxito de error 422 para mostrar el mensaje real del backend (zona no resoluble).
export const crearNapRapida = async (payload) => {
    try {
        const response = await axios.post(`/mapa-red/api/elementos/nap`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message:
                e?.response?.data?.message ||
                "Ocurrió un error al crear la NAP",
        };
    }
};
