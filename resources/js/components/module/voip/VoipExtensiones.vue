<template>
    <div class="voip-extensiones">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="fa fa-phone me-2 text-primary"></i>Extensiones VoIP (PJSIP)
            </h4>
            <button v-if="canCreate" class="btn btn-primary btn-sm" @click="abrirModal()">
                <i class="fa fa-plus me-1"></i> Nueva extensión
            </button>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-body p-0">
                <div v-if="cargando" class="text-center py-5 text-muted">
                    <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
                </div>
                <div v-else-if="extensiones.length === 0" class="text-center py-5 text-muted">
                    <i class="fa fa-phone-slash fa-2x mb-2 d-block"></i>
                    Aún no hay extensiones.<br>
                    <small>Crea la primera con el botón <strong>Nueva extensión</strong>.</small>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Número</th>
                                <th>Nombre</th>
                                <th>Agente</th>
                                <th>Tipo</th>
                                <th>Equipo</th>
                                <th>Estado</th>
                                <th class="text-center">Activa</th>
                                <th class="text-end" style="min-width:180px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in extensiones" :key="e.id">
                                <td class="fw-medium"><code>{{ e.numero }}</code></td>
                                <td>{{ e.nombre }}</td>
                                <td class="text-muted">{{ e.agente_nombre || '—' }}</td>
                                <td>
                                    <span class="badge" :class="e.tipo_dispositivo === 'softphone' ? 'bg-info' : 'bg-secondary'">
                                        {{ e.tipo_dispositivo === 'softphone' ? 'Softphone' : 'Teléfono IP' }}
                                    </span>
                                </td>
                                <td style="white-space:nowrap">
                                    <template v-if="equipos[e.numero]">
                                        <template v-if="equipos[e.numero].tipo === 'fisico'">
                                            <i class="fa fa-phone text-secondary me-1"
                                               :title="equipos[e.numero].user_agent"></i>
                                            <span :title="equipos[e.numero].user_agent" style="cursor:default">
                                                {{ equipos[e.numero].modelo }}
                                            </span>
                                        </template>
                                        <template v-else-if="equipos[e.numero].tipo === 'softphone'">
                                            <i class="fa fa-laptop text-info me-1"
                                               :title="equipos[e.numero].user_agent"></i>
                                            <span :title="equipos[e.numero].user_agent"
                                                  class="text-info" style="cursor:default">
                                                {{ equipos[e.numero].modelo }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <i class="fa fa-question-circle text-muted me-1"
                                               :title="equipos[e.numero].user_agent"></i>
                                            <span :title="equipos[e.numero].user_agent"
                                                  class="text-muted small" style="cursor:default">
                                                Desconocido
                                            </span>
                                        </template>
                                    </template>
                                    <span v-else class="text-muted small">
                                        <i class="fa fa-circle-xmark me-1"></i>Sin registrar
                                    </span>
                                </td>
                                <td>
                                    <span v-if="e.provisionado_at" class="text-success">
                                        <i class="fa fa-circle-dot me-1"></i>Provisionada
                                    </span>
                                    <span v-else class="text-warning">
                                        <i class="fa fa-circle-notch me-1"></i>Sin provisionar
                                    </span>
                                    <span v-if="e.provisionado_at" class="ms-2">
                                        <span v-if="conectadas.includes(e.numero)" class="badge bg-success text-white">
                                            <i class="fa fa-wifi me-1"></i>Conectada
                                        </span>
                                        <span v-else class="badge bg-secondary text-white">
                                            Desconectada
                                        </span>
                                    </span>
                                    <span v-if="verificaciones[e.id]" class="ms-2">
                                        <span v-if="verificaciones[e.id].ok" class="badge bg-success-subtle text-success">
                                            {{ verificaciones[e.id].estado }}
                                        </span>
                                        <span v-else class="badge bg-danger-subtle text-danger">
                                            {{ verificaciones[e.id].estado || 'error' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <i v-if="canEdit"
                                       :class="e.activo ? 'fa fa-toggle-on text-success' : 'fa fa-toggle-off text-secondary'"
                                       class="fa-lg"
                                       style="cursor:pointer"
                                       :title="e.activo ? 'Desactivar' : 'Activar'"
                                       @click="toggleActiva(e)"></i>
                                    <i v-else
                                       :class="e.activo ? 'fa fa-toggle-on text-success' : 'fa fa-toggle-off text-secondary'"
                                       class="fa-lg"></i>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button v-if="canProvision"
                                                class="btn btn-outline-primary"
                                                :disabled="provisionando === e.id"
                                                @click="provisionar(e)"
                                                title="Provisionar en Asterisk">
                                            <i class="fa fa-upload"></i>
                                        </button>
                                        <button v-if="canTest"
                                                class="btn btn-outline-secondary"
                                                :disabled="verificando === e.id"
                                                @click="verificar(e)"
                                                title="Verificar en Asterisk">
                                            <i class="fa fa-magnifying-glass"></i>
                                        </button>
                                        <button v-if="canEdit"
                                                class="btn btn-outline-secondary"
                                                @click="abrirModal(e)"
                                                title="Editar">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <button v-if="canDelete"
                                                class="btn btn-outline-danger"
                                                @click="confirmarEliminar(e)"
                                                title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <div class="modal fade" id="modalExtension" tabindex="-1" ref="modalEl">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa fa-phone me-2"></i>
                            {{ editando ? 'Editar extensión' : 'Nueva extensión' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Número <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" v-model="form.numero"
                                       placeholder="ej. 1001"
                                       :class="{'is-invalid': errores.numero}">
                                <div class="invalid-feedback">{{ errores.numero }}</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small">Nombre / descripción <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" v-model="form.nombre"
                                       placeholder="ej. Diana Recepción"
                                       :class="{'is-invalid': errores.nombre}">
                                <div class="invalid-feedback">{{ errores.nombre }}</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small">
                                    Secret / Password <span class="text-danger">*</span>
                                    <small v-if="editando" class="text-muted">(dejar vacío = sin cambio)</small>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" v-model="form.secret"
                                           :type="mostrarSecret ? 'text' : 'password'"
                                           autocomplete="new-password"
                                           :class="{'is-invalid': errores.secret}">
                                    <button class="btn btn-outline-secondary" type="button"
                                            @click="mostrarSecret = !mostrarSecret"
                                            title="Mostrar/ocultar">
                                        <i :class="mostrarSecret ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button"
                                            @click="generarSecret"
                                            title="Generar automáticamente">
                                        <i class="fa fa-rotate-right"></i>
                                    </button>
                                    <div class="invalid-feedback">{{ errores.secret }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Agente (usuario del sistema)</label>
                                <select class="form-select form-select-sm" v-model="form.user_id">
                                    <option :value="null">— Sin asignar —</option>
                                    <option v-for="u in usuarios" :key="u.id" :value="u.id">
                                        {{ u.nombre }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small">Tipo de dispositivo</label>
                                <select class="form-select form-select-sm" v-model="form.tipo_dispositivo">
                                    <option value="softphone">Softphone</option>
                                    <option value="telefono_ip">Teléfono IP</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">CallerID (opcional)</label>
                                <input class="form-control form-control-sm" v-model="form.callerid"
                                       placeholder='ej. "Diana" <1001>'>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small">Contexto Asterisk</label>
                                <input class="form-control form-control-sm" v-model="form.contexto"
                                       placeholder="from-internal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Codecs</label>
                                <input class="form-control form-control-sm" v-model="form.codecs"
                                       placeholder="ulaw,alaw">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Transporte PJSIP</label>
                                <input class="form-control form-control-sm" v-model="form.transporte"
                                       placeholder="transport-udp">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" v-model="form.activo" id="chkActivoExt">
                                    <label class="form-check-label" for="chkActivoExt">Extensión activa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="guardar" :disabled="guardando">
                            <i class="fa fa-save me-1"></i>
                            {{ guardando ? 'Guardando…' : (editando ? 'Actualizar' : 'Crear extensión') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar -->
        <div class="modal fade" id="modalEliminarExt" tabindex="-1" ref="modalEliminarEl">
            <div class="modal-dialog">
                <div class="modal-content border-danger">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fa fa-trash me-2"></i>Eliminar extensión</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" v-if="extAEliminar">
                        <p>¿Eliminar la extensión <strong>{{ extAEliminar.numero }} — {{ extAEliminar.nombre }}</strong>?</p>
                        <p v-if="extAEliminar.provisionado_at" class="text-warning small">
                            <i class="fa fa-triangle-exclamation me-1"></i>
                            Se desprovisionará automáticamente de Asterisk.
                        </p>
                        <p class="text-danger small">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger btn-sm"
                                @click="ejecutarEliminar" :disabled="eliminando">
                            {{ eliminando ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:11000">
            <div ref="toastEl" class="toast align-items-center border-0" role="alert"
                 :class="toastTipo === 'error' ? 'text-bg-danger' : 'text-bg-success'">
                <div class="d-flex">
                    <div class="toast-body">{{ toastMsg }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'VoipExtensiones',

    props: {
        csrfToken: { type: String, required: true },
        baseUrl:   { type: String, required: true },
    },

    data() {
        return {
            extensiones:    [],
            usuarios:       [],
            cargando:       false,
            guardando:      false,
            eliminando:     false,
            provisionando:  null,
            verificando:    null,
            editando:       null,
            extAEliminar:   null,
            mostrarSecret:  false,
            verificaciones:  {},
            conectadas:      [],
            equipos:         {},
            intervaloEstados: null,
            toastMsg:        '',
            toastTipo:      'success',
            form:           this.formVacio(),
            errores:        {},
        };
    },

    computed: {
        canCreate()    { return this.$store?.getters?.hasPermission?.('voip.extensiones.create')    ?? false; },
        canEdit()      { return this.$store?.getters?.hasPermission?.('voip.extensiones.edit')      ?? false; },
        canDelete()    { return this.$store?.getters?.hasPermission?.('voip.extensiones.delete')    ?? false; },
        canProvision() { return this.$store?.getters?.hasPermission?.('voip.extensiones.provision') ?? false; },
        canTest()      { return this.$store?.getters?.hasPermission?.('voip.extensiones.test')      ?? false; },
    },

    mounted() {
        this.cargar();
        this.cargarUsuarios();
        this.cargarEstados();
        this.intervaloEstados = setInterval(() => this.cargarEstados(), 30000);
    },

    unmounted() {
        clearInterval(this.intervaloEstados);
    },

    methods: {
        generarSecret() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
            this.form.secret = Array.from({ length: 14 }, () =>
                chars[Math.floor(Math.random() * chars.length)]
            ).join('');
            this.mostrarSecret = true;
        },

        formVacio() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
            const secret = Array.from({ length: 14 }, () =>
                chars[Math.floor(Math.random() * chars.length)]
            ).join('');
            return {
                numero:          '',
                nombre:          '',
                secret,
                user_id:         null,
                tipo_dispositivo:'softphone',
                contexto:        'from-internal',
                codecs:          'ulaw,alaw',
                transporte:      'transport-udp',
                callerid:        '',
                activo:          true,
            };
        },

        async cargar() {
            this.cargando = true;
            try {
                const r = await fetch(`${this.baseUrl}/extensiones/data`, { headers: this.headers() });
                if (r.ok) this.extensiones = await r.json();
            } finally {
                this.cargando = false;
            }
        },

        async cargarUsuarios() {
            const r = await fetch(`${this.baseUrl}/extensiones/usuarios`, { headers: this.headers() });
            if (r.ok) this.usuarios = await r.json();
        },

        async cargarEstados() {
            try {
                const r    = await fetch(`${this.baseUrl}/extensiones/estados`, { headers: this.headers() });
                const body = await r.json();
                if (r.ok) {
                    this.conectadas = body.conectadas ?? [];
                    this.equipos    = body.equipos    ?? {};
                }
            } catch { /* silencioso — los badges simplemente no aparecen */ }
        },

        abrirModal(ext = null) {
            this.errores      = {};
            this.mostrarSecret = false;
            if (ext) {
                this.editando = ext;
                this.form = {
                    numero:          ext.numero,
                    nombre:          ext.nombre,
                    secret:          '',
                    user_id:         ext.user_id,
                    tipo_dispositivo:ext.tipo_dispositivo,
                    contexto:        ext.contexto,
                    codecs:          ext.codecs,
                    transporte:      ext.transporte,
                    callerid:        ext.callerid || '',
                    activo:          ext.activo,
                };
            } else {
                this.editando = null;
                this.form = this.formVacio();
            }
            bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl).show();
        },

        async guardar() {
            this.errores  = {};
            this.guardando = true;
            const url    = this.editando
                ? `${this.baseUrl}/extensiones/${this.editando.id}`
                : `${this.baseUrl}/extensiones`;
            const method = this.editando ? 'PUT' : 'POST';

            try {
                const r    = await fetch(url, {
                    method,
                    headers: { ...this.headers(), 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const body = await r.json();

                if (r.status === 422) { this.errores = body.errors ?? {}; return; }
                if (!r.ok)           { this.toast('Error: ' + (body.error ?? r.statusText), 'error'); return; }

                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl).hide();
                this.toast(this.editando ? 'Extensión actualizada' : 'Extensión creada');
                await this.cargar();
            } finally {
                this.guardando = false;
            }
        },

        confirmarEliminar(e) {
            this.extAEliminar = e;
            bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).show();
        },

        async ejecutarEliminar() {
            if (!this.extAEliminar) return;
            this.eliminando = true;
            try {
                const r = await fetch(`${this.baseUrl}/extensiones/${this.extAEliminar.id}`, {
                    method: 'DELETE', headers: this.headers(),
                });
                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).hide();
                if (r.ok) { this.toast('Extensión eliminada'); await this.cargar(); }
                else      { this.toast('Error al eliminar', 'error'); }
            } finally {
                this.eliminando = false;
            }
        },

        async toggleActiva(e) {
            const previo = e.activo;
            e.activo = !previo;  // feedback inmediato

            try {
                const r    = await fetch(`${this.baseUrl}/extensiones/${e.id}/toggle`, {
                    method: 'PATCH', headers: this.headers(),
                });
                const body = await r.json();
                if (!r.ok || !body.ok) {
                    e.activo = previo;  // rollback
                    this.toast('Error al cambiar estado', 'error');
                } else {
                    e.activo = body.activo;
                    this.toast(body.activo ? `Extensión ${e.numero} activada` : `Extensión ${e.numero} desactivada`);
                    if (body.warning) this.toast('Advertencia: ' + body.warning, 'error');
                }
            } catch {
                e.activo = previo;  // rollback si falla la red
                this.toast('Error de comunicación', 'error');
            }
        },

        async provisionar(e) {
            this.provisionando = e.id;
            try {
                const r    = await fetch(`${this.baseUrl}/extensiones/${e.id}/provisionar`, {
                    method: 'POST', headers: this.headers(),
                });
                const body = await r.json();
                if (body.ok) { this.toast(`Extensión ${e.numero} provisionada`); await this.cargar(); }
                else         { this.toast('Error: ' + (body.error ?? 'Sin detalle'), 'error'); }
            } finally {
                this.provisionando = null;
            }
        },

        async verificar(e) {
            this.verificando = e.id;
            try {
                const r    = await fetch(`${this.baseUrl}/extensiones/${e.id}/verificar`, { headers: this.headers() });
                const body = await r.json();
                this.verificaciones = { ...this.verificaciones, [e.id]: body };
            } finally {
                this.verificando = null;
            }
        },

        headers() {
            return { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' };
        },

        toast(msg, tipo = 'success') {
            this.toastMsg  = msg;
            this.toastTipo = tipo;
            bootstrap.Toast.getOrCreateInstance(this.$refs.toastEl, { delay: 3500 }).show();
        },
    },
};
</script>
