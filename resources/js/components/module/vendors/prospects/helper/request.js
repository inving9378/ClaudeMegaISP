import axios from "axios";

export const getById = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/prospectos/${id}/getById`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};
