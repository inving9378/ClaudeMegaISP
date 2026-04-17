export const getAll = async () => {
    let data = [];
    await axios["get"](`/configuracion/medios-de-venta/get-mediums-sales`).then((response) => {
        data = response.data;
    });
    return data;
}

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/configuracion/medios-de-venta/${id}/get-by-id`).then((response) => {
        data = response.data;
    });
    return data;
}

export const createMediumSale = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/medios-de-venta/create`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const updateMediumSale = async (id, data) => {
    let response = {};
    await axios["post"](`/configuracion/medios-de-venta/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const deleteMediumSale = async (id) => {
    let response = {};
    await axios["delete"](`/configuracion/medios-de-venta/${id}/destroy`).then((res) => {
        response = res.data;
    });
    return response;
}