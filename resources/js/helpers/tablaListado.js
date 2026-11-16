/**
 * Listados con class="tabla-listado" (#1342): las celdas largas se cortan con «…». Al pasar el
 * ratón por una celda cortada se le pone su texto completo como tooltip; las que caben no lo llevan.
 *
 * Uso: <q-table class="tabla-listado" @mouseover="tituloSiCorta" …>
 */
export function tituloSiCorta(evento) {
    const celda = evento.target?.closest?.("td");
    if (!celda) return;

    if (celda.scrollWidth > celda.clientWidth) {
        celda.title = celda.innerText.trim();
        celda.dataset.tituloCorto = "1";
    } else if (celda.dataset.tituloCorto) {
        celda.removeAttribute("title");
        delete celda.dataset.tituloCorto;
    }
}
