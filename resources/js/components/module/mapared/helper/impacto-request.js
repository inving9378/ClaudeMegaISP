// MR-17 Fase 3b (item roadmap #9990594) — trazo inverso de impacto: quién depende de un
// elemento (clientes/MRR afectados). Backend Fase 3a (item #9990593), mismo patrón de
// request que enlaces-request.js.
export const getImpacto = async (tipo, id) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/impacto`, {
            params: { tipo, id },
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
