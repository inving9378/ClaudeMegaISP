export const requestCreateClientInvoice = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/invoice/create-new-client-invoice/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}
