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
