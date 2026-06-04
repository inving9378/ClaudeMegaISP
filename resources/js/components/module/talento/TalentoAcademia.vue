<template>
  <div class="talento-academia">

    <!-- Tabs principales -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'catalog' }" href="#" @click.prevent="tab='catalog'; courseDetail=null">
          <i class="fa fa-book-open me-1"></i>Catálogo de cursos
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'certs' }" href="#" @click.prevent="tab='certs'; loadMyCerts()">
          <i class="fa fa-medal me-1"></i>Mis certificaciones
        </a>
      </li>
      <li class="nav-item" v-if="canManage">
        <a class="nav-link" :class="{ active: tab === 'admin' }" href="#" @click.prevent="tab='admin'">
          <i class="fa fa-cog me-1"></i>Admin
        </a>
      </li>
    </ul>

    <!-- ── TAB CATÁLOGO ── -->
    <div v-if="tab === 'catalog'">

      <!-- Vista detalle del curso -->
      <template v-if="courseDetail">
        <div class="d-flex align-items-center gap-3 mb-3">
          <button @click="courseDetail=null; examScreen=false" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>Catálogo
          </button>
          <h5 class="mb-0">{{ courseDetail.title }}</h5>
          <span v-if="myCertMap[courseDetail.id]" class="badge bg-success ms-2">
            <i class="fa fa-medal me-1"></i>Certificado
          </span>
        </div>

        <!-- Pantalla de examen -->
        <div v-if="examScreen">
          <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-2">
              <strong>{{ courseDetail.exams?.[0]?.title }}</strong>
              <span class="float-end small">Puntaje mínimo: {{ courseDetail.exams?.[0]?.passing_score }}%</span>
            </div>
            <div class="card-body">
              <div v-if="examResult">
                <div class="text-center py-4">
                  <div class="display-4 fw-bold" :class="examResult.passed ? 'text-success' : 'text-danger'">
                    {{ examResult.score }}<span class="fs-4">/100</span>
                  </div>
                  <div class="fs-5 mt-2" :class="examResult.passed ? 'text-success' : 'text-danger'">
                    {{ examResult.passed ? '✅ APROBADO' : '❌ No aprobado' }}
                    (mínimo: {{ examResult.passing_score }}%)
                  </div>
                  <div v-if="examResult.certification" class="alert alert-success mt-3 d-inline-block">
                    <i class="fa fa-medal me-1"></i>¡Certificación otorgada!
                  </div>
                  <div class="mt-3">
                    <button @click="examScreen=false; examResult=null; loadCourseDetail(courseDetail.id)" class="btn btn-primary">
                      Volver al curso
                    </button>
                  </div>
                </div>
              </div>
              <div v-else>
                <div v-for="(q, idx) in examQuestions" :key="q.id" class="mb-4">
                  <div class="fw-semibold mb-2">{{ idx + 1 }}. {{ q.question }}
                    <span class="badge bg-light text-muted border ms-1" style="font-size:10px">{{ q.points }} pt{{ q.points > 1 ? 's' : '' }}</span>
                  </div>
                  <div v-for="(opt, oi) in q.options" :key="oi" class="form-check ms-2">
                    <input :id="`q${q.id}o${oi}`" class="form-check-input"
                           :type="q.type === 'multiple' ? 'checkbox' : 'radio'"
                           :name="`q${q.id}`"
                           :checked="isSelected(q.id, oi)"
                           @change="toggleAnswer(q, oi)">
                    <label :for="`q${q.id}o${oi}`" class="form-check-label">{{ opt }}</label>
                  </div>
                </div>
                <button @click="submitExam" class="btn btn-success" :disabled="submittingExam">
                  <span v-if="submittingExam"><span class="spinner-border spinner-border-sm me-1"></span>Calificando…</span>
                  <span v-else><i class="fa fa-paper-plane me-1"></i>Enviar y calificar</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Vista normal del curso -->
        <template v-else>
          <p v-if="courseDetail.description" class="text-muted small mb-3">{{ courseDetail.description }}</p>

          <!-- Materiales -->
          <div v-if="courseDetail.materials?.length" class="mb-4">
            <h6 class="text-uppercase text-muted small mb-2">Material del curso</h6>
            <div v-for="mat in courseDetail.materials" :key="mat.id" class="card mb-2 shadow-sm">
              <div class="card-body py-2">
                <div class="fw-semibold small mb-1">
                  <i class="fa me-1" :class="matIcon(mat.type)"></i>{{ mat.title ?? typeLabelMat(mat.type) }}
                </div>

                <!-- Texto -->
                <div v-if="mat.type === 'text' && mat.content" class="small" v-html="mat.content" style="max-height:300px;overflow-y:auto;"></div>

                <!-- Video -->
                <div v-if="mat.type === 'video' && mat.video_url">
                  <div class="ratio ratio-16x9" style="max-width:560px;">
                    <iframe :src="embedUrl(mat.video_url)" allowfullscreen class="rounded"></iframe>
                  </div>
                </div>

                <!-- Referencia a estándar -->
                <div v-if="mat.type === 'reference' && mat.reference_standard" class="d-flex gap-3 align-items-start">
                  <img v-if="mat.reference_standard.reference_image_path"
                       :src="`/storage/${mat.reference_standard.reference_image_path}`"
                       class="img-thumbnail" style="width:100px;height:80px;object-fit:cover;"
                       @error="$event.target.style.display='none'">
                  <div class="small">
                    <div class="fw-semibold">{{ mat.reference_standard.name }}</div>
                    <div v-if="mat.reference_standard.ideal_value" class="text-success mt-1">
                      <i class="fa fa-bullseye me-1"></i>Valor ideal: {{ mat.reference_standard.ideal_value }}
                    </div>
                  </div>
                </div>

                <!-- Referencia a tipo de penalización -->
                <div v-if="mat.type === 'reference' && mat.reference_penalty_type" class="d-flex gap-3 align-items-start">
                  <img v-if="mat.reference_penalty_type.reference_image_path"
                       :src="`/storage/${mat.reference_penalty_type.reference_image_path}`"
                       class="img-thumbnail" style="width:100px;height:80px;object-fit:cover;"
                       @error="$event.target.style.display='none'">
                  <div class="small">
                    <div class="fw-semibold text-danger">
                      <i class="fa fa-exclamation-triangle me-1"></i>{{ mat.reference_penalty_type.name }}
                    </div>
                    <div class="text-muted mt-1">Penalización: ${{ fmt2(mat.reference_penalty_type.amount) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-muted small fst-italic mb-3">Sin materiales cargados aún.</div>

          <!-- Sección examen -->
          <div v-if="courseDetail.exams?.length" class="card shadow-sm mb-3">
            <div class="card-body py-2">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <h6 class="mb-1 small"><i class="fa fa-file-alt me-1 text-primary"></i>{{ courseDetail.exams[0].title }}</h6>
                  <div class="small text-muted">Puntaje mínimo para aprobar: <strong>{{ courseDetail.exams[0].passing_score }}%</strong></div>
                  <div v-if="myBestAttempt" class="small mt-1">
                    Mejor puntaje: <strong :class="myBestAttempt.passed ? 'text-success' : 'text-warning'">{{ myBestAttempt.score }}%</strong>
                    <span v-if="myBestAttempt.passed" class="text-success ms-1">✅ Aprobado</span>
                  </div>
                  <div v-else class="small text-muted mt-1">No presentado aún.</div>
                </div>
                <button @click="startExam" class="btn btn-sm btn-primary" :disabled="loadingExam">
                  <span v-if="loadingExam"><span class="spinner-border spinner-border-sm me-1"></span></span>
                  <span v-else><i class="fa fa-pencil-alt me-1"></i>{{ myBestAttempt?.passed ? 'Re-presentar' : 'Presentar examen' }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Evaluación práctica (solo evaluadores) -->
          <div v-if="canEvaluate" class="card shadow-sm mb-3 border-info">
            <div class="card-body py-2">
              <h6 class="small mb-2"><i class="fa fa-user-check me-1 text-info"></i>Evaluación práctica</h6>
              <div class="row g-2">
                <div class="col-md-4">
                  <select v-model="practForm.colaborador_id" class="form-select form-select-sm">
                    <option :value="null">— Técnico —</option>
                    <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <div class="d-flex gap-3 pt-1">
                    <div class="form-check">
                      <input v-model="practForm.result" type="radio" value="approved" class="form-check-input" id="pr-ok">
                      <label for="pr-ok" class="form-check-label small text-success">✅ Aprobado</label>
                    </div>
                    <div class="form-check">
                      <input v-model="practForm.result" type="radio" value="rejected" class="form-check-input" id="pr-no">
                      <label for="pr-no" class="form-check-label small text-danger">❌ Rechazado</label>
                    </div>
                  </div>
                </div>
                <div class="col-md-5 d-flex gap-2">
                  <input type="file" class="form-control form-control-sm" accept="image/*"
                         @change="e => practForm.evidenceFile = e.target.files[0]">
                  <button @click="submitPractical" class="btn btn-sm btn-info text-white" :disabled="practForm.saving || !practForm.colaborador_id">
                    <span v-if="practForm.saving"><span class="spinner-border spinner-border-sm"></span></span>
                    <span v-else>Registrar</span>
                  </button>
                </div>
                <div class="col-12" v-if="practForm.notes !== undefined">
                  <input v-model="practForm.notes" type="text" class="form-control form-control-sm" placeholder="Notas de la evaluación">
                </div>
              </div>
              <div v-if="practForm.successMsg" class="alert alert-success py-1 small mt-2 mb-0">{{ practForm.successMsg }}</div>
              <div v-if="practForm.error" class="alert alert-danger py-1 small mt-2 mb-0">{{ practForm.error }}</div>
            </div>
          </div>
        </template>
      </template>

      <!-- Lista de cursos (catálogo) -->
      <template v-else>
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <select v-model="catFilter" @change="loadCourses" class="form-select form-select-sm" style="width:180px">
            <option value="">Todos los departamentos</option>
            <option value="técnicos">Técnicos</option>
            <option value="general">General</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div v-if="loadingCourses" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else class="row g-3">
          <div v-for="c in courses" :key="c.id" class="col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm" :class="myCertMap[c.id] ? 'border-success' : ''">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-1">
                  <h6 class="card-title mb-0 small fw-semibold">{{ c.title }}</h6>
                  <span v-if="myCertMap[c.id]" class="badge bg-success ms-1" style="white-space:nowrap"><i class="fa fa-medal"></i></span>
                </div>
                <div v-if="c.department" class="badge bg-light text-muted border small mb-2">{{ c.department }}</div>
                <p v-if="c.description" class="card-text small text-muted" style="font-size:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ c.description }}</p>
              </div>
              <div class="card-footer p-1">
                <button @click="loadCourseDetail(c.id)" class="btn btn-xs btn-primary w-100">
                  <i class="fa fa-book-open me-1"></i>Ver curso
                </button>
              </div>
            </div>
          </div>
          <div v-if="!courses?.length" class="col-12 text-center text-muted py-4">Sin cursos disponibles.</div>
        </div>
      </template>
    </div>

    <!-- ── TAB MIS CERTIFICACIONES ── -->
    <div v-if="tab === 'certs'">
      <div v-if="loadingCerts" class="text-center py-5"><div class="spinner-border text-success"></div></div>
      <div v-else>
        <!-- Progreso global -->
        <div v-if="myProgress" class="card mb-4 shadow-sm border-0 bg-light">
          <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div class="progress flex-fill" style="height:12px;min-width:150px;">
                <div class="progress-bar bg-success" :style="`width:${myProgress.completion_pct}%`"></div>
              </div>
              <span class="fw-bold">{{ myProgress.certified_count }} / {{ myProgress.total_courses }} cursos</span>
              <span class="text-muted small">({{ myProgress.completion_pct }}% completado)</span>
            </div>
          </div>
        </div>
        <div class="row g-3">
          <div v-for="cert in myCertifications" :key="cert.id" class="col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm border-success">
              <div class="card-body text-center py-3">
                <div class="display-6 mb-2">🏅</div>
                <div class="fw-semibold">{{ cert.course?.title }}</div>
                <div class="small text-muted mt-1">Certificado el {{ fmtDate(cert.certified_at) }}</div>
                <div v-if="cert.exam_attempt" class="small text-success mt-1">
                  Examen: {{ cert.exam_attempt.score }}%
                </div>
                <span class="badge mt-2" :class="cert.status === 'active' ? 'bg-success' : 'bg-secondary'">
                  {{ cert.status === 'active' ? 'Activo' : 'Revocado' }}
                </span>
              </div>
            </div>
          </div>
          <div v-if="!myCertifications?.length" class="col-12 text-center text-muted py-4">
            <i class="fa fa-graduation-cap fa-2x mb-2 d-block text-muted"></i>
            Sin certificaciones aún. Completa cursos del catálogo.
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB ADMIN ── -->
    <div v-if="tab === 'admin' && canManage">
      <div class="d-flex justify-content-end mb-3">
        <button @click="openNewCourse" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Nuevo curso
        </button>
      </div>

      <div class="accordion" id="coursesAccordion">
        <div v-for="c in courses" :key="c.id" class="accordion-item mb-2 shadow-sm">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed py-2" type="button"
                    data-bs-toggle="collapse" :data-bs-target="`#acc-${c.id}`">
              <span class="fw-semibold me-2">{{ c.title }}</span>
              <span class="badge bg-light text-muted border small me-2">{{ c.department ?? 'sin depto' }}</span>
              <span class="badge" :class="c.active ? 'bg-success' : 'bg-secondary'">{{ c.active ? 'Activo' : 'Inactivo' }}</span>
            </button>
          </h2>
          <div :id="`acc-${c.id}`" class="accordion-collapse collapse" data-bs-parent="#coursesAccordion">
            <div class="accordion-body py-2">
              <div class="d-flex gap-2 mb-3 flex-wrap">
                <button @click="openEditCourse(c)" class="btn btn-xs btn-outline-primary"><i class="fa fa-pen me-1"></i>Editar curso</button>
                <button @click="openAddMaterial(c.id)" class="btn btn-xs btn-outline-secondary"><i class="fa fa-plus me-1"></i>Material</button>
                <button @click="openAddExam(c.id)" class="btn btn-xs btn-outline-info"><i class="fa fa-file-alt me-1"></i>Examen</button>
                <button @click="viewColCerts(c.id)" class="btn btn-xs btn-outline-success"><i class="fa fa-medal me-1"></i>Certificaciones</button>
              </div>

              <!-- Materials list -->
              <div v-if="adminMaterials[c.id]?.length" class="mb-2">
                <div class="small text-muted mb-1">Materiales ({{ adminMaterials[c.id].length }}):</div>
                <ul class="list-group list-group-flush">
                  <li v-for="m in adminMaterials[c.id]" :key="m.id" class="list-group-item py-1 d-flex justify-content-between align-items-center small">
                    <span><i class="fa me-1" :class="matIcon(m.type)"></i>{{ m.title ?? typeLabelMat(m.type) }}</span>
                    <button @click="deleteMaterial(m.id, c.id)" class="btn btn-xs btn-outline-danger"><i class="fa fa-trash"></i></button>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cert viewer por colaborador -->
      <div v-if="adminCertView.show" class="card mt-4 shadow-sm">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
          <strong class="small">Certificaciones — {{ adminCertView.courseTitle }}</strong>
          <button @click="adminCertView.show=false" class="btn btn-xs btn-outline-secondary">✕</button>
        </div>
        <div class="card-body p-0">
          <div v-if="!adminCertView.certs?.length" class="text-center text-muted py-3 small">Sin certificaciones para este curso.</div>
          <table v-else class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Técnico</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <tr v-for="cert in adminCertView.certs" :key="cert.id">
                <td class="small fw-semibold">{{ cert.colaborador?.user?.name }}</td>
                <td class="small">{{ fmtDate(cert.certified_at) }}</td>
                <td><span class="badge" :class="cert.status==='active'?'bg-success':'bg-secondary'">{{ cert.status==='active'?'Activo':'Revocado' }}</span></td>
                <td>
                  <button v-if="cert.status==='active'" @click="revokeCart(cert)" class="btn btn-xs btn-outline-danger">Revocar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── MODAL CURSO ── -->
    <div v-if="courseModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ courseModal.id ? 'Editar curso' : 'Nuevo curso' }}</h5>
            <button @click="courseModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Título <span class="text-danger">*</span></label>
                <input v-model="courseModal.title" type="text" class="form-control"></div>
              <div class="col-12"><label class="form-label">Descripción</label>
                <textarea v-model="courseModal.description" class="form-control form-control-sm" rows="2"></textarea></div>
              <div class="col-md-6"><label class="form-label">Departamento</label>
                <input v-model="courseModal.department" type="text" class="form-control form-control-sm" placeholder="técnicos, general, admin…"></div>
              <div class="col-md-3"><label class="form-label">Orden</label>
                <input v-model.number="courseModal.order" type="number" min="0" class="form-control form-control-sm"></div>
              <div class="col-md-3 d-flex align-items-end gap-2">
                <input v-model="courseModal.active" type="checkbox" class="form-check-input" id="ca">
                <label for="ca" class="form-check-label small">Activo</label>
              </div>
              <div v-if="courseModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ courseModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="courseModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveCourse" class="btn btn-primary" :disabled="courseModal.saving">
              <span v-if="courseModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL MATERIAL ── -->
    <div v-if="matModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Agregar material</h5>
            <button @click="matModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-5"><label class="form-label">Tipo</label>
                <select v-model="matModal.type" class="form-select form-select-sm">
                  <option value="text">Texto</option>
                  <option value="video">Video</option>
                  <option value="reference">Referencia (norma/penalización)</option>
                </select>
              </div>
              <div class="col-md-5"><label class="form-label">Título</label>
                <input v-model="matModal.title" type="text" class="form-control form-control-sm"></div>
              <div class="col-md-2"><label class="form-label">Orden</label>
                <input v-model.number="matModal.order" type="number" min="0" class="form-control form-control-sm"></div>

              <div v-if="matModal.type === 'text'" class="col-12">
                <label class="form-label">Contenido (HTML/texto)</label>
                <textarea v-model="matModal.content" class="form-control" rows="6"></textarea>
              </div>
              <div v-if="matModal.type === 'video'" class="col-12">
                <label class="form-label">URL del video (YouTube/Vimeo)</label>
                <input v-model="matModal.video_url" type="url" class="form-control form-control-sm" placeholder="https://youtube.com/watch?v=…">
              </div>
              <template v-if="matModal.type === 'reference'">
                <div class="col-md-6"><label class="form-label">Estándar de construcción</label>
                  <select v-model="matModal.reference_standard_id" class="form-select form-select-sm">
                    <option :value="null">— Ninguno —</option>
                    <option v-for="s in allStandards" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label">Tipo de penalización</label>
                  <select v-model="matModal.reference_penalty_type_id" class="form-select form-select-sm">
                    <option :value="null">— Ninguno —</option>
                    <option v-for="p in allPenaltyTypes" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
              </template>
              <div v-if="matModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ matModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="matModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveMaterial" class="btn btn-primary" :disabled="matModal.saving">
              <span v-if="matModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Agregar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL EXAMEN + PREGUNTAS ── -->
    <div v-if="examModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ examModal.examId ? 'Examen creado — agregar preguntas' : 'Crear examen' }}</h5>
            <button @click="examModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <!-- Paso 1: crear examen -->
            <div v-if="!examModal.examId" class="row g-3 mb-3">
              <div class="col-md-6"><label class="form-label">Título del examen</label>
                <input v-model="examModal.title" type="text" class="form-control form-control-sm"></div>
              <div class="col-md-3"><label class="form-label">Puntaje mínimo (%)</label>
                <input v-model.number="examModal.passing_score" type="number" min="1" max="100" class="form-control form-control-sm"></div>
              <div class="col-md-3 d-flex align-items-end">
                <button @click="createExam" class="btn btn-primary btn-sm w-100" :disabled="examModal.saving">Crear examen</button>
              </div>
            </div>

            <!-- Paso 2: agregar preguntas -->
            <div v-if="examModal.examId">
              <div class="alert alert-success py-1 small mb-3">Examen creado. Agrega preguntas:</div>
              <div v-for="(q, qi) in examModal.questions" :key="qi" class="card mb-3 shadow-sm">
                <div class="card-body py-2">
                  <div class="row g-2">
                    <div class="col-12"><input v-model="q.question" type="text" class="form-control form-control-sm" placeholder="Pregunta…"></div>
                    <div class="col-md-4">
                      <select v-model="q.type" class="form-select form-select-sm">
                        <option value="single">Opción única</option>
                        <option value="multiple">Múltiple</option>
                        <option value="true_false">Verdadero/Falso</option>
                      </select>
                    </div>
                    <div class="col-md-2"><input v-model.number="q.points" type="number" min="1" class="form-control form-control-sm" placeholder="pts"></div>
                    <div class="col-12">
                      <div v-for="(opt, oi) in q.options" :key="oi" class="d-flex gap-2 mb-1 align-items-center">
                        <input :type="q.type === 'multiple' ? 'checkbox' : 'radio'"
                               :name="`qr${qi}`" class="form-check-input"
                               :checked="q.correct_answer.includes(oi)"
                               @change="toggleCorrect(q, oi)">
                        <input v-model="q.options[oi]" type="text" class="form-control form-control-sm" placeholder="Opción…">
                        <button @click="q.options.splice(oi,1)" class="btn btn-xs btn-outline-danger">✕</button>
                      </div>
                      <button @click="q.options.push('')" class="btn btn-xs btn-outline-secondary mt-1">+ Opción</button>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button @click="saveQuestion(qi)" class="btn btn-xs btn-success" :disabled="q.saving">
                        <span v-if="q.saving"><span class="spinner-border spinner-border-sm"></span></span>
                        <span v-else-if="q.saved"><i class="fa fa-check text-white"></i> Guardada</span>
                        <span v-else>Guardar pregunta</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <button @click="examModal.questions.push({question:'',type:'single',options:['',''],correct_answer:[],points:1,saving:false,saved:false})"
                      class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus me-1"></i>Nueva pregunta
              </button>
            </div>
            <div v-if="examModal.error" class="alert alert-danger py-2 small mt-2 mb-0">{{ examModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="examModal.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoAcademia',
  data() {
    return {
      tab: 'catalog',
      canManage:  window.__talento_perms?.includes('talento.academy.manage')   ?? false,
      canEvaluate:window.__talento_perms?.includes('talento.academy.evaluate') ?? true, // assume true for simplicity

      // Catálogo
      courses: [], loadingCourses: true, catFilter: '',
      courseDetail: null, loadingDetail: false,
      myCertMap: {},      // { course_id: bool }
      myBestAttempt: null,

      // Examen
      examScreen: false, loadingExam: false,
      examQuestions: [],  // questions without correct_answer
      examAnswers: {},    // { question_id: [indices] }
      examResult: null, submittingExam: false,

      // Evaluación práctica
      practForm: { colaborador_id: null, result: 'approved', notes: '',
                   evidenceFile: null, saving: false, error: '', successMsg: '' },

      // Mis certificaciones
      myCertifications: [], loadingCerts: false, myProgress: null,

      // Admin
      adminMaterials: {},  // { course_id: [...] }
      adminCertView: { show: false, courseId: null, courseTitle: '', certs: [] },
      colaboradores: [],
      allStandards: [], allPenaltyTypes: [],

      // Modals
      courseModal: { show:false, id:null, title:'', description:'', department:'', order:0, active:true, saving:false, error:'' },
      matModal: { show:false, courseId:null, type:'text', title:'', content:'', video_url:'',
                  reference_standard_id:null, reference_penalty_type_id:null, order:0, saving:false, error:'' },
      examModal: { show:false, courseId:null, examId:null, title:'', passing_score:70,
                   questions:[], saving:false, error:'' },
    };
  },
  mounted() {
    this.loadCourses();
    this.loadMyCertMap();
    this.loadColaboradores();
  },
  methods: {
    // ── Data loading ────────────────────────────────────────────────────────
    async loadCourses() {
      this.loadingCourses = true;
      try {
        const params = this.catFilter ? { department: this.catFilter, active_only: 1 } : { active_only: 1 };
        const { data } = await axios.get('/talento/api/courses', { params });
        this.courses = data ?? [];
        if (this.tab === 'admin') await this.loadAllMaterials();
      } finally { this.loadingCourses = false; }
    },
    async loadMyCertMap() {
      try {
        const { data } = await axios.get('/talento/api/my-certifications');
        this.myCertMap = {};
        (data ?? []).forEach(c => { if (c.status === 'active') this.myCertMap[c.course_id] = true; });
      } catch { /* graceful */ }
    },
    async loadMyCerts() {
      this.loadingCerts = true;
      try {
        const [certsRes, progRes] = await Promise.all([
          axios.get('/talento/api/my-certifications'),
          axios.get('/talento/api/my-certifications'), // progress via same certs
        ]);
        this.myCertifications = certsRes.data ?? [];
      } finally { this.loadingCerts = false; }
    },
    async loadCourseDetail(id) {
      this.loadingDetail = true;
      this.courseDetail = null;
      this.examScreen = false;
      this.examResult = null;
      this.myBestAttempt = null;
      try {
        const { data } = await axios.get(`/talento/api/courses/${id}`);
        this.courseDetail = data;
        // Load best attempt if exam exists
        if (data.exams?.[0]) {
          const atRes = await axios.get(`/talento/api/exams/${data.exams[0].id}/my-attempts`);
          const attempts = atRes.data ?? [];
          const passed = attempts.filter(a => a.passed);
          this.myBestAttempt = passed.length
            ? passed.reduce((best, a) => a.score > best.score ? a : best)
            : (attempts.length ? attempts[0] : null);
        }
      } finally { this.loadingDetail = false; }
    },
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 300, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async loadAllMaterials() {
      for (const c of (this.courses ?? [])) {
        const { data } = await axios.get(`/talento/api/courses/${c.id}`);
        this.adminMaterials[c.id] = data?.materials ?? [];
      }
    },
    async loadReferenceData() {
      const [std, pen] = await Promise.all([
        axios.get('/talento/api/standards', { params: { active_only: 1 } }),
        axios.get('/talento/api/penalty-types', { params: { active_only: 1 } }),
      ]);
      this.allStandards    = std.data ?? [];
      this.allPenaltyTypes = pen.data ?? [];
    },

    // ── Exam ────────────────────────────────────────────────────────────────
    async startExam() {
      if (!this.courseDetail?.exams?.[0]) return;
      this.loadingExam = true;
      try {
        const { data } = await axios.get(`/talento/api/exams/${this.courseDetail.exams[0].id}/take`);
        this.examQuestions = data?.questions ?? [];
        this.examAnswers   = {};
        this.examResult    = null;
        this.examScreen    = true;
      } finally { this.loadingExam = false; }
    },
    isSelected(qId, optIndex) {
      return (this.examAnswers[qId] ?? []).includes(optIndex);
    },
    toggleAnswer(q, optIndex) {
      if (!this.examAnswers[q.id]) this.examAnswers[q.id] = [];
      if (q.type === 'multiple') {
        const idx = this.examAnswers[q.id].indexOf(optIndex);
        if (idx >= 0) this.examAnswers[q.id].splice(idx, 1);
        else this.examAnswers[q.id].push(optIndex);
      } else {
        this.examAnswers[q.id] = [optIndex];
      }
    },
    async submitExam() {
      this.submittingExam = true;
      try {
        const { data } = await axios.post(
          `/talento/api/exams/${this.courseDetail.exams[0].id}/submit`,
          { answers: this.examAnswers }
        );
        this.examResult = data;
        if (data.passed) { this.myCertMap[this.courseDetail.id] = true; }
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al enviar examen.');
      } finally { this.submittingExam = false; }
    },

    // ── Practical ───────────────────────────────────────────────────────────
    async submitPractical() {
      if (!this.practForm.colaborador_id || !this.practForm.result) return;
      this.practForm.saving = true;
      this.practForm.error = '';
      this.practForm.successMsg = '';
      try {
        const fd = new FormData();
        fd.append('colaborador_id', this.practForm.colaborador_id);
        fd.append('result', this.practForm.result);
        if (this.practForm.notes) fd.append('notes', this.practForm.notes);
        if (this.practForm.evidenceFile) fd.append('evidence', this.practForm.evidenceFile);
        const { data } = await axios.post(
          `/talento/api/courses/${this.courseDetail.id}/practical`, fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        );
        this.practForm.successMsg = `Evaluación registrada${data.certification ? ' · ¡Certificación otorgada!' : ''}`;
        if (data.certification) this.myCertMap[this.courseDetail.id] = true;
        this.practForm.colaborador_id = null;
      } catch (e) {
        this.practForm.error = e.response?.data?.error ?? 'Error al registrar.';
      } finally { this.practForm.saving = false; }
    },

    // ── Admin ───────────────────────────────────────────────────────────────
    openNewCourse() {
      this.courseModal = { show:true, id:null, title:'', description:'', department:'', order:0, active:true, saving:false, error:'' };
    },
    openEditCourse(c) {
      this.courseModal = { show:true, id:c.id, title:c.title, description:c.description??'',
                           department:c.department??'', order:c.order, active:c.active, saving:false, error:'' };
    },
    async saveCourse() {
      this.courseModal.error = '';
      if (!this.courseModal.title) { this.courseModal.error = 'El título es requerido.'; return; }
      this.courseModal.saving = true;
      try {
        const payload = { title:this.courseModal.title, description:this.courseModal.description||null,
                          department:this.courseModal.department||null, order:this.courseModal.order, active:this.courseModal.active };
        if (this.courseModal.id) await axios.put(`/talento/api/courses/${this.courseModal.id}`, payload);
        else                     await axios.post('/talento/api/courses', payload);
        this.courseModal.show = false;
        this.loadCourses();
      } catch (e) {
        this.courseModal.error = e.response?.data?.message ?? 'Error.';
      } finally { this.courseModal.saving = false; }
    },
    async openAddMaterial(courseId) {
      await this.loadReferenceData();
      this.matModal = { show:true, courseId, type:'text', title:'', content:'', video_url:'',
                        reference_standard_id:null, reference_penalty_type_id:null, order:0, saving:false, error:'' };
    },
    async saveMaterial() {
      this.matModal.error = '';
      this.matModal.saving = true;
      try {
        await axios.post(`/talento/api/courses/${this.matModal.courseId}/materials`, {
          type: this.matModal.type, title: this.matModal.title || null,
          content: this.matModal.content || null, video_url: this.matModal.video_url || null,
          order: this.matModal.order,
          reference_standard_id: this.matModal.reference_standard_id,
          reference_penalty_type_id: this.matModal.reference_penalty_type_id,
        });
        this.matModal.show = false;
        this.loadCourses();
      } catch (e) {
        this.matModal.error = e.response?.data?.message ?? 'Error.';
      } finally { this.matModal.saving = false; }
    },
    async deleteMaterial(matId, courseId) {
      if (!confirm('¿Eliminar este material?')) return;
      await axios.delete(`/talento/api/course-materials/${matId}`);
      this.adminMaterials[courseId] = (this.adminMaterials[courseId] ?? []).filter(m => m.id !== matId);
    },
    openAddExam(courseId) {
      this.examModal = { show:true, courseId, examId:null, title:'', passing_score:70, questions:[], saving:false, error:'' };
    },
    async createExam() {
      if (!this.examModal.title) { this.examModal.error = 'El título es requerido.'; return; }
      this.examModal.saving = true;
      try {
        const { data } = await axios.post(`/talento/api/courses/${this.examModal.courseId}/exams`, {
          title: this.examModal.title, passing_score: this.examModal.passing_score,
        });
        this.examModal.examId = data.id;
        this.examModal.questions = [{ question:'', type:'single', options:['',''], correct_answer:[], points:1, saving:false, saved:false }];
      } catch (e) {
        this.examModal.error = e.response?.data?.message ?? 'Error.';
      } finally { this.examModal.saving = false; }
    },
    toggleCorrect(q, idx) {
      if (q.type === 'multiple') {
        const i = q.correct_answer.indexOf(idx);
        if (i >= 0) q.correct_answer.splice(i, 1); else q.correct_answer.push(idx);
      } else {
        q.correct_answer = [idx];
      }
    },
    async saveQuestion(qi) {
      const q = this.examModal.questions[qi];
      if (!q.question || !q.options.length) return;
      q.saving = true;
      try {
        await axios.post(`/talento/api/exams/${this.examModal.examId}/questions`, {
          question: q.question, type: q.type, options: q.options,
          correct_answer: q.correct_answer, points: q.points, order: qi,
        });
        q.saved = true;
      } catch (e) {
        alert(e.response?.data?.message ?? 'Error al guardar pregunta.');
      } finally { q.saving = false; }
    },
    async viewColCerts(courseId) {
      const course = this.courses.find(c => c.id === courseId);
      const { data } = await axios.get(`/talento/api/colaboradores/0/certifications`); // placeholder
      // Fetch all certs for the course via existing endpoint (we'll filter client-side)
      const allCerts = [];
      for (const col of this.colaboradores.slice(0, 20)) {
        const res = await axios.get(`/talento/api/colaboradores/${col.id}/certifications`);
        (res.data ?? []).filter(c => c.course_id === courseId).forEach(c => {
          c.colaborador = { user: col.user };
          allCerts.push(c);
        });
      }
      this.adminCertView = { show: true, courseId, courseTitle: course?.title ?? '', certs: allCerts };
    },
    async revokeCart(cert) {
      if (!confirm('¿Revocar esta certificación?')) return;
      await axios.post(`/talento/api/certifications/${cert.id}/revoke`);
      cert.status = 'revoked';
    },

    // ── Helpers ─────────────────────────────────────────────────────────────
    matIcon(t)     { return { text:'fa-align-left', video:'fa-play-circle', reference:'fa-link' }[t] ?? 'fa-file'; },
    typeLabelMat(t){ return { text:'Texto', video:'Video', reference:'Referencia' }[t] ?? t; },
    embedUrl(url)  {
      if (!url) return '';
      // YouTube
      const yt = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
      if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
      // Vimeo
      const vm = url.match(/vimeo\.com\/(\d+)/);
      if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
      return url;
    },
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
    },
    fmt2: n => Number(n ?? 0).toLocaleString('es-MX', { minimumFractionDigits:2, maximumFractionDigits:2 }),
  },
};
</script>
