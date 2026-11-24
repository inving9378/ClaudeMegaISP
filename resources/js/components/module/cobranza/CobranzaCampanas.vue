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
            <h5 class="mb-0"><i class="fa fa-broadcast-tower me-2 text-primary"></i>Campañas/Blaster</h5>
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
                            <div class="text-muted small text-truncate nota-lista" :title="c.notas">{{ c.notas }}</div>
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
            <div class="modal-box card p-4" style="max-width:640px;width:100%;max-height:90vh;overflow-y:auto;">
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
                    <div class="col-12">
                        <label class="form-label small">
                            Nota — es el mensaje que escucha el cliente{{ nueva.tipo !== 'cobranza' ? ' *' : '' }}
                        </label>
                        <textarea class="form-control form-control-sm" v-model="nueva.notas" rows="4" maxlength="2000"
                                  :class="{'is-invalid': erroresNueva.notas}"
                                  :placeholder="nueva.tipo === 'cobranza'
                                      ? 'Vacía = el mensaje de cobranza de siempre'
                                      : 'Ej: Le informamos que el día... habrá un corte programado de servicio en su zona...'"></textarea>
                        <div class="invalid-feedback">{{ primerError('notas') }}</div>
                        <div class="form-text small" v-if="nueva.tipo === 'cobranza'">
                            Puedes usar <code>{nombre}</code>, <code>{saldo}</code>, <code>{fecha}</code> y
                            <code>{numero_cliente}</code>: en cada llamada se cambian por los datos del cliente.
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="voz-panel">
                            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                <span class="small fw-600"><i class="fa fa-microphone me-1 text-primary"></i>Voz de la llamada</span>
                                <button type="button" class="btn btn-sm"
                                        :class="reproduciendo ? 'btn-outline-danger' : 'btn-outline-primary'"
                                        :disabled="!reproduciendo && (generandoMuestra || !vozOpciones.asignada || !textoNota)"
                                        @click="reproduciendo ? $refs.muestra.pause() : escucharNota()">
                                    <i class="fa me-1" :class="reproduciendo ? 'fa-stop' : (generandoMuestra ? 'fa-spinner fa-spin' : 'fa-play')"></i>
                                    {{ reproduciendo ? 'Detener' : (generandoMuestra ? 'Generando…' : 'Escuchar') }}
                                </button>
                            </div>
                            <div v-if="vozCargada && !vozOpciones.asignada" class="alert alert-warning py-1 px-2 small mb-2">
                                {{ vozOpciones.motivo || 'No hay voz asignada en Integraciones → Módulos IA.' }}
                            </div>
                            <div v-if="errorMuestra" class="alert alert-danger py-1 px-2 small mb-2">{{ errorMuestra }}</div>
                            <!-- La voz generada queda en un reproductor: se vuelve a oír sin generarla otra vez, y si el
                                 navegador no deja que suene solo, aquí se le da ▶. -->
                            <audio ref="muestra" class="voz-muestra mb-2" controls preload="auto" v-show="urlMuestra"
                                   @play="reproduciendo = !!urlMuestra" @pause="reproduciendo = false" @ended="reproduciendo = false"></audio>
                            <div v-if="avisoAutoplay" class="small text-muted mb-2">
                                Tu navegador no dejó que sonara solo: dale <i class="fa fa-play"></i> en el reproductor.
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Voz</label>
                                    <select class="form-select form-select-sm" v-model="nueva.voz">
                                        <option v-for="v in vozOpciones.voces" :key="v.id" :value="v.id">{{ v.etiqueta }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Velocidad: <strong>{{ velocidadEtiqueta }}</strong></label>
                                    <input type="range" class="form-range" step="0.05" v-model.number="nueva.voz_velocidad"
                                           :min="vozOpciones.velocidad.min" :max="vozOpciones.velocidad.max">
                                    <div class="d-flex justify-content-between small text-muted"><span>Lenta</span><span>Rápida</span></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Tono</label>
                                    <select class="form-select form-select-sm" v-model="nueva.voz_tono" :disabled="!vozOpciones.soporta_tono">
                                        <option value="">Natural</option>
                                        <option v-for="t in vozOpciones.tonos" :key="t.id" :value="t.id">{{ t.etiqueta }}</option>
                                    </select>
                                    <div class="form-text small" v-if="vozCargada && !vozOpciones.soporta_tono">
                                        Se activa con el modelo gpt-4o-mini-tts en Integraciones → Módulos IA.
                                    </div>
                                </div>
                            </div>
                            <div class="form-text small" v-if="nueva.tipo === 'cobranza'">
                                Se escucha con un cliente de ejemplo: Juan Pérez, saldo de $350.00.
                            </div>
                            <div class="text-danger small" v-for="campo in ['voz', 'voz_velocidad', 'voz_tono']" :key="campo">
                                {{ primerError(campo) }}
                            </div>
                        </div>
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
// La nota es el mensaje que escucha el cliente; en cobranza arranca con el de siempre.
function nuevaCampana(opcionesVoz) {
    return { nombre: '', tipo: 'cobranza', max_canales_simultaneos: null,
             fecha_inicio: '', fecha_fin: '', hora_inicio: '09:00', hora_fin: '20:00',
             max_intentos: 3, minutos_entre_intentos: 180, dias_vencimiento: 1,
             notas: opcionesVoz.plantilla || '',
             voz: opcionesVoz.voz || 'nova', voz_velocidad: opcionesVoz.velocidad.defecto, voz_tono: '' };
}

// 10 ms de silencio: el play que se da dentro del clic, antes de que llegue la voz (ver escucharNota).
const SILENCIO = 'data:audio/wav;base64,UklGRnQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVAAAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgA==';

const VOZ_INICIAL = { asignada: false, motivo: '', soporta_tono: false, voces: [], tonos: [], voz: 'nova',
                      velocidad: { defecto: 0.95, min: 0.5, max: 1.5 }, plantilla: '' };

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
            nueva:       nuevaCampana(VOZ_INICIAL),
            erroresNueva:        {},
            // Voz de la campaña: opciones del servidor y la muestra que se está escuchando
            vozOpciones:         { ...VOZ_INICIAL },
            vozCargada:          false,
            generandoMuestra:    false,
            reproduciendo:       false,
            errorMuestra:        '',
            avisoAutoplay:       false,
            urlMuestra:          null,
            creando:             false,
            campanaSeleccionada: null,
            llamadas:            [],
            cargandoLlamadas:    false,
            filtroEstadoLlamadas:'',
            estadosLlamada:      ['pendiente','marcando','contestada','no_contesto','ocupado','fallida','pagada','excluida'],
            // MegaVoz Fase 7 — distrito/zona/caja del "activar por zona"
            // (aviso/anuncio/corte).
            distritos:         [],
            zonasDelDistrito:  [],
            cajasDeZona:       [],
            zona:              { district_id: null, zone_id: null, box_id: null },
            activandoZona:     false,
        };
    },
    computed: {
        // Lo que se va a leer: en cobranza, nota vacía = el mensaje de siempre
        textoNota() {
            const nota = (this.nueva.notas || '').trim();
            return nota || (this.nueva.tipo === 'cobranza' ? this.vozOpciones.plantilla : '');
        },
        velocidadEtiqueta() {
            return `${Number(this.nueva.voz_velocidad || 1).toFixed(2)}×`;
        },
    },
    watch: {
        'nueva.tipo'(tipo) {
            const plantilla = this.vozOpciones.plantilla;
            if (tipo === 'cobranza' && !(this.nueva.notas || '').trim()) this.nueva.notas = plantilla;
            if (tipo !== 'cobranza' && plantilla && this.nueva.notas === plantilla) this.nueva.notas = '';
        },
        'modal.nueva'(abierto) {
            if (!abierto) this.detenerMuestra();
        },
    },
    mounted() {
        this.cargar();
        this.cargarKpis();
    },
    beforeUnmount() {
        this.detenerMuestra();
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
        async abrirNueva() {
            this.nueva         = nuevaCampana(this.vozOpciones);
            this.erroresNueva  = {};
            this.errorMuestra  = '';
            this.modal.nueva   = true;
            if (!this.vozCargada) await this.cargarVoz();
        },
        async cargarVoz() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/voz`);
                this.vozOpciones = { ...VOZ_INICIAL, ...data };
                this.vozCargada  = true;
                // Si el modal ya está abierto y no lo han tocado, se llena con lo del servidor
                if (this.nueva.tipo === 'cobranza' && !(this.nueva.notas || '').trim()) this.nueva.notas = data.plantilla || '';
                if (!this.vozOpciones.voces.some(v => v.id === this.nueva.voz)) this.nueva.voz = this.vozOpciones.voz;
                if (!this.vozOpciones.soporta_tono) this.nueva.voz_tono = '';
            } catch (e) {
                console.error(e);
                this.vozOpciones = { ...VOZ_INICIAL, motivo: 'No se pudieron cargar las voces.' };
                this.vozCargada  = true;
            }
        },
        primerError(campo) {
            const e = this.erroresNueva[campo];
            return Array.isArray(e) ? e[0] : (e || '');
        },
        async escucharNota() {
            this.detenerMuestra();
            this.errorMuestra     = '';
            this.avisoAutoplay    = false;
            this.generandoMuestra = true;
            // Generar la voz tarda unos segundos y, para entonces, Safari, Firefox o Chrome con el
            // sonido bloqueado ya no lo cuentan como parte del clic y no dejan reproducir. Se le da
            // play al reproductor aquí, todavía dentro del clic (con un silencio), y luego se le cambia
            // el audio: el navegador ya lo dio por permitido.
            const audio = this.$refs.muestra;
            if (audio) {
                audio.src = SILENCIO;
                audio.play().catch(() => {});
            }
            try {
                const { data } = await axios.post(`${this.baseUrl}/campanas/escuchar`, {
                    notas:         this.textoNota,
                    tipo:          this.nueva.tipo,
                    fecha_fin:     this.nueva.fecha_fin || null,
                    voz:           this.nueva.voz,
                    voz_velocidad: this.nueva.voz_velocidad,
                    voz_tono:      this.nueva.voz_tono || null,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken }, responseType: 'blob' });
                if (!(data instanceof Blob) || !data.size) throw new Error('sin audio');
                this.urlMuestra = URL.createObjectURL(data);
                if (!audio) return;
                audio.src = this.urlMuestra;
                try {
                    await audio.play();
                } catch (e) {
                    // Bloqueado aun así: queda el reproductor a la vista para darle play a mano.
                    if (e && e.name === 'NotAllowedError') this.avisoAutoplay = true;
                    else throw e;
                }
            } catch (e) {
                this.detenerMuestra();
                this.errorMuestra = await this.errorDeMuestra(e);
            } finally {
                this.generandoMuestra = false;
            }
        },
        detenerMuestra() {
            const audio = this.$refs.muestra;
            if (audio) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
            }
            if (this.urlMuestra) {
                URL.revokeObjectURL(this.urlMuestra);
                this.urlMuestra = null;
            }
            this.reproduciendo = false;
            this.avisoAutoplay = false;
        },
        // La respuesta de error llega como Blob (se pidió audio); se lee el JSON de adentro
        async errorDeMuestra(e) {
            if (e.response?.status === 429) return 'Muchas pruebas seguidas: espera un minuto y vuelve a escuchar.';
            if (!e.response) return 'El navegador no pudo reproducir la voz. Revisa que el sonido de esta página no esté bloqueado e inténtalo otra vez.';
            try {
                const json = JSON.parse(await e.response.data.text());
                const primero = json.errors ? Object.values(json.errors)[0] : null;
                return json.error || (Array.isArray(primero) ? primero[0] : '') || json.message || 'No se pudo generar la voz.';
            } catch (_) {
                return 'No se pudo reproducir la voz.';
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
                await axios.post(`${this.baseUrl}/campanas`, { ...this.nueva, voz_tono: this.nueva.voz_tono || null }, {
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
.nota-lista { max-width: 360px; }
.voz-muestra { width: 100%; height: 36px; display: block; }
.voz-panel { border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .5rem; padding: .75rem; }
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
</style>
