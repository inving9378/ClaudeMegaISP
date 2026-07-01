<template>
    <div class="cic-wrap" :class="{ 'cic-dark': darkMode }">
        <form
            method="POST"
            @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)"
        >
            <!-- ── HEADER MODERNO ─────────────────────────────────────────── -->
            <div class="card cic-header-card">
                <div class="cic-header">
                    <div class="cic-avatar">{{ initials }}</div>
                    <div class="cic-header-main">
                        <h5 class="cic-title">
                            {{ headerName || "Cliente" }}
                            <span class="text-muted fw-normal">· #{{ id }}</span>
                            <span class="badge cic-estado ms-1" v-if="dataForm.data.estado" :class="clientStatusMeta(dataForm.data.estado).bsClass">{{ dataForm.data.estado }}</span>
                        </h5>
                        <div class="cic-subline">
                            <span v-if="planLabel"><i class="mdi mdi-package-variant me-1"></i>Plan {{ planLabel }}</span>
                            <span v-if="colonyLabel || municipalityName"><i class="mdi mdi-map-marker-outline me-1"></i>{{ [colonyLabel, municipalityName].filter(Boolean).join(", ") }}</span>
                            <span v-if="sellerLabel"><i class="mdi mdi-account-tie me-1"></i>Vendedor {{ sellerLabel }}</span>
                            <span v-if="dischargeDateFmt"><i class="mdi mdi-calendar me-1"></i>Alta {{ dischargeDateFmt }}</span>
                        </div>
                    </div>
                    <div class="cic-header-actions">
                        <div class="cic-balance" v-if="balance !== null">
                            <span class="cic-balance-label">Saldo</span>
                            <span class="cic-balance-value">$ {{ balance }}</span>
                        </div>
                        <div v-if="clientMainInformationId" class="dropdown d-inline-block me-2">
                            <button type="button" class="btn btn-outline-secondary waves-effect waves-light" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="ms-1">Tareas</span>
                                <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button type="button" class="btn btn-outline-info waves-effect waves-light dropdown-item" data-bs-target="#crudTask" data-bs-toggle="modal">
                                    <span>Agregar Tarea</span>
                                </button>
                            </div>
                        </div>
                        <div class="dropdown d-inline-block me-2">
                            <button type="button" class="btn btn-outline-secondary waves-effect waves-light" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="ms-1">Soporte</span>
                                <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                                <span class="badge bg-danger rounded-pill ms-1" v-text="clientTickets.open"></span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" :href="`/tickets/crear/${id}`">Crear Ticket</a>
                                <a class="dropdown-item" :href="`/tickets/abiertos/${id}`">Abiertos
                                    <span class="badge bg-danger rounded-pill ms-1" v-text="clientTickets.open"></span>
                                </a>
                                <a class="dropdown-item" :href="`/tickets/cerrados/${id}`">Cerrados
                                    <span class="badge bg-success rounded-pill ms-1" v-text="clientTickets.closed"></span>
                                </a>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" :disabled="dataForm.data.errors.any()">
                            {{ submitButtonAction }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- ── COLUMNA IZQUIERDA: secciones de campos ─────────────── -->
                <div class="col-xl-7">
                    <!-- Datos personales -->
                    <section class="cic-card cic-card--personal">
                        <div class="cic-card-head"><i class="mdi mdi-account-outline"></i> Datos personales</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.datos_personales)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>

                    <!-- Dirección -->
                    <section class="cic-card cic-card--direccion">
                        <div class="cic-card-head"><i class="mdi mdi-map-marker-outline"></i> Dirección</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.direccion)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>

                    <!-- Contrato y facturación -->
                    <section class="cic-card cic-card--contrato">
                        <div class="cic-card-head"><i class="mdi mdi-file-document-outline"></i> Contrato y facturación</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.contrato)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>

                    <!-- Encuesta de instalación -->
                    <section class="cic-card cic-card--encuesta">
                        <div class="cic-card-head"><i class="mdi mdi-clipboard-check-outline"></i> Encuesta de instalación</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.encuesta)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>

                    <!-- Otros campos (anti-pérdida: cualquier campo del backend no asignado) -->
                    <section class="cic-card cic-card--otros" v-if="otrosFields().length">
                        <div class="cic-card-head"><i class="mdi mdi-dots-horizontal"></i> Otros campos</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in otrosFields()"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>
                </div>

                <!-- ── COLUMNA DERECHA: Ubicación (mapa Google + stats) ───── -->
                <div class="col-xl-5">
                    <section class="cic-card cic-card--ubicacion">
                        <div class="cic-card-head cic-map-head">
                            <span><i class="mdi mdi-map"></i> Ubicación</span>
                            <span class="badge" :class="geodataFixed ? 'bg-success' : 'bg-secondary'">
                                {{ geodataFixed ? "Fijada" : "Sin ubicación" }}
                            </span>
                        </div>
                        <div class="cic-map-box">
                            <ClientMapInline :lat="mapLat" :lng="mapLng" />
                            <div class="cic-map-edit mt-2">
                            <InputModalWithGoogleMapForm
                                :property="{
                                    field: 'geodata',
                                    label: '',
                                    class_col: 'full',
                                    class_field: 'form-control',
                                    class_label: 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                                }"
                                :key="dataForm.data.geodata"
                                :modelValue="dataForm.data.geodata"
                                :errors="dataForm.data.errors"
                                @update-field="updateThisField"
                            />
                            </div>
                        </div>
                        <div class="cic-stats">
                            <div class="cic-stat">
                                <div class="cic-stat-label">Zona / Municipio</div>
                                <div class="cic-stat-value">{{ municipalityName || "—" }}</div>
                            </div>
                            <div class="cic-stat">
                                <div class="cic-stat-label">Distancia a oficina</div>
                                <div class="cic-stat-value">{{ distanceToOffice }}</div>
                            </div>
                        </div>
                    </section>

                    <!-- Servicio y conexión -->
                    <section class="cic-card cic-card--servicio">
                        <div class="cic-card-head"><i class="mdi mdi-router-wireless"></i> Servicio y conexión</div>
                        <div v-if="dataForm.data.box_nomenclator_old" class="cic-banner-old">
                            <span class="text-muted">Nomenclatura antigua:</span>
                            <strong class="ms-2">{{ dataForm.data.box_nomenclator_old }}</strong>
                        </div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.servicio)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                        <!-- Widget GPON ONT (preservado) -->
                        <div v-if="hasField('gpon_ont')" class="cic-widget-row">
                            <label for="gpon_ont" class="cic-wlabel">GPON ONT</label>
                            <div class="d-flex gap-2 align-items-center flex-grow-1">
                                <input type="text" id="gpon_ont" name="gpon_ont" class="form-control" disabled v-model="dataForm.data['gpon_ont']" />
                                <q-btn label="Mirar / Fijar" :loading="loadingOnu" no-caps unelevated padding="7px" style="background: #e9e9ef" @click="onFixedShow" />
                            </div>
                        </div>
                        <!-- Widget potencia dbm + estado ONU (preservado) -->
                        <div v-if="hasField('power_dbm')" class="cic-widget-row">
                            <label for="power_dbm" class="cic-wlabel">Potencia en dbm</label>
                            <div class="d-flex gap-2 align-items-center flex-grow-1">
                                <input type="text" id="power_dbm" name="power_dbm" class="form-control" placeholder="Potencia técnico" v-model="dataForm.data['power_dbm']" />
                                <input type="text" id="olt_power_dbm" name="olt_power_dbm" class="form-control" placeholder="Potencia OLT" readonly :value="dataForm.data['olt_power_dbm'] ?? 'Desconocida'" />
                                <q-icon :name="getOnuStatusIcon(dataForm.data['status_smart'])" :color="getOnuStatusClass(dataForm.data['status_smart'])" size="sm">
                                    <q-tooltip>{{ dataForm.data['status_smart'] ?? "Desconocido" }}</q-tooltip>
                                </q-icon>
                            </div>
                        </div>
                    </section>

                    <!-- Credenciales -->
                    <section class="cic-card cic-card--credenciales">
                        <div class="cic-card-head"><i class="mdi mdi-key-outline"></i> Credenciales</div>
                        <div class="cic-grid">
                            <ComponentFormDefault
                                v-for="val in sectionFields(SECTIONS.credenciales)"
                                :key="val.field" :id="id" :json="val"
                                :errors="dataForm.data.errors"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField" @clear-error="clearError"
                            />
                        </div>
                    </section>
                </div>
            </div>

            <div class="form-group text-center mt-2 mb-3">
                <button class="btn btn-primary" type="submit" :disabled="dataForm.data.errors.any()">
                    {{ submitButtonAction }}
                </button>
            </div>
        </form>

        <!-- Bitácora -->
        <div class="mt-2">
            <ClientRecentActivity :id="id" @show-information="showInformation"></ClientRecentActivity>
        </div>
    </div>

    <!-- Modal: detalle de actividad (diff) — preservado -->
    <div class="modal fade modal-center modal-activity" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Informacion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-xl">
                    <div class="col-lg-12 row mt-3">
                        <div class="col-lg-12">
                            <span><b>Fecha De Creación: </b>{{ textInformation.created_at }}</span>
                        </div>
                        <div class="col-lg-12 mt-2">
                            <span><b>Usuario: </b>{{ textInformation.user }}</span>
                        </div>
                        <div class="col-lg-12 mt-2 mb-2">
                            <span><b>Descripción: </b>{{ textInformation.text }}</span>
                        </div>
                        <div class="col-lg-6">
                            <span><h3>Atributos Antiguos</h3></span>
                            <div v-for="attribute in oldAttributes" :key="Object.keys(attribute)[0]">
                                <p v-for="(value, key) in attribute" :key="key" :class="{ 'highlight-difference': hasDifference(key, value) }">
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <span><h3>Atributos Nuevos</h3></span>
                            <div v-for="attribute in newAttributes" :key="Object.keys(attribute)[0]">
                                <p v-for="(value, key) in attribute" :key="key" :class="{ 'highlight-difference': hasDifference(key, value) }">
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="module_id" :value="id" />

    <div class="modal fade" id="crudTask" data-backdrop="static" data-keyboard="false" v-if="clientMainInformationId">
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Tarea</h6>
                </div>
                <TaskClients action="/scheduling/task/add" :customerId="clientMainInformationId"></TaskClients>
            </div>
        </div>
    </div>

    <panel-onu
        :onu="currentOnu"
        :show="showPanelOnu"
        :from-form="true"
        :from-client="true"
        :has-permission="hasPermission"
        @hide="showPanelOnu = false"
        @removed="onRemoveOnu"
        @update="onUpdateCurrentOnu"
    />

    <dialog-unconfigured-onus
        :show="showDialogUnconfigured"
        :client="getClient()"
        :has-permission="hasPermission"
        @created="onCreatedOnu"
        @hide="showDialogUnconfigured = false"
    />
</template>

<script setup>
import { watch, onMounted, ref, reactive, onUnmounted, computed } from "vue";
import {
    fieldsJson,
    dataForm,
    clearError,
    getfieldsEditedWithMultipleModel,
} from "../../../hook/crudHook";
import { getClientTickets, getClientWithBalance } from "./helpers/helper";
import { darkMode } from "../../../hook/appConfig";
import ClientMapInline from "./ClientMapInline.vue";
import ComponentFormDefault from "../../ComponentFormDefault";
import ViewTopNameWithBalance from "./ViewTopNameWithBalance";
import ClientInfoAccountBalance from "./info/ClientInfoAccountBalance";

import DialogUnconfiguredOnus from "../olts/components/dialogs/DialogUnconfiguredOnus.vue";
import PanelOnu from "../olts/components/onus/PanelOnu.vue";

import InputModalWithGoogleMapForm from "../../../shared/InputModalWithGoogleMapForm";
import { changeBalance, clientMainInformationId, clientLoadToken } from "./info/comun_variable";
import TaskClients from "./helpers/TaskClients.vue";
import CrmRecentActivity from "../crm/components/CrmRecentActivity.vue";
import ClientRecentActivity from "./components/ClientRecentActivity.vue";
import Swal from "sweetalert2";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";
import { clientStatusMeta } from "../../../helpers/clientStatus";
import { getOnuByClient, getSignal } from "../olts/helper/request";
import { formatDate } from "@fullcalendar/core/index.js";
import { message } from "../../../helpers/toastMsg";
import { useOlts } from "../../../composables/useOlts";

defineOptions({
    name: "InformationClientCrud",
});

const { getOnuStatusClass, getOnuStatusIcon } = useOlts();

const props = defineProps({
    action: String,
    id: {
        type: String,
        default: null,
    },
    officeLat: { type: String, default: null },
    officeLng: { type: String, default: null },
});

const emits = defineEmits(["getTypeOfBilling"]);

// ── Sub-fase rediseño (PASO C): agrupación de campos por sección ───────────────
// Orden aprobado por Irving. Los campos especiales (mapa + widgets gpon/power +
// banner nomenclatura antigua) se renderizan a mano y se EXCLUYEN de los loops.
const SECTIONS = {
    datos_personales: ["name", "father_last_name", "mother_last_name", "email", "phone", "phone2", "phone3", "nif_pasaport", "social_id"],
    direccion: ["street", "external_number", "internal_number", "colony_id", "zip", "municipality_id", "state_id", "location_id", "address", "geo_data"],
    servicio: ["estado", "connection_type", "ift", "category", "is_dedicated", "modem_sn", "vendor", "box_nomenclator", "last_time_online", "reinstatement"],
    credenciales: ["user", "password", "original_password", "password_wifi", "user_film", "password_film"],
    contrato: ["type_of_billing_id", "duration_contract_id", "activation_date", "discharge_date", "activation_cost", "is_payment_activation_cost", "distribute_commission", "partner_id", "seller_id", "medium_id"],
    encuesta: ["installation_on_time", "technician_attencion", "amount_technician_and_why", "doubt_signed_contract", "comment"],
};
// Campos renderizados a mano (mapa/widgets/banner) → no van en los loops de sección.
const SPECIAL_WIDGET_FIELDS = ["geodata", "gpon_ont", "power_dbm", "olt_power_dbm", "status_smart", "box_nomenclator_old"];

// Devuelve los objetos de config (de cualquiera de los 2 grupos) en el orden pedido.
function sectionFields(names) {
    const m = fieldsJson.value?.["client_main_information"] ?? {};
    const a = fieldsJson.value?.["client_additional_information"] ?? {};
    return (names || []).map((n) => m[n] ?? a[n]).filter(Boolean);
}

function hasField(name) {
    const m = fieldsJson.value?.["client_main_information"] ?? {};
    const a = fieldsJson.value?.["client_additional_information"] ?? {};
    return !!(m[name] || a[name]);
}

// Anti-pérdida: cualquier campo del backend no asignado a una sección ni especial.
function otrosFields() {
    const assigned = new Set([...Object.values(SECTIONS).flat(), ...SPECIAL_WIDGET_FIELDS]);
    const out = [];
    for (const grpKey of ["client_main_information", "client_additional_information"]) {
        const grp = fieldsJson.value?.[grpKey] ?? {};
        for (const k of Object.keys(grp)) {
            if (!assigned.has(k)) out.push(grp[k]);
        }
    }
    return out;
}

// ── Header + stats del mapa ────────────────────────────────────────────────────
const headerName = computed(() => {
    const d = dataForm.data ?? {};
    return [d.name, d.father_last_name, d.mother_last_name].filter(Boolean).join(" ").trim();
});

// ISSUE 1: iniciales (primera letra de nombre + primera del apellido paterno) o "?".
const initials = computed(() => {
    const d = dataForm.data ?? {};
    const a = (d.name || "").trim()[0] ?? "";
    const b = (d.father_last_name || "").trim()[0] ?? "";
    const s = (a + b).toUpperCase();
    return s || "?";
});

// ISSUE 2: saldo limpio (helper, sin el template completo de ClientInfoAccountBalance).
const balance = ref(null);

// ISSUE 3: resuelve el label de un campo select desde sus options ({value,text}/{value,label}).
// Degrada elegantemente: si no hay options array o no matchea → null (se omite, nunca "null").
function optionLabel(fieldName) {
    const m = fieldsJson.value?.["client_main_information"] ?? {};
    const a = fieldsJson.value?.["client_additional_information"] ?? {};
    const cfg = m[fieldName] ?? a[fieldName];
    const val = dataForm.data?.[fieldName];
    if (!cfg || val === null || val === undefined || val === "") return null;
    const opts = cfg.options;
    if (Array.isArray(opts)) {
        const f = opts.find((o) => String(o.value ?? o.id ?? o.key) === String(val));
        if (f) return f.text ?? f.label ?? f.name ?? null;
    }
    return null;
}

const planLabel = computed(() => optionLabel("ift"));
const colonyLabel = computed(() => optionLabel("colony_id"));
const municipalityName = computed(() => optionLabel("municipality_id"));
const sellerLabel = computed(() => optionLabel("seller_id"));

const MESES = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];
const dischargeDateFmt = computed(() => {
    const v = dataForm.data?.discharge_date;
    if (!v) return null;
    const d = new Date(v);
    if (isNaN(d.getTime())) return null;
    return `${String(d.getDate()).padStart(2, "0")} ${MESES[d.getMonth()]} ${d.getFullYear()}`;
});

function parseGeo(g) {
    if (!g || typeof g !== "string") return null;
    const parts = g.split(",").map((s) => parseFloat(String(s).trim()));
    return parts.length === 2 && isFinite(parts[0]) && isFinite(parts[1])
        ? { lat: parts[0], lng: parts[1] }
        : null;
}

const geodataFixed = computed(() => !!parseGeo(dataForm.data?.geodata));
const mapLat = computed(() => parseGeo(dataForm.data?.geodata)?.lat ?? null);
const mapLng = computed(() => parseGeo(dataForm.data?.geodata)?.lng ?? null);

const distanceToOffice = computed(() => {
    const oLat = parseFloat(props.officeLat);
    const oLng = parseFloat(props.officeLng);
    if (!isFinite(oLat) || !isFinite(oLng)) return "Configura coords de oficina";
    const p = parseGeo(dataForm.data?.geodata);
    if (!p) return "Sin ubicación del cliente";
    const R = 6371;
    const dLat = ((p.lat - oLat) * Math.PI) / 180;
    const dLng = ((p.lng - oLng) * Math.PI) / 180;
    const aa =
        Math.sin(dLat / 2) ** 2 +
        Math.cos((oLat * Math.PI) / 180) * Math.cos((p.lat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
    const km = R * 2 * Math.atan2(Math.sqrt(aa), Math.sqrt(1 - aa));
    return km.toFixed(1) + " km";
});

let submitButtonAction = props.id && "Salvar Cliente";
const clientTickets = ref({
    open: 0,
    closed: 0,
});

const hasPermission = reactive({
    data: new Permission({}),
});

const oldStatus = ref(null);
const currentOnu = ref(null);
const showPanelOnu = ref(false);
const showDialogUnconfigured = ref(false);
let timer = null;
const loadingOnu = ref(false);

// Carga de los datos del cliente `id`. Extraída del onMounted para poder re-ejecutarla
// cuando cambia props.id (watch) y no solo al montar. Cada llamada toma un token del
// contador compartido; tras cada await comprueba que sigue siendo la carga vigente antes
// de aplicar resultados o de escribir el singleton dataForm (getfieldsEditedWithMultipleModel).
// Así una carga lenta del cliente anterior no pisa la del cliente actual.
const loadClient = async (id) => {
    const myToken = ++clientLoadToken.value;

    // Antes NO tenían try/catch: un fallo aquí abortaba la carga ANTES de refrescar
    // dataForm y dejaba en pantalla los datos del cliente previo.
    try {
        clientTickets.value = await getClientTickets(id);
    } catch (e) {
        clientTickets.value = { open: 0, closed: 0 };
    }
    if (myToken !== clientLoadToken.value) return;

    try {
        const b = await getClientWithBalance(id);
        balance.value = b?.balance ?? null;
    } catch (e) {
        balance.value = null;
    }
    if (myToken !== clientLoadToken.value) return;

    let ids = {};
    try {
        ids = await getClientMainInformationIdAndClientAdditionalInformationId(id);
    } catch (e) {
        ids = {};
    }
    if (myToken !== clientLoadToken.value) return;

    clientMainInformationId.value = ids.clientMainInformationId;

    // Guard ANTES del write al singleton: si otra carga arrancó, no escribimos dataForm.
    if (myToken !== clientLoadToken.value) return;
    await getfieldsEditedWithMultipleModel(
        [
            {
                client_main_information: "ClientMainInformation",
            },
            {
                client_additional_information:
                    "ClientAdditionalInformation",
            },
        ],
        id,
        {
            client_main_information: ids.clientMainInformationId,
            client_additional_information:
                ids.clientAdditionalInformationId,
        }
    );
    if (myToken !== clientLoadToken.value) return;

    oldStatus.value = dataForm.data.estado;
    emits("getTypeOfBilling", {
        typeOfBilling: dataForm.data.type_of_billing_id,
    });
};

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    if (props.id) {
        await loadClient(props.id);
    }
});

// Si la instancia se reutilizara con otro id (o el id cambia en caliente), recargar los
// datos del nuevo cliente. En el flujo SPA normal la instancia se remonta (onMounted ya
// cubre), pero este watch es la red de seguridad ante reutilización de instancia.
watch(
    () => props.id,
    (nuevo, viejo) => {
        if (nuevo && nuevo !== viejo) loadClient(nuevo);
    }
);

onUnmounted(() => {
    clearInterval(timer);
});

watch(changeBalance, async () => {
    if (changeBalance.value == true) {
        let ids =
            await getClientMainInformationIdAndClientAdditionalInformationId(
                props.id
            );

        await getfieldsEditedWithMultipleModel(
            [
                {
                    client_main_information: "ClientMainInformation",
                },
                {
                    client_additional_information:
                        "ClientAdditionalInformation",
                },
            ],
            props.id,
            {
                client_main_information: ids.clientMainInformationId,
                client_additional_information:
                    ids.clientAdditionalInformationId,
            }
        );
    }
    changeBalance.value = false;
});

const getClientMainInformationIdAndClientAdditionalInformationId = async (
    clientId
) => {
    let res;
    await axios
        .post(
            `/cliente/${clientId}/get-client-main-information-id-and-client-additional-information-id`
        )
        .then((r) => {
            res = r.data;
        });

    return {
        clientMainInformationId: res.clientMainInformationId,
        clientAdditionalInformationId: res.clientAdditionalInformationId,
    };
};

const onSubmit = async () => {
    if (await checkifClientStatusActivadoAndChangeToLocked()) {
        dataForm.data
            .submit("post", `/cliente/${props.action}`, props.action)
            .then((response) => {
                toastr.success("Cliente actualizado correctamente", "Cliente");
            })
            .error((error) => {
                Swal.fire("Error", "Ocurrió un error al actualizar", "error");
            });
    }
};

const checkifClientStatusActivadoAndChangeToLocked = async () => {
    // Verificar si el estado cambió
    if (oldStatus.value == dataForm.data.estado) {
        return true; // No hay cambio de estado, permite continuar
    }

    // Verificar permisos si el estado cambió
    if (!hasPermission.data.canView("client_update_status")) {
        Swal.fire({
            title: "Permiso denegado",
            text: "Solo los administradores pueden cambiar el estado del cliente",
            icon: "error",
            confirmButtonColor: "#3085d6",
            confirmButtonText: "Aceptar",
        });
        return false;
    }

    // Si tiene permisos y el estado cambió, pedir confirmación
    const result = await Swal.fire({
        title: "¿Estás seguro que deseas actualizar el cliente?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, Actualizar",
        cancelButtonText: "Cancelar",
    });

    return result.isConfirmed;
};

const updateThisField = ({ field, value }) => {
    if (field == "type_of_billing_id") {
        emits("getTypeOfBilling", { typeOfBilling: value.value });
    }
    dataForm.data[field] = value;
};

const getCoordenates = ({ lat, lng }) => {
    dataForm.data["geodata"] = `${lat},${lng}`;
};

const textInformation = ref("");
const oldAttributes = ref([]);
const newAttributes = ref([]);
const showInformation = (item) => {
    textInformation.value = item;
    let info = JSON.parse(item.data);
    let dataOld = info.old;
    let dataNew = info.attributes;
    if (dataOld) {
        oldAttributes.value = Object.entries(dataOld).map(([key, value]) => {
            return { [key]: value };
        });
    }

    if (dataNew) {
        newAttributes.value = Object.entries(dataNew).map(([key, value]) => {
            return { [key]: value };
        });
    }
};

function hasDifference(key, value) {
    const newAttribute = newAttributes.value.find((attr) => attr[key]);
    return newAttribute && newAttribute[key] !== value;
}

const onCreatedOnu = (onu) => {
    dataForm.data.gpon_ont = onu.sn;
    currentOnu.value = onu;
    showPanelOnu.value = true;
    showDialogUnconfigured.value = false;
};

const onRemoveOnu = () => {
    dataForm.data.gpon_ont = null;
    showPanelOnu.value = false;
    currentOnu.value = null;
};

const getClient = () => {
    let info = fieldsJson?.value["client_main_information"] ?? null;
    return info
        ? {
              id: props.id,
              name: `${info.name.value} ${info.father_last_name.value} ${info.mother_last_name.value}`,
              address: info.address.value,
          }
        : null;
};

const onFixedShow = async () => {
    if (dataForm.data.gpon_ont) {
        if (!currentOnu.value) {
            loadingOnu.value = true;
            const result = await getOnuByClient(props.id);
            loadingOnu.value = false;
            if (result?.success) {
                currentOnu.value = result.onu;
                showPanelOnu.value = true;
            } else {
                message("Onu no encontrada", "error");
            }
        } else {
            showPanelOnu.value = true;
        }
    } else {
        showDialogUnconfigured.value = true;
        showPanelOnu.value = false;
    }
};

const onUpdateCurrentOnu = (onu) => {
    if (!currentOnu) {
        currentOnu.value = onu;
    } else {
        Object.assign(currentOnu.value, onu);
    }
};
</script>

<style scoped>
.cic-wrap { padding: 4px; }

/* Header moderno */
.cic-header-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 14px; }
.cic-header { display: flex; align-items: center; gap: 14px; padding: 14px 18px; flex-wrap: wrap; }
.cic-avatar { width: 52px; height: 52px; border-radius: 12px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; }
.cic-header-main { flex: 1 1 280px; min-width: 0; }
.cic-title { margin: 0; font-size: 1.15rem; font-weight: 700; }
.cic-estado { /* color driven by Bootstrap bg-* class from clientStatusMeta */ }
.cic-subline { display: flex; flex-wrap: wrap; gap: 14px; color: #6b7280; font-size: 0.82rem; margin-top: 3px; }
.cic-header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cic-balance { margin-right: 6px; }

/* Cards de sección */
.cic-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0 0 14px; margin-bottom: 14px; }
.cic-card-head { font-weight: 700; font-size: 0.92rem; color: #374151; padding: 12px 16px; border-bottom: 1px solid #f1f3f5; display: flex; align-items: center; gap: 8px; }
.cic-card-head i { color: #6366f1; }
.cic-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 18px; padding: 14px 16px 0; }
@media (max-width: 991px) { .cic-grid { grid-template-columns: 1fr; } }

/* Labels arriba + 11px (restyle sobre la salida de ComponentFormDefault) */
.cic-grid :deep(.form-group.row),
.cic-grid :deep(.partial-class-field) { display: block !important; margin-bottom: 10px; }
.cic-grid :deep(label.col-form-label),
.cic-grid :deep(label) { display: block !important; text-align: left !important; font-size: 11px !important; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 3px; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
.cic-grid :deep(.col-md-3),
.cic-grid :deep(.col-md-6),
.cic-grid :deep(.col-md-9),
.cic-grid :deep([class*="col-"]) { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; padding-left: 0; padding-right: 0; }

/* Banner nomenclatura antigua */
.cic-banner-old { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 8px 14px; margin: 12px 16px 0; font-size: 0.86rem; }

/* Widgets gpon/power */
.cic-widget-row { padding: 8px 16px 0; }
.cic-wlabel { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 3px; }

/* Mapa + stats */
.cic-sticky { position: sticky; top: 80px; }
.cic-map-head { justify-content: space-between; }
.cic-map-box { padding: 14px 16px 0; }
.cic-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 14px 16px 4px; }
.cic-stat { background: #f8fafc; border: 1px solid #eef0f3; border-radius: 10px; padding: 10px 12px; }
.cic-stat-label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; }
.cic-stat-value { font-size: 0.95rem; font-weight: 700; color: #334155; margin-top: 2px; }

.highlight-difference { background: #fff3cd; }

/* Saldo limpio en header (issue 2) */
.cic-balance { display: flex; flex-direction: column; align-items: flex-end; margin-right: 6px; line-height: 1.1; }
.cic-balance-label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; }
.cic-balance-value { font-size: 1.05rem; font-weight: 700; color: #16a34a; }

/* ── Color identificador por sección ─────────────────────────────────────────── */
/* MODO CLARO: fondo de color solo en el encabezado */
.cic-card--personal     .cic-card-head { background: #EEEDFE; color: #3C3489; }
.cic-card--direccion    .cic-card-head { background: #E6F1FB; color: #0C447C; }
.cic-card--servicio     .cic-card-head { background: #E1F5EE; color: #085041; }
.cic-card--credenciales .cic-card-head { background: #FAEEDA; color: #633806; }
.cic-card--contrato     .cic-card-head { background: #EAF3DE; color: #27500A; }
.cic-card--encuesta     .cic-card-head { background: #FAECE7; color: #712B13; }
.cic-card--ubicacion    .cic-card-head { background: #FBEAF0; color: #72243E; }
.cic-card--otros        .cic-card-head { background: #F1EFE8; color: #2C2C2A; }
/* El ícono hereda el color del encabezado (neutraliza .cic-card-head i { color:#6366f1 }) */
.cic-card--personal     .cic-card-head i, .cic-card--direccion    .cic-card-head i,
.cic-card--servicio     .cic-card-head i, .cic-card--credenciales .cic-card-head i,
.cic-card--contrato     .cic-card-head i, .cic-card--encuesta     .cic-card-head i,
.cic-card--ubicacion    .cic-card-head i, .cic-card--otros        .cic-card-head i { color: inherit; }

/* ── Dark mode (issue 6): el sistema usa el ref darkMode de hook/appConfig ───── */
.cic-dark .cic-header-card,
.cic-dark .cic-card { background: #1d1d21; border-color: #2c2c34; }
.cic-dark .cic-card-head { color: #e5e7eb; border-color: #2c2c34; background: #26262c; }
.cic-dark .cic-title { color: #f1f5f9; }
.cic-dark .cic-avatar { background: #312e81; color: #c7d2fe; }
.cic-dark .cic-stat { background: #26262c; border-color: #2c2c34; }
.cic-dark .cic-stat-value { color: #e5e7eb; }
.cic-dark .cic-banner-old { background: #3a2f0b; border-color: #5c4a12; color: #fde68a; }
.cic-dark .cic-wlabel,
.cic-dark .cic-stat-label,
.cic-dark .cic-subline { color: #9ca3af; }
.cic-dark .cic-grid :deep(label) { color: #9ca3af; }

/* MODO OSCURO: línea inferior coloreada + ícono del encabezado */
.cic-dark .cic-card--personal     { border-bottom: 3px solid #7F77DD; }
.cic-dark .cic-card--direccion    { border-bottom: 3px solid #378ADD; }
.cic-dark .cic-card--servicio     { border-bottom: 3px solid #1D9E75; }
.cic-dark .cic-card--credenciales { border-bottom: 3px solid #EF9F27; }
.cic-dark .cic-card--contrato     { border-bottom: 3px solid #639922; }
.cic-dark .cic-card--encuesta     { border-bottom: 3px solid #D85A30; }
.cic-dark .cic-card--ubicacion    { border-bottom: 3px solid #D4537E; }
.cic-dark .cic-card--otros        { border-bottom: 3px solid #888780; }
.cic-dark .cic-card--personal     .cic-card-head i { color: #AFA9EC; }
.cic-dark .cic-card--direccion    .cic-card-head i { color: #85B7EB; }
.cic-dark .cic-card--servicio     .cic-card-head i { color: #5DCAA5; }
.cic-dark .cic-card--credenciales .cic-card-head i { color: #FAC775; }
.cic-dark .cic-card--contrato     .cic-card-head i { color: #97C459; }
.cic-dark .cic-card--encuesta     .cic-card-head i { color: #F0997B; }
.cic-dark .cic-card--ubicacion    .cic-card-head i { color: #ED93B1; }
.cic-dark .cic-card--otros        .cic-card-head i { color: #B4B2A9; }
</style>
