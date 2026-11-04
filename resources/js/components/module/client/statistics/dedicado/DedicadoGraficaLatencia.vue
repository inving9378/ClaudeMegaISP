<template>
    <q-card flat bordered class="dg" :dark="darkMode">
        <q-card-section class="dg-encabezado">
            <div class="text-subtitle2 text-weight-bold">Latencia y pérdida de paquetes</div>
            <ul class="dg-leyenda" aria-label="Leyenda de la gráfica">
                <li v-for="(ip, i) in datos.latencia.ips" :key="ip.ip">
                    <svg width="28" height="10" aria-hidden="true">
                        <line x1="1" y1="5" x2="27" y2="5" :stroke="color(i)" stroke-width="2.5" :stroke-dasharray="dash(i)" />
                    </svg>
                    Latencia (ms) · {{ nombreIp(ip) }}
                </li>
                <li>
                    <span class="dg-muestra dg-barra" :style="{ background: colores.perdida }" aria-hidden="true"></span>
                    Pérdida (%)
                </li>
                <li>
                    <span class="dg-muestra" :style="{ background: colores.caida }" aria-hidden="true"></span>
                    Sin respuesta
                </li>
                <li>
                    <span class="dg-muestra dg-sin-datos" :style="{ background: colores.sinDatos }" aria-hidden="true"></span>
                    Sin datos del colector
                </li>
            </ul>
        </q-card-section>
        <q-card-section class="dg-cuerpo">
            <apexchart :key="clave" type="line" height="200" :options="opciones" :series="series" />
            <div class="dg-nota">
                Cada punto es el promedio de {{ minutosCubeta }}; la línea tenue marca el máximo, para no ocultar picos.
            </div>
        </q-card-section>
    </q-card>
</template>

<script>
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { darkMode } from "../../../../../hook/appConfig.js";
import { COLORES, DASH, cubeta, ejeX, franjas, lineaAhora, ms, nombreIp, pct, topeRedondo } from "./formato.js";

// #988 Fase 4, sección 6: una línea de latencia por IP (estilo distinto por IP) en el eje izquierdo
// con autoescala y decimales; la pérdida promedio entre IPs en barras en el eje derecho (0 a 20 %,
// crece si hay más). Sin respuesta y Sin datos del colector se sombrean; los huecos cortan la línea.
export default {
    name: "DedicadoGraficaLatencia",
    components: { apexchart: VueApexCharts },
    props: {
        datos: { type: Object, required: true },
    },
    setup(props) {
        const colores = computed(() => (darkMode.value ? COLORES.oscuro : COLORES.claro));
        const color = (i) => colores.value.latencia[i % colores.value.latencia.length];
        const dash = (i) => (DASH[i % DASH.length] ? String(DASH[i % DASH.length]) : "none");
        const tenue = (hex) => `${hex}66`;

        const nombres = computed(() => props.datos.latencia.ips.map((ip) => `Latencia · ${nombreIp(ip)}`));

        const series = computed(() => [
            ...props.datos.latencia.ips.flatMap((ip, i) => [
                { name: nombres.value[i], type: "line", data: ip.puntos.map(([x, y]) => ({ x, y })) },
                { name: `Máximo · ${nombreIp(ip)}`, type: "line", data: ip.puntos.map(([x, , y]) => ({ x, y })) },
            ]),
            { name: "Pérdida (%)", type: "column", data: props.datos.latencia.perdida.map(([x, y]) => ({ x, y })) },
        ]);

        // Autoescala con los datos (promedios y máximos de todas las IPs): las latencias son
        // sub-milisegundo y el eje crece solo si hay picos.
        const maxLatencia = computed(() =>
            topeRedondo(Math.max(0, ...props.datos.latencia.ips.flatMap((ip) => ip.puntos.flatMap((p) => [p[1] ?? 0, p[2] ?? 0]))))
        );

        // 0 a 20 %; si hay más, crece a 40, 60, 80 o 100 (marcas enteras de 5 en 5 hasta 25 en 25).
        const maxPerdida = computed(() => {
            const max = Math.max(0, ...props.datos.latencia.perdida.map((p) => p[1] ?? 0));
            return [20, 40, 60, 80, 100].find((v) => max <= v) ?? 100;
        });

        const minutosCubeta = computed(() => {
            const m = props.datos.rango.paso_seg / 60;
            return m >= 60 ? `${m / 60} h` : `${m} min`;
        });

        // ApexCharts 7 conserva los formateadores de la primera configuración al actualizar: cada
        // periodo, servicio o tema se dibuja en una gráfica nueva.
        const clave = computed(() => `${props.datos.servicio_id}-${props.datos.periodo}-${props.datos.rango.hasta}-${darkMode.value}`);

        const opciones = computed(() => {
            const c = colores.value;
            const ips = props.datos.latencia.ips;
            const lineas = ips.flatMap((_, i) => [color(i), tenue(color(i))]);
            return {
                chart: {
                    type: "line",
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: false },
                    background: "transparent",
                    foreColor: c.texto,
                    fontFamily: "inherit",
                },
                theme: { mode: darkMode.value ? "dark" : "light" },
                colors: [...lineas, c.perdida],
                stroke: {
                    width: [...ips.flatMap(() => [2, 1]), 0],
                    dashArray: [...ips.flatMap((_, i) => [DASH[i % DASH.length], DASH[i % DASH.length]]), 0],
                    curve: "straight",
                },
                fill: { opacity: [...ips.flatMap(() => [1, 1]), 0.55] },
                plotOptions: { bar: { columnWidth: "70%" } },
                markers: { size: 0, hover: { size: 3 } },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { borderColor: c.rejilla, padding: { left: 6, right: 6 } },
                xaxis: ejeX(props.datos, c),
                yaxis: [
                    {
                        seriesName: ips.flatMap((ip, i) => [nombres.value[i], `Máximo · ${nombreIp(ip)}`]),
                        title: { text: "Latencia (ms)", style: { color: c.texto, fontWeight: 500 } },
                        min: 0,
                        max: maxLatencia.value.max,
                        tickAmount: maxLatencia.value.marcas,
                        labels: { formatter: (v) => (v === null || v === undefined ? "" : ms(v).replace(" ms", "")), style: { colors: c.texto } },
                    },
                    {
                        seriesName: "Pérdida (%)",
                        opposite: true,
                        min: 0,
                        max: maxPerdida.value,
                        tickAmount: 4,
                        title: { text: "Pérdida (%)", style: { color: c.texto, fontWeight: 500 } },
                        labels: { formatter: (v) => `${Math.round(v)} %`, style: { colors: c.texto } },
                    },
                ],
                annotations: {
                    xaxis: [
                        ...franjas(props.datos, "sin_datos", c.sinDatos, 0.28, "Sin datos del colector"),
                        ...franjas(props.datos, "sin_respuesta", c.caida, 0.45, "Sin respuesta"),
                        ...lineaAhora(props.datos, c),
                    ],
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    theme: darkMode.value ? "dark" : "light",
                    x: { formatter: (v) => cubeta(v, props.datos) },
                    y: {
                        formatter: (v, { seriesIndex }) => {
                            if (v === null || v === undefined) return "—";
                            return seriesIndex === series.value.length - 1 ? pct(v) : ms(v);
                        },
                    },
                },
                noData: { text: "Sin lecturas en el periodo" },
            };
        });

        return { clave, darkMode, colores, color, dash, nombreIp, series, opciones, minutosCubeta };
    },
};
</script>

<style scoped>
.dg {
    border-radius: 10px;
}
.dg-encabezado {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    flex-wrap: wrap;
    padding-bottom: 0;
}
.dg-leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 16px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 0.8rem;
}
.dg-leyenda li {
    display: flex;
    align-items: center;
    gap: 6px;
}
.dg-muestra {
    display: inline-block;
    width: 16px;
    height: 10px;
    border-radius: 2px;
}
.dg-barra {
    width: 8px;
    height: 12px;
}
.dg-sin-datos {
    opacity: 0.6;
}
.dg-cuerpo {
    padding-top: 4px;
}
.dg-nota {
    font-size: 0.75rem;
    opacity: 0.8;
}
</style>
