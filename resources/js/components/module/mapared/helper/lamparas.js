// Item #1240 (lámpara F2): gestor de N lámparas sobre Leaflet.
// Cada lámpara = un recorrido iluminado en su propia capa (color de paleta fija, sin rojo:
// el rojo es de alertas), con halo + línea gruesa. Una leyenda flotante lista las encendidas
// y permite apagar una o todas. Independiente de trazoLayer.
export const PALETA_LAMPARAS = [
    "#f59e0b", // ámbar
    "#06b6d4", // cian
    "#a855f7", // violeta
    "#22c55e", // verde
    "#ec4899", // rosa
    "#3b82f6", // azul
    "#84cc16", // lima
    "#14b8a6", // turquesa
];

const par = (c) => (Array.isArray(c) ? [Number(c[0]), Number(c[1])] : [Number(c.lat), Number(c.lng)]);

export function crearGestorLamparas(L, map) {
    const lamparas = new Map(); // id -> {id, etiqueta, color, layer, bounds, completa}
    let usados = 0;
    let control = null;
    let cont = null;

    const esc = (s) =>
        String(s ?? "").replace(/[&<>"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]));

    const pintarLeyenda = () => {
        if (!cont) return;
        if (!lamparas.size) {
            cont.style.display = "none";
            cont.innerHTML = "";
            return;
        }
        cont.style.display = "block";
        let html = '<div style="font-weight:600;margin-bottom:4px">💡 Lámparas encendidas</div>';
        lamparas.forEach((l) => {
            html +=
                `<div style="display:flex;align-items:center;gap:6px;margin:2px 0">` +
                `<span style="width:14px;height:4px;border-radius:2px;background:${l.color};flex:none"></span>` +
                `<a href="#" data-zoom="${esc(l.id)}" style="flex:1;color:inherit">${esc(l.etiqueta)}${l.completa ? "" : " ⚠"}</a>` +
                `<a href="#" data-off="${esc(l.id)}" title="Apagar" style="text-decoration:none">✕</a></div>`;
        });
        if (lamparas.size > 1) {
            html += '<a href="#" data-off-all="1" style="display:block;margin-top:4px;text-align:right">Apagar todas</a>';
        }
        cont.innerHTML = html;
    };

    const apagar = (id) => {
        const l = lamparas.get(id);
        if (!l) return;
        map.removeLayer(l.layer);
        lamparas.delete(id);
        pintarLeyenda();
    };
    const apagarTodas = () => {
        [...lamparas.keys()].forEach(apagar);
        usados = 0;
    };

    const montarControl = () => {
        if (control) return;
        const Ctl = L.Control.extend({
            options: { position: "bottomleft" },
            onAdd() {
                cont = L.DomUtil.create("div", "mapared-lamparas-leyenda");
                cont.style.cssText =
                    "display:none;background:rgba(255,255,255,.95);padding:8px 10px;border-radius:6px;" +
                    "box-shadow:0 1px 5px rgba(0,0,0,.4);font-size:12px;color:#222;min-width:180px;max-width:260px";
                L.DomEvent.disableClickPropagation(cont);
                L.DomEvent.disableScrollPropagation(cont);
                cont.addEventListener("click", (e) => {
                    const a = e.target.closest("a");
                    if (!a) return;
                    e.preventDefault();
                    if (a.dataset.offAll) apagarTodas();
                    else if (a.dataset.off !== undefined) apagar(a.dataset.off);
                    else if (a.dataset.zoom !== undefined) zoom(a.dataset.zoom);
                });
                return cont;
            },
        });
        control = new Ctl();
        control.addTo(map);
    };

    const zoom = (id) => {
        const l = lamparas.get(id);
        if (l?.bounds?.length) map.fitBounds(l.bounds, { padding: [40, 40] });
    };

    // Enciende (o re-enciende, reemplazando) la lámpara `id` con la respuesta del endpoint.
    const encender = (id, etiqueta, r) => {
        montarControl();
        id = String(id);
        const previa = lamparas.get(id);
        const color = previa?.color ?? PALETA_LAMPARAS[usados++ % PALETA_LAMPARAS.length];
        if (previa) apagar(id);

        const layer = L.layerGroup().addTo(map);
        const todos = [];
        (r.tramos ?? []).forEach((t) => {
            const linea = (t.coords ?? []).map(par).filter((c) => !c.some(Number.isNaN));
            if (linea.length < 2) return;
            // halo (luz) + núcleo
            L.polyline(linea, { color, weight: 14, opacity: 0.25, interactive: false }).addTo(layer);
            L.polyline(linea, { color, weight: 6, opacity: 0.95 }).addTo(layer);
            todos.push(...linea);
        });
        (r.puntos ?? []).forEach((pt) => {
            if (!pt.posicion) return;
            const c = [Number(pt.posicion.lat), Number(pt.posicion.lng)];
            L.circleMarker(c, { radius: 6, color, weight: 3, fillColor: "#fff", fillOpacity: 1 })
                .bindTooltip(pt.etiqueta ?? pt.tipo)
                .addTo(layer);
            todos.push(c);
        });
        lamparas.set(id, { id, etiqueta, color, layer, bounds: todos, completa: !!r.completa });
        pintarLeyenda();
        zoom(id);
    };

    const destruir = () => {
        apagarTodas();
        if (control) map.removeControl(control);
        control = null;
        cont = null;
    };

    return { encender, apagar, apagarTodas, destruir, total: () => lamparas.size };
}
