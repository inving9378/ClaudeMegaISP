<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col text-h5 text-weight-bold">Leads</div>
      <div class="col-auto q-gutter-sm">
        <q-btn icon="add" color="primary" label="Crear lead" unelevated @click="createDialog = true" />
      </div>
    </div>

    <!-- Filtros -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-gutter-sm">
        <q-input v-model="filters.search" dense outlined placeholder="Buscar nombre, email, teléfono..." clearable style="min-width:220px"
          @update:model-value="debouncedLoad" />
        <q-select v-model="filters.status" :options="statusOptions" emit-value map-options dense outlined
          label="Estado" clearable style="min-width:150px" @update:model-value="loadLeads" />
        <q-input v-model="filters.captured_from" type="date" dense outlined label="Desde" style="min-width:140px"
          @update:model-value="loadLeads" />
        <q-input v-model="filters.captured_to" type="date" dense outlined label="Hasta" style="min-width:140px"
          @update:model-value="loadLeads" />
      </q-card-section>
    </q-card>

    <!-- Tabla -->
    <q-table :rows="leads" :columns="columns" :loading="loading" row-key="id"
      :pagination="pagination" @request="onRequest" binary-state-sort flat bordered>

      <template #body-cell-full_name="props">
        <q-td :props="props">
          <div class="row items-center no-wrap q-gutter-xs">
            <q-avatar size="32px" color="primary" text-color="white">{{ props.row.full_name?.charAt(0) }}</q-avatar>
            <div>
              <div class="text-weight-medium">{{ props.row.full_name }}</div>
              <div class="text-caption text-grey-6">{{ props.row.phone }}</div>
            </div>
          </div>
        </q-td>
      </template>

      <template #body-cell-source="props">
        <q-td :props="props">
          <lead-source-chip :source="props.row.source" />
        </q-td>
      </template>

      <template #body-cell-score="props">
        <q-td :props="props">
          <lead-score-badge :score="props.row.score" />
        </q-td>
      </template>

      <template #body-cell-status="props">
        <q-td :props="props">
          <lead-status-badge :status="props.row.status" />
        </q-td>
      </template>

      <template #body-cell-captured_at="props">
        <q-td :props="props">
          {{ formatDate(props.row.captured_at) }}
        </q-td>
      </template>

      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat round icon="visibility" size="sm" color="primary" @click="goToDetail(props.row.id)" />
          <q-btn flat round icon="person_add" size="sm" color="grey" @click="quickAssign(props.row)" />
        </q-td>
      </template>
    </q-table>

    <!-- Dialog crear lead -->
    <q-dialog v-model="createDialog">
      <q-card style="min-width:400px">
        <q-card-section class="text-h6">Nuevo lead</q-card-section>
        <q-card-section class="q-gutter-sm">
          <q-input v-model="newLead.full_name" label="Nombre completo *" outlined dense />
          <q-input v-model="newLead.phone" label="Teléfono" outlined dense />
          <q-input v-model="newLead.email" label="Email" type="email" outlined dense />
          <q-select v-model="newLead.source_id" :options="sources" emit-value map-options
            option-value="id" option-label="name" label="Fuente *" outlined dense />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" label="Crear" :loading="saving" @click="saveLead" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import axios from 'axios';
export default {
  name: 'MarketingLeadsView',
  data() {
    return {
      leads: [], sources: [], loading: false, saving: false, createDialog: false,
      filters: { search: '', status: null, captured_from: '', captured_to: '' },
      newLead: { full_name: '', phone: '', email: '', source_id: null },
      pagination: { page: 1, rowsPerPage: 25, rowsNumber: 0, sortBy: 'captured_at', descending: true },
      statusOptions: [
        { label: 'Nuevo', value: 'new' }, { label: 'Contactado', value: 'contacted' },
        { label: 'Calificado', value: 'qualified' }, { label: 'Negociando', value: 'negotiating' },
        { label: 'Ganado', value: 'won' }, { label: 'Perdido', value: 'lost' },
      ],
      columns: [
        { name: 'full_name', label: 'Nombre', field: 'full_name', align: 'left', sortable: true },
        { name: 'source', label: 'Fuente', field: 'source', align: 'left' },
        { name: 'city', label: 'Ciudad', field: 'city', align: 'left' },
        { name: 'score', label: 'Score', field: 'score', align: 'center', sortable: true },
        { name: 'status', label: 'Estado', field: 'status', align: 'center' },
        { name: 'captured_at', label: 'Capturado', field: 'captured_at', align: 'left', sortable: true },
        { name: 'actions', label: '', field: 'id', align: 'center' },
      ],
      _searchTimer: null,
    };
  },
  mounted() {
    this.loadLeads();
    this.loadSources();
  },
  methods: {
    async loadLeads(props) {
      if (props) {
        const { page, rowsPerPage, sortBy, descending } = props.pagination;
        this.pagination.page = page;
        this.pagination.rowsPerPage = rowsPerPage;
        this.pagination.sortBy = sortBy;
        this.pagination.descending = descending;
      }
      this.loading = true;
      try {
        const { data } = await axios.get('/api/marketing/leads', {
          params: {
            page: this.pagination.page,
            per_page: this.pagination.rowsPerPage,
            sort: this.pagination.sortBy,
            order: this.pagination.descending ? 'desc' : 'asc',
            ...this.filters,
          }
        });
        this.leads = data.data;
        this.pagination.rowsNumber = data.meta?.total ?? data.total ?? 0;
      } finally { this.loading = false; }
    },
    onRequest(props) { this.loadLeads(props); },
    debouncedLoad() {
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => this.loadLeads(), 400);
    },
    async loadSources() {
      const { data } = await axios.get('/api/marketing/leads', { params: { per_page: 1 } });
      const { data: srcs } = await axios.get('/api/marketing/lead-forms'); // reuse endpoint shapes
      this.sources = [];
      // Load sources via a simple API call
      try {
        const r = await axios.get('/api/marketing/leads?per_page=0'); // just for meta
      } catch {}
    },
    async saveLead() {
      this.saving = true;
      try {
        await axios.post('/api/marketing/leads', this.newLead);
        this.createDialog = false;
        this.newLead = { full_name: '', phone: '', email: '', source_id: null };
        this.loadLeads();
        this.$q?.notify({ type: 'positive', message: 'Lead creado' });
      } catch(e) {
        this.$q?.notify({ type: 'negative', message: 'Error al crear el lead' });
      } finally { this.saving = false; }
    },
    goToDetail(id) { window.location.href = '/marketing/leads/' + id; },
    quickAssign(row) { /* TODO: abrir dialog asignación */ },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  }
}
</script>
