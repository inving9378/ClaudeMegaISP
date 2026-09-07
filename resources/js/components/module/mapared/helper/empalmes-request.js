// MR-12 Fase B (item roadmap #9990408) — panel de unión de hilos. Consume los 4 endpoints
// de EmpalmesController (Fase A, item #9990501). Mismo patrón de request que enlaces-request.js.
export const getEmpalmesExistentes = async (elementoContenedorType, elementoContenedorId) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/empalmes/existentes`, {
            params: {
                elemento_contenedor_type: elementoContenedorType,
                elemento_contenedor_id: elementoContenedorId,
            },
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getHilosDisponibles = async (params) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/empalmes/disponibles`, { params })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const crearEmpalme = async (payload) => {
    try {
        const response = await axios.post(`/mapa-red/api/empalmes`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e.response?.data?.message || "No se pudo crear la unión.",
        };
    }
};

export const eliminarEmpalme = async (id) => {
    try {
        await axios.delete(`/mapa-red/api/empalmes/${id}`);
        return { ok: true };
    } catch (e) {
        return {
            ok: false,
            message: e.response?.data?.message || "No se pudo eliminar la unión.",
        };
    }
};
