<template>
    <div class="api-movil-docs mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Documentación API móvil</h5>
            <a v-if="apkUrl" :href="apkUrl" target="_blank" class="btn btn-sm btn-success">
                <i class="fa fa-download"></i> Descargar APK
            </a>
        </div>

        <div v-if="loading" class="text-muted">Cargando endpoints…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="endpoints.length === 0" class="text-muted">No se encontraron rutas /api/megafamilia/* registradas.</div>

        <div v-else class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px">Método</th>
                                <th>URL</th>
                                <th>Acción</th>
                                <th>Auth</th>
                                <th class="text-end">Copiar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e, i) in endpoints" :key="i">
                                <td><span class="badge" :class="methodBadge(e.method)">{{ e.method }}</span></td>
                                <td><code>{{ e.uri }}</code></td>
                                <td class="text-muted small">{{ shortAction(e.action) }}</td>
                                <td>
                                    <span v-if="e.auth_required" class="badge bg-primary"><i class="fa fa-lock"></i> sanctum</span>
                                    <span v-else class="badge bg-secondary">público</span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" :title="'Copiar ' + e.method + ' ' + e.uri" @click="copy(e)">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="copiedMsg" class="text-success small mt-2">{{ copiedMsg }}</div>
    </div>
</template>

<script>
export default {
    name: 'ApiMovilDocs',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { endpoints: [], apkUrl: '', loading: false, errorMsg: '', copiedMsg: '' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/docs/endpoints`);
                this.endpoints = data.endpoints || [];
                this.apkUrl = data.apk_url || '';
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        methodBadge(m) {
            return ({
                GET: 'bg-info', POST: 'bg-success', PUT: 'bg-warning text-dark',
                PATCH: 'bg-warning text-dark', DELETE: 'bg-danger',
            })[m] || 'bg-secondary';
        },
        shortAction(a) {
            if (! a) return '';
            const parts = a.split('\\');
            return parts.slice(-1)[0];
        },
        async copy(e) {
            const text = `${e.method} ${e.uri}`;
            try {
                await navigator.clipboard.writeText(text);
                this.copiedMsg = 'Copiado: ' + text;
                setTimeout(() => { this.copiedMsg = ''; }, 1500);
            } catch (_) { this.copiedMsg = 'No se pudo copiar al portapapeles'; }
        },
    },
};
</script>
