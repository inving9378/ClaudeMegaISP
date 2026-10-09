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

// MR-21 (sesión 2026-10-09) — corrige a mano la potencia de instalación de un NAP (se
// autocaptura sola con la primera lectura real; esto es solo para corregirla).
export const actualizarPotenciaInstalacion = async (layerId, potenciaDbm) => {
    try {
        const response = await axios.put(`/mapa-red/api/naps/${layerId}/potencia-instalacion`, {
            potencia_instalacion_dbm: potenciaDbm,
        });
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e?.response?.data?.message || "No se pudo guardar la potencia de instalación.",
        };
    }
};
