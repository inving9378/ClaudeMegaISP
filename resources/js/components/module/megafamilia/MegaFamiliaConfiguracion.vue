<template>
    <div class="megafamilia-configuracion mt-3">
        <h5 class="mb-3">
            <i class="fa fa-cog me-2 text-primary"></i>
            Configuración MegaFamilia
        </h5>

        <div v-if="loading" class="text-center py-3"><div class="spinner-border text-primary"></div></div>

        <template v-else>
            <!-- Firebase / FCM -->
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                    <strong><i class="fa fa-fire me-1 text-warning"></i> Firebase / FCM</strong>
                    <span class="badge" :class="settings.firebase_server_key ? 'bg-success' : 'bg-warning text-dark'">
                        {{ settings.firebase_server_key ? '✅ Configurado' : '⚠️ Sin configurar' }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Server Key (FCM Legacy o JSON service account)</label>
                            <textarea v-model="settings.firebase_server_key" class="form-control font-monospace" rows="3"
                                      placeholder="AAAA... o JSON completo de la cuenta de servicio"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sender ID</label>
                            <input v-model="settings.firebase_sender_id" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project ID</label>
                            <input v-model="settings.firebase_project_id" class="form-control" />
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fcm-enabled" v-model="settings.fcm_enabled" />
                                <label class="form-check-label" for="fcm-enabled">Notificaciones activas</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-info" :disabled="testing" @click="testFcm">
                        <i class="fa fa-paper-plane me-1"></i> {{ testing ? 'Probando...' : 'Probar push' }}
                    </button>
                    <button class="btn btn-sm btn-primary" :disabled="saving" @click="save('firebase')">
                        <i class="fa fa-save me-1"></i> Guardar Firebase
                    </button>
                </div>
                <div v-if="fcmTestResult" class="card-body py-2 border-top small">
                    <span v-if="fcmTestResult.success" class="text-success">
                        <i class="fa fa-check me-1"></i>
                        Push enviado correctamente — sent: {{ fcmTestResult.sent || 0 }}, failed: {{ fcmTestResult.failed || 0 }}
                    </span>
                    <span v-else class="text-danger">
                        <i class="fa fa-times me-1"></i> {{ fcmTestResult.message || fcmTestResult.error || 'Falló el envío.' }}
                    </span>
                </div>
            </div>

            <!-- Configuración general -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong><i class="fa fa-sliders-h me-1"></i> Configuración general</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del servicio</label>
                            <input v-model="settings.service_name" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL de Términos y Condiciones</label>
                            <input v-model="settings.terms_url" class="form-control" placeholder="https://…" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Versión mínima Android</label>
                            <input v-model="settings.min_android_version" class="form-control" placeholder="1.0.0" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Versión mínima iOS</label>
                            <input v-model="settings.min_ios_version" class="form-control" placeholder="1.0.0" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Días de gracia tras vencimiento</label>
                            <input v-model.number="settings.grace_days_after_expiration" type="number" min="0" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allow-no-isp" v-model="settings.allow_signup_without_isp" />
                                <label class="form-check-label" for="allow-no-isp">Permitir registro sin cliente ISP</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require-otp" v-model="settings.require_otp_on_login" />
                                <label class="form-check-label" for="require-otp">Requerir OTP en login</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-primary" :disabled="saving" @click="save('general')">
                        <i class="fa fa-save me-1"></i> Guardar General
                    </button>
                </div>
            </div>

            <!-- Límites -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong><i class="fa fa-tachometer-alt me-1"></i> Límites del sistema</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <label class="form-label">Max dispositivos / licencia</label>
                            <input v-model.number="settings.max_devices_per_license" type="number" min="1" class="form-control" />
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label">Max perfiles / cuenta</label>
                            <input v-model.number="settings.max_profiles_per_account" type="number" min="1" class="form-control" />
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label">Retención eventos (días)</label>
                            <input v-model.number="settings.event_retention_days" type="number" min="7" class="form-control" />
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label">Reporte de ubicación (seg)</label>
                            <input v-model.number="settings.location_interval_seconds" type="number" min="30" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-primary" :disabled="saving" @click="save('limits')">
                        <i class="fa fa-save me-1"></i> Guardar Límites
                    </button>
                </div>
            </div>

            <!-- Integraciones -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light"><strong><i class="fa fa-plug me-1"></i> Integraciones</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <strong>MikroTik</strong>
                                    <div class="text-muted small">
                                        Estado:
                                        <span v-if="settings.mikrotik_status?.reachable === true" class="text-success">
                                            <i class="fa fa-circle"></i> Conectado
                                        </span>
                                        <span v-else-if="settings.mikrotik_status?.reachable === false" class="text-danger">
                                            <i class="fa fa-circle"></i> Sin conexión
                                        </span>
                                        <span v-else class="text-muted">No probado</span>
                                    </div>
                                </div>
                                <a :href="`${baseUrl}/mikrotik`" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-arrow-right me-1"></i> Configurar
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <strong>Notificaciones</strong>
                                    <div class="text-muted small">Envíos masivos vía FCM</div>
                                </div>
                                <a :href="`${baseUrl}/notificaciones`" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-arrow-right me-1"></i> Configurar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- App móvil -->
            <div class="card mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <strong><i class="fa fa-mobile-alt me-1"></i> App móvil</strong>
                    <div class="d-flex gap-2">
                        <a :href="`${rootUrl}/megafamilia/descargar`" target="_blank" class="btn btn-sm btn-outline-info">
                            <i class="fa fa-link me-1"></i> Ver página pública
                        </a>
                        <button class="btn btn-sm btn-primary" @click="openVersionUpload">
                            <i class="fa fa-upload me-1"></i> Subir nueva versión
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="currentApp" class="alert alert-success py-2 mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <strong>Versión vigente: v{{ currentApp.version_name }}</strong>
                                <span class="text-muted ms-2">(code {{ currentApp.version_code }})</span>
                                <span v-if="currentApp.is_mandatory" class="badge bg-danger ms-2">Obligatoria</span>
                                <div class="small mt-1">
                                    Publicada {{ formatDate(currentApp.released_at) }} ·
                                    {{ formatBytes(currentApp.file_size) }} ·
                                    {{ currentApp.download_count }} descargas
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" @click="copyDownloadLink(currentApp)">
                                <i class="fa fa-copy me-1"></i> Copiar link
                            </button>
                        </div>
                    </div>
                    <div v-else class="alert alert-warning small mb-3">
                        No hay versión activa aún. Sube una para que los usuarios puedan descargarla.
                    </div>

                    <h6 class="text-uppercase small text-muted mb-2">Historial de versiones</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Versión</th>
                                    <th>Plataforma</th>
                                    <th class="text-center">Descargas</th>
                                    <th class="text-center">Mandatory</th>
                                    <th>Fecha</th>
                                    <th class="text-center">Activa</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!versions.length"><td colspan="7" class="text-center text-muted py-3">Sin versiones registradas.</td></tr>
                                <tr v-for="v in versions" :key="v.id" :class="{ 'table-success': v.active }">
                                    <td><strong>v{{ v.version_name }}</strong> <small class="text-muted">({{ v.version_code }})</small></td>
                                    <td>{{ v.platform }}</td>
                                    <td class="text-center">{{ v.download_count }}</td>
                                    <td class="text-center">
                                        <span v-if="v.is_mandatory" class="badge bg-danger">Sí</span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td class="small">{{ formatDate(v.released_at) }}</td>
                                    <td class="text-center">
                                        <span v-if="v.active" class="badge bg-success">Activa</span>
                                        <button v-else class="btn btn-sm btn-outline-primary" @click="activateVersion(v)">
                                            Activar
                                        </button>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary" @click="copyDownloadLink(v)" title="Copiar link">
                                            <i class="fa fa-link"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger ms-1" @click="deleteVersion(v)" title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal upload nueva versión -->
            <div v-if="upload.open" class="mf-modal-overlay" @click.self="upload.open = false">
                <div class="mf-modal-panel" style="max-width:600px;">
                    <div class="mf-modal-header">
                        <h5 class="m-0">Subir nueva versión</h5>
                        <button class="btn-close" @click="upload.open = false"></button>
                    </div>
                    <div class="mf-modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plataforma</label>
                                <select v-model="upload.platform" class="form-select">
                                    <option value="android">Android</option>
                                    <option value="ios">iOS</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Versión</label>
                                <input v-model="upload.version_name" class="form-control" placeholder="0.4.0" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Code</label>
                                <input v-model.number="upload.version_code" type="number" min="1" class="form-control" placeholder="4" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Changelog</label>
                                <textarea v-model="upload.changelog" rows="3" class="form-control" placeholder="Novedades de esta versión…"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="up-mandatory" v-model="upload.is_mandatory" />
                                    <label class="form-check-label" for="up-mandatory">Actualización obligatoria</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">
                                    Archivo {{ upload.platform === 'ios' ? 'IPA' : 'APK' }}
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" :accept="upload.platform === 'ios' ? '.ipa' : '.apk'" @change="onFile" />
                                <div v-if="upload.file" class="small text-muted mt-1">
                                    {{ upload.file.name }} — {{ formatBytes(upload.file.size) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mf-modal-footer">
                        <button class="btn btn-outline-secondary" @click="upload.open = false">Cancelar</button>
                        <button class="btn btn-primary" :disabled="!canUpload || upload.uploading" @click="doUpload">
                            <i class="fa fa-upload me-1"></i>
                            <span v-if="upload.uploading">Subiendo {{ upload.progress }}%…</span>
                            <span v-else>Subir y publicar</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaConfiguracion',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            saving: false,
            testing: false,
            settings: {},
            fcmTestResult: null,
            versions: [],
            currentApp: null,
            upload: {
                open: false, uploading: false, progress: 0,
                platform: 'android', version_name: '', version_code: null,
                changelog: '', is_mandatory: false, file: null,
            },
        };
    },
    computed: {
        rootUrl() { return this.baseUrl.replace(/\/megafamilia$/, ''); },
        canUpload() {
            return this.upload.version_name && this.upload.version_code && this.upload.file;
        },
    },
    mounted() {
        this.fetch();
        this.fetchVersions();
    },
    methods: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/configuracion/get`);
                this.settings = data || {};
            } catch (e) {
                console.error('[MF/Config]', e);
            } finally {
                this.loading = false;
            }
        },
        async save(section) {
            this.saving = true;
            try {
                const fields = {
                    firebase: ['firebase_server_key','firebase_sender_id','firebase_project_id','fcm_enabled'],
                    general:  ['service_name','terms_url','min_android_version','min_ios_version','grace_days_after_expiration','allow_signup_without_isp','require_otp_on_login'],
                    limits:   ['max_devices_per_license','max_profiles_per_account','event_retention_days','location_interval_seconds'],
                };
                const payload = { section };
                for (const k of (fields[section] || [])) payload[k] = this.settings[k];
                await axios.post(`${this.baseUrl}/configuracion/update`, payload, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.flash(`Sección "${section}" guardada.`);
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally { this.saving = false; }
        },
        async testFcm() {
            this.testing = true;
            this.fcmTestResult = null;
            try {
                const { data } = await axios.post(`${this.baseUrl}/configuracion/test-fcm`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.fcmTestResult = data;
            } catch (e) {
                this.fcmTestResult = { success: false, message: e.response?.data?.message || e.message };
            } finally { this.testing = false; }
        },
        flash(msg) {
            const div = document.createElement('div');
            div.textContent = msg;
            div.style.cssText = 'position:fixed;top:80px;right:20px;background:#198754;color:#fff;padding:8px 14px;border-radius:6px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.15)';
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 2500);
        },

        // ---------- App versions ----------
        async fetchVersions() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/app-versions`);
                this.versions = data.list || [];
                this.currentApp = data.current || null;
            } catch (e) { console.error('[MF/Versions]', e); }
        },
        openVersionUpload() {
            this.upload = {
                open: true, uploading: false, progress: 0,
                platform: 'android', version_name: '', version_code: null,
                changelog: '', is_mandatory: false, file: null,
            };
        },
        onFile(e) { this.upload.file = e.target.files[0] || null; },
        async doUpload() {
            if (!this.canUpload) return;
            const fd = new FormData();
            fd.append('platform', this.upload.platform);
            fd.append('version_name', this.upload.version_name);
            fd.append('version_code', this.upload.version_code);
            fd.append('changelog', this.upload.changelog || '');
            fd.append('is_mandatory', this.upload.is_mandatory ? '1' : '0');
            fd.append('file', this.upload.file);

            this.upload.uploading = true;
            try {
                await axios.post(`${this.baseUrl}/app-versions`, fd, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (e) => {
                        if (e.total) this.upload.progress = Math.round((e.loaded * 100) / e.total);
                    },
                });
                this.upload.open = false;
                this.flash('Versión subida y publicada.');
                await this.fetchVersions();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.upload.uploading = false;
            }
        },
        async activateVersion(v) {
            if (!confirm(`¿Activar v${v.version_name} y desactivar las demás de ${v.platform}?`)) return;
            try {
                await axios.post(`${this.baseUrl}/app-versions/${v.id}/activate`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetchVersions();
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        async deleteVersion(v) {
            if (!confirm(`¿Eliminar v${v.version_name}? Esto borra el archivo y el registro.`)) return;
            try {
                await axios.delete(`${this.baseUrl}/app-versions/${v.id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetchVersions();
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        copyDownloadLink(v) {
            const url = `${this.rootUrl}/api/megafamilia/mobile/download/${v.id}`;
            navigator.clipboard?.writeText(url).then(
                () => this.flash('Link copiado: ' + url),
                () => prompt('Copia el link manualmente:', url),
            );
        },
        formatBytes(b) {
            b = Number(b) || 0;
            if (b < 1024) return `${b} B`;
            if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
            return `${(b / 1024 / 1024).toFixed(1)} MB`;
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
.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
.mf-modal-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }
</style>
