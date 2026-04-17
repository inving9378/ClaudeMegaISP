import axios from "axios";

export const listInstallations = async (id) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/installations-list/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const storeInstallation = async (params) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/installations`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const updateInstallation = async (id, params) => {
    let result = null;
    await axios
        .put(`/sellers/cuts/installations/${id}`, params)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const destroyInstallation = async (id) => {
    let result = null;
    await axios
        .delete(`/sellers/cuts/installations/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};
