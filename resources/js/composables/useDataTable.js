const getColumns = async (table_id) => {
    const response = await axios.get(`/setting-table/get/${table_id}`);
    return response.data;
};

const saveColumns = async (table_id, columnsData) => {
    const response = await axios.post(`/setting-table/post/${table_id}`, {
        columnsData,
    });
    return response.data;
};

export function useDataTable() {
    return {
        getColumns,
        saveColumns,
    };
}
