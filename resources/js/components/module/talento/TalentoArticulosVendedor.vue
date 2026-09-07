<template>
  <div class="talento-articulos-vendedor">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-boxes me-2 text-primary"></i>Catálogo de Artículos de Vendedor</h5>
    </div>
    <p class="text-muted small mb-3">
      Catálogo global de artículos asignados a vendedores (solo lectura, reusa el inventario de Vendedores).
    </p>

    <div class="row mb-3">
      <div class="col-md-5">
        <input v-model="search" @input="debounceBuscar" type="text"
               class="form-control" placeholder="Buscar por artículo o vendedor...">
      </div>
    </div>

    <div v-if="loading" class="text-center py-3">
      <div class="spinner-border spinner-border-sm text-primary"></div>
    </div>
    <div v-else>
      <div v-if="!items.length" class="alert alert-light text-muted">
        No hay artículos asignados a vendedores.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Artículo</th>
              <th>Tipo</th>
              <th>Categoría</th>
              <th>Cantidad</th>
              <th>Condición</th>
              <th>Vendedor</th>
              <th>Asignado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="it in items" :key="it.stock_id">
              <td>{{ it.item_name }}</td>
              <td>{{ it.tipo ?? '—' }}</td>
              <td>{{ it.categoria ?? '—' }}</td>
              <td>{{ it.current_stock }}</td>
              <td>{{ it.condition ?? '—' }}</td>
              <td>{{ it.seller_name || '—' }}</td>
              <td class="small">{{ formatDate(it.assigned_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="lastPage > 1" class="d-flex justify-content-between align-items-center mt-2">
        <span class="small text-muted">Página {{ page }} de {{ lastPage }} ({{ total }} artículos)</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="page <= 1" @click="goPage(page - 1)">Anterior</button>
          <button class="btn btn-outline-secondary" :disabled="page >= lastPage" @click="goPage(page + 1)">Siguiente</button>
        </div>
      </nav>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TalentoArticulosVendedor',
  data() {
    return {
      items: [],
      search: '',
      page: 1,
      lastPage: 1,
      total: 0,
      loading: true,
      searchTimeout: null,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    debounceBuscar() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.goPage(1), 350);
    },
    goPage(page) {
      this.page = page;
      this.load();
    },
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/articulos-vendedor', {
          params: { search: this.search, page: this.page, per_page: 50 }
        });
        this.items = data?.data ?? [];
        this.lastPage = data?.last_page ?? 1;
        this.total = data?.total ?? this.items.length;
      } finally {
        this.loading = false;
      }
    },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>
