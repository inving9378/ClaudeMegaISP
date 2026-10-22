<template>
    <div class="ea-wrap" :class="{ 'ea-dark': darkMode }">
        <section v-if="!puedeGestionar" class="ea-card">
            <div class="ea-body">Solo un super-administrador puede ver y cambiar los envíos automáticos.</div>
        </section>

        <template v-else-if="estado">
            <section class="ea-card">
                <div class="ea-head">Avisos de cobro automáticos</div>
                <div class="ea-body">
                    <div class="ea-fila">
                        <span class="ea-badge" :class="estado.pausado ? 'ea-badge-pausa' : 'ea-badge-activo'">
                            {{ estado.pausado ? "Pausados" : "Activos" }}
                        </span>
                        <span class="ea-nota">
                            <template v-if="estado.cambiado_at">
                                Último cambio: {{ estado.cambiado_por || "un proceso del sistema" }} · {{ fecha(estado.cambiado_at) }}
                            </template>
                            <template v-else>Sin registro de quién ni cuándo: el último cambio es anterior a esta pantalla.</template>
                        </span>
                        <button v-if="estado.pausado" type="button" class="btn btn-success btn-sm ms-auto" @click="abrir('reactivar')">
                            Reactivar envíos…
                        </button>
                        <button v-else type="button" class="btn btn-outline-danger btn-sm ms-auto" @click="abrir('pausar')">
                            Pausar envíos…
                        </button>
                    </div>
                    <p class="ea-nota">
                        Controla el envío automático de prefacturas, recibos y tickets. Pausado, los documentos se siguen
                        generando y quedan en cola; nada sale solo.
                    </p>
                    <p v-if="estado.ritmo" class="ea-aviso">
                        Enviando en lotes de {{ estado.ritmo.por_hora }} por hora ({{ estado.ritmo.enviados_ultima_hora }} en la
                        última hora) hasta vaciar lo acumulado antes del {{ fecha(estado.ritmo.acumulado_hasta) }}. Después se quita solo.
                    </p>
                    <p v-if="!estado.pausado && estado.etapa.nombre === 'cerrado'" class="ea-aviso">
                        Aunque estén activos, hoy no sale nada automático: la etapa de reactivación es «cerrado».
                    </p>
                    <div class="ea-etapa">
                        <div class="ea-fila">
                            <b>Etapa de reactivación:</b>
                            <span class="ea-badge" :class="etapaVista.clase">{{ etapaVista.nombre }}</span>
                            <span>{{ etapaVista.detalle }}</span>
                            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" @click="abrir('etapa')">Cambiar etapa…</button>
                        </div>
                        <p class="ea-nota">
                            <template v-if="estado.etapa.origen === 'pantalla' && estado.etapa.cambiada_at">
                                Cambiada por {{ estado.etapa.cambiada_por || "un proceso del sistema" }} · {{ fecha(estado.etapa.cambiada_at) }}.
                            </template>
                            <template v-else>Hoy viene del .env del servidor (BILLING_REACTIVACION_ETAPA) hasta que se cambie aquí.</template>
                            Es la segunda compuerta: aunque se quite la pausa, solo sale lo que la etapa permite. Se detiene con
                            {{ estado.etapa.max_errores_por_corrida }} errores por corrida · WhatsApp {{ estado.etapa.whatsapp ? "aprobado" : "no aprobado" }}.
                        </p>
                    </div>
                </div>
            </section>

            <section class="ea-card">
                <div class="ea-head ea-fila">
                    En cola
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto ea-btn-head" @click="abrir('depurar')">
                        Depurar cola…
                    </button>
                </div>
                <div class="ea-body">
                    <table class="table table-sm ea-tabla mb-1">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th class="text-end">En cola</th>
                                <th class="text-end">Con más de {{ estado.opciones.horas_descarte }} h</th>
                                <th class="text-end">Con correo</th>
                                <th>La más vieja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in estado.cola.tipos" :key="t.tipo">
                                <td>{{ t.nombre }}</td>
                                <td class="text-end">{{ numero(t.total) }}</td>
                                <td class="text-end">{{ numero(t.viejas) }}</td>
                                <td class="text-end">{{ numero(t.con_correo) }}</td>
                                <td>{{ t.mas_vieja ? `${antiguedad(t.mas_vieja)} (${fecha(t.mas_vieja, true)})` : "—" }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="ea-nota">Solo salen los que tienen correo y ya cumplieron su ventana de espera.</p>
                </div>
            </section>

            <section class="ea-card">
                <div class="ea-head">Proforma anticipada (BILLING_PROFORMA_ANTICIPADA)</div>
                <div class="ea-body">
                    <div class="ea-fila">
                        <span class="ea-badge" :class="estado.proforma_anticipada.encendido ? 'ea-badge-activo' : 'ea-badge-gris'">
                            {{ estado.proforma_anticipada.encendido ? "Encendida" : "Apagada" }}
                        </span>
                        <span>Días de anticipo: <b>{{ estado.proforma_anticipada.dias }}</b> (valor general)</span>
                    </div>
                    <p class="ea-nota">
                        <template v-if="estado.proforma_anticipada.clientes_con_valor_propio">
                            {{ numero(estado.proforma_anticipada.clientes_con_valor_propio) }} clientes tienen su propio valor
                            (de {{ estado.proforma_anticipada.rango_propio[0] }} a {{ estado.proforma_anticipada.rango_propio[1] }} días).
                        </template>
                        Creación automática de proformas:
                        {{ estado.proforma_anticipada.creacion_automatica === null ? "sin configurar" : estado.proforma_anticipada.creacion_automatica ? "activa" : "apagada" }}.
                        <template v-if="!estado.proforma_anticipada.encendido">
                            Apagada, las proformas se generan como siempre (el día 25 del mes anterior) y los días no se usan.
                        </template>
                    </p>
                    <p class="ea-nota">
                        El interruptor se cambia en el .env del servidor; los días, en Notificaciones Finanzas › Facturas proforma.
                    </p>
                </div>
            </section>
        </template>

        <div v-else-if="errorCarga" class="ea-card"><div class="ea-body">{{ errorCarga }}</div></div>

        <div v-if="dialogo" class="ea-velo" @click.self="cerrar">
            <div class="ea-dialogo" role="dialog" aria-modal="true">
                <template v-if="dialogo === 'reactivar'">
                    <div class="ea-head">Reactivar envíos automáticos</div>
                    <div class="ea-body">
                        <p class="mb-2">
                            <b>¿Qué hago con lo acumulado?</b> Hay {{ numero(estado.cola.total) }} avisos en cola;
                            {{ numero(estado.cola.con_correo) }} tienen correo y podrían salir.
                        </p>
                        <p v-if="estado.etapa.nombre === 'cerrado'" class="ea-aviso mb-2">
                            La etapa de reactivación está en «cerrado»: aunque reactives, no sale nada hasta abrir la etapa
                            («Cambiar etapa…»). Lo que elijas aquí con lo acumulado sí se aplica ahora.
                        </p>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': opcion === 'descartar_48h' }">
                            <input v-model="opcion" type="radio" value="descartar_48h" />
                            <span>
                                <b>Descartar lo de más de {{ estado.opciones.horas_descarte }} h</b> <span class="ea-reco">Recomendada</span><br />
                                Se cancelan {{ numero(estado.cola.viejas) }} avisos viejos (no se borran y se pueden revertir). Sale solo lo reciente.
                            </span>
                        </label>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': opcion === 'enviar_todo' }">
                            <input v-model="opcion" type="radio" value="enviar_todo" />
                            <span>
                                <b>Enviar todo</b><br />
                                Sale lo que hay en cola, incluidos avisos de hace meses que pueden ya no ser ciertos.
                            </span>
                        </label>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': opcion === 'lotes_por_hora' }">
                            <input v-model="opcion" type="radio" value="lotes_por_hora" />
                            <span>
                                <b>Enviar en lotes por hora</b><br />
                                Sale todo, pero con tope por hora hasta vaciar lo acumulado.
                                <span v-if="opcion === 'lotes_por_hora'" class="ea-fila mt-1">
                                    <input v-model.number="porHora" type="number" class="form-control form-control-sm ea-input"
                                           :min="estado.opciones.lote_min" :max="estado.opciones.lote_max" />
                                    por hora · unas {{ horasParaVaciar }} h para vaciar
                                </span>
                            </span>
                        </label>
                        <p class="ea-nota">La etapa sigue mandando: {{ etapaVista.nombre }}, {{ etapaVista.detalle }}.</p>
                        <label class="ea-label" for="ea-conf">Escribe REACTIVAR para confirmar</label>
                        <input id="ea-conf" v-model="confirmacion" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                </template>
                <template v-else-if="dialogo === 'etapa'">
                    <div class="ea-head">Etapa de reactivación</div>
                    <div class="ea-body">
                        <p class="mb-2">Cuánto sale cuando los envíos están activos. Se guarda aquí; no hace falta tocar el .env.</p>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': nivel === 'cerrado' }">
                            <input v-model="nivel" type="radio" value="cerrado" />
                            <span><b>Cerrado</b><br />No sale nada automático aunque se quite la pausa.</span>
                        </label>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': nivel === 'baja' }">
                            <input v-model="nivel" type="radio" value="baja" />
                            <span>
                                <b>Etapa baja</b> <span class="ea-reco">Para empezar</span><br />
                                Todos los clientes, con tope. Cada corrida es cada 15 minutos.
                                <span v-if="nivel === 'baja'" class="ea-fila mt-1">
                                    <input v-model.number="porCorrida" type="number" class="form-control form-control-sm ea-input" min="1"
                                           :max="estado.opciones.etapa_corrida_max" aria-label="Por corrida" /> por corrida ·
                                    <input v-model.number="porDia" type="number" class="form-control form-control-sm ea-input" min="1"
                                           :max="estado.opciones.etapa_dia_max" aria-label="Por día" /> por día
                                </span>
                            </span>
                        </label>
                        <label class="ea-opcion" :class="{ 'ea-opcion-sel': nivel === 'normal', 'ea-opcion-off': estado.etapa.nivel !== 'baja' }">
                            <input v-model="nivel" type="radio" value="normal" :disabled="estado.etapa.nivel !== 'baja'" />
                            <span>
                                <b>Normal</b><br />
                                Sin tope de etapa: hasta 100 por corrida, cada 15 minutos.
                                <template v-if="estado.etapa.nivel !== 'baja'">Se habilita desde la etapa baja.</template>
                            </span>
                        </label>
                        <p v-if="estado.pausado && nivel !== 'cerrado'" class="ea-nota mb-2">
                            Los envíos están pausados: la etapa se guarda, pero no sale nada hasta reactivarlos.
                        </p>
                        <label class="ea-label" for="ea-conf">Escribe ETAPA para confirmar</label>
                        <input id="ea-conf" v-model="confirmacion" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                </template>
                <template v-else-if="dialogo === 'depurar'">
                    <div class="ea-head">Depurar la cola de avisos</div>
                    <div class="ea-body">
                        <p class="mb-2">
                            Cancela los avisos que llevan más de las horas indicadas en cola. No se borran: queda un archivo para
                            revertirlo. Funciona aunque los envíos estén pausados.
                        </p>
                        <div class="ea-fila mb-2">
                            <label class="ea-label m-0" for="ea-horas">Con más de</label>
                            <input id="ea-horas" v-model.number="depurarHoras" type="number" min="1" :max="estado.opciones.depurar_max_horas"
                                   class="form-control form-control-sm ea-input" @input="pedirConteo" />
                            <span>horas</span>
                            <select v-model="depurarTipo" class="form-select form-select-sm ea-select" aria-label="Tipo de aviso">
                                <option value="">Todos los tipos</option>
                                <option v-for="t in estado.cola.tipos" :key="t.tipo" :value="t.tipo">{{ t.nombre }}</option>
                            </select>
                        </div>
                        <table class="table table-sm ea-tabla mb-1">
                            <thead><tr><th>Tipo</th><th class="text-end">Se cancelarían</th></tr></thead>
                            <tbody>
                                <tr v-for="t in conteo?.tipos || []" :key="t.tipo" :class="{ 'ea-fuera': depurarTipo && depurarTipo !== t.tipo }">
                                    <td>{{ t.nombre }}</td>
                                    <td class="text-end">{{ contando ? "…" : numero(t.n) }}</td>
                                </tr>
                                <tr><td><b>Total a cancelar</b></td><td class="text-end"><b>{{ contando ? "…" : numero(aDepurar) }}</b></td></tr>
                            </tbody>
                        </table>
                        <label class="ea-label" for="ea-conf">Escribe DEPURAR para confirmar</label>
                        <input id="ea-conf" v-model="confirmacion" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                </template>
                <template v-else>
                    <div class="ea-head">Pausar envíos automáticos</div>
                    <div class="ea-body">
                        <p>Dejan de salir prefacturas, recibos y tickets automáticos. Lo que se genere queda en cola hasta reactivar.</p>
                        <label class="ea-label" for="ea-conf">Escribe PAUSAR para confirmar</label>
                        <input id="ea-conf" v-model="confirmacion" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                </template>
                <div v-if="errorAccion" class="ea-error">{{ errorAccion }}</div>
                <div class="ea-pie">
                    <button type="button" class="btn btn-light btn-sm" :disabled="enviando" @click="cerrar">Cancelar</button>
                    <button type="button" class="btn btn-sm" :class="{ reactivar: 'btn-success', etapa: 'btn-primary' }[dialogo] || 'btn-danger'"
                            :disabled="enviando || !confirmacionValida || (dialogo === 'depurar' && (contando || !aDepurar))" @click="aplicar">
                        {{ enviando ? "Aplicando…" : { reactivar: "Reactivar", pausar: "Pausar", depurar: `Cancelar ${numero(aDepurar)}`, etapa: "Guardar etapa" }[dialogo] }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Configuración › Finanzas › «Envíos automáticos»: estado y control de billing_config.envio_pausado,
 * la cola por tipo y la proforma anticipada. Solo super-administrador (lo exige el servidor).
 * GET /configuracion/envios-automaticos/estado · POST …/pausar · POST …/reactivar ·
 * GET …/depurar/conteo · POST …/depurar · POST …/etapa (etapa de reactivación en billing_config).
 */
import { computed, onMounted, ref } from "vue";
import { darkMode } from "../../../../../hook/appConfig";

const props = defineProps({ puedeGestionar: { type: Boolean, default: false } });

const estado = ref(null);
const errorCarga = ref("");
const dialogo = ref(null);
const opcion = ref("descartar_48h");
const porHora = ref(50);
const confirmacion = ref("");
const enviando = ref(false);
const errorAccion = ref("");

const depurarHoras = ref(48);
const depurarTipo = ref("");
const conteo = ref(null);
const contando = ref(false);
let temporizadorConteo = null;

const nivel = ref("baja");
const porCorrida = ref(20);
const porDia = ref(100);

const palabra = computed(() => ({ reactivar: "REACTIVAR", pausar: "PAUSAR", depurar: "DEPURAR", etapa: "ETAPA" })[dialogo.value]);
/** Cómo se ve la etapa: la de la pantalla (cerrado / baja / normal) o, si nunca se cambió aquí, la del .env. */
const etapaVista = computed(() => {
    const e = estado.value?.etapa || {};
    const topes = `${numero(e.limite_por_corrida)} por corrida y ${numero(e.limite_diario)} por día`;
    if (e.nivel === "cerrado" || e.nombre === "cerrado") return { nombre: "Cerrado", clase: "ea-badge-pausa", detalle: "no sale nada automático" };
    if (e.nivel === "baja") return { nombre: "Etapa baja", clase: "ea-badge-baja", detalle: topes };
    if (e.nivel === "normal") return { nombre: "Normal", clase: "ea-badge-activo", detalle: "sin tope de etapa (hasta 100 por corrida, cada 15 minutos)" };
    return { nombre: `«${e.nombre}» (del .env)`, clase: "ea-badge-gris", detalle: topes };
});
const aDepurar = computed(() => {
    if (!conteo.value) return 0;
    return depurarTipo.value ? conteo.value.tipos.find((t) => t.tipo === depurarTipo.value)?.n || 0 : conteo.value.total;
});
const confirmacionValida = computed(() => confirmacion.value.trim().toUpperCase() === palabra.value);
const horasParaVaciar = computed(() => {
    const n = Number(porHora.value) || 0;
    return n > 0 ? Math.ceil((estado.value?.cola.con_correo || 0) / n) : "—";
});

const cargar = async () => {
    try {
        const { data } = await axios.get("/configuracion/envios-automaticos/estado");
        estado.value = data;
        porHora.value = data.opciones.lote_sugerido;
    } catch (e) {
        errorCarga.value = e.response?.data?.message || "No se pudo cargar el estado de los envíos.";
    }
};

onMounted(() => {
    if (props.puedeGestionar) cargar();
});

const abrir = (tipo) => {
    dialogo.value = tipo;
    opcion.value = "descartar_48h";
    confirmacion.value = "";
    errorAccion.value = "";
    if (tipo === "etapa") {
        const e = estado.value.etapa;
        nivel.value = !e.nivel || e.nivel === "cerrado" ? "baja" : e.nivel;
        porCorrida.value = e.nivel === "baja" ? e.limite_por_corrida : estado.value.opciones.etapa_baja_por_corrida;
        porDia.value = e.nivel === "baja" ? e.limite_diario : estado.value.opciones.etapa_baja_por_dia;
    }
    if (tipo === "depurar") {
        depurarHoras.value = estado.value.opciones.horas_descarte;
        depurarTipo.value = "";
        conteo.value = null;
        cargarConteo();
    }
};

const cargarConteo = async () => {
    const horas = Number(depurarHoras.value);
    if (!Number.isInteger(horas) || horas < 1) {
        conteo.value = null;
        return;
    }
    contando.value = true;
    try {
        const { data } = await axios.get("/configuracion/envios-automaticos/depurar/conteo", { params: { horas } });
        if (horas === Number(depurarHoras.value)) conteo.value = data;
    } catch (e) {
        errorAccion.value = e.response?.data?.message || "No se pudo contar la cola.";
    } finally {
        contando.value = false;
    }
};

const pedirConteo = () => {
    clearTimeout(temporizadorConteo);
    temporizadorConteo = setTimeout(cargarConteo, 400);
};

const cerrar = () => {
    if (!enviando.value) dialogo.value = null;
};

const aplicar = async () => {
    enviando.value = true;
    errorAccion.value = "";
    try {
        const url = `/configuracion/envios-automaticos/${dialogo.value}`;
        const palabraEscrita = confirmacion.value.trim().toUpperCase();
        const cuerpo = {
            reactivar: { opcion: opcion.value, por_hora: opcion.value === "lotes_por_hora" ? porHora.value : null, confirmacion: palabraEscrita },
            pausar: { confirmacion: palabraEscrita },
            depurar: { horas: Number(depurarHoras.value), tipo: depurarTipo.value || null, esperado: aDepurar.value, confirmacion: palabraEscrita },
            etapa: {
                nivel: nivel.value,
                por_corrida: nivel.value === "baja" ? Number(porCorrida.value) : null,
                por_dia: nivel.value === "baja" ? Number(porDia.value) : null,
                confirmacion: palabraEscrita,
            },
        }[dialogo.value];
        const { data } = await axios.post(url, cuerpo);
        estado.value = data.estado;
        toastr.success(data.message, "Envíos automáticos");
        dialogo.value = null;
    } catch (e) {
        const errores = e.response?.data?.errors;
        errorAccion.value = errores ? Object.values(errores).flat()[0] : e.response?.data?.message || "No se pudo aplicar.";
        if (e.response?.data?.estado) estado.value = e.response.data.estado;
        if (e.response?.data?.conteo) conteo.value = e.response.data.conteo;
    } finally {
        enviando.value = false;
    }
};

const numero = (n) => Number(n || 0).toLocaleString("es-MX");
const fecha = (iso, soloDia = false) => {
    const d = new Date(iso);
    return soloDia
        ? d.toLocaleDateString("es-MX", { day: "numeric", month: "short", year: "numeric" })
        : d.toLocaleString("es-MX", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
};
const antiguedad = (iso) => {
    const horas = Math.floor((Date.now() - new Date(iso).getTime()) / 3600000);
    if (horas < 48) return `hace ${horas} h`;
    const dias = Math.floor(horas / 24);
    return dias < 60 ? `hace ${dias} días` : `hace ${Math.floor(dias / 30)} meses`;
};
</script>

<style scoped>
.ea-wrap { max-width: 980px; }
.ea-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 12px 0; }
.ea-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.ea-body { padding: 12px 16px; }
.ea-fila { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ea-badge { font-weight: 700; font-size: 12px; padding: 3px 10px; border-radius: 999px; }
.ea-badge-pausa { background: #FEF3C7; color: #92400E; }
.ea-badge-activo { background: #DCFCE7; color: #166534; }
.ea-badge-gris { background: #F1F5F9; color: #475569; }
.ea-badge-baja { background: #E0F2FE; color: #075985; }
.ea-nota { font-size: 12px; color: #6b7280; margin: 6px 0 0; }
.ea-aviso { font-size: 12.5px; background: #FFF7ED; color: #9A3412; border-radius: 8px; padding: 6px 10px; margin: 8px 0 0; }
.ea-etapa { font-size: 12.5px; margin-top: 10px; color: #334155; }
.ea-tabla { font-size: 13px; }
.ea-input { width: 90px; display: inline-block; }
.ea-select { width: auto; display: inline-block; }
.ea-btn-head { font-weight: 500; }
.ea-fuera td { opacity: 0.45; }
.ea-label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin: 10px 0 4px; }
.ea-velo { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 1050; display: flex;
    align-items: center; justify-content: center; padding: 16px; }
.ea-dialogo { background: #fff; border-radius: 12px; width: 100%; max-width: 560px; max-height: 90vh; overflow: auto; }
.ea-opcion { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 8px 10px; margin-bottom: 8px; font-size: 13px; cursor: pointer; }
.ea-opcion input[type="radio"] { margin-top: 4px; }
.ea-opcion-sel { border-color: #6366F1; background: #F5F7FF; }
.ea-opcion-off { opacity: 0.55; cursor: not-allowed; }
.ea-reco { font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; border-radius: 999px; padding: 1px 8px; }
.ea-error { color: #B91C1C; font-size: 12.5px; padding: 0 16px 8px; }
.ea-pie { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f3f5; }
.ea-dark .ea-card, .ea-dark .ea-dialogo { background: #1d1d21; border-color: #2c2c34; color: #e5e7eb; }
.ea-dark .ea-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.ea-dark .ea-nota, .ea-dark .ea-etapa { color: #9ca3af; }
.ea-dark .ea-label { color: #cbd5e1; }
.ea-dark .ea-opcion { border-color: #2c2c34; }
.ea-dark .ea-opcion-sel { background: #26262c; border-color: #818CF8; }
.ea-dark .ea-aviso { background: #3b2a1a; color: #fdba74; }
.ea-dark .ea-tabla { color: #e5e7eb; }
/* El tema oscuro global pinta de blanco el texto de los span: los distintivos fijan el suyo. */
.ea-dark .ea-badge-pausa { background: #78350F; color: #FDE68A !important; }
.ea-dark .ea-badge-activo { background: #14532D; color: #BBF7D0 !important; }
.ea-dark .ea-badge-gris { background: #334155; color: #E2E8F0 !important; }
.ea-dark .ea-badge-baja { background: #0C4A6E; color: #BAE6FD !important; }
.ea-dark .ea-reco { background: #14532D; color: #BBF7D0 !important; }
</style>
