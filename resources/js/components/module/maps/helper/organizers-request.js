export const saveOrganizer = async (object) => {
    let data = null;
    await axios[object.id ? "put" : "post"](
        `/maps/organizers${object.id ? `/${object.id}` : ""}`,
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

export const destroyOrganizer = async (id) => {
    let data = null;
    await axios
        .delete(`/maps/organizers/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};
