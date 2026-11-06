<template>
    <section v-if="puedeVer && cargado" class="fdc-card" :class="{ 'fdc-dark': darkMode }">
        <div class="fdc-head">
            <span><i class="mdi mdi-file-document-outline"></i> Datos de facturación</span>
            <!-- Guardar va arriba a la derecha: abajo lo tapaban los botones flotantes (item #497). -->
            <span class="fdc-head-acciones">
                <span class="fdc-badge" :class="form.facturacion_fiscal ? 'fdc-badge--on' : 'fdc-badge--off'">
                    {{ form.facturacion_fiscal ? "Requiere factura fiscal" : "Público en general" }}
                </span>
                <button v-if="puedeEditar" type="button" class="btn btn-primary btn-sm" :disabled="guardando" @click="guardar">
                    {{ guardando ? "Guardando…" : "Guardar datos de facturación" }}
                </button>
            </span>
        </div>

        <div class="fdc-body">
            <div class="form-check form-switch mb-3">
                <input
                    :id="`fdc-switch-${clientId}`"
                    v-model="form.facturacion_fiscal"
                    class="form-check-input"
                    type="checkbox"
                    :disabled="!puedeEditar"
                />
                <label class="form-check-label" :for="`fdc-switch-${clientId}`">
                    El cliente requiere factura fiscal
                </label>
            </div>
            <p v-if="!form.facturacion_fiscal" class="fdc-publico">
                Sin factura fiscal se le factura como <b>público en general</b> (factura de mostrador).
            </p>

            <div class="fdc-grid">
                <div>
                    <label class="fdc-label">RFC</label>
                    <input v-model.trim="form.rfc" class="form-control form-control-sm" :class="{ 'is-invalid': errores.rfc }"
                           maxlength="13" placeholder="12 o 13 caracteres" :disabled="!puedeEditar"
                           @input="form.rfc = (form.rfc || '').toUpperCase(); limpiar('rfc')" />
                    <div v-if="errores.rfc" class="invalid-feedback d-block">{{ errores.rfc }}</div>
                </div>
                <div>
                    <label class="fdc-label">Nombre o razón social</label>
                    <input v-model.trim="form.razon_social" class="form-control form-control-sm" :class="{ 'is-invalid': errores.razon_social }"
                           maxlength="250" :disabled="!puedeEditar" @input="limpiar('razon_social')" />
                    <div v-if="errores.razon_social" class="invalid-feedback d-block">{{ errores.razon_social }}</div>
                </div>
                <div>
                    <label class="fdc-label">Régimen fiscal</label>
                    <select v-model="form.regimen_fiscal" class="form-select form-select-sm" :class="{ 'is-invalid': errores.regimen_fiscal }"
                            :disabled="!puedeEditar" @change="limpiar('regimen_fiscal')">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="(nombre, clave) in catalogos.regimenes_fiscales" :key="clave" :value="clave">
                            {{ clave }} — {{ nombre }}
                        </option>
                    </select>
                    <div v-if="errores.regimen_fiscal" class="invalid-feedback d-block">{{ errores.regimen_fiscal }}</div>
                </div>
                <div>
                    <label class="fdc-label">C.P. del domicilio fiscal</label>
                    <input v-model.trim="form.codigo_postal_fiscal" class="form-control form-control-sm" :class="{ 'is-invalid': errores.codigo_postal_fiscal }"
                           maxlength="5" inputmode="numeric" placeholder="5 dígitos" :disabled="!puedeEditar"
                           @input="limpiar('codigo_postal_fiscal')" />
                    <div v-if="errores.codigo_postal_fiscal" class="invalid-feedback d-block">{{ errores.codigo_postal_fiscal }}</div>
                </div>
                <div>
                    <label class="fdc-label">Uso del CFDI</label>
                    <select v-model="form.uso_cfdi" class="form-select form-select-sm" :class="{ 'is-invalid': errores.uso_cfdi }"
                            :disabled="!puedeEditar" @change="limpiar('uso_cfdi')">
                        <option :value="null">— Selecciona —</option>
                        <option v-for="(nombre, clave) in catalogos.usos_cfdi" :key="clave" :value="clave">
                            {{ clave }} — {{ nombre }}
                        </option>
                    </select>
                    <div v-if="errores.uso_cfdi" class="invalid-feedback d-block">{{ errores.uso_cfdi }}</div>
                </div>
                <div>
                    <label class="fdc-label">Correo fiscal</label>
                    <input v-model.trim="form.correo_fiscal" type="email" class="form-control form-control-sm" :class="{ 'is-invalid': errores.correo_fiscal }"
                           maxlength="200" :disabled="!puedeEditar" @input="limpiar('correo_fiscal')" />
                    <div v-if="errores.correo_fiscal" class="invalid-feedback d-block">{{ errores.correo_fiscal }}</div>
                </div>
                <div>
                    <label class="fdc-label">Calle <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.calle" class="form-control form-control-sm" :class="{ 'is-invalid': errores.calle }"
                           maxlength="255" :disabled="!puedeEditar" @input="limpiar('calle')" />
                    <div v-if="errores.calle" class="invalid-feedback d-block">{{ errores.calle }}</div>
                </div>
                <div>
                    <label class="fdc-label">Ciudad <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.ciudad" class="form-control form-control-sm" :class="{ 'is-invalid': errores.ciudad }"
                           maxlength="150" :disabled="!puedeEditar" @input="limpiar('ciudad')" />
                    <div v-if="errores.ciudad" class="invalid-feedback d-block">{{ errores.ciudad }}</div>
                </div>
                <div>
                    <label class="fdc-label">Núm. exterior <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.num_exterior" class="form-control form-control-sm" :class="{ 'is-invalid': errores.num_exterior }"
                           maxlength="30" :disabled="!puedeEditar" @input="limpiar('num_exterior')" />
                    <div v-if="errores.num_exterior" class="invalid-feedback d-block">{{ errores.num_exterior }}</div>
                </div>
                <div>
                    <label class="fdc-label">Núm. interior <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.num_interior" class="form-control form-control-sm" :class="{ 'is-invalid': errores.num_interior }"
                           maxlength="30" :disabled="!puedeEditar" @input="limpiar('num_interior')" />
                    <div v-if="errores.num_interior" class="invalid-feedback d-block">{{ errores.num_interior }}</div>
                </div>
                <div>
                    <label class="fdc-label">Colonia <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.colonia" class="form-control form-control-sm" :class="{ 'is-invalid': errores.colonia }"
                           maxlength="150" :disabled="!puedeEditar" @input="limpiar('colonia')" />
                    <div v-if="errores.colonia" class="invalid-feedback d-block">{{ errores.colonia }}</div>
                </div>
                <div>
                    <label class="fdc-label">Municipio <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.municipio" class="form-control form-control-sm" :class="{ 'is-invalid': errores.municipio }"
                           maxlength="150" :disabled="!puedeEditar" @input="limpiar('municipio')" />
                    <div v-if="errores.municipio" class="invalid-feedback d-block">{{ errores.municipio }}</div>
                </div>
                <div>
                    <label class="fdc-label">Estado <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.estado" class="form-control form-control-sm" :class="{ 'is-invalid': errores.estado }"
                           maxlength="100" :disabled="!puedeEditar" @input="limpiar('estado')" />
                    <div v-if="errores.estado" class="invalid-feedback d-block">{{ errores.estado }}</div>
                </div>
                <div>
                    <label class="fdc-label">País <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.pais" class="form-control form-control-sm" :class="{ 'is-invalid': errores.pais }"
                           maxlength="100" :disabled="!puedeEditar" @input="limpiar('pais')" />
                    <div v-if="errores.pais" class="invalid-feedback d-block">{{ errores.pais }}</div>
                </div>
                <div>
                    <label class="fdc-label">Forma de pago <span class="fdc-opc">(opcional)</span></label>
                    <select v-model="form.forma_pago_cfdi" class="form-select form-select-sm" :class="{ 'is-invalid': errores.forma_pago_cfdi }"
                            :disabled="!puedeEditar" @change="limpiar('forma_pago_cfdi')">
                        <option :value="null">— Sin especificar —</option>
                        <option v-for="(nombre, clave) in catalogos.formas_pago" :key="clave" :value="clave">{{ clave }} — {{ nombre }}</option>
                    </select>
                    <div v-if="errores.forma_pago_cfdi" class="invalid-feedback d-block">{{ errores.forma_pago_cfdi }}</div>
                </div>
                <div>
                    <label class="fdc-label">Método de pago <span class="fdc-opc">(opcional)</span></label>
                    <select v-model="form.metodo_pago_cfdi" class="form-select form-select-sm" :class="{ 'is-invalid': errores.metodo_pago_cfdi }"
                            :disabled="!puedeEditar" @change="limpiar('metodo_pago_cfdi')">
                        <option :value="null">— Sin especificar —</option>
                        <option v-for="(nombre, clave) in catalogos.metodos_pago" :key="clave" :value="clave">{{ clave }} — {{ nombre }}</option>
                    </select>
                    <div v-if="errores.metodo_pago_cfdi" class="invalid-feedback d-block">{{ errores.metodo_pago_cfdi }}</div>
                </div>
                <div>
                    <label class="fdc-label">Día de recepción de facturas <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.dia_recepcion_facturas" class="form-control form-control-sm" :class="{ 'is-invalid': errores.dia_recepcion_facturas }"
                           maxlength="100" :disabled="!puedeEditar" @input="limpiar('dia_recepcion_facturas')" />
                    <div v-if="errores.dia_recepcion_facturas" class="invalid-feedback d-block">{{ errores.dia_recepcion_facturas }}</div>
                </div>
                <div>
                    <label class="fdc-label">Días de crédito <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.dias_credito" class="form-control form-control-sm" :class="{ 'is-invalid': errores.dias_credito }"
                           maxlength="3" inputmode="numeric" :disabled="!puedeEditar" @input="limpiar('dias_credito')" />
                    <div v-if="errores.dias_credito" class="invalid-feedback d-block">{{ errores.dias_credito }}</div>
                </div>
                <div>
                    <label class="fdc-label">Condiciones de crédito <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.condiciones_credito" class="form-control form-control-sm" :class="{ 'is-invalid': errores.condiciones_credito }"
                           maxlength="500" :disabled="!puedeEditar" @input="limpiar('condiciones_credito')" />
                    <div v-if="errores.condiciones_credito" class="invalid-feedback d-block">{{ errores.condiciones_credito }}</div>
                </div>
                <div>
                    <label class="fdc-label">Contacto de facturación <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.contacto_facturacion_nombre" class="form-control form-control-sm" :class="{ 'is-invalid': errores.contacto_facturacion_nombre }"
                           maxlength="200" :disabled="!puedeEditar" @input="limpiar('contacto_facturacion_nombre')" />
                    <div v-if="errores.contacto_facturacion_nombre" class="invalid-feedback d-block">{{ errores.contacto_facturacion_nombre }}</div>
                </div>
                <div>
                    <label class="fdc-label">Cargo del contacto <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.contacto_facturacion_cargo" class="form-control form-control-sm" :class="{ 'is-invalid': errores.contacto_facturacion_cargo }"
                           maxlength="150" :disabled="!puedeEditar" @input="limpiar('contacto_facturacion_cargo')" />
                    <div v-if="errores.contacto_facturacion_cargo" class="invalid-feedback d-block">{{ errores.contacto_facturacion_cargo }}</div>
                </div>
                <div>
                    <label class="fdc-label">Teléfono del contacto <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.contacto_facturacion_telefono" class="form-control form-control-sm" :class="{ 'is-invalid': errores.contacto_facturacion_telefono }"
                           maxlength="40" :disabled="!puedeEditar" @input="limpiar('contacto_facturacion_telefono')" />
                    <div v-if="errores.contacto_facturacion_telefono" class="invalid-feedback d-block">{{ errores.contacto_facturacion_telefono }}</div>
                </div>
                <div>
                    <label class="fdc-label">Extensión <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.contacto_facturacion_extension" class="form-control form-control-sm" :class="{ 'is-invalid': errores.contacto_facturacion_extension }"
                           maxlength="20" :disabled="!puedeEditar" @input="limpiar('contacto_facturacion_extension')" />
                    <div v-if="errores.contacto_facturacion_extension" class="invalid-feedback d-block">{{ errores.contacto_facturacion_extension }}</div>
                </div>
                <div>
                    <label class="fdc-label">Celular del contacto <span class="fdc-opc">(opcional)</span></label>
                    <input v-model.trim="form.contacto_facturacion_celular" class="form-control form-control-sm" :class="{ 'is-invalid': errores.contacto_facturacion_celular }"
                           maxlength="40" :disabled="!puedeEditar" @input="limpiar('contacto_facturacion_celular')" />
                    <div v-if="errores.contacto_facturacion_celular" class="invalid-feedback d-block">{{ errores.contacto_facturacion_celular }}</div>
                </div>
                <div class="form-check form-switch align-self-end">
                    <input :id="`fdc-oc-${clientId}`" v-model="form.requiere_orden_compra" class="form-check-input" type="checkbox" :disabled="!puedeEditar" />
                    <label class="form-check-label" :for="`fdc-oc-${clientId}`">Requiere orden de compra <span class="fdc-opc">(opcional)</span></label>
                </div>
            </div>

            <div class="fdc-historial mt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleHistorial">
                    {{ verHistorial ? "Ocultar historial de cambios" : "Historial de cambios" }}
                </button>
                <div v-if="verHistorial" class="mt-2">
                    <div v-if="historialMsg" class="fdc-nota">{{ historialMsg }}</div>
                    <div v-else class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Fecha</th><th>Usuario</th><th>Campo</th><th>Anterior</th><th>Nuevo</th></tr></thead>
                            <tbody>
                                <tr v-for="h in historial" :key="h.id">
                                    <td>{{ h.fecha }}</td><td>{{ h.usuario }}</td><td>{{ h.campo }}</td>
                                    <td>{{ h.valor_anterior ?? "—" }}</td><td>{{ h.valor_nuevo ?? "—" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="fdc-foot">
                <span class="fdc-nota">
                    La prefactura, la factura y el portal toman estos datos. Guardarlos no emite ninguna factura fiscal.
                </span>
            </div>
        </div>
    </section>
</template>

<script setup>
/**
 * «Datos de facturación» del cliente (FR-01 #535; antes «Datos fiscales», item #480): la ÚNICA
 * tarjeta para facturar, en Facturación › Vista de facturación. Reemplaza a «Dirección de
 * facturación». API: ClientFiscalDataController (GET/POST /cliente/billing/datos-fiscales/{id}),
 * tabla client_fiscal_data. Solo captura: no timbra nada. Permisos: facturacion.datos.ver para verla y
 * facturacion.datos.editar para guardar (el backend los exige igual). El RFC/CURP de Datos
 * personales es otro dato: aquí no se lee ni se precarga.
 */
import { reactive, ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    clientId: { type: [String, Number], required: true },
    puedeVer: { type: Boolean, default: false },
    puedeEditar: { type: Boolean, default: false },
});

const RFC = /^([A-ZÑ&]{3,4})(\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01]))([A-Z\d]{2}[A\d])$/;
const CAMPOS = ["rfc", "razon_social", "regimen_fiscal", "codigo_postal_fiscal", "uso_cfdi", "correo_fiscal", "calle", "ciudad", "num_exterior", "num_interior", "colonia", "municipio", "estado", "pais", "forma_pago_cfdi", "metodo_pago_cfdi", "dia_recepcion_facturas", "dias_credito", "condiciones_credito", "contacto_facturacion_nombre", "contacto_facturacion_cargo", "contacto_facturacion_telefono", "contacto_facturacion_extension", "contacto_facturacion_celular"];

const vacio = () => ({
    facturacion_fiscal: false,
    rfc: "",
    razon_social: "",
    regimen_fiscal: null,
    codigo_postal_fiscal: "",
    uso_cfdi: null,
    correo_fiscal: "",
    calle: "",
    ciudad: "",
    num_exterior: "",
    num_interior: "",
    colonia: "",
    municipio: "",
    estado: "",
    pais: "",
    forma_pago_cfdi: null,
    metodo_pago_cfdi: null,
    dia_recepcion_facturas: "",
    dias_credito: "",
    condiciones_credito: "",
    contacto_facturacion_nombre: "",
    contacto_facturacion_cargo: "",
    contacto_facturacion_telefono: "",
    contacto_facturacion_extension: "",
    contacto_facturacion_celular: "",
    requiere_orden_compra: false,
});

const form = reactive(vacio());
const errores = reactive({});
const catalogos = reactive({ regimenes_fiscales: {}, usos_cfdi: {}, formas_pago: {}, metodos_pago: {} });
const cargado = ref(false);
const guardando = ref(false);
let token = 0;

const limpiar = (campo) => {
    delete errores[campo];
};

const verHistorial = ref(false);
const historial = ref([]);
const historialMsg = ref("");
const toggleHistorial = async () => {
    verHistorial.value = !verHistorial.value;
    if (!verHistorial.value) {
        return;
    }
    historialMsg.value = "";
    try {
        const { data } = await axios.get(`/cliente/billing/datos-fiscales/${props.clientId}/historial`);
        historial.value = data.data || [];
        if (!historial.value.length) {
            historialMsg.value = "Aún no hay cambios registrados.";
        }
    } catch (e) {
        historialMsg.value = e?.response?.status === 403 ? "No tienes permiso para ver el historial." : "No se pudo cargar el historial.";
    }
};

const cargar = async () => {
    const mio = ++token;
    cargado.value = false;
    Object.assign(form, vacio());
    CAMPOS.forEach(limpiar);
    if (!props.puedeVer || !props.clientId) {
        return;
    }
    try {
        const { data } = await axios.get(`/cliente/billing/datos-fiscales/${props.clientId}`);
        if (mio !== token) {
            return; // ya se cambió de cliente: no pisar el actual
        }
        Object.assign(catalogos, data.catalogos || {});
        const f = data.fiscal_data || {};
        Object.assign(form, {
            facturacion_fiscal: !!f.facturacion_fiscal,
            rfc: f.rfc || "",
            razon_social: f.razon_social || "",
            regimen_fiscal: f.regimen_fiscal || null,
            codigo_postal_fiscal: f.codigo_postal_fiscal || "",
            uso_cfdi: f.uso_cfdi || null,
            correo_fiscal: f.correo_fiscal || "",
            calle: f.calle || "",
            ciudad: f.ciudad || "",
            num_exterior: f.num_exterior ?? "",
            num_interior: f.num_interior ?? "",
            colonia: f.colonia ?? "",
            municipio: f.municipio ?? "",
            estado: f.estado ?? "",
            pais: f.pais ?? "",
            forma_pago_cfdi: f.forma_pago_cfdi || null,
            metodo_pago_cfdi: f.metodo_pago_cfdi || null,
            dia_recepcion_facturas: f.dia_recepcion_facturas ?? "",
            dias_credito: f.dias_credito ?? "",
            condiciones_credito: f.condiciones_credito ?? "",
            contacto_facturacion_nombre: f.contacto_facturacion_nombre ?? "",
            contacto_facturacion_cargo: f.contacto_facturacion_cargo ?? "",
            contacto_facturacion_telefono: f.contacto_facturacion_telefono ?? "",
            contacto_facturacion_extension: f.contacto_facturacion_extension ?? "",
            contacto_facturacion_celular: f.contacto_facturacion_celular ?? "",
            requiere_orden_compra: !!f.requiere_orden_compra,
        });
        cargado.value = true;
    } catch (e) {
        cargado.value = false; // sin permiso o sin API: la tarjeta no se muestra
    }
};

const validar = () => {
    CAMPOS.forEach(limpiar);
    const requerido = form.facturacion_fiscal;
    if (requerido && !form.rfc) errores.rfc = "El RFC es obligatorio si requiere factura fiscal.";
    if (form.rfc && !RFC.test(form.rfc)) {
        errores.rfc = "RFC inválido: 12 caracteres (persona moral) o 13 (persona física), con fecha válida.";
    }
    if (requerido && !form.razon_social) errores.razon_social = "La razón social es obligatoria.";
    if (requerido && !form.regimen_fiscal) errores.regimen_fiscal = "Selecciona el régimen fiscal.";
    if (requerido && !form.codigo_postal_fiscal) errores.codigo_postal_fiscal = "El C.P. es obligatorio.";
    if (form.codigo_postal_fiscal && !/^\d{5}$/.test(form.codigo_postal_fiscal)) {
        errores.codigo_postal_fiscal = "El código postal debe ser de 5 dígitos.";
    }
    if (requerido && !form.uso_cfdi) errores.uso_cfdi = "Selecciona el uso del CFDI.";
    if (form.correo_fiscal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo_fiscal)) {
        errores.correo_fiscal = "El correo no es válido.";
    }
    return Object.keys(errores).length === 0;
};

const guardar = async () => {
    if (!validar()) {
        toastr.error("Revisa los campos marcados.", "Datos de facturación");
        return;
    }
    guardando.value = true;
    try {
        const payload = { ...form };
        CAMPOS.forEach((c) => {
            if (payload[c] === "") payload[c] = null;
        });
        await axios.post(`/cliente/billing/datos-fiscales/${props.clientId}`, payload);
        toastr.success("Datos de facturación guardados.", "Datos de facturación");
    } catch (error) {
        const errs = error.response?.data?.errors;
        if (error.response?.status === 422 && errs) {
            Object.entries(errs).forEach(([campo, msgs]) => {
                errores[campo] = Array.isArray(msgs) ? msgs[0] : String(msgs);
            });
            toastr.error("Revisa los campos marcados.", "Datos de facturación");
        } else if (error.response?.status === 403) {
            toastr.error("No tienes permiso para editar los datos de facturación.", "Datos de facturación");
        } else {
            toastr.error("No se pudieron guardar los datos de facturación.", "Datos de facturación");
        }
    } finally {
        guardando.value = false;
    }
};

watch(() => [props.clientId, props.puedeVer], cargar, { immediate: true });
</script>

<style scoped>
.fdc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 0 14px; }
.fdc-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 10px 16px;
    border-bottom: 1px solid #f1f3f5; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-radius: 12px 12px 0 0; }
.fdc-head-acciones { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fdc-badge { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 10px; }
.fdc-badge--on { background: #E9F5EF; color: #14724A; }
.fdc-badge--off { background: #F1F5F9; color: #64748B; }
.fdc-body { padding: 14px 16px; }
.fdc-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px 18px; }
@media (max-width: 991px) { .fdc-grid { grid-template-columns: 1fr; } }
.fdc-label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;
    letter-spacing: 0.3px; margin-bottom: 3px; }
.fdc-foot { margin-top: 12px; }
.fdc-nota { font-size: 12px; color: #6b7280; }
.fdc-opc { text-transform: none; font-weight: 400; letter-spacing: 0; }
.fdc-publico { font-size: 12.5px; color: #475569; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;
    padding: 6px 10px; margin: -4px 0 12px; }

.fdc-dark.fdc-card { background: #1d1d21; border-color: #2c2c34; }
.fdc-dark .fdc-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.fdc-dark .fdc-label, .fdc-dark .fdc-nota { color: #9ca3af; }
.fdc-dark .fdc-badge--off { background: #2c2c34; color: #9ca3af; }
.fdc-dark .fdc-publico { background: #26262c; border-color: #3a3a44; color: #cbd5e1; }
</style>
