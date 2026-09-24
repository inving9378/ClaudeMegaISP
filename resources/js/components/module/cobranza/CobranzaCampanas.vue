<template>
    <div class="cobranza-campanas">
        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Campañas activas</div>
                        <div class="fs-3 fw-bold text-primary">{{ kpis.campanas_activas ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Pendientes hoy</div>
                        <div class="fs-3 fw-bold text-warning">{{ kpis.llamadas_pendientes ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Contestadas hoy</div>
                        <div class="fs-3 fw-bold text-success">{{ kpis.llamadas_contestadas ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Pagadas hoy</div>
                        <div class="fs-3 fw-bold text-info">{{ kpis.pagadas_hoy ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fa fa-broadcast-tower me-2 text-primary"></i>Campañas de Cobranza</h5>
            <button class="btn btn-sm btn-primary" @click="abrirNueva">
                <i class="fa fa-plus me-1"></i> Nueva campaña
            </button>
        </div>

        <!-- Tabla campañas -->
        <div v-if="cargando" class="text-muted py-4 text-center">Cargando campañas…</div>
        <div v-else-if="campanas.length === 0" class="alert alert-info">Sin campañas registradas.</div>
        <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Periodo</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Contestadas</th>
                        <th class="text-end">Pagadas</th>
                        <th class="text-end">Pendientes</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in campanas" :key="c.id">
                        <td>
                            <div class="fw-600">
                                {{ c.nombre }}
                                <span class="badge bg-light text-dark border ms-1">{{ tipoEtiqueta(c.tipo) }}</span>
                            </div>
                            <div class="text-muted small">{{ c.notas }}</div>
                        </td>
                        <td>
                            <span class="badge" :class="estadoBadge(c.estado)">{{ c.estado }}</span>
                        </td>
                        <td class="small text-muted">
                            <span v-if="c.fecha_inicio">{{ formatFecha(c.fecha_inicio) }}</span>
                            <span v-if="c.fecha_fin"> — {{ formatFecha(c.fecha_fin) }}</span>
                            <span v-if="!c.fecha_inicio && !c.fecha_fin">—</span>
                        </td>
                        <td class="text-end">{{ c.total_llamadas }}</td>
                        <td class="text-end text-success">{{ c.contestadas }}</td>
                        <td class="text-end text-info fw-600">{{ c.pagadas }}</td>
                        <td class="text-end text-warning">{{ c.pendientes }}</td>
                        <td class="text-center">
                            <button v-if="['borrador','pausada'].includes(c.estado) && c.tipo === 'cobranza'"
                                    class="btn btn-sm btn-success me-1" @click="activar(c)"
                                    :disabled="procesando === c.id" title="Activar">
                                <i class="fa fa-play"></i>
                            </button>
                            <button v-if="['borrador','pausada'].includes(c.estado) && c.tipo !== 'cobranza'"
                                    class="btn btn-sm btn-success me-1" @click="abrirZona(c)"
                                    :disabled="procesando === c.id" title="Activar por zona">
                                <i class="fa fa-map-marker-alt"></i>
                            </button>
                            <button v-if="c.estado === 'activa'"
                                    class="btn btn-sm btn-warning me-1" @click="pausar(c)"
                                    :disabled="procesando === c.id" title="Pausar">
                                <i class="fa fa-pause"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary me-1"
                                    @click="verLlamadas(c)" title="Ver llamadas">
                                <i class="fa fa-list"></i>
                            </button>
                            <button v-if="c.estado === 'borrador'"
                                    class="btn btn-sm btn-outline-danger" @click="eliminar(c)"
                                    title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="d-flex justify-content-end gap-2 mt-2" v-if="pagination.last_page > 1">
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1"
                        @click="cambiarPagina(pagination.current_page - 1)">‹ Anterior</button>
                <span class="btn btn-sm btn-light disabled">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page"
                        @click="cambiarPagina(pagination.current_page + 1)">Siguiente ›</button>
            </div>
        </div>

        <!-- Modal Nueva Campaña -->
        <div v-if="modal.nueva" class="modal-overlay" @click.self="modal.nueva = false">
            <div class="modal-box card p-4" style="max-width:560px;width:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fa fa-plus-circle me-2 text-primary"></i>Nueva campaña</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.nueva = false">✕</button>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small">Nombre *</label>
                        <input class="form-control form-control-sm" v-model="nueva.nombre"
                               :class="{'is-invalid': erroresNueva.nombre}">
                        <div class="invalid-feedback">{{ erroresNueva.nombre }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Tipo *</label>
                        <select class="form-select form-select-sm" v-model="nueva.tipo">
                            <option value="cobranza">Cobranza (a morosos)</option>
                            <option value="aviso">Aviso (por zona)</option>
                            <option value="anuncio">Anuncio (por zona)</option>
                            <option value="corte">Corte de servicio (por zona)</option>
                        </select>
                        <div class="form-text small" v-if="nueva.tipo !== 'cobranza'">
                            Se activa eligiendo distrito/zona/caja, no aquí — el botón
                            <i class="fa fa-map-marker-alt"></i> de la lista abre ese paso.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Troncal de salida</label>
                        <select class="form-select form-select-sm" v-model="nueva.troncal_id">
                            <option :value="null">— Usar la troncal por defecto del sistema —</option>
                            <option v-for="t in troncales" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div class="col-12" v-if="nueva.tipo !== 'cobranza'">
                        <label class="form-label small">Mensaje a leer (se lee tal cual, por voz) *</label>
                        <textarea class="form-control form-control-sm" v-model="nueva.audio_mensaje" rows="3"
                                  :class="{'is-invalid': erroresNueva.audio_mensaje}"
                                  placeholder="Ej: Le informamos que el día... habrá un corte programado de servicio en su zona..."></textarea>
                        <div class="invalid-feedback">{{ erroresNueva.audio_mensaje }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Canales simultáneos (opcional)</label>
                        <input type="number" class="form-control form-control-sm" v-model.number="nueva.max_canales_simultaneos" min="1" max="100"
                               placeholder="Sin límite">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Fecha inicio</label>
                        <input type="date" class="form-control form-control-sm" v-model="nueva.fecha_inicio">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Fecha fin</label>
                        <input type="date" class="form-control form-control-sm" v-model="nueva.fecha_fin">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Hora inicio</label>
                        <input type="time" class="form-control form-control-sm" v-model="nueva.hora_inicio">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Hora fin</label>
                        <input type="time" class="form-control form-control-sm" v-model="nueva.hora_fin">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Máx. intentos</label>
                        <input type="number" class="form-control form-control-sm" v-model.number="nueva.max_intentos" min="1" max="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Min. entre intentos</label>
                        <input type="number" class="form-control form-control-sm" v-model.number="nueva.minutos_entre_intentos" min="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Días vencimiento</label>
                        <input type="number" class="form-control form-control-sm" v-model.number="nueva.dias_vencimiento" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Notas</label>
                        <textarea class="form-control form-control-sm" v-model="nueva.notas" rows="2"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.nueva = false">Cancelar</button>
                    <button class="btn btn-sm btn-primary" @click="crearCampana" :disabled="creando">
                        {{ creando ? 'Creando…' : 'Crear campaña' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Llamadas -->
        <div v-if="modal.llamadas" class="modal-overlay" @click.self="modal.llamadas = false">
            <div class="modal-box card p-4" style="max-width:800px;width:100%;max-height:85vh;overflow-y:auto;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fa fa-list me-2 text-primary"></i>{{ campanaSeleccionada?.nombre }}</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.llamadas = false">✕</button>
                </div>

                <div class="mb-2">
                    <select class="form-select form-select-sm w-auto" v-model="filtroEstadoLlamadas" @change="cargarLlamadas">
                        <option value="">Todos los estados</option>
                        <option v-for="e in estadosLlamada" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>

                <div v-if="cargandoLlamadas" class="text-muted">Cargando…</div>
                <div v-else class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Intentos</th>
                                <th>Último intento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in llamadas" :key="l.id">
                                <td class="small">{{ l.cliente_nombre }}</td>
                                <td class="small text-muted">{{ l.telefono }}</td>
                                <td><span class="badge" :class="llamadaBadge(l.estado)">{{ l.estado }}</span></td>
                                <td class="text-end small text-danger">${{ formatMoney(l.monto_vencido) }}</td>
                                <td class="text-end small">{{ l.intentos }}</td>
                                <td class="small text-muted">{{ formatFechaHora(l.ultimo_intento_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Activar por zona (MegaVoz Fase 7 — aviso/anuncio/corte) -->
        <div v-if="modal.zona" class="modal-overlay" @click.self="modal.zona = false">
            <div class="modal-box card p-4" style="max-width:480px;width:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fa fa-map-marker-alt me-2 text-primary"></i>Activar por zona</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.zona = false">✕</button>
                </div>
                <p class="text-muted small">
                    "{{ campanaSeleccionada?.nombre }}" se activará llamando a los clientes del área elegida.
                    Zona/caja son opcionales — sin elegirlas, se llama a TODO el distrito.
                </p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small">Distrito *</label>
                        <select class="form-select form-select-sm" v-model.number="zona.district_id" @change="cargarZonasDeDistrito">
                            <option :value="null">— Elegir —</option>
                            <option v-for="d in distritos" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="col-12" v-if="zona.district_id">
                        <label class="form-label small">Zona (opcional)</label>
                        <select class="form-select form-select-sm" v-model.number="zona.zone_id" @change="cargarCajasDeZona">
                            <option :value="null">— Todas las zonas del distrito —</option>
                            <option v-for="z in zonasDelDistrito" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <div class="col-12" v-if="zona.zone_id">
                        <label class="form-label small">Caja (opcional)</label>
                        <select class="form-select form-select-sm" v-model.number="zona.box_id">
                            <option :value="null">— Todas las cajas de la zona —</option>
                            <option v-for="c in cajasDeZona" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.zona = false">Cancelar</button>
                    <button class="btn btn-sm btn-success" @click="activarPorZona"
                            :disabled="!zona.district_id || activandoZona">
                        {{ activandoZona ? 'Activando…' : 'Activar campaña' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CobranzaCampanas',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            cargando:    true,
            campanas:    [],
            pagination:  {},
            kpis:        {},
            procesando:  null,
            modal:       { nueva: false, llamadas: false, zona: false },
            nueva:       { nombre: '', tipo: 'cobranza', troncal_id: null, max_canales_simultaneos: null,
                           audio_mensaje: '', fecha_inicio: '', fecha_fin: '', hora_inicio: '09:00',
                           hora_fin: '20:00', max_intentos: 3, minutos_entre_intentos: 180, dias_vencimiento: 1, notas: '' },
            erroresNueva:        {},
            creando:             false,
            campanaSeleccionada: null,
            llamadas:            [],
            cargandoLlamadas:    false,
            filtroEstadoLlamadas:'',
            estadosLlamada:      ['pendiente','marcando','contestada','no_contesto','ocupado','fallida','pagada','excluida'],
            // MegaVoz Fase 7 — troncales para el selector + distrito/zona/caja
            // del "activar por zona" (aviso/anuncio/corte).
            troncales:         [],
            distritos:         [],
            zonasDelDistrito:  [],
            cajasDeZona:       [],
            zona:              { district_id: null, zone_id: null, box_id: null },
            activandoZona:     false,
        };
    },
    mounted() {
        this.cargar();
        this.cargarKpis();
        this.cargarTroncales();
    },
    methods: {
        async cargar(page = 1) {
            this.cargando = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/data`, { params: { page } });
                this.campanas   = data.data;
                this.pagination = { current_page: data.current_page, last_page: data.last_page };
            } catch (e) {
                console.error(e);
            } finally {
                this.cargando = false;
            }
        },
        async cargarKpis() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/kpis`);
                this.kpis = data;
            } catch (e) {
                console.error(e);
            }
        },
        cambiarPagina(page) { this.cargar(page); },
        abrirNueva() {
            this.nueva         = { nombre: '', tipo: 'cobranza', troncal_id: null, max_canales_simultaneos: null,
                                   audio_mensaje: '', fecha_inicio: '', fecha_fin: '', hora_inicio: '09:00',
                                   hora_fin: '20:00', max_intentos: 3, minutos_entre_intentos: 180, dias_vencimiento: 1, notas: '' };
            this.erroresNueva  = {};
            this.modal.nueva   = true;
        },
        // MegaVoz Fase 7 — lista de troncales para el selector (best-effort:
        // si el usuario no tiene voip.troncales.view, la lista queda vacía y
        // el select se queda en "usar la troncal por defecto", sin bloquear
        // la creación de la campaña).
        async cargarTroncales() {
            try {
                const { data } = await axios.get('/voip/troncales/data');
                this.troncales = data;
            } catch (e) {
                this.troncales = [];
            }
        },
        tipoEtiqueta(tipo) {
            return { cobranza: 'Cobranza', aviso: 'Aviso', anuncio: 'Anuncio', corte: 'Corte' }[tipo] || 'Cobranza';
        },
        abrirZona(campana) {
            this.campanaSeleccionada = campana;
            this.zona                = { district_id: null, zone_id: null, box_id: null };
            this.zonasDelDistrito    = [];
            this.cajasDeZona         = [];
            this.modal.zona          = true;
            if (this.distritos.length === 0) this.cargarDistritos();
        },
        async cargarDistritos() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/zonas/distritos`);
                this.distritos = data;
            } catch (e) {
                console.error(e);
            }
        },
        async cargarZonasDeDistrito() {
            this.zona.zone_id   = null;
            this.zona.box_id    = null;
            this.cajasDeZona     = [];
            this.zonasDelDistrito = [];
            if (!this.zona.district_id) return;
            try {
                const { data } = await axios.get(`${this.baseUrl}/zonas/distritos/${this.zona.district_id}/zonas`);
                this.zonasDelDistrito = data;
            } catch (e) {
                console.error(e);
            }
        },
        async cargarCajasDeZona() {
            this.zona.box_id = null;
            this.cajasDeZona  = [];
            if (!this.zona.zone_id) return;
            try {
                const { data } = await axios.get(`${this.baseUrl}/zonas/zonas/${this.zona.zone_id}/cajas`);
                this.cajasDeZona = data;
            } catch (e) {
                console.error(e);
            }
        },
        async activarPorZona() {
            if (!this.campanaSeleccionada || !this.zona.district_id) return;
            this.activandoZona = true;
            try {
                const { data } = await axios.post(
                    `${this.baseUrl}/campanas/${this.campanaSeleccionada.id}/activar-por-zona`,
                    this.zona,
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                this.modal.zona = false;
                alert(`Campaña activada: ${data.insertados} clientes cargados.`);
                await this.cargar();
                await this.cargarKpis();
            } catch (e) {
                alert(e.response?.data?.error || 'Error al activar por zona.');
            } finally {
                this.activandoZona = false;
            }
        },
        async crearCampana() {
            this.creando      = true;
            this.erroresNueva = {};
            try {
                await axios.post(`${this.baseUrl}/campanas`, this.nueva, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.modal.nueva = false;
                await this.cargar();
                await this.cargarKpis();
            } catch (e) {
                if (e.response?.status === 422) this.erroresNueva = e.response.data.errors || {};
            } finally {
                this.creando = false;
            }
        },
        async activar(campana) {
            if (!confirm(`¿Activar campaña "${campana.nombre}"? Se cargará la lista de morosos.`)) return;
            this.procesando = campana.id;
            try {
                await axios.post(`${this.baseUrl}/campanas/${campana.id}/activar`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.cargar();
                await this.cargarKpis();
            } catch (e) {
                alert(e.response?.data?.error || 'Error al activar.');
            } finally {
                this.procesando = null;
            }
        },
        async pausar(campana) {
            this.procesando = campana.id;
            try {
                await axios.post(`${this.baseUrl}/campanas/${campana.id}/pausar`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.cargar();
            } catch (e) {
                alert(e.response?.data?.error || 'Error al pausar.');
            } finally {
                this.procesando = null;
            }
        },
        async eliminar(campana) {
            if (!confirm(`¿Eliminar "${campana.nombre}"?`)) return;
            try {
                await axios.delete(`${this.baseUrl}/campanas/${campana.id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.cargar();
            } catch (e) {
                alert(e.response?.data?.error || 'Error al eliminar.');
            }
        },
        async verLlamadas(campana) {
            this.campanaSeleccionada  = campana;
            this.filtroEstadoLlamadas = '';
            this.modal.llamadas       = true;
            await this.cargarLlamadas();
        },
        async cargarLlamadas() {
            if (!this.campanaSeleccionada) return;
            this.cargandoLlamadas = true;
            try {
                const { data } = await axios.get(
                    `${this.baseUrl}/campanas/${this.campanaSeleccionada.id}/llamadas`,
                    { params: { estado: this.filtroEstadoLlamadas } }
                );
                this.llamadas = data.data;
            } catch (e) {
                console.error(e);
            } finally {
                this.cargandoLlamadas = false;
            }
        },
        estadoBadge(e) {
            return { activa: 'bg-success', pausada: 'bg-warning text-dark',
                     borrador: 'bg-secondary', completada: 'bg-info' }[e] || 'bg-light text-dark';
        },
        llamadaBadge(e) {
            return { pendiente: 'bg-warning text-dark', marcando: 'bg-primary', contestada: 'bg-success',
                     no_contesto: 'bg-secondary', ocupado: 'bg-secondary', fallida: 'bg-danger',
                     pagada: 'bg-info', excluida: 'bg-light text-dark' }[e] || 'bg-light text-dark';
        },
        formatFecha(d) {
            if (!d) return '—';
            return new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
        },
        formatFechaHora(d) {
            if (!d) return '—';
            return new Date(d).toLocaleString('es-MX', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
</style>
