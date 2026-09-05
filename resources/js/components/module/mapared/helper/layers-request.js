export const getLayerConfig = async (id) => {
    let data = null;
    await axios
        .post(`/maps/layers/configuration/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getAvaiablesRoutes = async (id, all = false) => {
    let data = null;
    await axios
        .post(`/maps/layers/avaiables-routes${id ? `/${id}` : ""}`, { all })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const assignRoutes = async (id, routes) => {
    let data = null;
    await axios
        .post(`/maps/layers/assign-routes/${id}`, {
            routes,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const unassignRoute = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/layers/unassign-route/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const changeRoutePosition = async (id, params) => {
    let data = null;
    await axios
        .post(`/maps/layers/change-route-position/${id}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const createInput = async (id, params) => {
    let data = null;
    await axios
        .post(`/maps/layers/create-input/${id}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const updateInput = async (id, params) => {
    let data = null;
    await axios
        .post(`/maps/layers/update-input/${id}`, params)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const updateMarkersDistanceFromRoute = async (id, markers) => {
    let data = null;
    await axios
        .post(`/maps/layers/update-markers-distance-from-route/${id}`, {
            markers,
        })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
