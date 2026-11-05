/**
 * Día agendado de una orden de trabajo de Talento, para mostrarlo (2026-10-27). Una orden que se
 * guardó solo con el día queda a las 00:00 (no hay otra marca de «sin hora»), así que medianoche se
 * muestra como el día solo y no como «12:00 a.m.».
 */
export function fechaAgenda(d) {
    if (!d) return "—";
    // "YYYY-MM-DD HH:mm:ss" con T se lee como hora local en cualquier navegador.
    const fecha = new Date(typeof d === "string" ? d.replace(" ", "T") : d);
    if (isNaN(fecha.getTime())) return "—";
    if (fecha.getHours() === 0 && fecha.getMinutes() === 0) {
        return fecha.toLocaleDateString("es-MX", { dateStyle: "short" });
    }
    return fecha.toLocaleString("es-MX", { dateStyle: "short", timeStyle: "short" });
}
