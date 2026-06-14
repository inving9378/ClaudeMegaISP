<template>
    <div class="q-pa-md" style="max-width: 600px">

        <!-- Estado actual -->
        <q-banner v-if="cfg.activa" class="q-mb-md bg-positive text-white" rounded>
            <template #avatar>
                <q-icon name="check_circle" />
            </template>
            Credenciales activas — las OLTs sincronizarán con SmartOLT.
        </q-banner>
        <q-banner v-else class="q-mb-md bg-warning text-dark" rounded>
            <template #avatar>
                <q-icon name="warning" />
            </template>
            Sin credenciales configuradas — los crons de sync están inactivos.
        </q-banner>

        <q-form @submit.prevent="save">

            <!-- Dominio -->
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

            <!-- Token -->
            <q-input
                v-model="form.api_token"
                :label="cfg.token_set ? 'Token API (dejar vacío para conservar el actual)' : 'Token API'"
                :placeholder="cfg.token_masked ?? ''"
                outlined
                dense
                type="password"
                class="q-mb-md"
                :loading="loading"
                autocomplete="new-password"
            />

            <!-- Activa -->
            <q-toggle
                v-model="form.activa"
                label="Usar estas credenciales (desactivar usa el fallback de .env)"
                class="q-mb-md"
            />

            <!-- TTL y Budget (avanzado) -->
            <q-expansion-item label="Configuración avanzada" dense class="q-mb-md">
                <div class="q-pa-sm">
                    <q-input
                        v-model.number="form.ttl"
                        label="TTL de caché (segundos)"
                        type="number"
                        outlined
                        dense
                        class="q-mb-sm"
                    />
                    <q-input
                        v-model.number="form.hourly_budget"
                        label="Presupuesto de llamadas por hora"
                        type="number"
                        outlined
                        dense
                    />
                </div>
            </q-expansion-item>

            <!-- Botones -->
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

        <!-- Resultado del test -->
        <q-banner
            v-if="testResult"
            :class="testResult.success ? 'bg-positive text-white' : 'bg-negative text-white'"
            class="q-mt-md"
            rounded
        >
            <template #avatar>
                <q-icon :name="testResult.success ? 'check_circle' : 'error'" />
            </template>
            {{ testResult.message }}
        </q-banner>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useQuasar } from "../../../../../../../public/plugins/quasar/js/quasar.umd.prod";

defineOptions({ name: "SmartoltConfig" });

const $q = useQuasar();

const loading   = ref(false);
const saving    = ref(false);
const testing   = ref(false);
const importing = ref(false);
const testResult = ref(null);

const cfg = ref({
    api_domain:    "",
    token_set:     false,
    token_masked:  null,
    ttl:           120,
    hourly_budget: 1000,
    activa:        false,
});

const form = reactive({
    api_domain:    "",
    api_token:     "",
    ttl:           120,
    hourly_budget: 1000,
    activa:        false,
});

// Puede probar si hay dominio en el form o ya guardado, y token en el form o ya guardado
const canTest = computed(() =>
    (form.api_domain || cfg.value.api_domain) &&
    (form.api_token  || cfg.value.token_set)
);

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

async function save() {
    saving.value  = true;
    testResult.value = null;
    try {
        const payload = {
            api_domain:    form.api_domain,
            ttl:           form.ttl,
            hourly_budget: form.hourly_budget,
            activa:        form.activa,
        };
        if (form.api_token.trim() !== "") {
            payload.api_token = form.api_token.trim();
        }
        const res = await axios.post("/olts/settings/smartolt-config", payload);
        $q.notify({ type: "positive", message: res.data.message });
        form.api_token = "";
        await load();
    } catch (e) {
        $q.notify({ type: "negative", message: e?.response?.data?.message ?? "Error al guardar." });
    } finally {
        saving.value = false;
    }
}

async function testConnection() {
    testing.value    = true;
    testResult.value = null;
    try {
        // Si no hay token nuevo en el form pero ya hay uno guardado, enviamos el dominio
        // y dejamos que el backend use el token ya cifrado — para eso mandamos un indicador.
        // Si hay token nuevo en el form, lo enviamos directamente (prueba pre-guardado).
        const payload = {
            api_domain: form.api_domain || cfg.value.api_domain,
            api_token:  form.api_token.trim() !== ""
                ? form.api_token.trim()
                : "__use_saved__",
        };
        const res = await axios.post("/olts/settings/smartolt-config/test", payload);
        testResult.value = res.data;
    } catch (e) {
        testResult.value = {
            success: false,
            message: e?.response?.data?.message ?? "Error al conectar.",
        };
    } finally {
        testing.value = false;
    }
}

async function importInventory() {
    importing.value  = true;
    testResult.value = null;
    try {
        const res = await axios.post("/olts/settings/smartolt-config/import");
        $q.notify({ type: res.data.success ? "positive" : "warning", message: res.data.message });
    } catch (e) {
        $q.notify({ type: "negative", message: e?.response?.data?.message ?? "Error al iniciar importación." });
    } finally {
        importing.value = false;
    }
}

onMounted(load);
</script>
