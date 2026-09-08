// MR-26 Fase 4 (item roadmap #9990525) — capa de cobertura comercial en vivo (Fase 1, #9990522).
// Mismo patrón de request que naps-request.js/enlaces-request.js.
export const getCoberturaCapa = async () => {
    let data = null;
    await axios
        .get(`/mapa-red/api/cobertura/capa`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

// MR-26 Fase 4c (item roadmap #9990528) — "¿hay cobertura vendible en este punto?" (Fase 2, #9990523).
export const consultarCobertura = async (lat, lng) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/cobertura/consultar`, { params: { lat, lng } })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
