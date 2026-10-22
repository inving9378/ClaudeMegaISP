<template>
    <div class="ec-wrap" :class="{ 'ec-dark': darkMode }">
        <section v-if="!puedeGestionar" class="ec-card">
            <div class="ec-body">Solo un super-administrador puede ver la cola de avisos.</div>
        </section>

        <section v-else class="ec-card">
            <div class="ec-head ec-fila">
                Detalle de la cola de avisos
                <a href="/configuracion/envios-automaticos" class="btn btn-outline-secondary btn-sm ms-auto ec-btn-head">Volver a Envíos automáticos</a>
            </div>
            <div class="ec-body">
                <div class="ec-filtros">
                    <label class="ec-campo">
                        <span class="ec-label">Tipo</span>
                        <select v-model="filtro.tipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option v-for="(nombre, clave) in TIPOS" :key="clave" :value="clave">{{ nombre }}</option>
                        </select>
                    </label>
                    <label class="ec-campo">
                        <span class="ec-label">Estado</span>
                        <select v-model="filtro.estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option v-for="(nombre, clave) in ESTADOS" :key="clave" :value="clave">{{ nombre }}</option>
                        </select>
                    </label>
                    <label class="ec-campo">
                        <span class="ec-label">Antigüedad</span>
                        <select v-model="antiguedad" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <option value="24">Más de 24 horas</option>
                            <option value="48">Más de 48 horas</option>
                            <option value="168">Más de 7 días</option>
                            <option value="720">Más de 30 días</option>
                            <option value="rango">Entre fechas…</option>
                        </select>
                    </label>
                    <template v-if="antiguedad === 'rango'">
                        <label class="ec-campo">
                            <span class="ec-label">Desde</span>
                            <input v-model="filtro.desde" type="date" class="form-control form-control-sm" />
                        </label>
                        <label class="ec-campo">
                            <span class="ec-label">Hasta</span>
                            <input v-model="filtro.hasta" type="date" class="form-control form-control-sm" />
                        </label>
                    </template>
                    <label class="ec-campo ec-campo-ancho">
                        <span class="ec-label">Cliente</span>
                        <input v-model="filtro.cliente" type="search" class="form-control form-control-sm" placeholder="Número o nombre" />
                    </label>
                    <button type="button" class="btn btn-outline-secondary btn-sm ec-limpiar" @click="limpiarFiltro">Limpiar</button>
                </div>

                <div class="ec-barra">
                    <span class="ec-nota">
                        {{ numero(total) }} avisos con este filtro<template v-if="seleccionTexto"> · <b>{{ seleccionTexto }}</b></template>
                    </span>
                    <button v-if="total && !todosDelFiltro" type="button" class="btn btn-link btn-sm" @click="elegirTodosDelFiltro">
                        Seleccionar los {{ numero(total) }} del filtro
                    </button>
                    <button v-if="haySeleccion" type="button" class="btn btn-link btn-sm" @click="limpiarSeleccion">Quitar selección</button>
                    <span class="ms-auto"></span>
                    <button type="button" class="btn btn-outline-danger btn-sm" :disabled="!haySeleccion" @click="abrir('cancelar')">Cancelar…</button>
                    <button type="button" class="btn btn-outline-success btn-sm" :disabled="!haySeleccion" @click="abrir('reactivar')">Reactivar…</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm ec-tabla mb-1">
                        <thead>
                            <tr>
                                <th class="ec-check">
                                    <input type="checkbox" :checked="paginaMarcada" :disabled="todosDelFiltro || !filas.length"
                                           aria-label="Seleccionar la página" @change="alternarPagina" />
                                </th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th class="text-end">Monto</th>
                                <th>Canal</th>
                                <th>Creado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in filas" :key="f.id" :class="{ 'ec-marcada': marcada(f.id) }">
                                <td class="ec-check">
                                    <input type="checkbox" :checked="marcada(f.id)" :disabled="todosDelFiltro"
                                           :aria-label="`Seleccionar aviso ${f.id}`" @change="alternar(f.id)" />
                                </td>
                                <td>{{ TIPO_UNO[f.tipo] || f.tipo }}</td>
                                <td>
                                    <a :href="`/cliente/editar/${f.cliente_id}`" target="_blank" rel="noopener">#{{ f.cliente_id }}</a>
                                    {{ f.cliente || "" }}
                                </td>
                                <td class="text-end">{{ f.monto === null ? "—" : dinero(f.monto) }}</td>
                                <td><span class="ec-chip" :class="`ec-canal-${f.canal}`">{{ CANALES[f.canal] }}</span></td>
                                <td class="ec-fecha">{{ fechaHora(f.creado) }}</td>
                                <td>
                                    <span class="ec-chip" :class="`ec-est-${f.estado}`" :title="f.nota || ''">{{ ESTADOS[f.estado] || f.estado }}</span>
                                </td>
                            </tr>
                            <tr v-if="!filas.length && !cargando">
                                <td colspan="7" class="ec-vacio">No hay avisos con este filtro.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="errorCarga" class="ec-error">{{ errorCarga }}</p>

                <div class="ec-paginas">
                    <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="pagina <= 1 || cargando" @click="ir(pagina - 1)">‹ Anterior</button>
                    <span class="ec-nota">Página {{ pagina }} de {{ paginas }} · {{ POR_PAGINA }} por página</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="pagina >= paginas || cargando" @click="ir(pagina + 1)">Siguiente ›</button>
                </div>
                <p class="ec-nota">
                    Los tickets son solo para imprimir en caja: no se envían y no se pueden reactivar. Lo enviado no se toca.
                </p>
            </div>
        </section>

        <div v-if="dialogo" class="ec-velo" @click.self="cerrar">
            <div class="ec-dialogo" role="dialog" aria-modal="true">
                <div class="ec-head">{{ dialogo === "cancelar" ? "Cancelar avisos" : "Reactivar avisos" }}</div>
                <div class="ec-body">
                    <p v-if="!conteo && !errorAccion" class="ec-nota">Contando…</p>
                    <template v-if="conteo">
                        <p>
                            De {{ numero(conteo.seleccionados) }} seleccionados, se {{ dialogo === "cancelar" ? "cancelan" : "reactivan" }}
                            <b>{{ numero(conteo.total) }}</b>:
                        </p>
                        <table class="table table-sm ec-tabla mb-2">
                            <tbody>
                                <tr v-for="t in conteo.tipos" :key="t.tipo" :class="{ 'ec-fuera': !t.n }">
                                    <td>{{ t.nombre }}</td>
                                    <td class="text-end">{{ numero(t.n) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="conteo.no_aplican" class="ec-nota">
                            {{ numero(conteo.no_aplican) }} no cambian:
                            {{ dialogo === "cancelar" ? "ya se enviaron o ya estaban cancelados." : "están en espera, ya se enviaron o son tickets (solo caja)." }}
                        </p>
                        <p v-if="!conteo.total" class="ec-aviso">No hay nada que {{ dialogo === "cancelar" ? "cancelar" : "reactivar" }} en lo seleccionado.</p>
                        <p v-else-if="dialogo === 'reactivar'" class="ec-aviso">
                            Vuelven a «en espera»; {{ numero(conteo.con_correo) }} tienen correo.
                            <template v-if="conteo.pausado">Los envíos están pausados: no sale nada hasta que se reactiven.</template>
                            <template v-else>
                                Los envíos están activos: salen con el próximo envío automático (cada 15 minutos), con los límites de la
                                etapa «{{ conteo.etapa }}».
                            </template>
                        </p>
                        <p v-else class="ec-nota">
                            Se cancelan, no se borran: queda un archivo para revertir y el registro en la auditoría.
                        </p>
                        <template v-if="conteo.total">
                            <label class="ec-label" for="ec-conf">Escribe {{ palabra }} para confirmar</label>
                            <input id="ec-conf" v-model="confirmacion" class="form-control form-control-sm" autocomplete="off" @keyup.enter="aplicar" />
                        </template>
                    </template>
                </div>
                <div v-if="errorAccion" class="ec-error">{{ errorAccion }}</div>
                <div class="ec-pie">
                    <button type="button" class="btn btn-light btn-sm" :disabled="enviando" @click="cerrar">Volver</button>
                    <button type="button" class="btn btn-sm" :class="dialogo === 'cancelar' ? 'btn-danger' : 'btn-success'"
                            :disabled="!puedeAplicar" @click="aplicar">
                        {{ enviando ? "Aplicando…" : `${dialogo === "cancelar" ? "Cancelar" : "Reactivar"} ${numero(conteo?.total || 0)}` }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * #579 «Ver detalle de la cola» (Configuración › Finanzas › Envíos automáticos): lista paginada de
 * los avisos (50 por página) con filtros por tipo, estado, antigüedad y cliente, y selección para
 * cancelar o reactivar (filas sueltas o todos los del filtro). Solo super-administrador (lo exige
 * el servidor). GET …/cola/listar · POST …/cola/conteo · POST …/cola/aplicar.
 */
import { computed, onMounted, reactive, ref, watch } from "vue";
import { darkMode } from "../../../../../hook/appConfig";

const props = defineProps({ puedeGestionar: { type: Boolean, default: false } });

const URL = "/configuracion/envios-automaticos/cola";
const POR_PAGINA = 50;
const TIPOS = { prefactura: "Prefacturas", recibo: "Recibos", ticket: "Tickets" };
const TIPO_UNO = { prefactura: "Prefactura", recibo: "Recibo", ticket: "Ticket" };
const ESTADOS = { en_espera: "En espera", enviado: "Enviado", cancelado: "Cancelado", error: "Error" };
const CANALES = { correo: "Correo", whatsapp: "WhatsApp (respaldo)", caja: "Solo caja", ninguno: "Sin canal" };

const filtroVacio = () => ({ tipo: "", estado: "en_espera", desde: "", hasta: "", cliente: "" });
const filtro = reactive(filtroVacio());
const antiguedad = ref("");
const filas = ref([]);
const total = ref(0);
const pagina = ref(1);
const paginas = ref(1);
const cargando = ref(false);
const errorCarga = ref("");

const marcadas = ref(new Set());
const todosDelFiltro = ref(false);

const dialogo = ref(null);
const conteo = ref(null);
const confirmacion = ref("");
const enviando = ref(false);
const errorAccion = ref("");

const palabra = computed(() => (dialogo.value === "cancelar" ? "CANCELAR" : "REACTIVAR"));
const haySeleccion = computed(() => todosDelFiltro.value || marcadas.value.size > 0);
const paginaMarcada = computed(() => filas.value.length > 0 && filas.value.every((f) => marcadas.value.has(f.id)));
const seleccionTexto = computed(() => {
    if (todosDelFiltro.value) return `seleccionados los ${numero(total.value)} del filtro`;
    return marcadas.value.size ? `${numero(marcadas.value.size)} seleccionados` : "";
});
const puedeAplicar = computed(() => !enviando.value && conteo.value?.total > 0 && confirmacion.value.trim().toUpperCase() === palabra.value);

/** El filtro tal como lo pide el servidor (las horas salen de «Antigüedad»). */
const filtroServidor = () => ({
    tipo: filtro.tipo || null,
    estado: filtro.estado || null,
    horas: antiguedad.value && antiguedad.value !== "rango" ? Number(antiguedad.value) : null,
    desde: antiguedad.value === "rango" && filtro.desde ? filtro.desde : null,
    hasta: antiguedad.value === "rango" && filtro.hasta ? filtro.hasta : null,
    cliente: filtro.cliente.trim() || null,
});

let pedido = 0;
const cargar = async () => {
    const yo = ++pedido;
    cargando.value = true;
    errorCarga.value = "";
    try {
        const params = Object.fromEntries(Object.entries({ ...filtroServidor(), pagina: pagina.value }).filter(([, v]) => v !== null));
        const { data } = await axios.get(`${URL}/listar`, { params });
        if (yo !== pedido) return;
        filas.value = data.filas;
        total.value = data.total;
        pagina.value = data.pagina;
        paginas.value = data.paginas;
    } catch (e) {
        if (yo !== pedido) return;
        const errores = e.response?.data?.errors;
        errorCarga.value = errores ? Object.values(errores).flat()[0] : e.response?.data?.message || "No se pudo cargar la cola.";
    } finally {
        if (yo === pedido) cargando.value = false;
    }
};

let temporizador = null;
const recargarDesdeInicio = (espera = 0) => {
    clearTimeout(temporizador);
    limpiarSeleccion();
    temporizador = setTimeout(() => {
        pagina.value = 1;
        cargar();
    }, espera);
};

watch(() => [filtro.tipo, filtro.estado, antiguedad.value, filtro.desde, filtro.hasta], () => recargarDesdeInicio());
watch(() => filtro.cliente, () => recargarDesdeInicio(400));

onMounted(() => {
    if (props.puedeGestionar) cargar();
});

const ir = (n) => {
    pagina.value = n;
    cargar();
};

const limpiarFiltro = () => {
    Object.assign(filtro, filtroVacio());
    antiguedad.value = "";
};

const marcada = (id) => todosDelFiltro.value || marcadas.value.has(id);
const alternar = (id) => {
    const s = new Set(marcadas.value);
    s.has(id) ? s.delete(id) : s.add(id);
    marcadas.value = s;
};
const alternarPagina = () => {
    const s = new Set(marcadas.value);
    const todas = paginaMarcada.value;
    filas.value.forEach((f) => (todas ? s.delete(f.id) : s.add(f.id)));
    marcadas.value = s;
};
const elegirTodosDelFiltro = () => {
    marcadas.value = new Set();
    todosDelFiltro.value = true;
};
function limpiarSeleccion() {
    marcadas.value = new Set();
    todosDelFiltro.value = false;
}

const seleccion = () => (todosDelFiltro.value ? { filtro: filtroServidor() } : { ids: [...marcadas.value] });

const abrir = async (accion) => {
    dialogo.value = accion;
    conteo.value = null;
    confirmacion.value = "";
    errorAccion.value = "";
    try {
        const { data } = await axios.post(`${URL}/conteo`, { accion, ...seleccion() });
        if (dialogo.value === accion) conteo.value = data;
    } catch (e) {
        const errores = e.response?.data?.errors;
        errorAccion.value = errores ? Object.values(errores).flat()[0] : e.response?.data?.message || "No se pudo contar la selección.";
    }
};

const cerrar = () => {
    if (!enviando.value) dialogo.value = null;
};

const aplicar = async () => {
    if (!puedeAplicar.value) return;
    enviando.value = true;
    errorAccion.value = "";
    try {
        const { data } = await axios.post(`${URL}/aplicar`, {
            accion: dialogo.value,
            ...seleccion(),
            esperado: conteo.value.total,
            confirmacion: confirmacion.value.trim().toUpperCase(),
        });
        toastr.success(data.message, "Detalle de la cola");
        dialogo.value = null;
        limpiarSeleccion();
        cargar();
    } catch (e) {
        const errores = e.response?.data?.errors;
        errorAccion.value = errores ? Object.values(errores).flat()[0] : e.response?.data?.message || "No se pudo aplicar.";
        if (e.response?.data?.conteo) conteo.value = e.response.data.conteo;
    } finally {
        enviando.value = false;
    }
};

const numero = (n) => Number(n || 0).toLocaleString("es-MX");
const dinero = (n) => Number(n || 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
const fechaHora = (iso) =>
    new Date(iso).toLocaleString("es-MX", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
</script>

<style scoped>
.ec-wrap { max-width: 1200px; }
.ec-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 12px 0; }
.ec-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.ec-body { padding: 12px 16px; }
.ec-fila { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ec-btn-head { font-weight: 500; }
.ec-filtros { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.ec-campo { display: flex; flex-direction: column; min-width: 140px; margin: 0; }
.ec-campo-ancho { min-width: 220px; flex: 1 1 220px; }
.ec-label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
.ec-limpiar { height: 31px; }
.ec-barra { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 12px 0 6px; }
.ec-barra .btn-link { padding: 0 4px; }
.ec-nota { font-size: 12px; color: #6b7280; margin: 0; }
.ec-tabla { font-size: 13px; }
.ec-tabla td, .ec-tabla th { vertical-align: middle; }
.ec-check { width: 32px; }
.ec-fecha { white-space: nowrap; }
.ec-marcada td { background: #F5F7FF; }
.ec-vacio { text-align: center; color: #6b7280; padding: 18px 0 !important; }
.ec-fuera td { opacity: 0.45; }
.ec-paginas { display: flex; gap: 10px; align-items: center; justify-content: center; margin: 8px 0; }
.ec-chip { font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; white-space: nowrap; background: #F1F5F9; color: #475569; }
.ec-canal-correo { background: #E0E7FF; color: #3730A3; }
.ec-canal-whatsapp { background: #DCFCE7; color: #166534; }
.ec-canal-caja { background: #F1F5F9; color: #475569; }
.ec-canal-ninguno { background: #FEE2E2; color: #991B1B; }
.ec-est-en_espera { background: #FEF3C7; color: #92400E; }
.ec-est-enviado { background: #DCFCE7; color: #166534; }
.ec-est-cancelado { background: #F1F5F9; color: #475569; }
.ec-est-error { background: #FEE2E2; color: #991B1B; }
.ec-aviso { font-size: 12.5px; background: #FFF7ED; color: #9A3412; border-radius: 8px; padding: 6px 10px; margin: 8px 0; }
.ec-error { color: #B91C1C; font-size: 12.5px; padding: 0 16px 8px; }
.ec-velo { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 1050; display: flex;
    align-items: center; justify-content: center; padding: 16px; }
.ec-dialogo { background: #fff; border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow: auto; }
.ec-pie { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f3f5; }
.ec-dark .ec-card, .ec-dark .ec-dialogo { background: #1d1d21; border-color: #2c2c34; color: #e5e7eb; }
.ec-dark .ec-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.ec-dark .ec-nota, .ec-dark .ec-vacio { color: #9ca3af; }
.ec-dark .ec-label { color: #cbd5e1 !important; }
.ec-dark .ec-tabla { color: #e5e7eb; }
.ec-dark .ec-marcada td { background: #26262c; }
.ec-dark .ec-aviso { background: #3b2a1a; color: #fdba74; }
.ec-dark .ec-pie { border-color: #2c2c34; }
/* El tema oscuro global pinta de blanco el texto de los span: los distintivos fijan el suyo. */
.ec-dark .ec-chip { background: #334155; color: #E2E8F0 !important; }
.ec-dark .ec-canal-correo { background: #312E81; color: #E0E7FF !important; }
.ec-dark .ec-canal-whatsapp { background: #14532D; color: #BBF7D0 !important; }
.ec-dark .ec-canal-ninguno { background: #7F1D1D; color: #FECACA !important; }
.ec-dark .ec-est-en_espera { background: #78350F; color: #FDE68A !important; }
.ec-dark .ec-est-enviado { background: #14532D; color: #BBF7D0 !important; }
.ec-dark .ec-est-error { background: #7F1D1D; color: #FECACA !important; }
</style>
