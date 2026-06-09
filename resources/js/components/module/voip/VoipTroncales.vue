<template>
    <div class="voip-troncales">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="fa fa-sitemap me-2 text-primary"></i>Troncales VoIP (PJSIP)
            </h4>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" @click="probarConexion" :disabled="probando">
                    <i class="fa fa-plug me-1"></i>
                    {{ probando ? 'Probando…' : 'Probar conexión ARI' }}
                </button>
                <button v-if="canCreate" class="btn btn-primary btn-sm" @click="abrirModal()">
                    <i class="fa fa-plus me-1"></i> Nueva troncal
                </button>
            </div>
        </div>

        <!-- Resultado test conexión -->
        <div v-if="testConexion" class="alert mb-3"
             :class="testConexion.ok ? 'alert-success' : 'alert-danger'" style="font-size:0.85rem">
            <i :class="testConexion.ok ? 'fa fa-check-circle' : 'fa fa-times-circle'" class="me-1"></i>
            <span v-if="testConexion.ok">
                Asterisk ARI responde — versión {{ testConexion.version ?? 'N/D' }}
            </span>
            <span v-else>No se pudo conectar a ARI: {{ testConexion.error }}</span>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-body p-0">
                <div v-if="cargando" class="text-center py-5 text-muted">
                    <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
                </div>
                <div v-else-if="troncales.length === 0" class="text-center py-5 text-muted">
                    <i class="fa fa-phone-slash fa-2x mb-2 d-block"></i>
                    Aún no hay troncales configuradas.<br>
                    <small>Crea la primera con el botón <strong>Nueva troncal</strong>.</small>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Proveedor</th>
                                <th>Tipo</th>
                                <th>Dirección</th>
                                <th>Host</th>
                                <th>Estado</th>
                                <th class="text-center">Activa</th>
                                <th class="text-end" style="min-width:190px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in troncales" :key="t.id">
                                <td class="fw-medium">{{ t.nombre }}</td>
                                <td class="text-muted">{{ t.proveedor || '—' }}</td>
                                <td>
                                    <span class="badge" :class="t.tipo === 'registro' ? 'bg-info' : 'bg-secondary'">
                                        {{ t.tipo }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge"
                                          :class="{
                                              'bg-success': t.direccion === 'entrante',
                                              'bg-warning text-dark': t.direccion === 'saliente',
                                              'bg-primary': t.direccion === 'ambas'
                                          }">
                                        {{ t.direccion }}
                                    </span>
                                </td>
                                <td><code>{{ t.host }}:{{ t.puerto }}</code></td>
                                <td>
                                    <span v-if="t.provisionado_at" class="text-success">
                                        <i class="fa fa-circle-dot me-1"></i>
                                        Provisionada
                                    </span>
                                    <span v-else class="text-warning">
                                        <i class="fa fa-circle-notch me-1"></i>
                                        Sin provisionar
                                    </span>
                                    <!-- Resultado de verificación inline -->
                                    <span v-if="verificaciones[t.id]" class="ms-2">
                                        <span v-if="verificaciones[t.id].ok"
                                              class="badge bg-success-subtle text-success">
                                            {{ verificaciones[t.id].estado }}
                                        </span>
                                        <span v-else class="badge bg-danger-subtle text-danger">
                                            {{ verificaciones[t.id].estado || 'error' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <i :class="t.activo ? 'fa fa-toggle-on text-success' : 'fa fa-toggle-off text-secondary'" class="fa-lg"></i>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button v-if="canProvision"
                                                class="btn btn-outline-primary"
                                                :disabled="provisionando === t.id"
                                                @click="provisionar(t)"
                                                title="Provisionar en Asterisk">
                                            <i class="fa fa-upload"></i>
                                        </button>
                                        <button v-if="canTest"
                                                class="btn btn-outline-secondary"
                                                :disabled="verificando === t.id"
                                                @click="verificar(t)"
                                                title="Verificar estado en Asterisk">
                                            <i class="fa fa-magnifying-glass"></i>
                                        </button>
                                        <button v-if="canEdit"
                                                class="btn btn-outline-secondary"
                                                @click="abrirModal(t)"
                                                title="Editar">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <button v-if="canDelete"
                                                class="btn btn-outline-danger"
                                                @click="confirmarEliminar(t)"
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
        <div class="modal fade" id="modalTroncal" tabindex="-1" ref="modalEl">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa fa-sitemap me-2"></i>
                            {{ editando ? 'Editar troncal' : 'Nueva troncal' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Nombre <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" v-model="form.nombre"
                                       placeholder="ej. Servnet-Saliente"
                                       :class="{'is-invalid': errores.nombre}">
                                <div class="invalid-feedback">{{ errores.nombre }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Proveedor</label>
                                <input class="form-control form-control-sm" v-model="form.proveedor"
                                       placeholder="ej. Servnet, Vonage…">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" v-model="form.tipo"
                                        :class="{'is-invalid': errores.tipo}">
                                    <option value="">— Seleccionar —</option>
                                    <option value="registro">Registro SIP (usuario + secret)</option>
                                    <option value="ip">IP fija (sin credenciales)</option>
                                </select>
                                <div class="invalid-feedback">{{ errores.tipo }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Dirección <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" v-model="form.direccion"
                                        :class="{'is-invalid': errores.direccion}">
                                    <option value="">— Seleccionar —</option>
                                    <option value="entrante">Entrante</option>
                                    <option value="saliente">Saliente</option>
                                    <option value="ambas">Ambas</option>
                                </select>
                                <div class="invalid-feedback">{{ errores.direccion }}</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small">Host / IP <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" v-model="form.host"
                                       placeholder="sip.proveedor.com"
                                       :class="{'is-invalid': errores.host}">
                                <div class="invalid-feedback">{{ errores.host }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Puerto</label>
                                <input class="form-control form-control-sm" v-model.number="form.puerto"
                                       type="number" placeholder="5060">
                            </div>

                            <template v-if="form.tipo === 'registro'">
                                <div class="col-md-6">
                                    <label class="form-label small">Usuario SIP</label>
                                    <input class="form-control form-control-sm" v-model="form.usuario"
                                           autocomplete="off" placeholder="usuario@proveedor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">
                                        Secret / Password
                                        <small v-if="editando" class="text-muted">(dejar vacío = sin cambio)</small>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" v-model="form.secret"
                                               :type="mostrarSecret ? 'text' : 'password'"
                                               autocomplete="new-password"
                                               :class="{'is-invalid': errores.secret}">
                                        <button class="btn btn-outline-secondary" type="button"
                                                @click="mostrarSecret = !mostrarSecret">
                                            <i :class="mostrarSecret ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
                                        </button>
                                        <div class="invalid-feedback">{{ errores.secret }}</div>
                                    </div>
                                </div>
                            </template>

                            <div class="col-md-6">
                                <label class="form-label small">Contexto Asterisk</label>
                                <input class="form-control form-control-sm" v-model="form.contexto"
                                       placeholder="from-trunk">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">DID (entrante)</label>
                                <input class="form-control form-control-sm" v-model="form.did"
                                       placeholder="ej. 5551234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Codecs</label>
                                <input class="form-control form-control-sm" v-model="form.codecs"
                                       placeholder="ulaw,alaw">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Transporte PJSIP</label>
                                <input class="form-control form-control-sm" v-model="form.transporte"
                                       placeholder="transport-udp">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" v-model="form.activo" id="chkActivo">
                                    <label class="form-check-label" for="chkActivo">Troncal activa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="guardar" :disabled="guardando">
                            <i class="fa fa-save me-1"></i>
                            {{ guardando ? 'Guardando…' : (editando ? 'Actualizar' : 'Crear troncal') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar -->
        <div class="modal fade" id="modalEliminar" tabindex="-1" ref="modalEliminarEl">
            <div class="modal-dialog">
                <div class="modal-content border-danger">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fa fa-trash me-2"></i>Eliminar troncal</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" v-if="troncalAEliminar">
                        <p>¿Eliminar la troncal <strong>{{ troncalAEliminar.nombre }}</strong>?</p>
                        <p v-if="troncalAEliminar.provisionado_at" class="text-warning small">
                            <i class="fa fa-triangle-exclamation me-1"></i>
                            Esta troncal está provisionada. Se desprovisionará automáticamente de Asterisk.
                        </p>
                        <p class="text-danger small">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger btn-sm"
                                @click="ejecutarEliminar" :disabled="eliminando">
                            <i class="fa fa-trash me-1"></i>
                            {{ eliminando ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:11000">
            <div ref="toastEl" class="toast align-items-center text-bg-success border-0" role="alert"
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
    name: 'VoipTroncales',

    props: {
        csrfToken: { type: String, required: true },
        baseUrl:   { type: String, required: true },
    },

    data() {
        return {
            troncales:       [],
            cargando:        false,
            guardando:       false,
            eliminando:      false,
            probando:        false,
            provisionando:   null,
            verificando:     null,
            editando:        null,
            troncalAEliminar:null,
            mostrarSecret:   false,
            testConexion:    null,
            verificaciones:  {},
            toastMsg:        '',
            toastTipo:       'success',
            form: this.formVacio(),
            errores: {},

            canCreate:    false,
            canEdit:      false,
            canDelete:    false,
            canProvision: false,
            canTest:      false,
        };
    },

    mounted() {
        this.resolverPermisos();
        this.cargar();
    },

    methods: {
        formVacio() {
            return {
                nombre:    '',
                proveedor: '',
                tipo:      'registro',
                direccion: 'saliente',
                host:      '',
                puerto:    5060,
                usuario:   '',
                secret:    '',
                contexto:  'from-trunk',
                did:       '',
                codecs:    'ulaw,alaw',
                transporte:'transport-udp',
                activo:    true,
            };
        },

        resolverPermisos() {
            const store = window.__megaVueApp?._context?.config?.globalProperties?.$store;
            if (!store) return;
            const perms = store.getters?.['permissions'] ?? [];
            const has   = (p) => perms.includes(p);
            this.canCreate    = has('voip.troncales.create');
            this.canEdit      = has('voip.troncales.edit');
            this.canDelete    = has('voip.troncales.delete');
            this.canProvision = has('voip.troncales.provision');
            this.canTest      = has('voip.troncales.test');
        },

        async cargar() {
            this.cargando = true;
            try {
                const r = await fetch(`${this.baseUrl}/troncales/data`, { headers: this.headers() });
                if (r.ok) this.troncales = await r.json();
            } finally {
                this.cargando = false;
            }
        },

        abrirModal(troncal = null) {
            this.errores       = {};
            this.mostrarSecret = false;
            if (troncal) {
                this.editando = troncal;
                this.form = {
                    nombre:    troncal.nombre,
                    proveedor: troncal.proveedor || '',
                    tipo:      troncal.tipo,
                    direccion: troncal.direccion,
                    host:      troncal.host,
                    puerto:    troncal.puerto,
                    usuario:   troncal.usuario || '',
                    secret:    '',
                    contexto:  troncal.contexto,
                    did:       troncal.did || '',
                    codecs:    troncal.codecs,
                    transporte:troncal.transporte,
                    activo:    troncal.activo,
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
                ? `${this.baseUrl}/troncales/${this.editando.id}`
                : `${this.baseUrl}/troncales`;
            const method = this.editando ? 'PUT' : 'POST';

            try {
                const r = await fetch(url, {
                    method,
                    headers: { ...this.headers(), 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const body = await r.json();

                if (r.status === 422) {
                    this.errores = body.errors ?? {};
                    return;
                }
                if (!r.ok) {
                    this.toast('Error: ' + (body.error ?? r.statusText), 'error');
                    return;
                }

                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl).hide();
                this.toast(this.editando ? 'Troncal actualizada' : 'Troncal creada');
                await this.cargar();
            } finally {
                this.guardando = false;
            }
        },

        confirmarEliminar(t) {
            this.troncalAEliminar = t;
            bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).show();
        },

        async ejecutarEliminar() {
            if (!this.troncalAEliminar) return;
            this.eliminando = true;
            try {
                const r = await fetch(`${this.baseUrl}/troncales/${this.troncalAEliminar.id}`, {
                    method:  'DELETE',
                    headers: this.headers(),
                });
                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).hide();
                if (r.ok) {
                    this.toast('Troncal eliminada');
                    await this.cargar();
                } else {
                    this.toast('Error al eliminar', 'error');
                }
            } finally {
                this.eliminando = false;
            }
        },

        async provisionar(t) {
            this.provisionando = t.id;
            try {
                const r    = await fetch(`${this.baseUrl}/troncales/${t.id}/provisionar`, {
                    method:  'POST',
                    headers: this.headers(),
                });
                const body = await r.json();
                if (body.ok) {
                    this.toast(`Troncal "${t.nombre}" provisionada en Asterisk`);
                    await this.cargar();
                } else {
                    this.toast('Error: ' + (body.error ?? 'Sin detalle'), 'error');
                }
            } finally {
                this.provisionando = null;
            }
        },

        async verificar(t) {
            this.verificando = t.id;
            try {
                const r    = await fetch(`${this.baseUrl}/troncales/${t.id}/verificar`, { headers: this.headers() });
                const body = await r.json();
                this.verificaciones = { ...this.verificaciones, [t.id]: body };
            } finally {
                this.verificando = null;
            }
        },

        async probarConexion() {
            this.probando    = true;
            this.testConexion = null;
            try {
                const r = await fetch(`${this.baseUrl}/probar-conexion`, { headers: this.headers() });
                this.testConexion = await r.json();
            } finally {
                this.probando = false;
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
