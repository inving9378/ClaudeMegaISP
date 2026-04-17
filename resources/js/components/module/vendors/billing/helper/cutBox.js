import axios from "axios";

export const getCurrentBox = async (id) => {
    let result = null;
    await axios
        .get(`/sellers/cuts/get-user-current-box/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const findBox = async (id) => {
    let result = null;
    await axios
        .get(`/sellers/cuts/box/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const getReceivedPaymentsByBox = async (id) => {
    let result = null;
    await axios
        .get(`/sellers/cuts/get-received-payments-by-box/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const close = async (id) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/close-user-current-box/${id}`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const cuts = async (id, props) => {
    let result = null;
    await axios
        .post(`/sellers/cuts/${id}`, { ...props })
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};
