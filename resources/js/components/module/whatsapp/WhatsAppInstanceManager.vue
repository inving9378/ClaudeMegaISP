<template>
    <div class="wim-shell">
        <div v-if="fakeMode" class="wim-fake-banner">
            <i class="bi bi-cone-striped"></i>
            Modo desarrollo — Evolution API simulada. El QR mostrado es un placeholder.
        </div>

        <div class="wim-header">
            <h3><i class="bi bi-whatsapp"></i> Instancias WhatsApp</h3>
            <button class="wim-btn primary" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Nueva instancia
            </button>
        </div>

        <div v-if="loading" class="wim-empty">Cargando…</div>
        <div v-else-if="!instances.length" class="wim-empty">
            No hay instancias configuradas. Crea la primera para parear un número WhatsApp.
        </div>

        <div v-else class="wim-grid">
            <div v-for="ins in instances" :key="ins.id" class="wim-card">
                <div class="wim-card-head">
                    <strong>{{ ins.name }}</strong>
                    <span class="wim-status" :class="statusClass(ins.status)">
                        {{ statusLabel(ins.status) }}
                    </span>
                </div>
                <div v-if="isProduction(ins)" class="wim-prod-badge">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    En uso — producción · ventas · bot · conciliación
                </div>
                <div class="wim-card-body">
                    <div><span>Slug:</span> <code>{{ ins.slug }}</code></div>
                    <div><span>Instance ID:</span> <code>{{ ins.instance_id }}</code></div>
                    <div v-if="ins.phone_number">
                        <span>Número:</span> {{ ins.phone_number }}
                    </div>
                    <div v-if="ins.default_instance" class="wim-default-badge">
                        <i class="bi bi-star-fill"></i> Default
                    </div>
                </div>
                <div class="wim-card-actions">
                    <button class="wim-btn" @click="showQr(ins)">
                        <i class="bi bi-qr-code"></i>
                        {{ ins.status === 'connected' ? 'Reconectar' : 'Conectar / Ver QR' }}
                    </button>
                    <button
                        class="wim-btn"
                        @click="syncStatus(ins)"
                        :disabled="syncing[ins.id]"
                        title="Refrescar estado real desde Evolution API"
                    >
                        <i class="bi bi-arrow-clockwise" :class="{ 'wim-spin': syncing[ins.id] }"></i>
                        {{ syncing[ins.id] ? 'Sincronizando…' : 'Sincronizar' }}
                    </button>
                    <button
                        class="wim-btn"
                        :class="isProduction(ins) ? 'muted' : 'danger'"
                        @click="remove(ins)"
                        :title="isProduction(ins) ? 'Instancia de producción — eliminar con precaución' : 'Eliminar instancia'"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal crear instancia -->
        <div v-if="showCreate" class="wim-modal-backdrop" @click.self="showCreate = false">
            <div class="wim-modal">
                <h4>Nueva instancia WhatsApp</h4>
                <div class="wim-form">
                    <label>Nombre <input v-model="form.name" @input="autoSlug" placeholder="Ventas, Soporte..." /></label>
                    <label>Slug (URL) <input v-model="form.slug" placeholder="ventas-mx" /></label>
                    <label>Instance ID (en Evolution API) <input v-model="form.instance_id" placeholder="meganet-ventas" /></label>
                    <label>API URL <input v-model="form.api_url" placeholder="http://localhost/evolution" /></label>
                    <label>API Key <input v-model="form.api_key" type="password" placeholder="apikey de Evolution" /></label>
                    <label>Número WhatsApp (display) <input v-model="form.phone_number" placeholder="+52 55 1234 5678" /></label>
                    <label class="wim-checkbox">
                        <input type="checkbox" v-model="form.default_instance" />
                        Marcar como instancia por defecto
                    </label>
                </div>
                <div v-if="formError" class="wim-error">{{ formError }}</div>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="showCreate = false">Cancelar</button>
                    <button class="wim-btn primary" @click="create" :disabled="saving">
                        {{ saving ? 'Guardando…' : 'Crear' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal QR -->
        <div v-if="qrInstance" class="wim-modal-backdrop" @click.self="closeQr">
            <div class="wim-modal qr">
                <h4>Pareo WhatsApp — {{ qrInstance.name }}</h4>
                <div v-if="qrLoading" class="wim-empty">Solicitando QR…</div>
                <div v-else-if="qrError" class="wim-error">{{ qrError }}</div>
                <div v-else-if="qrImage" class="wim-qr-block">
                    <img :src="qrImageSrc" alt="QR WhatsApp" class="wim-qr-image" />
                    <div class="wim-qr-meta">
                        <div>Expira en: <strong>{{ qrCountdown }}s</strong></div>
                        <div v-if="qrPairingCode">
                            Código de pareo:
                            <code class="wim-pairing">{{ qrPairingCode }}</code>
                        </div>
                        <div class="wim-qr-status">
                            Estado: <strong>{{ statusLabel(qrInstance.status) }}</strong>
                        </div>
                    </div>
                    <p class="wim-qr-help">
                        Abre WhatsApp en tu teléfono → Menú → Dispositivos vinculados → Vincular un dispositivo.
                    </p>
                </div>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="closeQr">Cerrar</button>
                    <button class="wim-btn primary" @click="refreshQr">
                        <i class="bi bi-arrow-clockwise"></i> Refrescar QR
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'WhatsAppInstanceManager',

    props: {
        fakeMode: { type: Boolean, default: false },
    },

    data() {
        return {
            instances: [],
            loading: false,
            showCreate: false,
            saving: false,
            syncing: {},        // { instanceId: true } mientras Sincronizar está en curso
            formError: '',
            form: {
                name: '',
                slug: '',
                instance_id: '',
                api_url: 'http://localhost/evolution',
                api_key: '',
                phone_number: '',
                default_instance: false,
            },

            qrInstance: null,
            qrLoading: false,
            qrError: '',
            qrImage: '',
            qrPairingCode: '',
            qrExpiresAt: null,
            qrCountdown: 0,
            qrCountdownInterval: null,
            qrStatusInterval: null,
        };
    },

    computed: {
        qrImageSrc() {
            if (!this.qrImage) return '';
            if (this.qrImage.startsWith('data:image')) return this.qrImage;
            if (this.qrImage.startsWith('http')) return this.qrImage;
            // Si es solo base64 puro
            return 'data:image/png;base64,' + this.qrImage;
        },
    },

    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/whatsapp/api/instances');
                this.instances = Array.isArray(data) ? data : [];
            } catch (e) {
                this.instances = [];
            } finally {
                this.loading = false;
            }
        },

        statusClass(status) {
            return {
                connected: 'connected',
                qr_pending: 'pending',
                disconnected: 'disconnected',
            }[status] || 'disconnected';
        },

        statusLabel(status) {
            return {
                connected: 'Conectado',
                qr_pending: 'Esperando QR',
                disconnected: 'Desconectado',
            }[status] || 'Desconectado';
        },

        openCreate() {
            this.formError = '';
            this.form = {
                name: '',
                slug: '',
                instance_id: '',
                api_url: 'http://localhost/evolution',
                api_key: '',
                phone_number: '',
                default_instance: false,
            };
            this.showCreate = true;
        },

        autoSlug() {
            this.form.slug = this.form.name
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },

        async create() {
            this.formError = '';
            this.saving = true;
            try {
                await axios.post('/whatsapp/api/instances', this.form);
                this.showCreate = false;
                await this.load();
            } catch (e) {
                this.formError = e.response?.data?.message
                    || Object.values(e.response?.data?.errors || {}).flat().join(', ')
                    || 'Error al crear la instancia';
            } finally {
                this.saving = false;
            }
        },

        async syncStatus(ins) {
            this.syncing = { ...this.syncing, [ins.id]: true };
            try {
                const { data } = await axios.get(`/whatsapp/api/instances/${ins.id}/status`);
                // Actualizar inline sin recargar la lista entera
                ins.status = data.status;
            } catch (e) {
                alert('No se pudo sincronizar: ' + (e.response?.data?.message || e.message));
            } finally {
                this.syncing = { ...this.syncing, [ins.id]: false };
            }
        },

        // La instancia de PRODUCCIÓN (meganet-ventas) es la que usan ventas, el bot y la
        // conciliación. Se identifica por slug/instance_id — se protege visualmente y con
        // una confirmación reforzada para que nadie la borre por reflejo.
        isProduction(ins) {
            return ins.slug === 'meganet-ventas' || ins.instance_id === 'meganet-ventas';
        },

        async remove(ins) {
            const msg = this.isProduction(ins)
                ? `⚠️ Esto afecta el WhatsApp que usan conciliación y el bot.\n\n"${ins.name}" es el número de PRODUCCIÓN. Eliminarlo del panel quita su registro (no toca Evolution, pero perderías su seguimiento aquí).\n\n¿Seguro que quieres continuar?`
                : `¿Eliminar la instancia "${ins.name}"?`;
            if (!confirm(msg)) return;
            try {
                await axios.delete(`/whatsapp/api/instances/${ins.id}`);
                await this.load();
            } catch {}
        },

        // Refleja el estado real (open/close) de cada instancia consultando Evolution,
        // sin esperar el clic manual en "Sincronizar".
        autoSyncAll() {
            this.instances.forEach(ins => this.syncStatus(ins));
        },

        async showQr(ins) {
            this.qrInstance = ins;
            this.qrError = '';
            this.qrImage = '';
            this.qrPairingCode = '';
            await this.fetchQr();
            this.startQrPolling();
        },

        async fetchQr() {
            this.qrLoading = true;
            try {
                const { data } = await axios.get(`/whatsapp/api/instances/${this.qrInstance.id}/qr`);
                this.qrImage = data.qrcode || data.base64 || data.code || '';
                this.qrPairingCode = data.pairingCode || '';
                this.qrExpiresAt = Date.now() + 120000;
                this.startCountdown();
            } catch (e) {
                this.qrError = 'No se pudo obtener el QR: ' + (e.response?.data?.message || e.message);
            } finally {
                this.qrLoading = false;
            }
        },

        async refreshQr() {
            await this.fetchQr();
        },

        startCountdown() {
            clearInterval(this.qrCountdownInterval);
            this.qrCountdownInterval = setInterval(() => {
                const left = Math.max(0, Math.round((this.qrExpiresAt - Date.now()) / 1000));
                this.qrCountdown = left;
                if (left === 0) clearInterval(this.qrCountdownInterval);
            }, 1000);
        },

        startQrPolling() {
            clearInterval(this.qrStatusInterval);
            this.qrStatusInterval = setInterval(async () => {
                if (!this.qrInstance) return;
                try {
                    const { data } = await axios.get(`/whatsapp/api/instances/${this.qrInstance.id}/status`);
                    this.qrInstance.status = data.status;
                    const localIns = this.instances.find(i => i.id === this.qrInstance.id);
                    if (localIns) localIns.status = data.status;
                    if (data.status === 'connected') {
                        clearInterval(this.qrStatusInterval);
                        setTimeout(() => this.closeQr(), 1500);
                    }
                } catch {}
            }, 3000);
        },

        closeQr() {
            clearInterval(this.qrCountdownInterval);
            clearInterval(this.qrStatusInterval);
            this.qrInstance = null;
            this.qrImage = '';
            this.load();
        },
    },

    async mounted() {
        await this.load();
        this.autoSyncAll();
    },

    beforeUnmount() {
        clearInterval(this.qrCountdownInterval);
        clearInterval(this.qrStatusInterval);
    },
};
</script>

<style scoped>
.wim-shell {
    --wim-brand: #25d366; /* verde de marca WhatsApp — constante, legible en claro y oscuro */
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    color: var(--text-primary);
}
.wim-fake-banner {
    background: var(--warning);
    color: #1f2937;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 6px;
    margin-bottom: 16px;
}
.wim-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.wim-header h3 { margin: 0; color: var(--wim-brand); }
.wim-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 14px;
}
.wim-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-default);
    border-radius: 8px;
    padding: 14px;
    box-shadow: var(--shadow-card);
}
.wim-card-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
}
.wim-status {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.wim-status.connected { background: var(--success); color: #fff; }
.wim-status.disconnected { background: var(--danger); color: #fff; }
.wim-status.pending { background: var(--warning); color: #1f2937; }
.wim-card-body {
    font-size: 13px;
    color: var(--text-secondary);
}
.wim-card-body div { margin-bottom: 4px; }
.wim-card-body span { color: var(--text-secondary); margin-right: 6px; }
.wim-card-body code {
    background: var(--bg-primary);
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 12px;
    color: var(--success);
}
.wim-default-badge {
    color: var(--warning);
    margin-top: 4px;
    font-size: 12px;
}
.wim-prod-badge {
    display: flex; align-items: center; gap: 8px;
    background: var(--warning); color: #1f2937;
    font-weight: 700; font-size: 12.5px;
    padding: 7px 12px; border-radius: 6px;
    margin-bottom: 10px;
    border: 1px solid rgba(0, 0, 0, 0.18);
}
.wim-card-actions {
    display: flex; gap: 6px; margin-top: 12px;
}
.wim-btn {
    background: var(--bg-hover);
    color: var(--text-primary);
    border: 1px solid var(--border-default);
    border-radius: 6px;
    padding: 7px 12px;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.wim-btn.primary { background: var(--wim-brand); color: #06251a; border-color: var(--wim-brand); font-weight: 600; }
.wim-btn.danger { background: var(--bg-hover); color: var(--danger); border-color: var(--danger); }
.wim-btn.muted { background: var(--bg-secondary); color: var(--text-secondary); border-color: var(--border-default); opacity: .6; }
.wim-btn.muted:hover { opacity: .9; }
.wim-btn:disabled { opacity: .5; cursor: not-allowed; }

.wim-empty {
    color: var(--text-secondary);
    text-align: center;
    padding: 32px;
    background: var(--bg-surface);
    border-radius: 8px;
}

.wim-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.6);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
}
.wim-modal {
    background: var(--bg-surface);
    padding: 20px;
    border-radius: 8px;
    max-width: 480px;
    width: 90%;
    color: var(--text-primary);
    box-shadow: var(--shadow-card);
}
.wim-modal h4 { margin-top: 0; color: var(--wim-brand); }
.wim-modal.qr { max-width: 420px; text-align: center; }
.wim-form {
    display: flex; flex-direction: column; gap: 10px;
    margin: 14px 0;
}
.wim-form label {
    display: flex; flex-direction: column;
    font-size: 12px; color: var(--text-secondary);
    gap: 4px;
}
.wim-form input {
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border-default);
    border-radius: 4px;
    padding: 7px 10px;
    font-size: 13px;
}
.wim-checkbox { flex-direction: row !important; align-items: center; gap: 8px; }
.wim-checkbox input { width: auto; }
.wim-error {
    background: var(--bg-hover);
    color: var(--danger);
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    margin-bottom: 10px;
}
.wim-modal-actions {
    display: flex; justify-content: flex-end; gap: 8px;
    margin-top: 14px;
}
.wim-qr-block {
    display: flex; flex-direction: column; align-items: center; gap: 14px;
    margin: 16px 0;
}
.wim-qr-image {
    width: 256px; height: 256px;
    background: #fff;
    padding: 8px;
    border-radius: 8px;
}
.wim-qr-meta { text-align: center; color: var(--text-secondary); font-size: 13px; }
.wim-qr-meta div { margin: 4px 0; }
.wim-pairing {
    background: var(--bg-primary);
    color: var(--success);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 16px;
    letter-spacing: 2px;
}
.wim-qr-help {
    font-size: 12px;
    color: var(--text-secondary);
    margin: 0;
}
.wim-qr-status {
    margin-top: 8px;
}
.wim-spin {
    display: inline-block;
    animation: wim-spin 1s linear infinite;
}
@keyframes wim-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>
