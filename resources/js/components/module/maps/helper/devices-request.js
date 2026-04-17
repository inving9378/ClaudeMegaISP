export const saveDevice = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/devices${object.id ? `/${object.id}` : ""}`,
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

export const destroyDevice = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/devices/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const savePortDevice = async (object) => {
    let data = null;
    await axios
        .post(`/maps/devices/save-port/${object.id}`, object)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const addPorts = async (id, ports) => {
    let data = null;
    await axios
        .post(`/maps/devices/add-ports/${id}`, { ports })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const changeCardOLTDirection = async (id, card, order) => {
    let data = null;
    await axios
        .post(`/maps/devices/change-card-olt-direction/${id}`, { card, order })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
