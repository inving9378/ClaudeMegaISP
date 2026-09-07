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

// MR-19 Fase 2 (item roadmap #9990566). Consume el endpoint de carta agrupada de la Fase 1
// (item #9990565, GET /mapa-red/api/empalmes/carta) — mismo patrón de query params que existentes().
export const getCartaEmpalme = async (elementoContenedorType, elementoContenedorId) => {
    let data = null;
    await axios
        .get(`/mapa-red/api/empalmes/carta`, {
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

// URL del PDF (Fase 1, decisión libre de la fase backend): misma convención de query string que
// el resto de los endpoints de empalmes para no pelear con el nombre completo de la clase PHP
// (con backslashes) en un segmento de ruta.
export const cartaEmpalmePdfUrl = (elementoContenedorType, elementoContenedorId) => {
    const params = new URLSearchParams({
        elemento_contenedor_type: elementoContenedorType,
        elemento_contenedor_id: elementoContenedorId,
    });
    return `/mapa-red/empalmes/carta-pdf?${params.toString()}`;
};
