export const saveRack = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/sites/racks${object.id ? `/${object.id}` : ""}`,
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

export const destroyRack = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/sites/racks/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
