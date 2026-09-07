// MR-26 Fase 4b (item roadmap #9990527) — capa "Sectores" (torres/AP sectorizados, #9990524).
// Mismo patrón de request que cobertura-request.js/naps-request.js.
export const getSectoresCapa = async () => {
    let data = null;
    await axios
        .get(`/mapa-red/api/sectores`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
