import axios from "axios";

export const listSuppliersExpenses = async (id) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/suppliers-expenses-list/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const storeSuppliersExpenses = async (params) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/suppliers-expenses`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const updateSuppliersExpenses = async (id, params) => {
    let result = null;
    await axios
        .put(`/sellers/cuts/suppliers-expenses/${id}`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const destroySuppliersExpenses = async (id) => {
    let result = null;
    await axios
        .delete(`/sellers/cuts/suppliers-expenses/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};
