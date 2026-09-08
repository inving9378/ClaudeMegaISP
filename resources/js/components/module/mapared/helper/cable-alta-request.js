// MR-24e Fase 3b (item roadmap #9990582) — catálogo de tipos de cable para el diálogo de
// vista previa del modo dibujo. Mismo patrón que nap-alta-request.js. NO agregar aquí ninguna
// función de POST/crear cable: la persistencia real es Fase 4 (item #9990583).

export const getTiposCable = async () => {
    let data = [];
    await axios
        .get(`/mapa-red/api/catalogos/tipo-cable`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = [];
        });
    return data;
};
