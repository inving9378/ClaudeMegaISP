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
          <!-- #1225: órdenes eliminadas (borrado lógico), solo para quien puede eliminar. -->
          <div v-if="puedeEliminarOrden" class="col-12 col-md-auto d-flex align-items-center">
            <div class="form-check form-switch mb-0">
              <input id="ord_ver_eliminadas" v-model="filters.eliminadas" @change="load()" class="form-check-input" type="checkbox">
              <label class="form-check-label small" for="ord_ver_eliminadas">Ver eliminadas</label>
            </div>
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
                    <span v-if="o.type?.area === 'planta_externa'" class="text-muted"> (referencia)</span>
                  </span>
                  <span v-else-if="o.prospecto?.nombre" class="small">
                    <i class="fa fa-user-clock text-muted me-1" title="Prospecto CRM (aún no es cliente)"></i>{{ o.prospecto.nombre }}
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td>
                  <span class="tc-status is-slate">{{ o.type?.name }}</span>
                  <i v-if="o.is_billable" class="fa fa-dollar-sign text-success ms-1" title="Pagable"></i>
                  <!-- Item #1200: una orden de planta externa se reconoce por su caja/zona, no por un cliente. -->
                  <div v-if="identificadorOrden(o)" class="small text-muted mt-1">{{ identificadorOrden(o) }}</div>
                </td>
                <td class="text-center fw-bold">{{ o.points }}</td>
                <td class="small">{{ fechaAgenda(o.scheduled_at) }}</td>
                <td>
                  <template v-if="o.eliminada">
                    <span class="tc-status is-bad">Eliminada</span>
                    <div class="small text-muted">{{ o.eliminada.motivo }}</div>
                    <div class="small text-muted">{{ o.eliminada.por || 'Alguien' }} · {{ formatDatetime(o.eliminada.cuando) }}</div>
                  </template>
                  <template v-else>
                    <span class="tc-status" :class="statusBadge(o.status)">{{ statusLabel(o.status) }}</span>
                    <span v-if="o.atrasada" class="ord-atrasada" title="Ya pasó su día y no se ha terminado">Atrasada</span>
                  </template>
                </td>
                <td class="text-end">
                  <button @click="viewOrder(o)" class="tc-btn tc-btn-info me-1">Ver</button>
                  <button v-if="puedeCrear && o.atrasada && !o.eliminada" @click="abrirReprogramar(o)" class="tc-btn tc-btn-bad me-1">Reprogramar</button>
                  <!-- David (29-sep): "Validar"/"Iniciar"/"Completar" son
                       acciones de GESTIÓN (backend: talento.work_orders.
                       validate/manage, ninguna la tiene un técnico
                       autoservicio) — antes se mostraban a cualquiera que
                       viera la lista (con solo status como condición) y al
                       hacer clic 403eaban en silencio. El técnico avanza
                       SU PROPIA orden desde la app móvil de campo, no
                       desde aquí. Mismo flag que ya gatea "Nueva orden". -->
                  <button v-if="puedeCrear && o.status === 'completed' && !o.eliminada" @click="openValidate(o)" class="tc-btn tc-btn-ok me-1">Validar</button>
                  <button v-if="puedeCrear && canAdvance(o.status) && !o.eliminada" @click="advanceStatus(o)" class="tc-btn tc-btn-primary">
                    {{ nextStatusLabel(o.status) }}
                  </button>
                  <!-- #1225: Editar y Eliminar solo mientras está Pendiente (Atrasada incluida), en un menú para no
                       apilar más botones. El servidor vuelve a revisar el estado y el permiso. -->
                  <button v-if="tieneAccionesPendiente(o)" type="button" class="tc-btn tc-btn-info ord-mas ms-1"
                          title="Más acciones" aria-label="Más acciones" aria-haspopup="menu"
                          :aria-expanded="menuOrden?.id === o.id ? 'true' : 'false'" @click.stop="abrirMenu(o, $event)">
                    <i class="fa fa-ellipsis-v"></i>
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
            <h5 class="modal-title">{{ createModal.editId ? `Editar orden #${createModal.editId}` : 'Nueva Orden de Trabajo' }}</h5>
            <button @click="createModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <!-- Dentro de la ficha de un colaborador (colaboradorId presente): fija,
                     sin buscador — un supervisor sin acceso al roster completo no puede
                     ni debe buscar A OTROS, solo crear para éste. -->
                <div v-if="colaboradorId && !createModal.editId" class="form-control-plaintext small">
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
                <select v-model="createModal.type_id" class="form-select tc-select" id="no_tipo" ref="tipoSelect" :disabled="tipoFijo">
                  <option :value="null">— Seleccionar —</option>
                  <!-- Item #1200: con tipos de planta externa, dos bloques; sin ellos, la lista de siempre.
                       Al editar (#1225) solo salen los tipos a los que puede cambiar la orden. -->
                  <template v-if="tiposPlantaExterna.length && !createModal.editId">
                    <optgroup label="Cliente">
                      <option v-for="t in tiposCliente" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </optgroup>
                    <optgroup label="Planta externa">
                      <option v-for="t in tiposPlantaExterna" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </optgroup>
                  </template>
                  <option v-else v-for="t in (createModal.editId ? tiposEdicion : activeTypes)" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div v-if="selectedType" class="mt-1 small text-muted">
                  {{ selectedType.points }} pts
                  · {{ selectedType.is_billable ? '💲 Pagable' : 'No pagable' }}
                  {{ selectedType.requires_validation ? '· Requiere validación' : '' }}
                </div>
                <div v-if="tipoFijo" class="form-text">Las órdenes de planta externa conservan el tipo con que se crearon.</div>
                <!-- #1225: al cambiar el tipo, los puntos se recalculan con los del tipo nuevo. -->
                <div v-if="createModal.editId && selectedType && createModal.type_id !== createModal.tipoOriginal"
                     class="mt-1 small fw-semibold ord-pts-cambio">
                  PTS: {{ createModal.puntosActuales }} → {{ selectedType.points }}
                </div>
              </div>
              <!-- Item #1200: planta externa no lleva prospecto; el cliente es solo la referencia de la caja. -->
              <div v-if="esPlantaExterna" class="col-md-6 position-relative">
                <label class="form-label" for="no_cliente_ref">Cliente de referencia (opcional)</label>
                <div class="input-group">
                  <input id="no_cliente_ref" v-model="clienteRefSearch" @input="debounceClienteRef" @focus="clienteRefListOpen = true"
                         @blur="closeClienteRefDelayed" type="text" class="form-control" placeholder="Buscar por nombre, número o teléfono…"
                         :disabled="!!createModal.cliente_ref">
                  <button v-if="createModal.cliente_ref" @click="clearClienteRef" type="button"
                          class="btn btn-outline-secondary" title="Quitar cliente de referencia">
                    <i class="fa fa-times"></i>
                  </button>
                </div>
                <ul v-if="clienteRefListOpen && clienteRefSuggestions.length"
                    class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:220px;overflow-y:auto;width:100%">
                  <li v-for="c in clienteRefSuggestions" :key="c.id" @mousedown.prevent="selectClienteRef(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">
                    {{ c.nombre }} <span class="text-muted">· #{{ c.id }}<template v-if="c.direccion"> · {{ c.direccion }}</template></span>
                  </li>
                </ul>
                <div v-if="createModal.cliente_ref" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.cliente_ref.nombre }} · #{{ createModal.cliente_ref.id }}
                </div>
                <div class="form-text">Solo si la orden es por una caja reportada por un cliente.</div>
              </div>
              <!-- Cliente o prospecto. Al crear: solo prospecto, como siempre. Al editar (#1228): un solo buscador
                   de clientes y prospectos por nombre o teléfono (el de prospectos que ya existía + clientes con el
                   motor del listado de Clientes). Nunca los dos a la vez. -->
              <div v-else class="col-md-6 position-relative">
                <label class="form-label">{{ createModal.editId ? 'Cliente o prospecto' : 'Prospecto (CRM)' }}</label>
                <div class="input-group">
                  <input v-model="prospSearch" @input="debounceProspSearch" @focus="openProspList"
                         @blur="closeProspListDelayed"
                         type="text" class="form-control"
                         :placeholder="createModal.editId ? 'Buscar cliente o prospecto por nombre o teléfono…' : 'Buscar prospecto…'"
                         :disabled="vinculoElegido || bloqueoCambioCliente">
                  <button v-if="vinculoElegido && !bloqueoCambioCliente" @click="quitarVinculo" type="button"
                          class="btn btn-outline-secondary" :title="createModal.client_id ? 'Quitar cliente' : 'Quitar prospecto'">
                    <i class="fa fa-times"></i>
                  </button>
                </div>
                <div v-if="prospListOpen && haySugerencias"
                     class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:260px;overflow-y:auto;width:100%">
                  <template v-if="createModal.editId && clientSuggestions.length">
                    <li class="list-group-item prosp-group-header small fw-semibold py-1">Clientes</li>
                    <li v-for="c in clientSuggestions" :key="'c'+c.id" @click="selectCliente(c)"
                        class="list-group-item list-group-item-action small cursor-pointer">
                      {{ c.nombre }}
                      <span class="text-muted">· #{{ c.id }}<template v-if="c.telefono"> · {{ c.telefono }}</template><template v-if="c.colonia"> · {{ c.colonia }}</template></span>
                    </li>
                  </template>
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
                <div v-if="createModal.client_id" class="mt-1 small text-success">
                  <i class="fa fa-user me-1"></i>Cliente #{{ createModal.client_id }} · {{ createModal.client_name }}
                </div>
                <div v-if="createModal.crm_lead_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.crm_lead_name }}
                </div>
                <div v-if="bloqueoCambioCliente" class="mt-1 small text-muted">
                  <i class="fa fa-lock me-1"></i>No se puede cambiar: la orden trae {{ createModal.vinculos.join(' y ') }}
                  del cliente actual. Si el cliente es otro, elimínala y créala de nuevo.
                </div>
                <div v-if="createModal.client_id && createModal.crm_lead_id" class="mt-1 small text-danger">
                  Una orden es para un cliente o un prospecto, no ambos.
                </div>
              </div>
              <!-- #1228: qué cambiará al guardar si se cambió el cliente o prospecto. -->
              <div v-if="cambioDeCliente" class="col-12">
                <div class="alert alert-info py-2 small mb-0">
                  <div class="fw-semibold mb-1"><i class="fa fa-info-circle me-1"></i>Al guardar cambiará:</div>
                  <div>{{ cambioDeCliente.de }} → <strong>{{ cambioDeCliente.a }}</strong></div>
                  <div v-if="cambioDeCliente.conDireccion">
                    Dirección que verá el técnico: {{ cambioDeCliente.dirDe || 'sin dirección' }} →
                    <strong>{{ cambioDeCliente.dirA || 'sin dirección' }}</strong>
                  </div>
                  <div class="text-muted mt-1">El cliente o prospecto anterior no se modifica.</div>
                </div>
              </div>
              <!-- Item #1200: campos de la plantilla del tipo de planta externa (solo los de creación). -->
              <div v-if="esPlantaExterna" class="col-12">
                <div class="border rounded p-3 no-datos-orden">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h6 class="mb-0 fw-semibold">Datos de la orden</h6>
                    <select v-if="plantillas.length > 1" v-model="createModal.template_id" class="form-select form-select-sm w-auto"
                            aria-label="Plantilla" id="no_plantilla">
                      <option :value="null">— Plantilla —</option>
                      <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <div v-if="plantillasCargando" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Cargando plantilla…</div>
                  <div v-else-if="!plantillas.length" class="alert alert-warning small mb-0">
                    Este tipo de orden todavía no tiene una plantilla activa.
                  </div>
                  <div v-else-if="!plantillaSel" class="small text-muted">Elige la plantilla de la orden.</div>
                  <template v-else>
                    <div v-if="plantillaSel.description" class="small text-muted mb-2" style="white-space:pre-line">{{ plantillaSel.description }}</div>
                    <CamposPlantilla v-model="createModal.datos" :campos="camposCreacion" :errores="erroresCampos" id-prefix="no" />
                    <div v-if="camposCierre.length" class="small text-muted mt-3">
                      <i class="fa fa-info-circle me-1"></i>El técnico capturará al cerrar: {{ textoCamposCierre }}.
                    </div>
                    <div v-if="plantillaSel.checklist?.length" class="mt-3">
                      <div class="small fw-semibold mb-1">Materiales</div>
                      <ul class="small mb-0 ps-3">
                        <li v-for="(m, i) in plantillaSel.checklist" :key="i">{{ textoMaterial(m) }}</li>
                      </ul>
                    </div>
                  </template>
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
                <!-- #1227: el choque de agenda se marca con su propio icono; se quita al cambiar fecha o persona. -->
                <div ref="errorOrden" class="alert alert-danger py-2 small mb-0" role="alert">
                  <i class="fa me-1" :class="createModal.choque ? 'fa-calendar-times' : 'fa-exclamation-circle'"></i>{{ createModal.error }}
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="createModal.show=false" class="btn btn-secondary" :disabled="createModal.saving">Cancelar</button>
            <button @click="saveOrder" class="btn btn-primary" :disabled="createModal.saving">
              <span v-if="createModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>{{ createModal.editId ? 'Guardar cambios' : 'Crear orden' }}</span>
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
                <strong>{{ detail.order.type?.area === 'planta_externa' ? 'Cliente de referencia:' : 'Cliente:' }}</strong>
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
              <!-- Item #1200: en órdenes con plantilla, la descripción es el texto armado que ve el técnico. -->
              <div class="col-12" v-if="detail.order.notes && !detail.order.datos"><strong>Notas:</strong> {{ detail.order.notes }}</div>
              <div class="col-12" v-if="detail.order.eliminada">
                <div class="alert alert-secondary py-2 small mb-0">
                  <i class="fa fa-trash-alt me-1"></i>Eliminada el {{ formatDatetime(detail.order.eliminada.cuando) }}
                  por {{ detail.order.eliminada.por || 'alguien' }}. Motivo: {{ detail.order.eliminada.motivo }}
                </div>
              </div>
              <div class="col-12" v-if="detail.order.atrasada && !detail.order.eliminada">
                <div class="alert alert-danger py-2 small mb-0">
                  <i class="fa fa-exclamation-circle me-1"></i>Atrasada: ya pasó su día y no se ha terminado. Hay que terminarla o reprogramarla con un motivo.
                </div>
              </div>
            </div>
            <div v-if="detail.order.datos" class="mt-3 detalle-datos-orden">
              <h6 class="fw-semibold mb-2">
                Datos de la orden
                <small v-if="detail.order.datos.plantilla" class="text-muted fw-normal">· {{ detail.order.datos.plantilla }}</small>
              </h6>
              <CamposPlantilla solo-lectura id-prefix="det" :campos="camposDetalle('creacion')" :model-value="detail.order.datos.valores || {}" />
              <template v-if="camposDetalle('cierre').length">
                <div class="small fw-semibold mt-3 mb-2">Se captura al cerrar</div>
                <CamposPlantilla solo-lectura id-prefix="detc" texto-vacio="Pendiente" :campos="camposDetalle('cierre')" :model-value="detail.order.datos.valores || {}" />
              </template>
              <div v-if="detail.order.checklist?.length" class="mt-3">
                <div class="small fw-semibold mb-1">Materiales</div>
                <ul class="small mb-0 ps-3">
                  <li v-for="m in detail.order.checklist" :key="m.id">
                    <i v-if="m.checked" class="fa fa-check text-success me-1" title="Marcado por el técnico"></i>{{ textoMaterial(m) }}
                  </li>
                </ul>
              </div>
              <details v-if="detail.order.notes" class="mt-3 small">
                <summary class="text-muted">Texto que ve el técnico en la app y el portal</summary>
                <div class="mt-2 p-2 border rounded" style="white-space:pre-line">{{ detail.order.notes }}</div>
              </details>
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
            <button v-if="puedeCrear && detail.order && detail.order._source === 'task' && !detail.order.eliminada && ['pending', 'in_progress'].includes(detail.order.status)"
                    @click="abrirReprogramar(detail.order)" class="btn btn-outline-danger">Reprogramar</button>
            <!-- #1225: también desde la vista Agenda, que abre este mismo detalle. -->
            <button v-if="esEditable(detail.order) && puedeEditarOrden" @click="abrirEditar(detail.order)" class="btn btn-outline-primary">
              <i class="fa fa-pencil-alt me-1"></i>Editar
            </button>
            <button v-if="esEditable(detail.order) && puedeEliminarOrden" @click="abrirEliminar(detail.order)" class="btn btn-danger">
              <i class="fa fa-trash-alt me-1"></i>Eliminar
            </button>
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

    <!-- #1225: eliminar una orden pendiente, con motivo obligatorio. -->
    <div v-if="eliminarModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999"
         role="dialog" aria-modal="true" aria-labelledby="ord_eliminar_titulo">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="ord_eliminar_titulo" class="modal-title">Eliminar orden #{{ eliminarModal.order?.id }}</h5>
            <button @click="cerrarEliminar" type="button" class="btn-close" aria-label="Cerrar" :disabled="eliminarModal.saving"></button>
          </div>
          <div class="modal-body">
            <p class="mb-2">
              <strong>{{ eliminarModal.order?.type?.name }}</strong>
              · {{ fullName(eliminarModal.order?.colaborador?.user) || 'Sin asignar' }}
              · {{ fechaAgenda(eliminarModal.order?.scheduled_at) }}
            </p>
            <div v-if="ligaduras(eliminarModal.order)" class="alert alert-warning py-2 small">
              <i class="fa fa-exclamation-triangle me-1"></i>Esta orden está ligada a {{ ligaduras(eliminarModal.order) }}.
              <strong>Eliminar la orden no cancela el prospecto ni la venta.</strong>
            </div>
            <fieldset>
              <legend class="form-label fs-6 mb-1">Motivo <span class="text-danger">*</span></legend>
              <div v-for="m in motivosEliminacion" :key="m.valor" class="form-check">
                <input :id="`ord_motivo_${m.valor}`" v-model="eliminarModal.motivo" :value="m.valor" type="radio"
                       name="ord_motivo" class="form-check-input">
                <label :for="`ord_motivo_${m.valor}`" class="form-check-label">{{ m.texto }}</label>
              </div>
            </fieldset>
            <div v-if="eliminarModal.motivo === 'otro'" class="mt-2">
              <label for="ord_motivo_detalle" class="form-label small mb-1">¿Por qué? <span class="text-danger">*</span></label>
              <textarea id="ord_motivo_detalle" v-model="eliminarModal.detalle" maxlength="400" rows="2" class="form-control"></textarea>
            </div>
            <p class="text-muted small mt-2 mb-0">
              Sale de la agenda y del «Mi día» de la persona, y se le avisa. Queda registrada con su motivo en «Ver eliminadas».
            </p>
            <div v-if="eliminarModal.error" class="alert alert-danger py-2 small mt-2 mb-0">{{ eliminarModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="cerrarEliminar" class="btn btn-secondary" :disabled="eliminarModal.saving">Cancelar</button>
            <button @click="confirmarEliminar" class="btn btn-danger" :disabled="eliminarModal.saving || !motivoEliminacionListo">
              <span v-if="eliminarModal.saving" class="spinner-border spinner-border-sm me-1"></span>Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- #1225: menú «⋮» de la fila. Fijo a la pantalla para que la tabla con scroll no lo recorte;
         el fondo transparente lo cierra al hacer clic o desplazarse fuera (sin escuchas globales). -->
    <template v-if="menuOrden">
      <div class="ord-menu-fondo" @click="cerrarMenu" @wheel="cerrarMenu" @touchmove="cerrarMenu"></div>
      <ul class="ord-menu" role="menu" :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px' }" @keydown.esc="cerrarMenu">
        <li v-if="puedeEditarOrden" role="none">
          <button type="button" role="menuitem" @click="abrirEditar(menuOrden)"><i class="fa fa-pencil-alt me-2"></i>Editar</button>
        </li>
        <li v-if="puedeEliminarOrden" role="none">
          <button type="button" role="menuitem" class="ord-menu-peligro" @click="abrirEliminar(menuOrden)"><i class="fa fa-trash-alt me-2"></i>Eliminar</button>
        </li>
      </ul>
    </template>

  </div>
</template>

<script>
import Swal from "sweetalert2";
import { darkMode } from "../../../hook/appConfig.js";
import Permission from "../../../helpers/Permission.js";
import { allViewHasPermission } from "../../../helpers/Request.js";
import FechaHoraOrden from "./FechaHoraOrden.vue";
import CargaCalendario from "./CargaCalendario.vue";
import ReprogramarOrden from "./ReprogramarOrden.vue";
import TalentoAgenda from "./TalentoAgenda.vue";
import { fechaAgenda } from "./fechaAgenda.js";
import CamposPlantilla from "./campos/CamposPlantilla.vue";
import {
  camposDe, validarCampos, limpiarValores, erroresDeServidor, textoMaterial, esVacio,
} from "./campos/camposPlantilla.js";

const VISTA_KEY = "talento.ordenes.vista";

// #1225: mismas claves que OrdenPendienteService::MOTIVOS_ELIMINACION.
const MOTIVOS_ELIMINACION = [
  { valor: "creada_por_error", texto: "Creada por error" },
  { valor: "duplicada", texto: "Duplicada" },
  { valor: "cliente_cancelo", texto: "Cliente canceló" },
  { valor: "otro", texto: "Otro" },
];

export default {
  name: 'TalentoOrdenes',
  components: { FechaHoraOrden, CargaCalendario, ReprogramarOrden, TalentoAgenda, CamposPlantilla },
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
      filters: { search: '', prospecto: '', status: '', type_id: '', from: '', to: '', eliminadas: false },
      searchTimeout: null,
      createModal: { show: false, editId: null, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '', choque: null, crm_lead_id: null, crm_lead_name: '', client_id: null, client_name: '', client_dir: null, vinculos: [], original: null, tipoOriginal: null, puntosActuales: null, desdeDetalle: false, template_id: null, datos: {}, cliente_ref: null, conPlantilla: false },
      // #1225: editar / eliminar una orden pendiente.
      eliminarModal: { show: false, order: null, motivo: '', detalle: '', saving: false, error: '' },
      motivosEliminacion: MOTIVOS_ELIMINACION,
      menuOrden: null,
      menuPos: { top: 0, left: 0 },
      // Item #1200: plantillas del tipo de planta externa elegido y sus errores.
      plantillas: [],
      plantillasCargando: false,
      plantillasToken: 0,
      intentoGuardar: false,
      erroresServidor: {},
      revirtiendoTipo: false,
      clienteRefSearch: '',
      clienteRefSuggestions: [],
      clienteRefListOpen: false,
      clienteRefTimeout: null,
      clienteRefCloseTimeout: null,
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
      // #1228: clientes del buscador del formulario de edición.
      clientSuggestions: [],
    };
  },
  watch: {
    // #1227: el aviso de choque deja de ser cierto en cuanto se cambia la fecha o la persona.
    'createModal.scheduled_at'() { this.limpiarChoque(); },
    'createModal.colaborador_id'() { this.limpiarChoque(); },
  },
  computed: {
    // #1228: ya hay cliente o prospecto elegido (el buscador se bloquea hasta quitarlo).
    vinculoElegido() { return !!(this.createModal.crm_lead_id || this.createModal.client_id); },
    // La orden trae ticket, ONU o servicio del cliente actual: no se le cambia el cliente (el servidor también lo rechaza).
    bloqueoCambioCliente() { return !!(this.createModal.editId && this.createModal.vinculos?.length); },
    haySugerencias() {
      return (this.createModal.editId && this.clientSuggestions.length > 0)
        || this.prospSuggestions.propios.length > 0 || this.prospSuggestions.generales.length > 0;
    },
    // Qué cambiará al guardar: cliente/prospecto de antes → después y la dirección que verá el técnico.
    cambioDeCliente() {
      const m = this.createModal, o = m.original;
      if (!m.editId || !o) return null;
      if ((o.client_id || null) === (m.client_id || null) && (o.crm_lead_id || null) === (m.crm_lead_id || null)) return null;
      const texto = (cid, cnom, lid, lnom) => cid ? `Cliente #${cid} · ${cnom}` : (lid ? `Prospecto · ${lnom}` : 'Sin cliente ni prospecto');
      return {
        de: texto(o.client_id, o.client_name, o.crm_lead_id, o.crm_lead_name),
        a:  texto(m.client_id, m.client_name, m.crm_lead_id, m.crm_lead_name),
        conDireccion: !!(o.client_id || m.client_id),
        dirDe: o.client_id ? o.client_dir : null,
        dirA:  m.client_id ? m.client_dir : null,
      };
    },
    activeTypes() { return this.types.filter(t => t.active); },
    // Al editar, el tipo que ya tiene la orden sale en la lista aunque hoy esté inactivo.
    tiposFormulario() {
      return this.types.filter(t => t.active || (this.createModal.editId && t.id === this.createModal.tipoOriginal));
    },
    // Item #1200 (regla a): una orden de planta externa o con plantilla conserva su tipo, y una de
    // cliente no pasa a planta externa al editarla.
    tipoFijo() {
      if (!this.createModal.editId) return false;
      const original = this.types.find(t => t.id === this.createModal.tipoOriginal);
      return this.createModal.conPlantilla || original?.area === 'planta_externa';
    },
    tiposEdicion() {
      const original = this.types.find(t => t.id === this.createModal.tipoOriginal);
      if (original?.area === 'planta_externa' || this.createModal.conPlantilla) return original ? [original] : [];
      return this.tiposFormulario.filter(t => t.area !== 'planta_externa');
    },
    puedeEditarOrden() {
      return new Permission(this.permisosOrdenes).canDo('talento.ordenes.editar');
    },
    puedeEliminarOrden() {
      return new Permission(this.permisosOrdenes).canDo('talento.ordenes.eliminar');
    },
    motivoEliminacionListo() {
      const m = this.eliminarModal;
      return !!m.motivo && (m.motivo !== 'otro' || m.detalle.trim() !== '');
    },
    // Item #1200: tipos de cliente (los de siempre) y de planta externa.
    tiposCliente() { return this.activeTypes.filter(t => (t.area || 'cliente') !== 'planta_externa'); },
    tiposPlantaExterna() { return this.activeTypes.filter(t => t.area === 'planta_externa'); },
    esPlantaExterna() { return this.selectedType?.area === 'planta_externa'; },
    plantillaSel() { return this.plantillas.find(p => p.id === this.createModal.template_id) || null; },
    camposCreacion() { return camposDe(this.plantillaSel?.fields, 'creacion'); },
    camposCierre() { return camposDe(this.plantillaSel?.fields, 'cierre'); },
    textoCamposCierre() {
      return this.camposCierre.map(c => c.label + (c.unit ? ` (${c.unit})` : '')).join(', ');
    },
    // Errores junto a cada campo: los del navegador después del primer intento de guardar, y los
    // del servidor hasta que se cambie algún dato.
    erroresCampos() {
      const locales = this.intentoGuardar ? validarCampos(this.camposCreacion, this.createModal.datos) : {};
      return { ...this.erroresServidor, ...locales };
    },
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
  watch: {
    // Item #1200: al cambiar de tipo se limpian plantilla y datos; si se perdería algo ya capturado,
    // se pide confirmación. Entre tipos de cliente no se pierde nada: se comporta como siempre.
    'createModal.type_id'(nuevo, viejo) {
      if (this.revirtiendoTipo) { this.revirtiendoTipo = false; return; }
      // #1225: al editar, la plantilla y los datos vienen de la orden (el tipo de planta externa no cambia).
      if (!this.createModal.show || this.createModal.editId) return;
      const tipoViejo = this.types.find(t => t.id === viejo);
      const tipoNuevo = this.types.find(t => t.id === nuevo);
      const seBorra = (tipoViejo?.area === 'planta_externa' && (!esVacio(limpiarValores(this.camposCreacion, this.createModal.datos)) || this.createModal.cliente_ref))
        || (tipoNuevo?.area === 'planta_externa' && this.createModal.crm_lead_id);
      if (viejo != null && seBorra && !window.confirm('Al cambiar el tipo de orden se borran los datos de la orden que ya capturaste. ¿Continuar?')) {
        this.revirtiendoTipo = true;
        this.createModal.type_id = viejo;
        // El valor vuelve al anterior en el mismo ciclo, así que Vue no repinta el <select> y
        // seguiría mostrando el tipo que se canceló: se restaura a mano.
        this.$nextTick(() => {
          const sel = this.$refs.tipoSelect;
          if (sel) sel.selectedIndex = Array.from(sel.options).findIndex(o => o._value === viejo);
        });
        return;
      }
      this.prepararTipo();
    },
    'createModal.template_id'() {
      if (this.createModal.editId) return;
      this.createModal.datos = {};
      this.erroresServidor = {};
      this.intentoGuardar = false;
    },
    'createModal.datos'() { this.erroresServidor = {}; },
  },
  async mounted() {
    this.loadTypes();
    this.load();
    this.permisosOrdenes = await allViewHasPermission();
  },
  methods: {
    // El aviso va al final de un formulario largo (debajo del calendario de carga): llevarlo a la vista.
    verError() {
      this.$nextTick(() => this.$refs.errorOrden?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' }));
    },
    limpiarChoque() {
      if (this.createModal.choque) { this.createModal.choque = null; this.createModal.error = ''; }
    },
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const params = { ...this.filters, page };
        params.eliminadas = this.filters.eliminadas && this.puedeEliminarOrden ? 1 : undefined;
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
        type_id: null, scheduled_at: '', notes: '', saving: false, error: '', choque: null, crm_lead_id: null, crm_lead_name: '',
        editId: null, client_id: null, client_name: '', client_dir: null, vinculos: [], original: null, tipoOriginal: null, puntosActuales: null, desdeDetalle: false,
        template_id: null, datos: {}, cliente_ref: null, conPlantilla: false,
      };
      this.clientSuggestions = [];
      this.plantillas = [];
      this.intentoGuardar = false;
      this.erroresServidor = {};
      this.clienteRefSearch = '';
      this.clienteRefSuggestions = [];
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
    // ── Planta externa (item #1200) ────────────────────────────────────
    textoMaterial,
    async prepararTipo() {
      const token = ++this.plantillasToken;
      this.plantillas = [];
      this.createModal.template_id = null;
      this.createModal.datos = {};
      this.erroresServidor = {};
      this.intentoGuardar = false;
      this.createModal.error = '';
      if (!this.esPlantaExterna) {
        this.clearClienteRef();
        return;
      }
      this.clearProspecto();
      this.plantillasCargando = true;
      try {
        const { data } = await axios.get('/talento/api/order-templates', { params: { type_id: this.createModal.type_id } });
        if (token !== this.plantillasToken) return; // llegó tarde: ya eligieron otro tipo
        this.plantillas = data ?? [];
        // Con una sola plantilla se preselecciona y no se muestra el selector.
        if (this.plantillas.length === 1) this.createModal.template_id = this.plantillas[0].id;
      } catch (e) {
        if (token === this.plantillasToken) this.createModal.error = 'No se pudo cargar la plantilla del tipo de orden.';
      } finally {
        if (token === this.plantillasToken) this.plantillasCargando = false;
      }
    },
    // "[Habilitar caja] Zona 3 · Caja 12" → "Zona 3 · Caja 12" (solo planta externa).
    identificadorOrden(o) {
      if (o.type?.area !== 'planta_externa' || !o.title) return '';
      const resto = o.title.replace(/^\[[^\]]*\]\s*/, '');
      return resto === 'Nueva orden' ? '' : resto;
    },
    camposDetalle(momento) { return camposDe(this.detail.order?.datos?.campos, momento); },
    debounceClienteRef() {
      clearTimeout(this.clienteRefTimeout);
      this.clienteRefTimeout = setTimeout(() => this.searchClienteRef(), 300);
    },
    async searchClienteRef() {
      const q = this.clienteRefSearch.trim();
      if (q.length < 2) { this.clienteRefSuggestions = []; return; }
      const { data } = await axios.get('/talento/api/clientes-referencia', { params: { search: q } });
      this.clienteRefSuggestions = data ?? [];
      this.clienteRefListOpen = true;
    },
    closeClienteRefDelayed() {
      this.clienteRefCloseTimeout = setTimeout(() => { this.clienteRefListOpen = false; }, 200);
    },
    selectClienteRef(c) {
      this.createModal.cliente_ref = { id: c.id, nombre: c.nombre };
      this.clienteRefSearch = c.nombre;
      this.clienteRefListOpen = false;
    },
    clearClienteRef() {
      this.createModal.cliente_ref = null;
      this.clienteRefSearch = '';
      this.clienteRefSuggestions = [];
    },
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
      if (this.createModal.editId) this.searchClientes(this.prospSearch.trim());
      const { data } = await axios.get('/talento/api/prospectos-crm', {
        params: {
          colaborador_id: this.createModal.colaborador_id || undefined,
          search: this.prospSearch.trim() || undefined,
        },
      });
      this.prospSuggestions = { propios: data?.propios ?? [], generales: data?.generales ?? [] };
    },
    // #1228: clientes para el formulario de edición (mínimo 3 letras, como el listado de Clientes).
    async searchClientes(texto) {
      if (texto.length < 3) { this.clientSuggestions = []; return; }
      try {
        const { data } = await axios.get('/talento/api/clientes-orden', { params: { search: texto } });
        if (texto === this.prospSearch.trim()) this.clientSuggestions = Array.isArray(data) ? data : [];
      } catch (e) {
        this.clientSuggestions = [];
      }
    },
    selectCliente(c) {
      Object.assign(this.createModal, { client_id: c.id, client_name: c.nombre, client_dir: c.direccion || null, crm_lead_id: null, crm_lead_name: '' });
      this.prospSearch = c.nombre;
      this.prospListOpen = false;
    },
    // Quita lo elegido (cliente o prospecto) y deja el buscador libre.
    quitarVinculo() {
      Object.assign(this.createModal, { client_id: null, client_name: '', client_dir: null });
      this.clientSuggestions = [];
      this.clearProspecto();
    },
    selectProspecto(p) {
      // Cliente o prospecto, nunca los dos: elegir un prospecto quita el cliente.
      Object.assign(this.createModal, { client_id: null, client_name: '', client_dir: null });
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
      this.createModal.choque = null;
      if (!this.createModal.colaborador_id) { this.createModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.createModal.type_id) { this.createModal.error = 'Selecciona un tipo de orden.'; return; }
      if (!this.createModal.scheduled_at) { this.createModal.error = 'Elige el día de la orden.'; return; }
      if (this.createModal.editId) { await this.guardarEdicion(); return; }
      const payload = {
        colaborador_id: this.createModal.colaborador_id,
        type_id:        this.createModal.type_id,
        scheduled_at:   this.createModal.scheduled_at || null,
        notes:          this.createModal.notes || null,
        crm_lead_id:    this.createModal.crm_lead_id || null,
      };
      // Item #1200: planta externa manda plantilla, datos y cliente de referencia (sin prospecto).
      if (this.esPlantaExterna) {
        if (this.plantillas.length && !this.createModal.template_id) { this.createModal.error = 'Elige la plantilla de la orden.'; return; }
        this.intentoGuardar = true;
        if (Object.keys(validarCampos(this.camposCreacion, this.createModal.datos)).length) {
          this.createModal.error = 'Revisa los datos de la orden marcados en rojo.';
          return;
        }
        payload.crm_lead_id = null;
        payload.template_id = this.createModal.template_id || null;
        payload.datos = limpiarValores(this.camposCreacion, this.createModal.datos);
        payload.client_id = this.createModal.cliente_ref?.id || null;
      }
      this.createModal.saving = true;
      try {
        await axios.post('/talento/api/ordenes', payload);
        this.createModal.show = false;
        this.load();
      } catch (e) {
        this.createModal.choque = e.response?.data?.choque ?? null;
        this.createModal.error = this.mensajeError(e, 'Error al crear la orden.');
        this.erroresServidor = erroresDeServidor(e.response?.data?.errors);
        this.verError();
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
    // ── Editar / eliminar una orden pendiente (#1225) ─────────────────────
    esEditable(o) {
      return !!o && o._source === 'task' && o.status === 'pending' && !o.eliminada;
    },
    tieneAccionesPendiente(o) {
      return this.esEditable(o) && (this.puedeEditarOrden || this.puedeEliminarOrden);
    },
    abrirMenu(o, ev) {
      if (this.menuOrden?.id === o.id) { this.cerrarMenu(); return; }
      const r = ev.currentTarget.getBoundingClientRect();
      const ancho = 170;
      const alto = 96;
      const abajo = r.bottom + 4 + alto <= window.innerHeight;
      this.menuPos = {
        top: abajo ? r.bottom + 4 : Math.max(8, r.top - alto - 4),
        left: Math.min(Math.max(8, r.right - ancho), window.innerWidth - ancho - 8),
      };
      this.menuOrden = o;
      this.$nextTick(() => this.$el.querySelector('.ord-menu button')?.focus());
    },
    cerrarMenu() { this.menuOrden = null; },
    // "YYYY-MM-DD HH:mm:ss" → valor de FechaHoraOrden: solo el día si no tenía hora.
    valorFechaHora(s) {
      if (!s) return '';
      const v = String(s).replace('T', ' ').slice(0, 16);
      return v.length < 16 || v.slice(11) === '00:00' ? v.slice(0, 10) : v;
    },
    // Cliente/prospecto/ticket ligados, para el aviso del modal de eliminar.
    ligaduras(o) {
      if (!o) return '';
      const partes = [];
      if (o.prospecto?.id || o.prospecto?.nombre) partes.push(`el prospecto ${o.prospecto?.nombre || '#' + o.prospecto.id}`);
      if (o.ticket_id) partes.push(`el ticket #${o.ticket_id}`);
      return partes.join(' y ');
    },
    mensajeError(e, porDefecto) {
      const d = e.response?.data;
      const validacion = d?.errors ? Object.values(d.errors)[0]?.[0] : null;
      return d?.error ?? validacion ?? d?.message ?? porDefecto;
    },
    // El técnico la inició (o alguien la eliminó) mientras la editabas: avisar y refrescar.
    yaNoPendiente(e) {
      const d = e.response?.data;
      if (e.response?.status !== 422 || !d?.ya_no_pendiente) return false;
      this.createModal.show = false;
      this.eliminarModal.show = false;
      this.detail.show = false;
      this.refrescar();
      Swal.fire({
        icon: 'warning',
        title: /eliminada/i.test(d.error || '') ? d.error : 'Esta orden ya fue iniciada, actualiza la lista',
        text: /eliminada/i.test(d.error || '') ? '' : 'La lista se actualizó con su estado actual.',
      });
      return true;
    },
    refrescar() {
      if (this.vistaAgenda) this.$refs.agenda?.recargar();
      else this.load(this.pagination.current_page);
    },
    async abrirEditar(o) {
      this.cerrarMenu();
      let d;
      try {
        ({ data: d } = await axios.get(`/talento/api/ordenes/${o.id}`));
      } catch (e) {
        Swal.fire({ icon: 'error', title: this.mensajeError(e, 'No se pudo abrir la orden.') });
        return;
      }
      if (!this.esEditable(d)) {
        this.yaNoPendiente({ response: { status: 422, data: { ya_no_pendiente: true } } });
        return;
      }
      const desdeDetalle = this.detail.show;
      this.detail.show = false;
      // #1200: planta externa lleva el cliente como referencia; con plantilla, sus datos y su esquema.
      const plantaExterna = this.types.find(t => t.id === d.type_id)?.area === 'planta_externa';
      const conPlantilla = !!d.datos;
      this.plantillas = conPlantilla ? [{
        id: d.datos.template_id, name: d.datos.plantilla, fields: d.datos.campos || [], description: '',
        checklist: (d.checklist || []).map(c => ({ label: c.label, cantidad: c.cantidad, unidad: c.unidad })),
      }] : [];
      this.plantillasCargando = false;
      this.intentoGuardar = false;
      this.erroresServidor = {};
      this.createModal = {
        show: true, editId: d.id, desdeDetalle,
        colaborador_id: d.colaborador_id, colaborador_name: this.fullName(d.colaborador?.user),
        type_id: d.type_id, tipoOriginal: d.type_id, puntosActuales: d.points,
        scheduled_at: this.valorFechaHora(d.scheduled_at), notes: (conPlantilla ? d.datos.notas : d.notes) || '',
        crm_lead_id: d.prospecto?.id || null, crm_lead_name: d.prospecto?.nombre || '',
        client_id: plantaExterna ? null : (d.cliente?.id || null), client_name: plantaExterna ? '' : (d.cliente?.nombre || ''),
        client_dir: plantaExterna ? null : (d.cliente?.direccion || null),
        vinculos: d.vinculos || [],
        template_id: d.datos?.template_id ?? null, datos: { ...(d.datos?.valores || {}) },
        cliente_ref: plantaExterna && d.cliente ? { id: d.cliente.id, nombre: d.cliente.nombre } : null,
        conPlantilla,
        saving: false, error: '', choque: null,
      };
      // Lo que tenía al abrir, para mostrar qué cambiará al guardar (#1228).
      const m = this.createModal;
      m.original = { client_id: m.client_id, client_name: m.client_name, client_dir: m.client_dir, crm_lead_id: m.crm_lead_id, crm_lead_name: m.crm_lead_name };
      this.clientSuggestions = [];
      this.clienteRefSearch = '';
      this.clienteRefSuggestions = [];
      this.colSearch = this.createModal.colaborador_name;
      this.colSuggestions = [];
      this.prospSearch = d.prospecto?.nombre || d.cliente?.nombre || '';
      this.prospSuggestions = { propios: [], generales: [] };
      this.prospListOpen = false;
    },
    async guardarEdicion() {
      const m = this.createModal;
      const payload = {
        colaborador_id: m.colaborador_id,
        type_id:        m.type_id,
        scheduled_at:   m.scheduled_at,
        notes:          m.notes || null,
        crm_lead_id:    m.crm_lead_id || null,
        client_id:      m.client_id || null,
      };
      // #1200: planta externa manda el cliente de referencia (sin prospecto) y, con plantilla,
      // todos sus campos de creación.
      if (this.esPlantaExterna) {
        payload.crm_lead_id = null;
        payload.client_id = m.cliente_ref?.id || null;
        if (m.conPlantilla) {
          this.intentoGuardar = true;
          if (Object.keys(validarCampos(this.camposCreacion, m.datos)).length) {
            m.error = 'Revisa los datos de la orden marcados en rojo.';
            return;
          }
          payload.datos = limpiarValores(this.camposCreacion, m.datos);
        }
      }
      m.saving = true;
      try {
        const { data } = await axios.put(`/talento/api/ordenes/${m.editId}`, payload);
        m.show = false;
        this.refrescar();
        if (m.desdeDetalle) this.detail = { show: true, order: data };
      } catch (e) {
        if (this.yaNoPendiente(e)) return;
        m.choque = e.response?.data?.choque ?? null;
        this.erroresServidor = erroresDeServidor(e.response?.data?.errors);
        m.error = this.mensajeError(e, 'Error al guardar la orden.');
        this.verError();
      } finally { m.saving = false; }
    },
    abrirEliminar(o) {
      this.cerrarMenu();
      this.detail.show = false;
      this.eliminarModal = { show: true, order: o, motivo: '', detalle: '', saving: false, error: '' };
    },
    cerrarEliminar() {
      if (!this.eliminarModal.saving) this.eliminarModal.show = false;
    },
    async confirmarEliminar() {
      const m = this.eliminarModal;
      if (!this.motivoEliminacionListo) { m.error = m.motivo === 'otro' ? 'Escribe el motivo.' : 'Elige el motivo.'; return; }
      m.saving = true;
      m.error = '';
      try {
        await axios.delete(`/talento/api/ordenes/${m.order.id}`, {
          data: { motivo: m.motivo, detalle: m.motivo === 'otro' ? m.detalle.trim() : null },
        });
        m.show = false;
        this.refrescar();
        Swal.fire({ icon: 'success', title: `Orden #${m.order.id} eliminada`, timer: 1800, showConfirmButton: false });
      } catch (e) {
        if (this.yaNoPendiente(e)) return;
        m.error = this.mensajeError(e, 'No se pudo eliminar la orden.');
      } finally { m.saving = false; }
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
.ord-menu-fondo {
  position: fixed;
  inset: 0;
  z-index: 1060;
}
.ord-menu {
  position: fixed;
  z-index: 1061;
  min-width: 170px;
  margin: 0;
  padding: 4px 0;
  list-style: none;
  background: var(--tc-surface, #fff);
  color: var(--tc-ink, #111827);
  border: 1px solid var(--tc-line, #e5e7eb);
  border-radius: 8px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
}
.ord-menu button {
  display: block;
  width: 100%;
  padding: 7px 14px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  font-size: 13px;
}
.ord-menu button:hover,
.ord-menu button:focus-visible {
  background: var(--tc-bg2, #f8fafc);
  outline: none;
}
.ord-menu .ord-menu-peligro {
  color: var(--tc-bad, #dc2626);
}
.ord-mas {
  padding-left: 9px;
  padding-right: 9px;
}
.ord-pts-cambio {
  color: var(--tc-warn, #d97706);
}
.talento-ordenes .prosp-group-header {
  background: var(--tc-bg2, #f8fafc);
  color: var(--tc-muted, #6b7280);
}
</style>
