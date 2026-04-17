import axios from "axios";

export const getAll = async () => {
    let data = [];
    await axios["get"](`/administracion/rol/get-all`).then((response) => {
        data = response.data;
    });
    return data;
}

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/administracion/rol/editar-role/${id}`).then((response) => {
        data = response.data;
    });
    return data;
}

export const create = async (data) => {
    let response = {};
    await axios["post"](`/administracion/rol/add`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const update = async (id, data) => {
    let response = {};
    await axios["post"](`/administracion/rol/update-role/${id}`, data).then((res) => {
        response = res.data;
    });
    return response;
}

export const deleteRol = async (id) => {
    let response = {};
    await axios["delete"](`/administracion/rol/destroy/${id}`).then((res) => {
        response = res.data;
    });
    return response;
}

export const getPermissionsForRole= async (id) => {
    let data = [];
    await axios["get"](`/administracion/permisos/get-permission-for-role/${id}`).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestPermissionForRole = async (rol) => {
    let fields = {}
    await axios["post"](`/administracion/permisos/get-permission-for-role/${rol}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const updatePermissionByRole = async (id, data) => {
    let response = {};
    await axios["post"](`/administracion/permisos/update-permission-for-role/${id}`, data).then((res) => {
        response = res.data;
    }).catch((error) => {
        console.log(error);
    });
    return response;
}
