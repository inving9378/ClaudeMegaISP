export const saveConnection = async (params) => {
    let data = null;
    await axios[params.id ? "put" : "post"](
        `/maps/connections${params.id ? `/${params.id}` : ""}`,
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

export const saveConnectionMultiple = async (id, connections) => {
    let data = null;
    await axios
        .post(`/maps/connections-multiple/${id}`, { connections })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const destroyConnection = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/connections/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const cutConnections = async (layer_id, cuts, removed, updated) => {
    let data = null;
    await axios
        .post(`/maps/connections/cut/${layer_id}`, {
            cuts,
            removed,
            updated,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const removeClientFromDrop = async (id) => {
    let data = null;
    await axios
        .post(`/maps/service-box/remove-client-from-drop/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
