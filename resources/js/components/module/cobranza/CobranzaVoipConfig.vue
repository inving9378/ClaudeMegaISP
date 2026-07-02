<template>
    <div class="cobranza-voip-config">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fa fa-phone-square me-2 text-primary"></i>Configuración VoIP — Troncal SIP</h4>
            <span class="badge" :class="estadoBadge(config.estado)">
                {{ estadoLabel(config.estado) }}
            </span>
        </div>

        <div class="row g-3">
            <!-- Formulario -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header py-2">
                        <strong class="small">Credenciales Servnet</strong>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small">Host SIP</label>
                                <input class="form-control form-control-sm" v-model="form.sip_host"
                                       placeholder="sip.servnet.com.mx" :class="{'is-invalid': errors.sip_host}">
                                <div class="invalid-feedback">{{ errors.sip_host }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Puerto</label>
                                <input class="form-control form-control-sm" v-model.number="form.sip_port"
                                       type="number" placeholder="5060">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Usuario SIP</label>
                                <input class="form-control form-control-sm" v-model="form.sip_username"
                                       autocomplete="off" :class="{'is-invalid': errors.sip_username}">
                                <div class="invalid-feedback">{{ errors.sip_username }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Password SIP</label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" v-model="form.sip_secret"
                                           :type="mostrarSecret ? 'text' : 'password'"
                                           autocomplete="new-password"
                                           :placeholder="config.sip_secret === '••••••••' ? '(sin cambios)' : ''"
                                           :class="{'is-invalid': errors.sip_secret}">
                                    <button class="btn btn-outline-secondary" type="button"
                                            @click="mostrarSecret = !mostrarSecret">
                                        <i :class="mostrarSecret ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
                                    </button>
                                    <div class="invalid-feedback">{{ errors.sip_secret }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">From User (opcional)</label>
                                <input class="form-control form-control-sm" v-model="form.sip_fromuser"
                                       placeholder="DID asignado">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">From Domain (opcional)</label>
                                <input class="form-control form-control-sm" v-model="form.sip_fromdomain">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small">CallerID Nombre</label>
                                <input class="form-control form-control-sm" v-model="form.callerid_nombre"
                                       placeholder="Meganet Telecomunicaciones">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">CallerID Número</label>
                                <input class="form-control form-control-sm" v-model="form.callerid_numero"
                                       placeholder="5551234567">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button class="btn btn-primary btn-sm" @click="guardar" :disabled="guardando">
                                <i class="fa fa-save me-1"></i>
                                {{ guardando ? 'Aplicando…' : 'Guardar y aplicar' }}
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" @click="probarConexion" :disabled="probando">
                                <i class="fa fa-plug me-1"></i>
                                {{ probando ? 'Probando…' : 'Probar conexión' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de estado y test -->
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header py-2">
                        <strong class="small">Estado del registro SIP</strong>
                    </div>
                    <div class="card-body">
                        <div v-if="!testResult" class="text-muted small">
                            Guarda la configuración y presiona "Probar conexión" para verificar el registro con Servnet.
                        </div>
                        <div v-else>
                            <div class="mb-3">
                                <span v-if="testResult.registrado" class="badge bg-success fs-6">
                                    <i class="fa fa-check-circle me-1"></i> Registrado ✓
                                </span>
                                <span v-else class="badge bg-danger fs-6">
                                    <i class="fa fa-times-circle me-1"></i> Sin registro ✗
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Output de Asterisk</span>
                                <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-1"
                                        @click="mostrarOutput = !mostrarOutput">
                                    {{ mostrarOutput ? 'Ocultar' : 'Ver' }}
                                </button>
                            </div>
                            <pre v-if="mostrarOutput" class="bg-dark text-success p-2 rounded small"
                                 style="max-height:200px;overflow-y:auto;font-size:0.72rem;">{{ testResult.output }}</pre>
                        </div>

                        <div v-if="mensajeGuardado" class="alert alert-success py-2 small mt-3">
                            <i class="fa fa-check me-1"></i>{{ mensajeGuardado }}
                        </div>
                        <div v-if="errorGuardado" class="alert alert-danger py-2 small mt-3">
                            <i class="fa fa-exclamation-triangle me-1"></i>{{ errorGuardado }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CobranzaVoipConfig',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            config:         {},
            form:           { sip_host: '', sip_port: 5060, sip_username: '', sip_secret: '',
                              sip_fromuser: '', sip_fromdomain: '', callerid_nombre: 'Meganet Telecomunicaciones', callerid_numero: '' },
            errors:         {},
            guardando:      false,
            probando:       false,
            mostrarSecret:  false,
            mostrarOutput:  false,
            testResult:     null,
            mensajeGuardado: '',
            errorGuardado:  '',
        };
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/voip/configuracion`);
                if (data) {
                    this.config = data;
                    this.form   = { ...data, sip_secret: '' };
                }
            } catch (e) {
                console.error(e);
            }
        },
        async guardar() {
            this.guardando     = true;
            this.errors        = {};
            this.mensajeGuardado = '';
            this.errorGuardado   = '';

            // Si el campo secret está vacío y ya había uno guardado, no lo enviamos
            const payload = { ...this.form };
            if (!payload.sip_secret) delete payload.sip_secret;

            try {
                const { data } = await axios.post(`${this.baseUrl}/voip/configuracion`, payload, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (data && data.ok === false) {
                    // Fallo de infraestructura (llega como HTTP 200 {ok:false}): no es éxito.
                    this.errorGuardado = data.msg || 'No se pudo aplicar la configuración en Asterisk.';
                } else {
                    const avisos = (data && data.warnings && data.warnings.length)
                        ? '  ⚠ ' + data.warnings.join('  ⚠ ') : '';
                    this.mensajeGuardado = (data?.msg || 'Configuración guardada y aplicada en Asterisk.') + avisos;
                    await this.cargar();
                }
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                } else {
                    this.errorGuardado = e.response?.data?.message || 'Error al guardar.';
                }
            } finally {
                this.guardando = false;
            }
        },
        async probarConexion() {
            this.probando   = true;
            this.testResult = null;
            try {
                const { data } = await axios.get(`${this.baseUrl}/voip/test`);
                this.testResult    = data;
                this.mostrarOutput = false;
            } catch (e) {
                this.testResult = { registrado: false, output: e.message };
            } finally {
                this.probando = false;
            }
        },
        estadoBadge(e) {
            return { activa: 'bg-success', inactiva: 'bg-secondary', error: 'bg-danger' }[e] || 'bg-light text-dark';
        },
        estadoLabel(e) {
            return { activa: 'Activa', inactiva: 'Sin configurar', error: 'Error' }[e] || '—';
        },
    },
};
</script>

<style scoped>
.btn-xs { font-size: 0.7rem; }
</style>
