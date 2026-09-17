<template>
  <div class="talento-asistencia tc-wrap" :class="{ 'tc-dark': darkMode }">

    <div class="tc-card">
      <div class="tc-cardhead d-flex flex-wrap align-items-center justify-content-between gap-2 p-3">
        <h5 class="tc-h1"><i class="fa fa-calendar-check me-2"></i>Asistencia</h5>
        <div class="d-flex align-items-center gap-2">
          <button @click="setRange('all')"   class="tc-btn" :class="range==='all'   ? 'tc-btn-info' : 'tc-btn-seg'">Todos</button>
          <button @click="setRange('today')" class="tc-btn" :class="range==='today' ? 'tc-btn-info' : 'tc-btn-seg'">Hoy</button>
          <button @click="setRange('week')"  class="tc-btn" :class="range==='week'  ? 'tc-btn-info' : 'tc-btn-seg'">Esta semana</button>
          <span v-if="flaggedCount > 0" class="tc-status is-warn">
            <i class="fa fa-flag me-1"></i>{{ flaggedCount }} flagged
          </span>
        </div>
      </div>

      <div class="p-3">
        <!-- Filtros -->
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <input v-model="filters.search" @input="debounceLoad" type="text"
                   class="form-control form-control-sm" placeholder="Buscar colaborador…">
          </div>
          <div class="col-md-2">
            <select v-model="filters.status" @change="load" class="form-select form-select-sm tc-select">
              <option value="">Todos</option>
              <option value="open">Abierta</option>
              <option value="closed">Cerrada</option>
              <option value="flagged">Flagged</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.from" @change="load" type="date" class="form-control form-control-sm" title="Desde">
          </div>
          <div class="col-md-2">
            <input v-model="filters.to" @change="load" type="date" class="form-control form-control-sm" title="Hasta">
          </div>
          <div class="col-md-2 d-flex align-items-center gap-2">
            <div class="form-check mb-0">
              <input v-model="filters.flagged" @change="load" type="checkbox" class="form-check-input" id="onlyFlagged">
              <label class="form-check-label small" for="onlyFlagged">Solo flagged</label>
            </div>
          </div>
        </div>

        <!-- Tabla -->
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Colaborador</th>
                <th>Entrada</th>
                <th>Salida esp.</th>
                <th>Salida real</th>
                <th>Sitio</th>
                <th class="text-center">Flag</th>
                <th class="text-center">Día</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="att in attendances" :key="att.id" :class="{'asis-row-flag': att.check_in_flagged}">
                <td class="fw-semibold small">{{ fullName(att.colaborador?.user) }}</td>
                <td class="small">{{ fmt(att.check_in_at) }}</td>
                <td class="small tc-muted-txt">{{ att.expected_end_at ? fmt(att.expected_end_at) : '—' }}</td>
                <td class="small">
                  <span v-if="att.check_out_at">{{ fmt(att.check_out_at) }}</span>
                  <span v-else class="tc-status" :class="openStatusVariant(att.status)">{{ openStatusLabel(att.status) }}</span>
                </td>
                <td class="small">{{ att.site?.name ?? '—' }}</td>
                <td class="text-center">
                  <span v-if="att.check_in_flagged" class="tc-status is-warn" :title="att.check_in_flag_reason">
                    <i class="fa fa-flag"></i>
                  </span>
                  <span v-else class="text-success"><i class="fa fa-check-circle"></i></span>
                </td>
                <td class="text-center">
                  <span class="tc-status" :class="dayBadge(att.day_type)">{{ dayLabel(att.day_type) }}</span>
                </td>
                <td>
                  <button @click="openDetail(att)" class="tc-btn tc-btn-info">Ver</button>
                </td>
              </tr>
              <tr v-if="!attendances.length">
                <td colspan="8" class="text-center tc-muted-txt py-4">Sin registros para este período.</td>
              </tr>
            </tbody>
          </table>
          <div v-if="pagination.last_page > 1" class="d-flex justify-content-end">
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item" :class="{disabled: pagination.current_page<=1}">
                <button class="page-link" @click="goPage(pagination.current_page-1)">‹</button>
              </li>
              <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{active: p===pagination.current_page}">
                <button class="page-link" @click="goPage(p)">{{ p }}</button>
              </li>
              <li class="page-item" :class="{disabled: pagination.current_page>=pagination.last_page}">
                <button class="page-link" @click="goPage(pagination.current_page+1)">›</button>
              </li>
            </ul></nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal detalle -->
    <div v-if="detail.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header" :class="detail.att?.check_in_flagged ? 'tc-modal-header-warn' : ''">
            <h5 class="modal-title">
              {{ fullName(detail.att?.colaborador?.user) }} — {{ fmtDate(detail.att?.check_in_at) }}
            </h5>
            <button @click="detail.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="detail.att">
            <div class="row g-3 mb-3">
              <div class="col-md-4"><strong>Check-in:</strong> {{ fmt(detail.att.check_in_at) }}</div>
              <div class="col-md-4"><strong>Salida esperada:</strong> {{ detail.att.expected_end_at ? fmt(detail.att.expected_end_at) : '—' }}</div>
              <div class="col-md-4"><strong>Check-out:</strong> {{ detail.att.check_out_at ? fmt(detail.att.check_out_at) : '—' }}</div>
              <div class="col-md-6">
                <strong>Sitio:</strong> {{ detail.att.site?.name ?? '—' }}
                <span class="ms-2 tc-status" :class="detail.att.check_in_within_geofence ? 'is-ok' : 'is-slate'">
                  {{ detail.att.check_in_within_geofence ? 'Dentro de geocerca' : 'Fuera de geocerca' }}
                </span>
              </div>
              <div class="col-md-6" v-if="detail.att.check_in_flagged">
                <span class="tc-status is-warn"><i class="fa fa-flag me-1"></i>{{ detail.att.check_in_flag_reason }}</span>
              </div>
            </div>

            <!-- Clasificación del día (supervisor) -->
            <div class="row g-2 align-items-center mb-3">
              <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Tipo de día</label>
                <select v-model="detail.dayType" class="form-select form-select-sm tc-select">
                  <option value="worked">Trabajado</option>
                  <option value="no_work_company">No laborable (empresa)</option>
                  <option value="absent">Falta del colaborador</option>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label form-label-sm mb-1">Notas</label>
                <input v-model="detail.notes" type="text" class="form-control form-control-sm">
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <button @click="saveDetailAdmin" class="tc-btn tc-btn-ok" :disabled="detail.saving">
                  <span v-if="detail.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
                  Guardar
                </button>
              </div>
            </div>

            <!-- Extensiones -->
            <h6 class="mb-2">Extensiones de turno</h6>
            <div v-if="!(detail.att.extensions?.length)" class="tc-muted-txt mb-2">Sin extensiones registradas.</div>
            <ul v-else class="list-group list-group-flush mb-2">
              <li v-for="e in detail.att.extensions" :key="e.id" class="list-group-item px-0 py-1 small d-flex justify-content-between">
                <span><strong>+{{ e.minutes }} min</strong> · {{ extLabel(e.reason_category) }}
                  <span v-if="e.reason_text" class="tc-muted-txt ms-1">"{{ e.reason_text }}"</span>
                </span>
                <span class="tc-muted-txt">{{ fmtDate(e.created_at) }}</span>
              </li>
            </ul>

            <!-- Agregar extensión -->
            <div class="asis-subpanel p-3">
              <div class="row g-2 align-items-end">
                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-1">Minutos</label>
                  <input v-model.number="ext.minutes" type="number" min="1" max="480" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm mb-1">Motivo</label>
                  <select v-model="ext.reason_category" class="form-select form-select-sm tc-select">
                    <option value="overtime">Tiempo extra</option>
                    <option value="emergency">Emergencia</option>
                    <option value="client_request">Solicitud cliente</option>
                    <option value="other">Otro</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label form-label-sm mb-1">Detalle</label>
                  <input v-model="ext.reason_text" type="text" class="form-control form-control-sm" placeholder="Opcional">
                </div>
                <div class="col-md-3">
                  <button @click="saveExtension" class="tc-btn tc-btn-seg" :disabled="ext.saving">
                    + Agregar
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="detail.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";

export default {
  name: 'TalentoAsistencia',
  setup() {
    return { darkMode };
  },
  data() {
    return {
      attendances: [],
      loading: true,
      pagination: { current_page: 1, last_page: 1 },
      // Sin restricción de fecha por defecto: la lista debe mostrar TODO (flagged
      // mezclado con el resto) — "Hoy"/"Esta semana" son atajos opcionales para
      // acotar, no el estado inicial. Antes el default era "Hoy", así que en
      // cuanto no había check-ins el mismo día la lista salía vacía — daba la
      // impresión de que los flagged estaban ocultos "a propósito" cuando en
      // realidad TODO se ocultaba por la fecha, flagged incluido.
      filters: { search: '', status: '', from: '', to: '', flagged: false },
      range: 'all',
      flaggedCount: 0,
      searchTimeout: null,
      detail: { show: false, att: null, dayType: 'worked', notes: '', saving: false },
      ext: { minutes: 30, reason_category: 'overtime', reason_text: '', saving: false },
    };
  },
  mounted() { this.load(); this.loadFlaggedCount(); },
  methods: {
    setRange(r) {
      this.range = r;
      const today = new Date();
      if (r === 'all') {
        this.filters.from = ''; this.filters.to = '';
      } else if (r === 'today') {
        const d = today.toISOString().substring(0, 10);
        this.filters.from = d; this.filters.to = d;
      } else {
        const mon = new Date(today); mon.setDate(today.getDate() - today.getDay() + 1);
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        this.filters.from = mon.toISOString().substring(0, 10);
        this.filters.to   = sun.toISOString().substring(0, 10);
      }
      this.load();
    },
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/asistencia', {
          params: { ...this.filters, flagged: this.filters.flagged ? 1 : undefined, page }
        });
        this.attendances = data?.data ?? [];
        this.pagination = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally { this.loading = false; }
    },
    async loadFlaggedCount() {
      try {
        const { data } = await axios.get('/talento/api/asistencia', { params: { flagged: 1, per_page: 1 } });
        this.flaggedCount = data?.total ?? 0;
      } catch {}
    },
    goPage(p) { if (p >= 1 && p <= this.pagination.last_page) this.load(p); },
    async openDetail(att) {
      const { data } = await axios.get(`/talento/api/asistencia/${att.id}`);
      this.detail = { show: true, att: data, dayType: data.day_type, notes: data.notes ?? '', saving: false };
      this.ext = { minutes: 30, reason_category: 'overtime', reason_text: '', saving: false };
    },
    async saveDetailAdmin() {
      this.detail.saving = true;
      try {
        const { data } = await axios.put(`/talento/api/asistencia/${this.detail.att.id}`, {
          day_type: this.detail.dayType, notes: this.detail.notes,
        });
        this.detail.att = { ...this.detail.att, ...data };
        this.load();
      } finally { this.detail.saving = false; }
    },
    async saveExtension() {
      if (!this.ext.minutes || this.ext.minutes < 1) return;
      this.ext.saving = true;
      try {
        const { data } = await axios.post(`/talento/api/asistencia/${this.detail.att.id}/extension`, {
          minutes: this.ext.minutes,
          reason_category: this.ext.reason_category,
          reason_text: this.ext.reason_text || null,
        });
        // Refresh detail
        const r = await axios.get(`/talento/api/asistencia/${this.detail.att.id}`);
        this.detail.att = r.data;
        this.ext.reason_text = '';
      } finally { this.ext.saving = false; }
    },
    // Reemplaza al viejo statusBadge(): antes devolvía un string de HTML que
    // el template interpolaba con {{ }} (sin v-html), así que se veía el
    // <span> literal como texto en pantalla — bug preexistente, corregido de
    // paso al migrar a tc-status.
    openStatusVariant(s) { return { open: 'is-info', closed: 'is-slate', flagged: 'is-warn' }[s] ?? 'is-slate'; },
    openStatusLabel(s) { return { open: 'Abierta', closed: 'Cerrada', flagged: 'Flagged' }[s] ?? s; },
    // Mismo hallazgo que Órdenes/Compensación/Liquidaciones: colaboradores
    // que comparten nombre de pila se ven como duplicados si solo se
    // muestra el primer nombre. Null-safe.
    fullName(user) {
      return [user?.name, user?.father_last_name, user?.mother_last_name]
        .filter(Boolean).join(' ');
    },
    dayBadge(d) { return { worked: 'is-ok', no_work_company: 'is-info', absent: 'is-bad' }[d] ?? 'is-slate'; },
    dayLabel(d) { return { worked: 'Trabajado', no_work_company: 'No laborable', absent: 'Falta' }[d] ?? d; },
    extLabel(c) { return { overtime: 'Tiempo extra', emergency: 'Emergencia', client_request: 'Solicitud cliente', other: 'Otro' }[c] ?? c; },
    fmt(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
/* .bg-warning-subtle / .bg-light no están cubiertos por _torre-theme.scss
   (mismo hallazgo que en Talento-liquidaciones: quedan transparentes en modo
   oscuro, perdiendo el aviso visual). Recoloreados con los tokens --tc-*. */
.talento-asistencia .tc-modal-header-warn {
  background: rgba(217, 119, 6, 0.12);
  border-bottom-color: rgba(217, 119, 6, 0.25);
}
.talento-asistencia .asis-subpanel {
  background: var(--tc-bg2);
  border-radius: 12px;
}
.talento-asistencia .asis-row-flag > * {
  background: rgba(217, 119, 6, 0.1) !important;
}
</style>
