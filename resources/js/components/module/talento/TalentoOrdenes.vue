<template>
  <div class="talento-ordenes tc-wrap" :class="{ 'tc-dark': darkMode }">

    <div class="tc-card">
      <div class="tc-cardhead d-flex flex-wrap align-items-center justify-content-between gap-2 p-3">
        <h5 class="tc-h1 mb-0"><i class="fa fa-clipboard-list me-2"></i>Órdenes de Trabajo</h5>
        <button @click="openCreate" class="tc-btn tc-btn-ok">
          <i class="fa fa-plus me-1"></i> Nueva orden
        </button>
      </div>

      <div class="p-3">
        <!-- Filtros -->
        <div class="row g-2 mb-3">
          <div class="col-6 col-sm-6 col-md-2">
            <input v-model="filters.search" @input="debounceLoad" type="text"
                   class="form-control form-control-sm" placeholder="Buscar colaborador…">
          </div>
          <div class="col-6 col-sm-6 col-md-2">
            <input v-model="filters.prospecto" @input="debounceLoad" type="text"
                   class="form-control form-control-sm" placeholder="Buscar prospecto…">
          </div>
          <div class="col-6 col-sm-6 col-md-2">
            <select v-model="filters.status" @change="load" class="form-select form-select-sm">
              <option value="">Todos los estados</option>
              <option value="pending">Pendiente</option>
              <option value="in_progress">En curso</option>
              <option value="completed">Completada</option>
              <option value="validated">Validada</option>
              <option value="cancelled">Cancelada</option>
            </select>
          </div>
          <div class="col-6 col-sm-6 col-md-2">
            <select v-model="filters.type_id" @change="load" class="form-select form-select-sm">
              <option value="">Todos los tipos</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div class="col-6 col-sm-3 col-md-2">
            <input v-model="filters.from" @change="load" type="date" class="form-control form-control-sm" title="Desde">
          </div>
          <div class="col-6 col-sm-3 col-md-2">
            <input v-model="filters.to" @change="load" type="date" class="form-control form-control-sm" title="Hasta">
          </div>
        </div>

        <!-- Tabla -->
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Colaborador</th>
                <th>Prospecto</th>
                <th>Tipo</th>
                <th class="text-center">Pts</th>
                <th>Agendada</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in orders" :key="o.id">
                <td class="text-muted small">{{ o.id }}</td>
                <td>
                  <div class="fw-semibold small">{{ fullName(o.colaborador?.user) }}</div>
                </td>
                <td>
                  <span v-if="o.prospecto?.nombre" class="small">
                    <i class="fa fa-user-clock text-muted me-1" title="Prospecto CRM (aún no es cliente)"></i>{{ o.prospecto.nombre }}
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td>
                  <span class="tc-status is-slate">{{ o.type?.name }}</span>
                  <i v-if="o.is_billable" class="fa fa-dollar-sign text-success ms-1" title="Pagable"></i>
                </td>
                <td class="text-center fw-bold">{{ o.points }}</td>
                <td class="small">{{ formatDatetime(o.scheduled_at) }}</td>
                <td><span class="tc-status" :class="statusBadge(o.status)">{{ statusLabel(o.status) }}</span></td>
                <td class="text-end">
                  <button @click="viewOrder(o)" class="tc-btn tc-btn-info me-1">Ver</button>
                  <button v-if="o.status === 'completed'" @click="openValidate(o)" class="tc-btn tc-btn-ok me-1">Validar</button>
                  <button v-if="canAdvance(o.status)" @click="advanceStatus(o)" class="tc-btn tc-btn-primary">
                    {{ nextStatusLabel(o.status) }}
                  </button>
                </td>
              </tr>
              <tr v-if="!orders.length">
                <td colspan="8" class="text-center text-muted py-4">No hay órdenes que mostrar.</td>
              </tr>
            </tbody>
          </table>
          <div v-if="pagination.last_page > 1" class="d-flex justify-content-end">
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
                <button class="page-link" @click="goPage(pagination.current_page-1)">‹</button>
              </li>
              <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{active: p === pagination.current_page}">
                <button class="page-link" @click="goPage(p)">{{ p }}</button>
              </li>
              <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
                <button class="page-link" @click="goPage(pagination.current_page+1)">›</button>
              </li>
            </ul></nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal crear orden -->
    <div v-if="createModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nueva Orden de Trabajo</h5>
            <button @click="createModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <input v-model="colSearch" @input="debounceColSearch" type="text" class="form-control"
                       placeholder="Buscar colaborador…">
                <ul v-if="colSuggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:180px;overflow-y:auto">
                  <li v-for="c in colSuggestions" :key="c.id" @click="selectCol(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">
                    {{ fullName(c.user) }} <span class="text-muted">· {{ c.type }}</span>
                  </li>
                </ul>
                <div v-if="createModal.colaborador_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.colaborador_name }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo de orden <span class="text-danger">*</span></label>
                <select v-model="createModal.type_id" @change="onTypeChange" class="form-select tc-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="t in activeTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div v-if="selectedType" class="mt-1 small text-muted">
                  {{ selectedType.points }} pts
                  · {{ selectedType.is_billable ? '💲 Pagable' : 'No pagable' }}
                  {{ selectedType.requires_validation ? '· Requiere validación' : '' }}
                </div>
              </div>
              <div class="col-md-6 position-relative">
                <label class="form-label">Prospecto (CRM)</label>
                <div class="input-group">
                  <input v-model="prospSearch" @input="debounceProspSearch" @focus="openProspList"
                         @blur="closeProspListDelayed"
                         type="text" class="form-control" placeholder="Buscar prospecto…"
                         :disabled="!!createModal.crm_lead_id">
                  <button v-if="createModal.crm_lead_id" @click="clearProspecto" type="button"
                          class="btn btn-outline-secondary" title="Quitar prospecto">
                    <i class="fa fa-times"></i>
                  </button>
                </div>
                <div v-if="prospListOpen && (prospSuggestions.propios.length || prospSuggestions.generales.length)"
                     class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:220px;overflow-y:auto;width:100%">
                  <template v-if="prospSuggestions.propios.length">
                    <li class="list-group-item prosp-group-header small fw-semibold py-1">
                      Prospectos de {{ createModal.colaborador_name || 'este colaborador' }}
                    </li>
                    <li v-for="p in prospSuggestions.propios" :key="'p'+p.id" @click="selectProspecto(p)"
                        class="list-group-item list-group-item-action small cursor-pointer">
                      {{ p.nombre }} <span class="text-muted">· {{ p.telefono }} · {{ p.status }}</span>
                    </li>
                  </template>
                  <template v-if="prospSuggestions.generales.length">
                    <li class="list-group-item prosp-group-header small fw-semibold py-1">Otros prospectos</li>
                    <li v-for="p in prospSuggestions.generales" :key="'g'+p.id" @click="selectProspecto(p)"
                        class="list-group-item list-group-item-action small cursor-pointer">
                      {{ p.nombre }} <span class="text-muted">· {{ p.telefono }} · {{ p.status }}</span>
                    </li>
                  </template>
                </div>
                <div v-if="createModal.crm_lead_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.crm_lead_name }}
                </div>
                <div v-if="createModal.client_id && createModal.crm_lead_id" class="mt-1 small text-danger">
                  Una orden es para un cliente o un prospecto, no ambos.
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Agendar para</label>
                <input v-model="createModal.scheduled_at" type="datetime-local" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea v-model="createModal.notes" class="form-control" rows="2"></textarea>
              </div>
              <div v-if="createModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ createModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="createModal.show=false" class="btn btn-secondary" :disabled="createModal.saving">Cancelar</button>
            <button @click="saveOrder" class="btn btn-primary" :disabled="createModal.saving">
              <span v-if="createModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Crear orden</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal ver detalle -->
    <div v-if="detail.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Orden #{{ detail.order?.id }} — {{ fullName(detail.order?.colaborador?.user) }}</h5>
            <button @click="detail.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="detail.order">
            <div class="row g-3">
              <div class="col-md-4"><strong>Tipo:</strong> {{ detail.order.type?.name }}</div>
              <div class="col-md-2"><strong>Pts:</strong> {{ detail.order.points }}</div>
              <div class="col-md-3"><strong>Estado:</strong> <span class="tc-status" :class="statusBadge(detail.order.status)">{{ statusLabel(detail.order.status) }}</span></div>
              <div class="col-md-3"><strong>Agendada:</strong> {{ formatDatetime(detail.order.scheduled_at) }}</div>
              <div class="col-md-4" v-if="detail.order.prospecto?.nombre">
                <strong>Prospecto (CRM):</strong> {{ detail.order.prospecto.nombre }}
              </div>
              <div class="col-md-4" v-if="detail.order.validated_at">
                <strong>Validada:</strong> {{ formatDatetime(detail.order.validated_at) }}<br>
                <small class="text-muted">por {{ detail.order.validated_by?.name }}</small>
              </div>
              <div class="col-12" v-if="detail.order.notes"><strong>Notas:</strong> {{ detail.order.notes }}</div>
            </div>
            <div v-if="detail.order.activities?.length" class="mt-3">
              <h6>Actividades</h6>
              <ul class="list-group list-group-flush">
                <li v-for="a in detail.order.activities" :key="a.id" class="list-group-item px-0 py-1 small">
                  {{ a.description }}
                  <span v-if="a.duration_minutes" class="text-muted ms-1">({{ a.duration_minutes }} min)</span>
                </li>
              </ul>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="detail.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal validar -->
    <div v-if="validateModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Validar orden</h5>
            <button @click="validateModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p>¿Confirmar validación de la orden <strong>#{{ validateModal.order?.id }}</strong> — <em>{{ validateModal.order?.type?.name }}</em> ({{ validateModal.order?.points }} pts)?</p>
            <p class="text-muted small mb-0">Una vez validada se contabiliza para la liquidación semanal.</p>
          </div>
          <div class="modal-footer">
            <button @click="validateModal.show=false" class="btn btn-secondary" :disabled="validateModal.saving">Cancelar</button>
            <button @click="confirmValidate" class="btn btn-success" :disabled="validateModal.saving">
              <span v-if="validateModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              Sí, validar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";

export default {
  name: 'TalentoOrdenes',
  setup() {
    return { darkMode };
  },
  data() {
    return {
      orders: [],
      types: [],
      loading: true,
      pagination: { current_page: 1, last_page: 1 },
      filters: { search: '', prospecto: '', status: '', type_id: '', from: '', to: '' },
      searchTimeout: null,
      createModal: { show: false, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '', crm_lead_id: null, crm_lead_name: '' },
      detail: { show: false, order: null },
      validateModal: { show: false, order: null, saving: false },
      colSearch: '',
      colSuggestions: [],
      colSearchTimeout: null,
      // #9991201/#9991204: dropdown de prospecto CRM en el modal de crear orden.
      prospSearch: '',
      prospSuggestions: { propios: [], generales: [] },
      prospListOpen: false,
      prospSearchTimeout: null,
      prospCloseTimeout: null,
    };
  },
  computed: {
    activeTypes() { return this.types.filter(t => t.active); },
    selectedType() { return this.types.find(t => t.id === this.createModal.type_id) || null; },
  },
  mounted() {
    this.loadTypes();
    this.load();
  },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/ordenes', { params: { ...this.filters, page } });
        this.orders = data?.data ?? [];
        this.pagination = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally { this.loading = false; }
    },
    async loadTypes() {
      const { data } = await axios.get('/talento/api/order-types');
      this.types = data ?? [];
    },
    goPage(p) { if (p >= 1 && p <= this.pagination.last_page) this.load(p); },
    openCreate() {
      this.createModal = { show: true, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '', crm_lead_id: null, crm_lead_name: '' };
      this.colSearch = '';
      this.colSuggestions = [];
      this.prospSearch = '';
      this.prospSuggestions = { propios: [], generales: [] };
      this.prospListOpen = false;
    },
    debounceColSearch() {
      clearTimeout(this.colSearchTimeout);
      this.colSearchTimeout = setTimeout(() => this.searchCols(), 300);
    },
    async searchCols() {
      if (!this.colSearch.trim()) { this.colSuggestions = []; return; }
      const { data } = await axios.get('/talento/api/colaboradores', { params: { search: this.colSearch, per_page: 10 } });
      this.colSuggestions = data?.data ?? [];
    },
    // Varios colaboradores comparten el mismo nombre de pila (ej. 3 "GUADALUPE"
    // distintas, cada una con su propio user_id/colaborador_id real) — solo el
    // primer nombre los hacía ver como duplicados en el dropdown/tabla. Nombre
    // completo con apellidos para diferenciarlos (mismo patrón que el resto del
    // sistema). Acepta cualquier objeto user-like (name/father_last_name/
    // mother_last_name), null-safe.
    fullName(user) {
      return [user?.name, user?.father_last_name, user?.mother_last_name]
        .filter(Boolean).join(' ');
    },
    selectCol(c) {
      this.createModal.colaborador_id = c.id;
      this.createModal.colaborador_name = this.fullName(c.user);
      this.colSearch = this.fullName(c.user);
      this.colSuggestions = [];
      // El colaborador cambió → los "propios" del prospecto quedaron obsoletos.
      this.prospSuggestions = { propios: [], generales: [] };
    },
    onTypeChange() { /* selectedType computed updates automatically */ },
    // ── Prospecto CRM (#9991201/#9991204) ──────────────────────────────────
    openProspList() {
      clearTimeout(this.prospCloseTimeout);
      this.prospListOpen = true;
      if (!this.prospSuggestions.propios.length && !this.prospSuggestions.generales.length) {
        this.searchProspectos();
      }
    },
    closeProspListDelayed() {
      // Delay para que el click en un <li> registre antes de cerrar por blur.
      this.prospCloseTimeout = setTimeout(() => { this.prospListOpen = false; }, 200);
    },
    debounceProspSearch() {
      clearTimeout(this.prospSearchTimeout);
      this.prospSearchTimeout = setTimeout(() => this.searchProspectos(), 300);
    },
    async searchProspectos() {
      const { data } = await axios.get('/talento/api/prospectos-crm', {
        params: {
          colaborador_id: this.createModal.colaborador_id || undefined,
          search: this.prospSearch.trim() || undefined,
        },
      });
      this.prospSuggestions = { propios: data?.propios ?? [], generales: data?.generales ?? [] };
    },
    selectProspecto(p) {
      this.createModal.crm_lead_id = p.id;
      this.createModal.crm_lead_name = p.nombre;
      this.prospSearch = p.nombre;
      this.prospListOpen = false;
    },
    clearProspecto() {
      this.createModal.crm_lead_id = null;
      this.createModal.crm_lead_name = '';
      this.prospSearch = '';
      this.prospSuggestions = { propios: [], generales: [] };
    },
    async saveOrder() {
      this.createModal.error = '';
      if (!this.createModal.colaborador_id) { this.createModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.createModal.type_id) { this.createModal.error = 'Selecciona un tipo de orden.'; return; }
      this.createModal.saving = true;
      try {
        await axios.post('/talento/api/ordenes', {
          colaborador_id: this.createModal.colaborador_id,
          type_id:        this.createModal.type_id,
          scheduled_at:   this.createModal.scheduled_at || null,
          notes:          this.createModal.notes || null,
          crm_lead_id:    this.createModal.crm_lead_id || null,
        });
        this.createModal.show = false;
        this.load();
      } catch (e) {
        this.createModal.error = e.response?.data?.error ?? e.response?.data?.message ?? 'Error al crear la orden.';
      } finally { this.createModal.saving = false; }
    },
    async viewOrder(o) {
      const { data } = await axios.get(`/talento/api/ordenes/${o.id}`);
      this.detail = { show: true, order: data };
    },
    openValidate(o) { this.validateModal = { show: true, order: o, saving: false }; },
    async confirmValidate() {
      this.validateModal.saving = true;
      try {
        await axios.post(`/talento/api/ordenes/${this.validateModal.order.id}/validate`);
        this.validateModal.show = false;
        this.load();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al validar.');
      } finally { this.validateModal.saving = false; }
    },
    canAdvance(status) { return ['pending','in_progress'].includes(status); },
    nextStatusLabel(status) { return { pending: 'Iniciar', in_progress: 'Completar' }[status] ?? ''; },
    async advanceStatus(o) {
      const next = { pending: 'in_progress', in_progress: 'completed' }[o.status];
      if (!next) return;
      await axios.put(`/talento/api/ordenes/${o.id}/status`, { status: next });
      this.load();
    },
    statusBadge(s) {
      // Mapeo Torre (#9991200): completed queda is-warn porque aún requiere validación
      // (is-ok se reserva para el estado final positivo, validated).
      return { pending: 'is-slate', in_progress: 'is-info', completed: 'is-warn',
               validated: 'is-ok', cancelled: 'is-bad' }[s] ?? 'is-slate';
    },
    statusLabel(s) {
      return { pending: 'Pendiente', in_progress: 'En curso', completed: 'Completada',
               validated: 'Validada', cancelled: 'Cancelada' }[s] ?? s;
    },
    formatDatetime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

<style scoped>
/* Filtros (buscador/selects/fechas): #9991200 dejó las clases Bootstrap
   (form-control/form-select) sin retocar — se veían "crudas" frente al resto
   ya con tema Torre (mismo hallazgo que el buscador de Vendedores/Artículos).
   Recoloreados aquí con los tokens --tc-*, sin depender de :deep() porque
   son elementos nativos del propio template (no de un componente hijo). */
.talento-ordenes .form-control,
.talento-ordenes .form-select {
  border-color: var(--tc-line, #e5e7eb);
  color: var(--tc-ink, #111827);
  background-color: var(--tc-surface, #fff);
}
.talento-ordenes .form-control::placeholder {
  color: var(--tc-muted, #6b7280);
}
.talento-ordenes .form-control:focus,
.talento-ordenes .form-select:focus {
  border-color: var(--tc-accent, #0d9488);
  box-shadow: 0 0 0 0.2rem rgba(13, 148, 136, 0.15);
}

/* Botones "Ver"/"Iniciar-Completar": el tema Torre solo trae tc-btn-ok
   (relleno, acento teal) y tc-btn-seg/warn/bad (outline). "Ver" y el botón
   de avanzar estado usaban tc-btn-seg (outline azul) los dos — se ven
   iguales pese a ser acciones distintas (una de solo lectura, otra que
   cambia el estado de la orden). Se agregan dos variantes RELLENAS,
   locales a esta pantalla (no se tocó _torre-theme.scss compartido):
   - tc-btn-info: relleno con --tc-info (mismo azul de "Interno" en
     Vendedores) — para la acción de solo-lectura "Ver".
   - tc-btn-primary: relleno índigo, un tono distinto de --tc-accent
     (que ya usan "Nueva orden"/"Validar") y de --tc-info, para que
     "Iniciar"/"Completar" (la acción que de verdad avanza la orden)
     se note como la más importante de la fila. */
.talento-ordenes .tc-btn-info {
  background: var(--tc-info, #2563eb);
  border-color: var(--tc-info, #2563eb);
  color: #fff;
}
.talento-ordenes .tc-btn-info:hover {
  filter: brightness(1.08);
  color: #fff;
}
.talento-ordenes .tc-btn-primary {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
}
.talento-ordenes .tc-btn-primary:hover {
  filter: brightness(1.1);
  color: #fff;
}
.talento-ordenes.tc-dark .tc-btn-primary {
  background: #6366f1;
  border-color: #6366f1;
}

/* Paginación: el tema Torre no trae reglas para .pagination (#9991200),
   así que se recolorea aquí con los mismos tokens --tc-* en vez de dejar
   el azul default de Bootstrap. */
.talento-ordenes :deep(.page-link) {
  border-color: var(--tc-line, #e5e7eb);
  color: var(--tc-ink, #111827);
  background: var(--tc-surface, #fff);
}
.talento-ordenes :deep(.page-link:hover) {
  background: var(--tc-bg2, #f8fafc);
  color: var(--tc-accent, #0d9488);
}
.talento-ordenes :deep(.page-item.active .page-link) {
  background: var(--tc-accent, #0d9488);
  border-color: var(--tc-accent, #0d9488);
  color: #fff;
}
.talento-ordenes :deep(.page-item.disabled .page-link) {
  color: var(--tc-muted, #6b7280);
  background: var(--tc-bg2, #f8fafc);
}

/* "Tipo de orden": mismo .form-select genérico de arriba (borde/fondo con
   --tc-*), pero pedido explícitamente con más cuidado (foco/chevron/radius)
   que el resto — se ve "de fábrica" del navegador frente a los tc-btn/
   tc-status ya rediseñados alrededor. */
.talento-ordenes .tc-select {
  border-radius: 9px;
  padding: 0.45rem 2.1rem 0.45rem 0.75rem;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M8 11 3 6h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.7rem center;
  background-size: 12px;
}
.talento-ordenes .tc-select:focus {
  border-color: var(--tc-accent, #0d9488);
  box-shadow: 0 0 0 0.2rem rgba(13, 148, 136, 0.15);
  outline: none;
}
.talento-ordenes.tc-dark .tc-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%239aa7bd' d='M8 11 3 6h10z'/%3E%3C/svg%3E");
}

/* Grupo "Prospectos de X" / "Otros prospectos" dentro del dropdown de
   prospecto: NO se usa .bg-light (Bootstrap, sin tratamiento oscuro
   verificado) — se recolorea con los tokens --tc-* como el resto. */
.talento-ordenes .prosp-group-header {
  background: var(--tc-bg2, #f8fafc);
  color: var(--tc-muted, #6b7280);
}
</style>
