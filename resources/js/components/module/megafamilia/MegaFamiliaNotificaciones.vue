<template>
    <div class="megafamilia-notificaciones mt-3">
        <h5 class="mb-3">Notificaciones push (FCM)</h5>

        <div class="row">
            <div class="col-lg-5 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Nuevo envío</h6>
                        <div class="mb-2">
                            <label class="form-label">Título</label>
                            <input v-model="form.title" class="form-control" maxlength="80" />
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Mensaje</label>
                            <textarea v-model="form.message" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Segmento</label>
                                <select v-model="form.segment" class="form-select" @change="form.target = ''">
                                    <option value="all">Todos</option>
                                    <option value="plan">Plan</option>
                                    <option value="profile_type">Tipo de perfil</option>
                                    <option value="account">Cuenta (id)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Target</label>
                                <select v-if="form.segment === 'plan'" v-model="form.target" class="form-select">
                                    <option value="basico">Básico</option>
                                    <option value="plus">Plus</option>
                                    <option value="premium">Premium</option>
                                </select>
                                <select v-else-if="form.segment === 'profile_type'" v-model="form.target" class="form-select">
                                    <option value="nino">Niño</option>
                                    <option value="preadolescente">Preadolescente</option>
                                    <option value="adolescente">Adolescente</option>
                                </select>
                                <input v-else-if="form.segment === 'account'" v-model="form.target" class="form-control" placeholder="id cuenta" />
                                <input v-else class="form-control" disabled placeholder="—" />
                            </div>
                        </div>

                        <button class="btn btn-primary mt-3" :disabled="sending || !form.title || !form.message" @click="send">
                            <i class="fa fa-paper-plane"></i> {{ sending ? 'Enviando…' : 'Enviar push' }}
                        </button>

                        <div v-if="lastResult" class="mt-3" :class="lastResult.success ? 'text-success' : 'text-danger'">
                            <strong>{{ lastResult.success ? 'Enviado' : 'Falló' }}</strong> ·
                            target {{ lastResult.segment_size || 0 }} dispositivos
                            <span v-if="lastResult.sent !== undefined"> · FCM ok {{ lastResult.sent }} · fallaron {{ lastResult.failed }}</span>
                            <div v-if="lastResult.error" class="small">{{ lastResult.error }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-title m-0">Historial</h6>
                            <button class="btn btn-sm btn-outline-secondary" @click="fetchHistory" :disabled="loading">
                                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                            </button>
                        </div>
                        <div v-if="history.length === 0" class="text-muted small">Sin envíos previos.</div>
                        <ul v-else class="list-unstyled m-0">
                            <li v-for="ev in history" :key="ev.id" class="history-item">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ payloadOf(ev).title || '—' }}</strong>
                                    <span class="text-muted small">{{ formatDate(ev.created_at) }}</span>
                                </div>
                                <div class="small text-muted">
                                    Segmento: <code>{{ payloadOf(ev).segment }}</code>
                                    <span v-if="payloadOf(ev).target"> = {{ payloadOf(ev).target }}</span>
                                    · Tokens: {{ payloadOf(ev).segment_size || 0 }}
                                </div>
                                <div class="small">{{ payloadOf(ev).message || '' }}</div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaNotificaciones',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            form: { title: '', message: '', segment: 'all', target: '' },
            history: [], loading: false, sending: false, lastResult: null,
        };
    },
    mounted() { this.fetchHistory(); },
    methods: {
        async fetchHistory() {
            this.loading = true;
            try { const { data } = await axios.get(`${this.baseUrl}/notificaciones/history`); this.history = data.data || []; }
            catch (_) { /* silent */ }
            finally { this.loading = false; }
        },
        async send() {
            this.sending = true; this.lastResult = null;
            try {
                const { data } = await axios.post(`${this.baseUrl}/notificaciones/send`, this.form, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.lastResult = { success: data.success !== false, ...data };
                await this.fetchHistory();
            } catch (e) { this.lastResult = { success: false, error: e.response?.data?.error || e.message }; }
            finally { this.sending = false; }
        },
        payloadOf(ev) { try { return typeof ev.detail === 'string' ? JSON.parse(ev.detail) : (ev.detail || {}); } catch (_) { return {}; } },
        formatDate(ts) { return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); },
    },
};
</script>

<style scoped>
.history-item { padding: 0.5rem 0; border-bottom: 1px dashed #eef0f4; }
.history-item:last-child { border-bottom: 0; }
</style>
