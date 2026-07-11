<template>
  <div class="integrations-hub">

    <!-- Header -->
    <div class="hub-header">
      <div class="hub-header-left">
        <div class="hub-title-row">
          <div class="hub-icon-wrap">
            <i class="bi bi-plugin"></i>
          </div>
          <div>
            <h4 class="hub-title">Integration Hub</h4>
            <p class="hub-subtitle">Gestión centralizada de claves API y conexiones externas</p>
          </div>
        </div>
      </div>
      <div class="hub-header-right">
        <div v-if="!loading" class="hub-stats">
          <div class="hub-stat">
            <span class="hub-stat-num">{{ integrations.filter(i => i.active).length }}</span>
            <span class="hub-stat-label">activas</span>
          </div>
          <div class="hub-stat-divider"></div>
          <div class="hub-stat">
            <span class="hub-stat-num text-success">{{ integrations.filter(i => i.last_validation_status === 'valid').length }}</span>
            <span class="hub-stat-label">válidas</span>
          </div>
          <div class="hub-stat-divider"></div>
          <div class="hub-stat">
            <span class="hub-stat-num text-warning">{{ integrations.filter(i => i.has_key && i.last_validation_status !== 'valid').length }}</span>
            <span class="hub-stat-label">sin verificar</span>
          </div>
        </div>
        <button v-if="can('manage-integrations')" class="btn btn-primary btn-sm" @click="openCreate()">
          <i class="bi bi-plus-lg me-1"></i>Nueva integración
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="hub-loading">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-3 text-muted">Cargando integraciones…</p>
    </div>

    <!-- Provider grid -->
    <div v-else class="provider-grid">
      <div
        v-for="provider in providers"
        :key="provider.id"
        class="provider-card"
        :class="providerCardClass(provider)"
      >
        <!-- Card header -->
        <div class="provider-card-header">
          <div class="provider-logo" :style="{ background: providerColor(provider.id) }">
            <i :class="providerIcon(provider.id)"></i>
          </div>
          <div class="provider-info">
            <div class="provider-name">{{ provider.name }}</div>
            <div class="provider-desc">{{ provider.description }}</div>
          </div>
          <div class="provider-header-actions">
            <a v-if="provider.docs_url" :href="provider.docs_url" target="_blank"
               class="btn btn-link btn-sm p-0 text-muted" title="Documentación">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </div>
        </div>

        <!-- No integrations empty state -->
        <div v-if="integrationsFor(provider.id).length === 0" class="provider-empty">
          <i class="bi bi-key-fill text-muted"></i>
          <span class="ms-2 text-muted small">Sin configurar</span>
          <button v-if="can('manage-integrations')" class="btn btn-link btn-sm p-0 ms-2" @click="openCreate(provider.id)">
            Configurar ahora
          </button>
        </div>

        <!-- Integration rows -->
        <div
          v-for="item in integrationsFor(provider.id)"
          :key="item.id"
          class="integration-row"
          :class="{ 'integration-row--inactive': !item.active }"
        >
          <!-- Status indicator -->
          <div class="int-status-dot" :class="statusDotClass(item)" :title="statusLabel(item)"></div>

          <!-- Main info -->
          <div class="int-info">
            <div class="int-name">
              {{ item.name }}
              <span v-if="item.is_default_for_provider" class="badge bg-primary-subtle text-primary ms-1">
                <i class="bi bi-star-fill me-1" style="font-size:0.6rem"></i>Principal
              </span>
              <span v-if="!item.active" class="badge bg-secondary-subtle text-secondary ms-1">Inactiva</span>
            </div>
            <div class="int-meta">
              <code class="int-key-preview">{{ item.has_key ? item.key_preview : '— sin key —' }}</code>
              <span v-if="item.last_validated_at" class="int-validated text-muted">
                <i class="bi bi-clock me-1"></i>{{ timeAgo(item.last_validated_at) }}
              </span>
            </div>
          </div>

          <!-- Validation badge -->
          <div class="int-badge">
            <span class="badge" :class="statusBadgeClass(item)">
              <i :class="statusBadgeIcon(item)" class="me-1"></i>{{ statusLabel(item) }}
            </span>
          </div>

          <!-- Actions -->
          <div class="int-actions">
            <button
              v-if="can('validate-integrations') && item.has_key"
              class="btn btn-icon" :class="validating[item.id] ? 'disabled' : 'btn-icon-success'"
              @click="validateItem(item)" :title="validating[item.id] ? 'Validando…' : 'Validar key'"
            >
              <span v-if="validating[item.id]" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bi bi-check2-circle"></i>
            </button>
            <button
              v-if="can('manage-integrations') && !item.is_default_for_provider"
              class="btn btn-icon btn-icon-warning"
              @click="setDefault(item)" title="Marcar como principal"
            ><i class="bi bi-star"></i></button>
            <button
              v-if="can('rotate-integration-keys') && item.has_key"
              class="btn btn-icon btn-icon-secondary"
              @click="openRotate(item)" title="Rotar key"
            ><i class="bi bi-arrow-repeat"></i></button>
            <button
              v-if="can('manage-integrations')"
              class="btn btn-icon btn-icon-secondary"
              @click="openEdit(item)" title="Editar"
            ><i class="bi bi-pencil"></i></button>
            <button
              v-if="can('view-integration-logs')"
              class="btn btn-icon btn-icon-secondary"
              @click="openLogs(item)" title="Ver historial"
            ><i class="bi bi-journal-text"></i></button>
            <button
              v-if="can('manage-integrations') && !item.is_default_for_provider"
              class="btn btn-icon btn-icon-danger"
              @click="confirmDelete(item)" title="Eliminar"
            ><i class="bi bi-trash3"></i></button>
          </div>
        </div>

        <!-- Add another -->
        <div v-if="integrationsFor(provider.id).length > 0 && can('manage-integrations')" class="provider-add-more">
          <button class="btn btn-link btn-sm text-muted p-0" @click="openCreate(provider.id)">
            <i class="bi bi-plus-circle me-1"></i>Agregar otra
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import Swal from 'sweetalert2';

const Toast = Swal.mixin({
  toast: true,
  position: 'bottom-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
});

export default {
  name: 'IntegrationsHubView',

  data() {
    return {
      loading: true,
      providers: [],
      integrations: [],
      validating: {},
    };
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const [pRes, iRes] = await Promise.all([
          axios.get('/api/hub/providers'),
          axios.get('/api/hub/integrations'),
        ]);
        this.providers    = pRes.data.data ?? [];
        this.integrations = iRes.data.data ?? [];
      } finally {
        this.loading = false;
      }
    },

    integrationsFor(providerId) {
      return this.integrations.filter(i => i.provider === providerId);
    },

    // ── Create / Edit ─────────────────────────────────────────────────────────

    openCreate(providerId = null) {
      const providerOptions = this.providers
        .map(p => `<option value="${p.id}" ${p.id === providerId ? 'selected' : ''}>${p.name}</option>`)
        .join('');

      Swal.fire({
        title: 'Nueva integración',
        width: 480,
        showCancelButton: true,
        confirmButtonText: 'Crear integración',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        html: `
          <div class="text-start">
            <div class="mb-3">
              <label class="form-label fw-semibold">Proveedor <span class="text-danger">*</span></label>
              <select id="sw-provider" class="form-select">
                <option value="">— Seleccionar —</option>
                ${providerOptions}
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
              <input id="sw-name" type="text" class="form-control" placeholder="Ej: Anthropic producción">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">API Key</label>
              <input id="sw-key" type="password" class="form-control font-monospace" placeholder="sk-…">
            </div>
            <div id="sw-evolution-wrap" style="display:none">
              <div class="mb-3">
                <label class="form-label fw-semibold">Endpoint URL</label>
                <input id="sw-endpoint" type="text" class="form-control" placeholder="http://localhost:8080">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Instancia</label>
                <input id="sw-instance" type="text" class="form-control" placeholder="meganet-ventas">
              </div>
            </div>
            <div id="sw-meta-wrap" style="display:none">
              <div class="mb-3">
                <label class="form-label fw-semibold">App ID</label>
                <input id="sw-app-id" type="text" class="form-control font-monospace" placeholder="1234567890123456">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">App Secret</label>
                <input id="sw-app-secret" type="password" class="form-control font-monospace" placeholder="App Secret de developers.facebook.com">
              </div>
            </div>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" id="sw-active" checked>
              <label class="form-check-label" for="sw-active">Integración activa</label>
            </div>
          </div>
        `,
        didOpen: () => {
          const sel = document.getElementById('sw-provider');
          const evo = document.getElementById('sw-evolution-wrap');
          const meta = document.getElementById('sw-meta-wrap');
          const toggle = () => {
            evo.style.display  = sel.value === 'evolution' ? 'block' : 'none';
            meta.style.display = sel.value === 'meta' ? 'block' : 'none';
          };
          sel.addEventListener('change', toggle);
          toggle();
        },
        preConfirm: () => {
          const provider = document.getElementById('sw-provider').value;
          const name     = document.getElementById('sw-name').value.trim();
          if (!provider) { Swal.showValidationMessage('Selecciona un proveedor'); return false; }
          if (!name)     { Swal.showValidationMessage('El nombre es requerido'); return false; }
          return {
            provider,
            name,
            key:       document.getElementById('sw-key').value,
            active:    document.getElementById('sw-active').checked,
            endpoint:  document.getElementById('sw-endpoint')?.value ?? '',
            instance:  document.getElementById('sw-instance')?.value ?? '',
            appId:     document.getElementById('sw-app-id')?.value ?? '',
            appSecret: document.getElementById('sw-app-secret')?.value ?? '',
          };
        },
      }).then(async result => {
        if (!result.isConfirmed) return;
        const { provider, name, key, active, endpoint, instance, appId, appSecret } = result.value;
        try {
          const payload = {
            provider,
            name,
            active,
            slug: provider + '-' + Date.now(),
            ...(key ? { key } : {}),
            ...(provider === 'evolution' ? { config: { endpoint, instance } } : {}),
            ...(provider === 'meta' ? { config: { app_id: appId, app_secret: appSecret } } : {}),
          };
          await axios.post('/api/hub/integrations', payload);
          this.toast('success', 'Integración creada');
          await this.load();
        } catch (e) {
          this.toast('danger', e.response?.data?.error ?? 'Error al crear');
        }
      });
    },

    openEdit(item) {
      const isEvo  = item.provider === 'evolution';
      const isMeta = item.provider === 'meta';

      Swal.fire({
        title: `Editar: ${item.name}`,
        width: 480,
        showCancelButton: true,
        confirmButtonText: 'Guardar cambios',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        html: `
          <div class="text-start">
            <div class="mb-3">
              <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
              <input id="sw-name" type="text" class="form-control" value="${this.escHtml(item.name)}">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">
                API Key
                <span class="fw-normal text-muted small">(dejar vacío para no cambiar)</span>
              </label>
              <input id="sw-key" type="password" class="form-control font-monospace" placeholder="sk-…">
            </div>
            ${isEvo ? `
            <div class="mb-3">
              <label class="form-label fw-semibold">Endpoint URL</label>
              <input id="sw-endpoint" type="text" class="form-control" value="${this.escHtml(item.config?.endpoint ?? '')}" placeholder="http://localhost:8080">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Instancia</label>
              <input id="sw-instance" type="text" class="form-control" value="${this.escHtml(item.config?.instance ?? '')}" placeholder="meganet-ventas">
            </div>
            ` : ''}
            ${isMeta ? `
            <div class="mb-3">
              <label class="form-label fw-semibold">App ID</label>
              <input id="sw-app-id" type="text" class="form-control font-monospace" value="${this.escHtml(item.config?.app_id ?? '')}" placeholder="1234567890123456">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">
                App Secret
                <span class="fw-normal text-muted small">(dejar vacío para no cambiar)</span>
              </label>
              <input id="sw-app-secret" type="password" class="form-control font-monospace" placeholder="App Secret de developers.facebook.com">
            </div>
            ` : ''}
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" id="sw-active" ${item.active ? 'checked' : ''}>
              <label class="form-check-label" for="sw-active">Integración activa</label>
            </div>
          </div>
        `,
        preConfirm: () => {
          const name = document.getElementById('sw-name').value.trim();
          if (!name) { Swal.showValidationMessage('El nombre es requerido'); return false; }
          return {
            name,
            key:       document.getElementById('sw-key').value,
            active:    document.getElementById('sw-active').checked,
            endpoint:  document.getElementById('sw-endpoint')?.value ?? '',
            instance:  document.getElementById('sw-instance')?.value ?? '',
            appId:     document.getElementById('sw-app-id')?.value ?? '',
            appSecret: document.getElementById('sw-app-secret')?.value ?? '',
          };
        },
      }).then(async result => {
        if (!result.isConfirmed) return;
        const { name, key, active, endpoint, instance, appId, appSecret } = result.value;
        try {
          const payload = {
            name,
            active,
            ...(key ? { key } : {}),
            ...(isEvo ? { config: { endpoint, instance } } : {}),
            ...(isMeta ? { config: { ...item.config, app_id: appId, ...(appSecret ? { app_secret: appSecret } : {}) } } : {}),
          };
          await axios.put(`/api/hub/integrations/${item.id}`, payload);
          this.toast('success', 'Integración actualizada');
          await this.load();
        } catch (e) {
          this.toast('danger', e.response?.data?.error ?? 'Error al guardar');
        }
      });
    },

    // ── Rotate key ────────────────────────────────────────────────────────────

    openRotate(item) {
      Swal.fire({
        title: 'Rotar API Key',
        width: 440,
        showCancelButton: true,
        confirmButtonText: 'Rotar key',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#fd7e14',
        html: `
          <div class="text-start">
            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <small>La key anterior quedará invalidada inmediatamente.</small>
            </div>
            <p class="mb-3 text-muted small">Integración: <strong class="text-dark">${this.escHtml(item.name)}</strong></p>
            <label class="form-label fw-semibold">Nueva API Key <span class="text-danger">*</span></label>
            <input id="sw-newkey" type="password" class="form-control font-monospace" placeholder="sk-…">
          </div>
        `,
        preConfirm: () => {
          const key = document.getElementById('sw-newkey').value.trim();
          if (!key) { Swal.showValidationMessage('Ingresa la nueva key'); return false; }
          return key;
        },
      }).then(async result => {
        if (!result.isConfirmed) return;
        try {
          const res = await axios.post(`/api/hub/integrations/${item.id}/rotate`, { new_key: result.value });
          this.toast('success', res.data.message ?? 'Key rotada correctamente');
          await this.load();
        } catch (e) {
          this.toast('danger', e.response?.data?.error ?? 'Error al rotar');
        }
      });
    },

    // ── Validate ──────────────────────────────────────────────────────────────

    async validateItem(item) {
      this.validating = { ...this.validating, [item.id]: true };
      try {
        const res = await axios.post(`/api/hub/integrations/${item.id}/validate`);
        const ok  = res.data.success;
        this.toast(ok ? 'success' : 'warning', ok ? `${item.name}: key válida` : `${item.name}: ${res.data.message}`);
        const idx = this.integrations.findIndex(i => i.id === item.id);
        if (idx !== -1) {
          this.integrations.splice(idx, 1, {
            ...this.integrations[idx],
            last_validation_status: res.data.status,
            last_validated_at: new Date().toISOString(),
          });
        }
      } catch {
        this.toast('danger', 'Error al validar');
      } finally {
        this.validating = { ...this.validating, [item.id]: false };
      }
    },

    // ── Set default ───────────────────────────────────────────────────────────

    async setDefault(item) {
      try {
        await axios.post(`/api/hub/integrations/${item.id}/set-default`);
        this.toast('success', 'Marcada como principal');
        await this.load();
      } catch { this.toast('danger', 'Error'); }
    },

    // ── Delete ────────────────────────────────────────────────────────────────

    confirmDelete(item) {
      Swal.fire({
        title: '¿Eliminar integración?',
        html: `¿Eliminar <strong>${this.escHtml(item.name)}</strong>?<br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        focusCancel: true,
      }).then(async result => {
        if (!result.isConfirmed) return;
        try {
          await axios.delete(`/api/hub/integrations/${item.id}`);
          this.toast('success', 'Integración eliminada');
          await this.load();
        } catch (e) {
          this.toast('danger', e.response?.data?.error ?? 'Error');
        }
      });
    },

    // ── Logs ──────────────────────────────────────────────────────────────────

    async openLogs(item) {
      Swal.fire({
        title: `Historial — ${item.name}`,
        width: 540,
        showConfirmButton: false,
        showCloseButton: true,
        html: `<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>`,
      });

      try {
        const res  = await axios.get(`/api/hub/integrations/${item.id}/logs`);
        const logs = res.data.data ?? [];

        if (!logs.length) {
          Swal.update({
            html: `<div class="text-center py-4 text-muted">
              <i class="bi bi-journal-x d-block mb-2" style="font-size:2rem"></i>
              Sin registros en el historial
            </div>`,
          });
          return;
        }

        const rows = logs.map(log => `
          <div class="log-item">
            <div class="log-dot ${this.logDotClass(log.event_type)}">
              <i class="${this.logIcon(log.event_type)}"></i>
            </div>
            <div class="log-content">
              <div class="log-label">${this.escHtml(this.logLabel(log.event_type))}</div>
              <div class="log-meta text-muted">
                <i class="bi bi-person-circle me-1"></i>${this.escHtml(log.actor ?? '—')}
                <span class="mx-2">·</span>
                <i class="bi bi-clock me-1"></i>${this.escHtml(this.formatDate(log.created_at))}
              </div>
            </div>
          </div>
        `).join('');

        Swal.update({ html: `<div class="log-timeline text-start">${rows}</div>` });
      } catch {
        Swal.update({ html: `<div class="text-danger text-center py-3">Error al cargar el historial</div>` });
      }
    },

    // ── Helpers ───────────────────────────────────────────────────────────────

    can(permission) {
      return window.__permissions?.includes(permission) ?? true;
    },

    toast(type, message) {
      const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
      Toast.fire({ icon: iconMap[type] ?? 'info', title: message });
    },

    escHtml(str) {
      return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    },

    providerColor(id) {
      const map = {
        anthropic: '#7c3aed', 'anthropic-legacy': '#9e6fd0',
        openai: '#10a37f', evolution: '#25d366',
        pexels: '#05a081', 'google-maps': '#4285f4',
        meta: '#0866ff',
      };
      return map[id] ?? '#6c757d';
    },

    providerIcon(id) {
      const map = {
        anthropic: 'bi bi-robot', 'anthropic-legacy': 'bi bi-robot',
        openai: 'bi bi-stars', evolution: 'bi bi-whatsapp',
        pexels: 'bi bi-images', 'google-maps': 'bi bi-geo-alt-fill',
        meta: 'bi bi-facebook',
      };
      return map[id] ?? 'bi bi-plugin';
    },

    providerName(id) {
      return this.providers.find(p => p.id === id)?.name ?? id;
    },

    providerCardClass(provider) {
      const items = this.integrationsFor(provider.id);
      if (!items.length) return 'provider-card--empty';
      const hasValid   = items.some(i => i.last_validation_status === 'valid' && i.active);
      const hasInvalid = items.some(i => i.last_validation_status === 'invalid');
      if (hasInvalid) return 'provider-card--warn';
      if (hasValid)   return 'provider-card--ok';
      return 'provider-card--unchecked';
    },

    statusDotClass(item) {
      if (!item.active) return 'dot-inactive';
      if (!item.has_key) return 'dot-nokey';
      if (item.last_validation_status === 'valid') return 'dot-valid';
      if (item.last_validation_status === 'invalid') return 'dot-invalid';
      return 'dot-pending';
    },

    statusBadgeClass(item) {
      if (!item.has_key) return 'bg-secondary-subtle text-secondary';
      if (item.last_validation_status === 'valid') return 'bg-success-subtle text-success';
      if (item.last_validation_status === 'invalid') return 'bg-danger-subtle text-danger';
      return 'bg-warning-subtle text-warning';
    },

    statusBadgeIcon(item) {
      if (!item.has_key) return 'bi bi-key-fill';
      if (item.last_validation_status === 'valid') return 'bi bi-check-circle-fill';
      if (item.last_validation_status === 'invalid') return 'bi bi-x-circle-fill';
      return 'bi bi-question-circle';
    },

    statusLabel(item) {
      if (!item.has_key) return 'Sin key';
      if (item.last_validation_status === 'valid') return 'Válida';
      if (item.last_validation_status === 'invalid') return 'Inválida';
      return 'Sin verificar';
    },

    logDotClass(type) {
      if (type.includes('fail') || type.includes('invalid')) return 'log-dot--danger';
      if (type.includes('delet')) return 'log-dot--warning';
      if (type.includes('rotat')) return 'log-dot--info';
      return 'log-dot--success';
    },

    logIcon(type) {
      if (type.includes('fail') || type.includes('invalid')) return 'bi bi-x-circle';
      if (type.includes('rotat')) return 'bi bi-arrow-repeat';
      if (type.includes('delet')) return 'bi bi-trash3';
      if (type.includes('valid')) return 'bi bi-check-circle';
      return 'bi bi-info-circle';
    },

    logLabel(eventType) {
      const map = {
        key_created: 'Key creada',
        key_updated: 'Key actualizada',
        key_rotated: 'Key rotada',
        key_deleted: 'Key eliminada',
        validated: 'Validación exitosa',
        validation_failed: 'Validación fallida',
        set_as_default: 'Marcada como principal',
        key_migrated_from_legacy: 'Migrada desde configuración anterior',
      };
      return map[eventType] ?? eventType;
    },

    timeAgo(iso) {
      const mins = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
      if (mins < 1)  return 'hace un momento';
      if (mins < 60) return `hace ${mins}m`;
      const hrs = Math.floor(mins / 60);
      if (hrs < 24)  return `hace ${hrs}h`;
      return `hace ${Math.floor(hrs / 24)}d`;
    },

    formatDate(iso) {
      return new Date(iso).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.integrations-hub {
  max-width: 1100px;
  margin: 0 auto;
  padding: 1.5rem;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.hub-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.hub-header-left { flex: 1; min-width: 0; }
.hub-header-right { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }

.hub-title-row { display: flex; align-items: center; gap: 1rem; }
.hub-icon-wrap {
  width: 48px; height: 48px;
  background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.hub-icon-wrap .bi { font-size: 1.5rem; color: #fff; }
.hub-title { font-size: 1.25rem; font-weight: 700; margin: 0; color: #111827; }
.hub-subtitle { font-size: 0.8125rem; color: #6b7280; margin: 0; }

.hub-stats { display: flex; align-items: center; gap: 0.75rem; }
.hub-stat { text-align: center; }
.hub-stat-num { display: block; font-size: 1.25rem; font-weight: 700; line-height: 1; color: #111827; }
.hub-stat-label { font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
.hub-stat-divider { width: 1px; height: 32px; background: #e5e7eb; }

/* ── Provider grid ───────────────────────────────────────────────────────── */
.provider-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(440px, 1fr));
  gap: 1.25rem;
}
@media (max-width: 600px) {
  .provider-grid { grid-template-columns: 1fr; }
}

.provider-card {
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  transition: box-shadow .2s;
  position: relative;
}
.provider-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0;
  height: 3px;
  background: #e5e7eb;
  transition: background .2s;
}
.provider-card--ok::before    { background: #10b981; }
.provider-card--warn::before  { background: #ef4444; }
.provider-card--unchecked::before { background: #f59e0b; }
.provider-card--empty::before { background: #d1d5db; }
.provider-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }

/* ── Provider card header ────────────────────────────────────────────────── */
.provider-card-header {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 1rem 1rem 0.75rem;
  border-bottom: 1px solid #f3f4f6;
}
.provider-logo {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.provider-logo .bi { font-size: 1.25rem; color: #fff; }
.provider-info { flex: 1; min-width: 0; }
.provider-name { font-weight: 600; font-size: 0.9375rem; color: #111827; }
.provider-desc { font-size: 0.75rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.provider-header-actions { flex-shrink: 0; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.provider-empty {
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  font-size: 0.8125rem;
  color: #9ca3af;
}

/* ── Integration row ─────────────────────────────────────────────────────── */
.integration-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 1rem;
  border-bottom: 1px solid #f9fafb;
  transition: background .15s;
}
.integration-row:last-of-type { border-bottom: none; }
.integration-row:hover { background: #f9fafb; }
.integration-row--inactive { opacity: .6; }

.int-status-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot-valid    { background: #10b981; box-shadow: 0 0 0 2px #d1fae5; }
.dot-invalid  { background: #ef4444; box-shadow: 0 0 0 2px #fee2e2; }
.dot-pending  { background: #f59e0b; box-shadow: 0 0 0 2px #fef3c7; }
.dot-nokey    { background: #9ca3af; }
.dot-inactive { background: #d1d5db; }

.int-info { flex: 1; min-width: 0; }
.int-name {
  font-size: 0.875rem; font-weight: 500; color: #111827;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.int-meta { display: flex; align-items: center; gap: 0.75rem; margin-top: 1px; }
.int-key-preview { font-size: 0.7rem; color: #6b7280; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; }
.int-validated { font-size: 0.7rem; white-space: nowrap; }

.int-badge { flex-shrink: 0; }
.int-badge .badge { font-size: 0.7rem; font-weight: 500; padding: 0.25em 0.6em; }

.int-actions { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }

/* ── Icon buttons ────────────────────────────────────────────────────────── */
.btn-icon {
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border: none; border-radius: 6px;
  background: transparent;
  font-size: 0.8125rem;
  cursor: pointer; padding: 0;
  transition: background .15s, color .15s;
  color: #9ca3af;
}
.btn-icon:hover            { background: #f3f4f6; color: #374151; }
.btn-icon-success:hover    { background: #d1fae5; color: #059669; }
.btn-icon-warning:hover    { background: #fef3c7; color: #d97706; }
.btn-icon-secondary:hover  { background: #e5e7eb; color: #374151; }
.btn-icon-danger:hover     { background: #fee2e2; color: #dc2626; }

/* ── Add more ────────────────────────────────────────────────────────────── */
.provider-add-more {
  padding: 0.5rem 1rem;
  border-top: 1px dashed #e5e7eb;
  background: #fafafa;
}

/* ── Loading ─────────────────────────────────────────────────────────────── */
.hub-loading { text-align: center; padding: 4rem 0; }

/* ── Log timeline (rendered inside Swal) ────────────────────────────────── */
:deep(.log-timeline) { display: flex; flex-direction: column; gap: 0; }
:deep(.log-item) {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 0.625rem 0; border-bottom: 1px solid #f3f4f6;
}
:deep(.log-item:last-child) { border-bottom: none; }
:deep(.log-dot) {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 0.75rem; margin-top: 1px;
}
:deep(.log-dot--success) { background: #d1fae5; color: #059669; }
:deep(.log-dot--danger)  { background: #fee2e2; color: #dc2626; }
:deep(.log-dot--warning) { background: #fef3c7; color: #d97706; }
:deep(.log-dot--info)    { background: #dbeafe; color: #2563eb; }
:deep(.log-label)  { font-size: 0.875rem; font-weight: 500; color: #111827; }
:deep(.log-meta)   { font-size: 0.75rem; margin-top: 1px; }
</style>
