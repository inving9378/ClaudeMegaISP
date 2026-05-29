<template>
  <div class="q-pa-md" style="max-width:1100px;margin:0 auto">
    <div class="row items-center q-mb-md">
      <q-icon name="hub" size="28px" color="primary" class="q-mr-sm" />
      <div class="text-h5 text-weight-bold">Integration Hub</div>
      <q-space />
      <q-btn v-if="can('manage-integrations')" unelevated color="primary" icon="add" label="Nueva integración" @click="openCreate" />
    </div>

    <!-- Provider groups -->
    <div v-if="loading" class="text-center q-pa-xl">
      <q-spinner size="40px" color="primary" />
    </div>

    <div v-else>
      <div v-for="provider in providers" :key="provider.id" class="q-mb-xl">
        <div class="row items-center q-mb-sm">
          <q-icon :name="provider.icon" size="20px" color="grey-7" class="q-mr-xs" />
          <div class="text-subtitle1 text-weight-medium">{{ provider.name }}</div>
          <div class="text-caption text-grey-6 q-ml-sm">{{ provider.description }}</div>
          <q-space />
          <q-btn
            v-if="provider.docs_url"
            flat dense size="sm" icon="open_in_new" color="grey"
            :href="provider.docs_url" target="_blank"
            label="Docs"
          />
        </div>

        <div v-if="integrationsFor(provider.id).length === 0" class="text-caption text-grey-5 q-ml-md q-mb-md">
          Sin configurar —
          <a class="cursor-pointer text-primary" @click="openCreate(provider.id)">Agregar</a>
        </div>

        <q-card
          v-for="item in integrationsFor(provider.id)"
          :key="item.id"
          flat bordered
          class="q-mb-sm"
          :class="{ 'bg-grey-1': !item.active }"
        >
          <q-card-section horizontal class="items-center q-pa-sm">
            <!-- Status chip -->
            <div class="q-mr-sm">
              <q-chip
                dense size="sm"
                :color="statusColor(item)"
                text-color="white"
                :icon="statusIcon(item)"
              >{{ statusLabel(item) }}</q-chip>
              <q-chip v-if="item.is_default_for_provider" dense size="sm" color="blue-1" text-color="blue-8" icon="star">Predeterminada</q-chip>
              <q-chip v-if="!item.active" dense size="sm" color="grey-3" text-color="grey-7">Inactiva</q-chip>
            </div>

            <!-- Name + preview -->
            <div class="col">
              <div class="text-weight-medium">{{ item.name }}</div>
              <div class="text-caption text-grey-6 font-mono">
                {{ item.has_key ? item.key_preview : 'Sin key' }}
              </div>
            </div>

            <!-- Validation timestamp -->
            <div class="text-caption text-grey-5 q-mr-md" v-if="item.last_validated_at">
              Validada {{ timeAgo(item.last_validated_at) }}
            </div>

            <!-- Actions -->
            <div class="row q-gutter-xs">
              <q-btn
                v-if="can('validate-integrations') && item.has_key"
                flat dense size="sm" icon="check_circle" color="positive"
                :loading="validating[item.id]"
                @click="validateItem(item)"
              >
                <q-tooltip>Validar</q-tooltip>
              </q-btn>
              <q-btn
                v-if="can('manage-integrations') && !item.is_default_for_provider"
                flat dense size="sm" icon="star_border" color="amber"
                @click="setDefault(item)"
              >
                <q-tooltip>Hacer predeterminada</q-tooltip>
              </q-btn>
              <q-btn
                v-if="can('rotate-integration-keys') && item.has_key"
                flat dense size="sm" icon="refresh" color="warning"
                @click="openRotate(item)"
              >
                <q-tooltip>Rotar key</q-tooltip>
              </q-btn>
              <q-btn
                v-if="can('manage-integrations')"
                flat dense size="sm" icon="edit" color="grey"
                @click="openEdit(item)"
              >
                <q-tooltip>Editar</q-tooltip>
              </q-btn>
              <q-btn
                v-if="can('view-integration-logs')"
                flat dense size="sm" icon="history" color="grey"
                @click="openLogs(item)"
              >
                <q-tooltip>Logs</q-tooltip>
              </q-btn>
              <q-btn
                v-if="can('manage-integrations') && !item.is_default_for_provider"
                flat dense size="sm" icon="delete" color="negative"
                @click="confirmDelete(item)"
              >
                <q-tooltip>Eliminar</q-tooltip>
              </q-btn>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="dialog.open" persistent>
      <q-card style="min-width:500px">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ dialog.isEdit ? 'Editar integración' : 'Nueva integración' }}</div>
          <q-space />
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section class="q-gutter-md">
          <q-select
            v-if="!dialog.isEdit"
            v-model="dialog.form.provider"
            :options="providers.map(p => ({ label: p.name, value: p.id }))"
            emit-value map-options
            label="Proveedor *"
            outlined dense
          />
          <q-input v-model="dialog.form.name" label="Nombre *" outlined dense />
          <q-input
            v-model="dialog.form.key"
            :label="'API Key' + (dialog.isEdit ? ' (dejar vacío para no cambiar)' : '')"
            outlined dense
            :type="showKey ? 'text' : 'password'"
          >
            <template #append>
              <q-icon :name="showKey ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showKey = !showKey" />
            </template>
          </q-input>

          <!-- Config fields for evolution -->
          <template v-if="dialog.form.provider === 'evolution'">
            <q-input v-model="dialog.config.endpoint" label="Endpoint URL" outlined dense placeholder="http://localhost:8080" />
            <q-input v-model="dialog.config.instance" label="Instancia" outlined dense placeholder="meganet" />
          </template>

          <q-toggle v-model="dialog.form.active" label="Activa" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" :label="dialog.isEdit ? 'Guardar' : 'Crear'" :loading="dialog.saving" @click="saveDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Rotate Dialog -->
    <q-dialog v-model="rotateDialog.open" persistent>
      <q-card style="min-width:450px">
        <q-card-section>
          <div class="text-h6">Rotar key — {{ rotateDialog.item?.name }}</div>
          <div class="text-caption text-grey-6 q-mt-xs">La key anterior quedará invalidada inmediatamente.</div>
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-input
            v-model="rotateDialog.newKey"
            label="Nueva API Key *"
            outlined dense
            :type="showRotateKey ? 'text' : 'password'"
          >
            <template #append>
              <q-icon :name="showRotateKey ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showRotateKey = !showRotateKey" />
            </template>
          </q-input>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="warning" label="Rotar" :loading="rotateDialog.saving" @click="doRotate" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Logs Dialog -->
    <q-dialog v-model="logsDialog.open">
      <q-card style="min-width:600px;max-height:80vh">
        <q-card-section class="row items-center">
          <div class="text-h6">Logs — {{ logsDialog.item?.name }}</div>
          <q-space />
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section class="scroll" style="max-height:500px">
          <div v-if="logsDialog.loading" class="text-center q-pa-md"><q-spinner color="primary" /></div>
          <q-list v-else dense separator>
            <q-item v-for="log in logsDialog.items" :key="log.id">
              <q-item-section avatar>
                <q-icon :name="logIcon(log.event_type)" :color="logColor(log.event_type)" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ logLabel(log.event_type) }}</q-item-label>
                <q-item-label caption>{{ log.actor }} · {{ formatDate(log.created_at) }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item v-if="!logsDialog.items.length">
              <q-item-section class="text-grey-5">Sin registros</q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
export default {
  name: 'IntegrationsHubView',

  data() {
    return {
      loading: true,
      providers: [],
      integrations: [],
      validating: {},
      showKey: false,
      showRotateKey: false,
      dialog: {
        open: false,
        isEdit: false,
        saving: false,
        item: null,
        form: { provider: null, name: '', key: '', active: true },
        config: { endpoint: '', instance: '' },
      },
      rotateDialog: { open: false, saving: false, item: null, newKey: '' },
      logsDialog: { open: false, loading: false, item: null, items: [] },
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
        this.providers     = pRes.data.data ?? [];
        this.integrations  = iRes.data.data ?? [];
      } finally {
        this.loading = false;
      }
    },

    integrationsFor(providerId) {
      return this.integrations.filter(i => i.provider === providerId);
    },

    // ── Dialog handlers ──────────────────────────────────────────────────────

    openCreate(providerId = null) {
      this.dialog.isEdit   = false;
      this.dialog.item     = null;
      this.dialog.form     = { provider: providerId, name: '', key: '', active: true };
      this.dialog.config   = { endpoint: '', instance: '' };
      this.showKey         = false;
      this.dialog.open     = true;
    },

    openEdit(item) {
      this.dialog.isEdit   = true;
      this.dialog.item     = item;
      this.dialog.form     = { provider: item.provider, name: item.name, key: '', active: item.active };
      this.dialog.config   = { endpoint: item.config?.endpoint ?? '', instance: item.config?.instance ?? '' };
      this.showKey         = false;
      this.dialog.open     = true;
    },

    async saveDialog() {
      if (!this.dialog.form.name || (!this.dialog.isEdit && !this.dialog.form.provider)) {
        this.$q.notify({ type: 'warning', message: 'Completa los campos requeridos' });
        return;
      }

      this.dialog.saving = true;
      try {
        const config = this.dialog.form.provider === 'evolution' ? this.dialog.config : undefined;
        const payload = {
          name:   this.dialog.form.name,
          active: this.dialog.form.active,
          ...(this.dialog.form.key ? { key: this.dialog.form.key } : {}),
          ...(config ? { config } : {}),
        };

        if (this.dialog.isEdit) {
          await axios.put(`/api/hub/integrations/${this.dialog.item.id}`, payload);
          this.$q.notify({ type: 'positive', message: 'Integración actualizada' });
        } else {
          const slug = this.dialog.form.provider + '-' + Date.now();
          await axios.post('/api/hub/integrations', {
            ...payload,
            provider: this.dialog.form.provider,
            slug,
          });
          this.$q.notify({ type: 'positive', message: 'Integración creada' });
        }

        this.dialog.open = false;
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.error ?? 'Error al guardar' });
      } finally {
        this.dialog.saving = false;
      }
    },

    openRotate(item) {
      this.rotateDialog.item   = item;
      this.rotateDialog.newKey = '';
      this.showRotateKey       = false;
      this.rotateDialog.open   = true;
    },

    async doRotate() {
      if (!this.rotateDialog.newKey) {
        this.$q.notify({ type: 'warning', message: 'Ingresa la nueva key' });
        return;
      }
      this.rotateDialog.saving = true;
      try {
        const res = await axios.post(`/api/hub/integrations/${this.rotateDialog.item.id}/rotate`, {
          new_key: this.rotateDialog.newKey,
        });
        this.$q.notify({ type: 'positive', message: res.data.message });
        this.rotateDialog.open = false;
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.error ?? 'Error al rotar' });
      } finally {
        this.rotateDialog.saving = false;
      }
    },

    async validateItem(item) {
      this.$set ? this.$set(this.validating, item.id, true) : (this.validating[item.id] = true);
      try {
        const res = await axios.post(`/api/hub/integrations/${item.id}/validate`);
        const ok  = res.data.success;
        this.$q.notify({
          type: ok ? 'positive' : 'warning',
          message: ok ? `✅ ${item.name}: válida` : `⚠️ ${item.name}: ${res.data.message}`,
        });
        const idx = this.integrations.findIndex(i => i.id === item.id);
        if (idx !== -1) {
          this.integrations[idx] = {
            ...this.integrations[idx],
            last_validation_status: res.data.status,
            last_validated_at: new Date().toISOString(),
          };
        }
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al validar' });
      } finally {
        this.validating[item.id] = false;
      }
    },

    async setDefault(item) {
      try {
        await axios.post(`/api/hub/integrations/${item.id}/set-default`);
        this.$q.notify({ type: 'positive', message: 'Marcada como predeterminada' });
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error' });
      }
    },

    confirmDelete(item) {
      this.$q.dialog({
        title: 'Eliminar integración',
        message: `¿Eliminar "${item.name}"? Esta acción no se puede deshacer.`,
        cancel: true,
        color: 'negative',
      }).onOk(async () => {
        try {
          await axios.delete(`/api/hub/integrations/${item.id}`);
          this.$q.notify({ type: 'positive', message: 'Eliminada' });
          await this.load();
        } catch (e) {
          this.$q.notify({ type: 'negative', message: e.response?.data?.error ?? 'Error' });
        }
      });
    },

    async openLogs(item) {
      this.logsDialog.item    = item;
      this.logsDialog.items   = [];
      this.logsDialog.loading = true;
      this.logsDialog.open    = true;
      try {
        const res = await axios.get(`/api/hub/integrations/${item.id}/logs`);
        this.logsDialog.items = res.data.data ?? [];
      } finally {
        this.logsDialog.loading = false;
      }
    },

    // ── Helpers ──────────────────────────────────────────────────────────────

    can(permission) {
      return window.__permissions?.includes(permission) ?? true;
    },

    statusColor(item) {
      if (!item.has_key) return 'grey';
      if (item.last_validation_status === 'valid') return 'positive';
      if (item.last_validation_status === 'invalid') return 'negative';
      return 'grey';
    },

    statusIcon(item) {
      if (!item.has_key) return 'key_off';
      if (item.last_validation_status === 'valid') return 'check_circle';
      if (item.last_validation_status === 'invalid') return 'error';
      return 'help_outline';
    },

    statusLabel(item) {
      if (!item.has_key) return 'Sin key';
      if (item.last_validation_status === 'valid') return 'Válida';
      if (item.last_validation_status === 'invalid') return 'Inválida';
      return 'Sin validar';
    },

    logLabel(eventType) {
      const map = {
        key_created: 'Key creada',
        key_updated: 'Key actualizada',
        key_rotated: 'Key rotada',
        key_deleted: 'Key eliminada',
        validated: 'Validación exitosa',
        validation_failed: 'Validación fallida',
        set_as_default: 'Marcada como predeterminada',
        key_migrated_from_legacy: 'Migrada desde configuración anterior',
      };
      return map[eventType] ?? eventType;
    },

    logIcon(eventType) {
      if (eventType.includes('fail') || eventType.includes('invalid')) return 'warning';
      if (eventType.includes('rotat')) return 'refresh';
      if (eventType.includes('delet')) return 'delete';
      if (eventType.includes('valid')) return 'check_circle';
      return 'info';
    },

    logColor(eventType) {
      if (eventType.includes('fail') || eventType.includes('invalid')) return 'negative';
      if (eventType.includes('delet')) return 'warning';
      return 'positive';
    },

    timeAgo(iso) {
      const diff = Date.now() - new Date(iso).getTime();
      const mins = Math.floor(diff / 60000);
      if (mins < 1) return 'hace un momento';
      if (mins < 60) return `hace ${mins}m`;
      const hrs = Math.floor(mins / 60);
      if (hrs < 24) return `hace ${hrs}h`;
      return `hace ${Math.floor(hrs / 24)}d`;
    },

    formatDate(iso) {
      return new Date(iso).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>
