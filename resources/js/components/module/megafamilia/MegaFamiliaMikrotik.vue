<template>
    <div class="megafamilia-mikrotik mt-3">
        <h5 class="mb-3">Integración MikroTik</h5>

        <div v-if="loading && !settings" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div v-else>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100" :class="{ 'card-enabled': settings.enabled }">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="m-0">Activar integración</h6>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="settings.enabled" />
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                Si está activada, MegaFamilia podrá pausar internet de un perfil escribiendo
                                en la <em>address list</em> del router.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6>Configuración</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Address list (corte)</label>
                                    <input v-model="settings.address_list_block" class="form-control" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Acción al pausar</label>
                                    <select v-model="settings.pause_internet_action" class="form-select">
                                        <option value="address_list">Agregar a address list</option>
                                        <option value="queue">Bajar bandwidth con queue</option>
                                        <option value="disable_user">Deshabilitar usuario PPP/PPPoE</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-primary" :disabled="saving" @click="save">
                                    <i class="fa fa-save"></i> {{ saving ? 'Guardando…' : 'Guardar' }}
                                </button>
                                <button class="btn btn-outline-info" :disabled="testing" @click="test">
                                    <i class="fa fa-plug" :class="{ 'fa-spin': testing }"></i>
                                    {{ testing ? 'Probando…' : 'Probar conexión' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="testResult" class="card mt-2">
                <div class="card-body">
                    <h6 class="card-title">
                        Resultado:
                        <span class="badge ms-1" :class="testResult.reachable ? 'bg-success' : 'bg-danger'">
                            {{ testResult.reachable ? 'Alcanzable' : 'No alcanzable' }}
                        </span>
                    </h6>
                    <div v-if="testResult.note" class="text-muted small mb-2">{{ testResult.note }}</div>
                    <div v-if="testResult.error" class="text-danger small">{{ testResult.error }}</div>
                    <table v-if="testResult.results && testResult.results.length" class="table table-sm m-0">
                        <thead>
                            <tr><th>Router</th><th>IP</th><th>Estado</th><th>Error</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in testResult.results" :key="r.router_id">
                                <td>#{{ r.router_id }}</td>
                                <td><code>{{ r.ip_host }}</code></td>
                                <td>
                                    <span class="badge" :class="r.reachable ? 'bg-success' : 'bg-danger'">
                                        {{ r.reachable ? 'OK' : 'Falló' }}
                                    </span>
                                </td>
                                <td class="text-danger small">{{ r.error || '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaMikrotik',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { settings: null, loading: false, errorMsg: '', saving: false, testing: false, testResult: null }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try { const { data } = await axios.get(`${this.baseUrl}/mikrotik/get`); this.settings = data; }
            catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        async save() {
            this.saving = true;
            try { await axios.post(`${this.baseUrl}/mikrotik/update`, this.settings, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.saving = false; }
        },
        async test() {
            this.testing = true; this.testResult = null;
            try { const { data } = await axios.post(`${this.baseUrl}/mikrotik/test`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); this.testResult = data; }
            catch (e) { this.testResult = { reachable: false, error: e.response?.data?.error || e.message }; }
            finally { this.testing = false; }
        },
    },
};
</script>

<style scoped>
.card-enabled { border-left: 3px solid #38c172; background: #f5fff8; }
</style>
