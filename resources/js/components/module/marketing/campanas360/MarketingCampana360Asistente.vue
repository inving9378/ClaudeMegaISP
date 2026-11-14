<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-c360-asis">
            <mkt-encabezado-pantalla
                :titulo="id ? 'Editar campaña' : 'Nueva campaña'"
                :subtitulo="subtitulo"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton tipo="volver" icono-fa="fa-arrow-left" :etiqueta="id ? 'Volver a la ficha' : 'Volver a la lista'" :href="urlVolver" />
                </template>

                <mkt-estado-vacio v-if="carga === 'sin_permiso'" icono="lock" titulo="No puedes crear campañas 360" texto="No tienes acceso a Marketing. Si lo necesitas, pídelo a Irving." />

                <mkt-estado-vacio v-else-if="carga === 'no_encontrado'" icono="search_off" titulo="No encontrado" texto="Esa campaña no existe o ya se eliminó." />

                <mkt-estado-vacio v-else-if="carga === 'error'" icono="cloud_off" titulo="No se pudo cargar la campaña" :texto="mensajeCarga" />

                <mkt-estado-vacio
                    v-else-if="carga === 'listo' && !editable"
                    icono="lock_clock"
                    titulo="Esta campaña no se puede editar ahora"
                    :texto="estadoCampana === 'active' ? 'Pausa la campaña para cambiar sus datos.' : 'Las campañas terminadas o canceladas ya no cambian.'" />
            </mkt-encabezado-pantalla>

            <mkt-tarjeta-panel
                v-if="!['sin_permiso', 'no_encontrado', 'error'].includes(carga) && !(carga === 'listo' && !editable)"
                :cargando="carga === 'cargando'"
                :titulo="pasoActual.titulo"
                :tono="pasoActual.tono"
                :icono-fa="pasoActual.fa"
            >
                <q-stepper
                    v-model="paso"
                    flat
                    header-nav
                    :contracted="esMovil"
                    :animated="false"
                    active-color="primary"
                    done-color="positive"
                    error-color="negative"
                    class="mkt-c360-asis__pasos"
                    @update:model-value="alCambiarPaso"
                >
                    <q-step
                        v-for="p in pasos"
                        :key="p.n"
                        :name="p.n"
                        :title="p.titulo"
                        :icon="p.icono"
                        :done="visitados.includes(p.n) && paso !== p.n && !pasoConError(p.n)"
                        :error="pasoConError(p.n)"
                        :header-nav="p.n <= maxPermitido"
                    >
                        <p class="mkt-c360-asis__ayuda">{{ p.ayuda }}</p>
                        <paso-objetivo v-if="p.n === 1" v-model="form" :errores="errores" />
                        <paso-publico v-else-if="p.n === 2" v-model="form" :errores="errores" />
                        <paso-oferta v-else-if="p.n === 3" v-model="form" :errores="errores" />
                        <paso-canales v-else-if="p.n === 4" v-model="form" :errores="errores" />
                        <paso-presupuesto-fechas v-else-if="p.n === 5" v-model="form" :errores="errores" :fecha-inicio-original="original?.fecha_inicio || null" />
                        <paso-revisar v-else v-model="form" @ir-a="irA" />
                    </q-step>
                </q-stepper>

                <div v-if="aviso" class="mkt-c360-asis__aviso" :class="`mkt-c360-asis__aviso--${aviso.tono}`" role="alert">
                    <i class="fa fa-fw" :class="aviso.tono === 'peligro' ? 'fa-ban' : 'fa-info-circle'" aria-hidden="true"></i>
                    <span class="mkt-c360-asis__aviso-texto">{{ aviso.texto }}</span>
                    <mkt-boton v-if="aviso.ficha && id" tipo="enlace" chico etiqueta="Ir a la ficha" :href="`/marketing/campanas-360/${id}`" />
                </div>

                <template #pie>
                    <div class="mkt-c360-asis__nav">
                        <mkt-boton
                            v-if="paso > 1"
                            tipo="volver"
                            icono-fa="fa-arrow-left"
                            etiqueta="Atrás"
                            :deshabilitado="ocupado"
                            @click="irA(paso - 1)"
                        />
                        <span class="mkt-c360-asis__espacio" />
                        <mkt-boton
                            tipo="secundario"
                            icono-fa="fa-save"
                            :etiqueta="id ? 'Guardar cambios' : 'Guardar borrador'"
                            :cargando="guardando"
                            :deshabilitado="ocupado"
                            @click="guardarBorrador"
                        />
                        <mkt-boton
                            v-if="paso < 6"
                            tipo="guardar"
                            etiqueta="Siguiente"
                            :deshabilitado="ocupado"
                            @click="siguiente"
                        ><i class="fa fa-fw fa-arrow-right ms-1" aria-hidden="true"></i></mkt-boton>
                        <mkt-boton
                            v-else-if="puedeGenerar"
                            tipo="guardar"
                            icono-fa="fa-magic"
                            etiqueta="Generar con IA"
                            :cargando="generando"
                            :deshabilitado="ocupado"
                            @click="generar"
                        />
                        <mkt-boton
                            v-else
                            tipo="guardar"
                            icono-fa="fa-check"
                            etiqueta="Guardar y ver la ficha"
                            :cargando="guardando"
                            :deshabilitado="ocupado"
                            @click="guardarYVer"
                        />
                    </div>
                </template>
            </mkt-tarjeta-panel>
        </div>
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
    MktBoton,
    mensajeDeError,
    erroresPorCampo,
} from "../kit";
import { LIMITES_CAMPANA, RE_PRECIO, RE_PRESUPUESTO, OBJETIVOS, CANALES, hoyMexico, diasEntre } from "./catalogos360";
import PasoObjetivo from "./PasoObjetivo.vue";
import PasoPublico from "./PasoPublico.vue";
import PasoOferta from "./PasoOferta.vue";
import PasoCanales from "./PasoCanales.vue";
import PasoPresupuestoFechas from "./PasoPresupuestoFechas.vue";
import PasoRevisar from "./PasoRevisar.vue";

const PASOS = [
    { n: 1, titulo: "Objetivo", icono: "flag", tono: "lila", fa: "fa-flag", ayuda: "Ponle nombre y dinos qué quieres lograr." },
    { n: 2, titulo: "Público", icono: "groups", tono: "azul", fa: "fa-users", ayuda: "A quién le habla la campaña. Mientras más claro, mejores piezas." },
    { n: 3, titulo: "Oferta", icono: "sell", tono: "verde", fa: "fa-tag", ayuda: "Lo que se ofrece. La IA solo usa estos datos: no inventa precios ni promociones." },
    { n: 4, titulo: "Canales", icono: "share", tono: "oliva", fa: "fa-share-alt", ayuda: "Dónde va a salir la campaña." },
    { n: 5, titulo: "Presupuesto y fechas", icono: "payments", tono: "crema", fa: "fa-money-bill-wave", ayuda: "Cuánto se invierte (en pesos) y cuándo corre." },
    { n: 6, titulo: "Revisar", icono: "fact_check", tono: "gris", fa: "fa-clipboard-check", ayuda: "Revisa todo. Puedes guardar como borrador o generar ya con IA." },
];

/** Paso al que pertenece cada campo del contrato (§2.4.2), para llevar al usuario al error. */
function pasoDeCampo(clave) {
    if (clave === "nombre" || clave === "objetivo") return 1;
    if (clave.startsWith("publico")) return 2;
    if (clave.startsWith("oferta")) return 3;
    if (clave.startsWith("canales")) return 4;
    if (clave === "presupuesto_mxn" || clave.startsWith("fecha_")) return 5;
    return 6;
}

const formVacio = () => ({
    nombre: "",
    objetivo: null,
    publico: { descripcion: "" },
    oferta: { descripcion: "", plan: "", precio_mxn: "", condiciones: "", vigencia_hasta: "" },
    canales: [],
    presupuesto_mxn: "",
    fecha_inicio: "",
    fecha_fin: "",
});

const limpiarMonto = (v) => String(v ?? "").replace(/[,\s$]/g, "");
const textoOpcional = (v) => {
    const t = String(v ?? "").trim();
    return t === "" ? null : t;
};

/**
 * Asistente de Campañas 360 (/marketing/campanas-360/nueva y /{id}/editar) — contrato Fase 2 §2.4 rutas 2, 3, 4 y 7.
 * Guardar en cualquier paso: POST la primera vez (la URL pasa a /{id}/editar para que recargar no duplique) y PUT después.
 */
export default {
    name: "MarketingCampana360Asistente",
    components: {
        MktRaiz,
        MktEncabezadoPantalla,
        MktTarjetaPanel,
        MktEstadoVacio,
        MktBoton,
        PasoObjetivo,
        PasoPublico,
        PasoOferta,
        PasoCanales,
        PasoPresupuestoFechas,
        PasoRevisar,
    },
    props: {
        campanaId: { type: [Number, String], default: null },
    },
    data() {
        const id = Number(this.campanaId) > 0 ? Number(this.campanaId) : null;
        return {
            contexto: null,
            pasos: PASOS,
            id,
            paso: 1,
            visitados: [1],
            form: formVacio(),
            original: null,
            carga: id ? "cargando" : "listo", // cargando | listo | error | sin_permiso | no_encontrado
            mensajeCarga: "",
            estadoCampana: "draft",
            generacionEstado: "sin_generar",
            errores: {},
            aviso: null, // { tono: 'peligro'|'info', texto, ficha?: bool }
            guardando: false,
            generando: false,
            esMovil: false,
            mql: null,
        };
    },
    computed: {
        /** Paso actual: da título, tono y ícono al bloque (como los bloques de la ficha de Cliente). */
        pasoActual() {
            return PASOS.find((p) => p.n === this.paso) || PASOS[0];
        },
        subtitulo() {
            if (this.id && this.form.nombre) return this.form.nombre;
            return "Seis pasos: objetivo, público, oferta, canales, presupuesto y revisión";
        },
        urlVolver() {
            return this.id ? `/marketing/campanas-360/${this.id}` : "/marketing/campanas-360";
        },
        editable() {
            return ["draft", "paused"].includes(this.estadoCampana);
        },
        puedeGenerar() {
            return this.generacionEstado === "sin_generar" && this.estadoCampana === "draft";
        },
        ocupado() {
            return this.guardando || this.generando;
        },
        /** Se puede saltar hacia adelante solo hasta el primer paso con error local. */
        maxPermitido() {
            for (let n = 1; n <= 5; n++) {
                if (Object.keys(this.validarPaso(n)).length) return Math.max(n, ...this.visitados);
            }
            return 6;
        },
    },
    mounted() {
        if (typeof window.matchMedia === "function") {
            this.mql = window.matchMedia("(max-width: 599px)");
            this.esMovil = this.mql.matches;
            this.mql.addEventListener ? this.mql.addEventListener("change", this.alCambiarAncho) : this.mql.addListener(this.alCambiarAncho);
        }
        if (this.id) this.cargarCampana();
    },
    beforeUnmount() {
        if (this.mql) {
            this.mql.removeEventListener ? this.mql.removeEventListener("change", this.alCambiarAncho) : this.mql.removeListener(this.alCambiarAncho);
        }
    },
    methods: {
        alCambiarAncho(e) {
            this.esMovil = e.matches;
        },
        async cargarCampana() {
            this.carga = "cargando";
            try {
                const { data } = await axios.get(`/api/marketing/campanas-360/${this.id}`);
                const c = data?.data || {};
                this.estadoCampana = c.estado || "draft";
                this.generacionEstado = c.generacion?.estado || "sin_generar";
                const base = formVacio();
                this.form = {
                    nombre: c.nombre || "",
                    objetivo: c.objetivo || null,
                    // Solo el tipo de público (ajuste de Henry): lo demás que traiga la campaña no se edita aquí.
                    publico: { ...base.publico, descripcion: c.publico?.descripcion || "" },
                    oferta: { ...base.oferta, ...(c.oferta || {}) },
                    canales: Array.isArray(c.canales) ? c.canales : [],
                    presupuesto_mxn: c.presupuesto_mxn ?? "",
                    fecha_inicio: c.fecha_inicio || "",
                    fecha_fin: c.fecha_fin || "",
                };
                ["plan", "precio_mxn", "condiciones", "vigencia_hasta"].forEach((k) => {
                    if (this.form.oferta[k] === null) this.form.oferta[k] = "";
                });
                this.original = { fecha_inicio: c.fecha_inicio || null };
                this.visitados = [1, 2, 3, 4, 5, 6];
                this.carga = "listo";
            } catch (e) {
                const s = e?.response?.status;
                if (s === 403) this.carga = "sin_permiso";
                else if (s === 404) this.carga = "no_encontrado";
                else {
                    this.mensajeCarga = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.carga = "error";
                }
            }
        },

        // ── Validación local (espejo de §2.4.2; el backend manda) ──────────
        validarPaso(n) {
            const f = this.form;
            const L = LIMITES_CAMPANA;
            const e = {};
            const largo = (v) => String(v ?? "").trim().length;
            if (n === 1) {
                if (largo(f.nombre) < L.nombre[0]) e.nombre = `Escribe un nombre de al menos ${L.nombre[0]} letras.`;
                else if (largo(f.nombre) > L.nombre[1]) e.nombre = `Máximo ${L.nombre[1]} caracteres.`;
                if (!OBJETIVOS.some((o) => o.value === f.objetivo)) e.objetivo = "Elige un objetivo.";
            }
            if (n === 2) {
                const p = f.publico || {};
                if (largo(p.descripcion) < L.publicoDescripcion[0]) e["publico.descripcion"] = "Escribe el tipo de público (al menos 3 letras).";
            }
            if (n === 3) {
                const o = f.oferta || {};
                if (largo(o.descripcion) < L.ofertaDescripcion[0]) e["oferta.descripcion"] = "Describe la oferta (al menos 3 letras).";
                const precio = limpiarMonto(o.precio_mxn);
                if (precio !== "" && !RE_PRECIO.test(precio)) e["oferta.precio_mxn"] = "Escribe el precio en pesos, con máximo 2 decimales. Ej.: 599.00";
            }
            if (n === 4) {
                const validos = (f.canales || []).filter((c) => CANALES.some((x) => x.value === c));
                if (!validos.length) e.canales = "Elige al menos un canal.";
            }
            if (n === 5) {
                const monto = limpiarMonto(f.presupuesto_mxn);
                if (monto === "") e.presupuesto_mxn = "Escribe el presupuesto (0 si es orgánica).";
                else if (!RE_PRESUPUESTO.test(monto)) e.presupuesto_mxn = "Escribe el monto en pesos, con máximo 2 decimales. Ej.: 15000.00";
                const hoy = hoyMexico();
                const cambioInicio = !this.original || this.original.fecha_inicio !== f.fecha_inicio;
                if (!f.fecha_inicio) e.fecha_inicio = "Elige la fecha de inicio.";
                else if (cambioInicio && f.fecha_inicio < hoy) e.fecha_inicio = "No puede empezar antes de hoy.";
                if (!f.fecha_fin) e.fecha_fin = "Elige la fecha de fin.";
                else if (f.fecha_inicio && f.fecha_fin < f.fecha_inicio) e.fecha_fin = "La fecha de fin no puede ser anterior a la de inicio.";
                else if (f.fecha_inicio && diasEntre(f.fecha_inicio, f.fecha_fin) > L.vigenciaDias) e.fecha_fin = `La campaña puede durar máximo ${L.vigenciaDias} días.`;
            }
            return e;
        },
        pasoConError(n) {
            return Object.keys(this.errores).some((k) => pasoDeCampo(k) === n);
        },
        limpiarErroresDe(n) {
            this.errores = Object.fromEntries(Object.entries(this.errores).filter(([k]) => pasoDeCampo(k) !== n));
        },
        marcarVisitado(n) {
            if (!this.visitados.includes(n)) this.visitados = [...this.visitados, n];
        },
        irA(n) {
            this.paso = n;
            this.marcarVisitado(n);
            this.$nextTick(() => this.$el?.scrollIntoView?.({ block: "start", behavior: "smooth" }));
        },
        alCambiarPaso(n) {
            this.marcarVisitado(n);
        },
        siguiente() {
            const e = this.validarPaso(this.paso);
            this.limpiarErroresDe(this.paso);
            if (Object.keys(e).length) {
                this.errores = { ...this.errores, ...e };
                return;
            }
            this.aviso = null;
            this.irA(this.paso + 1);
        },

        // ── Guardar y generar ──────────────────────────────────────────────
        payload() {
            const f = this.form;
            const p = f.publico || {};
            const o = f.oferta || {};
            return {
                nombre: String(f.nombre || "").trim(),
                objetivo: f.objetivo,
                // Solo el tipo de público: edad, nichos, zonas e intereses se omiten (ya no se piden).
                publico: {
                    descripcion: String(p.descripcion || "").trim(),
                },
                oferta: {
                    descripcion: String(o.descripcion || "").trim(),
                    plan: textoOpcional(o.plan),
                    precio_mxn: textoOpcional(limpiarMonto(o.precio_mxn)),
                    condiciones: textoOpcional(o.condiciones),
                    vigencia_hasta: textoOpcional(o.vigencia_hasta),
                },
                canales: f.canales || [],
                presupuesto_mxn: limpiarMonto(f.presupuesto_mxn),
                fecha_inicio: textoOpcional(f.fecha_inicio),
                fecha_fin: textoOpcional(f.fecha_fin),
            };
        },
        /** POST o PUT. Devuelve true si guardó. Maneja 422 (campos), 409 y errores de red. */
        async guardar() {
            this.aviso = null;
            try {
                if (this.id) {
                    const { data } = await axios.put(`/api/marketing/campanas-360/${this.id}`, this.payload());
                    this.estadoCampana = data?.data?.estado || this.estadoCampana;
                    this.original = { fecha_inicio: data?.data?.fecha_inicio ?? this.form.fecha_inicio };
                } else {
                    const { data } = await axios.post("/api/marketing/campanas-360", this.payload());
                    const nuevo = Number(data?.data?.id);
                    if (!nuevo) throw new Error("respuesta sin id");
                    this.id = nuevo;
                    this.original = { fecha_inicio: data?.data?.fecha_inicio ?? this.form.fecha_inicio };
                    // Recargar a mitad ya no crea otra campaña: la URL apunta a la que se acaba de crear.
                    try {
                        window.history.replaceState(window.history.state, "", `/marketing/campanas-360/${nuevo}/editar`);
                    } catch (e) { /* sin history: no pasa nada */ }
                }
                this.errores = {};
                return true;
            } catch (e) {
                const s = e?.response?.status;
                if (s === 422 && e.response.data?.errors) {
                    // «publico» u «oferta» sin subcampo (bloque faltante) se pinta en su campo obligatorio.
                    const crudos = erroresPorCampo(e);
                    const alias = { publico: "publico.descripcion", oferta: "oferta.descripcion" };
                    this.errores = Object.fromEntries(Object.entries(crudos).map(([k, v]) => [alias[k] || k, v]));
                    const pasosConError = [...new Set(Object.keys(this.errores).map(pasoDeCampo))].sort((a, b) => a - b);
                    const nombres = pasosConError.map((n) => PASOS[n - 1]?.titulo).filter(Boolean).join(", ");
                    this.aviso = {
                        tono: "peligro",
                        texto: `Aún no se puede guardar: faltan o hay que corregir datos en ${nombres}. Los pasos marcados en rojo dicen qué falta.`,
                    };
                } else if (s === 403) {
                    this.aviso = { tono: "peligro", texto: "No tienes permiso para guardar campañas." };
                } else {
                    this.aviso = { tono: "peligro", texto: mensajeDeError(e, "No se pudo guardar. Intenta de nuevo."), ficha: s === 409 };
                }
                return false;
            }
        },
        async guardarBorrador() {
            if (this.ocupado) return;
            this.guardando = true;
            const nuevo = !this.id;
            const ok = await this.guardar();
            this.guardando = false;
            if (ok) message(nuevo ? "Borrador guardado." : "Cambios guardados.");
        },
        async guardarYVer() {
            if (this.ocupado) return;
            if (!this.validarTodo()) return;
            this.guardando = true;
            const ok = await this.guardar();
            if (ok) {
                window.location.href = `/marketing/campanas-360/${this.id}`;
                return;
            }
            this.guardando = false;
        },
        /** Valida los 5 pasos; si alguno falla lo marca y lleva al primero. */
        validarTodo() {
            let todos = {};
            for (let n = 1; n <= 5; n++) todos = { ...todos, ...this.validarPaso(n) };
            this.errores = todos;
            const claves = Object.keys(todos);
            if (!claves.length) return true;
            const primero = Math.min(...claves.map(pasoDeCampo));
            this.aviso = { tono: "peligro", texto: `Faltan datos en «${PASOS[primero - 1].titulo}».` };
            this.irA(primero);
            return false;
        },
        async generar() {
            if (this.ocupado) return;
            if (!this.validarTodo()) return;
            this.generando = true;
            const ok = await this.guardar();
            if (!ok) {
                this.generando = false;
                return;
            }
            try {
                await axios.post(`/api/marketing/campanas-360/${this.id}/generar`, { alcance: "todo", reemplazar: false });
                message("Generando con IA. Puedes seguir el avance en la ficha.");
                window.location.href = `/marketing/campanas-360/${this.id}`;
                return;
            } catch (e) {
                const d = e?.response?.data || {};
                if (d.error === "ia_no_configurada") {
                    this.aviso = { tono: "info", texto: `La campaña quedó guardada como borrador, pero no se pudo generar: ${d.mensaje || "falta asignar una IA."}`, ficha: true };
                } else {
                    this.aviso = { tono: "peligro", texto: `La campaña quedó guardada, pero no se pudo generar: ${mensajeDeError(e, "intenta de nuevo desde la ficha.")}`, ficha: true };
                }
            }
            this.generando = false;
        },
    },
};
</script>

<style scoped>
.mkt-c360-asis { min-width: 0; }
.mkt-c360-asis__pasos { background: transparent; color: inherit; }
.mkt-c360-asis__pasos :deep(.q-stepper__header) { border-bottom: 1px solid var(--mkt-borde); }
.mkt-c360-asis__pasos :deep(.q-stepper__step-inner) { padding: 16px 0 8px; }
.mkt-c360-asis__pasos :deep(.q-stepper__title) { font-size: 14px; }
.mkt-c360-asis__pasos :deep(.q-stepper__tab) { padding: 12px; }
.mkt-c360-asis__pasos :deep(.q-stepper__dot) { margin-right: 8px; flex: 0 0 auto; }
.mkt-c360-asis__pasos :deep(.q-stepper__header--contracted .q-stepper__dot) { margin-right: 0; }
.mkt-c360-asis__ayuda { margin: 0 0 16px; font-size: 14px; color: var(--mkt-texto-suave); }
.mkt-c360-asis__aviso {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
    padding: 12px 16px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid var(--mkt-borde);
}
.mkt-c360-asis__aviso-texto { flex: 1 1 200px; min-width: 0; overflow-wrap: anywhere; }
.mkt-c360-asis__aviso--peligro {
    background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent);
    border-color: color-mix(in srgb, var(--mkt-peligro) 40%, transparent);
}
.mkt-c360-asis__aviso--info {
    background: color-mix(in srgb, var(--mkt-info) 10%, transparent);
    border-color: color-mix(in srgb, var(--mkt-info) 40%, transparent);
}
.mkt-c360-asis__nav { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 4px 0; }
.mkt-c360-asis__espacio { flex: 1 1 auto; }

@media (max-width: 599px) {
    .mkt-c360-asis__pasos :deep(.q-stepper__tab) { padding: 12px 4px; min-height: 0; }
    .mkt-c360-asis__nav > :deep(.btn) { flex: 1 1 100%; }
    .mkt-c360-asis__espacio { display: none; }
}
</style>
