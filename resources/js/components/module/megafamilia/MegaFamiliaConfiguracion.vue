<template>
    <div class="megafamilia-configuracion mt-3">
        <h5 class="mb-3">Configuración global MegaFamilia</h5>

        <div v-if="loading && !settings" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div v-else class="row">
            <!-- Firebase -->
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa fa-fire text-warning"></i> Firebase / FCM
                        </h6>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" v-model="settings.fcm_enabled" id="fcm-toggle" />
                            <label class="form-check-label" for="fcm-toggle">Habilitar envío de push</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Server key</label>
                            <input v-model="settings.firebase_server_key" type="password" class="form-control" placeholder="AAAA…" />
                            <div class="form-text">API legacy server key. Si se deja vacío, se usa FCM_SERVER_KEY del .env.</div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Project ID</label>
                            <input v-model="settings.firebase_project_id" class="form-control" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maps -->
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa fa-map-location-dot text-info"></i> Google Maps
                        </h6>
                        <div class="mb-0">
                            <label class="form-label">API key</label>
                            <input v-model="settings.maps_api_key" class="form-control" />
                            <div class="form-text">Default: el valor de MIX_VUE_APP_GOOGLEMAPS_KEY del .env.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Globales -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa fa-sliders text-secondary"></i> Globales
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Intervalo de GPS por defecto (segundos)</label>
                                <input v-model.number="settings.gps_default_interval" type="number" min="30" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Umbral de batería baja (%)</label>
                                <input v-model.number="settings.low_battery_threshold" type="number" min="0" max="100" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 text-end">
                <button class="btn btn-primary" :disabled="saving" @click="save">
                    <i class="fa fa-save"></i> {{ saving ? 'Guardando…' : 'Guardar configuración' }}
                </button>
                <div v-if="savedAt" class="text-success small mt-2">
                    Guardado · {{ savedAt }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaConfiguracion',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { settings: null, loading: false, errorMsg: '', saving: false, savedAt: '' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try { const { data } = await axios.get(`${this.baseUrl}/configuracion/get`); this.settings = data; }
            catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        async save() {
            this.saving = true;
            try {
                await axios.post(`${this.baseUrl}/configuracion/update`, this.settings, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.savedAt = new Date().toLocaleTimeString('es-MX');
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
    },
};
</script>
