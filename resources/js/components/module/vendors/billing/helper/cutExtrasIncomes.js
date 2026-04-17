import axios from "axios";

export const listExtraIncome = async (id) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/extras-incomes-list/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const storeExtraIncome = async (params) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/extras-incomes`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const updateExtraIncome = async (id, params) => {
    let result = null;
    await axios
        .put(`/sellers/cuts/extras-incomes/${id}`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const destroyExtraIncome = async (id) => {
    let result = null;
    await axios
        .delete(`/sellers/cuts/extras-incomes/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};
