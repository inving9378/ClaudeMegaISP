import axios from "axios";

// filtros: estado, rol, sortBy, descending (opcionales). Un error se propaga: quien llama decide
// (el listado conserva lo que mostraba; exportar avisa en vez de bajar un archivo vacío).
export const getAll = async (page, rowsPerPage, search, filtros = {}) => {
    const response = await axios.get(`/administracion/user/get-all-users`, {
        params: {
            page,
            rowsPerPage,
            search,
            ...filtros,
        },
    });
    return {
        data: response.data.data,
        total: response.data.total,
    };
};

export const getById = async (id) => {
    let data = {};
    await axios["get"](`/user/${id}`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getRoles = async () => {
    let data = [];
    await axios["get"](`/administracion/user/getRoles`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getStates = async () => {
    let data = [];
    await axios["get"](`/administracion/addresses/states`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getMunicipalities = async (id) => {
    let data = {};
    await axios["get"](`/administracion/addresses/${id}/municipalities`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getColonies = async (id) => {
    let data = {};
    await axios["get"](`/administracion/addresses/${id}/colonies`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getStatusSellers = async () => {
    let data = {};
    await axios["get"](`/configuracion/estados-vendedores/get-all-status`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getTypesSellers = async () => {
    let data = {};
    await axios["get"](`/configuracion/tipos-vendedores/get-all-types`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getRules = async () => {
    let data = [];
    await axios["get"](`/configuracion/rules/get-all`).then((response) => {
        data = response.data;
    });
    return data;
};

export const createUser = async (data) => {
    let response = {};
    await axios["post"](`/administracion/user/create`, data).then((res) => {
        response = res.data;
    });
    return response;
};

export const updateUser = async (id, data) => {
    let response = {};
    await axios["post"](`/administracion/user/${id}/update`, data).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const deleteUser = async (id) => {
    let response = {};
    await axios["delete"](`/administracion/user/${id}/destroy`).then((res) => {
        response = res.data;
    });
    return response;
};

export const activeOrInactive = async (id) => {
    let response = {};
    await axios["post"](`/administracion/user/${id}/inactive-or-active`).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const bloquearUser = async (id) => {
    let response = {};
    await axios.patch(`/administracion/user/${id}/bloquear`).then(
        (res) => { response = res.data; }
    );
    return response;
};

export const getPermissionsForUser = async (id) => {
    let data = [];
    await axios["get"](
        `/administracion/permisos/get-permission-for-user/${id}`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const updatePermissionByUser = async (id, data) => {
    let response = {};
    await axios["post"](
        `/administracion/permisos/update-permission-for-user/${id}`,
        data
    )
        .then((res) => {
            response = res.data;
        })
        .catch((error) => {
            console.log(error);
        });
    return response;
};

export const getAvaiablesPromotions = async (code) => {
    let data = null;
    await axios["get"](
        `/administracion/user/avaiables-promotions/${code}`
    ).then((response) => {
        data = response.data;
    });
    return data;
};
