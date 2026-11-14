<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <!-- Contexto de la empresa (oculto: solo Meganet). -->
        <mkt-selector-empresa v-show="false" @cargado="(x) => (contexto = x)" />
        <div class="mkt-c360-ficha">
            <!-- Estados de la pantalla -->
            <mkt-tarjeta-panel v-if="carga === 'cargando'" :cargando="true" />
            <mkt-tarjeta-panel v-else-if="carga === 'sin_permiso'">
                <mkt-estado-vacio icono="lock" titulo="No puedes ver esta campaña" texto="No tienes acceso a Marketing. Si lo necesitas, pídelo a Irving." />
            </mkt-tarjeta-panel>
            <mkt-tarjeta-panel v-else-if="carga === 'no_encontrado'">
                <mkt-estado-vacio icono="search_off" titulo="No encontrado" texto="Esa campaña no existe o ya se eliminó." />
            </mkt-tarjeta-panel>
            <mkt-tarjeta-panel v-else-if="carga === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudo cargar la campaña" :texto="mensajeCarga" />
            </mkt-tarjeta-panel>

            <template v-else>
                <!-- Como la ficha de Cliente: primero UNA fila de pestañas tipo carpeta… -->
                <mkt-pestanas v-model="pestana" :items="itemsPestanas" etiqueta="Secciones de la campaña" />

                <!-- …luego la tarjeta de encabezado (nombre, estado, datos clave, cifras y botones). -->
                <mkt-ficha-encabezado :titulo="c.nombre || 'Campaña 360'" :numero="`#${id}`" icono-fa="fa-bullhorn">
                    <template #estado>
                        <mkt-insignia-estado :texto="c.estado_etiqueta || etiquetaEstadoCampana(c.estado)" :tono="tonoEstadoCampana(c.estado)" />
                    </template>
                    <template #datos>
                        <span v-if="c.objetivo_etiqueta || c.objetivo"><i class="fa fa-fw fa-flag me-1" aria-hidden="true"></i>{{ c.objetivo_etiqueta || etiquetaObjetivo(c.objetivo) }}</span>
                        <span v-if="c.fecha_inicio || c.fecha_fin"><i class="fa fa-fw fa-calendar-alt me-1" aria-hidden="true"></i>{{ formatoDia(c.fecha_inicio) }} – {{ formatoDia(c.fecha_fin) }}</span>
                    </template>
                    <template #cifras>
                        <div class="mkt-ficha__cifra"><span>Presupuesto</span><strong>{{ formatoMXN(c.presupuesto_mxn) }}</strong></div>
                        <div class="mkt-ficha__cifra"><span>Días</span><strong class="mkt-c360-ficha__cifra-neutra">{{ diasCampana ?? "—" }}</strong></div>
                        <div class="mkt-ficha__cifra"><span>Piezas listas</span><strong class="mkt-c360-ficha__cifra-neutra">{{ c.generacion?.piezas_total ? numero(c.generacion.piezas_listas) : "—" }}</strong></div>
                        <div class="mkt-ficha__cifra"><span>Videos listos</span><strong class="mkt-c360-ficha__cifra-neutra">{{ c.videos_resumen?.total ? numero(c.videos_resumen.listos) : "—" }}</strong></div>
                    </template>
                    <template #acciones>
                        <!-- Acción principal de la ficha (UNA, arriba a la derecha): generar si aún no hay nada generado. -->
                        <mkt-boton v-if="accionGenerar === 'todo'" class="mkt-c360-ficha__principal" tipo="guardar" icono-fa="fa-magic" etiqueta="Generar con IA" :cargando="generando" :deshabilitado="ocupado" @click="generar('todo')" />
                        <mkt-boton v-else-if="accionGenerar === 'piezas'" class="mkt-c360-ficha__principal" tipo="guardar" icono-fa="fa-magic" etiqueta="Generar piezas" :cargando="generando" :deshabilitado="ocupado" @click="generar('piezas')" />
                        <mkt-boton v-if="editable" tipo="secundario" icono-fa="fa-edit" etiqueta="Editar datos" :href="`/marketing/campanas-360/${id}/editar`" :deshabilitado="ocupado" />
                        <mkt-boton
                            v-for="t in transiciones"
                            :key="t.a"
                            :tipo="accionGenerar && tipoTransicion(t) === 'guardar' ? 'secundario' : tipoTransicion(t)"
                            :class="{ 'mkt-c360-ficha__principal': !accionGenerar && tipoTransicion(t) === 'guardar' }"
                            :icono-fa="iconoTransicion(t)"
                            :etiqueta="t.label"
                            :cargando="cambiandoA === t.a"
                            :deshabilitado="ocupado"
                            @click="pedirTransicion(t)"
                        />
                        <mkt-boton v-if="c.estado === 'draft'" tipo="peligro-secundario" icono-fa="fa-trash-alt" etiqueta="Eliminar" :deshabilitado="ocupado" @click="confirmarBorrar = true" />
                    </template>
                </mkt-ficha-encabezado>

                <!-- Aviso de una acción -->
                <div v-if="aviso" class="mkt-c360-ficha__aviso" :class="`mkt-c360-ficha__aviso--${aviso.tono}`" role="alert">
                    <i class="fa fa-fw" :class="aviso.tono === 'peligro' ? 'fa-ban' : 'fa-info-circle'" aria-hidden="true"></i>
                    <span class="mkt-c360-ficha__aviso-texto">{{ aviso.texto }}</span>
                    <mkt-boton v-if="aviso.reintentar" tipo="enlace" chico etiqueta="Revisar ahora" @click="reanudarSondeo" />
                    <q-btn flat dense round icon="close" aria-label="Cerrar aviso" @click="aviso = null" />
                </div>

                <!-- IA no configurada (no se reintenta en bucle) -->
                <mkt-tarjeta-panel v-if="iaNoConfigurada">
                    <mkt-estado-vacio icono="smart_toy" titulo="Falta configurar la IA" :texto="iaNoConfigurada" />
                </mkt-tarjeta-panel>

                <!-- Estado de la generación -->
                <mkt-tarjeta-panel v-if="mostrarProgreso" titulo="Generación con IA" :subtitulo="textoProgreso" tono="azul" icono-fa="fa-magic">
                    <template #acciones>
                        <mkt-insignia-estado :texto="estadoGeneracion.label" :tono="estadoGeneracion.tono" />
                    </template>
                    <q-linear-progress
                        v-if="c.generacion?.estado === 'generando'"
                        :value="avanceGeneracion"
                        :indeterminate="!c.generacion?.piezas_total"
                        color="primary"
                        track-color="grey-3"
                        rounded
                        size="8px"
                        :aria-label="textoProgreso"
                    />
                    <p v-if="c.generacion?.estado === 'con_errores'" class="mkt-c360-ficha__nota">
                        {{ c.generacion.piezas_error === 1 ? "Una pieza no se generó." : `${c.generacion.piezas_error} piezas no se generaron.` }}
                        Ábrela en su canal y usa «Regenerar».
                    </p>
                </mkt-tarjeta-panel>

                <!-- Sin generar todavía -->
                <mkt-tarjeta-panel v-if="sinGenerar && !iaNoConfigurada">
                    <mkt-estado-vacio
                        icono="auto_awesome"
                        titulo="Aún no se genera con IA"
                        texto="La IA prepara la estrategia y todas las piezas de los canales elegidos. Tarda unos minutos; puedes seguir el avance aquí." />
                </mkt-tarjeta-panel>

                <div v-show="pestana === 'resumen'" class="mkt-c360-ficha__pestana">
                    <panel-estrategia
                        :campana-id="id"
                        :estrategia="c.estrategia"
                        :estado="c.estrategia_estado"
                        :error="c.estrategia_error"
                        :generada-at="c.estrategia_generada_at"
                        :presupuesto="c.presupuesto_mxn"
                        :puede-editar="c.estrategia_estado === 'lista'"
                        :puede-generar="puedeGenerar"
                        :ocupado="ocupado || c.generacion?.estado === 'generando'"
                        @regenerar="generar('estrategia')"
                        @actualizada="alActualizarCampana"
                    />

                    <mkt-tarjeta-panel v-if="faltanPiezas" titulo="Piezas" subtitulo="La estrategia está lista, pero aún no hay piezas" tono="verde" icono-fa="fa-magic">
                        <mkt-estado-vacio icono="auto_awesome" titulo="Genera las piezas" texto="Guiones, textos, anuncios y mensajes para los canales elegidos." />
                    </mkt-tarjeta-panel>

                    <mkt-tarjeta-panel titulo="Datos de la campaña" tono="gris" icono-fa="fa-info-circle">
                        <template v-if="editable" #acciones>
                            <mkt-boton tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" :href="`/marketing/campanas-360/${id}/editar`" />
                        </template>
                        <dl class="mkt-c360-ficha__datos">
                            <template v-for="d in datos" :key="d.etiqueta">
                                <dt>{{ d.etiqueta }}</dt>
                                <dd :class="{ 'mkt-c360-ficha__suave': !d.valor }">{{ d.valor || "—" }}</dd>
                            </template>
                        </dl>
                        <template v-if="puedeGenerar && c.generacion?.piezas_total" #pie>
                            <div class="mkt-c360-ficha__pie">
                                <span>¿Cambiaste los datos? Puedes volver a generar todo (la estrategia y las piezas de texto se reemplazan; los videos se quedan).</span>
                                <mkt-boton tipo="secundario" chico icono-fa="fa-redo" etiqueta="Volver a generar todo" :deshabilitado="ocupado || c.generacion?.estado === 'generando'" @click="confirmarReemplazo = true" />
                            </div>
                        </template>
                    </mkt-tarjeta-panel>
                </div>

                <div v-for="t in pestanasCanal" v-show="pestana === t.canal" :key="`p-${t.canal}`" class="mkt-c360-ficha__pestana">
                    <mkt-tarjeta-panel v-if="!t.piezas.length">
                        <mkt-estado-vacio
                            :icono="t.icono"
                            :titulo="`Sin piezas de ${t.label}`"
                            :texto="c.estrategia_estado === 'lista' ? 'Genera las piezas desde la pestaña Estrategia.' : 'Primero se genera la estrategia; después llegan las piezas.'"
                        />
                    </mkt-tarjeta-panel>

                    <template v-else-if="t.canal === 'reels' || t.canal === 'video'">
                        <tarjeta-guion
                            v-for="p in t.piezas"
                            :key="p.id"
                            :campana-id="id"
                            :pieza="p"
                            :videos="videosDe(p.id)"
                            :nichos="nichos"
                            :puede-generar="puedeGenerar"
                            @actualizada="reemplazarPieza"
                            @en-proceso="reanudarSondeo"
                            @video-creado="agregarVideo"
                        />
                    </template>

                    <tarjeta-copy
                        v-else-if="t.canal === 'facebook' || t.canal === 'instagram'"
                        :campana-id="id"
                        :piezas="t.piezas"
                        :puede-generar="puedeGenerar"
                        @actualizada="reemplazarPieza"
                        @en-proceso="reanudarSondeo"
                    />

                    <div v-else-if="t.canal === 'anuncios_meta'" class="mkt-c360-ficha__anuncios">
                        <tarjeta-anuncio
                            v-for="p in t.piezas"
                            :key="p.id"
                            :campana-id="id"
                            :pieza="p"
                            :puede-generar="puedeGenerar"
                            @actualizada="reemplazarPieza"
                            @en-proceso="reanudarSondeo"
                        />
                    </div>

                    <tarjeta-whats-app
                        v-else-if="t.canal === 'whatsapp'"
                        :campana-id="id"
                        :piezas="t.piezas"
                        :puede-generar="puedeGenerar"
                        @actualizada="reemplazarPieza"
                        @en-proceso="reanudarSondeo"
                    />
                </div>
            </template>
        </div>

        <!-- Confirmaciones -->
        <mkt-dialogo v-model="confirmarBorrar" titulo="Eliminar campaña" :guardando="borrando" :ancho="460">
            <p class="mkt-c360-ficha__confirma">Se elimina el borrador «{{ c?.nombre }}» con sus piezas de texto. Los videos ya producidos se conservan.</p>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="borrando" @click="confirmarBorrar = false" />
                <mkt-boton tipo="eliminar" etiqueta="Eliminar" :cargando="borrando" @click="borrar" />
            </template>
        </mkt-dialogo>

        <mkt-dialogo v-model="confirmarTransicion" :titulo="transicionPendiente?.label || 'Cambiar estado'" :guardando="!!cambiandoA" :ancho="460">
            <p class="mkt-c360-ficha__confirma">{{ textoConfirmaTransicion }}</p>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="No, regresar" :deshabilitado="!!cambiandoA" @click="confirmarTransicion = false" />
                <mkt-boton :tipo="transicionPendiente?.a === 'canceled' ? 'eliminar' : 'guardar'" :etiqueta="transicionPendiente?.label" :cargando="!!cambiandoA" @click="cambiarEstado(transicionPendiente)" />
            </template>
        </mkt-dialogo>

        <mkt-dialogo v-model="confirmarReemplazo" titulo="Volver a generar todo" :guardando="generando" :ancho="480">
            <p class="mkt-c360-ficha__confirma">
                La IA vuelve a escribir la estrategia y todas las piezas de texto. Lo que hayas editado a mano en las piezas se pierde.
                Los videos ya producidos se quedan.
            </p>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="generando" @click="confirmarReemplazo = false" />
                <mkt-boton tipo="guardar" icono-fa="fa-magic" etiqueta="Sí, generar todo" :cargando="generando" @click="generar('todo', true)" />
            </template>
        </mkt-dialogo>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import {
    MktRaiz,
    MktEncabezadoPantalla,
    MktTarjetaPanel,
    MktEstadoVacio,
    MktInsigniaEstado,
    MktDialogo,
    MktPestanas,
    MktFichaEncabezado,
    MktBoton,
    MktSelectorEmpresa,
    mensajeDeError,
    formatoMXN,
    formatoDia,
} from "../kit";
import {
    CANALES,
    TRANSICIONES,
    ESTADOS_GENERACION,
    etiquetaEstadoCampana,
    tonoEstadoCampana,
    etiquetaObjetivo,
    etiquetaCanal,
    diasEntre,
} from "./catalogos360";
import { crearSondeo, hayAlgoEnProceso } from "./sondeo";
import PanelEstrategia from "./PanelEstrategia.vue";
import TarjetaGuion from "./TarjetaGuion.vue";
import TarjetaCopy from "./TarjetaCopy.vue";
import TarjetaAnuncio from "./TarjetaAnuncio.vue";
import TarjetaWhatsApp from "./TarjetaWhatsApp.vue";

const CONFIRMA = {
    active: "La campaña pasa a Activa y aparece en el Panel de Marketing.",
    paused: "La campaña se pausa. Puedes reanudarla después.",
    finished: "La campaña se da por terminada. Ya no se podrá activar otra vez ni cambiar sus datos.",
    canceled: "La campaña se cancela. Ya no se podrá activar ni cambiar sus datos.",
};

/**
 * Ficha de Campaña 360 (/marketing/campanas-360/{id}) — contrato Fase 2 §2.4 rutas 3, 5, 6, 7 y 8; §2.9.
 * Sondeo de progreso cada 5 s solo mientras haya algo generando, en cola o produciendo.
 */
export default {
    name: "MarketingCampana360Ficha",
    components: {
        MktRaiz,
        MktEncabezadoPantalla,
        MktTarjetaPanel,
        MktEstadoVacio,
        MktInsigniaEstado,
        MktDialogo,
        MktPestanas,
        MktFichaEncabezado,
        MktBoton,
        MktSelectorEmpresa,
        PanelEstrategia,
        TarjetaGuion,
        TarjetaCopy,
        TarjetaAnuncio,
        TarjetaWhatsApp,
    },
    props: {
        campanaId: { type: [Number, String], required: true },
    },
    data() {
        return {
            contexto: null,
            id: Number(this.campanaId) || 0,
            c: null,
            carga: "cargando", // cargando | listo | error | sin_permiso | no_encontrado
            mensajeCarga: "",
            pestana: "resumen",
            aviso: null, // { tono, texto, reintentar? }
            iaNoConfigurada: "",
            generando: false,
            cambiandoA: null,
            borrando: false,
            confirmarBorrar: false,
            confirmarTransicion: false,
            confirmarReemplazo: false,
            transicionPendiente: null,
            nichos: [],
            sondeo: null,
            firmaProgreso: "",
        };
    },
    computed: {
        /** Botón principal de generar en el encabezado: 'todo' (nada generado), 'piezas' (falta generar piezas) o null. */
        accionGenerar() {
            if (this.sinGenerar && !this.iaNoConfigurada && this.puedeGenerar) return "todo";
            if (this.faltanPiezas && this.puedeGenerar) return "piezas";
            return null;
        },
        subtitulo() {
            if (!this.c) return "";
            const partes = [this.c.objetivo_etiqueta || etiquetaObjetivo(this.c.objetivo)];
            if (this.c.fecha_inicio || this.c.fecha_fin) partes.push(`${formatoDia(this.c.fecha_inicio)} – ${formatoDia(this.c.fecha_fin)}`);
            return partes.filter(Boolean).join(" · ");
        },
        cerrada() {
            return ["finished", "canceled"].includes(this.c?.estado);
        },
        editable() {
            return ["draft", "paused"].includes(this.c?.estado);
        },
        puedeGenerar() {
            return !!this.c && !this.cerrada;
        },
        ocupado() {
            return this.generando || !!this.cambiandoA || this.borrando;
        },
        transiciones() {
            return TRANSICIONES[this.c?.estado] || [];
        },
        diasCampana() {
            const d = diasEntre(this.c?.fecha_inicio, this.c?.fecha_fin);
            return d === null ? null : d + 1;
        },
        estadoGeneracion() {
            return ESTADOS_GENERACION[this.c?.generacion?.estado] || ESTADOS_GENERACION.sin_generar;
        },
        sinGenerar() {
            return this.c?.estrategia_estado === "sin_generar" && (this.c?.generacion?.estado || "sin_generar") === "sin_generar";
        },
        faltanPiezas() {
            return this.c?.estrategia_estado === "lista" && !this.c?.generacion?.piezas_total && this.c?.generacion?.estado !== "generando" && this.puedeGenerar;
        },
        mostrarProgreso() {
            return ["generando", "con_errores"].includes(this.c?.generacion?.estado);
        },
        avanceGeneracion() {
            const g = this.c?.generacion || {};
            return g.piezas_total ? (Number(g.piezas_listas) + Number(g.piezas_error || 0)) / Number(g.piezas_total) : 0;
        },
        textoProgreso() {
            const g = this.c?.generacion || {};
            if (this.c?.estrategia_estado === "generando") return "Escribiendo la estrategia…";
            if (g.estado === "generando") return `Generando piezas: ${g.piezas_listas || 0} de ${g.piezas_total || 0} listas`;
            if (g.estado === "con_errores") return `${g.piezas_listas || 0} de ${g.piezas_total || 0} piezas listas`;
            return "";
        },
        /** Pestañas tipo carpeta (sin íconos): Estrategia + un canal por pestaña; el estado va en texto. */
        itemsPestanas() {
            return [
                { clave: "resumen", etiqueta: "Estrategia" },
                ...this.pestanasCanal.map((t) => ({
                    clave: t.canal,
                    etiqueta: t.enProceso ? `${t.label} (generando)` : t.conError ? `${t.label} (con error)` : t.label,
                })),
            ];
        },
        pestanasCanal() {
            if (!this.c) return [];
            const canales = Array.isArray(this.c.canales) ? this.c.canales : [];
            return CANALES.filter((k) => canales.includes(k.value)).map((k) => {
                const piezas = this.piezasDe(k.pieza);
                return {
                    canal: k.value,
                    label: k.corto,
                    icono: k.icono,
                    piezas,
                    enProceso: piezas.some((p) => p.estado === "generando" || p.estado === "pendiente")
                        || this.videosDeTipo(k.pieza).some((v) => v.estado === "en_cola" || v.estado === "produciendo"),
                    conError: piezas.some((p) => p.estado === "error"),
                };
            });
        },
        datos() {
            const c = this.c || {};
            const p = c.publico || {};
            const o = c.oferta || {};
            return [
                { etiqueta: "Objetivo", valor: c.objetivo_etiqueta || etiquetaObjetivo(c.objetivo) },
                { etiqueta: "Tipo de público", valor: p.descripcion },
                { etiqueta: "Oferta", valor: o.descripcion },
                { etiqueta: "Plan", valor: o.plan },
                { etiqueta: "Precio al mes", valor: o.precio_mxn ? formatoMXN(o.precio_mxn) : "" },
                { etiqueta: "Condiciones", valor: o.condiciones },
                { etiqueta: "La oferta vale hasta", valor: o.vigencia_hasta ? formatoDia(o.vigencia_hasta) : "" },
                { etiqueta: "Canales", valor: (c.canales || []).map(etiquetaCanal).join(", ") },
                { etiqueta: "Presupuesto", valor: formatoMXN(c.presupuesto_mxn) },
            ];
        },
        textoConfirmaTransicion() {
            return CONFIRMA[this.transicionPendiente?.a] || "";
        },
    },
    mounted() {
        this.sondeo = crearSondeo({
            tarea: () => this.consultarProgreso(),
            alAgotarse: () => {
                this.aviso = {
                    tono: "info",
                    texto: "Dejamos de revisar el avance solo después de 30 minutos. Usa «Revisar ahora» para ver lo último.",
                    reintentar: true,
                };
            },
        });
        this.cargarNichos();
        this.cargar();
    },
    beforeUnmount() {
        if (this.sondeo) this.sondeo.detener();
    },
    methods: {
        etiquetaEstadoCampana,
        tonoEstadoCampana,
        etiquetaObjetivo,
        formatoMXN,
        formatoDia,
        /** Botón de cada cambio de estado con los tipos del sistema: el principal en azul, cancelar en rojo. */
        tipoTransicion(t) {
            if (t.a === "canceled") return "peligro-secundario";
            return t.principal ? "guardar" : "secundario";
        },
        iconoTransicion(t) {
            return { active: "fa-play", paused: "fa-pause", finished: "fa-flag", canceled: "fa-ban" }[t.a] || "";
        },
        numero: (v) => (v === null || v === undefined || v === "" || !Number.isFinite(Number(v)) ? null : Number(v)),
        piezasDe(tipo) {
            const lista = this.c?.piezas?.[tipo];
            return Array.isArray(lista) ? lista : [];
        },
        videosDe(guionId) {
            return (this.c?.videos || []).filter((v) => v.guion_id === guionId);
        },
        videosDeTipo(tipoGuion) {
            const ids = this.piezasDe(tipoGuion).map((p) => p.id);
            return (this.c?.videos || []).filter((v) => ids.includes(v.guion_id));
        },
        async cargarNichos() {
            try {
                const { data } = await axios.get("/api/marketing/niches");
                this.nichos = (Array.isArray(data?.data) ? data.data : [])
                    .filter((n) => n && n.slug)
                    .map((n) => ({ value: n.slug, label: n.name || n.slug, activo: n.active === undefined || !!n.active }));
            } catch (e) {
                this.nichos = [];
            }
        },
        /** GET ficha. `silencioso` = refresco tras el sondeo (sin pasar a «cargando»). */
        async cargar({ silencioso = false } = {}) {
            if (!silencioso) this.carga = "cargando";
            try {
                const { data } = await axios.get(`/api/marketing/campanas-360/${this.id}`);
                this.c = this.normalizar(data?.data);
                this.carga = "listo";
                this.firmaProgreso = this.firma(this.c);
                if (hayAlgoEnProceso(this.c)) this.sondeo.iniciar();
            } catch (e) {
                if (silencioso) return;
                const s = e?.response?.status;
                if (s === 403) this.carga = "sin_permiso";
                else if (s === 404) this.carga = "no_encontrado";
                else {
                    this.mensajeCarga = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.carga = "error";
                }
            }
        },
        normalizar(c) {
            const x = { ...(c || {}) };
            x.piezas = x.piezas && typeof x.piezas === "object" ? x.piezas : {};
            x.videos = Array.isArray(x.videos) ? x.videos : [];
            x.generacion = x.generacion || { estado: "sin_generar", piezas_total: 0, piezas_listas: 0, piezas_generando: 0, piezas_error: 0 };
            x.videos_resumen = x.videos_resumen || { total: 0, listos: 0, en_proceso: 0, error: 0 };
            return x;
        },
        /** Firma de estados (estrategia + piezas): si cambia, hay que traer la ficha completa (contenido nuevo). */
        firma(datos) {
            const piezas = Array.isArray(datos?.piezas) ? datos.piezas : Object.values(datos?.piezas || {}).flat();
            return [datos?.estrategia_estado, ...piezas.map((p) => `${p.id}:${p.estado}`).sort()].join("|");
        },
        /** Paso del sondeo: GET progreso; devuelve true si sigue habiendo algo en proceso. */
        async consultarProgreso() {
            const { data } = await axios.get(`/api/marketing/campanas-360/${this.id}/progreso`);
            if (!this.c) return false;
            this.c.generacion = data?.generacion || this.c.generacion;
            this.c.estrategia_estado = data?.estrategia_estado ?? this.c.estrategia_estado;
            this.c.estrategia_error = data?.estrategia_error ?? null;
            if (Array.isArray(data?.videos)) {
                this.c.videos = data.videos;
                this.c.videos_resumen = {
                    total: data.videos.length,
                    listos: data.videos.filter((v) => v.estado === "listo").length,
                    en_proceso: data.videos.filter((v) => v.estado === "en_cola" || v.estado === "produciendo").length,
                    error: data.videos.filter((v) => v.estado === "error").length,
                };
            }
            const nueva = this.firma(data);
            if (nueva !== this.firmaProgreso) {
                this.firmaProgreso = nueva;
                await this.cargar({ silencioso: true });
            }
            return hayAlgoEnProceso(data);
        },
        reanudarSondeo() {
            if (this.aviso?.reintentar) this.aviso = null;
            this.sondeo.detener();
            this.sondeo.iniciar();
        },

        // ── Acciones ────────────────────────────────────────────────────────
        async generar(alcance, reemplazar = false) {
            if (this.generando) return;
            this.generando = true;
            this.aviso = null;
            try {
                const { data } = await axios.post(`/api/marketing/campanas-360/${this.id}/generar`, { alcance, reemplazar });
                this.iaNoConfigurada = "";
                this.confirmarReemplazo = false;
                if (data?.generacion) this.c.generacion = data.generacion;
                if (alcance !== "piezas") this.c.estrategia_estado = "generando";
                message(alcance === "estrategia" ? "Generando la estrategia." : "Generando con IA. El avance se actualiza solo.");
                await this.cargar({ silencioso: true });
                this.reanudarSondeo();
            } catch (e) {
                const d = e?.response?.data || {};
                this.confirmarReemplazo = false;
                if (d.error === "ia_no_configurada") {
                    this.iaNoConfigurada = d.mensaje || "Falta asignar una IA en Integraciones → Módulos IA. Pídeselo a Irving.";
                } else if (d.error === "piezas_existentes") {
                    this.confirmarReemplazo = true;
                } else {
                    this.aviso = { tono: "peligro", texto: mensajeDeError(e, "No se pudo generar. Intenta de nuevo.") };
                    if (d.error === "generando") this.reanudarSondeo();
                }
            } finally {
                this.generando = false;
            }
        },
        pedirTransicion(t) {
            this.transicionPendiente = t;
            if (t.confirmar) this.confirmarTransicion = true;
            else this.cambiarEstado(t);
        },
        async cambiarEstado(t) {
            if (!t || this.cambiandoA) return;
            this.cambiandoA = t.a;
            this.aviso = null;
            try {
                const { data } = await axios.post(`/api/marketing/campanas-360/${this.id}/estado`, { estado: t.a });
                this.alActualizarCampana(data?.data);
                this.confirmarTransicion = false;
                message(`Campaña: ${this.c.estado_etiqueta || etiquetaEstadoCampana(this.c.estado)}.`);
            } catch (e) {
                this.confirmarTransicion = false;
                this.aviso = { tono: "peligro", texto: mensajeDeError(e, "No se pudo cambiar el estado.") };
            } finally {
                this.cambiandoA = null;
            }
        },
        async borrar() {
            if (this.borrando) return;
            this.borrando = true;
            try {
                await axios.delete(`/api/marketing/campanas-360/${this.id}`);
                message("Campaña eliminada.");
                window.location.href = "/marketing/campanas-360";
            } catch (e) {
                this.confirmarBorrar = false;
                this.aviso = { tono: "peligro", texto: mensajeDeError(e, "No se pudo eliminar.") };
                this.borrando = false;
            }
        },
        /** Respuesta Campana360 (sin piezas/videos) → se mezcla conservando piezas y videos. */
        alActualizarCampana(nueva) {
            if (!nueva) return;
            const { piezas, videos, ...resto } = nueva;
            this.c = this.normalizar({ ...this.c, ...resto, piezas: piezas || this.c.piezas, videos: videos || this.c.videos });
        },
        reemplazarPieza(pieza) {
            if (!pieza?.id || !this.c) return;
            const lista = this.piezasDe(pieza.pieza);
            const i = lista.findIndex((p) => p.id === pieza.id);
            if (i === -1) return;
            const copia = [...lista];
            copia[i] = { ...lista[i], ...pieza };
            this.c.piezas = { ...this.c.piezas, [pieza.pieza]: copia };
            this.firmaProgreso = this.firma(this.c);
        },
        agregarVideo(video) {
            if (!video?.id || !this.c) return;
            this.c.videos = [video, ...this.c.videos.filter((v) => v.id !== video.id)];
            this.c.videos_resumen = { ...this.c.videos_resumen, total: this.c.videos.length };
            this.reanudarSondeo();
        },
    },
};
</script>

<style scoped>
/* El único botón lleno (acción principal) va al final, a la derecha, como «Salvar Cliente» en la ficha de Cliente. */
.mkt-c360-ficha__principal { order: 1; }
.mkt-c360-ficha { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.mkt-c360-ficha > * { min-width: 0; }
/* Las pestañas y el encabezado traen su propio margen inferior; el gap del contenedor lo da. */
.mkt-c360-ficha > :deep(.mkt-pestanas),
.mkt-c360-ficha > :deep(.mkt-ficha) { margin-bottom: 0; }
.mkt-c360-ficha__cifra-neutra { color: var(--mkt-kpi-valor) !important; }
.mkt-c360-ficha__aviso {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid var(--mkt-borde);
}
.mkt-c360-ficha__aviso-texto { flex: 1 1 200px; min-width: 0; overflow-wrap: anywhere; }
.mkt-c360-ficha__aviso--peligro {
    background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent);
    border-color: color-mix(in srgb, var(--mkt-peligro) 40%, transparent);
}
.mkt-c360-ficha__aviso--info {
    background: color-mix(in srgb, var(--mkt-info) 10%, transparent);
    border-color: color-mix(in srgb, var(--mkt-info) 40%, transparent);
}
.mkt-c360-ficha__nota { margin: 8px 0 0; font-size: 14px; color: var(--mkt-texto-suave); }
.mkt-c360-ficha__pestana { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.mkt-c360-ficha__datos { display: grid; grid-template-columns: minmax(140px, max-content) minmax(0, 1fr); gap: 6px 24px; margin: 0; font-size: 14px; }
.mkt-c360-ficha__datos dt { color: var(--mkt-texto-suave); font-weight: 500; }
.mkt-c360-ficha__datos dd { margin: 0; overflow-wrap: anywhere; white-space: pre-line; }
.mkt-c360-ficha__suave { color: var(--mkt-texto-suave); }
.mkt-c360-ficha__pie { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-c360-ficha__anuncios { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
.mkt-c360-ficha__confirma { margin: 0; font-size: 14px; line-height: 22px; }

@media (max-width: 599px) {
    .mkt-c360-ficha__datos { grid-template-columns: minmax(0, 1fr); gap: 2px; }
    .mkt-c360-ficha__datos dd { margin-bottom: 8px; }
    .mkt-c360-ficha__anuncios { grid-template-columns: minmax(0, 1fr); }
}
</style>
