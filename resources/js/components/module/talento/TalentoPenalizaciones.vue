<template>
  <div class="talento-penalizaciones">

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'penalties' }" href="#" @click.prevent="tab='penalties'">
          <i class="fa fa-gavel me-1"></i>Penalizaciones
          <span v-if="pendingAppeals" class="badge bg-warning text-dark ms-1">{{ pendingAppeals }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'appeals' }" href="#" @click.prevent="tab='appeals'">
          <i class="fa fa-balance-scale me-1"></i>Apelaciones
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'types' }" href="#" @click.prevent="tab='types'">
          <i class="fa fa-list me-1"></i>Catálogo de tipos
        </a>
      </li>
    </ul>

    <!-- ── TAB PENALIZACIONES ── -->
    <div v-if="tab === 'penalties'">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex gap-2 flex-wrap">
          <select v-model="pFilters.colaborador_id" @change="loadPenalties" class="form-select form-select-sm" style="width:200px">
            <option value="">Todos los técnicos</option>
            <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
          </select>
          <select v-model="pFilters.category" @change="loadPenalties" class="form-select form-select-sm" style="width:160px">
            <option value="">Todas las categorías</option>
            <option v-for="(label, val) in categoryLabels" :key="val" :value="val">{{ label }}</option>
          </select>
          <select v-model="pFilters.status" @change="loadPenalties" class="form-select form-select-sm" style="width:150px">
            <option value="">Todos los estados</option>
            <option value="applied">Applied</option>
            <option value="appealed">En apelación</option>
            <option value="overturned">Revocada</option>
            <option value="upheld">Mantenida</option>
          </select>
        </div>
        <button @click="openApply" class="btn btn-sm btn-danger">
          <i class="fa fa-gavel me-1"></i>Aplicar penalización
        </button>
      </div>

      <div v-if="loadingP" class="text-center py-5"><div class="spinner-border text-danger"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Técnico</th><th>Tipo</th><th>Monto</th><th>Aplicado por</th><th>Fecha</th><th>Estado</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="p in penalties" :key="p.id">
              <td class="fw-semibold small">{{ p.colaborador?.user?.name ?? '—' }}</td>
              <td class="small">
                <span class="badge me-1" :class="catColor(p.penalty_type?.category)">{{ categoryLabels[p.penalty_type?.category] ?? '?' }}</span>
                {{ p.penalty_type?.name ?? '—' }}
              </td>
              <td class="small fw-bold text-danger">{{ fmtMXN(p.amount) }}</td>
              <td class="small text-muted">{{ p.applied_by_colaborador?.user?.name ?? '—' }}</td>
              <td class="small">{{ fmtDate(p.created_at) }}</td>
              <td><span class="badge" :class="statusColor(p.status)">{{ statusLabel(p.status) }}</span></td>
              <td>
                <button @click="openView(p.id)" class="btn btn-xs btn-outline-secondary">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!penalties?.length">
              <td colspan="7" class="text-center text-muted py-4">Sin penalizaciones.</td>
            </tr>
          </tbody>
        </table>
        <!-- Paginación simple -->
        <div v-if="pPagination.last_page > 1" class="d-flex justify-content-center gap-1 mt-2">
          <button v-for="pg in pPagination.last_page" :key="pg" @click="loadPenalties(pg)"
                  class="btn btn-xs" :class="pg === pPagination.current_page ? 'btn-danger' : 'btn-outline-secondary'">
            {{ pg }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── TAB APELACIONES ── -->
    <div v-if="tab === 'appeals'">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="form-check form-switch">
          <input v-model="aFilters.pendingOnly" @change="loadAppeals" type="checkbox" class="form-check-input" id="pending-only">
          <label for="pending-only" class="form-check-label small">Solo pendientes</label>
        </div>
      </div>

      <div v-if="loadingA" class="text-center py-5"><div class="spinner-border text-warning"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Técnico</th><th>Tipo · Monto</th><th>Aplicado por</th><th>Motivo (resumen)</th><th>Estado</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="ap in appeals" :key="ap.id">
              <td class="fw-semibold small">{{ ap.penalty?.colaborador?.user?.name ?? '—' }}</td>
              <td class="small">
                {{ ap.penalty?.penalty_type?.name ?? '—' }}
                <span class="text-danger fw-bold ms-1">{{ fmtMXN(ap.penalty?.amount) }}</span>
              </td>
              <td class="small text-muted">{{ ap.penalty?.applied_by_colaborador?.user?.name ?? '—' }}</td>
              <td class="small text-truncate" style="max-width:220px" :title="ap.reason">{{ ap.reason }}</td>
              <td>
                <span v-if="ap.decision === null" class="badge bg-warning text-dark">⏳ Pendiente</span>
                <span v-else-if="ap.decision === 'overturned'" class="badge bg-success">✅ Revocada</span>
                <span v-else class="badge bg-danger">❌ Mantenida</span>
              </td>
              <td>
                <button @click="openResolve(ap)" class="btn btn-xs btn-outline-warning"
                        :disabled="ap.decision !== null">
                  {{ ap.decision !== null ? 'Ver' : 'Resolver' }}
                </button>
              </td>
            </tr>
            <tr v-if="!appeals?.length">
              <td colspan="6" class="text-center text-muted py-4">Sin apelaciones.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB CATÁLOGO ── -->
    <div v-if="tab === 'types'">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <select v-model="tFilter" @change="loadTypes" class="form-select form-select-sm" style="width:200px">
          <option value="">Todas las categorías</option>
          <option v-for="(label, val) in categoryLabels" :key="val" :value="val">{{ label }}</option>
        </select>
        <button @click="openNewType" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Nuevo tipo
        </button>
      </div>

      <div v-if="loadingT" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else>
        <div v-for="(group, cat) in groupedTypes" :key="cat" class="mb-4">
          <h6 class="text-uppercase small border-bottom pb-1 mb-2" :class="catTextColor(cat)">
            <i class="fa fa-tag me-1"></i>{{ categoryLabels[cat] ?? cat }}
          </h6>
          <div class="row g-3">
            <div v-for="t in group" :key="t.id" class="col-md-4 col-lg-3">
              <div class="card h-100 shadow-sm">
                <div v-if="t.reference_image_path" style="height:100px;overflow:hidden;background:#f8f9fa;">
                  <img :src="`/storage/${t.reference_image_path}`" class="w-100 h-100"
                       style="object-fit:cover;" :alt="t.name" @error="$event.target.style.display='none'">
                </div>
                <div v-else class="d-flex align-items-center justify-content-center bg-light" style="height:70px;">
                  <i class="fa fa-2x" :class="catIcon(t.category)" style="opacity:.3"></i>
                </div>
                <div class="card-body p-2">
                  <div class="fw-semibold small">{{ t.name }}</div>
                  <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge badge-sm" :class="catColor(t.category)" style="font-size:10px">{{ categoryLabels[t.category] }}</span>
                    <span class="badge bg-light text-muted border" style="font-size:10px">{{ t.penalty_kind === 'event' ? 'Evento' : 'Estado' }}</span>
                    <span v-if="!t.active" class="badge bg-secondary" style="font-size:10px">Inactivo</span>
                  </div>
                  <div class="text-danger fw-bold mt-1 small">{{ fmtMXN(t.amount) }}</div>
                </div>
                <div class="card-footer p-1 d-flex gap-1">
                  <button @click="openEditType(t)" class="btn btn-xs btn-outline-primary flex-fill"><i class="fa fa-pen"></i></button>
                  <button @click="openTypeImg(t)" class="btn btn-xs btn-outline-secondary flex-fill" title="Imagen de referencia"><i class="fa fa-image"></i></button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!penaltyTypes?.length" class="text-center text-muted py-4">Sin tipos. Crea el primero.</div>
      </div>
    </div>

    <!-- ── MODAL APLICAR PENALIZACIÓN ── -->
    <div v-if="applyModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-gavel me-2 text-danger"></i>Aplicar penalización</h5>
            <button @click="applyModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Técnico <span class="text-danger">*</span></label>
                <select v-model="applyModal.colaborador_id" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo de penalización <span class="text-danger">*</span></label>
                <select v-model="applyModal.penalty_type_id" @change="onTypeSelected" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="t in penaltyTypes" :key="t.id" :value="t.id">
                    [{{ categoryLabels[t.category] }}] {{ t.name }} — {{ fmtMXN(t.amount) }}
                  </option>
                </select>
              </div>

              <!-- Referencia del tipo -->
              <div v-if="selectedType" class="col-12">
                <div class="d-flex gap-3 align-items-start p-2 bg-light rounded">
                  <img v-if="selectedType.reference_image_path"
                       :src="`/storage/${selectedType.reference_image_path}`"
                       class="img-thumbnail" style="width:80px;height:60px;object-fit:cover;"
                       @error="$event.target.style.display='none'">
                  <div class="small">
                    <div class="fw-semibold">{{ selectedType.name }}</div>
                    <div class="text-muted">{{ categoryLabels[selectedType.category] }} · {{ selectedType.penalty_kind === 'event' ? 'Evento puntual' : 'Estado persistente' }}</div>
                    <div class="text-danger fw-bold mt-1">Monto default: {{ fmtMXN(selectedType.amount) }}</div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Monto ($) <span class="text-muted small">(editable)</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model.number="applyModal.amount" type="number" min="0" step="50" class="form-control">
                </div>
              </div>

              <!-- Foto evidencia -->
              <div class="col-12">
                <label class="form-label">Foto de evidencia</label>
                <input type="file" class="form-control form-control-sm" accept="image/*"
                       @change="onEvidenceSelected" ref="evidenceInput">
                <div class="small text-muted mt-1">
                  <i class="fa fa-shield-alt me-1 text-primary"></i>Se almacena cifrada. Aplica antifraude Fase 4a.
                </div>
              </div>

              <!-- GPS -->
              <div class="col-md-4">
                <label class="form-label">Latitud</label>
                <input v-model.number="applyModal.captured_lat" type="number" step="any" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label">Longitud</label>
                <input v-model.number="applyModal.captured_lng" type="number" step="any" class="form-control form-control-sm">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button @click="useGps" class="btn btn-sm btn-outline-secondary w-100">
                  <i class="fa fa-map-marker-alt me-1"></i>Mi ubicación
                </button>
              </div>

              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea v-model="applyModal.notes" class="form-control form-control-sm" rows="2"
                          placeholder="Describe la situación observada…"></textarea>
              </div>

              <div v-if="applyModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ applyModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="applyModal.show=false" class="btn btn-secondary" :disabled="applyModal.saving">Cancelar</button>
            <button @click="submitPenalty" class="btn btn-danger" :disabled="applyModal.saving">
              <span v-if="applyModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Aplicando…</span>
              <span v-else><i class="fa fa-gavel me-1"></i>Aplicar penalización</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL VER PENALIZACIÓN ── -->
    <div v-if="viewModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Penalización #{{ viewModal.penalty?.id }}
              <span class="ms-2 badge" :class="statusColor(viewModal.penalty?.status)">
                {{ statusLabel(viewModal.penalty?.status) }}
              </span>
            </h5>
            <button @click="viewModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="viewModal.penalty">
            <div class="row g-3">
              <div class="col-md-4 small text-muted">Técnico:
                <span class="text-dark fw-semibold">{{ viewModal.penalty.colaborador?.user?.name }}</span>
              </div>
              <div class="col-md-4 small text-muted">Aplicado por:
                <span class="text-dark">{{ viewModal.penalty.applied_by_colaborador?.user?.name ?? '—' }}</span>
              </div>
              <div class="col-md-4 small text-muted">Fecha:
                <span class="text-dark">{{ fmtDate(viewModal.penalty.created_at) }}</span>
              </div>
              <div class="col-md-6 small text-muted">Tipo:
                <span class="text-dark fw-semibold">{{ viewModal.penalty.penalty_type?.name }}</span>
                <span class="badge ms-2" :class="catColor(viewModal.penalty.penalty_type?.category)">
                  {{ categoryLabels[viewModal.penalty.penalty_type?.category] }}
                </span>
              </div>
              <div class="col-md-3 small text-muted">Monto:
                <span class="text-danger fw-bold fs-5">{{ fmtMXN(viewModal.penalty.amount) }}</span>
              </div>

              <!-- Foto evidencia -->
              <div v-if="viewModal.penalty.evidence_photo_path" class="col-12">
                <label class="form-label small text-muted">Evidencia fotográfica</label>
                <div>
                  <img :src="`/talento/penalty-evidence/${viewModal.penalty.id}`"
                       class="img-thumbnail" style="max-height:220px;"
                       @error="$event.target.style.display='none'">
                </div>
              </div>

              <div v-if="viewModal.penalty.notes" class="col-12 small">
                <span class="text-muted">Notas:</span> {{ viewModal.penalty.notes }}
              </div>

              <!-- Apelación existente -->
              <template v-if="viewModal.penalty.appeal">
                <div class="col-12"><hr class="my-1">
                  <h6 class="text-uppercase small text-muted">Apelación</h6>
                </div>
                <div class="col-12 small">
                  <span class="text-muted">Motivo:</span> {{ viewModal.penalty.appeal.reason }}
                </div>
                <div v-if="viewModal.penalty.appeal.decision" class="col-12">
                  <span class="badge fs-6" :class="viewModal.penalty.appeal.decision === 'overturned' ? 'bg-success' : 'bg-danger'">
                    {{ viewModal.penalty.appeal.decision === 'overturned' ? '✅ Revocada' : '❌ Mantenida' }}
                  </span>
                  <span class="small text-muted ms-2">{{ viewModal.penalty.appeal.decision_notes }}</span>
                </div>
              </template>

              <!-- Formulario apelar (solo si status=applied y el usuario es el técnico) -->
              <template v-if="viewModal.penalty.status === 'applied' && !viewModal.penalty.appeal && viewModal.canAppeal">
                <div class="col-12"><hr class="my-1">
                  <h6 class="small text-uppercase text-muted">
                    <i class="fa fa-balance-scale me-1"></i>Apelar esta penalización
                  </h6>
                </div>
                <div class="col-12">
                  <label class="form-label">Motivo de la apelación <span class="text-danger">*</span></label>
                  <textarea v-model="viewModal.appealReason" class="form-control form-control-sm" rows="3"
                            placeholder="Describe por qué consideras incorrecta esta penalización…"></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Evidencia (opcional)</label>
                  <input type="file" class="form-control form-control-sm" accept="image/*"
                         @change="onAppealEvidenceSelected" ref="appealEvidenceInput">
                </div>
                <div v-if="viewModal.appealError" class="col-12">
                  <div class="alert alert-danger py-2 small mb-0">{{ viewModal.appealError }}</div>
                </div>
                <div class="col-12">
                  <button @click="submitAppeal" class="btn btn-warning" :disabled="viewModal.submittingAppeal">
                    <span v-if="viewModal.submittingAppeal"><span class="spinner-border spinner-border-sm me-1"></span>Enviando…</span>
                    <span v-else><i class="fa fa-balance-scale me-1"></i>Enviar apelación</span>
                  </button>
                </div>
              </template>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="viewModal.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL RESOLVER APELACIÓN ── -->
    <div v-if="resolveModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-balance-scale me-2 text-warning"></i>Resolver apelación</h5>
            <button @click="resolveModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="resolveModal.appeal">
            <div class="card mb-3 border-0 bg-light">
              <div class="card-body py-2 small">
                <div><span class="text-muted">Penalización:</span>
                  <strong class="ms-1">{{ resolveModal.appeal.penalty?.penalty_type?.name }}</strong>
                  <span class="text-danger fw-bold ms-2">{{ fmtMXN(resolveModal.appeal.penalty?.amount) }}</span>
                </div>
                <div><span class="text-muted">Técnico:</span>
                  <span class="ms-1">{{ resolveModal.appeal.penalty?.colaborador?.user?.name }}</span>
                </div>
                <div><span class="text-muted">Aplicada por:</span>
                  <span class="ms-1 fw-semibold">{{ resolveModal.appeal.penalty?.applied_by_colaborador?.user?.name }}</span>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Motivo del técnico</label>
              <div class="p-2 bg-light rounded small">{{ resolveModal.appeal.reason }}</div>
            </div>

            <!-- Regla de justicia -->
            <div v-if="resolveModal.isApplier" class="alert alert-warning py-2 small">
              <i class="fa fa-exclamation-triangle me-1"></i>
              <strong>Conflicto de interés:</strong> Eres quien aplicó esta penalización.
              La regla de justicia requiere que el revisor sea una persona distinta.
              No puedes resolver esta apelación.
            </div>

            <template v-if="!resolveModal.appeal.decision && !resolveModal.isApplier">
              <div class="mb-3">
                <label class="form-label">Decisión <span class="text-danger">*</span></label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input v-model="resolveModal.decision" type="radio" value="overturned"
                           class="form-check-input" id="r-overturned">
                    <label for="r-overturned" class="form-check-label text-success fw-semibold">
                      <i class="fa fa-check-circle me-1"></i>Revocar (crédito al ledger)
                    </label>
                  </div>
                  <div class="form-check">
                    <input v-model="resolveModal.decision" type="radio" value="upheld"
                           class="form-check-input" id="r-upheld">
                    <label for="r-upheld" class="form-check-label text-danger fw-semibold">
                      <i class="fa fa-times-circle me-1"></i>Mantener penalización
                    </label>
                  </div>
                </div>
              </div>
              <div v-if="resolveModal.decision === 'overturned'" class="alert alert-success py-2 small mb-3">
                <i class="fa fa-info-circle me-1"></i>
                Se agregará un asiento de <strong>crédito (reversión)</strong> en el ledger por
                {{ fmtMXN(resolveModal.appeal.penalty?.amount) }}. El débito original permanece intacto (ledger inmutable).
              </div>
              <div class="mb-3">
                <label class="form-label">Notas de decisión</label>
                <textarea v-model="resolveModal.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
              <div v-if="resolveModal.error" class="alert alert-danger py-2 small mb-0">{{ resolveModal.error }}</div>
            </template>

            <!-- Vista resolved -->
            <template v-if="resolveModal.appeal.decision">
              <div>
                <span class="badge fs-6" :class="resolveModal.appeal.decision === 'overturned' ? 'bg-success' : 'bg-danger'">
                  {{ resolveModal.appeal.decision === 'overturned' ? '✅ Revocada' : '❌ Mantenida' }}
                </span>
                <span v-if="resolveModal.appeal.decision_notes" class="small text-muted ms-2">
                  {{ resolveModal.appeal.decision_notes }}
                </span>
              </div>
            </template>
          </div>
          <div class="modal-footer">
            <button @click="resolveModal.show=false" class="btn btn-secondary">Cerrar</button>
            <button v-if="!resolveModal.appeal?.decision && !resolveModal.isApplier"
                    @click="confirmResolve" class="btn btn-warning"
                    :disabled="resolveModal.saving || !resolveModal.decision">
              <span v-if="resolveModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else><i class="fa fa-gavel me-1"></i>Confirmar decisión</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL TIPO (crear / editar) ── -->
    <div v-if="typeModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ typeModal.id ? 'Editar tipo' : 'Nuevo tipo de penalización' }}</h5>
            <button @click="typeModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="typeModal.name" type="text" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Categoría</label>
                <select v-model="typeModal.category" class="form-select">
                  <option v-for="(label, val) in categoryLabels" :key="val" :value="val">{{ label }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo de penalización</label>
                <select v-model="typeModal.penalty_kind" class="form-select">
                  <option value="event">Evento puntual</option>
                  <option value="status">Estado persistente</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Monto ($)</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model.number="typeModal.amount" type="number" min="0" step="50" class="form-control">
                </div>
              </div>
              <div class="col-md-6 d-flex align-items-end gap-2">
                <input v-model="typeModal.active" type="checkbox" class="form-check-input" id="t-active">
                <label for="t-active" class="form-check-label">Activo</label>
              </div>
              <div v-if="typeModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ typeModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="typeModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveType" class="btn btn-primary" :disabled="typeModal.saving">
              <span v-if="typeModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL IMAGEN TIPO ── -->
    <div v-if="imgModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Imagen de referencia — {{ imgModal.typeName }}</h5>
            <button @click="imgModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div v-if="imgModal.currentPath" class="text-center mb-3">
              <img :src="`/storage/${imgModal.currentPath}`" class="img-fluid img-thumbnail" style="max-height:160px;"
                   @error="$event.target.style.display='none'">
            </div>
            <input type="file" class="form-control" accept="image/*" @change="onTypeImgSelected" ref="typeImgInput">
            <div v-if="imgModal.error" class="alert alert-danger py-2 small mt-2 mb-0">{{ imgModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="imgModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="uploadTypeImg" class="btn btn-primary" :disabled="imgModal.saving || !imgModal.file">
              <span v-if="imgModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Subiendo…</span>
              <span v-else>Subir imagen</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoPenalizaciones',
  data() {
    return {
      tab: 'penalties',

      // Penalizaciones
      penalties: [], loadingP: true,
      pFilters: { colaborador_id: '', category: '', status: '' },
      pPagination: { current_page: 1, last_page: 1 },

      // Apelaciones
      appeals: [], loadingA: true,
      aFilters: { pendingOnly: true },

      // Catálogo
      penaltyTypes: [], loadingT: true,
      tFilter: '',

      // Data compartida
      colaboradores: [],

      // Diccionarios
      categoryLabels: { safety: '🦺 Seguridad', aesthetic: '🎨 Estético', malpractice: '⚠ Mala práctica', other: '📋 Otro' },

      // Apply modal
      applyModal: {
        show: false, colaborador_id: null, penalty_type_id: null, amount: 0,
        evidenceFile: null, captured_lat: null, captured_lng: null,
        notes: '', saving: false, error: '',
      },

      // View modal
      viewModal: {
        show: false, penalty: null, canAppeal: false,
        appealReason: '', appealEvidenceFile: null,
        appealError: '', submittingAppeal: false,
      },

      // Resolve modal
      resolveModal: {
        show: false, appeal: null, isApplier: false,
        decision: '', notes: '', saving: false, error: '',
      },

      // Type modal
      typeModal: {
        show: false, id: null, name: '', category: 'safety',
        penalty_kind: 'event', amount: 0, active: true,
        saving: false, error: '',
      },

      // Img modal
      imgModal: {
        show: false, typeId: null, typeName: '', currentPath: null,
        file: null, saving: false, error: '',
      },

      // Auth
      myColaboradorId: null,
    };
  },
  computed: {
    selectedType() {
      if (!this.applyModal.penalty_type_id) return null;
      return this.penaltyTypes.find(t => t.id === this.applyModal.penalty_type_id) ?? null;
    },
    groupedTypes() {
      const g = {};
      for (const t of (this.penaltyTypes ?? [])) {
        if (!g[t.category]) g[t.category] = [];
        g[t.category].push(t);
      }
      return g;
    },
    pendingAppeals() {
      return (this.appeals ?? []).filter(a => a.decision === null).length || null;
    },
  },
  mounted() {
    this.loadAll();
  },
  methods: {
    async loadAll() {
      await Promise.all([
        this.loadColaboradores(),
        this.loadTypes(),
        this.loadPenalties(),
        this.loadAppeals(),
        this.loadMyProfile(),
      ]);
    },
    async loadMyProfile() {
      try {
        const { data } = await axios.get('/talento/api/colaboradores', { params: { my_profile: 1, per_page: 1 } });
        // Intentamos encontrar el colaborador cuyo user_id coincide con el auth
        // Usamos el primer resultado si el endpoint filtra por el usuario actual
        if (data?.data?.length) this.myColaboradorId = data.data[0]?.id ?? null;
      } catch { /* graceful */ }
    },
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 300, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async loadTypes() {
      this.loadingT = true;
      try {
        const params = this.tFilter ? { category: this.tFilter } : {};
        const { data } = await axios.get('/talento/api/penalty-types', { params });
        this.penaltyTypes = data ?? [];
      } finally { this.loadingT = false; }
    },
    async loadPenalties(page = 1) {
      this.loadingP = true;
      try {
        const params = { page };
        if (this.pFilters.colaborador_id) params.colaborador_id = this.pFilters.colaborador_id;
        if (this.pFilters.category)        params.category        = this.pFilters.category;
        if (this.pFilters.status)          params.status          = this.pFilters.status;
        const { data } = await axios.get('/talento/api/penalties', { params });
        this.penalties    = data?.data ?? [];
        this.pPagination  = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally { this.loadingP = false; }
    },
    async loadAppeals() {
      this.loadingA = true;
      try {
        const params = this.aFilters.pendingOnly ? { pending_only: 1 } : {};
        const { data } = await axios.get('/talento/api/penalty-appeals', { params });
        this.appeals = data?.data ?? [];
      } finally { this.loadingA = false; }
    },

    // ── Apply ──────────────────────────────────────────────────────────────
    openApply() {
      this.applyModal = {
        show: true, colaborador_id: null, penalty_type_id: null, amount: 0,
        evidenceFile: null, captured_lat: null, captured_lng: null,
        notes: '', saving: false, error: '',
      };
    },
    onTypeSelected() {
      if (this.selectedType) this.applyModal.amount = Number(this.selectedType.amount);
    },
    onEvidenceSelected(e) { this.applyModal.evidenceFile = e.target.files[0] ?? null; },
    useGps() {
      if (!navigator.geolocation) return alert('Geolocalización no disponible.');
      navigator.geolocation.getCurrentPosition(pos => {
        this.applyModal.captured_lat = parseFloat(pos.coords.latitude.toFixed(7));
        this.applyModal.captured_lng = parseFloat(pos.coords.longitude.toFixed(7));
      }, () => alert('No se pudo obtener ubicación.'));
    },
    async submitPenalty() {
      this.applyModal.error = '';
      if (!this.applyModal.colaborador_id) { this.applyModal.error = 'Selecciona un técnico.'; return; }
      if (!this.applyModal.penalty_type_id) { this.applyModal.error = 'Selecciona un tipo de penalización.'; return; }
      this.applyModal.saving = true;
      try {
        const fd = new FormData();
        fd.append('colaborador_id',  this.applyModal.colaborador_id);
        fd.append('penalty_type_id', this.applyModal.penalty_type_id);
        fd.append('amount',          this.applyModal.amount);
        if (this.applyModal.captured_lat != null) fd.append('captured_lat', this.applyModal.captured_lat);
        if (this.applyModal.captured_lng != null) fd.append('captured_lng', this.applyModal.captured_lng);
        fd.append('captured_in_app', '0');
        if (this.applyModal.notes) fd.append('notes', this.applyModal.notes);
        if (this.applyModal.evidenceFile) fd.append('evidence_photo', this.applyModal.evidenceFile);
        await axios.post('/talento/api/penalties', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        this.applyModal.show = false;
        this.loadPenalties();
      } catch (e) {
        this.applyModal.error = e.response?.data?.message ?? e.response?.data?.error ?? 'Error al aplicar.';
      } finally { this.applyModal.saving = false; }
    },

    // ── View / Appeal ──────────────────────────────────────────────────────
    async openView(id) {
      const { data } = await axios.get(`/talento/api/penalties/${id}`);
      this.viewModal = {
        show: true, penalty: data,
        canAppeal: this.myColaboradorId !== null && this.myColaboradorId === data.colaborador_id,
        appealReason: '', appealEvidenceFile: null, appealError: '', submittingAppeal: false,
      };
    },
    onAppealEvidenceSelected(e) { this.viewModal.appealEvidenceFile = e.target.files[0] ?? null; },
    async submitAppeal() {
      this.viewModal.appealError = '';
      if (!this.viewModal.appealReason) { this.viewModal.appealError = 'El motivo es requerido.'; return; }
      this.viewModal.submittingAppeal = true;
      try {
        const fd = new FormData();
        fd.append('reason', this.viewModal.appealReason);
        if (this.viewModal.appealEvidenceFile) fd.append('evidence', this.viewModal.appealEvidenceFile);
        await axios.post(`/talento/api/penalties/${this.viewModal.penalty.id}/appeal`, fd,
          { headers: { 'Content-Type': 'multipart/form-data' } });
        this.viewModal.show = false;
        this.loadPenalties();
        this.loadAppeals();
      } catch (e) {
        this.viewModal.appealError = e.response?.data?.error ?? 'Error al enviar apelación.';
      } finally { this.viewModal.submittingAppeal = false; }
    },

    // ── Resolve ────────────────────────────────────────────────────────────
    openResolve(ap) {
      const appliedByColId = ap.penalty?.applied_by_colaborador?.id
                          ?? ap.penalty?.applied_by;
      this.resolveModal = {
        show: true, appeal: ap,
        isApplier: this.myColaboradorId !== null && this.myColaboradorId === appliedByColId,
        decision: '', notes: '', saving: false, error: '',
      };
    },
    async confirmResolve() {
      this.resolveModal.error = '';
      if (!this.resolveModal.decision) { this.resolveModal.error = 'Selecciona una decisión.'; return; }
      this.resolveModal.saving = true;
      try {
        const { data } = await axios.post(
          `/talento/api/penalty-appeals/${this.resolveModal.appeal.id}/resolve`,
          { decision: this.resolveModal.decision, decision_notes: this.resolveModal.notes || null }
        );
        this.resolveModal.appeal = data;
        this.loadPenalties();
        this.loadAppeals();
      } catch (e) {
        this.resolveModal.error = e.response?.data?.error ?? e.response?.data?.message ?? 'Error al resolver.';
      } finally { this.resolveModal.saving = false; }
    },

    // ── Types CRUD ─────────────────────────────────────────────────────────
    openNewType() {
      this.typeModal = { show: true, id: null, name: '', category: 'safety',
                         penalty_kind: 'event', amount: 0, active: true, saving: false, error: '' };
    },
    openEditType(t) {
      this.typeModal = { show: true, id: t.id, name: t.name, category: t.category,
                         penalty_kind: t.penalty_kind, amount: Number(t.amount),
                         active: t.active, saving: false, error: '' };
    },
    async saveType() {
      this.typeModal.error = '';
      if (!this.typeModal.name) { this.typeModal.error = 'El nombre es requerido.'; return; }
      this.typeModal.saving = true;
      try {
        const payload = { name: this.typeModal.name, category: this.typeModal.category,
                          penalty_kind: this.typeModal.penalty_kind, amount: this.typeModal.amount,
                          active: this.typeModal.active };
        if (this.typeModal.id) {
          await axios.put(`/talento/api/penalty-types/${this.typeModal.id}`, payload);
        } else {
          await axios.post('/talento/api/penalty-types', payload);
        }
        this.typeModal.show = false;
        this.loadTypes();
      } catch (e) {
        this.typeModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.typeModal.saving = false; }
    },
    openTypeImg(t) {
      this.imgModal = { show: true, typeId: t.id, typeName: t.name,
                        currentPath: t.reference_image_path, file: null, saving: false, error: '' };
    },
    onTypeImgSelected(e) { this.imgModal.file = e.target.files[0] ?? null; },
    async uploadTypeImg() {
      if (!this.imgModal.file) return;
      this.imgModal.saving = true;
      try {
        const fd = new FormData();
        fd.append('image', this.imgModal.file);
        const { data } = await axios.post(`/talento/api/penalty-types/${this.imgModal.typeId}/image`, fd,
          { headers: { 'Content-Type': 'multipart/form-data' } });
        this.imgModal.currentPath = data.path;
        this.imgModal.show = false;
        this.loadTypes();
      } catch (e) {
        this.imgModal.error = e.response?.data?.message ?? 'Error al subir.';
      } finally { this.imgModal.saving = false; }
    },

    // ── Helpers ────────────────────────────────────────────────────────────
    statusColor(s) {
      return { applied:'bg-primary', appealed:'bg-warning text-dark', overturned:'bg-success', upheld:'bg-danger' }[s] ?? 'bg-secondary';
    },
    statusLabel(s) {
      return { applied:'Aplicada', appealed:'En apelación', overturned:'Revocada', upheld:'Mantenida' }[s] ?? s;
    },
    catColor(c) {
      return { safety:'bg-danger', malpractice:'bg-warning text-dark', aesthetic:'bg-info text-dark', other:'bg-secondary' }[c] ?? 'bg-light';
    },
    catTextColor(c) {
      return { safety:'text-danger', malpractice:'text-warning', aesthetic:'text-info', other:'text-muted' }[c] ?? 'text-muted';
    },
    catIcon(c) {
      return { safety:'fa-hard-hat', malpractice:'fa-exclamation-triangle', aesthetic:'fa-paint-brush', other:'fa-tag' }[c] ?? 'fa-tag';
    },
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    fmtMXN(n) {
      return n == null ? '—' : '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
  },
};
</script>
