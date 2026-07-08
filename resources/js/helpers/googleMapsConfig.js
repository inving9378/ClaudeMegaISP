/**
 * Fuente ÚNICA (cliente) de la key de Google Maps: el servidor (BD/env) vía
 * /configuracion/credenciales-google-maps/render-config. NO se hornea en el
 * bundle (process.env.MIX_VUE_APP_* no se inyectaba → quedaba undefined).
 * Devuelve { api_key, latitude, longitude, zoom } o null.
 */
export const fetchGoogleMapsConfig = async () => {
    try {
        const { data } = await axios.get(
            "/configuracion/credenciales-google-maps/render-config"
        );
        return Array.isArray(data) ? data[0] : data;
    } catch (e) {
        return null;
    }
};
