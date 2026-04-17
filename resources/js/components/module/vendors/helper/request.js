import axios from "axios";

// Datos del vendedor
export const getAll = async () => {
    let data = [];
    await axios["get"](`/vendedores/data`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/${id}/getDataById`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getStatusSeller = async () => {
    let data = [];
    await axios["get"](`/vendedores/get-status-sellers`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getTypeSeller = async () => {
    let data = [];
    await axios["get"](`/vendedores/get-type-sellers`).then((response) => {
        data = response.data;
    });
    return data;
};

export const updateSeller = async (id, data) => {
    let response = {};
    await axios["post"](`/vendedores/${id}/update`, data).then((res) => {
        response = res.data;
    });
    return response;
};

// Estadisticas
export const getTotalProspects = async () => {
    let data = [];
    await axios["get"](`/vendedores/ventas/total-prospects`).then((res) => {
        data = res.data;
    });
    return data;
};

export const getTotalSales = async () => {
    let data = [];
    await axios["get"](`/vendedores/ventas/total-sales`).then((res) => {
        data = res.data;
    });
    return data;
};

export const getLostSales = async () => {
    let data = [];
    await axios["get"](`/vendedores/ventas/total-lost-sales`).then((res) => {
        data = res.data;
    });
    return data;
};

export const getAllActivities = async () => {
    let data = {};
    await axios["get"](`/get-all-activities`).then((response) => {
        data = response.data;
    });
    return data;
};

export const salesByMedium = async (startDate, endDate) => {
    let data = [];
    await axios["get"](
        `/vendedores/ventas/salesByMedium/${startDate}/${endDate}`
    ).then((res) => {
        data = res.data;
    });
    return data;
};

export const compareSalesByMonth = async () => {
    let data = [];
    await axios["get"](`/vendedores/ventas/salesByMonth`).then((res) => {
        data = res.data;
    });
    return data;
};

export const salesAndProspectsByDateRange = async (startDate, endDate) => {
    let data = [];
    await axios
        .get(
            `/vendedores/ventas/salesAndProspectsByDateRange/${startDate}/${endDate}`
        )
        .then((res) => {
            data = res.data;
        });
    return data;
};

export const prospectsByStatus = async (startDate, endDate) => {
    let data = [];
    await axios["get"](
        `/vendedores/prospectos/statusProspects/${startDate}/${endDate}`
    ).then((res) => {
        data = res.data;
    });
    return data;
};

export const rankingSales = async (startDate, endDate) => {
    let data = [];
    await axios["get"](
        `/vendedores/ventas/rankingSales/${startDate}/${endDate}`
    ).then((res) => {
        data = res.data;
    });
    return data;
};

// Credencial
export const getImageFront = async () => {
    let data = [];
    await axios["get"](`/configuracion/credencial/image-front`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getImageBack = async () => {
    let data = [];
    await axios["get"](`/configuracion/credencial/image-back`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getImageLogo = async () => {
    let data = [];
    await axios["get"](`/configuracion/credencial/image-logo`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const updateCredential = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/credencial/upload`, data).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};
