/**
 * Preferencias de vista del usuario en sesión (tabla preferencias_vista_usuario). Las vistas y
 * preferencias permitidas se declaran en config/preferencias_vista.php.
 */
export function usePreferenciasVista(vista) {
    const cargar = async () => {
        const response = await axios.get(`/preferencias-vista/${vista}`);
        return response.data?.preferencias ?? {};
    };

    const guardar = async (preferencia, valor) => {
        const response = await axios.put(`/preferencias-vista/${vista}/${preferencia}`, { valor });
        return response.data;
    };

    // Borra la preferencia: la vista vuelve a su valor inicial.
    const restablecer = (preferencia) => guardar(preferencia, null);

    return { cargar, guardar, restablecer };
}
