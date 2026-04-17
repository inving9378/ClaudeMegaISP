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
    await axios["get"]("/maps/projects")
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
        `/maps/projects${id ? `/${id}` : ""}`,
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
    await axios["post"]("/maps/layers/destroy-multiple", { layers })
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
    await axios["post"](`/maps/layers/coords/${id}`, layer)
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
        `/maps/layers${object.id ? `/${object.id}` : ""}`,
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
    await axios["get"]("/maps/layers")
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
    await axios["post"]("/maps/clients-without-project")
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
    await axios["delete"](`/maps/${route}/${id}`)
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
    await axios["post"](`/maps/kmz${node ? "/" + node : ""}`, formData, {
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

export const convertToNetwork = async (props) => {
    let data = null;
    await axios["post"]("/maps/change-classification", props)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const saveSplitter = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/splitters${object.id ? `/${object.id}` : ""}`,
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

export const destroySplitter = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/splitters/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const splittersFromBox = async (id) => {
    let data = null;
    await axios
        .get(`/maps/splitters-from-box/${id}`)
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
        .post(`/maps/client-to-service-box/${client}/${box}`)
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
        .post(`/maps/service-box/remove-client/${id}`, params)
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
        .post(`/maps/service-box/selected-clients/${box}`, params)
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
        .post(`/maps/service-box/avaiables-clients`, params)
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
        .post(`/maps/service-box/add-clients/${box}`, params)
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
        .post(`/maps/service-box/remove-clients`, { clients })
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
        .post(`/maps/projects/move-${type}/${node}${to ? "/" + to : ""}`, {
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
        .post(`/maps/layers/convert-from-project/${id}`, {
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
        .post(`/maps/layers/convert-from-layer/${id}`, {
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
        .post(`/maps/layers/convert-from-tickeds`, {
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
        .get(`/maps/zones`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
