<template>
    <q-card flat bordered class="dg" :dark="darkMode">
        <q-card-section class="dg-encabezado">
            <div class="text-subtitle2 text-weight-bold">Tráfico del cliente</div>
            <ul class="dg-leyenda" aria-label="Leyenda de la gráfica">
                <li>
                    <span class="dg-muestra" :style="{ background: colores.bajada, opacity: 0.8 }" aria-hidden="true"></span>
                    Bajada del cliente
                </li>
                <li>
                    <svg width="28" height="10" aria-hidden="true">
                        <line x1="1" y1="5" x2="27" y2="5" :stroke="colores.subida" stroke-width="2.5" />
                    </svg>
                    Subida del cliente
                </li>
                <li v-if="contratada">
                    <svg width="28" height="10" aria-hidden="true">
                        <line x1="1" y1="5" x2="27" y2="5" :stroke="colores.contratada" stroke-width="2" stroke-dasharray="4 3" />
                    </svg>
                    Velocidad contratada {{ mbps(contratada, 0) }}
                </li>
                <li v-if="datos.bandas?.sin_datos?.length">
                    <span class="dg-muestra" :style="{ background: colores.sinDatos, opacity: OPACIDAD.sinDatos }" aria-hidden="true"></span>
                    Sin datos del colector
                </li>
                <li v-if="datos.bandas?.pausado?.length">
                    <span class="dg-muestra" :style="{ background: colores.pausado, opacity: OPACIDAD.pausado }" aria-hidden="true"></span>
                    Monitoreo pausado
                </li>
            </ul>
        </q-card-section>
        <q-card-section class="dg-cuerpo">
            <apexchart :key="clave" type="line" height="200" :options="opciones" :series="series" />
            <div class="dg-nota">
                <template v-if="contratada">
                    La velocidad contratada sale del plan del servicio{{ datos.trafico.plan ? ` («${datos.trafico.plan}»)` : "" }}.
                    <strong v-if="superaPlan">El tráfico superó esa velocidad: puede que el router tenga otra configurada.</strong>
                </template>
                <template v-else>El plan del servicio no tiene velocidad registrada.</template>
                Cada punto es el promedio de {{ minutosCubeta }}; la línea tenue marca el máximo.
            </div>
        </q-card-section>
    </q-card>
</template>

<script>
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { darkMode } from "../../../../../hook/appConfig.js";
import { COLORES, OPACIDAD, cubeta, ejeX, franjas, lineaAhora, mbps } from "./formato.js";

// #988 Fase 4, sección 7: bajada (tx de la VLAN) en azul con relleno suave y subida (rx) en naranja
// oscuro, ya convertidas en el servidor; línea punteada gris con la velocidad del plan. Eje Y de 0
// a la velocidad contratada, o al pico si la supera. Mismos periodos, marcas y huecos que la de
// latencia.
export default {
    name: "DedicadoGraficaTrafico",
    components: { apexchart: VueApexCharts },
    props: {
        datos: { type: Object, required: true },
    },
    setup(props) {
        const colores = computed(() => (darkMode.value ? COLORES.oscuro : COLORES.claro));
        const contratada = computed(() => props.datos.trafico.velocidad_contratada_bps || null);
        const pico = computed(() =>
            Math.max(0, ...props.datos.trafico.bajada.map((p) => p[2] ?? 0), ...props.datos.trafico.subida.map((p) => p[2] ?? 0))
        );
        const superaPlan = computed(() => contratada.value !== null && pico.value > contratada.value);

        const series = computed(() => [
            { name: "Bajada del cliente", type: "area", data: props.datos.trafico.bajada.map(([x, y]) => ({ x, y })) },
            { name: "Subida del cliente", type: "line", data: props.datos.trafico.subida.map(([x, y]) => ({ x, y })) },
            { name: "Bajada del cliente (máximo)", type: "line", data: props.datos.trafico.bajada.map(([x, , y]) => ({ x, y })) },
            { name: "Subida del cliente (máximo)", type: "line", data: props.datos.trafico.subida.map(([x, , y]) => ({ x, y })) },
        ]);

        const minutosCubeta = computed(() => {
            const m = props.datos.rango.paso_seg / 60;
            return m >= 60 ? `${m / 60} h` : `${m} min`;
        });

        // ApexCharts 7 conserva los formateadores de la primera configuración al actualizar: cada
        // periodo, servicio o tema se dibuja en una gráfica nueva.
        const clave = computed(() => `${props.datos.servicio_id}-${props.datos.periodo}-${props.datos.rango.hasta}-${darkMode.value}`);

        const opciones = computed(() => {
            const c = colores.value;
            const tope = contratada.value && !superaPlan.value ? contratada.value : Math.max(pico.value * 1.05, 1e6);
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
                colors: [c.bajada, c.subida, `${c.bajada}66`, `${c.subida}66`],
                stroke: { width: [2, 2, 1, 1], curve: "straight" },
                fill: { type: ["solid", "solid", "solid", "solid"], opacity: [0.18, 1, 1, 1] },
                markers: { size: 0, hover: { size: 3 } },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { borderColor: c.rejilla, padding: { left: 6, right: 6 } },
                xaxis: ejeX(props.datos, c),
                yaxis: {
                    min: 0,
                    max: tope,
                    tickAmount: 5,
                    title: { text: "Mbps", style: { color: c.texto, fontWeight: 500 } },
                    labels: { formatter: (v) => mbps(v, 0).replace(" Mbps", ""), style: { colors: c.texto } },
                },
                annotations: {
                    yaxis: contratada.value
                        ? [{ y: contratada.value, borderColor: c.contratada, strokeDashArray: 5, borderWidth: 2 }]
                        : [],
                    xaxis: [...franjas(props.datos, "sin_datos", c.sinDatos, OPACIDAD.sinDatos, "Sin datos del colector"), ...franjas(props.datos, "pausado", c.pausado, OPACIDAD.pausado, "Monitoreo pausado"), ...lineaAhora(props.datos, c)],
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    theme: darkMode.value ? "dark" : "light",
                    x: { formatter: (v) => cubeta(v, props.datos) },
                    y: { formatter: (v) => (v === null || v === undefined ? "—" : mbps(v)) },
                },
                noData: { text: "Sin lecturas en el periodo" },
            };
        });

        return { OPACIDAD, clave, darkMode, colores, contratada, superaPlan, series, opciones, minutosCubeta, mbps };
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
.dg-muestra {
    display: inline-block;
    width: 16px;
    height: 10px;
    border-radius: 2px;
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
.dg-cuerpo {
    padding-top: 4px;
}
.dg-nota {
    font-size: 0.75rem;
    opacity: 0.8;
}
</style>
