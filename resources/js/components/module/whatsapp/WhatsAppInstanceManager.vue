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
                    <div><span>Número:</span> {{ ins.phone_number || '—' }}</div>
                    <div v-if="ins.default_instance" class="wim-default-badge">
                        <i class="bi bi-star-fill"></i> Default
                    </div>
                </div>

                <div v-if="catalog.length" class="wim-functions">
                    <div class="wim-functions-title">Funciones de esta línea</div>
                    <div class="wim-func-list">
                        <button
                            v-for="fn in catalog" :key="fn.id"
                            class="wim-func"
                            :class="{ on: hasFunction(ins, fn.id) }"
                            :disabled="funcBusy"
                            @click="toggleFunction(ins, fn)"
                            :title="fn.exclusive ? 'Exclusiva: una sola línea' : 'Compartida: puede estar en varias'"
                        >
                            <i class="bi" :class="hasFunction(ins, fn.id) ? 'bi-check-square-fill' : 'bi-square'"></i>
                            {{ fn.name }}
                            <small class="wim-func-tag" :class="fn.exclusive ? 'ex' : 'sh'">{{ fn.exclusive ? 'exclusiva' : 'compartida' }}</small>
                        </button>
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
                        v-if="ins.status === 'connected'"
                        class="wim-btn"
                        @click="askDisconnect(ins)"
                        title="Cerrar la sesión de WhatsApp en Evolution (logout). Se reconecta por QR."
                    >
                        <i class="bi bi-power"></i> Desconectar
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
                    <label>Etiqueta / ID interno (opcional) <input v-model="form.instance_id" placeholder="Se usa el slug si lo dejas vacío" /></label>
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

        <!-- Modal: mover función exclusiva -->
        <div v-if="moveModal" class="wim-modal-backdrop" @click.self="moveModal = null">
            <div class="wim-modal">
                <h4>Mover función exclusiva</h4>
                <p class="wim-modal-text">
                    «<strong>{{ moveModal.fn.name }}</strong>» es exclusiva y hoy está en
                    «<strong>{{ moveModal.fromName }}</strong>». ¿Moverla a
                    «<strong>{{ moveModal.to.name }}</strong>»? Se quitará de la otra línea.
                </p>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="moveModal = null">Cancelar</button>
                    <button class="wim-btn primary" @click="confirmMove" :disabled="funcBusy">Mover aquí</button>
                </div>
            </div>
        </div>

        <!-- Modal: confirmar que la función quede sin línea (no bloqueante) -->
        <div v-if="orphanModal" class="wim-modal-backdrop" @click.self="orphanModal = null">
            <div class="wim-modal">
                <h4>Quitar la última línea</h4>
                <p class="wim-modal-text">
                    «<strong>{{ orphanModal.fn.name }}</strong>» quedará <strong>sin ninguna línea</strong>
                    atendiéndola. ¿Continuar?
                </p>
                <div v-if="otherLines(orphanModal.from).length" class="wim-orphan-alt">
                    <div class="wim-orphan-alt-title">o muévela a otra línea:</div>
                    <label class="wim-reassign-label">
                        <select v-model="reassignTarget">
                            <option :value="null" disabled>Elige una línea…</option>
                            <option v-for="l in otherLines(orphanModal.from)" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </label>
                    <div v-if="reassignError" class="wim-error">{{ reassignError }}</div>
                    <button class="wim-btn" :disabled="!reassignTarget || funcBusy" @click="confirmReassignFromOrphan">
                        Mover en vez de quitar
                    </button>
                </div>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="orphanModal = null">Cancelar</button>
                    <button class="wim-btn danger" @click="confirmOrphan" :disabled="funcBusy">Quitar de todos modos</button>
                </div>
            </div>
        </div>

        <!-- Modal AVISO (no bloqueante): eliminar línea que es dueña única de funciones -->
        <div v-if="warnDeleteModal" class="wim-modal-backdrop" @click.self="warnDeleteModal = null">
            <div class="wim-modal">
                <h4>Estas funciones quedarán sin línea</h4>
                <p class="wim-modal-text">
                    Al eliminar «<strong>{{ warnDeleteModal.ins.name }}</strong>», estas funciones quedarán
                    <strong>sin ninguna línea</strong>: <strong>{{ warnDeleteModal.functions.join(', ') }}</strong>.
                    <template v-if="isProduction(warnDeleteModal.ins)"><br>⚠️ Además es el número de <strong>PRODUCCIÓN</strong>.</template>
                    ¿Eliminar de todos modos?
                </p>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="warnDeleteModal = null">Cancelar</button>
                    <button class="wim-btn danger" @click="confirmWarnDelete" :disabled="funcBusy">Eliminar de todos modos</button>
                </div>
            </div>
        </div>

        <!-- Modal FUERTE: desconectar (logout real). Producción exige escribir el nombre. -->
        <div v-if="disconnectModal" class="wim-modal-backdrop" @click.self="disconnectModal = null">
            <div class="wim-modal">
                <h4>⚠️ Desconectar sesión de WhatsApp</h4>
                <p class="wim-modal-text">
                    Vas a <strong>cerrar la sesión</strong> de «<strong>{{ disconnectModal.ins.name }}</strong>»
                    ({{ disconnectModal.ins.phone_number || disconnectModal.ins.instance_id }}) en Evolution.
                    <template v-if="isProduction(disconnectModal.ins)">
                        <br><br><strong>Dejarán de llegar comprobantes y el bot dejará de responder</strong>
                        hasta que se reconecte escaneando el QR de nuevo.
                    </template>
                    <template v-else>
                        <br>La línea quedará desconectada y se reconecta por QR.
                    </template>
                </p>
                <div v-if="isProduction(disconnectModal.ins)" class="wim-confirm-type">
                    <div>Para confirmar, escribe el nombre de la instancia: <code>{{ disconnectModal.ins.instance_id }}</code></div>
                    <input v-model="disconnectModal.typed" placeholder="Escribe el nombre exacto" />
                </div>
                <div v-if="disconnectError" class="wim-error">{{ disconnectError }}</div>
                <div class="wim-modal-actions">
                    <button class="wim-btn" @click="disconnectModal = null">Cancelar</button>
                    <button
                        class="wim-btn danger"
                        :disabled="funcBusy || (isProduction(disconnectModal.ins) && disconnectModal.typed !== disconnectModal.ins.instance_id)"
                        @click="confirmDisconnect"
                    >Desconectar</button>
                </div>
            </div>
        </div>

        <div v-if="funcToast" class="wim-toast" :class="funcToast.type">{{ funcToast.msg }}</div>
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
            // Funciones por línea (Fase 3)
            catalog: [],        // funciones activas para los checks
            funcBusy: false,
            moveModal: null,    // { fn, to, fromName }
            orphanModal: null,  // { fn, from } — confirmar que la función quede sin línea
            reassignTarget: null,
            reassignError: '',
            warnDeleteModal: null, // { ins, functions:[names] } — aviso, NO bloqueo
            disconnectModal: null, // { ins, typed } — logout real (producción exige teclear nombre)
            disconnectError: '',
            funcToast: null,
            form: {
                name: '',
                slug: '',
                instance_id: '',
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
        // Mapa function_id -> [instance ids que la tienen], desde las funciones cargadas.
        ownership() {
            const map = {};
            this.instances.forEach(ins => (ins.functions || []).forEach(f => {
                (map[f.id] = map[f.id] || []).push(ins.id);
            }));
            return map;
        },
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

        // ── Funciones por línea (Fase 3) ─────────────────────────────────────────
        async loadCatalog() {
            try {
                const { data } = await axios.get('/whatsapp/api/instances/functions-catalog');
                this.catalog = Array.isArray(data) ? data : [];
            } catch (e) {
                this.catalog = [];
            }
        },
        hasFunction(ins, fnId) {
            return (ins.functions || []).some(f => f.id === fnId);
        },
        otherLines(ins) {
            return this.instances.filter(i => i.id !== ins.id);
        },
        soleOwnedFunctions(ins) {
            return (ins.functions || [])
                .filter(f => (this.ownership[f.id] || []).length <= 1)
                .map(f => f.name);
        },
        funcErr(e) {
            return e.response?.data?.message
                || Object.values(e.response?.data?.errors || {}).flat().join(', ')
                || 'No se pudo completar la operación.';
        },
        showFuncToast(msg, type) {
            this.funcToast = { msg, type };
            setTimeout(() => { this.funcToast = null; }, 3800);
        },
        async toggleFunction(ins, fn) {
            if (this.funcBusy) return;
            if (this.hasFunction(ins, fn.id)) {
                // Quitar: si es la única línea → CONFIRMAR que la función quede sin línea (no bloqueo).
                if ((this.ownership[fn.id] || []).length <= 1) {
                    this.reassignTarget = null;
                    this.reassignError = '';
                    this.orphanModal = { fn, from: ins };
                    return;
                }
                await this.doUnassign(ins, fn);
            } else {
                // Asignar: exclusiva ya en otra línea → confirmar el movimiento.
                if (fn.exclusive) {
                    const otherId = (this.ownership[fn.id] || []).find(id => id !== ins.id);
                    if (otherId) {
                        const fromName = (this.instances.find(i => i.id === otherId) || {}).name || 'otra línea';
                        this.moveModal = { fn, to: ins, fromName };
                        return;
                    }
                }
                await this.doAssign(ins, fn);
            }
        },
        async doAssign(ins, fn) {
            this.funcBusy = true;
            try {
                const { data } = await axios.post(`/whatsapp/api/instances/${ins.id}/functions`, { function_id: fn.id });
                await this.load();
                if (data.moved) this.showFuncToast(`«${fn.name}» se movió a «${ins.name}»`, 'ok');
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
            }
        },
        async doUnassign(ins, fn) {
            this.funcBusy = true;
            try {
                await axios.delete(`/whatsapp/api/instances/${ins.id}/functions/${fn.id}`);
                await this.load();
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
            }
        },
        async confirmMove() {
            const { fn, to } = this.moveModal;
            this.moveModal = null;
            await this.doAssign(to, fn);
        },
        // Confirmar dejar la función sin línea (regla relajada).
        async confirmOrphan() {
            const { fn, from } = this.orphanModal;
            this.orphanModal = null;
            await this.doUnassign(from, fn);
        },
        // Opción secundaria del modal huérfana: mover a otra línea en vez de quitar.
        async confirmReassignFromOrphan() {
            if (!this.reassignTarget) return;
            const { fn, from } = this.orphanModal;
            this.funcBusy = true;
            this.reassignError = '';
            try {
                await axios.post(`/whatsapp/api/instances/${from.id}/functions/${fn.id}/reassign`, { to_instance_id: this.reassignTarget });
                this.orphanModal = null;
                await this.load();
                this.showFuncToast(`«${fn.name}» reasignada`, 'ok');
            } catch (e) {
                this.reassignError = this.funcErr(e);
            } finally {
                this.funcBusy = false;
            }
        },
        // Desconectar (logout real en Evolution) — distinto de Eliminar (fila local).
        askDisconnect(ins) {
            if (this.isProduction(ins)) {
                this.disconnectError = '';
                this.disconnectModal = { ins, typed: '' };
                return;
            }
            if (!confirm(`¿Desconectar «${ins.name}»? Cierra la sesión de WhatsApp; se reconecta por QR.`)) return;
            this.doDisconnect(ins);
        },
        async confirmDisconnect() {
            const ins = this.disconnectModal.ins;
            if (this.isProduction(ins) && this.disconnectModal.typed !== ins.instance_id) return;
            await this.doDisconnect(ins);
        },
        async doDisconnect(ins) {
            this.funcBusy = true;
            this.disconnectError = '';
            try {
                const { data } = await axios.post(`/whatsapp/api/instances/${ins.id}/disconnect`);
                this.disconnectModal = null;
                await this.load();
                this.showFuncToast(`«${ins.name}» desconectada — reconecta por QR`, 'ok');
            } catch (e) {
                this.disconnectError = this.funcErr(e);
                if (!this.disconnectModal) this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
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
            // Regla relajada: si es la única línea de funciones → AVISO (no bloqueo).
            const sole = this.soleOwnedFunctions(ins);
            if (sole.length) {
                this.warnDeleteModal = { ins, functions: sole };
                return;
            }
            const msg = this.isProduction(ins)
                ? `⚠️ Esto afecta el WhatsApp que usan conciliación y el bot.\n\n"${ins.name}" es el número de PRODUCCIÓN. Eliminarlo del panel quita su registro (no toca Evolution, pero perderías su seguimiento aquí).\n\n¿Seguro que quieres continuar?`
                : `¿Eliminar la instancia "${ins.name}"?`;
            if (!confirm(msg)) return;
            await this.proceedDelete(ins);
        },
        async confirmWarnDelete() {
            const ins = this.warnDeleteModal.ins;
            this.warnDeleteModal = null;
            await this.proceedDelete(ins);
        },
        async proceedDelete(ins) {
            try {
                await axios.delete(`/whatsapp/api/instances/${ins.id}`);
                await this.load();
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            }
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
        this.loadCatalog();
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

/* Modo oscuro: estilo de línea inferior (texto blanco + color de estado abajo, sin fondo).
   Solo dark → el modo claro conserva la pastilla de color de arriba. */
[data-layout-mode="dark"] .wim-status {
    background: transparent !important;
    border-radius: 0;
    border-bottom: 2px solid var(--text-secondary);
    color: #fff !important;
    padding: 2px 0;
}
[data-layout-mode="dark"] .wim-status.connected { border-bottom-color: var(--success); }
[data-layout-mode="dark"] .wim-status.disconnected { border-bottom-color: var(--danger); }
[data-layout-mode="dark"] .wim-status.pending { border-bottom-color: var(--warning); }
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

/* Funciones por línea (Fase 3) */
.wim-functions {
    margin-top: 10px; padding-top: 10px;
    border-top: 1px dashed var(--border-default);
}
.wim-functions-title {
    font-size: 11px; text-transform: uppercase; letter-spacing: .4px;
    color: var(--text-secondary); margin-bottom: 6px;
}
.wim-func-list { display: flex; flex-wrap: wrap; gap: 6px; }
.wim-func {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--bg-hover); color: var(--text-secondary);
    border: 1px solid var(--border-default); border-radius: 14px;
    padding: 4px 10px; font-size: 12px; cursor: pointer;
}
.wim-func.on { background: var(--success); color: #fff; border-color: transparent; }
.wim-func:disabled { opacity: .6; cursor: wait; }
.wim-func-tag {
    font-size: 10px; padding: 0 5px; border-radius: 8px; font-weight: 600;
}
.wim-func-tag.ex { background: var(--warning); color: #1f2937; }
.wim-func-tag.sh { background: var(--bg-primary); color: var(--text-secondary); }
.wim-func.on .wim-func-tag.sh { color: var(--text-secondary); }

.wim-modal-text { color: var(--text-primary); font-size: 13.5px; line-height: 1.5; }
.wim-reassign-label {
    display: flex; flex-direction: column; gap: 6px;
    font-size: 12px; color: var(--text-secondary); margin: 12px 0;
}
.wim-reassign-label select {
    background: var(--bg-primary); color: var(--text-primary);
    border: 1px solid var(--border-default); border-radius: 4px; padding: 7px 10px; font-size: 13px;
}
.wim-orphan-alt {
    margin: 12px 0; padding: 10px; border-radius: 6px;
    background: var(--bg-hover); border: 1px solid var(--border-default);
}
.wim-orphan-alt-title { font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; }
.wim-confirm-type { margin: 12px 0; font-size: 12.5px; color: var(--text-secondary); }
.wim-confirm-type code {
    background: var(--bg-primary); color: var(--success); padding: 1px 6px; border-radius: 3px;
}
.wim-confirm-type input {
    display: block; width: 100%; margin-top: 6px;
    background: var(--bg-primary); color: var(--text-primary);
    border: 1px solid var(--danger); border-radius: 4px; padding: 7px 10px; font-size: 13px;
}
.wim-toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600;
    z-index: 10001; box-shadow: var(--shadow-card); max-width: 90%; text-align: center;
}
.wim-toast.ok { background: var(--success); color: #fff; }
.wim-toast.error { background: var(--danger); color: #fff; }

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
