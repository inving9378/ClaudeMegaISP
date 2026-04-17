export const getAll = async () => {
    let data = [];
    await axios["get"](`/configuracion/metodos-de-pago/get-all-methods`).then((response) => {
        data = response.data;
    });
    return data;
}

export const editMethodPayment = async (id) => {
    let data = [];
    await axios["get"](`/configuracion/metodos-de-pago/${id}/edit`).then((response) => {
        data = response.data;
    });
    return data;
}

export const createMethodPayment = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/metodos-de-pago/create`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const updateMethodPayment = async (id, data) => {
    let response = {};
    await axios["post"](`/configuracion/metodos-de-pago/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const deleteMethodPayment = async (id) => {
    let response = {};
    await axios["delete"](`/configuracion/metodos-de-pago/${id}/destroy`).then((res) => {
        response = res.data;
    });
    return response;
}