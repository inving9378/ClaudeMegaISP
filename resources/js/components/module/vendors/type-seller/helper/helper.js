export const getAll = async () => {
    let data = [];
    await axios["get"](`/configuracion/tipos-vendedores/get-all-types`).then((response) => {
        data = response.data;
    });
    return data;
}

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/configuracion/tipos-vendedores/${id}/edit`).then((response) => {
        data = response.data;
    });
    return data;
}

export const createTypeSeller = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/tipos-vendedores/create`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const updateTypeSeller = async (id, data) => {
    let response = {};
    await axios["post"](`/configuracion/tipos-vendedores/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const deleteTypeSeller = async (id) => {
    let response = {};
    await axios["delete"](`/configuracion/tipos-vendedores/${id}/destroy`).then((res) => {
        response = res.data;
    });
    return response;
}