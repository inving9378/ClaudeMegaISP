export const getAll = async () => {
    let data = [];
    await axios["get"](`/configuracion/rangos-venta/get-all-ranges-sales`).then((response) => {
        data = response.data;
    });
    return data;
}

export const editRange = async (id) => {
    let data = [];
    await axios["get"](`/configuracion/rangos-venta/${id}/edit`).then((response) => {
        data = response.data;
    });
    return data;
}

export const update = async (id, data) => {
    let response = {};
    await axios["post"](`/configuracion/rangos-venta/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
}