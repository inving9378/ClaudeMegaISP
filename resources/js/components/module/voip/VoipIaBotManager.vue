<template>
  <div class="ia-bot-manager">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-0"><i class="fas fa-robot text-primary me-2"></i>Asistente IA MegaVoz</h4>
        <small class="text-muted">María — Atención a clientes y ventas</small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span :class="['badge', config.enabled ? 'bg-success' : 'bg-secondary']">
          {{ config.enabled ? 'Habilitado' : 'Deshabilitado' }}
        </span>
        <button v-if="canConfig" class="btn btn-sm btn-outline-primary" @click="toggleEnabled" :disabled="saving">
          {{ config.enabled ? 'Deshabilitar' : 'Habilitar' }}
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'config' }" @click="tab = 'config'">
          <i class="fas fa-cog me-1"></i>Configuración
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'conversations' }" @click="tab = 'conversations'; loadConversaciones()">
          <i class="fas fa-phone-alt me-1"></i>Conversaciones
          <span v-if="convTotal" class="badge bg-secondary ms-1">{{ convTotal }}</span>
        </button>
      </li>
      <li class="nav-item" v-if="canLeads">
        <button class="nav-link" :class="{ active: tab === 'leads' }" @click="tab = 'leads'; loadLeads()">
          <i class="fas fa-user-plus me-1"></i>Leads
          <span v-if="leadsNew" class="badge bg-warning text-dark ms-1">{{ leadsNew }}</span>
        </button>
      </li>
      <li class="nav-item" v-if="canKb">
        <button class="nav-link" :class="{ active: tab === 'kb' }" @click="tab = 'kb'; loadKb()">
          <i class="fas fa-book me-1"></i>Base de conocimiento
        </button>
      </li>
    </ul>

    <!-- TAB: CONFIG -->
    <div v-show="tab === 'config'">
      <div v-if="loadingConfig" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      <div v-else-if="!canConfig" class="alert alert-info"><i class="fas fa-lock me-2"></i>No tienes permiso para editar la configuración.</div>
      <form v-else @submit.prevent="saveConfig">
        <div class="row g-3">
          <!-- System prompt -->
          <div class="col-12">
            <label class="form-label fw-bold">Prompt del sistema (instrucciones para María)</label>
            <textarea class="form-control font-monospace" rows="10" v-model="config.system_prompt"
              placeholder="Eres María, asistente de..."></textarea>
            <div class="form-text">
              Incluye <code>[TRANSFERIR]</code> en las instrucciones para que el bot sepa cuándo transferir a un agente.
            </div>
          </div>

          <!-- Saludo cliente -->
          <div class="col-md-6">
            <label class="form-label">Saludo a cliente existente</label>
            <textarea class="form-control" rows="3" v-model="config.greeting_customer"
              placeholder="Hola [nombre], bienvenido a Meganet..."></textarea>
            <div class="form-text">Usa <code>[nombre]</code> para insertar el nombre del cliente.</div>
          </div>

          <!-- Saludo lead -->
          <div class="col-md-6">
            <label class="form-label">Saludo a nuevo interesado</label>
            <textarea class="form-control" rows="3" v-model="config.greeting_lead"
              placeholder="Hola, bienvenido a Meganet..."></textarea>
          </div>

          <!-- Voz -->
          <div class="col-md-4">
            <label class="form-label">Voz (OpenAI TTS)</label>
            <select class="form-select" v-model="config.voice">
              <option value="nova">Nova (mujer, recomendada)</option>
              <option value="shimmer">Shimmer (mujer)</option>
              <option value="alloy">Alloy (neutral)</option>
              <option value="echo">Echo (hombre)</option>
              <option value="onyx">Onyx (hombre, grave)</option>
              <option value="fable">Fable (neutral)</option>
            </select>
          </div>

          <!-- Temperatura -->
          <div class="col-md-4">
            <label class="form-label">Creatividad (temperatura): <strong>{{ config.temperature }}</strong></label>
            <input type="range" class="form-range" min="0" max="1" step="0.1" v-model.number="config.temperature">
            <div class="d-flex justify-content-between small text-muted"><span>Preciso</span><span>Creativo</span></div>
          </div>

          <!-- Grupo de timbrado -->
          <div class="col-md-4">
            <label class="form-label">Grupo de timbrado para transferencias</label>
            <input class="form-control" v-model="config.grupo_timbrado_name" placeholder="ej. soporte, ventas">
            <div class="form-text">Nombre del endpoint PJSIP del grupo.</div>
          </div>

          <!-- Límites -->
          <div class="col-md-4">
            <label class="form-label">Turnos máximos: <strong>{{ config.max_turns }}</strong></label>
            <input type="range" class="form-range" min="3" max="30" step="1" v-model.number="config.max_turns">
          </div>
          <div class="col-md-4">
            <label class="form-label">Duración máxima (s): <strong>{{ config.max_duration_seconds }}</strong></label>
            <input type="range" class="form-range" min="60" max="900" step="30" v-model.number="config.max_duration_seconds">
          </div>
          <div class="col-md-4">
            <label class="form-label">Timeout escucha (s): <strong>{{ config.timeout_seconds }}</strong></label>
            <input type="range" class="form-range" min="5" max="30" step="1" v-model.number="config.timeout_seconds">
          </div>

          <!-- Submit -->
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <i class="fas fa-save me-1"></i>{{ saving ? 'Guardando...' : 'Guardar configuración' }}
            </button>
            <button type="button" class="btn btn-outline-secondary" @click="loadConfig">
              <i class="fas fa-undo me-1"></i>Descartar cambios
            </button>
          </div>
        </div>
      </form>

      <!-- Alertas de prereqs -->
      <div class="mt-4">
        <h6 class="text-muted">Prerequisitos para funcionar</h6>
        <div class="list-group">
          <div class="list-group-item d-flex align-items-center gap-2">
            <i class="fas fa-check-circle text-success"></i>
            <span>Asterisk activo y AGI habilitado</span>
          </div>
          <div class="list-group-item d-flex align-items-center gap-2">
            <i class="fas fa-exclamation-triangle text-warning"></i>
            <span>OpenAI API Key configurada en <a href="/integraciones" target="_blank">Integration Hub</a> (provider: openai)</span>
          </div>
          <div class="list-group-item d-flex align-items-center gap-2">
            <i class="fas fa-exclamation-triangle text-warning"></i>
            <span>ffmpeg disponible en el servidor para conversión de audio</span>
          </div>
          <div class="list-group-item d-flex align-items-center gap-2">
            <i :class="config.grupo_timbrado_name ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger'"></i>
            <span>Grupo de timbrado configurado: <strong>{{ config.grupo_timbrado_name || 'no configurado' }}</strong></span>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: CONVERSACIONES -->
    <div v-show="tab === 'conversations'">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <input class="form-control form-control-sm" v-model="convFilter.phone" placeholder="Buscar por teléfono..." @input="debouncedLoadConv">
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" v-model="convFilter.type" @change="loadConversaciones">
            <option value="">Todos los tipos</option>
            <option value="customer_support">Atención cliente</option>
            <option value="sales_lead">Lead / venta</option>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" v-model="convFilter.status" @change="loadConversaciones">
            <option value="">Todos los estados</option>
            <option value="completed">Completada</option>
            <option value="transferred">Transferida</option>
            <option value="timeout">Timeout</option>
            <option value="failed">Fallida</option>
          </select>
        </div>
      </div>

      <div v-if="loadingConv" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      <div v-else-if="!conversaciones.length" class="text-center py-5 text-muted">
        <i class="fas fa-phone-slash fa-3x mb-3 d-block"></i>No hay conversaciones registradas aún.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm">
          <thead class="table-light">
            <tr>
              <th>Teléfono</th>
              <th>Tipo</th>
              <th>Cliente</th>
              <th>Duración</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Costo USD</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in conversaciones" :key="c.id">
              <td>{{ c.phone_number }}</td>
              <td>
                <span :class="['badge', c.conversation_type === 'customer_support' ? 'bg-info text-dark' : 'bg-warning text-dark']">
                  {{ c.conversation_type === 'customer_support' ? 'Atención' : 'Lead' }}
                </span>
              </td>
              <td>{{ c.customer_name || '—' }}</td>
              <td>{{ c.duration ? `${c.duration}s` : '—' }}</td>
              <td>
                <span :class="['badge', statusBadge(c.status)]">{{ c.status }}</span>
              </td>
              <td>{{ formatDate(c.started_at) }}</td>
              <td>${{ c.cost_usd || '0.00' }}</td>
              <td>
                <button class="btn btn-xs btn-outline-secondary" @click="showTranscript(c)">
                  <i class="fas fa-comment-alt"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginación simple -->
        <div class="d-flex justify-content-between align-items-center mt-2">
          <small class="text-muted">{{ convMeta.total }} conversaciones</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="convMeta.current_page <= 1" @click="loadConversaciones(convMeta.current_page - 1)">
              <i class="fas fa-chevron-left"></i>
            </button>
            <span class="btn btn-outline-secondary disabled">{{ convMeta.current_page }} / {{ convMeta.last_page }}</span>
            <button class="btn btn-outline-secondary" :disabled="convMeta.current_page >= convMeta.last_page" @click="loadConversaciones(convMeta.current_page + 1)">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: LEADS -->
    <div v-show="tab === 'leads'">
      <div class="row g-3 mb-3 align-items-center">
        <div class="col-md-3">
          <select class="form-select form-select-sm" v-model="leadsFilter.status" @change="loadLeads">
            <option value="">Todos los estados</option>
            <option value="new">Nuevo</option>
            <option value="contacted">Contactado</option>
            <option value="interested">Interesado</option>
            <option value="converted">Convertido</option>
            <option value="rejected">Rechazado</option>
          </select>
        </div>
      </div>

      <div v-if="loadingLeads" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      <div v-else-if="!leads.length" class="text-center py-5 text-muted">
        <i class="fas fa-user-times fa-3x mb-3 d-block"></i>No hay leads generados aún.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm">
          <thead class="table-light">
            <tr>
              <th>Teléfono</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Servicio interés</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lead in leads" :key="lead.id">
              <td>{{ lead.phone }}</td>
              <td>{{ lead.name || '—' }}</td>
              <td>{{ lead.email || '—' }}</td>
              <td>{{ lead.interest_service || '—' }}</td>
              <td>
                <select class="form-select form-select-sm" v-model="lead.status" @change="updateLead(lead)" style="width:120px">
                  <option value="new">Nuevo</option>
                  <option value="contacted">Contactado</option>
                  <option value="interested">Interesado</option>
                  <option value="converted">Convertido</option>
                  <option value="rejected">Rechazado</option>
                </select>
              </td>
              <td>{{ formatDate(lead.created_at) }}</td>
              <td>
                <button class="btn btn-xs btn-outline-primary" @click="showLead(lead)">
                  <i class="fas fa-eye"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: BASE DE CONOCIMIENTO -->
    <div v-show="tab === 'kb'">
      <div class="d-flex justify-content-between mb-3">
        <h6 class="mb-0">Problemas y soluciones conocidos</h6>
        <button v-if="canKb" class="btn btn-sm btn-primary" @click="openKbModal()">
          <i class="fas fa-plus me-1"></i>Agregar problema
        </button>
      </div>

      <div v-if="loadingKb" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      <div v-else>
        <div v-for="(items, cat) in kbByCategory" :key="cat" class="mb-4">
          <h6 class="text-uppercase text-muted small mb-2">
            <i :class="categoryIcon(cat)"></i> {{ cat }}
          </h6>
          <div class="list-group">
            <div v-for="item in items" :key="item.id" class="list-group-item">
              <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                  <strong>{{ item.problem }}</strong>
                  <div class="small text-muted mt-1">
                    <i class="fas fa-tags me-1"></i>{{ item.keywords || 'Sin keywords' }}
                  </div>
                  <div class="small mt-1">
                    <i class="fas fa-list me-1"></i>{{ Object.keys(item.solution_steps).length }} pasos
                    <span v-if="item.escalate_if_fails" class="ms-2 text-warning">
                      <i class="fas fa-user-tie"></i> Escala si falla
                    </span>
                  </div>
                </div>
                <div class="d-flex gap-1" v-if="canKb">
                  <button class="btn btn-xs btn-outline-secondary" @click="openKbModal(item)">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button class="btn btn-xs btn-outline-danger" @click="deleteKb(item)">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!kbItems.length" class="text-center py-5 text-muted">
          <i class="fas fa-book-open fa-3x mb-3 d-block"></i>Base de conocimiento vacía.
        </div>
      </div>
    </div>

    <!-- Modal: Transcript -->
    <div class="modal fade" id="transcriptModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i>Conversación — {{ selectedConv?.phone_number }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" style="max-height:500px;overflow-y:auto">
            <div v-if="selectedConv" class="d-flex flex-column gap-2">
              <div v-for="(turn, i) in selectedConv.transcript" :key="i"
                   :class="['p-2 rounded', turn.role === 'user' ? 'bg-light align-self-end text-end' : 'bg-primary bg-opacity-10']"
                   style="max-width:80%">
                <small class="text-muted d-block">{{ turn.role === 'user' ? 'Cliente' : 'María' }}</small>
                {{ turn.text || turn.content }}
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: KB -->
    <div class="modal fade" id="kbModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ kbForm.id ? 'Editar' : 'Nuevo' }} problema en base de conocimiento</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select class="form-select" v-model="kbForm.category">
                  <option value="internet">Internet</option>
                  <option value="voip">VoIP</option>
                  <option value="facturacion">Facturación</option>
                  <option value="cobertura">Cobertura</option>
                  <option value="otro">Otro</option>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label">Problema</label>
                <input class="form-control" v-model="kbForm.problem" placeholder="Describe el problema brevemente">
              </div>
              <div class="col-12">
                <label class="form-label">Keywords (separados por coma)</label>
                <input class="form-control" v-model="kbForm.keywords" placeholder="sin internet, no conecta, wifi caído">
              </div>
              <div class="col-12">
                <label class="form-label">Pasos de solución</label>
                <div v-for="(val, key) in kbForm.solution_steps" :key="key" class="input-group mb-2">
                  <span class="input-group-text small">{{ key }}</span>
                  <input class="form-control" v-model="kbForm.solution_steps[key]" :placeholder="`Paso ${key}`">
                  <button class="btn btn-outline-danger" type="button" @click="removeStep(key)"><i class="fas fa-times"></i></button>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" @click="addStep">
                  <i class="fas fa-plus me-1"></i>Agregar paso
                </button>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="kbForm.escalate_if_fails" id="escalateCheck">
                  <label class="form-check-label" for="escalateCheck">
                    Escalar a asesor si los pasos no resuelven el problema
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" @click="saveKb" :disabled="savingKb">
              {{ savingKb ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Lead detalle -->
    <div class="modal fade" id="leadModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Lead — {{ selectedLead?.phone }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" v-if="selectedLead">
            <dl class="row">
              <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ selectedLead.phone || '—' }}</dd>
              <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ selectedLead.name || '—' }}</dd>
              <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ selectedLead.email || '—' }}</dd>
              <dt class="col-sm-4">Dirección</dt><dd class="col-sm-8">{{ selectedLead.address || '—' }}</dd>
              <dt class="col-sm-4">Servicio interés</dt><dd class="col-sm-8">{{ selectedLead.interest_service || '—' }}</dd>
              <dt class="col-sm-4">Plan interés</dt><dd class="col-sm-8">{{ selectedLead.interest_plan || '—' }}</dd>
              <dt class="col-sm-4">Estado</dt>
              <dd class="col-sm-8">
                <select class="form-select form-select-sm" v-model="selectedLead.status" @change="updateLead(selectedLead)" style="width:150px">
                  <option value="new">Nuevo</option>
                  <option value="contacted">Contactado</option>
                  <option value="interested">Interesado</option>
                  <option value="converted">Convertido</option>
                  <option value="rejected">Rechazado</option>
                </select>
              </dd>
              <dt class="col-sm-4">Notas</dt>
              <dd class="col-sm-8">
                <textarea class="form-control form-control-sm" v-model="selectedLead.notes" rows="3" placeholder="Agregar notas..."></textarea>
                <button class="btn btn-sm btn-primary mt-1" @click="updateLead(selectedLead)">Guardar notas</button>
              </dd>
            </dl>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VoipIaBotManager',
  props: {
    csrfToken: String,
    baseUrl:   String,
    canConfig: { type: String, default: '0' },
    canLeads:  { type: String, default: '0' },
    canKb:     { type: String, default: '0' },
  },
  data() {
    return {
      tab: 'config',
      config: { enabled: false, system_prompt: '', greeting_customer: '', greeting_lead: '',
                voice: 'nova', temperature: 0.7, max_turns: 12, max_duration_seconds: 480,
                timeout_seconds: 15, grupo_timbrado_name: '' },
      saving: false,
      loadingConfig: true,

      conversaciones: [],
      convMeta: { current_page: 1, last_page: 1, total: 0 },
      convTotal: 0,
      convFilter: { phone: '', type: '', status: '' },
      loadingConv: false,
      selectedConv: null,
      _convDebounce: null,

      leads: [],
      leadsNew: 0,
      leadsFilter: { status: '' },
      loadingLeads: false,
      selectedLead: null,

      kbItems: [],
      loadingKb: false,
      kbForm: { id: null, category: 'internet', problem: '', keywords: '', solution_steps: { step1: '' }, escalate_if_fails: true },
      savingKb: false,
    };
  },
  computed: {
    canConfig() { return this.$props.canConfig === '1'; },
    canLeads()  { return this.$props.canLeads  === '1'; },
    canKb()     { return this.$props.canKb     === '1'; },
    kbByCategory() {
      return this.kbItems.reduce((acc, item) => {
        if (!acc[item.category]) acc[item.category] = [];
        acc[item.category].push(item);
        return acc;
      }, {});
    },
  },
  mounted() {
    this.loadConfig();
  },
  methods: {
    async request(method, url, body = null) {
      const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
      };
      if (body) opts.body = JSON.stringify(body);
      const res = await fetch(this.baseUrl + url, opts);
      if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message || 'Error');
      }
      return res.json();
    },

    async loadConfig() {
      this.loadingConfig = true;
      try {
        const data = await this.request('GET', '/ia-bot/config');
        this.config = data;
      } catch (e) { this.$notify?.({ type: 'error', title: e.message }); }
      finally { this.loadingConfig = false; }
    },

    async saveConfig() {
      this.saving = true;
      try {
        const { data } = await this.request('PUT', '/ia-bot/config', this.config);
        this.config = data;
        this.showToast('Configuración guardada correctamente', 'success');
      } catch (e) { this.showToast(e.message, 'danger'); }
      finally { this.saving = false; }
    },

    async toggleEnabled() {
      this.config.enabled = !this.config.enabled;
      await this.saveConfig();
    },

    async loadConversaciones(page = 1) {
      this.loadingConv = true;
      try {
        const params = new URLSearchParams({ page, ...this.convFilter });
        const data = await this.request('GET', `/ia-bot/conversaciones?${params}`);
        this.conversaciones = data.data;
        this.convMeta = { current_page: data.current_page, last_page: data.last_page, total: data.total };
        this.convTotal = data.total;
      } catch (e) { this.showToast(e.message, 'danger'); }
      finally { this.loadingConv = false; }
    },

    debouncedLoadConv() {
      clearTimeout(this._convDebounce);
      this._convDebounce = setTimeout(() => this.loadConversaciones(), 400);
    },

    showTranscript(conv) {
      this.selectedConv = conv;
      this.openModal('transcriptModal');
    },

    async loadLeads() {
      this.loadingLeads = true;
      try {
        const params = new URLSearchParams(this.leadsFilter);
        const data = await this.request('GET', `/ia-bot/leads?${params}`);
        this.leads = data.data;
        this.leadsNew = data.data.filter(l => l.status === 'new').length;
      } catch (e) { this.showToast(e.message, 'danger'); }
      finally { this.loadingLeads = false; }
    },

    async updateLead(lead) {
      try {
        await this.request('PUT', `/ia-bot/leads/${lead.id}`, { status: lead.status, notes: lead.notes, assigned_to_user_id: lead.assigned_to_user_id });
        this.showToast('Lead actualizado', 'success');
      } catch (e) { this.showToast(e.message, 'danger'); }
    },

    showLead(lead) {
      this.selectedLead = { ...lead };
      this.openModal('leadModal');
    },

    async loadKb() {
      this.loadingKb = true;
      try {
        const { data } = await this.request('GET', '/ia-bot/kb');
        this.kbItems = data;
      } catch (e) { this.showToast(e.message, 'danger'); }
      finally { this.loadingKb = false; }
    },

    openKbModal(item = null) {
      if (item) {
        this.kbForm = { ...item, solution_steps: { ...item.solution_steps } };
      } else {
        this.kbForm = { id: null, category: 'internet', problem: '', keywords: '', solution_steps: { step1: '' }, escalate_if_fails: true };
      }
      this.openModal('kbModal');
    },

    addStep() {
      const n = Object.keys(this.kbForm.solution_steps).length + 1;
      this.kbForm.solution_steps[`step${n}`] = '';
    },

    removeStep(key) {
      const steps = { ...this.kbForm.solution_steps };
      delete steps[key];
      this.kbForm.solution_steps = steps;
    },

    async saveKb() {
      this.savingKb = true;
      try {
        if (this.kbForm.id) {
          await this.request('PUT', `/ia-bot/kb/${this.kbForm.id}`, this.kbForm);
        } else {
          await this.request('POST', '/ia-bot/kb', this.kbForm);
        }
        this.showToast('Guardado correctamente', 'success');
        this.closeModal('kbModal');
        await this.loadKb();
      } catch (e) { this.showToast(e.message, 'danger'); }
      finally { this.savingKb = false; }
    },

    async deleteKb(item) {
      if (!confirm(`¿Eliminar "${item.problem}"?`)) return;
      try {
        await this.request('DELETE', `/ia-bot/kb/${item.id}`);
        this.showToast('Eliminado', 'success');
        await this.loadKb();
      } catch (e) { this.showToast(e.message, 'danger'); }
    },

    statusBadge(status) {
      return { completed: 'bg-success', transferred: 'bg-info text-dark', timeout: 'bg-warning text-dark', failed: 'bg-danger' }[status] || 'bg-secondary';
    },

    categoryIcon(cat) {
      return { internet: 'fas fa-wifi me-1', voip: 'fas fa-phone me-1', facturacion: 'fas fa-file-invoice me-1', cobertura: 'fas fa-map-marker-alt me-1' }[cat] || 'fas fa-tag me-1';
    },

    formatDate(dt) {
      if (!dt) return '—';
      return new Date(dt).toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },

    openModal(id) {
      const el = document.getElementById(id);
      if (el && window.bootstrap) new window.bootstrap.Modal(el).show();
    },

    closeModal(id) {
      const el = document.getElementById(id);
      if (el && window.bootstrap) window.bootstrap.Modal.getInstance(el)?.hide();
    },

    showToast(msg, type = 'info') {
      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
      toastEl.setAttribute('role', 'alert');
      toastEl.style.zIndex = 9999;
      toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
      document.body.appendChild(toastEl);
      if (window.bootstrap) {
        const toast = new window.bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
      }
    },
  },
};
</script>

<style scoped>
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
</style>
