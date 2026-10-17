<template>
    <section v-if="puedeVer && cargado" class="dfr-card" :class="{ 'dfr-dark': darkMode }">
        <div class="dfr-head">
            <span><i class="mdi mdi-file-document-outline"></i> Datos de facturación</span>
            <span class="dfr-head-acciones">
                <span class="dfr-badge" :class="datos.facturacion_fiscal ? 'dfr-badge--on' : 'dfr-badge--off'">
                    {{ datos.facturacion_fiscal ? "Requiere factura fiscal" : "Público en general" }}
                </span>
                <a class="btn btn-outline-primary btn-sm" :href="`/cliente/editar/${clientId}?tab=facturacion`">
                    {{ puedeEditar ? "Editar en Facturación" : "Ver en Facturación" }}
                </a>
            </span>
        </div>
        <div class="dfr-body">
            <p v-if="!hayDatos" class="dfr-vacio">
                Sin datos de facturación capturados: se le factura como público en general.
            </p>
            <dl v-else class="dfr-grid">
                <div v-for="f in filas" :key="f.etiqueta">
                    <dt>{{ f.etiqueta }}</dt>
                    <dd>{{ f.valor || "—" }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>

<script setup>
/**
 * FR-01 (#535): resumen de SOLO LECTURA de los «Datos de facturación» en la pestaña Información.
 * Se capturan únicamente en Facturación › Vista de facturación (ClientDatosFacturacionCard); el
 * enlace abre esa pestaña (?tab=facturacion). Misma API y mismo permiso de lectura (facturacion.ver).
 */
import { computed, reactive, ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    clientId: { type: [String, Number], required: true },
    puedeVer: { type: Boolean, default: false },
    puedeEditar: { type: Boolean, default: false },
});

const datos = reactive({});
const catalogos = reactive({ regimenes_fiscales: {}, usos_cfdi: {} });
const cargado = ref(false);
let token = 0;

const conNombre = (clave, catalogo) => (clave ? `${clave} — ${catalogo[clave] ?? ""}`.replace(/ — $/, "") : "");

const filas = computed(() => [
    { etiqueta: "RFC", valor: datos.rfc },
    { etiqueta: "Nombre o razón social", valor: datos.razon_social },
    { etiqueta: "Régimen fiscal", valor: conNombre(datos.regimen_fiscal, catalogos.regimenes_fiscales) },
    { etiqueta: "C.P. fiscal", valor: datos.codigo_postal_fiscal },
    { etiqueta: "Uso del CFDI", valor: conNombre(datos.uso_cfdi, catalogos.usos_cfdi) },
    { etiqueta: "Correo fiscal", valor: datos.correo_fiscal },
    { etiqueta: "Calle", valor: datos.calle },
    { etiqueta: "Ciudad", valor: datos.ciudad },
]);

const hayDatos = computed(() => filas.value.some((f) => f.valor));

const cargar = async () => {
    const mio = ++token;
    cargado.value = false;
    Object.keys(datos).forEach((k) => delete datos[k]);
    if (!props.puedeVer || !props.clientId) {
        return;
    }
    try {
        const { data } = await axios.get(`/cliente/billing/datos-fiscales/${props.clientId}`);
        if (mio !== token) {
            return; // ya se cambió de cliente
        }
        Object.assign(catalogos, data.catalogos || {});
        Object.assign(datos, data.fiscal_data || {});
        cargado.value = true;
    } catch (e) {
        cargado.value = false; // sin permiso o sin API: no se muestra
    }
};

watch(() => [props.clientId, props.puedeVer], cargar, { immediate: true });
</script>

<style scoped>
.dfr-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 0 14px; }
.dfr-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 10px 16px;
    border-bottom: 1px solid #f1f3f5; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-radius: 12px 12px 0 0; }
.dfr-head-acciones { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.dfr-badge { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 10px; }
.dfr-badge--on { background: #E9F5EF; color: #14724A; }
.dfr-badge--off { background: #F1F5F9; color: #475569; }
.dfr-body { padding: 12px 16px; }
.dfr-vacio { margin: 0; font-size: 13px; color: #475569; }
.dfr-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 18px; margin: 0; }
@media (max-width: 991px) { .dfr-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 575px) { .dfr-grid { grid-template-columns: 1fr; } }
.dfr-grid dt { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; }
.dfr-grid dd { margin: 0; font-size: 13px; overflow-wrap: anywhere; }

.dfr-dark.dfr-card { background: #1d1d21; border-color: #2c2c34; }
.dfr-dark .dfr-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.dfr-dark .dfr-grid dt { color: #9ca3af; }
.dfr-dark .dfr-grid dd, .dfr-dark .dfr-vacio { color: #e5e7eb; }
.dfr-dark .dfr-badge--off { background: #2c2c34; color: #cbd5e1; }
</style>
