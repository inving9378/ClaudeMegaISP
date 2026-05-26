<template>
    <div class="megafamilia-terminos mt-3">
        <h5 class="mb-3">
            <i class="fa fa-file-contract me-2 text-primary"></i>
            Términos y Condiciones
        </h5>

        <div v-if="loading" class="text-center py-3"><div class="spinner-border text-primary"></div></div>

        <template v-else>
            <!-- Versión actual -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong>Versión vigente</strong></div>
                <div class="card-body">
                    <div v-if="!current" class="text-muted">
                        Aún no se ha publicado ninguna versión. Crea una usando el editor abajo.
                    </div>
                    <div v-else class="row align-items-center">
                        <div class="col-md-3">
                            <div class="display-6 fw-bold">v{{ current.version_number }}</div>
                            <small class="text-muted">Publicada {{ formatDate(current.published_at) }}</small>
                        </div>
                        <div class="col-md-6">
                            <div class="small">Total aceptaciones: <strong>{{ stats.total_acceptances }}</strong></div>
                            <div class="small">Aceptaciones de esta versión: <strong>{{ stats.current_version_accept }}</strong></div>
                            <div class="small">Pendientes de aceptar: <strong class="text-warning">{{ stats.pending_acceptance }}</strong></div>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="badge bg-success fs-6"><i class="fa fa-check me-1"></i>Vigente</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Editor -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong>Editor de términos</strong></div>
                <div class="card-body">
                    <label class="form-label">Contenido (texto plano o Markdown)</label>
                    <textarea v-model="editor.content" rows="14" class="form-control font-monospace small"
                              placeholder="Escribe los términos y condiciones aquí…"></textarea>

                    <div class="mt-3">
                        <label class="form-label">Notas del cambio (interno)</label>
                        <input v-model="editor.notes" class="form-control" placeholder="¿Qué cambió en esta versión?" />
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="reaccept" v-model="editor.require_reacceptance" />
                        <label class="form-check-label" for="reaccept">
                            Requiere re-aceptación de todos los usuarios
                            <small class="text-muted d-block">Al publicar, todos los clientes deberán aceptar de nuevo.</small>
                        </label>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-secondary" :disabled="saving" @click="saveDraft">
                        <i class="fa fa-save me-1"></i> Guardar borrador
                    </button>
                    <button class="btn btn-sm btn-primary" :disabled="saving || !editor.content.trim()" @click="publish">
                        <i class="fa fa-paper-plane me-1"></i>
                        Publicar v{{ nextVersion }}
                    </button>
                </div>
            </div>

            <!-- Historial -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong>Historial de versiones</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Versión</th><th>Estado</th><th>Publicada</th><th>Notas</th></tr>
                        </thead>
                        <tbody>
                            <tr v-if="!history.length"><td colspan="4" class="text-center text-muted py-3">Sin versiones registradas.</td></tr>
                            <tr v-for="v in history" :key="v.id" role="button" @click="openVersion(v.version_number)">
                                <td><strong>v{{ v.version_number }}</strong></td>
                                <td>
                                    <span v-if="v.is_draft" class="badge bg-secondary">Borrador</span>
                                    <span v-else class="badge bg-success">Publicada</span>
                                </td>
                                <td class="small">{{ v.published_at ? formatDate(v.published_at) : '—' }}</td>
                                <td class="small text-truncate" style="max-width:380px;" :title="v.notes">{{ v.notes || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Aceptaciones -->
            <div class="card">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <strong>Aceptaciones por cuenta</strong>
                    <input v-model="accepFilters.search" type="search" class="form-control form-control-sm"
                           style="width:240px" placeholder="Buscar cliente…" @keyup.enter="fetchAcceptances(1)" />
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Cliente</th><th>Email</th><th class="text-center">Versión</th><th>Fecha aceptación</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                            <tr v-if="!acceptances.length"><td colspan="5" class="text-center text-muted py-3">Sin aceptaciones registradas.</td></tr>
                            <tr v-for="a in acceptances" :key="a.id">
                                <td><strong>{{ a.user?.name || '—' }}</strong></td>
                                <td class="small">{{ a.user?.email || '—' }}</td>
                                <td class="text-center"><span class="badge bg-light text-dark">v{{ a.version_accepted || '?' }}</span></td>
                                <td class="small">{{ formatDate(a.accepted_at) }}</td>
                                <td class="small"><code>{{ a.ip || '—' }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Modal versión completa -->
        <div v-if="viewing" class="mf-modal-overlay" @click.self="viewing = null">
            <div class="mf-modal-panel" style="max-width:780px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Términos v{{ viewing.version_number }}</h5>
                    <button class="btn-close" @click="viewing = null"></button>
                </div>
                <div class="mf-modal-body">
                    <p class="small text-muted mb-2">
                        <span v-if="viewing.is_draft" class="badge bg-secondary me-2">Borrador</span>
                        <span v-else>Publicada {{ formatDate(viewing.published_at) }}</span>
                    </p>
                    <p v-if="viewing.notes" class="small"><strong>Notas:</strong> {{ viewing.notes }}</p>
                    <pre class="bg-light p-3 rounded small terms-content">{{ viewing.content || '(vacío)' }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaTerminos',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            loading: false, saving: false,
            current: null, draft: null,
            history: [], acceptances: [],
            stats: { total_acceptances: 0, current_version_accept: 0, pending_acceptance: 0 },
            editor: { content: '', notes: '', require_reacceptance: false },
            accepFilters: { search: '', version: '' },
            viewing: null,
        };
    },
    computed: {
        nextVersion() {
            const max = (this.history || []).reduce((m, v) => Math.max(m, v.version_number || 0), 0);
            return max + 1;
        },
    },
    mounted() {
        this.fetch();
        this.fetchAcceptances();
    },
    methods: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/terminos/data`);
                this.current = data.current;
                this.draft   = data.draft;
                this.history = data.history || [];
                this.stats   = data.stats || this.stats;
                // Cargar el borrador si existe; si no, el contenido vigente como base
                this.editor.content = this.draft?.content || this.current?.content || '';
                this.editor.notes   = this.draft?.notes || '';
                this.editor.require_reacceptance = false;
            } catch (e) {
                console.error('[MF/Terminos]', e);
            } finally {
                this.loading = false;
            }
        },
        async fetchAcceptances(page = 1) {
            try {
                const params = { page };
                if (this.accepFilters.search) params.search = this.accepFilters.search;
                const { data } = await axios.get(`${this.baseUrl}/terminos/acceptances`, { params });
                this.acceptances = data.data || [];
            } catch (e) { /* no-op */ }
        },
        async saveDraft() {
            this.saving = true;
            try {
                await axios.post(`${this.baseUrl}/terminos/draft`, {
                    content: this.editor.content,
                    notes: this.editor.notes,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                await this.fetch();
                alert('Borrador guardado.');
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
        async publish() {
            if (!confirm(`¿Publicar nueva versión v${this.nextVersion}? Esta acción es definitiva.`)) return;
            this.saving = true;
            try {
                await axios.post(`${this.baseUrl}/terminos`, {
                    content: this.editor.content,
                    notes: this.editor.notes,
                    require_reacceptance: this.editor.require_reacceptance,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                await this.fetch();
                await this.fetchAcceptances();
                alert('Versión publicada.');
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
        async openVersion(version) {
            try {
                const { data } = await axios.get(`${this.baseUrl}/terminos/${version}`);
                this.viewing = data;
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('es-MX', { day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit' });
        },
    },
};
</script>

<style scoped>
.megafamilia-terminos tbody tr[role="button"] { cursor: pointer; }
.terms-content { white-space: pre-wrap; max-height: 60vh; overflow-y: auto; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(780px, 92vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
</style>
