import axios from "axios";

export const listObservations = async (id) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/observations-list/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const storeObservation = async (params) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/observations`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const updateObservation = async (id, params) => {
    let result = null;
    await axios
        .put(`/sellers/cuts/observations/${id}`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const destroyObservation = async (id) => {
    let result = null;
    await axios
        .delete(`/sellers/cuts/observations/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};
