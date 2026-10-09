// MR-08 Fase 3 (item roadmap #9990534) — administración de los 4 catálogos reales
// (cables/conectores/cajas/splitters), consumiendo el CRUD construido en la Fase 2
// (#9990536/#9990538, CatalogosController). Mismo patrón que cable-alta-request.js:
// distingue éxito de error 422 (guard de referencia) para mostrar el mensaje real del backend.

const RECURSOS = {
    cables: "cables",
    conectores: "conectores",
    cajas: "cajas",
    splitters: "splitters",
};

export const listarCatalogo = async (recurso) => {
    try {
        const response = await axios.get(`/mapa-red/api/catalogos/${RECURSOS[recurso]}`);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e?.response?.data?.message || "No se pudo cargar el catálogo.",
        };
    }
};

export const guardarCatalogo = async (recurso, payload, id = null) => {
    try {
        const response = id
            ? await axios.put(`/mapa-red/api/catalogos/${RECURSOS[recurso]}/${id}`, payload)
            : await axios.post(`/mapa-red/api/catalogos/${RECURSOS[recurso]}`, payload);
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message: e?.response?.data?.message || "No se pudo guardar el registro.",
            errors: e?.response?.data?.errors || null,
        };
    }
};

export const eliminarCatalogo = async (recurso, id) => {
    try {
        await axios.delete(`/mapa-red/api/catalogos/${RECURSOS[recurso]}/${id}`);
        return { ok: true };
    } catch (e) {
        return {
            ok: false,
            // 422 = guard de referencia (hay cables/splitters físicos usando este tipo).
            message: e?.response?.data?.message || "No se pudo eliminar el registro.",
        };
    }
};
