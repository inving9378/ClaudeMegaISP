<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col text-h5 text-weight-bold">Cola de Renders</div>
      <div class="col-auto q-gutter-sm">
        <q-btn flat dense icon="refresh" label="Actualizar" @click="load" :loading="loading" />
        <q-btn unelevated color="primary" label="Nuevo video" to="/marketing/video-generator" icon="add" />
      </div>
    </div>

    <!-- Filtros -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-gutter-sm">
        <q-select
          v-model="filters.status"
          :options="statusOptions"
          emit-value map-options dense outlined
          label="Estado" clearable style="min-width:150px"
          @update:model-value="load"
        />
      </q-card-section>
    </q-card>

    <!-- Lista de renders -->
    <q-list bordered separator>
      <q-item v-if="loading" class="text-center q-pa-md">
        <q-item-section><q-spinner size="32px" color="primary" /></q-item-section>
      </q-item>

      <q-item v-else-if="items.length === 0" class="text-grey-6 text-center q-pa-md">
        <q-item-section>No hay renders todavía.</q-item-section>
      </q-item>

      <q-item v-for="item in items" :key="item.id" class="q-py-md">
        <q-item-section avatar>
          <q-icon :name="statusIcon(item.status)" :color="statusColor(item.status)" size="32px" />
        </q-item-section>

        <q-item-section>
          <q-item-label class="text-weight-medium">
            Video #{{ item.id }}
            <q-chip dense size="sm" :color="statusColor(item.status)" text-color="white" class="q-ml-sm">
              {{ statusLabel(item.status) }}
            </q-chip>
          </q-item-label>
          <q-item-label caption>
            {{ formatDate(item.created_at) }}
            <span v-if="item.generation_metadata?.template_slug">
              · {{ item.generation_metadata.template_slug }}
            </span>
            <span v-if="item.generation_metadata?.aspect_ratio">
              · {{ item.generation_metadata.aspect_ratio }}
            </span>
          </q-item-label>

          <!-- Progress bar -->
          <div v-if="item.status === 'generating'" class="q-mt-sm">
            <q-linear-progress
              :value="(item.render_progress ?? 0) / 100"
              color="primary"
              track-color="grey-3"
              rounded
              size="8px"
            />
            <div class="text-caption text-grey-6 q-mt-xs">
              {{ item.render_stage ?? 'procesando...' }} — {{ item.render_progress ?? 0 }}%
            </div>
          </div>

          <!-- Cost info if ready -->
          <div v-if="item.status === 'ready'" class="text-caption text-grey-6 q-mt-xs">
            Duración: {{ item.generation_metadata?.duration_sec?.toFixed(1) ?? '?' }}s
            <span v-if="item.generation_cost_usd > 0">
              · Costo TTS: ${{ item.generation_cost_usd?.toFixed(4) }} USD
            </span>
          </div>

          <!-- Error log -->
          <div v-if="item.status === 'failed' && item.render_log?.length" class="text-caption text-negative q-mt-xs">
            {{ item.render_log.slice(-1)[0]?.error ?? 'Error desconocido' }}
          </div>
        </q-item-section>

        <q-item-section side>
          <div class="row q-gutter-sm">
            <!-- Progress polling badge for generating -->
            <q-btn
              v-if="item.status === 'generating'"
              flat round dense icon="autorenew"
              color="primary"
              @click="pollOnce(item)"
            />
            <!-- Download if ready -->
            <q-btn
              v-if="item.status === 'ready' && item.output_path"
              flat round dense icon="download"
              color="positive"
              :href="'/api/marketing/generated-content/' + item.id + '/download'"
              target="_blank"
              title="Descargar video"
            />
            <!-- Delete -->
            <q-btn
              flat round dense icon="delete"
              color="negative"
              @click="confirmDelete(item)"
              title="Eliminar"
            />
          </div>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Pagination -->
    <div class="row justify-center q-mt-md" v-if="pagination.last_page > 1">
      <q-pagination
        v-model="pagination.current_page"
        :max="pagination.last_page"
        boundary-links
        @update:model-value="load"
      />
    </div>

    <!-- Delete confirm dialog -->
    <q-dialog v-model="deleteDialog">
      <q-card>
        <q-card-section class="text-h6">Eliminar render</q-card-section>
        <q-card-section>¿Eliminar el video #{{ deleteTarget?.id }}? Esta acción no se puede deshacer.</q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="negative" label="Eliminar" :loading="deleting" @click="deleteItem" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
export default {
  name: 'MarketingVideoQueueView',

  data() {
    return {
      loading: false,
      deleting: false,
      items: [],
      filters: { status: null },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      deleteDialog: false,
      deleteTarget: null,
      pollTimer: null,
      statusOptions: [
        { label: 'Pendiente', value: 'pending' },
        { label: 'Generando', value: 'generating' },
        { label: 'Listo',     value: 'ready' },
        { label: 'Fallido',   value: 'failed' },
      ],
    };
  },

  mounted() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('id')) {
      this.highlightId = parseInt(params.get('id'));
    }
    this.load();
    this.startPolling();
  },

  beforeUnmount() {
    this.stopPolling();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const params = { page: this.pagination.current_page };
        if (this.filters.status) params.status = this.filters.status;

        const res = await axios.get('/api/marketing/generated-content', { params });
        const data = res.data;
        this.items = data.data ?? [];
        this.pagination = {
          current_page: data.current_page ?? 1,
          last_page:    data.last_page    ?? 1,
          total:        data.total        ?? 0,
        };
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al cargar la cola' });
      } finally {
        this.loading = false;
      }
    },

    async pollOnce(item) {
      try {
        const res = await axios.get(`/api/marketing/generated-content/${item.id}/progress`);
        const idx = this.items.findIndex(i => i.id === item.id);
        if (idx !== -1) {
          this.items[idx] = { ...this.items[idx], ...res.data };
        }
      } catch (e) { /* silent */ }
    },

    startPolling() {
      this.pollTimer = setInterval(() => {
        const hasActive = this.items.some(i => i.status === 'generating' || i.status === 'pending');
        if (hasActive) this.load();
      }, 5000);
    },

    stopPolling() {
      if (this.pollTimer) clearInterval(this.pollTimer);
    },

    confirmDelete(item) {
      this.deleteTarget = item;
      this.deleteDialog = true;
    },

    async deleteItem() {
      if (!this.deleteTarget) return;
      this.deleting = true;
      try {
        await axios.delete(`/api/marketing/generated-content/${this.deleteTarget.id}`);
        this.deleteDialog = false;
        this.$q.notify({ type: 'positive', message: 'Eliminado correctamente' });
        await this.load();
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'Error al eliminar' });
      } finally {
        this.deleting = false;
        this.deleteTarget = null;
      }
    },

    statusIcon(s) {
      return { pending: 'hourglass_empty', generating: 'sync', ready: 'check_circle', failed: 'error' }[s] ?? 'help';
    },

    statusColor(s) {
      return { pending: 'grey', generating: 'blue', ready: 'positive', failed: 'negative' }[s] ?? 'grey';
    },

    statusLabel(s) {
      return { pending: 'Pendiente', generating: 'Generando', ready: 'Listo', failed: 'Fallido' }[s] ?? s;
    },

    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>
