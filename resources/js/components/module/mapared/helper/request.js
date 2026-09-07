const route = "/configuracion/credenciales-google-maps";

export const createMap = async (obj) => {
    let data = {};
    await axios["post"](`${route}/create`, obj).then((response) => {
        data = response.data;
    });
    return data;
};

export const getMap = async (id, obj) => {
    let data = null;
    await axios["get"](`${route}/edit`, obj).then((response) => {
        data = response.data[0];
    });
    return data;
};

// Config para el RENDER del mapa: devuelve la api_key REAL (no enmascarada).
// getMap()/edit se reserva para la pantalla de config (key enmascarada).
export const getMapRenderConfig = async () => {
    let data = null;
    await axios["get"](`${route}/render-config`).then((response) => {
        data = response.data;
    });
    return data;
};

export const updateMap = async (id, obj) => {
    let data = {};
    await axios["post"](`${route}/${id}/update`, obj).then((response) => {
        data = response.data;
    });
    return data;
};

export const deleteMap = async (id) => {
    let data = {};
    await axios["delete"](`${route}/${id}/destroy`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getProjects = async () => {
    let data = [];
    await axios["get"]("/mapa-red/api/projects")
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};

export const saveProject = async (id = null, params) => {
    let data = null;
    await axios[id ? "put" : "post"](
        `/mapa-red/api/projects${id ? `/${id}` : ""}`,
        params
    )
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const destroyLayers = async (layers) => {
    let data = null;
    await axios["post"]("/mapa-red/api/layers/destroy-multiple", { layers })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const updateCoordinates = async (id, layer) => {
    let data = null;
    await axios["post"](`/mapa-red/api/layers/coords/${id}`, layer)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const saveObject = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/mapa-red/api/layers${object.id ? `/${object.id}` : ""}`,
        object
    )
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getLayers = async () => {
    let data = [];
    await axios["get"]("/mapa-red/api/layers")
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};

export const getClientsWithoutProject = async () => {
    let data = [];
    await axios["post"]("/mapa-red/api/projects/clients-without-project")
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};

export const destroyObject = async (node) => {
    let data = null,
        route = node.coords ? "layers" : "projects",
        id = node.layer ? node.layer.id : node.id;
    await axios["delete"](`/mapa-red/api/${route}/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const loadKMZ = async (node, file) => {
    let data = null;
    const formData = new FormData();
    formData.append("file", file);
    await axios["post"](`/mapa-red/api/kmz${node ? "/" + node : ""}`, formData, {
        headers: {
            "Content-Type": "multipart/form-data",
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content"),
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

// MR-25 Fase 2 (item #9990442) — wizard de importación con previsualización.
export const previsualizarKml = async (file) => {
    let data = null;
    const formData = new FormData();
    formData.append("file", file);
    await axios["post"](`/mapa-red/api/import/kml/preview`, formData, {
        headers: {
            "Content-Type": "multipart/form-data",
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content"),
        },
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = { error: e?.response?.data?.error ?? "Error al previsualizar el archivo" };
        });
    return data;
};

export const confirmarKml = async (items, projectId) => {
    let data = null;
    await axios["post"](`/mapa-red/api/import/kml/commit`, {
        items,
        project_id: projectId,
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

// MR-25 Fase 3b (item #9990444) — CSV con auto-detección de columnas. El preview puede
// devolver 422 con `{errores: [...]}` (política de Irving: valida TODO el archivo antes,
// aborta si hay cualquier fila inválida) — se propaga tal cual para que el wizard lo distinga
// de un preview normal.
export const previsualizarCsv = async (file) => {
    let data = null;
    const formData = new FormData();
    formData.append("file", file);
    await axios["post"](`/mapa-red/api/import/csv/preview`, formData, {
        headers: {
            "Content-Type": "multipart/form-data",
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content"),
        },
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = e?.response?.data ?? { error: "Error al previsualizar el archivo" };
        });
    return data;
};

export const confirmarCsv = async (items, projectId) => {
    let data = null;
    await axios["post"](`/mapa-red/api/import/csv/commit`, {
        items,
        project_id: projectId,
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const convertToNetwork = async (props) => {
    let data = null;
    await axios["post"]("/mapa-red/api/change-classification", props)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const addClientToServiceBox = async (client, box) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/client-to-service-box/${client}/${box}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const removeClientFromServiceBox = async (id, params) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/service-box/remove-client/${id}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getSelectedClients = async (box, params) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/service-box/selected-clients/${box}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getAvaiablesClients = async (params) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/service-box/avaiables-clients`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const addClientsToServiceBox = async (box, params) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/service-box/add-clients/${box}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const removeClientsFromServiceBox = async (clients) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/service-box/remove-clients`, { clients })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const moveNode = async (node, to, type, positions) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/projects/move-${type}/${node}${to ? "/" + to : ""}`, {
            positions,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const convertFromProject = async (id, to) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/layers/convert-from-project/${id}`, {
            to,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const convertFromLayer = async (id, to) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/layers/convert-from-layer/${id}`, {
            to,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const convertFromTickeds = async (ids, to) => {
    let data = null;
    await axios
        .post(`/mapa-red/api/layers/convert-from-tickeds`, {
            ids,
            to,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const zones = async () => {
    let data = null;
    await axios
        .get(`/mapa-red/api/zones`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
