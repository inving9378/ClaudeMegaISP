<template>
    <div class="embajadores-configuracion">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Configuración del programa</h5>

                        <div v-if="loading" class="text-muted">Cargando configuración…</div>
                        <form v-else @submit.prevent="guardar">

                            <div class="mb-3 d-flex align-items-center gap-3">
                                <label class="form-label mb-0 fw-600">Programa activo</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" v-model="form.program_active" id="switchActivo">
                                    <label class="form-check-label" for="switchActivo">
                                        <span v-if="form.program_active" class="text-success">Activo</span>
                                        <span v-else class="text-danger">Inactivo</span>
                                    </label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-600">Nombre del programa</label>
                                    <input type="text" class="form-control" v-model="form.program_name" maxlength="100" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-600">Umbral de activación <small class="text-muted">(MXN)</small></label>
                                    <input type="number" class="form-control" v-model="form.threshold_amount" min="1" step="0.01" required>
                                    <div class="form-text">El cliente debe pagar este monto antes de activarse como embajador.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-600">Meses máx.</label>
                                    <input type="number" class="form-control" v-model="form.duration_months" min="1" max="24" required>
                                    <div class="form-text">Duración máxima de comisiones por referido.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-600">Niveles</label>
                                    <input type="number" class="form-control" v-model="form.max_levels" min="1" max="5" required>
                                    <div class="form-text">Profundidad multinivel (máx. 5).</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-600">Mensaje de bienvenida <small class="text-muted">(app cliente)</small></label>
                                <textarea class="form-control" v-model="form.welcome_message" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-600">Plantilla de mensaje para compartir</label>
                                <textarea class="form-control" v-model="form.share_template_default" rows="3"></textarea>
                                <div class="form-text">Usa <code>{referral_link}</code> para insertar el link del embajador.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-600">Términos y condiciones</label>
                                <textarea class="form-control" v-model="form.terms_conditions" rows="5"></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" :disabled="saving">
                                    <i class="fa fa-save me-1"></i>
                                    {{ saving ? 'Guardando…' : 'Guardar configuración' }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" @click="cargar">
                                    <i class="fa fa-undo me-1"></i> Descartar cambios
                                </button>
                            </div>

                            <div v-if="message" class="alert mt-3" :class="messageClass">{{ message }}</div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-info">
                    <div class="card-body">
                        <h6 class="card-title text-info"><i class="fa fa-info-circle me-1"></i> Notas importantes</h6>
                        <ul class="small mb-0">
                            <li class="mb-2">El <strong>umbral de activación</strong> aplica tanto al embajador (para poder recomendar) como a cada referido (para que genere comisiones).</li>
                            <li class="mb-2">Desactivar el programa <strong>no cancela</strong> comisiones ya generadas — solo detiene nuevas.</li>
                            <li class="mb-2">Los porcentajes de comisión se configuran en la pantalla <strong>Porcentajes</strong>.</li>
                            <li>El nombre del programa en UI siempre debe ser <strong>"Embajadores Meganet"</strong>.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'EmbajadoresConfiguracion',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: true,
            saving:  false,
            message: '',
            messageClass: '',
            form: {
                program_active:          true,
                threshold_amount:        1500,
                duration_months:         12,
                max_levels:              5,
                program_name:            '',
                welcome_message:         '',
                share_template_default:  '',
                terms_conditions:        '',
            },
        };
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/configuracion/get`);
                Object.assign(this.form, data);
            } catch (e) {
                this.showMessage('Error cargando la configuración.', 'danger');
            } finally {
                this.loading = false;
            }
        },
        async guardar() {
            this.saving  = true;
            this.message = '';
            try {
                const { data } = await axios.post(`${this.baseUrl}/configuracion/update`, this.form, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.showMessage(data.message || 'Guardado correctamente.', 'success');
                Object.assign(this.form, data.settings);
            } catch (e) {
                const msg = e.response?.data?.message || 'Error guardando la configuración.';
                this.showMessage(msg, 'danger');
            } finally {
                this.saving = false;
            }
        },
        showMessage(msg, type) {
            this.message      = msg;
            this.messageClass = `alert-${type}`;
            setTimeout(() => { this.message = ''; }, 4000);
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
</style>
