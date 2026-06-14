<template>
    <div class="q-pa-md">

        <!-- ── Indicador persistente de estado ─────────────────────────── -->
        <div class="smartolt-status-card q-mb-lg">

            <!-- Fila de estado principal -->
            <div class="status-header q-mb-md">
                <span class="status-dot" :class="dotClass"></span>
                <span class="status-label" :class="labelClass">
                    {{ estadoLabel }}
                </span>
                <q-spinner v-if="loadingStatus" size="14px" class="q-ml-sm" color="grey-5" />
            </div>

            <!-- 4 tarjetas de información -->
            <div class="status-grid">

                <div class="status-tile">
                    <div class="tile-title">Subdominio</div>
                    <div class="tile-value">
                        <template v-if="status.api_domain">
                            {{ status.api_domain }}<span class="tile-suffix">.smartolt.com</span>
                        </template>
                        <span v-else class="tile-empty">—</span>
                    </div>
                </div>

                <div class="status-tile">
                    <div class="tile-title">Conectada desde</div>
                    <div class="tile-value">
                        <template v-if="status.connected_since">
                            {{ relativo(status.connected_since) }}
                            <div class="tile-abs">{{ absoluto(status.connected_since) }}</div>
                        </template>
                        <span v-else class="tile-empty">—</span>
                    </div>
                </div>

                <div class="status-tile">
                    <div class="tile-title">Última sincronización</div>
                    <div class="tile-value">
                        <template v-if="status.last_sync_ok_at">
                            {{ relativo(status.last_sync_ok_at) }}
                            <div class="tile-hint">cron cada 10 min</div>
                        </template>
                        <span v-else class="tile-empty">—</span>
                    </div>
                </div>

                <div class="status-tile">
                    <div class="tile-title">OLTs detectadas</div>
                    <div class="tile-value">
                        <template v-if="status.last_olt_count != null">
                            {{ status.last_olt_count }}
                        </template>
                        <span v-else class="tile-empty">—</span>
                    </div>
                </div>

            </div>

            <!-- Mensaje de error cuando sin_conexion -->
            <div
                v-if="status.estado === 'sin_conexion' && status.last_error_message"
                class="status-error q-mt-sm"
            >
                <q-icon name="error_outline" size="14px" class="q-mr-xs" />
                {{ status.last_error_message }}
            </div>

        </div>

        <!-- ── Formulario de credenciales ───────────────────────────────── -->
        <q-form @submit.prevent="save" style="max-width:560px">

            <q-input
                v-model="form.api_domain"
                label="Dominio SmartOLT"
                hint="Solo el subdominio, sin .smartolt.com (ej: mi-isp)"
                outlined
                dense
                class="q-mb-md"
                :loading="loading"
            >
                <template #append>
                    <span class="text-grey-6 text-caption">.smartolt.com</span>
                </template>
            </q-input>

            <q-input
                v-model="form.api_token"
                :label="cfg.token_set
                    ? 'Token API (vacío = conservar el actual)'
                    : 'Token API'"
                :placeholder="cfg.token_masked ?? ''"
                outlined
                dense
                type="password"
                class="q-mb-md"
                :loading="loading"
                autocomplete="new-password"
            />

            <q-toggle
                v-model="form.activa"
                label="Usar estas credenciales (desactivar = usar .env)"
                class="q-mb-md"
            />

            <q-expansion-item label="Configuración avanzada" dense class="q-mb-md">
                <div class="q-pa-sm row q-gutter-sm">
                    <q-input
                        v-model.number="form.ttl"
                        label="TTL caché (segundos)"
                        type="number"
                        outlined
                        dense
                        style="width:180px"
                    />
                    <q-input
                        v-model.number="form.hourly_budget"
                        label="Llamadas por hora"
                        type="number"
                        outlined
                        dense
                        style="width:180px"
                    />
                </div>
            </q-expansion-item>

            <div class="row q-gutter-sm">
                <q-btn
                    type="submit"
                    color="primary"
                    label="Guardar"
                    :loading="saving"
                    :disable="!form.api_domain"
                />
                <q-btn
                    outline
                    color="secondary"
                    label="Probar conexión"
                    :loading="testing"
                    :disable="!canTest"
                    @click="testConnection"
                />
                <q-btn
                    outline
                    color="accent"
                    icon="cloud_download"
                    label="Importar inventario"
                    :loading="importing"
                    :disable="!cfg.activa"
                    @click="importInventory"
                />
            </div>

        </q-form>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useQuasar } from "../../../../../../../public/plugins/quasar/js/quasar.umd.prod";

defineOptions({ name: "SmartoltConfig" });

const $q = useQuasar();

// ── Estado remoto ─────────────────────────────────────────────────────────
const status = ref({
    estado: "sin_configurar",
    api_domain: null,
    connected_since: null,
    last_sync_ok_at: null,
    last_sync_error_at: null,
    last_error_message: null,
    last_olt_count: null,
});

const loadingStatus = ref(false);

// ── Config form ───────────────────────────────────────────────────────────
const loading   = ref(false);
const saving    = ref(false);
const testing   = ref(false);
const importing = ref(false);

const cfg = ref({
    api_domain: "", token_set: false, token_masked: null,
    ttl: 120, hourly_budget: 1000, activa: false,
});

const form = reactive({
    api_domain: "", api_token: "",
    ttl: 120, hourly_budget: 1000, activa: false,
});

// ── Computed ──────────────────────────────────────────────────────────────
const canTest = computed(() =>
    (form.api_domain || cfg.value.api_domain) &&
    (form.api_token  || cfg.value.token_set)
);

const dotClass = computed(() => ({
    "dot-green":  status.value.estado === "conectada",
    "dot-red":    status.value.estado === "sin_conexion",
    "dot-grey":   status.value.estado === "sin_configurar",
}));

const labelClass = computed(() => ({
    "text-positive": status.value.estado === "conectada",
    "text-negative": status.value.estado === "sin_conexion",
    "text-grey-6":   status.value.estado === "sin_configurar",
}));

const estadoLabel = computed(() => ({
    conectada:      "Conectada",
    sin_conexion:   "Sin conexión",
    sin_configurar: "Sin configurar",
}[status.value.estado] ?? "—"));

// ── Helpers de tiempo ─────────────────────────────────────────────────────
function relativo(iso) {
    if (!iso) return "—";
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60)  return "hace " + diff + " s";
    if (diff < 3600) {
        const m = Math.floor(diff / 60);
        return "hace " + m + " min";
    }
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    return m > 0 ? `hace ${h} h ${m} min` : `hace ${h} h`;
}

function absoluto(iso) {
    if (!iso) return "";
    return new Date(iso).toLocaleString("es-MX", {
        day: "2-digit", month: "2-digit", year: "numeric",
        hour: "2-digit", minute: "2-digit",
    });
}

// ── Carga de datos ────────────────────────────────────────────────────────
async function loadStatus() {
    loadingStatus.value = true;
    try {
        const res = await axios.get("/olts/settings/smartolt-config/status");
        status.value = res.data;
    } catch { /* silent */ } finally {
        loadingStatus.value = false;
    }
}

async function load() {
    loading.value = true;
    try {
        const res = await axios.get("/olts/settings/smartolt-config");
        cfg.value = res.data;
        form.api_domain    = res.data.api_domain    ?? "";
        form.ttl           = res.data.ttl           ?? 120;
        form.hourly_budget = res.data.hourly_budget ?? 1000;
        form.activa        = res.data.activa        ?? false;
    } finally {
        loading.value = false;
    }
}

// ── Acciones ──────────────────────────────────────────────────────────────
async function save() {
    saving.value = true;
    try {
        const payload = {
            api_domain: form.api_domain,
            ttl: form.ttl,
            hourly_budget: form.hourly_budget,
            activa: form.activa,
        };
        if (form.api_token.trim() !== "") payload.api_token = form.api_token.trim();

        const res = await axios.post("/olts/settings/smartolt-config", payload);
        $q.notify({ type: "positive", message: res.data.message, timeout: 2500 });
        form.api_token = "";
        await Promise.all([load(), loadStatus()]);
    } catch (e) {
        $q.notify({ type: "negative", message: e?.response?.data?.message ?? "Error al guardar." });
    } finally {
        saving.value = false;
    }
}

async function testConnection() {
    testing.value = true;
    try {
        const payload = {
            api_domain: form.api_domain || cfg.value.api_domain,
            api_token:  form.api_token.trim() !== ""
                ? form.api_token.trim()
                : "__use_saved__",
        };
        const res = await axios.post("/olts/settings/smartolt-config/test", payload);

        $q.notify({
            type:    res.data.success ? "positive" : "negative",
            message: res.data.message,
            timeout: 3500,
        });

        // Actualizar indicador: el backend ya llamó markSyncOk/Error
        await loadStatus();
    } catch (e) {
        $q.notify({ type: "negative", message: e?.response?.data?.message ?? "Error al conectar." });
    } finally {
        testing.value = false;
    }
}

async function importInventory() {
    importing.value = true;
    try {
        const res = await axios.post("/olts/settings/smartolt-config/import");
        $q.notify({ type: res.data.success ? "positive" : "warning", message: res.data.message, timeout: 4000 });
    } catch (e) {
        $q.notify({ type: "negative", message: e?.response?.data?.message ?? "Error al iniciar importación." });
    } finally {
        importing.value = false;
    }
}

onMounted(() => Promise.all([load(), loadStatus()]));
</script>

<style scoped>
/* ── Card de estado ────────────────────────────────────────────────────── */
.smartolt-status-card {
    background: var(--q-color-card, var(--sys-surface, #fff));
    border: 1px solid var(--q-color-separator, #e0e0e0);
    border-radius: 8px;
    padding: 16px 20px;
    max-width: 680px;
}

/* dark mode: usa la variable de Quasar si existe */
.body--dark .smartolt-status-card {
    background: var(--q-color-dark, #1d1d1d);
    border-color: rgba(255,255,255,0.12);
}

/* Fila de estado principal */
.status-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot-green  { background: #21ba45; box-shadow: 0 0 6px rgba(33,186,69,.5); }
.dot-red    { background: #c10015; box-shadow: 0 0 6px rgba(193,0,21,.4); }
.dot-grey   { background: #9e9e9e; }

.status-label {
    font-weight: 600;
    font-size: 15px;
}

/* Grid de 4 tarjetas */
.status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 600px) {
    .status-grid { grid-template-columns: repeat(2, 1fr); }
}

.status-tile {
    background: var(--q-color-grey-1, #f5f5f5);
    border-radius: 6px;
    padding: 10px 12px;
}
.body--dark .status-tile {
    background: rgba(255,255,255,0.05);
}

.tile-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--q-color-grey-7, #616161);
    margin-bottom: 4px;
}
.body--dark .tile-title { color: rgba(255,255,255,.5); }

.tile-value {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
    word-break: break-all;
}

.tile-suffix {
    font-size: 11px;
    font-weight: 400;
    color: var(--q-color-grey-6, #757575);
}
.tile-abs, .tile-hint {
    font-size: 11px;
    font-weight: 400;
    color: var(--q-color-grey-6, #757575);
    margin-top: 2px;
}
.tile-empty {
    color: var(--q-color-grey-5, #9e9e9e);
    font-weight: 400;
}

/* Mensaje de error */
.status-error {
    font-size: 12px;
    color: var(--q-color-negative, #c10015);
    background: rgba(193,0,21,.07);
    border-radius: 4px;
    padding: 6px 10px;
    display: flex;
    align-items: flex-start;
    word-break: break-word;
}
.body--dark .status-error {
    background: rgba(193,0,21,.15);
}
</style>
