<template>
    <div class="api-movil-config mt-3">
        <h5 class="mb-3">Configuración API Móvil</h5>

        <div v-if="loading && !config" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div v-else class="card">
            <div class="card-body">
                <div class="mb-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">API base URL</div>
                        <code class="d-block fs-6">{{ config.api_base_url }}</code>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="api-enabled" v-model="config.api_enabled" />
                        <label class="form-check-label" for="api-enabled">
                            <strong :class="config.api_enabled ? 'text-success' : 'text-danger'">
                                {{ config.api_enabled ? 'Habilitada' : 'Deshabilitada' }}
                            </strong>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">URL base</label>
                    <input v-model="config.api_base_url" class="form-control" />
                </div>

                <div class="mb-3">
                    <label class="form-label">Roles permitidos</label>
                    <div class="d-flex gap-3 flex-wrap">
                        <div v-for="role in availableRoles" :key="role" class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :id="`role-${role}`"
                                :value="role"
                                v-model="config.allowed_roles"
                            />
                            <label class="form-check-label" :for="`role-${role}`">{{ role }}</label>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Token expiry (horas)</label>
                        <input v-model.number="config.token_expiry_hours" type="number" min="1" max="8760" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max requests / minuto</label>
                        <input v-model.number="config.max_requests_per_minute" type="number" min="1" max="6000" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Versión mínima de app</label>
                        <input v-model="config.min_app_version" class="form-control" placeholder="ej. 1.2.0" />
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">URL de descarga APK</label>
                    <input v-model="config.apk_url" class="form-control" />
                </div>

                <div class="text-end mt-3">
                    <button class="btn btn-primary" :disabled="saving" @click="save">
                        <i class="fa fa-save"></i> {{ saving ? 'Guardando…' : 'Guardar' }}
                    </button>
                    <div v-if="savedAt" class="text-success small mt-2">Guardado · {{ savedAt }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ApiMovilConfig',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            config: null,
            availableRoles: ['cliente', 'tecnico', 'hijo', 'padre', 'admin'],
            loading: false,
            errorMsg: '',
            saving: false,
            savedAt: '',
        };
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/get`);
                this.config = data.config;
                if (! Array.isArray(this.config.allowed_roles)) this.config.allowed_roles = [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        async save() {
            this.saving = true; this.savedAt = '';
            try {
                await axios.post(`${this.baseUrl}/update`, this.config, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.savedAt = new Date().toLocaleTimeString('es-MX');
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
    },
};
</script>
