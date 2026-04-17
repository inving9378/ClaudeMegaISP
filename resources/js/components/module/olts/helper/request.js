import axios from "axios";

export const getOLTData = async (url, params = null) => {
    let result = [];
    await axios
        .post(url, params)
        .then((response) => {
            result = response.data;
        })
        .catch((e) => {
            result = [];
        });
    return result;
};

export const getOLTs = async (params = null) => {
    return await getOLTData("/olts/list", params);
};

export const getONUs = async (params = null) => {
    return await getOLTData("/olts/onus/configured", params);
};

export const getUnconfiguredOnus = async (olt = null, params = null) => {
    return await getOLTData(
        olt ? `/olts/onus/unconfigured/${olt}` : "/olts/onus/unconfigured",
        params
    );
};

export const getSavedUnconfiguredOnus = async () => {
    return await getOLTData("/olts/onus/saved-unconfigured");
};

export const getNomenclatures = async (nomenclatures = null) => {
    return await getOLTData("/olts/nomenclatures", { nomenclatures });
};

export const getSignal = async (id) => {
    return await getOLTData(`/olts/onus/signal/${id}`);
};

export const getOnuByClient = async (id) => {
    return await getOLTData(`/olts/onus/get-by-client/${id}`);
};
