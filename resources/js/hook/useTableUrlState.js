import { onBeforeUnmount } from "vue";
import { debounce } from "lodash";
import { currentHistoryState, currentSearch } from "./comunValues";

const CLAVE_STATE = "estadoTablas";

/**
 * Conserva el estado de un listado en su entrada del historial, para que sobreviva a atrás/adelante
 * (spa-nav remonta la vista) y a F5.
 *
 * - `url`: lo que puede verse en la barra de direcciones (query string).
 * - `privado`: lo que no debe salir del navegador, como el término de búsqueda (#9990815). Va en
 *   history.state: no llega a los logs del servidor, y aun así sobrevive a atrás/adelante y a F5.
 *
 * Solo escribe con replaceState y conserva lo demás que haya en history.state (spa-nav guarda ahí
 * `{ spa, url }`) y en la query string.
 *
 * @param {string[]} parametros Nombres de los parámetros de URL que administra.
 * @param {string}   prefijo    Para vistas con más de una tabla: prefija parámetros y clave de state.
 * @param {number}   esperaMs   Debounce de escritura.
 */
export function useTableUrlState({ parametros, prefijo = "", esperaMs = 300 }) {
    const clave = prefijo || "tabla";
    const nombre = (parametro) => `${prefijo}${parametro}`;

    const leer = () => {
        const query = new URLSearchParams(currentSearch());
        const url = {};
        parametros.forEach((parametro) => {
            const valor = query.get(nombre(parametro));
            if (valor !== null) url[parametro] = valor;
        });
        return { url, privado: currentHistoryState()?.[CLAVE_STATE]?.[clave] ?? {} };
    };

    const escribir = (url, privado) => {
        const destino = new URL(window.location.href);
        parametros.forEach((parametro) => {
            const valor = url[parametro];
            if (valor === null || valor === undefined || valor === "") {
                destino.searchParams.delete(nombre(parametro));
            } else {
                destino.searchParams.set(nombre(parametro), valor);
            }
        });
        // Comas y dos puntos son válidos en una query: sin esto, estado y fechas se vuelven ilegibles.
        destino.search = destino.search.replace(/%2C/gi, ",").replace(/%3A/gi, ":");

        const anterior = window.history.state ?? {};
        const tablas = { ...(anterior[CLAVE_STATE] ?? {}) };
        // El scroll (#1163) lo escribe guardarScroll al abrir una fila: no lo pisa el guardado de filtros.
        const scrollY = anterior[CLAVE_STATE]?.[clave]?.scrollY;
        const conScroll = scrollY !== undefined ? { ...privado, scrollY } : privado;
        if (Object.keys(conScroll).length > 0) {
            tablas[clave] = conScroll;
        } else {
            delete tablas[clave];
        }

        const sinCambios =
            destino.href === window.location.href &&
            JSON.stringify(tablas) === JSON.stringify(anterior[CLAVE_STATE] ?? {});
        if (sinCambios) return;

        const siguiente = { ...anterior };
        if (Object.keys(tablas).length > 0) {
            siguiente[CLAVE_STATE] = tablas;
        } else {
            delete siguiente[CLAVE_STATE];
        }
        if (siguiente.url) siguiente.url = destino.href;

        window.history.replaceState(
            Object.keys(siguiente).length > 0 ? siguiente : null,
            "",
            destino.href
        );
    };

    const escribirConEspera = debounce(escribir, esperaMs);

    // Al volver o avanzar, window.location ya es la entrada DESTINO mucho antes de que spa-nav
    // desmonte esta vista: escribir desde aquí pisaría el historial de otra pantalla.
    let detenido = false;
    const alPopstate = () => {
        detenido = true;
        escribirConEspera.cancel();
    };
    window.addEventListener("popstate", alPopstate);

    // Al navegar hacia adelante, spa-nav desmonta ANTES de su pushState: la entrada actual sigue
    // siendo la de este listado, así que aquí se guarda lo último que se haya tecleado.
    onBeforeUnmount(() => {
        window.removeEventListener("popstate", alPopstate);
        escribirConEspera.flush();
    });

    // #1163: al abrir una fila se recuerda el scroll en la entrada actual del historial; al volver con
    // atrás se restaura cuando ya hay filas (spa-nav hace scrollTo(0,0) al montar, las filas llegan después).
    const scrollPendiente = Number(currentHistoryState()?.[CLAVE_STATE]?.[clave]?.scrollY) || 0;

    const guardarScroll = () => {
        if (detenido) return;
        const anterior = window.history.state ?? {};
        const tablas = { ...(anterior[CLAVE_STATE] ?? {}) };
        tablas[clave] = { ...(tablas[clave] ?? {}), scrollY: Math.round(window.scrollY) };
        window.history.replaceState({ ...anterior, [CLAVE_STATE]: tablas }, "", window.location.href);
    };

    const alClic = (e) => {
        if (e.target?.closest?.("a[href]")) guardarScroll();
    };
    window.addEventListener("click", alClic, true);
    onBeforeUnmount(() => window.removeEventListener("click", alClic, true));

    let restaurado = false;
    const restaurarScroll = () => {
        if (restaurado || scrollPendiente <= 0) return;
        restaurado = true;
        requestAnimationFrame(() => window.scrollTo(0, scrollPendiente));
    };

    const guardar = (url, privado = {}) => {
        if (!detenido) escribirConEspera(url, privado);
    };

    return { leer, guardar, restaurarScroll };
}
