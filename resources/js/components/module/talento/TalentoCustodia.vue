<template>
  <div class="talento-custodia">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-boxes me-2 text-primary"></i>Custodia de Material por Colaborador</h5>
    </div>
    <p class="text-muted small mb-3">
      Muestra el inventario en custodia asignado a cada colaborador (solo lectura, desde Inventario).
    </p>

    <!-- Selección de colaborador -->
    <div class="row mb-3">
      <div class="col-md-5">
        <input v-model="searchColaborador" @input="debounceBuscar" type="text"
               class="form-control" placeholder="Buscar colaborador...">
      </div>
    </div>

    <div v-if="loadingLista" class="text-center py-3">
      <div class="spinner-border spinner-border-sm text-primary"></div>
    </div>

    <div v-else-if="!selected" class="row g-3">
      <div v-for="col in colaboradores" :key="col.id" class="col-md-6 col-xl-4">
        <div class="card card-hover border-0 shadow-sm cursor-pointer" @click="selectColaborador(col)">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="avatar-sm bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center">
              <i class="fa fa-user text-primary"></i>
            </div>
            <div>
              <div class="fw-semibold">{{ col.user?.name }}</div>
              <div class="small text-muted">{{ col.user?.email }}</div>
              <span class="badge" :class="col.type === 'interno' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'">
                {{ col.type }}
              </span>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!colaboradores.length" class="col-12 text-center text-muted py-4">
        No se encontraron colaboradores.
      </div>
    </div>

    <!-- Detalle custodia de colaborador seleccionado -->
    <div v-else>
      <button @click="selected = null" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="fa fa-arrow-left me-1"></i> Volver
      </button>
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa fa-user-circle fa-2x text-primary"></i>
        <div>
          <div class="fw-semibold fs-5">{{ selected.colaborador?.name }}</div>
          <span class="badge bg-light text-dark">{{ selected.colaborador?.type }}</span>
        </div>
      </div>

      <div v-if="loadingDetalle" class="text-center py-3">
        <div class="spinner-border spinner-border-sm text-primary"></div>
      </div>
      <div v-else>
        <h6 class="mb-2">Material en custodia</h6>
        <div v-if="!selected.stocks?.length" class="alert alert-light text-muted">
          Este colaborador no tiene material en custodia registrado.
        </div>
        <div v-else class="table-responsive mb-4">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Artículo</th>
                <th>SKU</th>
                <th>Categoría</th>
                <th>Cantidad</th>
                <th>Condición</th>
                <th>Asignado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in selected.stocks" :key="s.stock_id">
                <td>{{ s.item_name }}</td>
                <td class="font-monospace small">{{ s.sku ?? '—' }}</td>
                <td>{{ s.category ?? '—' }}</td>
                <td>{{ s.current_stock }} {{ s.unit }}</td>
                <td>{{ s.condition ?? '—' }}</td>
                <td class="small">{{ formatDate(s.assigned_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <h6 class="mb-2">Últimos movimientos (50)</h6>
        <div v-if="!selected.movements?.length" class="text-muted small fst-italic">Sin movimientos registrados.</div>
        <div v-else class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Artículo</th>
                <th>Tipo</th>
                <th>Cantidad</th>
                <th>Descripción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in selected.movements" :key="m.id">
                <td class="small">{{ formatDate(m.created_at) }}</td>
                <td>{{ m.item_name }}</td>
                <td><span class="badge bg-light text-dark">{{ m.type }}</span></td>
                <td>{{ m.quantity }}</td>
                <td class="small text-muted">{{ m.description ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TalentoCustodia',
  data() {
    return {
      colaboradores: [],
      searchColaborador: '',
      selected: null,
      loadingLista: true,
      loadingDetalle: false,
      searchTimeout: null,
    };
  },
  mounted() {
    this.loadLista();
  },
  methods: {
    debounceBuscar() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.loadLista(), 350);
    },
    async loadLista() {
      this.loadingLista = true;
      this.selected = null;
      try {
        const { data } = await axios.get('/talento/api/colaboradores', {
          params: { search: this.searchColaborador, per_page: 60, status: 'active' }
        });
        this.colaboradores = data?.data ?? [];
      } finally {
        this.loadingLista = false;
      }
    },
    async selectColaborador(col) {
      this.loadingDetalle = true;
      this.selected = { colaborador: { name: col.user?.name, type: col.type }, stocks: [], movements: [] };
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${col.id}/custodia`);
        this.selected = data ?? this.selected;
      } finally {
        this.loadingDetalle = false;
      }
    },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>
