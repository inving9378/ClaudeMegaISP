export const saveSwitch = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/switchs${object.id ? `/${object.id}` : ""}`,
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

export const destroySwitch = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/switchs/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
