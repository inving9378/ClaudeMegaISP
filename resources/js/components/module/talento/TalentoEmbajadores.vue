<template>
  <div class="talento-embajadores">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="mb-0"><i class="fa fa-link me-2 text-primary"></i>Colaboradores con Roles Múltiples</h5>
      <div class="d-flex gap-2">
        <input v-model="search" @input="debounceLoad" type="text" class="form-control form-control-sm" placeholder="Buscar colaborador…" style="width:220px">
      </div>
    </div>

    <div class="alert alert-light small mb-3 py-2">
      <i class="fa fa-info-circle me-1 text-primary"></i>
      Vista <strong>solo lectura</strong>. Un colaborador puede ser también cliente/embajador o vendedor. Esta pantalla muestra ese vínculo sin modificar ninguna tabla externa.
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <div v-else-if="!items.length" class="alert alert-light text-center">Sin colaboradores encontrados</div>
    <div v-else>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Colaborador</th>
              <th>Tipo</th>
              <th>Es embajador</th>
              <th>Referidos</th>
              <th>Comisiones acumuladas</th>
              <th>Es vendedor</th>
              <th>Ventas 4 sem.</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="col in items" :key="col.id">
              <td>
                <div class="fw-semibold small">{{ col.user?.name }}</div>
                <div class="small text-muted">{{ col.user?.email }}</div>
              </td>
              <td><span class="badge" :class="col.type==='interno'?'bg-primary-subtle text-primary':'bg-secondary-subtle text-secondary'">{{ col.type }}</span></td>
              <td>
                <span v-if="col._embajador === null" class="text-muted small">—</span>
                <span v-else-if="col._embajador?.is_ambassador" class="badge bg-success-subtle text-success">
                  <i class="fa fa-check me-1"></i>Sí
                </span>
                <span v-else class="badge bg-light text-secondary">No</span>
              </td>
              <td class="small">{{ col._embajador?.total_referrals ?? '—' }}</td>
              <td class="small text-success">
                <span v-if="col._embajador?.is_ambassador">${{ fmt2(col._embajador.total_commissions) }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="col._seller === null" class="text-muted small">—</span>
                <span v-else-if="col._seller?.is_seller" class="badge bg-info-subtle text-info">
                  <i class="fa fa-store me-1"></i>Sí
                </span>
                <span v-else class="badge bg-light text-secondary">No</span>
              </td>
              <td class="small">
                <span v-if="col._seller?.is_seller">${{ fmt2(col._seller.last_4w_commission) }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <button @click="loadDetail(col)" class="btn btn-xs btn-outline-primary" title="Cargar detalle">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="pagination.last_page > 1" class="mt-3 d-flex justify-content-center">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
            <a class="page-link" href="#" @click.prevent="loadPage(pagination.current_page - 1)">‹</a>
          </li>
          <li class="page-item active"><span class="page-link">{{ pagination.current_page }} / {{ pagination.last_page }}</span></li>
          <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
            <a class="page-link" href="#" @click.prevent="loadPage(pagination.current_page + 1)">›</a>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Detalle modal -->
    <div class="modal fade" id="modalEmbajadorDetail" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="detailCol">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-user-circle me-2"></i>{{ detailCol.user?.name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <!-- Embajador -->
              <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                  <div class="card-header small fw-semibold bg-success-subtle text-success"><i class="fa fa-share-alt me-1"></i>Como Embajador</div>
                  <div class="card-body small">
                    <div v-if="detailEmbajador === null" class="text-muted">Cargando…</div>
                    <div v-else-if="!detailEmbajador.is_ambassador" class="text-muted">{{ detailEmbajador.message }}</div>
                    <div v-else>
                      <div class="mb-2"><strong>Referidos totales:</strong> {{ detailEmbajador.total_referrals }}</div>
                      <div class="mb-2"><strong>Comisiones pagadas:</strong> ${{ fmt2(detailEmbajador.total_commissions) }}</div>
                      <div class="mb-2"><strong>Recompensas pendientes:</strong> ${{ fmt2(detailEmbajador.pending_rewards) }}</div>
                      <div v-if="detailEmbajador.recent_referrals?.length" class="mt-2">
                        <div class="fw-semibold mb-1">Referidos recientes:</div>
                        <div v-for="r in detailEmbajador.recent_referrals.slice(0,5)" :key="r.id" class="border-bottom py-1 d-flex justify-content-between">
                          <span>{{ r.referred_client?.name ?? 'Cliente #'+r.referred_client_id }}</span>
                          <span class="badge" :class="r.status==='active'?'bg-success-subtle text-success':'bg-secondary-subtle text-secondary'">{{ r.status }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Vendedor -->
              <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                  <div class="card-header small fw-semibold bg-info-subtle text-info"><i class="fa fa-store me-1"></i>Como Vendedor</div>
                  <div class="card-body small">
                    <div v-if="detailSeller === null" class="text-muted">Cargando…</div>
                    <div v-else-if="!detailSeller.is_seller" class="text-muted">{{ detailSeller.message }}</div>
                    <div v-else>
                      <div class="mb-2"><strong>% comisión:</strong> {{ detailSeller.commission_pct }}%</div>
                      <div class="mb-2"><strong>Transacciones (4 sem.):</strong> {{ detailSeller.last_4w_txns }}</div>
                      <div class="mb-2"><strong>Comisión generada (4 sem.):</strong> ${{ fmt2(detailSeller.last_4w_commission) }}</div>
                      <div class="alert alert-warning py-1 mt-2 small">
                        <i class="fa fa-info-circle me-1"></i>Las reglas de comisión de ventas se gestionan en el módulo de Vendedores (independiente).
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoEmbajadores',
  data() {
    return {
      items: [],
      loading: false,
      search: '',
      searchTimeout: null,
      pagination: { current_page: 1, last_page: 1 },
      detailCol: null,
      detailEmbajador: null,
      detailSeller: null,
      detailModal: null,
    };
  },
  mounted() {
    this.load(1);
  },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(1), 350);
    },
    async load(page = 1) {
      this.loading = true;
      const r = await axios.get('/talento/api/colaboradores', { params: { page, per_page: 20, search: this.search } }).catch(() => null);
      this.loading = false;
      const data = r?.data?.data ?? [];
      this.pagination = { current_page: r?.data?.current_page ?? 1, last_page: r?.data?.last_page ?? 1 };
      // Init with null cross-links (lazy loaded)
      this.items = data.map(c => ({ ...c, _embajador: undefined, _seller: undefined }));
      // Lazy load cross-links for visible rows
      this.items.forEach(col => this.loadCrossLinks(col));
    },
    loadPage(p) { this.load(p); },
    async loadCrossLinks(col) {
      const [embRes, selRes] = await Promise.all([
        axios.get(`/talento/api/colaboradores/${col.id}/embajador-data`).catch(() => null),
        axios.get(`/talento/api/colaboradores/${col.id}/seller-data`).catch(() => null),
      ]);
      col._embajador = embRes?.data ?? null;
      col._seller    = selRes?.data ?? null;
    },
    async loadDetail(col) {
      this.detailCol = col;
      this.detailEmbajador = null;
      this.detailSeller = null;
      if (!this.detailModal) {
        await this.$nextTick();
        this.detailModal = new bootstrap.Modal(document.getElementById('modalEmbajadorDetail'));
      }
      this.detailModal.show();
      const [embRes, selRes] = await Promise.all([
        axios.get(`/talento/api/colaboradores/${col.id}/embajador-data`).catch(() => null),
        axios.get(`/talento/api/colaboradores/${col.id}/seller-data`).catch(() => null),
      ]);
      this.detailEmbajador = embRes?.data ?? { is_ambassador: false, message: 'Error al cargar' };
      this.detailSeller    = selRes?.data ?? { is_seller: false, message: 'Error al cargar' };
    },
    fmt2(v) { return v != null ? Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; },
  },
};
</script>
