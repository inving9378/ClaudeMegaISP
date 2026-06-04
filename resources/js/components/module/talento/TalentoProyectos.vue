<template>
  <div class="talento-proyectos">

    <!-- ── LIST ── -->
    <template v-if="!detail">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fa fa-project-diagram me-2 text-primary"></i>Proyectos de Planta Externa</h5>
        <button @click="openCreate" class="btn btn-sm btn-primary"><i class="fa fa-plus me-1"></i>Nuevo proyecto</button>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-md-3"><input v-model="filters.search" @input="debounce" type="text" class="form-control form-control-sm" placeholder="Buscar…"></div>
        <div class="col-md-2">
          <select v-model="filters.status" @change="load" class="form-select form-select-sm">
            <option value="">Todos</option>
            <option value="planning">Planificación</option>
            <option value="active">Activo</option>
            <option value="paused">Pausado</option>
            <option value="done">Finalizado</option>
          </select>
        </div>
      </div>
      <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Nombre</th><th>Estado</th><th>Lead</th><th>Vigencia</th><th>Avance</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="p in projects" :key="p.id">
              <td class="fw-semibold">{{ p.name }}</td>
              <td><span class="badge" :class="statusColor(p.status)">{{ statusLabel(p.status) }}</span></td>
              <td class="small">{{ p.lead?.user?.name ?? '—' }}</td>
              <td class="small">{{ p.end_date ? fmtDate(p.end_date) : '—' }}</td>
              <td>
                <div v-if="p.overall_pct != null" class="d-flex align-items-center gap-2">
                  <div class="progress flex-fill" style="height:6px;min-width:80px;">
                    <div class="progress-bar" :class="p.overall_pct >= 100 ? 'bg-success' : 'bg-primary'"
                         :style="`width:${Math.min(100, p.overall_pct)}%`"></div>
                  </div>
                  <span class="small fw-bold">{{ fmt1(p.overall_pct) }}%</span>
                </div>
                <span v-else class="text-muted small">—</span>
              </td>
              <td>
                <button @click="openDetail(p.id)" class="btn btn-xs btn-primary me-1"><i class="fa fa-eye"></i> Ver</button>
                <button v-if="p.status === 'active' || p.status === 'done'" @click="openBonus(p)" class="btn btn-xs btn-outline-warning"><i class="fa fa-award"></i></button>
              </td>
            </tr>
            <tr v-if="!projects.length"><td colspan="6" class="text-center text-muted py-4">Sin proyectos.</td></tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── DETAIL ── -->
    <template v-else>
      <div class="d-flex align-items-center gap-3 mb-3">
        <button @click="detail=null; destroyCorridorMap()" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Volver</button>
        <h5 class="mb-0">{{ detail.name }}
          <span class="ms-2 badge" :class="statusColor(detail.status)">{{ statusLabel(detail.status) }}</span>
        </h5>
        <button @click="editDetail=true" class="ms-auto btn btn-xs btn-outline-primary"><i class="fa fa-pen"></i> Editar</button>
      </div>

      <!-- Meta info -->
      <div class="row g-3 mb-3">
        <div class="col-md-3 small text-muted">Lead: <span class="text-dark fw-semibold">{{ detail.lead?.user?.name ?? '—' }}</span></div>
        <div class="col-md-3 small text-muted">Inicio: <span class="text-dark">{{ detail.start_date ? fmtDate(detail.start_date) : '—' }}</span></div>
        <div class="col-md-3 small text-muted">Vigencia: <span class="text-dark">{{ detail.end_date ? fmtDate(detail.end_date) : '—' }}</span></div>
        <div class="col-md-3">
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-fill" style="height:10px;">
              <div class="progress-bar" :class="detail.overall_pct >= 100 ? 'bg-success' : 'bg-primary'"
                   :style="`width:${Math.min(100, detail.overall_pct ?? 0)}%`"></div>
            </div>
            <span class="fw-bold small">{{ fmt1(detail.overall_pct ?? 0) }}% global</span>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Pool de actividades -->
        <div class="col-md-8">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0 text-uppercase text-muted small">Pool de actividades</h6>
            <button @click="openAddActivity" class="btn btn-xs btn-outline-primary"><i class="fa fa-plus me-1"></i>Agregar actividad</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr><th>Tipo</th><th>Planeado</th><th>Aprobado</th><th>Restante</th><th>%</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="act in detail.activities" :key="act.id">
                  <td>
                    <div class="fw-semibold small">{{ act.activity_type?.name }}</div>
                    <div class="text-muted" style="font-size:11px;">{{ act.location_notes }}</div>
                  </td>
                  <td class="small">{{ fmt2(act.planned_quantity) }} <span class="text-muted">{{ act.activity_type?.unit }}</span></td>
                  <td class="small text-success fw-semibold">{{ fmt2(act.approved_total) }}</td>
                  <td class="small" :class="act.remaining <= 0 ? 'text-danger fw-bold' : ''">{{ fmt2(act.remaining) }}</td>
                  <td style="min-width:100px;">
                    <div class="d-flex align-items-center gap-1">
                      <div class="progress flex-fill" style="height:5px;">
                        <div class="progress-bar" :class="act.progress_pct >= 100 ? 'bg-success' : 'bg-primary'"
                             :style="`width:${Math.min(100, act.progress_pct)}%`"></div>
                      </div>
                      <span style="font-size:11px;font-weight:bold;">{{ fmt1(act.progress_pct) }}%</span>
                    </div>
                  </td>
                  <td>
                    <button v-if="act.remaining > 0" @click="openReport(act)" class="btn btn-xs btn-outline-success"><i class="fa fa-plus"></i></button>
                    <button @click="openActReports(act)" class="btn btn-xs btn-outline-secondary ms-1"><i class="fa fa-list"></i></button>
                  </td>
                </tr>
                <tr v-if="!detail.activities?.length"><td colspan="6" class="text-muted small text-center py-2">Sin actividades. Agrega una.</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Bonus scale -->
          <div v-if="detail.bonus_scale?.length || detail.bonus_amount" class="mt-2 small text-muted">
            <i class="fa fa-award text-warning me-1"></i>
            <span v-if="detail.bonus_scale?.length">
              Escala: <span v-for="(b,i) in detail.bonus_scale" :key="i">≥{{ b.threshold_pct }}% → ${{ fmt2(b.amount) }}<span v-if="i < detail.bonus_scale.length-1"> · </span></span>
            </span>
            <span v-else>Bono plano: ${{ fmt2(detail.bonus_amount) }}</span>
            <button v-if="detail.status !== 'done'" @click="awardBonus" class="btn btn-xs btn-warning ms-2" :disabled="awardingBonus">
              <span v-if="awardingBonus"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else><i class="fa fa-award me-1"></i>Otorgar bono</span>
            </button>
          </div>
        </div>

        <!-- Producción por técnico -->
        <div class="col-md-4">
          <h6 class="text-uppercase text-muted small mb-2">Producción por técnico</h6>
          <div v-if="!detail.collaborator_summary?.length" class="text-muted small fst-italic">Sin reportes aprobados aún.</div>
          <div v-for="cs in detail.collaborator_summary" :key="cs.colaborador_id" class="mb-2 d-flex justify-content-between align-items-center small">
            <span class="fw-semibold">{{ cs.name }}</span>
            <div class="text-end">
              <div class="fw-bold text-primary">{{ fmt2(cs.points) }} pts</div>
              <div class="text-muted" style="font-size:11px;">{{ fmt2(cs.quantity) }} ud</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── SECCIÓN CORREDOR ── -->
      <div class="mt-4 border rounded">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light rounded-top"
             style="cursor:pointer" @click="corridorOpen = !corridorOpen">
          <h6 class="mb-0 text-uppercase text-muted small">
            <i class="fa fa-route me-1 text-primary"></i>Corredor de línea
            <span v-if="corridor.path?.length" class="badge bg-primary ms-2">{{ corridor.path.length }} pts</span>
          </h6>
          <i class="fa" :class="corridorOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </div>

        <div v-if="corridorOpen" class="p-3">
          <!-- Mapa Leaflet -->
          <div class="position-relative mb-2">
            <div ref="corridorMap" style="height:400px;border-radius:4px;z-index:1;"></div>
            <!-- controles sobre el mapa -->
            <div class="position-absolute top-0 end-0 m-2 d-flex gap-1" style="z-index:500;">
              <button v-if="corridor.editMode" @click="clearCorridor"
                      class="btn btn-xs btn-danger shadow-sm" title="Limpiar trazado">
                <i class="fa fa-trash"></i>
              </button>
              <button v-if="corridor.editMode && corridor.draft?.length >= 2"
                      @click="finishDraw"
                      class="btn btn-xs btn-success shadow-sm">
                <i class="fa fa-check me-1"></i>Terminar
              </button>
            </div>
            <div v-if="corridor.editMode" class="position-absolute bottom-0 start-0 m-2 px-2 py-1 bg-white rounded shadow-sm small" style="z-index:500;pointer-events:none;">
              <i class="fa fa-mouse-pointer me-1 text-primary"></i>Click para agregar punto · Doble-clic para terminar
            </div>
          </div>

          <!-- Controles bajo el mapa -->
          <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            <span class="small text-muted">
              <i class="fa fa-map-pin me-1"></i>
              <span v-if="corridor.draft?.length">{{ corridor.draft.length }} puntos (borrando)</span>
              <span v-else-if="corridor.path?.length">{{ corridor.path.length }} puntos guardados</span>
              <span v-else>Sin corredor trazado</span>
            </span>
            <button v-if="corridor.draft?.length >= 2 && corridor.editMode"
                    @click="saveCorridor" class="btn btn-sm btn-primary ms-auto" :disabled="corridor.saving">
              <span v-if="corridor.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else><i class="fa fa-save me-1"></i>Guardar corredor</span>
            </button>
            <button v-if="!corridor.editMode" @click="startEdit"
                    class="btn btn-sm btn-outline-primary ms-auto">
              <i class="fa fa-pen me-1"></i>{{ corridor.path?.length ? 'Redibujar' : 'Trazar corredor' }}
            </button>
            <button v-if="corridor.editMode" @click="cancelEdit" class="btn btn-sm btn-outline-secondary">
              Cancelar
            </button>
          </div>

          <!-- Tabla desvíos -->
          <div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="text-uppercase text-muted small mb-0">
                <i class="fa fa-exclamation-triangle text-warning me-1"></i>Desvíos detectados
              </h6>
              <button @click="analyzeDeviations" class="btn btn-xs btn-outline-warning" :disabled="corridor.analyzing">
                <span v-if="corridor.analyzing"><span class="spinner-border spinner-border-sm me-1"></span>Analizando…</span>
                <span v-else><i class="fa fa-search me-1"></i>Analizar últimos 7 días</span>
              </button>
            </div>
            <div v-if="corridor.loadingDeviations" class="text-center py-3">
              <div class="spinner-border spinner-border-sm text-warning"></div>
            </div>
            <div v-else-if="corridor.deviations?.length" class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr><th>Técnico</th><th>Fecha</th><th>Duración</th><th>Distancia max.</th></tr>
                </thead>
                <tbody>
                  <tr v-for="dev in corridor.deviations" :key="dev.id"
                      style="cursor:pointer" @click="focusDeviation(dev)"
                      :class="{ 'table-warning': dev.id === corridor.focusedDevId }">
                    <td class="small fw-semibold">{{ dev.colaborador?.user?.name ?? '—' }}</td>
                    <td class="small">{{ fmtDate(dev.detected_at) }}</td>
                    <td class="small">{{ dev.sustained_minutes }} min</td>
                    <td class="small">
                      <span :class="dev.deviation_m > 600 ? 'text-danger fw-bold' : 'text-warning'">
                        {{ Math.round(dev.deviation_m) }} m
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted small fst-italic py-2">
              Sin desvíos detectados.
              <span v-if="!corridor.path?.length"> Traza el corredor primero.</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── MODAL CREAR/EDITAR PROYECTO ── -->
    <div v-if="projectModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ projectModal.id ? 'Editar proyecto' : 'Nuevo proyecto' }}</h5>
            <button @click="projectModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="projectModal.name" type="text" class="form-control"></div>
              <div class="col-12"><label class="form-label">Descripción</label>
                <textarea v-model="projectModal.description" class="form-control" rows="2"></textarea></div>
              <div class="col-md-4"><label class="form-label">Estado</label>
                <select v-model="projectModal.status" class="form-select">
                  <option value="planning">Planificación</option>
                  <option value="active">Activo</option>
                  <option value="paused">Pausado</option>
                </select>
              </div>
              <div class="col-md-4"><label class="form-label">Inicio</label>
                <input v-model="projectModal.start_date" type="date" class="form-control"></div>
              <div class="col-md-4"><label class="form-label">Vigencia (deadline bono)</label>
                <input v-model="projectModal.end_date" type="date" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Bono plano ($)</label>
                <div class="input-group"><span class="input-group-text">$</span>
                  <input v-model.number="projectModal.bonus_amount" type="number" min="0" step="50" class="form-control" placeholder="0"></div>
              </div>
              <!-- Bonus scale editor -->
              <div class="col-12">
                <label class="form-label">Escala de bono (opcional)</label>
                <div class="small text-muted mb-2">Define umbrales de avance → monto. Si defines escala, el bono plano queda como respaldo.</div>
                <div v-for="(b, i) in projectModal.bonus_scale" :key="i" class="d-flex gap-2 mb-2">
                  <div class="input-group input-group-sm" style="width:180px">
                    <span class="input-group-text">≥</span>
                    <input v-model.number="b.threshold_pct" type="number" min="1" max="100" class="form-control" placeholder="80">
                    <span class="input-group-text">%</span>
                  </div>
                  <div class="input-group input-group-sm" style="width:160px">
                    <span class="input-group-text">$</span>
                    <input v-model.number="b.amount" type="number" min="0" step="50" class="form-control" placeholder="500">
                  </div>
                  <button @click="projectModal.bonus_scale.splice(i,1)" class="btn btn-xs btn-outline-danger">✕</button>
                </div>
                <button @click="projectModal.bonus_scale.push({threshold_pct:80,amount:300})" class="btn btn-xs btn-outline-secondary">
                  + Agregar escala
                </button>
              </div>
              <div v-if="projectModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ projectModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="projectModal.show=false" class="btn btn-secondary" :disabled="projectModal.saving">Cancelar</button>
            <button @click="saveProject" class="btn btn-primary" :disabled="projectModal.saving">
              <span v-if="projectModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL AGREGAR ACTIVIDAD ── -->
    <div v-if="actModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Agregar actividad al pool</h5>
            <button @click="actModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Tipo de actividad <span class="text-danger">*</span></label>
                <select v-model="actModal.activity_type_id" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="t in activityTypes" :key="t.id" :value="t.id">
                    {{ t.name }} ({{ t.unit }}, {{ t.points_per_unit }} pts/ud)
                  </option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Cantidad planeada (techo) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input v-model.number="actModal.planned_quantity" type="number" min="0.001" step="0.5" class="form-control">
                  <span class="input-group-text small">{{ selectedTypeUnit }}</span>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas de ubicación</label>
                <input v-model="actModal.location_notes" type="text" class="form-control" placeholder="Tramo, poste, zona…">
              </div>
              <div v-if="actModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ actModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="actModal.show=false" class="btn btn-secondary" :disabled="actModal.saving">Cancelar</button>
            <button @click="saveActivity" class="btn btn-primary" :disabled="actModal.saving">
              <span v-if="actModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Agregar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL REPORTE DIARIO ── -->
    <div v-if="reportModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reportar actividad — {{ reportModal.activity?.activity_type?.name }}</h5>
            <button @click="reportModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info py-2 small mb-3">
              Restante disponible: <strong>{{ fmt2(reportModal.activity?.remaining) }} {{ reportModal.activity?.activity_type?.unit }}</strong>
              (techo: {{ fmt2(reportModal.activity?.planned_quantity) }})
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input v-model.number="reportModal.quantity" type="number" min="0.001" step="0.5" class="form-control">
                  <span class="input-group-text small">{{ reportModal.activity?.activity_type?.unit }}</span>
                </div>
                <div v-if="reportModal.quantity > reportModal.activity?.remaining" class="small text-warning mt-1">
                  <i class="fa fa-exclamation-triangle me-1"></i>
                  Excede el restante. Se aprobará solo {{ fmt2(reportModal.activity?.remaining) }} (capped).
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha</label>
                <input v-model="reportModal.report_date" type="date" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Participantes <span class="text-danger">*</span></label>
                <div class="small text-muted mb-2">
                  Selecciona los técnicos. Se repartirá <strong>1/N</strong> entre {{ reportModal.colaborador_ids.length || 0 }} seleccionado(s).
                  <span v-if="reportModal.colaborador_ids.length > 0" class="text-success ms-2">
                    Cada uno: {{ fmt3(reportModal.quantity / reportModal.colaborador_ids.length) }} {{ reportModal.activity?.activity_type?.unit }}
                    × {{ reportModal.activity?.activity_type?.points_per_unit }} pts/ud =
                    <strong>{{ fmt3((reportModal.quantity / reportModal.colaborador_ids.length) * (reportModal.activity?.activity_type?.points_per_unit ?? 0)) }} pts</strong>
                  </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <div v-for="col in colaboradores" :key="col.id" class="form-check form-check-inline">
                    <input :id="`col-${col.id}`" type="checkbox" class="form-check-input"
                           :checked="reportModal.colaborador_ids.includes(col.id)"
                           @change="toggleParticipant(col.id)">
                    <label :for="`col-${col.id}`" class="form-check-label small">{{ col.user?.name }}</label>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <input v-model="reportModal.notes" type="text" class="form-control form-control-sm">
              </div>
              <div v-if="reportModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ reportModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="reportModal.show=false" class="btn btn-secondary" :disabled="reportModal.saving">Cancelar</button>
            <button @click="submitReport" class="btn btn-success" :disabled="reportModal.saving || !reportModal.colaborador_ids.length">
              <span v-if="reportModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Reportando…</span>
              <span v-else><i class="fa fa-check me-1"></i>Reportar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL VER REPORTES DE UNA ACTIVIDAD ── -->
    <div v-if="reportsListModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reportes — {{ reportsListModal.activity?.activity_type?.name }}</h5>
            <button @click="reportsListModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div v-if="reportsListModal.loading" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr><th>Fecha</th><th>Cant.</th><th>Aprobado</th><th>Estado</th><th>Participantes</th><th>Pts/ud</th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in reportsListModal.reports" :key="r.id">
                    <td class="small">{{ fmtDate(r.report_date) }}</td>
                    <td class="small">{{ fmt2(r.quantity) }}</td>
                    <td class="small fw-semibold text-success">{{ fmt2(r.approved_quantity) }}</td>
                    <td><span class="badge" :class="reportStatusColor(r.status)">{{ reportStatusLabel(r.status) }}</span></td>
                    <td class="small">{{ (r.participants ?? []).map(p => p.colaborador?.user?.name).join(', ') }}</td>
                    <td class="small">{{ fmt3((r.participants ?? [])[0]?.points_earned ?? 0) }}</td>
                  </tr>
                  <tr v-if="!reportsListModal.reports?.length"><td colspan="6" class="text-muted text-center py-3">Sin reportes.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer"><button @click="reportsListModal.show=false" class="btn btn-secondary">Cerrar</button></div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import L from 'leaflet';

export default {
  name: 'TalentoProyectos',
  data() {
    const today = new Date().toISOString().substring(0, 10);
    return {
      projects: [],
      loading: true,
      filters: { search: '', status: '' },
      debounceTimer: null,
      detail: null,
      editDetail: false,
      awardingBonus: false,
      activityTypes: [],
      colaboradores: [],

      projectModal: { show: false, id: null, name: '', description: '', status: 'planning',
                      start_date: today, end_date: '', bonus_amount: '', bonus_scale: [],
                      saving: false, error: '' },
      actModal: { show: false, activity_type_id: null, planned_quantity: 1, location_notes: '', saving: false, error: '' },
      reportModal: { show: false, activity: null, quantity: 1, report_date: today, colaborador_ids: [], notes: '', saving: false, error: '' },
      reportsListModal: { show: false, activity: null, reports: [], loading: false },

      // Corredor
      corridorOpen: false,
      corridor: {
        path: [],          // puntos guardados [[lat,lng],...]
        draft: [],         // puntos en edición
        editMode: false,
        saving: false,
        analyzing: false,
        loadingDeviations: false,
        deviations: [],
        focusedDevId: null,
      },
      _corridorMap: null,       // instancia L.Map
      _corridorPolyline: null,  // polyline del corredor guardado
      _corridorDraftLine: null, // polyline del draft
      _corridorMarkers: [],     // circleMarkers de puntos del draft
      _deviationMarkers: [],    // markers rojos de desvíos
    };
  },
  computed: {
    selectedTypeUnit() {
      const t = this.activityTypes.find(t => t.id === this.actModal.activity_type_id);
      return t?.unit ?? 'ud';
    },
  },
  mounted() {
    this.load();
    this.loadActivityTypes();
    this.loadColaboradores();
  },
  methods: {
    debounce() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.load(), 350);
    },
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/proyectos', { params: this.filters });
        this.projects = data?.data ?? [];
      } finally { this.loading = false; }
    },
    async loadActivityTypes() {
      const { data } = await axios.get('/talento/api/activity-types');
      this.activityTypes = data ?? [];
    },
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 200, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async openDetail(id) {
      this.destroyCorridorMap();
      this.detail = null;
      this.corridorOpen = false;
      this.corridor = { path: [], draft: [], editMode: false, saving: false,
                        analyzing: false, loadingDeviations: false, deviations: [], focusedDevId: null };
      const { data } = await axios.get(`/talento/api/proyectos/${id}`);
      this.detail = data;
    },

    destroyCorridorMap() {
      if (this._corridorMap) { this._corridorMap.remove(); this._corridorMap = null; }
      this._corridorPolyline = null;
      this._corridorDraftLine = null;
      this._corridorMarkers = [];
      this._deviationMarkers = [];
    },
    openCreate() {
      const today = new Date().toISOString().substring(0,10);
      this.projectModal = { show:true, id:null, name:'', description:'', status:'planning',
                            start_date:today, end_date:'', bonus_amount:'', bonus_scale:[],
                            saving:false, error:'' };
    },
    openBonus(p) { this.openDetail(p.id); },
    async saveProject() {
      this.projectModal.error = '';
      if (!this.projectModal.name) { this.projectModal.error = 'El nombre es requerido.'; return; }
      this.projectModal.saving = true;
      try {
        const payload = {
          name: this.projectModal.name, description: this.projectModal.description,
          status: this.projectModal.status, start_date: this.projectModal.start_date || null,
          end_date: this.projectModal.end_date || null,
          bonus_amount: this.projectModal.bonus_amount || null,
          bonus_scale: this.projectModal.bonus_scale.length ? this.projectModal.bonus_scale : null,
        };
        if (this.projectModal.id) {
          await axios.put(`/talento/api/proyectos/${this.projectModal.id}`, payload);
        } else {
          await axios.post('/talento/api/proyectos', payload);
        }
        this.projectModal.show = false;
        this.load();
        if (this.detail) await this.openDetail(this.detail.id);
      } catch (e) {
        this.projectModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.projectModal.saving = false; }
    },
    openAddActivity() {
      this.actModal = { show:true, activity_type_id:null, planned_quantity:1, location_notes:'', saving:false, error:'' };
    },
    async saveActivity() {
      this.actModal.error = '';
      if (!this.actModal.activity_type_id) { this.actModal.error = 'Selecciona un tipo.'; return; }
      this.actModal.saving = true;
      try {
        await axios.post(`/talento/api/proyectos/${this.detail.id}/actividades`, {
          activity_type_id: this.actModal.activity_type_id,
          planned_quantity: this.actModal.planned_quantity,
          location_notes: this.actModal.location_notes || null,
        });
        this.actModal.show = false;
        await this.openDetail(this.detail.id);
      } catch (e) {
        this.actModal.error = e.response?.data?.message ?? 'Error al agregar.';
      } finally { this.actModal.saving = false; }
    },
    openReport(act) {
      const today = new Date().toISOString().substring(0,10);
      this.reportModal = { show:true, activity:act, quantity:1, report_date:today,
                           colaborador_ids:[], notes:'', saving:false, error:'' };
    },
    toggleParticipant(id) {
      const idx = this.reportModal.colaborador_ids.indexOf(id);
      if (idx >= 0) this.reportModal.colaborador_ids.splice(idx, 1);
      else this.reportModal.colaborador_ids.push(id);
    },
    async submitReport() {
      this.reportModal.error = '';
      if (!this.reportModal.colaborador_ids.length) { this.reportModal.error = 'Selecciona participantes.'; return; }
      this.reportModal.saving = true;
      try {
        await axios.post(`/talento/api/proyectos/${this.detail.id}/actividades/${this.reportModal.activity.id}/reportes`, {
          quantity: this.reportModal.quantity,
          report_date: this.reportModal.report_date,
          colaborador_ids: this.reportModal.colaborador_ids,
          notes: this.reportModal.notes || null,
        });
        this.reportModal.show = false;
        await this.openDetail(this.detail.id);
      } catch (e) {
        this.reportModal.error = e.response?.data?.error ?? 'Error al reportar.';
      } finally { this.reportModal.saving = false; }
    },
    async openActReports(act) {
      this.reportsListModal = { show:true, activity:act, reports:[], loading:true };
      try {
        const { data } = await axios.get(`/talento/api/proyectos/${this.detail.id}/actividades/${act.id}/reportes`);
        this.reportsListModal.reports = data ?? [];
      } finally { this.reportsListModal.loading = false; }
    },
    async awardBonus() {
      if (!confirm(`¿Otorgar bono del proyecto «${this.detail.name}»? Esta acción escribe asientos en el ledger.`)) return;
      this.awardingBonus = true;
      try {
        const { data } = await axios.post(`/talento/api/proyectos/${this.detail.id}/bono`);
        alert(`Bono otorgado. Avance: ${data.progress_pct}% · Total: $${this.fmt2(data.bonus_total)} · Asientos: ${data.entries_written}`);
        await this.openDetail(this.detail.id);
        this.load();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al otorgar bono.');
      } finally { this.awardingBonus = false; }
    },
    statusColor(s) { return {planning:'bg-secondary',active:'bg-primary',paused:'bg-warning text-dark',done:'bg-success'}[s]??'bg-light'; },
    statusLabel(s) { return {planning:'Planificación',active:'Activo',paused:'Pausado',done:'Finalizado'}[s]??s; },
    reportStatusColor(s) { return {pending:'bg-secondary',approved:'bg-success',capped:'bg-warning text-dark',rejected:'bg-danger'}[s]??'bg-light'; },
    reportStatusLabel(s) { return {pending:'Pendiente',approved:'Aprobado',capped:'Recortado',rejected:'Rechazado'}[s]??s; },
    fmtDate(d) { if(!d)return'—'; return new Date(d+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}); },
    fmt1(n) { return Number(n??0).toFixed(1); },
    fmt2(n) { return Number(n??0).toFixed(2); },
    fmt3(n) { return Number(n??0).toFixed(3); },

    // ── Corredor ────────────────────────────────────────────────────────────
    async toggleCorridor() {
      this.corridorOpen = !this.corridorOpen;
      if (this.corridorOpen) {
        await this.$nextTick();
        this.initCorridorMap();
      }
    },

    async loadCorridor() {
      const { data } = await axios.get(`/talento/api/proyectos/${this.detail.id}/corredor`);
      this.corridor.path = data?.corridor_path ?? [];
      this.corridor.draft = [];
      this.renderCorridorOnMap();
      await this.loadDeviations();
    },

    initCorridorMap() {
      if (this._corridorMap) return; // ya inicializado
      if (!this.$refs.corridorMap) return;

      // Centro CDMX como fallback
      const center = this.corridor.path?.length
        ? this.corridor.path[Math.floor(this.corridor.path.length / 2)]
        : [19.432, -99.133];

      this._corridorMap = L.map(this.$refs.corridorMap, { doubleClickZoom: false }).setView(center, 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
      }).addTo(this._corridorMap);

      this._corridorMap.on('click', (e) => {
        if (!this.corridor.editMode) return;
        const pt = [e.latlng.lat, e.latlng.lng];
        this.corridor.draft.push(pt);
        this.updateDraftVisuals();
      });

      this._corridorMap.on('dblclick', () => {
        if (!this.corridor.editMode) return;
        if (this.corridor.draft.length >= 2) this.finishDraw();
      });

      this.renderCorridorOnMap();
    },

    renderCorridorOnMap() {
      if (!this._corridorMap) return;

      // Limpiar polyline guardada
      if (this._corridorPolyline) { this._corridorPolyline.remove(); this._corridorPolyline = null; }

      if (this.corridor.path?.length >= 2) {
        this._corridorPolyline = L.polyline(this.corridor.path, { color: '#0d6efd', weight: 4, opacity: 0.8 })
          .addTo(this._corridorMap);
        this._corridorMap.fitBounds(this._corridorPolyline.getBounds(), { padding: [30, 30] });
      }

      this.renderDeviationMarkers();
    },

    updateDraftVisuals() {
      if (!this._corridorMap) return;

      // Limpiar markers del draft
      this._corridorMarkers.forEach(m => m.remove());
      this._corridorMarkers = [];
      if (this._corridorDraftLine) { this._corridorDraftLine.remove(); this._corridorDraftLine = null; }

      const pts = this.corridor.draft;
      if (!pts.length) return;

      pts.forEach((pt, i) => {
        const m = L.circleMarker(pt, {
          radius: i === 0 ? 8 : 5,
          color: '#0d6efd', fillColor: '#0d6efd', fillOpacity: 1,
        }).addTo(this._corridorMap);
        this._corridorMarkers.push(m);
      });

      if (pts.length >= 2) {
        this._corridorDraftLine = L.polyline(pts, { color: '#0d6efd', weight: 3, dashArray: '6,4' })
          .addTo(this._corridorMap);
      }
    },

    renderDeviationMarkers() {
      if (!this._corridorMap) return;
      this._deviationMarkers.forEach(m => m.remove());
      this._deviationMarkers = [];

      (this.corridor.deviations ?? []).forEach(dev => {
        const m = L.circleMarker([dev.detected_lat, dev.detected_lng], {
          radius: 9, color: '#dc3545', fillColor: '#dc3545', fillOpacity: 0.7, weight: 2,
        }).bindPopup(
          `<strong>${dev.colaborador?.user?.name ?? '?'}</strong><br>${dev.sustained_minutes} min · ${Math.round(dev.deviation_m)} m`
        ).addTo(this._corridorMap);
        this._deviationMarkers.push(m);
      });
    },

    startEdit() {
      this.corridor.editMode = true;
      this.corridor.draft = [...(this.corridor.path ?? [])];
      if (this._corridorMap) this._corridorMap.getContainer().style.cursor = 'crosshair';
      this.updateDraftVisuals();
    },

    cancelEdit() {
      this.corridor.editMode = false;
      this.corridor.draft = [];
      if (this._corridorMap) this._corridorMap.getContainer().style.cursor = '';
      this._corridorMarkers.forEach(m => m.remove()); this._corridorMarkers = [];
      if (this._corridorDraftLine) { this._corridorDraftLine.remove(); this._corridorDraftLine = null; }
    },

    clearCorridor() {
      this.corridor.draft = [];
      this.updateDraftVisuals();
    },

    finishDraw() {
      // Termina la edición y deja el draft listo para guardar (no lo guarda aún)
      if (this._corridorMap) this._corridorMap.getContainer().style.cursor = '';
      // Mantiene editMode=true para que aparezca "Guardar corredor"
    },

    async saveCorridor() {
      this.corridor.saving = true;
      try {
        await axios.put(`/talento/api/proyectos/${this.detail.id}/corredor`, {
          corridor_path: this.corridor.draft,
        });
        this.corridor.path = [...this.corridor.draft];
        this.corridor.editMode = false;
        this.corridor.draft = [];
        if (this._corridorMap) this._corridorMap.getContainer().style.cursor = '';
        this._corridorMarkers.forEach(m => m.remove()); this._corridorMarkers = [];
        if (this._corridorDraftLine) { this._corridorDraftLine.remove(); this._corridorDraftLine = null; }
        this.renderCorridorOnMap();
      } finally { this.corridor.saving = false; }
    },

    async loadDeviations() {
      this.corridor.loadingDeviations = true;
      try {
        const { data } = await axios.get(`/talento/api/proyectos/${this.detail.id}/corredor/desvios`);
        this.corridor.deviations = data ?? [];
        this.renderDeviationMarkers();
      } finally { this.corridor.loadingDeviations = false; }
    },

    async analyzeDeviations() {
      this.corridor.analyzing = true;
      try {
        const { data } = await axios.post(`/talento/api/proyectos/${this.detail.id}/corredor/analizar`);
        alert(`Análisis completado. Desvíos encontrados: ${data.deviations_found}`);
        await this.loadDeviations();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al analizar.');
      } finally { this.corridor.analyzing = false; }
    },

    focusDeviation(dev) {
      this.corridor.focusedDevId = dev.id;
      if (this._corridorMap) {
        this._corridorMap.setView([dev.detected_lat, dev.detected_lng], 16);
      }
    },
  },
  watch: {
    // Cuando el usuario abre la sección corredor, inicia el mapa
    corridorOpen(val) {
      if (val) {
        this.$nextTick(() => {
          this.initCorridorMap();
          if (this.detail?.id && !this.corridor.path?.length && !this._corridorPolyline) {
            this.loadCorridor();
          }
        });
      }
    },
  },
};
</script>
