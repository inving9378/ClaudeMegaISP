<template>
    <div class="evaluador-empresarial container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h4 mb-4">
                    <i class="bi bi-clipboard-check me-2"></i>
                    Evaluador de Servicios Empresariales
                </h2>

                <!-- STUB: contenido del cuestionario va aquí.
                     Pegar el HTML del evaluador standalone y enlazarlo al state local. -->
                <div class="alert alert-info">
                    <strong>Componente en construcción.</strong>
                    El formulario completo se pegará aquí en el siguiente paso.
                </div>

                <!-- Datos de contacto: ejemplo mínimo funcional -->
                <form @submit.prevent="enviar" v-if="!resultado">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del contacto *</label>
                            <input v-model="form.nombre_contacto" type="text" class="form-control" required maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Empresa *</label>
                            <input v-model="form.empresa" type="text" class="form-control" required maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input v-model="form.email_contacto" type="email" class="form-control" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp</label>
                            <input v-model="form.whatsapp_contacto" type="text" class="form-control" maxlength="20">
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" :disabled="enviando">
                            <span v-if="enviando" class="spinner-border spinner-border-sm me-2"></span>
                            Calcular evaluación
                        </button>
                    </div>
                </form>

                <!-- Resultado -->
                <div v-else class="resultado mt-3">
                    <h3 class="h5">Resultado</h3>
                    <p>
                        <strong>Categoría:</strong> {{ resultado.categoria }}<br>
                        <strong>Puntaje:</strong> {{ resultado.puntaje_total }}<br>
                        <strong>Plan recomendado:</strong> {{ resultado.plan_recomendado }}
                    </p>
                    <a :href="`/evaluador-empresarial/resultado/${resultado.token}`" class="btn btn-link p-0">
                        Ver enlace público
                    </a>
                </div>

                <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'EvaluadorEmpresarial',

    data() {
        return {
            form: {
                nombre_contacto:   '',
                empresa:           '',
                email_contacto:    '',
                whatsapp_contacto: '',
                cargo:             '',
                puntaje_total:     0,
                categoria:         'BASICO',
                plan_recomendado:  '',
                respuestas_json:   {},
                score_criticidad:  0,
                score_redundancia: 0,
                score_ancho_banda: 0,
                score_sla:         0,
                canal_origen:      'directo',
                vendedor_id:       null,
            },
            enviando:  false,
            error:     null,
            resultado: null,
        };
    },

    methods: {
        calcularScoring() {
            // TODO: lógica de scoring real cuando se pegue el HTML del evaluador.
            // Por ahora valores demo para que el flow funcione end-to-end.
            this.form.puntaje_total     = 50;
            this.form.categoria         = 'PROFESIONAL';
            this.form.plan_recomendado  = 'MegaPro 200/200';
            this.form.respuestas_json   = { stub: true };
        },

        async enviar() {
            this.error    = null;
            this.enviando = true;

            try {
                this.calcularScoring();

                const { data } = await axios.post('/evaluador-empresarial/guardar', this.form);
                if (data.success) {
                    this.resultado = {
                        ...this.form,
                        id:    data.id,
                        token: data.token,
                    };

                    // Envío opcional por email si llenó email_contacto
                    if (this.form.email_contacto) {
                        try {
                            await axios.post('/evaluador-empresarial/enviar-email', {
                                evaluacion_id: data.id,
                                email_destino: this.form.email_contacto,
                            });
                        } catch (e) {
                            // No-bloquear el flow si falla el email
                        }
                    }
                } else {
                    this.error = data.mensaje || 'No se pudo guardar la evaluación.';
                }
            } catch (e) {
                this.error = e.response?.data?.mensaje || 'Error de red';
            } finally {
                this.enviando = false;
            }
        },
    },
};
</script>

<style scoped>
.evaluador-empresarial {
    max-width: 960px;
}
</style>
