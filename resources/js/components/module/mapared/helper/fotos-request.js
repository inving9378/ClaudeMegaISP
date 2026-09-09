// MR-23 fase 4c (item roadmap #9990544) — fotos adjuntas a nodos/enlaces del Mapa de Red.
// Consume los 3 endpoints de FotosController (backend, item #9990541). `tipo` es la clave
// corta del allowlist del controller ('layer', 'device', 'proyecto', 'enlace', 'fiber'),
// NO el nombre completo de la clase PHP. Mismo patrón try/catch que empalmes-request.js.
export const getFotos = async (tipo, id) => {
    try {
        const response = await axios.get(`/mapa-red/api/fotos/${tipo}/${id}`);
        return response.data;
    } catch (e) {
        return null;
    }
};

export const subirFoto = async (tipo, id, file, caption = null) => {
    const formData = new FormData();
    formData.append("foto", file);
    if (caption) formData.append("caption", caption);

    try {
        const response = await axios.post(`/mapa-red/api/fotos/${tipo}/${id}`, formData, {
            headers: {
                "Content-Type": "multipart/form-data",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),
            },
        });
        return { ok: true, data: response.data };
    } catch (e) {
        return {
            ok: false,
            message:
                e.response?.data?.errors?.foto?.[0] ||
                e.response?.data?.message ||
                "No se pudo subir la foto.",
        };
    }
};

export const eliminarFoto = async (fotoId) => {
    try {
        await axios.delete(`/mapa-red/api/fotos/${fotoId}`);
        return { ok: true };
    } catch (e) {
        return {
            ok: false,
            message: e.response?.data?.message || "No se pudo eliminar la foto.",
        };
    }
};
