import axios from "axios";

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/ventas/${id}/salesBySeller`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getSalesBySeller = async (id, params) => {
    let result = null;
    await axios["post"](`/vendedores/ventas/sales-by-seller/${id}`, params)
        .then((response) => {
            result = { ...response.data };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};
