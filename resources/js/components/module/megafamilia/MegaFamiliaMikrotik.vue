<template>
    <div class="megafamilia-mikrotik mt-3">
        <h5 class="mb-3">
            <i class="fa fa-network-wired me-2 text-primary"></i>
            Integración MikroTik
        </h5>

        <div v-if="loading" class="text-center py-3"><div class="spinner-border text-primary"></div></div>

        <template v-else>
            <!-- Conexión -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong>Conexión MikroTik</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mt-enabled" v-model="settings.enabled" />
                                <label class="form-check-label" for="mt-enabled">Integración activa</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Router asociado</label>
                            <select v-model.number="settings.router_id" class="form-select">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="r in routers" :key="r.id" :value="r.id">
                                    {{ r.title || `#${r.id}` }} · {{ r.ip_host }} · {{ r.type_of_nas }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Puerto API</label>
                            <input v-model.number="settings.port" type="number" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Host (override)</label>
                            <input v-model="settings.host" class="form-control" placeholder="auto desde router" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usuario</label>
                            <input v-model="settings.username" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contraseña</label>
                            <input v-model="settings.password" type="password" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span v-if="testResult && testResult.reachable" class="badge bg-success">
                            <i class="fa fa-check me-1"></i> Conectado · {{ testResult.latency_ms }}ms
                        </span>
                        <span v-else-if="testResult && !testResult.reachable" class="badge bg-danger">
                            <i class="fa fa-times me-1"></i> Sin conexión
                        </span>
                        <span v-if="testResult?.note" class="text-muted small ms-2">{{ testResult.note }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-info" :disabled="testing" @click="testConn">
                            <i class="fa fa-satellite-dish me-1"></i> {{ testing ? 'Probando...' : 'Probar conexión' }}
                        </button>
                        <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
                            <i class="fa fa-save me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Address-list -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <strong>Address List</strong>
                    <button class="btn btn-sm btn-outline-secondary" :disabled="loadingList" @click="fetchList">
                        <i class="fa fa-sync" :class="{ 'fa-spin': loadingList }"></i>
                    </button>
                </div>
                <div class="card-body">
                    <label class="form-label">Nombre de address-list para bloqueos</label>
                    <input v-model="settings.address_list_block" class="form-control mb-2" />
                    <div v-if="listData?.note" class="alert alert-info small py-2">{{ listData.note }}</div>

                    <h6 class="text-uppercase small text-muted mt-3">Entradas actuales en RouterOS</h6>
                    <div v-if="!listData?.entries?.length" class="text-muted small">Sin entradas.</div>
                    <table v-else class="table table-sm">
                        <thead class="table-light">
                            <tr><th>IP</th><th>Comentario</th><th>Creado</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e,i) in listData.entries" :key="i">
                                <td><code>{{ e.ip }}</code></td>
                                <td class="small">{{ e.comment || '—' }}</td>
                                <td class="small">{{ e.created_at || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h6 class="text-uppercase small text-muted mt-3">Acciones registradas</h6>
                    <div v-if="!listData?.tracked?.length" class="text-muted small">Sin acciones registradas.</div>
                    <table v-else class="table table-sm">
                        <thead class="table-light">
                            <tr><th>Acción</th><th>Perfil</th><th>Detalle</th><th>Fecha</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in listData.tracked" :key="t.id">
                                <td>
                                    <span class="badge" :class="t.action === 'internet.pause' ? 'bg-warning text-dark' : 'bg-success'">
                                        {{ t.action }}
                                    </span>
                                </td>
                                <td>{{ t.profile_id || '—' }}</td>
                                <td class="small text-truncate" style="max-width:240px;" :title="t.detail">{{ t.detail }}</td>
                                <td class="small">{{ formatDate(t.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-warning" :disabled="syncing" @click="sync">
                        <i class="fa fa-sync me-1"></i> Sincronizar ahora
                    </button>
                </div>
            </div>

            <!-- Control manual -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong>Control manual de internet</strong></div>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Perfil</label>
                            <select v-model.number="manual.profile_id" class="form-select">
                                <option :value="null">— Selecciona perfil —</option>
                                <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-warning w-50" :disabled="!manual.profile_id || acting" @click="pause">
                                <i class="fa fa-pause me-1"></i> Pausar
                            </button>
                            <button class="btn btn-success w-50" :disabled="!manual.profile_id || acting" @click="resume">
                                <i class="fa fa-play me-1"></i> Reanudar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaMikrotik',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false, saving: false, testing: false, syncing: false, acting: false,
            loadingList: false,
            settings: { enabled: false, router_id: null, port: 8728, address_list_block: 'megafamilia-blocked' },
            routers: [], profiles: [], listData: null,
            testResult: null,
            manual: { profile_id: null },
        };
    },
    mounted() {
        this.fetch();
        this.fetchProfiles();
        this.fetchList();
    },
    methods: {
        async fetch() {
            this.loading = true;
            try {
                const [{ data: cfg }, { data: rs }] = await Promise.all([
                    axios.get(`${this.baseUrl}/mikrotik/get`),
                    axios.get(`${this.baseUrl}/mikrotik/routers`),
                ]);
                this.settings = Object.assign({}, this.settings, cfg);
                this.routers = rs.routers || [];
                if (cfg?.last_reachable !== null) {
                    this.testResult = { reachable: cfg.last_reachable, latency_ms: cfg.last_latency_ms, note: null };
                }
            } catch (e) { console.error('[MF/Mikrotik]', e); }
            finally { this.loading = false; }
        },
        async fetchProfiles() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/perfiles/data`);
                this.profiles = data.profiles || [];
            } catch (e) { /* no-op */ }
        },
        async fetchList() {
            this.loadingList = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/mikrotik/address-list`);
                this.listData = data;
            } catch (e) { this.listData = { note: e.response?.data?.message || e.message, entries: [], tracked: [] }; }
            finally { this.loadingList = false; }
        },
        async save() {
            this.saving = true;
            try {
                await axios.post(`${this.baseUrl}/mikrotik/update`, this.settings, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                alert('Configuración guardada.');
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
        async testConn() {
            this.testing = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/mikrotik/test`,
                    { router_id: this.settings.router_id },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.testResult = data;
            } catch (e) {
                this.testResult = { reachable: false, note: e.response?.data?.error || e.message };
            } finally { this.testing = false; }
        },
        async sync() {
            this.syncing = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/mikrotik/sync`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                alert(`Sync ejecutado. ${data.count} perfiles afectados.`);
                await this.fetchList();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.syncing = false; }
        },
        async pause() {
            if (!this.manual.profile_id) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/mikrotik/pause/${this.manual.profile_id}`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetchList();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async resume() {
            if (!this.manual.profile_id) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/mikrotik/resume/${this.manual.profile_id}`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetchList();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('es-MX', { day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit' });
        },
    },
};
</script>
