// MR-20 (item roadmap #956) — ocupación de puertos por NAP en lote (D16).
export const getOcupacionLote = async (puertableType, ids) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/naps/ocupacion-lote`, {
            params: { puertable_type: puertableType, ids },
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

// MR-21 (item roadmap #957/#9990489) — semáforo de salud por NAP (D17), alimentado por MultiOLT.
export const getSaludLote = async (puertableType, ids) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/naps/salud-lote`, {
            params: { puertable_type: puertableType, ids },
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

// Dashboard de salud de una sola NAP (consumido por el side panel, MR-21 parte 2/2).
export const getSalud = async (puertableType, puertableId) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/naps/salud`, {
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
