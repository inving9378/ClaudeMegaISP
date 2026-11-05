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

export const getPermissionsCatalog = async () => {
    let data = { permissions: [] };
    await axios["get"](`/administracion/permisos/catalog`).then((response) => {
        data = response.data;
    }).catch((error) => {
        console.log(error);
        // La ventana de permisos avisa que la pestaña «Otros» no cargó.
        data = { permissions: [], failed: true };
    });
    return data;
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

export const syncRolesToBasePermissions = async () => {
    let data = { ok: false, report: {}, total: 0, message: '' };
    await axios["post"](`/administracion/permisos/sync-roles`, {}).then((res) => {
        data = res.data;
    }).catch((error) => {
        console.log(error);
        throw error;
    });
    return data;
}

export const updatePermissionByRole = async (id, data) => {
    let response = {};
    await axios["post"](`/administracion/permisos/update-permission-for-role/${id}`, data).then((res) => {
        response = res.data;
    }).catch((error) => {
        console.log(error);
        // Para mostrar el motivo (p. ej. «vuelve a cargar la página») en vez de un error genérico.
        response = error.response?.data || {};
    });
    return response;
}
