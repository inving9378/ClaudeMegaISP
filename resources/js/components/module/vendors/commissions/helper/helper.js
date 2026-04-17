import axios from "axios";

export const getTypeSellers = async () => {
    let data = [];
    await axios["get"](`/configuracion/comisiones/get-types-sellers`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getSellersByType = async (id) => {
    let data = [];
    await axios["get"](
        `/configuracion/reglas-comisiones/get-sellers-by-type/${id}`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const getSellers = async () => {
    let data = [];
    await axios["get"](`/vendedores/data`).then((response) => {
        data = response.data;
    });
    return data;
};

export const createCommission = async (data) => {
    let response = {};
    await axios["post"](
        `/configuracion/comisiones/create-comision-internal-distributor`,
        data
    ).then((res) => {
        response = res.data;
    });
    return response;
};

export const getRuleById = async (id) => {
    let response = {};
    await axios["get"](
        `/configuracion/reglas-comisiones/get-rule-by-id/${id}`
    ).then((res) => {
        response = res.data;
    });
    return response;
};

export const createVendorRule = async (data) => {
    let response = {};
    await axios["post"](`/configuracion/reglas-comisiones/create`, data).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const updateVendorRule = async (id, data) => {
    let response = {};
    await axios["post"](
        `/configuracion/reglas-comisiones/update/${id}`,
        data
    ).then((res) => {
        response = res.data;
    });
    return response;
};

export const deleteVendorRule = async (id) => {
    let response = {};
    await axios["delete"](`/configuracion/reglas-comisiones/delete/${id}`).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const getAllRules = async (page, rowsPerPage) => {
    let result = { data: [], total: 0 };
    await axios
        .get(`/configuracion/reglas-comisiones/get-all-rules`, {
            params: {
                page,
                rowsPerPage,
            },
        })
        .then((response) => {
            result = {
                data: response.data.data,
                total: response.data.total,
            };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const getAllRangesOfSales = async () => {
    let data = [];
    await axios["get"](`/configuracion/rangos-venta/get-all-ranges-sales`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getSellersByRule = async (id) => {
    let response = {};
    await axios["get"](
        `/configuracion/reglas-comisiones/get-sellers/${id}`
    ).then((res) => {
        response = res.data;
    });
    return response;
};
