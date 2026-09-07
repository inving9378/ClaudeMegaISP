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

// MR-21 (item roadmap #957, UI seguimiento #9990490) — dashboard de salud de una NAP (D17):
// semáforo, potencia promedio y tabla de ONUs.
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
