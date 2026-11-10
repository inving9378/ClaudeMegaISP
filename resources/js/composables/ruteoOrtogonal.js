// Ruteo ortogonal por canaletas (solo presentación). Función pura, sin DOM.
// Cada conexión sale horizontal de su puerto, sube/baja por una canaleta
// vertical y entra horizontal al puerto destino. Las que caen en la misma
// canaleta se reparten en carriles paralelos.
export const PASO_CANALETA = 24;
export const PASO_CARRIL = 6;

export const rutearOrtogonal = (items) => {
    // items: [{ id, startX, startY, endX, endY }] → { id: "M… H… V… H…" }
    const porCanaleta = new Map();
    items.forEach((it) => {
        const medio = (it.startX + it.endX) / 2;
        const canaleta = Math.round(medio / PASO_CANALETA) * PASO_CANALETA;
        if (!porCanaleta.has(canaleta)) porCanaleta.set(canaleta, []);
        porCanaleta.get(canaleta).push(it);
    });
    const paths = {};
    porCanaleta.forEach((grupo, canaleta) => {
        grupo.sort((a, b) => a.startY + a.endY - (b.startY + b.endY));
        const n = grupo.length;
        grupo.forEach((it, i) => {
            const x = canaleta + (i - (n - 1) / 2) * PASO_CARRIL;
            paths[it.id] = `M${it.startX},${it.startY} H${x} V${it.endY} H${it.endX}`;
        });
    });
    return paths;
};
