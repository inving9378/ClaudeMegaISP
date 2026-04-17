export const requestPerfilJson = async (id) => {
    let fields = {}
    await axios["post"](`/perfil/get-perfil-by-id/${id}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}
