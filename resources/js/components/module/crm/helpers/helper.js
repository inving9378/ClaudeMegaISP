export const updateLastContacted = (id) => {
    axios["post"](`/crm/update-last-contacted/${id}`, {});
}

export const getLogActivities = async (id, module) => {
    let data = {}
    await axios["post"](`/get-log-activities/${id}`, {module}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getCrmClientIfExistInDb = async (json) => {
    let data = {}
    await axios["post"](`/get-crm-client-if-exist`, {json}).then((response) => {
        data = response.data;
    });
    return data;
}
