<template>
  <div class="talento-ordenes tc-wrap" :class="{ 'tc-dark': darkMode }">

    <div class="tc-card">
      <div class="tc-cardhead d-flex flex-wrap align-items-center justify-content-between gap-2 p-3">
        <h5 class="tc-h1 mb-0"><i class="fa fa-clipboard-list me-2"></i>Órdenes de Trabajo</h5>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <!-- Item #1158: la misma lista vista como agenda (mes/semana/día), con colores por tipo. -->
          <div class="ord-vista" role="group" aria-label="Ver como">
            <button type="button" :class="{ activo: !vistaAgenda }" @click="ponerVista(false)"><i class="fa fa-list me-1"></i>Lista</button>
            <button type="button" :class="{ activo: vistaAgenda }" @click="ponerVista(true)"><i class="fa fa-calendar-alt me-1"></i>Agenda</button>
          </div>
          <button v-if="puedeCrear" @click="openCreate" class="tc-btn tc-btn-ok">
            <i class="fa fa-plus me-1"></i> Nueva orden
          </button>
        </div>
      </div>

      <div class="p-3">
        <TalentoAgenda v-if="vistaAgenda" ref="agenda" :colaborador-id="colaboradorId"
                       :puede-gestionar="puedeCrear" :puede-editar-colores="puedeEditarColores"
                       @ver="viewOrder" @mover="abrirReprogramarAgenda" />
        <template v-else>
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
              <option value="atrasadas">Atrasadas (ya pasó su día)</option>
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
                <th>Cliente / prospecto</th>
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
                  <!-- Las órdenes asignadas desde la ficha del cliente no decían de quién eran (2026-10-27). -->
                  <span v-if="o.cliente?.nombre" class="small">
                    <i class="fa fa-user text-muted me-1" title="Cliente"></i>
                    <a v-if="puedeAbrirCliente" :href="`/cliente/editar/${o.cliente.id}`">{{ o.cliente.nombre }}</a>
                    <template v-else>{{ o.cliente.nombre }}</template>
                  </span>
                  <span v-else-if="o.prospecto?.nombre" class="small">
                    <i class="fa fa-user-clock text-muted me-1" title="Prospecto CRM (aún no es cliente)"></i>{{ o.prospecto.nombre }}
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td>
                  <span class="tc-status is-slate">{{ o.type?.name }}</span>
                  <i v-if="o.is_billable" class="fa fa-dollar-sign text-success ms-1" title="Pagable"></i>
                </td>
                <td class="text-center fw-bold">{{ o.points }}</td>
                <td class="small">{{ fechaAgenda(o.scheduled_at) }}</td>
                <td>
                  <span class="tc-status" :class="statusBadge(o.status)">{{ statusLabel(o.status) }}</span>
                  <span v-if="o.atrasada" class="ord-atrasada" title="Ya pasó su día y no se ha terminado">Atrasada</span>
                </td>
                <td class="text-end">
                  <button @click="viewOrder(o)" class="tc-btn tc-btn-info me-1">Ver</button>
                  <button v-if="puedeCrear && o.atrasada" @click="abrirReprogramar(o)" class="tc-btn tc-btn-bad me-1">Reprogramar</button>
                  <!-- David (29-sep): "Validar"/"Iniciar"/"Completar" son
                       acciones de GESTIÓN (backend: talento.work_orders.
                       validate/manage, ninguna la tiene un técnico
                       autoservicio) — antes se mostraban a cualquiera que
                       viera la lista (con solo status como condición) y al
                       hacer clic 403eaban en silencio. El técnico avanza
                       SU PROPIA orden desde la app móvil de campo, no
                       desde aquí. Mismo flag que ya gatea "Nueva orden". -->
                  <button v-if="puedeCrear && o.status === 'completed'" @click="openValidate(o)" class="tc-btn tc-btn-ok me-1">Validar</button>
                  <button v-if="puedeCrear && canAdvance(o.status)" @click="advanceStatus(o)" class="tc-btn tc-btn-primary">
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
        </template>
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
                <!-- Dentro de la ficha de un colaborador (colaboradorId presente): fija,
                     sin buscador — un supervisor sin acceso al roster completo no puede
                     ni debe buscar A OTROS, solo crear para éste. -->
                <div v-if="colaboradorId" class="form-control-plaintext small">
                  <i class="fa fa-check-circle text-success me-1"></i>{{ createModal.colaborador_name }}
                </div>
                <template v-else>
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
                </template>
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
              <!-- Carga de trabajo (item #1158): cuántas órdenes tiene ya cada quien por día; tocar un día lo elige. -->
              <div class="col-12">
                <label class="form-label">Trabajo ya asignado</label>
                <CargaCalendario v-model="diaNuevaOrden" :colaborador-id="createModal.colaborador_id" />
              </div>
              <!-- El día es obligatorio, igual que en «Asignar trabajo» (2026-10-27): sin él la orden no le
                   salía al técnico en «Mi día». La hora es opcional. -->
              <div class="col-md-6">
                <label class="form-label" for="no_fecha">Agendar para <span class="text-danger">*</span></label>
                <FechaHoraOrden v-model="createModal.scheduled_at" input-id="no_fecha" />
                <div class="form-text">La hora es opcional.</div>
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
              <div class="col-md-3"><strong>Agendada:</strong> {{ fechaAgenda(detail.order.scheduled_at) }}</div>
              <div class="col-md-4" v-if="detail.order.cliente?.nombre">
                <strong>Cliente:</strong>
                <a v-if="puedeAbrirCliente" :href="`/cliente/editar/${detail.order.cliente.id}`">{{ detail.order.cliente.nombre }}</a>
                <template v-else>{{ detail.order.cliente.nombre }}</template>
              </div>
              <div class="col-md-4" v-if="detail.order.prospecto?.nombre">
                <strong>Prospecto (CRM):</strong> {{ detail.order.prospecto.nombre }}
              </div>
              <div class="col-md-4" v-if="detail.order.validated_at">
                <strong>Validada:</strong> {{ formatDatetime(detail.order.validated_at) }}<br>
                <small class="text-muted">por {{ detail.order.validated_by?.name }}</small>
              </div>
              <div class="col-12" v-if="detail.order.notes"><strong>Notas:</strong> {{ detail.order.notes }}</div>
              <div class="col-12" v-if="detail.order.atrasada">
                <div class="alert alert-danger py-2 small mb-0">
                  <i class="fa fa-exclamation-circle me-1"></i>Atrasada: ya pasó su día y no se ha terminado. Hay que terminarla o reprogramarla con un motivo.
                </div>
              </div>
            </div>
            <div v-if="detail.order.reprogramaciones?.length" class="mt-3">
              <h6>Cambios de día</h6>
              <ul class="list-group list-group-flush">
                <li v-for="(r, i) in detail.order.reprogramaciones" :key="i" class="list-group-item px-0 py-1 small">
                  <strong>{{ fechaAgenda(r.de) }} → {{ fechaAgenda(r.a) }}</strong>
                  <span v-if="r.atrasada" class="ord-atrasada">estaba atrasada</span>
                  <div>{{ r.motivo }}</div>
                  <div class="text-muted">{{ r.quien || 'Alguien' }} · {{ formatDatetime(r.cuando) }}</div>
                </li>
              </ul>
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
            <button v-if="detail.order?.validated_at && puedeCorregir" @click="openCorregir(detail.order)" class="btn btn-warning">Corregir orden validada</button>
            <button v-if="puedeCrear && detail.order && detail.order._source === 'task' && ['pending', 'in_progress'].includes(detail.order.status)"
                    @click="abrirReprogramar(detail.order)" class="btn btn-outline-danger">Reprogramar</button>
            <button @click="detail.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <ReprogramarOrden v-if="reprogramar" :orden="reprogramar" :fecha-inicial="reprogramar.fechaInicial"
                      :url="`/talento/api/ordenes/${reprogramar.id}/reprogramar`"
                      @cerrar="reprogramar = null" @reprogramada="trasReprogramar" />

    <!-- Modal corregir orden validada (UNI-T D7.3) -->
    <div v-if="corregirModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Corregir orden validada #{{ corregirModal.order?.id }}</h5>
            <button @click="corregirModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label">Tipo</label>
              <select v-model="corregirModal.tipo_id" class="form-select">
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Puntos <small class="text-muted">(vacío = los del tipo)</small></label>
              <input v-model="corregirModal.puntos" type="number" min="0" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Motivo <small class="text-muted">(obligatorio, mínimo 10 caracteres)</small></label>
              <textarea v-model="corregirModal.motivo" rows="3" class="form-control"></textarea>
            </div>
            <p class="text-muted small mb-0">Si la semana de pago ya está cerrada, la orden no se reescribe: se registra un ajuste en la siguiente semana abierta.</p>
            <div v-if="corregirModal.error" class="alert alert-danger py-1 mt-2 mb-0">{{ corregirModal.error }}</div>
            <div v-if="corregirModal.resultado" class="alert alert-success py-1 mt-2 mb-0">{{ corregirModal.resultado }}</div>
          </div>
          <div class="modal-footer">
            <button @click="corregirModal.show=false" class="btn btn-secondary" :disabled="corregirModal.saving">Cerrar</button>
            <button @click="confirmCorregir" class="btn btn-warning" :disabled="corregirModal.saving || !!corregirModal.resultado">
              <span v-if="corregirModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              Corregir
            </button>
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
import Permission from "../../../helpers/Permission.js";
import { allViewHasPermission } from "../../../helpers/Request.js";
import FechaHoraOrden from "./FechaHoraOrden.vue";
import CargaCalendario from "./CargaCalendario.vue";
import ReprogramarOrden from "./ReprogramarOrden.vue";
import TalentoAgenda from "./TalentoAgenda.vue";
import { fechaAgenda } from "./fechaAgenda.js";

const VISTA_KEY = "talento.ordenes.vista";

export default {
  name: 'TalentoOrdenes',
  components: { FechaHoraOrden, CargaCalendario, ReprogramarOrden, TalentoAgenda },
  props: {
    // Si viene, filtra la lista a este colaborador únicamente (uso: pestaña
    // "Órdenes de trabajo" de la ficha de un colaborador) — sin esto, se
    // comporta igual que siempre (lista global).
    colaboradorId: { type: [Number, String], default: null },
    // Nombre a mostrar cuando colaboradorId viene fijo (evita otra llamada
    // solo para mostrar el nombre — el padre, TalentoColaboradorFicha.vue,
    // ya lo tiene cargado).
    colaboradorNombre: { type: String, default: '' },
    // Resuelto server-side por la ficha (TalentoColaboradorController::ficha):
    // true si además de/en vez del permiso global talento.work_orders.manage,
    // quien mira ES el supervisor directo (talento_colaboradores.supervisor_id)
    // de este colaborador — David, 28-sep: "supervisor" no es un rol, es esa
    // relación asignada al crear/editar un colaborador. Solo tiene sentido
    // junto con colaboradorId (la lista global no tiene un "de quién" al que
    // supervisar).
    puedeGestionar: { type: Boolean, default: false },
  },
  setup() {
    return { darkMode };
  },
  data() {
    return {
      // "Nueva orden" es una acción de GESTIÓN, no de ver-lo-mío — un técnico
      // no debe poder crearse órdenes a sí mismo. talento.work_orders.manage
      // ya está acotado a super-administrator/DESARROLLADOR (David, 28-sep:
      // "si no eres admin, desarrollador, supervisor no deberías poder crear
      // órdenes" — el rol de supervisor de campo pendiente de sumarse a ese
      // permiso, ver bitácora).
      permisosOrdenes: {},
      orders: [],
      types: [],
      loading: true,
      pagination: { current_page: 1, last_page: 1 },
      filters: { search: '', prospecto: '', status: '', type_id: '', from: '', to: '' },
      searchTimeout: null,
      createModal: { show: false, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '', crm_lead_id: null, crm_lead_name: '' },
      detail: { show: false, order: null },
      vistaAgenda: (() => { try { return localStorage.getItem(VISTA_KEY) === "agenda"; } catch (e) { return false; } })(),
      reprogramar: null,
      validateModal: { show: false, order: null, saving: false },
      corregirModal: { show: false, order: null, tipo_id: null, puntos: '', motivo: '', saving: false, error: '', resultado: '' },
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
    puedeCrear() {
      // David (29-sep): mismo criterio de staff que el backend
      // (esStaffOrdenes en TalentoWorkOrderController) — talento.employees
      // .view cubre a Mostrador igual que talento.work_orders.manage.
      return this.puedeGestionar
        || new Permission(this.permisosOrdenes).canDo('talento.work_orders.manage')
        || new Permission(this.permisosOrdenes).canDo('talento.employees.view');
    },
    // Mismo permiso que abre /cliente/editar/{id}; sin él el nombre va sin enlace.
    puedeCorregir() {
      return new Permission(this.permisosOrdenes).canDo('talento.ordenes.corregir_validada');
    },
    puedeEditarColores() {
      return new Permission(this.permisosOrdenes).canDo('talento.work_orders.manage');
    },
    // El día del calendario de carga y el de «Agendar para» son el mismo (se conserva la hora).
    diaNuevaOrden: {
      get() { return this.createModal.scheduled_at ? this.createModal.scheduled_at.slice(0, 10) : null; },
      set(dia) {
        const v = this.createModal.scheduled_at || '';
        this.createModal.scheduled_at = dia ? dia + (v.length > 10 ? v.slice(10) : '') : '';
      },
    },
    puedeAbrirCliente() {
      return new Permission(this.permisosOrdenes).canDo('client_edit_client');
    },
  },
  async mounted() {
    this.loadTypes();
    this.load();
    this.permisosOrdenes = await allViewHasPermission();
  },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const params = { ...this.filters, page };
        if (this.colaboradorId) params.colaborador_id = this.colaboradorId;
        const { data } = await axios.get('/talento/api/ordenes', { params });
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
      this.createModal = {
        show: true,
        // Pre-llenado y fijo si viene de la ficha de un colaborador — ver la nota junto
        // al buscador en el template.
        colaborador_id: this.colaboradorId || null,
        colaborador_name: this.colaboradorId ? this.colaboradorNombre : '',
        type_id: null, scheduled_at: '', notes: '', saving: false, error: '', crm_lead_id: null, crm_lead_name: '',
      };
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
      if (!this.createModal.scheduled_at) { this.createModal.error = 'Elige el día de la orden.'; return; }
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
    openCorregir(o) {
      this.corregirModal = { show: true, order: o, tipo_id: o.type?.id ?? o.type_id ?? null, puntos: '', motivo: '', saving: false, error: '', resultado: '' };
    },
    async confirmCorregir() {
      const m = this.corregirModal;
      m.saving = true; m.error = '';
      try {
        const { data } = await axios.post(`/talento/api/ordenes/${m.order.id}/corregir-validada`, {
          tipo_id: m.tipo_id, puntos: m.puntos === '' ? null : m.puntos, motivo: m.motivo,
        });
        m.resultado = data.ajuste
          ? `Semana cerrada: se registró un ajuste de ${data.points_delta} pts en la siguiente semana abierta.`
          : 'Orden corregida.';
        this.load();
        this.viewOrder(m.order);
      } catch (e) {
        m.error = e.response?.data?.error ?? 'Error al corregir.';
      } finally { m.saving = false; }
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
    fechaAgenda,
    ponerVista(agenda) {
      this.vistaAgenda = agenda;
      try { localStorage.setItem(VISTA_KEY, agenda ? "agenda" : "lista"); } catch (e) { /* sin almacenamiento */ }
      if (!agenda) this.load();
    },
    // Item #1158: o (fila de la lista, detalle o evento de la agenda) → datos del diálogo de reprogramar.
    abrirReprogramar(o, fechaInicial = null) {
      const fecha = o.fecha || (o.scheduled_at ? String(o.scheduled_at).slice(0, 10) : null);
      this.reprogramar = {
        id: o.id,
        tipo: o.tipo || o.type?.name || '',
        cliente: typeof o.cliente === 'string' ? o.cliente : (o.cliente?.nombre || o.prospecto?.nombre || ''),
        fecha,
        atrasada: !!o.atrasada,
        fechaInicial,
      };
    },
    abrirReprogramarAgenda({ orden, fecha }) {
      this.abrirReprogramar(orden, fecha);
    },
    trasReprogramar() {
      this.reprogramar = null;
      if (this.detail.show && this.detail.order) this.viewOrder(this.detail.order);
      if (this.vistaAgenda) this.$refs.agenda?.recargar();
      else this.load();
    },
    formatDatetime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

<style scoped>
/* form-control/form-select, tc-select (chevron+foco), tc-btn-info/
   tc-btn-primary y la paginación (:deep(.page-link) etc.) vivían aquí como
   estilos LOCALES (#9991200); al construir más pantallas con el mismo
   tratamiento (Compensación, Liquidaciones) se promovieron a
   _torre-theme.scss (bajo .tc-wrap, mismo patrón que tc-card/tc-btn-ok/
   tc-status) para no duplicar la misma hoja de estilos en cada pantalla
   nueva. Lo que sigue abajo es específico de ESTA pantalla (grupo de
   prospecto). */

/* Grupo "Prospectos de X" / "Otros prospectos" dentro del dropdown de
   prospecto: NO se usa .bg-light (Bootstrap, sin tratamiento oscuro
   verificado) — se recolorea con los tokens --tc-* como el resto. */
.ord-vista {
  display: inline-flex;
  border: 1px solid var(--tc-border, #dee2e6);
  border-radius: 18px;
  overflow: hidden;
}
.ord-vista button {
  border: 0;
  background: transparent;
  color: inherit;
  padding: 4px 12px;
  font-size: 13px;
}
.ord-vista button + button {
  border-left: 1px solid var(--tc-border, #dee2e6);
}
.ord-vista button.activo {
  background: rgba(26, 115, 232, 0.12);
  color: #1a73e8;
  font-weight: 600;
}
.ord-atrasada {
  display: inline-block;
  margin-left: 4px;
  padding: 1px 7px;
  border-radius: 8px;
  background: #d50000;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
}
.talento-ordenes .prosp-group-header {
  background: var(--tc-bg2, #f8fafc);
  color: var(--tc-muted, #6b7280);
}
</style>
