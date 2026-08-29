<template>
    <div>
        <!-- Bandeja global --------------------------------------------------->
        <q-dialog v-model="dialogo" full-width>
            <q-card style="max-width: 1100px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        <i class="bi bi-inbox"></i>
                        Bandeja de pendientes
                    </div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section>
                    <div class="row q-col-gutter-sm q-mb-md">
                        <q-select
                            class="col-12 col-md-5"
                            dense
                            outlined
                            clearable
                            use-input
                            v-model="filtroResponsable"
                            :options="responsablesOpciones"
                            option-value="id"
                            option-label="name"
                            emit-value
                            map-options
                            label="Responsable"
                            @filter="buscarResponsables"
                            @update:model-value="cargar"
                        />
                        <q-select
                            class="col-12 col-md-5"
                            dense
                            outlined
                            clearable
                            v-model="filtroEstado"
                            :options="opcionesEstado"
                            emit-value
                            map-options
                            label="Estado"
                            @update:model-value="cargar"
                        />
                        <div class="col-12 col-md-2 flex items-center">
                            <q-checkbox v-model="soloVencidos" dense label="Solo vencidos" @update:model-value="cargar" />
                        </div>
                    </div>
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-inner-loading :showing="cargando">
                        <q-spinner size="34px" color="primary" />
                    </q-inner-loading>

                    <div v-if="!cargando && pendientesFiltrados.length === 0" class="text-center text-grey q-pa-lg">
                        <i class="bi bi-inboxes" style="font-size: 30px"></i>
                        <div class="q-mt-sm">
                            No hay pendientes{{ filtroResponsable || filtroEstado || soloVencidos ? ' con ese filtro' : '' }}.
                        </div>
                    </div>

                    <q-list v-else bordered separator>
                        <q-item v-for="p in pendientesFiltrados" :key="p.id" clickable @click="abrirFicha(p)">
                            <q-item-section avatar>
                                <q-icon
                                    :name="p.vencido ? 'event_busy' : iconoEstado(p.estado)"
                                    :color="p.vencido ? 'negative' : colorEstado(p.estado)"
                                />
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>
                                    {{ p.concepto ? p.concepto.nombre : 'Concepto #' + p.concepto_id }}
                                    <span v-if="p.concepto && p.concepto.apartado" class="text-caption text-grey">
                                        · apartado {{ p.concepto.apartado.clave }}
                                    </span>
                                </q-item-label>
                                <q-item-label caption>
                                    {{ p.responsable ? p.responsable.name : 'Sin responsable asignado' }} ·
                                    {{ p.fecha_compromiso ? 'compromiso ' + p.fecha_compromiso : 'sin fecha compromiso' }}
                                    <span v-if="p.recordatorio_enviado_at"> · recordatorio {{ soloFecha(p.recordatorio_enviado_at) }}</span>
                                </q-item-label>
                                <q-item-label v-if="p.comentarios" caption class="text-grey-7">{{ p.comentarios }}</q-item-label>
                            </q-item-section>
                            <q-item-section side>
                                <q-badge v-if="p.vencido" color="negative" label="vencido" />
                                <q-badge v-else :color="colorEstado(p.estado)" :label="etiquetaEstado(p.estado)" outline />
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-dialog>

        <!-- Alta / edición de un pendiente ------------------------------------->
        <q-dialog v-model="dialogoFicha">
            <q-card style="min-width: 420px; max-width: 520px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        {{ ficha.id ? 'Editar pendiente' : 'Asignar responsable' }}
                    </div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-inner-loading :showing="cargandoFicha">
                        <q-spinner size="30px" color="primary" />
                    </q-inner-loading>

                    <div class="text-subtitle2 q-mb-sm">{{ conceptoNombre }}</div>

                    <div class="row q-col-gutter-sm">
                        <q-select
                            class="col-12"
                            outlined
                            dense
                            use-input
                            clearable
                            v-model="form.responsable_user_id"
                            :options="responsablesOpciones"
                            option-value="id"
                            option-label="name"
                            emit-value
                            map-options
                            label="Responsable"
                            @filter="buscarResponsables"
                        />
                        <q-input
                            class="col-12 col-md-6"
                            outlined
                            dense
                            type="date"
                            v-model="form.fecha_compromiso"
                            label="Fecha compromiso"
                        />
                        <q-select
                            class="col-12 col-md-6"
                            outlined
                            dense
                            v-model="form.estado"
                            :options="opcionesEstado"
                            emit-value
                            map-options
                            label="Estado"
                        />
                        <q-input
                            class="col-12"
                            outlined
                            dense
                            type="textarea"
                            v-model="form.comentarios"
                            label="Comentarios"
                        />
                    </div>
                </q-card-section>

                <q-separator />

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" label="Guardar" :loading="guardando" @click="guardarFicha" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
/**
 * Bandeja de pendientes (Fase 2b, item #735): quién debe conseguir qué concepto
 * del expediente y para cuándo. Dos puntos de entrada: `abrir()` (lista global,
 * botón del header de DcExpediente) y `abrirParaConcepto(concepto)` (desde la
 * tarjeta "sin fuente configurada" de un concepto puntual, que ya trae
 * `metricas.pendiente_id` si ya existe gestión abierta).
 */
export default {
    name: 'DcPendientesBandeja',

    data() {
        return {
            dialogo: false,
            dialogoFicha: false,
            cargando: false,
            cargandoFicha: false,
            guardando: false,
            pendientes: [],
            filtroResponsable: null,
            filtroEstado: null,
            soloVencidos: false,
            responsablesOpciones: [],
            ficha: {},
            conceptoNombre: '',
            form: this.formVacio(),
            estadosLabel: {
                pendiente: 'Pendiente',
                en_proceso: 'En proceso',
                entregado: 'Entregado',
                no_aplica: 'No aplica',
            },
        };
    },

    computed: {
        opcionesEstado() {
            return Object.entries(this.estadosLabel).map(([value, label]) => ({ value, label }));
        },

        pendientesFiltrados() {
            if (!this.soloVencidos) return this.pendientes;
            return this.pendientes.filter((p) => p.vencido);
        },
    },

    methods: {
        /** Punto de entrada: botón "Bandeja de pendientes" del header. */
        abrir() {
            this.dialogo = true;
            this.cargar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtroResponsable) params.responsable_user_id = this.filtroResponsable;
                if (this.filtroEstado) params.estado = this.filtroEstado;
                const { data } = await axios.get('/documentacion-corporativa/api/pendientes', { params });
                this.pendientes = data.data;
            } catch (e) {
                this.aviso('No se pudo cargar la bandeja de pendientes.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        formVacio() {
            return {
                concepto_id: null,
                responsable_user_id: null,
                fecha_compromiso: '',
                estado: 'pendiente',
                comentarios: '',
            };
        },

        /** Punto de entrada: tarjeta "sin fuente" de un concepto en DcExpediente. */
        async abrirParaConcepto(concepto) {
            this.conceptoNombre = concepto.nombre;
            this.responsablesOpciones = [];

            const pendienteId = concepto.metricas ? concepto.metricas.pendiente_id : null;
            if (pendienteId) {
                await this.cargarFicha(pendienteId);
                return;
            }

            this.ficha = {};
            this.form = { ...this.formVacio(), concepto_id: concepto.id };
            this.dialogoFicha = true;
        },

        /** Punto de entrada: click en una fila de la bandeja. */
        async abrirFicha(pendiente) {
            this.conceptoNombre = pendiente.concepto
                ? pendiente.concepto.nombre
                : 'Concepto #' + pendiente.concepto_id;
            await this.cargarFicha(pendiente.id);
        },

        async cargarFicha(id) {
            this.dialogoFicha = true;
            this.cargandoFicha = true;
            try {
                const { data } = await axios.get(`/documentacion-corporativa/api/pendientes/${id}`);
                this.ficha = data;
                this.conceptoNombre = data.concepto ? data.concepto.nombre : this.conceptoNombre;
                this.form = {
                    concepto_id: data.concepto_id,
                    responsable_user_id: data.responsable_user_id,
                    fecha_compromiso: data.fecha_compromiso || '',
                    estado: data.estado,
                    comentarios: data.comentarios || '',
                };
                this.responsablesOpciones = data.responsable
                    ? [{ id: data.responsable.id, name: data.responsable.name }]
                    : [];
            } catch (e) {
                this.aviso('No se pudo abrir el pendiente.', 'negative');
                this.dialogoFicha = false;
            } finally {
                this.cargandoFicha = false;
            }
        },

        async guardarFicha() {
            this.guardando = true;
            try {
                if (this.ficha.id) {
                    const { data } = await axios.put(
                        `/documentacion-corporativa/api/pendientes/${this.ficha.id}`,
                        this.form
                    );
                    this.ficha = data;
                } else {
                    const { data } = await axios.post('/documentacion-corporativa/api/pendientes', this.form);
                    this.ficha = data;
                }
                this.aviso('Pendiente guardado.', 'positive');
                this.dialogoFicha = false;
                this.$emit('guardado');
                if (this.dialogo) this.cargar();
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo guardar el pendiente.', 'negative');
            } finally {
                this.guardando = false;
            }
        },

        async buscarResponsables(val, update) {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/pendientes/data/responsables', {
                    params: { search: val },
                });
                update(() => {
                    this.responsablesOpciones = data;
                });
            } catch (e) {
                update(() => {});
            }
        },

        iconoEstado(e) {
            return {
                pendiente: 'schedule',
                en_proceso: 'autorenew',
                entregado: 'check_circle',
                no_aplica: 'block',
            }[e] || 'help_outline';
        },

        colorEstado(e) {
            return {
                pendiente: 'warning',
                en_proceso: 'primary',
                entregado: 'positive',
                no_aplica: 'grey-6',
            }[e] || 'grey-6';
        },

        etiquetaEstado(e) {
            return this.estadosLabel[e] || e;
        },

        soloFecha(fechaHora) {
            return String(fechaHora).slice(0, 10);
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
