<template>
    <div class="cf-wrap" :class="{ 'cf-dark': darkMode }">
        <section v-if="!puedeGestionar" class="cf-card">
            <div class="cf-body">Solo un super-administrador puede ver y resolver las correcciones de facturación.</div>
        </section>

        <template v-else>
            <section class="cf-card">
                <div class="cf-head">Correcciones de facturación</div>
                <div class="cf-body">
                    <p class="cf-nota mt-0">
                        Al actualizar el sistema se revisan las facturas abiertas de todos los clientes. Lo que es seguro se
                        corrige solo (deuda fantasma que no mueve dinero y casos ya decididos); aquí queda lo que necesita a una
                        persona. Nada se borra: cada corrección guarda respaldo y forma de deshacerla.
                    </p>
                    <div v-if="corrida" class="cf-fila">
                        <span>Última revisión: <b>{{ estadoCorrida[corrida.estado] || corrida.estado }}</b>
                            {{ corrida.terminada_at ? `· ${fecha(corrida.terminada_at)}` : "" }}</span>
                        <span v-for="(n, k) in resumen" :key="k" class="cf-chip">{{ etiquetaResumen[k] || k }}: {{ n }}</span>
                    </div>
                    <div v-else class="cf-nota">Aún no corre ninguna revisión en este servidor.</div>
                    <div v-if="corrida?.error" class="cf-aviso">{{ corrida.error }}</div>
                </div>
            </section>

            <div class="cf-tabs">
                <button type="button" :class="{ activo: vista === 'pendientes' }" @click="cambiar('pendientes')">
                    Pendientes ({{ conteo.pendiente || 0 }})
                </button>
                <button type="button" :class="{ activo: vista === 'historial' }" @click="cambiar('historial')">Historial</button>
            </div>

            <div v-if="cargando" class="cf-nota">Cargando…</div>
            <div v-else-if="!correcciones.length" class="cf-card"><div class="cf-body">
                {{ vista === "pendientes" ? "No hay correcciones pendientes." : "Todavía no hay correcciones resueltas." }}
            </div></div>

            <section v-for="c in correcciones" :key="c.id" class="cf-card">
                <div class="cf-head cf-fila">
                    <a :href="`/cliente/editar/${c.client_id}`" target="_blank" rel="noopener">Cliente #{{ c.client_id }}</a>
                    <span class="cf-cliente">{{ c.cliente }}</span>
                    <span class="cf-chip">{{ tipos[c.tipo] }}</span>
                    <span class="cf-chip cf-chip-suave">{{ origenes[c.origen] || c.origen }}</span>
                    <span v-if="vista === 'historial'" class="cf-chip" :class="`cf-est-${c.estado}`">{{ etiquetaEstado(c) }}</span>
                </div>
                <div class="cf-body">
                    <p class="mb-2">{{ c.detalle }}</p>

                    <template v-if="c.tipo === 'reacomodo'">
                        <div class="table-responsive">
                            <table class="table table-sm cf-tabla mb-2">
                                <thead>
                                    <tr><th>Factura</th><th>Periodo</th><th>Vence</th><th class="text-end">Total</th><th>Antes</th><th>Después</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="f in c.antes || []" :key="f.factura" :class="{ 'cf-cambia': f.cambia }">
                                        <td>{{ f.factura }}</td>
                                        <td>{{ f.periodo }}</td>
                                        <td>{{ f.vence }}</td>
                                        <td class="text-end">{{ dinero(f.total) }}</td>
                                        <td>{{ lado(f.antes) }}</td>
                                        <td><b v-if="f.anula">Se cancela</b><template v-else>{{ lado(f.despues) }}</template></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="c.deuda_antes !== null" class="cf-nota">
                            Lo que debe en estas facturas: {{ dinero(c.deuda_antes) }} → <b>{{ dinero(c.deuda_despues) }}</b>
                        </p>
                        <p v-for="p in c.despues?.sin_factura || []" :key="`sf${p.pago}`" class="cf-nota">
                            El pago {{ p.pago }} ({{ p.fecha }}, {{ dinero(p.monto) }}) queda sin factura: su dinero sigue como saldo a favor.
                        </p>
                    </template>

                    <template v-else-if="c.tipo === 'monto_pago'">
                        <p class="cf-nota">
                            Pago {{ c.antes.pago }} del {{ c.antes.fecha }}: el pago dice <b>{{ dinero(c.antes.en_pago) }}</b> y el estado
                            de cuenta <b>{{ dinero(c.antes.en_cuenta) }}</b>. Saldo actual: {{ dinero(c.antes.saldo) }}.
                        </p>
                        <template v-if="c.estado === 'pendiente'">
                            <label v-for="o in c.despues?.opciones || []" :key="o.monto" class="cf-opcion" :class="{ sel: montos[c.id] === o.monto }">
                                <input v-model="montos[c.id]" type="radio" :value="o.monto" />
                                <span><b>El banco confirma {{ dinero(o.monto) }}</b><br />{{ o.efecto }}</span>
                            </label>
                        </template>
                        <p v-else-if="c.parametros?.monto !== undefined" class="cf-nota">Monto confirmado: <b>{{ dinero(c.parametros.monto) }}</b></p>
                    </template>

                    <template v-else>
                        <p class="cf-nota">
                            {{ c.antes?.detalle }}<template v-if="c.antes?.fecha_corte"> · Fecha de corte: {{ String(c.antes.fecha_corte).slice(0, 10) }}</template>
                            <template v-if="c.antes?.saldo !== undefined"> · Saldo: {{ dinero(c.antes.saldo) }}</template>
                        </p>
                        <p class="cf-nota">No trae una corrección para aplicar: se atiende a mano y aquí se marca como atendida.</p>
                    </template>

                    <div v-if="vista === 'historial'" class="cf-nota">
                        {{ cierre(c) }}
                        <template v-if="c.motivo"> Motivo: {{ c.motivo }}</template>
                        <template v-if="c.tiene_deshacer"> Respaldo y SQL para deshacer guardados ({{ c.respaldo_path }}).</template>
                    </div>

                    <div v-if="c.estado === 'pendiente'" class="cf-acciones">
                        <button type="button" class="btn btn-light btn-sm" @click="abrir('descartar', c)">
                            {{ c.tipo === "revision" ? "Marcar atendida" : "Descartar" }}
                        </button>
                        <button v-if="c.tipo !== 'revision'" type="button" class="btn btn-success btn-sm"
                                :disabled="c.tipo === 'monto_pago' && montos[c.id] === undefined" @click="abrir('aprobar', c)">
                            Aprobar
                        </button>
                    </div>
                </div>
            </section>
        </template>

        <div v-if="dialogo" class="cf-velo" @click.self="cerrar">
            <div class="cf-dialogo" role="dialog" aria-modal="true">
                <div class="cf-head">{{ dialogo.accion === "aprobar" ? "Aprobar corrección" : atendida ? "Marcar atendida" : "Descartar corrección" }} · cliente #{{ dialogo.c.client_id }}</div>
                <div class="cf-body">
                    <template v-if="dialogo.accion === 'aprobar'">
                        <p v-if="dialogo.c.tipo === 'monto_pago'">
                            Se registra que el banco confirmó <b>{{ dinero(montos[dialogo.c.id]) }}</b> para el pago {{ dialogo.c.antes.pago }}.
                        </p>
                        <p v-else>Se aplica el reacomodo tal como se muestra: cada pago a su factura y las marcadas se cancelan.</p>
                        <p class="cf-nota">Antes de escribir se guarda un respaldo y el SQL para deshacerlo. Si los datos del cliente cambiaron, no se aplica y se recalcula.</p>
                    </template>
                    <template v-else>
                        <label class="cf-label" for="cf-motivo">{{ atendida ? "¿Qué se hizo con este caso?" : "¿Por qué se descarta?" }}</label>
                        <textarea id="cf-motivo" v-model="motivo" class="form-control form-control-sm" rows="3"></textarea>
                    </template>
                    <div v-if="errorAccion" class="cf-error">{{ errorAccion }}</div>
                </div>
                <div class="cf-pie">
                    <button type="button" class="btn btn-light btn-sm" :disabled="enviando" @click="cerrar">Cancelar</button>
                    <button type="button" class="btn btn-sm" :class="dialogo.accion === 'aprobar' || atendida ? 'btn-success' : 'btn-danger'"
                            :disabled="enviando || (dialogo.accion === 'descartar' && motivo.trim().length < 5)" @click="confirmar">
                        {{ enviando ? "Aplicando…" : dialogo.accion === "aprobar" ? "Aprobar" : atendida ? "Marcar atendida" : "Descartar" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Finanzas › «Correcciones pendientes» (item #571, Parte B). GET /finanzas/correcciones-pendientes/listar,
 * POST …/{id}/aprobar y …/{id}/descartar. Solo super-administrador (lo exige el servidor).
 */
import { computed, onMounted, reactive, ref } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({ puedeGestionar: { type: Boolean, default: false } });

const vista = ref("pendientes");
const correcciones = ref([]);
const conteo = ref({});
const corrida = ref(null);
const cargando = ref(false);
const montos = reactive({});
const dialogo = ref(null);
const motivo = ref("");
const enviando = ref(false);
const errorAccion = ref("");

const tipos = { reacomodo: "Reacomodo de pagos", monto_pago: "Monto de pago", revision: "Revisión" };
const origenes = { propuesta: "Propuesta", decidida: "Caso decidido", automatica: "Automática" };
const estados = { pendiente: "Pendiente", aplicada: "Aplicada", descartada: "Descartada", obsoleta: "Ya no aplica" };
const estadoCorrida = { pendiente: "pendiente", en_proceso: "en proceso", terminada: "terminada", fallida: "falló" };
const etiquetaResumen = { aplicada_sola: "Corregidos solos", pendiente: "Pendientes", ya_resuelta: "Ya resueltos", ya_aplicada: "Ya aplicados", sin_cambios: "Sin cambios", error: "Con error", obsoletas: "Ya no aplican" };
const estadosFactura = { draft: "sin pagar", issued: "emitida", partially_paid: "pago parcial", paid: "pagada", overdue: "vencida", cancelled: "cancelada" };

const resumen = computed(() => {
    try {
        return typeof corrida.value?.resumen === "string" ? JSON.parse(corrida.value.resumen) : corrida.value?.resumen || {};
    } catch (e) {
        return {};
    }
});

const cargar = async () => {
    cargando.value = true;
    try {
        const { data } = await axios.get("/finanzas/correcciones-pendientes/listar", { params: { vista: vista.value } });
        correcciones.value = data.correcciones;
        conteo.value = data.conteo || {};
        corrida.value = data.ultima_corrida;
    } catch (e) {
        toastr.error(e.response?.data?.message || "No se pudieron cargar las correcciones.");
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    if (props.puedeGestionar) cargar();
});

const cambiar = (v) => {
    vista.value = v;
    cargar();
};

const abrir = (accion, c) => {
    dialogo.value = { accion, c };
    motivo.value = "";
    errorAccion.value = "";
};

const cerrar = () => {
    if (!enviando.value) dialogo.value = null;
};

const confirmar = async () => {
    const { accion, c } = dialogo.value;
    enviando.value = true;
    errorAccion.value = "";
    try {
        const cuerpo = accion === "aprobar" ? { monto: c.tipo === "monto_pago" ? montos[c.id] : null } : { motivo: motivo.value.trim() };
        const { data } = await axios.post(`/finanzas/correcciones-pendientes/${c.id}/${accion}`, cuerpo);
        toastr.success(data.message, `Cliente #${c.client_id}`);
        dialogo.value = null;
        cargar();
    } catch (e) {
        const errores = e.response?.data?.errors;
        errorAccion.value = errores ? Object.values(errores).flat()[0] : e.response?.data?.message || "No se pudo aplicar.";
        if (e.response?.status === 422) cargar();
    } finally {
        enviando.value = false;
    }
};

const dinero = (n) => Number(n || 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
const lado = (x) => (x ? `${estadosFactura[x.estado] || x.estado} · ${x.pago ? `pago ${x.pago}` : "sin pago"} · debe ${dinero(x.pendiente)}` : "");
const fecha = (iso) => new Date(iso).toLocaleString("es-MX", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
// Una revisión no se «descarta»: se marca atendida (mismo estado en el servidor, otra palabra).
const etiquetaEstado = (c) => (c.tipo === "revision" && c.estado === "descartada" ? "Atendida" : estados[c.estado] || c.estado);
const cierre = (c) => {
    const t = `${etiquetaEstado(c)}${c.resuelta_por ? ` por ${c.resuelta_por}` : ""}${c.resuelta_at ? ` · ${fecha(c.resuelta_at)}` : ""}`;
    return t.endsWith(".") ? t : `${t}.`;
};
const atendida = computed(() => dialogo.value?.accion === "descartar" && dialogo.value?.c.tipo === "revision");
</script>

<style scoped>
.cf-wrap { max-width: 1100px; }
.cf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 12px 0; }
.cf-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.cf-body { padding: 12px 16px; font-size: 13px; }
.cf-fila { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cf-cliente { font-weight: 600; color: #1f2937; }
.cf-chip { font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; background: #E0E7FF; color: #3730A3; }
.cf-chip-suave { background: #F1F5F9; color: #475569; }
.cf-est-aplicada { background: #DCFCE7; color: #166534; }
.cf-est-descartada { background: #FEE2E2; color: #991B1B; }
.cf-est-obsoleta { background: #F1F5F9; color: #475569; }
.cf-nota { font-size: 12.5px; color: #6b7280; margin: 6px 0 0; }
.cf-aviso { font-size: 12.5px; background: #FEF2F2; color: #991B1B; border-radius: 8px; padding: 6px 10px; margin-top: 8px; }
.cf-tabs { display: flex; gap: 6px; margin-top: 4px; }
.cf-tabs button { border: 1px solid #e5e7eb; background: #fff; border-radius: 999px; padding: 4px 14px; font-size: 13px; }
.cf-tabs button.activo { background: #4F46E5; border-color: #4F46E5; color: #fff; }
.cf-tabla { font-size: 12.5px; }
.cf-cambia td { background: #FFFBEB; }
.cf-opcion { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; margin: 8px 0 0; cursor: pointer; }
.cf-opcion input { margin-top: 4px; }
.cf-opcion.sel { border-color: #6366F1; background: #F5F7FF; }
.cf-acciones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.cf-label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
.cf-error { color: #B91C1C; font-size: 12.5px; margin-top: 8px; }
.cf-velo { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 16px; }
.cf-dialogo { background: #fff; border-radius: 12px; width: 100%; max-width: 520px; }
.cf-pie { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f3f5; }
.cf-dark .cf-card, .cf-dark .cf-dialogo { background: #1d1d21; border-color: #2c2c34; color: #e5e7eb; }
.cf-dark .cf-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.cf-dark .cf-cliente { color: #e5e7eb; }
.cf-dark .cf-nota { color: #9ca3af; }
.cf-dark .cf-tabla { color: #e5e7eb; }
.cf-dark .cf-cambia td { background: #2a2414; }
.cf-dark .cf-opcion { border-color: #2c2c34; }
.cf-dark .cf-opcion.sel { background: #26262c; border-color: #818CF8; }
.cf-dark .cf-tabs button { background: #1d1d21; border-color: #2c2c34; color: #e5e7eb; }
.cf-dark .cf-tabs button.activo { background: #4F46E5; color: #fff !important; }
/* El tema oscuro global pinta de blanco el texto de los span: los distintivos fijan el suyo. */
.cf-dark .cf-chip { background: #312E81; color: #E0E7FF !important; }
.cf-dark .cf-chip-suave { background: #334155; color: #E2E8F0 !important; }
.cf-dark .cf-est-aplicada { background: #14532D; color: #BBF7D0 !important; }
.cf-dark .cf-est-descartada { background: #7F1D1D; color: #FECACA !important; }
.cf-dark .cf-aviso { background: #3b1a1a; color: #fca5a5; }
</style>
