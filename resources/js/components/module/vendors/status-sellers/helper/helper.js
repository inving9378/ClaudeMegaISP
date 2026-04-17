export const getAll = async () => {
    let data = [];
    await axios["get"](`/configuracion/estados-vendedores/get-all-status`).then((response) => {
        data = response.data;
    });
    return data;
}

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/configuracion/estados-vendedores/${id}/edit`).then((response) => {
        data = response.data;
    });
    return data;
}

export const createStatusSeller = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/estados-vendedores/create`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const updateStatusSeller = async (id, data) => {
    let response = {};
    await axios["post"](`/configuracion/estados-vendedores/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const deleteStatusSeller = async (id) => {
    let response = {};
    await axios["delete"](`/configuracion/estados-vendedores/${id}/destroy`).then((res) => {
        response = res.data;
    });
    return response;
}