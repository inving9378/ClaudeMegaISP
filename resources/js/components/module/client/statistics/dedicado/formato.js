// #988 Fase 4: formatos, colores y eje X compartidos del histórico de un servicio dedicado.
// Las horas se muestran en la zona del sistema (la manda el servidor), no en la del navegador.

// Latencia índigo; pérdida y subida naranja oscuro; bajada azul; caída rosa claro. Las líneas de
// latencia de cada IP se distinguen además por estilo (DASH), no solo por color.
// Las franjas de las gráficas y su muestra en la leyenda usan la misma opacidad, para que coincidan.
export const OPACIDAD = { sinDatos: 0.28, pausado: 0.32 };

export const COLORES = {
    claro: {
        latencia: ["#4338ca", "#4f46e5", "#3730a3", "#6366f1"],
        perdida: "#c2410c",
        bajada: "#1d4ed8",
        subida: "#c2410c",
        contratada: "#6b7280",
        caida: "#fda4af",
        sinDatos: "#9ca3af",
        pausado: "#2dd4bf",
        texto: "#4b5563",
        rejilla: "rgba(0, 0, 0, 0.08)",
    },
    oscuro: {
        latencia: ["#a5b4fc", "#818cf8", "#c7d2fe", "#6366f1"],
        perdida: "#fb923c",
        bajada: "#60a5fa",
        subida: "#fb923c",
        contratada: "#9ca3af",
        caida: "#fb7185",
        sinDatos: "#6b7280",
        pausado: "#14b8a6",
        texto: "#d1d5db",
        rejilla: "rgba(255, 255, 255, 0.1)",
    },
};

export const DASH = [0, 6, 2, 10];

export const PERIODOS = [
    { valor: "hoy", texto: "Hoy" },
    { valor: "24h", texto: "Últimas 24 h" },
    { valor: "7d", texto: "7 días" },
    { valor: "30d", texto: "30 días" },
];

const numero = (v, decimales) =>
    Number(v).toLocaleString("es-MX", { minimumFractionDigits: decimales, maximumFractionDigits: decimales });

export const ms = (v) => {
    if (v === null || v === undefined) return "—";
    const n = Number(v);
    return `${numero(n, n < 10 ? 2 : n < 100 ? 1 : 0)} ms`;
};

export const pct = (v, decimales = 1) => {
    if (v === null || v === undefined) return "—";
    const n = Number(v);
    return `${Number.isInteger(n) ? numero(n, 0) : numero(n, decimales)} %`;
};

export const mbps = (bps, decimales = 1) => (bps === null || bps === undefined ? "—" : `${numero(bps / 1e6, decimales)} Mbps`);

export const gb = (bytes) => numero((bytes || 0) / 1e9, 1);

// Tope «redondo» del eje (1, 2, 2.5 o 5 × 10^n) por encima del valor más alto: autoescala con los
// datos. ApexCharts 7 aplica el min/max de un eje a todos si el otro no los trae, así que las dos
// gráficas los dan siempre explícitos.
export const topeRedondo = (valor) => {
    if (!(valor > 0)) return { max: 1, marcas: 5 };
    const objetivo = valor * 1.05;
    const base = 10 ** Math.floor(Math.log10(objetivo));
    const m = [1, 2, 2.5, 5, 10].find((f) => f * base >= objetivo);
    return { max: m * base, marcas: m === 2 ? 4 : 5 };
};

// «192.0.2.10» → «.10», como lo pide la especificación en el detalle de los eventos.
export const octeto = (ip) => (/^\d+\.\d+\.\d+\.\d+$/.test(ip) ? ip.slice(ip.lastIndexOf(".")) : ip);

export const nombreIp = (ip) => (ip.etiqueta ? `${ip.etiqueta} (${octeto(ip.ip)})` : ip.ip);

const formateadores = new Map();
const formateador = (zona, opciones) => {
    const clave = zona + JSON.stringify(opciones);
    if (!formateadores.has(clave)) {
        formateadores.set(clave, new Intl.DateTimeFormat("es-MX", { timeZone: zona, ...opciones }));
    }
    return formateadores.get(clave);
};

const partes = (t, zona) =>
    Object.fromEntries(
        formateador(zona, { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
            .formatToParts(new Date(t))
            .map((p) => [p.type, p.value.replace(".", "")])
    );

export const hora = (t, zona) => formateador(zona, { hour: "2-digit", minute: "2-digit", hourCycle: "h23" }).format(new Date(t));

export const fecha = (t, zona) => {
    const p = partes(t, zona);
    return `${p.day} ${p.month}`;
};

export const fechaHora = (t, zona) => `${fecha(t, zona)} ${hora(t, zona)}`;

export const duracion = (minutos) => {
    if (minutos < 60) return `${minutos} min`;
    if (minutos < 1440) {
        const h = Math.floor(minutos / 60);
        const m = minutos % 60;
        return m ? `${h} h ${String(m).padStart(2, "0")} min` : `${h} h`;
    }
    const d = Math.floor(minutos / 1440);
    const h = Math.floor((minutos % 1440) / 60);
    return h ? `${d} d ${h} h` : `${d} d`;
};

export const subtitulo = (datos) => {
    if (!datos) return "";
    const { desde, hasta } = datos.rango;
    const z = datos.zona;
    const texto = PERIODOS.find((p) => p.valor === datos.periodo)?.texto || "";
    if (datos.periodo === "hoy") return `${texto} · 00:00 a ${hora(hasta, z)}`;
    if (datos.periodo === "24h") return `${texto} · ${fechaHora(desde, z)} a ${fechaHora(hasta, z)}`;
    return `${texto} · ${fecha(desde, z)} a ${fechaHora(hasta, z)}`;
};

/**
 * Eje X numérico (epoch ms) con una línea de cuadrícula por hora o por día y etiqueta solo donde
 * la pide la especificación: «Hoy» y «24 h» cada 4 h, «7 días» una por día, «30 días» una por
 * semana (los lunes).
 */
export const ejeX = (datos, colores) => {
    const { eje_desde: min, eje_hasta: max, cuadricula_seg: cuadricula, marcas } = datos.rango;
    const paso = cuadricula * 1000;
    const etiqueta = (v) => {
        const t = min + Math.round((v - min) / paso) * paso;
        if (Math.abs(v - t) > 60000) return "";
        const p = partes(t, datos.zona);
        if (marcas === "4h") {
            if (Number(p.hour) % 4 !== 0 || p.minute !== "00") return "";
            return datos.periodo === "hoy" && t === max ? "24:00" : `${p.hour}:00`;
        }
        if (t === max) return "";
        if (marcas === "dia") return `${p.weekday} ${p.day}`;
        return p.weekday.toLowerCase().startsWith("lun") ? `${p.day} ${p.month}` : "";
    };
    return {
        type: "numeric",
        min,
        max,
        tickAmount: Math.round((max - min) / paso),
        labels: { formatter: etiqueta, rotate: 0, hideOverlappingLabels: true, style: { colors: colores.texto } },
        axisTicks: { show: true, color: colores.rejilla },
        axisBorder: { color: colores.rejilla },
        tooltip: { enabled: false },
    };
};

// Texto del intervalo de una cubeta para el tooltip («30 oct 11:40 a 11:50»).
export const cubeta = (t, datos) => {
    const mitad = (datos.rango.paso_seg * 1000) / 2;
    const ini = t - mitad;
    const fin = t + mitad;
    const z = datos.zona;
    return fecha(ini, z) === fecha(fin, z) ? `${fechaHora(ini, z)} a ${hora(fin, z)}` : `${fechaHora(ini, z)} a ${fechaHora(fin, z)}`;
};

// Franjas sombreadas: al menos del ancho de una cubeta para que se vean en periodos largos (el
// minuto exacto está en la tabla de eventos).
export const franjas = (datos, tipo, color, opacidad, etiqueta) => {
    const ancho = datos.rango.paso_seg * 1000;
    return (datos.bandas?.[tipo] || []).map(([ini, fin]) => {
        const falta = Math.max(0, ancho - (fin - ini)) / 2;
        return {
            x: ini - falta,
            x2: fin + falta,
            fillColor: color,
            opacity: opacidad,
            borderColor: "transparent",
            strokeDashArray: 0,
            label: { text: etiqueta, borderWidth: 0, style: { background: "transparent", color: "transparent" } },
        };
    });
};

export const lineaAhora = (datos, colores) =>
    datos.periodo === "hoy"
        ? [{
              x: datos.rango.hasta,
              borderColor: colores.contratada,
              strokeDashArray: 4,
              label: {
                  text: "ahora",
                  orientation: "horizontal",
                  borderWidth: 0,
                  style: { background: "transparent", color: colores.texto, fontSize: "11px" },
              },
          }]
        : [];
