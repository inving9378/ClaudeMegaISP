<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h5 text-weight-bold">Cola de Publicaciones</div>
      </div>
      <div class="col-auto">
        <q-btn color="primary" icon="add" label="Nueva Publicación" to="/marketing/publishing/campaign" />
      </div>
    </div>

    <!-- Filtros -->
    <div class="row q-gutter-sm q-mb-md">
      <q-select v-model="filters.status" :options="statusOptions" label="Estado" clearable dense outlined style="min-width:160px" emit-value map-options @update:model-value="load" />
      <q-select v-model="filters.platform" :options="platformOptions" label="Plataforma" clearable dense outlined style="min-width:160px" emit-value map-options @update:model-value="load" />
      <q-btn icon="refresh" color="grey-7" @click="load" flat round />
    </div>

    <q-table
      :rows="publications"
      :columns="columns"
      :loading="loading"
      row-key="id"
      flat
      bordered
      :pagination="{ rowsPerPage: 15 }"
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="statusColor(props.value)" :label="statusLabel(props.value)" />
        </q-td>
      </template>

      <template v-slot:body-cell-channel="props">
        <q-td :props="props">
          <q-icon :name="platformIcon(props.row.pub_channel?.platform)" size="16px" class="q-mr-xs" />
          {{ props.row.pub_channel?.name }}
        </q-td>
      </template>

      <template v-slot:body-cell-scheduled_for="props">
        <q-td :props="props">
          <span v-if="props.value">{{ formatDate(props.value) }}</span>
          <span v-else class="text-grey-5">Inmediato</span>
        </q-td>
      </template>

      <template v-slot:body-cell-metrics="props">
        <q-td :props="props">
          <span v-if="props.row.metrics">
            <span v-if="props.row.metrics.likes">❤️ {{ props.row.metrics.likes }}</span>
            <span v-if="props.row.metrics.views" class="q-ml-xs">▶️ {{ props.row.metrics.views }}</span>
          </span>
          <span v-else class="text-grey-5">—</span>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn-group flat>
            <q-btn
              v-if="props.row.external_post_url"
              flat dense icon="open_in_new" size="sm" color="blue"
              :href="props.row.external_post_url" target="_blank"
            >
              <q-tooltip>Ver publicación</q-tooltip>
            </q-btn>
            <q-btn
              v-if="['failed','waiting_credentials'].includes(props.row.status)"
              flat dense icon="replay" size="sm" color="orange"
              @click="retry(props.row)"
            >
              <q-tooltip>Reintentar</q-tooltip>
            </q-btn>
            <q-btn
              v-if="['queued','scheduled','waiting_credentials'].includes(props.row.status)"
              flat dense icon="cancel" size="sm" color="negative"
              @click="cancel(props.row)"
            >
              <q-tooltip>Cancelar</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.status === 'published'"
              flat dense icon="bar_chart" size="sm" color="positive"
              @click="fetchMetrics(props.row)"
            >
              <q-tooltip>Actualizar métricas</q-tooltip>
            </q-btn>
          </q-btn-group>
        </q-td>
      </template>
    </q-table>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'MarketingPublicationQueueView',
  data() {
    return {
      publications: [],
      loading: false,
      filters: { status: null, platform: null },
      statusOptions: [
        { label: 'En cola', value: 'queued' },
        { label: 'Programada', value: 'scheduled' },
        { label: 'Publicando', value: 'publishing' },
        { label: 'Publicada', value: 'published' },
        { label: 'Fallida', value: 'failed' },
        { label: 'Esperando credenciales', value: 'waiting_credentials' },
        { label: 'Cancelada', value: 'cancelled' },
      ],
      platformOptions: [
        { label: 'Facebook', value: 'facebook' },
        { label: 'Instagram', value: 'instagram' },
        { label: 'WhatsApp', value: 'whatsapp' },
        { label: 'Email', value: 'email' },
      ],
      columns: [
        { name: 'id',           label: 'ID',       field: 'id',           sortable: true, align: 'left' },
        { name: 'channel',      label: 'Canal',    field: 'pub_channel',  align: 'left' },
        { name: 'status',       label: 'Estado',   field: 'status',       align: 'left' },
        { name: 'scheduled_for',label: 'Programada',field: 'scheduled_for',align: 'left' },
        { name: 'ab_variant_tag',label: 'Variante',field: 'ab_variant_tag',align: 'left' },
        { name: 'metrics',      label: 'Métricas', field: 'metrics',      align: 'left' },
        { name: 'actions',      label: 'Acciones', field: 'id',           align: 'center' },
      ],
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const params = {};
        if (this.filters.status)   params.status   = this.filters.status;
        if (this.filters.platform) params.platform = this.filters.platform;
        const { data } = await axios.get('/api/marketing/publishing/publications', { params });
        this.publications = data.data ?? data;
      } finally {
        this.loading = false;
      }
    },
    async retry(pub) {
      await axios.post(`/api/marketing/publishing/publications/${pub.id}/retry`);
      this.$q.notify({ type: 'positive', message: 'Publicación puesta en cola para reintento' });
      this.load();
    },
    async cancel(pub) {
      await axios.post(`/api/marketing/publishing/publications/${pub.id}/cancel`);
      this.$q.notify({ type: 'info', message: 'Publicación cancelada' });
      this.load();
    },
    async fetchMetrics(pub) {
      const { data } = await axios.get(`/api/marketing/publishing/publications/${pub.id}/metrics`);
      this.$q.notify({ type: 'positive', message: 'Métricas actualizadas' });
      const idx = this.publications.findIndex(p => p.id === pub.id);
      if (idx >= 0) this.publications[idx].metrics = data.metrics;
    },
    statusColor(status) {
      const map = {
        draft: 'grey', queued: 'blue', scheduled: 'cyan', publishing: 'orange',
        published: 'positive', failed: 'negative', waiting_credentials: 'warning', cancelled: 'grey-5',
      };
      return map[status] || 'grey';
    },
    statusLabel(status) {
      const map = {
        draft: 'Borrador', queued: 'En cola', scheduled: 'Programada',
        publishing: 'Publicando...', published: 'Publicada', failed: 'Fallida',
        waiting_credentials: 'Esperando credenciales', cancelled: 'Cancelada',
      };
      return map[status] || status;
    },
    platformIcon(platform) {
      const map = { facebook: 'fab fa-facebook', instagram: 'fab fa-instagram', whatsapp: 'fab fa-whatsapp', email: 'email' };
      return map[platform] || 'share';
    },
    formatDate(d) {
      return d ? new Date(d).toLocaleString('es-MX') : '';
    },
  },
};
</script>
