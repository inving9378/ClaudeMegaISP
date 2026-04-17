export const saveOlt = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/olts${object.id ? `/${object.id}` : ""}`,
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

export const destroyOlt = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/olts/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
