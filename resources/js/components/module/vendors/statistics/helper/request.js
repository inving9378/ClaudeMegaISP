export const salesAndProspects = async (id, range) => {
    let data = [];
    await axios
        .post(`/statics/sales-and-prospects${id ? `/${id}` : ""}`, { range })
        .then((res) => {
            data = res.data;
        })
        .catch(() => {
            data = null;
        });
    return data;
};

export const salesByMedium = async (id, range) => {
    let data = [];
    await axios
        .post(`/statics/sales-by-medium${id ? `/${id}` : ""}`, {
            range,
        })
        .then((res) => {
            data = res.data;
        });
    return data;
};

export const compareSalesByMonth = async (id) => {
    let data = [];
    await axios.post(`/statics/compare-sales${id ? `/${id}` : ""}`).then((res) => {
        data = res.data;
    });
    return data;
};

export const prospectsByStatus = async (id, range) => {
    let data = [];
    await axios
        .post(`/statics/prospects-by-status${id ? `/${id}` : ""}`, { range })
        .then((res) => {
            data = res.data;
        });
    return data;
};

export const getActivities = async (id) => {
    let data = null;
    await axios["get"](
        id ? `/get-activities-by-user/${id}` : "/get-all-activities"
    )
        .then((response) => {
            data = response.data;
        })
        .catch(() => {
            data = null;
        });
    return data;
};

export const rankingSales = async (range) => {
    let data = [];
    await axios
        .post(`/statics/ranking-sales`, { range })
        .then((res) => {
            data = res.data;
        })
        .catch(() => {
            data = null;
        });
    return data;
};

export const getTotalProspects = async () => {
    let data = [];
    await axios.post(`/statics/total-prospects`).then((res) => {
        data = res.data;
    });
    return data;
};

export const getTotalSales = async () => {
    let data = [];
    await axios.post(`/statics/total-sales`).then((res) => {
        data = res.data;
    });
    return data;
};

export const getLostSales = async () => {
    let data = [];
    await axios.post(`/statics/total-lost-sales`).then((res) => {
        data = res.data;
    });
    return data;
};
