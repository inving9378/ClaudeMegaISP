<template>
    <div class="mkt-estr">
        <!-- Generando -->
        <mkt-tarjeta-panel v-if="estado === 'generando'" titulo="Estrategia" subtitulo="La IA está escribiendo la estrategia…" tono="lila" icono-fa="fa-lightbulb">
            <div class="mkt-estr__esqueleto" aria-busy="true">
                <q-skeleton type="text" width="40%" height="24px" />
                <q-skeleton type="text" width="95%" />
                <q-skeleton type="text" width="88%" />
                <q-skeleton type="text" width="70%" />
                <q-skeleton type="rect" height="120px" class="mkt-estr__esq-bloque" />
            </div>
        </mkt-tarjeta-panel>

        <!-- Error -->
        <mkt-tarjeta-panel v-else-if="estado === 'error'" titulo="Estrategia" tono="lila" icono-fa="fa-lightbulb">
            <template v-if="puedeGenerar" #acciones>
                <mkt-boton tipo="secundario" chico icono-fa="fa-redo" etiqueta="Volver a intentar" :cargando="ocupado" @click="$emit('regenerar')" />
            </template>
            <mkt-estado-vacio icono="error_outline" titulo="No se pudo generar la estrategia" :texto="error || 'La IA no respondió como se esperaba.'" />
        </mkt-tarjeta-panel>

        <!-- Sin generar -->
        <mkt-tarjeta-panel v-else-if="!e" titulo="Estrategia" tono="lila" icono-fa="fa-lightbulb">
            <mkt-estado-vacio icono="insights" titulo="Aún no hay estrategia" texto="Genera con IA para obtener el resumen, los mensajes clave, el plan por semana y el reparto del presupuesto." />
        </mkt-tarjeta-panel>

        <template v-else>
            <mkt-tarjeta-panel titulo="Estrategia" :subtitulo="subtitulo" tono="lila" icono-fa="fa-lightbulb">
                <template v-if="puedeEditar" #acciones>
                    <mkt-boton tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" :deshabilitado="ocupado" @click="abrirEditor" />
                    <mkt-boton v-if="puedeGenerar" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" :cargando="ocupado" @click="$emit('regenerar')" />
                </template>
                <div class="mkt-estr__resumen">
                    <p class="mkt-estr__texto">{{ e.resumen }}</p>
                    <div v-if="e.propuesta_valor" class="mkt-estr__propuesta">
                        <span class="mkt-estr__etiqueta">Propuesta de valor</span>
                        <p>{{ e.propuesta_valor }}</p>
                    </div>
                </div>
                <div class="mkt-estr__dos">
                    <section v-if="lista(e.mensajes_clave).length">
                        <h3 class="mkt-estr__h3">Mensajes clave</h3>
                        <ol class="mkt-estr__mensajes">
                            <li v-for="(m, i) in lista(e.mensajes_clave)" :key="i">{{ m }}</li>
                        </ol>
                    </section>
                    <section v-if="e.publico_detalle">
                        <h3 class="mkt-estr__h3">El público, a detalle</h3>
                        <p class="mkt-estr__texto">{{ e.publico_detalle }}</p>
                    </section>
                </div>
            </mkt-tarjeta-panel>

            <div class="mkt-estr__rejilla">
                <mkt-tarjeta-panel titulo="Reparto del presupuesto" :subtitulo="`Total ${formatoMXN(presupuesto)}`" tono="azul" icono-fa="fa-chart-pie">
                    <mkt-estado-vacio
                        v-if="!reparto.length"
                        icono="payments"
                        titulo="Sin reparto"
                        texto="La estrategia no trae reparto del presupuesto."
                    />
                    <div v-else class="mkt-estr__reparto">
                        <div v-if="sumaMontos > 0" class="mkt-estr__grafica" aria-hidden="true">
                            <apexchart type="bar" :height="altoGrafica" :options="opcionesGrafica" :series="seriesGrafica" />
                        </div>
                        <p v-else class="mkt-estr__nota">Campaña orgánica: sin pauta pagada.</p>
                        <table class="mkt-estr__tabla" aria-label="Reparto del presupuesto por canal">
                            <thead>
                                <tr><th scope="col">Canal</th><th scope="col" class="mkt-estr__num">%</th><th scope="col" class="mkt-estr__num">Monto</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="(r, i) in reparto" :key="r.canal + i">
                                    <td>
                                        <span class="mkt-estr__muestra" :style="{ background: colores[i % colores.length] }" aria-hidden="true"></span>
                                        {{ etiquetaCanal(r.canal) }}
                                    </td>
                                    <td class="mkt-estr__num">{{ formatoPorcentaje(r.porcentaje) }}</td>
                                    <td class="mkt-estr__num">{{ formatoMXN(r.monto_mxn) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr><th scope="row">Total</th><td class="mkt-estr__num">100 %</td><td class="mkt-estr__num">{{ formatoMXN(presupuesto) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </mkt-tarjeta-panel>

                <mkt-tarjeta-panel titulo="Metas" subtitulo="Indicadores que propone la estrategia" tono="verde" icono-fa="fa-bullseye">
                    <mkt-estado-vacio v-if="!lista(e.kpis).length" icono="flag" titulo="Sin metas" texto="La estrategia no trae metas." />
                    <ul v-else class="mkt-estr__kpis">
                        <li v-for="(k, i) in lista(e.kpis)" :key="i">
                            <span class="mkt-estr__kpi-nombre">{{ k.nombre }}</span>
                            <span class="mkt-estr__kpi-meta">{{ k.meta }}</span>
                        </li>
                    </ul>
                    <template v-if="lista(e.riesgos).length" #pie>
                        <div class="mkt-estr__riesgos">
                            <span class="mkt-estr__etiqueta">Riesgos a cuidar</span>
                            <ul>
                                <li v-for="(r, i) in lista(e.riesgos)" :key="i">{{ r }}</li>
                            </ul>
                        </div>
                    </template>
                </mkt-tarjeta-panel>
            </div>

            <mkt-tarjeta-panel v-if="lista(e.por_canal).length" titulo="Papel de cada canal" tono="oliva" icono-fa="fa-share-alt">
                <mkt-tabla-ejecutiva :columnas="columnasCanal" :filas="porCanal" fila-key="clave" :pagination="{ rowsPerPage: 0 }" hide-bottom :menu="false" :buscador="false" />
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel v-if="lista(e.calendario).length" titulo="Plan por semana" tono="crema" icono-fa="fa-calendar-alt">
                <ol class="mkt-estr__semanas">
                    <li v-for="s in lista(e.calendario)" :key="s.semana" class="mkt-estr__semana">
                        <span class="mkt-estr__semana-num">Semana {{ s.semana }}</span>
                        <ul>
                            <li v-for="(a, i) in lista(s.acciones)" :key="i">{{ a }}</li>
                        </ul>
                    </li>
                </ol>
            </mkt-tarjeta-panel>
        </template>

        <!-- Editor de estrategia (PUT /estrategia) -->
        <mkt-dialogo v-model="editor" titulo="Editar estrategia" :guardando="guardando" :ancho="720">
            <div v-if="borrador" class="mkt-estr__form">
                <q-input v-model="borrador.resumen" outlined autogrow type="textarea" label="Resumen" counter maxlength="600" :error="!!err('resumen')" :error-message="err('resumen')" />
                <q-input v-model="borrador.propuesta_valor" outlined autogrow type="textarea" label="Propuesta de valor" counter maxlength="300" :error="!!err('propuesta_valor')" :error-message="err('propuesta_valor')" />
                <q-input
                    v-model="borrador.mensajesTexto"
                    outlined
                    autogrow
                    type="textarea"
                    label="Mensajes clave (uno por renglón, de 3 a 5)"
                    :error="!!err('mensajes_clave')"
                    :error-message="err('mensajes_clave')"
                />
                <q-input v-model="borrador.publico_detalle" outlined autogrow type="textarea" label="El público, a detalle" counter maxlength="600" :error="!!err('publico_detalle')" :error-message="err('publico_detalle')" />
                <fieldset v-if="borrador.reparto.length" class="mkt-estr__fieldset">
                    <legend>Reparto del presupuesto (%) — los montos los recalcula el sistema</legend>
                    <div v-for="(r, i) in borrador.reparto" :key="r.canal" class="mkt-estr__fila-pct">
                        <span>{{ etiquetaCanal(r.canal) }}</span>
                        <q-input
                            v-model="r.porcentaje"
                            outlined
                            dense
                            inputmode="decimal"
                            suffix="%"
                            :aria-label="`Porcentaje para ${etiquetaCanal(r.canal)}`"
                            :error="!!err(`reparto_presupuesto.${i}.porcentaje`)"
                            :error-message="err(`reparto_presupuesto.${i}.porcentaje`)"
                        />
                    </div>
                    <p class="mkt-estr__nota" :class="{ 'mkt-estr__nota--aviso': Math.abs(sumaPct - 100) > 0.001 }">
                        Suma: {{ formatoPorcentaje(sumaPct) }}<span v-if="Math.abs(sumaPct - 100) > 0.001"> · si no da 100 %, el sistema lo ajusta en proporción</span>
                    </p>
                </fieldset>
                <q-input v-model="borrador.riesgosTexto" outlined autogrow type="textarea" label="Riesgos (uno por renglón)" :error="!!err('riesgos')" :error-message="err('riesgos')" />
                <div v-if="errorGuardar" class="mkt-estr__error" role="alert">{{ errorGuardar }}</div>
            </div>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="editor = false" />
                <mkt-boton tipo="guardar" icono-fa="fa-save" etiqueta="Guardar estrategia" :cargando="guardando" @click="guardar" />
            </template>
        </mkt-dialogo>
    </div>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import {
    MktTarjetaPanel,
    MktEstadoVacio,
    MktTablaEjecutiva,
    MktDialogo,
    MktBoton,
    formatoMXN,
    formatoPorcentaje,
    formatoFecha,
    mensajeDeError,
    erroresPorCampo,
    SERIE_GRAFICAS,
    opcionesBaseGrafica,
} from "../kit";
import { etiquetaCanal, clonar } from "./catalogos360";

/**
 * Ficha 360 · estrategia (§2.5.4) con reparto del presupuesto en MXN (barra horizontal ApexCharts + tabla en texto).
 * Editar = PUT /api/marketing/campanas-360/{id}/estrategia (§2.4.9); el backend recalcula los montos.
 */
export default {
    name: "PanelEstrategia",
    components: { MktTarjetaPanel, MktEstadoVacio, MktTablaEjecutiva, MktDialogo, MktBoton },
    props: {
        campanaId: { type: Number, required: true },
        estrategia: { type: Object, default: null },
        estado: { type: String, default: "sin_generar" },
        error: { type: String, default: null },
        generadaAt: { type: String, default: null },
        presupuesto: { type: [String, Number], default: null },
        puedeEditar: { type: Boolean, default: false },
        puedeGenerar: { type: Boolean, default: false },
        ocupado: { type: Boolean, default: false },
    },
    emits: ["regenerar", "actualizada"],
    data() {
        return {
            oscuro: false,
            observador: null,
            editor: false,
            borrador: null,
            guardando: false,
            erroresCampo: {},
            errorGuardar: "",
            colores: SERIE_GRAFICAS,
            columnasCanal: [
                { name: "canal", label: "Canal", field: "canal_etiqueta", align: "left" },
                { name: "rol", label: "Papel", field: "rol", align: "left" },
                { name: "formato", label: "Formato", field: "formato", align: "left", ocultarEnMovil: true },
                { name: "frecuencia", label: "Frecuencia", field: "frecuencia", align: "left" },
            ],
        };
    },
    computed: {
        e() {
            return this.estrategia && typeof this.estrategia === "object" ? this.estrategia : null;
        },
        subtitulo() {
            return this.generadaAt ? `Generada el ${formatoFecha(this.generadaAt, { conHora: true })}` : "";
        },
        reparto() {
            return this.lista(this.e?.reparto_presupuesto);
        },
        sumaMontos() {
            return this.reparto.reduce((s, r) => s + (Number(r.monto_mxn) || 0), 0);
        },
        porCanal() {
            return this.lista(this.e?.por_canal).map((r, i) => ({ ...r, clave: `${r.canal}-${i}`, canal_etiqueta: etiquetaCanal(r.canal) }));
        },
        altoGrafica() {
            return Math.max(140, this.reparto.length * 44);
        },
        seriesGrafica() {
            return [{ name: "Monto", data: this.reparto.map((r) => Number(r.monto_mxn) || 0) }];
        },
        opcionesGrafica() {
            const base = opcionesBaseGrafica(this.oscuro);
            return {
                ...base,
                colors: this.reparto.map((_, i) => this.colores[i % this.colores.length]),
                plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: "62%" } },
                xaxis: {
                    categories: this.reparto.map((r) => etiquetaCanal(r.canal)),
                    labels: { formatter: (v) => formatoMXN(v).replace(" MXN", "") },
                    tickAmount: 3,
                },
                yaxis: { labels: { maxWidth: 120 } },
                tooltip: { ...base.tooltip, y: { formatter: (v) => formatoMXN(v) } },
                states: { hover: { filter: { type: "none" } } },
            };
        },
        sumaPct() {
            return (this.borrador?.reparto || []).reduce((s, r) => s + (Number(String(r.porcentaje).replace(",", ".")) || 0), 0);
        },
    },
    mounted() {
        this.leerModo();
        if (typeof MutationObserver !== "undefined") {
            this.observador = new MutationObserver(() => this.leerModo());
            this.observador.observe(document.body, { attributes: true, attributeFilter: ["data-layout-mode"] });
        }
    },
    beforeUnmount() {
        if (this.observador) this.observador.disconnect();
    },
    methods: {
        formatoMXN,
        formatoPorcentaje,
        etiquetaCanal,
        lista: (v) => (Array.isArray(v) ? v : []),
        leerModo() {
            this.oscuro = document.body.getAttribute("data-layout-mode") === "dark";
        },
        err(clave) {
            if (this.erroresCampo[clave]) return this.erroresCampo[clave];
            const prefijo = `estrategia.${clave}`;
            const k = Object.keys(this.erroresCampo).find((x) => x === prefijo || x.startsWith(prefijo + "."));
            return k ? this.erroresCampo[k] : "";
        },
        abrirEditor() {
            const e = clonar(this.e) || {};
            this.borrador = {
                ...e,
                resumen: e.resumen || "",
                propuesta_valor: e.propuesta_valor || "",
                publico_detalle: e.publico_detalle || "",
                mensajesTexto: this.lista(e.mensajes_clave).join("\n"),
                riesgosTexto: this.lista(e.riesgos).join("\n"),
                reparto: this.lista(e.reparto_presupuesto).map((r) => ({ ...r, porcentaje: r.porcentaje })),
            };
            this.erroresCampo = {};
            this.errorGuardar = "";
            this.editor = true;
        },
        async guardar() {
            if (this.guardando || !this.borrador) return;
            const b = this.borrador;
            const renglones = (t) => String(t || "").split("\n").map((x) => x.trim()).filter(Boolean);
            const { mensajesTexto, riesgosTexto, reparto, ...resto } = b;
            const estrategia = {
                ...resto,
                resumen: b.resumen.trim(),
                propuesta_valor: b.propuesta_valor.trim(),
                publico_detalle: b.publico_detalle.trim(),
                mensajes_clave: renglones(mensajesTexto),
                riesgos: renglones(riesgosTexto),
                reparto_presupuesto: reparto.map((r) => ({
                    canal: r.canal,
                    porcentaje: (Number(String(r.porcentaje).replace(",", ".")) || 0).toFixed(2),
                    monto_mxn: r.monto_mxn,
                })),
            };
            this.guardando = true;
            this.errorGuardar = "";
            this.erroresCampo = {};
            try {
                const { data } = await axios.put(`/api/marketing/campanas-360/${this.campanaId}/estrategia`, { estrategia });
                this.$emit("actualizada", data?.data || null);
                this.editor = false;
                message("Estrategia guardada.");
            } catch (e) {
                this.erroresCampo = erroresPorCampo(e);
                this.errorGuardar = mensajeDeError(e, "No se pudo guardar la estrategia.");
            } finally {
                this.guardando = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-estr { display: flex; flex-direction: column; gap: 14px; }
.mkt-estr__esqueleto { display: flex; flex-direction: column; gap: 6px; }
.mkt-estr__esq-bloque { margin-top: var(--mkt-esp-1); border-radius: var(--mkt-radio); }
.mkt-estr__resumen { display: grid; gap: var(--mkt-esp-2); grid-template-columns: minmax(0, 3fr) minmax(220px, 2fr); align-items: start; }
.mkt-estr__texto { margin: 0; font-size: 14px; line-height: 22px; white-space: pre-line; overflow-wrap: anywhere; }
.mkt-estr__propuesta {
    padding: 12px var(--mkt-esp-2);
    border-left: 3px solid var(--mkt-carpeta);
    background: var(--mkt-fondo-suave);
    border-radius: 0 var(--mkt-radio) var(--mkt-radio) 0;
}
.mkt-estr__propuesta p { margin: 4px 0 0; font-size: 15px; font-weight: 600; line-height: 22px; overflow-wrap: anywhere; }
.mkt-estr__etiqueta { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.4px; color: #94a3b8; }
.mkt-estr__dos { display: grid; gap: var(--mkt-esp-3); grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: var(--mkt-esp-3); }
.mkt-estr__h3 { margin: 0 0 var(--mkt-esp-1); font-size: 14px; font-weight: 600; color: var(--mkt-texto); }
.mkt-estr__mensajes { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
.mkt-estr__rejilla { display: grid; gap: 14px; grid-template-columns: minmax(0, 3fr) minmax(260px, 2fr); align-items: start; }
.mkt-estr__reparto { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-estr__grafica { min-width: 0; }
.mkt-estr__tabla { width: 100%; border-collapse: collapse; font-size: 14px; }
.mkt-estr__tabla th { font-size: 12px; font-weight: 500; text-align: left; padding: 8px; border-bottom: 1px solid var(--mkt-borde); }
.mkt-estr__tabla td { height: 48px; padding: 7px 8px; border-bottom: 1px solid var(--mkt-borde); }
.mkt-estr__tabla tfoot th, .mkt-estr__tabla tfoot td { font-weight: 600; color: var(--mkt-texto); border-bottom: 0; font-size: 14px; text-transform: none; letter-spacing: 0; }
.mkt-estr__num { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
.mkt-estr__muestra { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; }
.mkt-estr__kpis { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.mkt-estr__kpis li { display: flex; justify-content: space-between; gap: var(--mkt-esp-2); padding: 8px 0; border-bottom: 1px solid var(--mkt-borde); font-size: 14px; }
.mkt-estr__kpis li:last-child { border-bottom: 0; }
.mkt-estr__kpi-meta { font-weight: 600; font-variant-numeric: tabular-nums; }
.mkt-estr__riesgos ul { margin: 4px 0 0; padding-left: 18px; font-size: 13px; }
.mkt-estr__semanas { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
.mkt-estr__semana { background: var(--mkt-kpi-fondo); border: 1px solid var(--mkt-kpi-borde); border-radius: 10px; padding: 10px 12px; min-width: 0; overflow-wrap: anywhere; }
.mkt-estr__semana-num { font-size: 13px; font-weight: 700; }
.mkt-estr__semana ul { margin: 6px 0 0; padding-left: 18px; font-size: 14px; display: flex; flex-direction: column; gap: 4px; }
.mkt-estr__nota { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-estr__nota--aviso { color: var(--mkt-peligro); }
.mkt-estr__form { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-estr__fieldset { border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); padding: 12px var(--mkt-esp-2); margin: 0; min-width: 0; }
.mkt-estr__fieldset legend { font-size: 13px; font-weight: 600; padding: 0 4px; width: auto; float: none; margin: 0; color: var(--mkt-texto-suave); }
.mkt-estr__fila-pct { display: grid; grid-template-columns: minmax(0, 1fr) 140px; align-items: center; gap: var(--mkt-esp-2); margin-bottom: 4px; font-size: 14px; }
.mkt-estr__error { padding: 8px 12px; border-radius: 4px; background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent); border: 1px solid color-mix(in srgb, var(--mkt-peligro) 40%, transparent); font-size: 14px; }

@media (max-width: 1023px) {
    .mkt-estr__resumen, .mkt-estr__dos, .mkt-estr__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-estr__fila-pct { grid-template-columns: minmax(0, 1fr) 110px; }
}
</style>
