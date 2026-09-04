<template>
    <div>
        <!-- Bandeja global --------------------------------------------------->
        <q-dialog v-model="dialogo" full-width>
            <q-card style="max-width: 1100px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        <i class="bi bi-envelope-paper"></i>
                        Solicitudes de información recibidas
                    </div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section>
                    <div class="row items-center q-col-gutter-sm q-mb-md">
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
                        <q-space />
                        <q-btn color="primary" icon="add" label="Nueva solicitud" @click="abrirFicha()" />
                    </div>
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-inner-loading :showing="cargando">
                        <q-spinner size="34px" color="primary" />
                    </q-inner-loading>

                    <div v-if="!cargando && solicitudes.length === 0" class="text-center text-grey q-pa-lg">
                        <i class="bi bi-envelope" style="font-size: 30px"></i>
                        <div class="q-mt-sm">
                            No hay solicitudes registradas{{ filtroEstado ? ' con ese estado' : '' }}.
                        </div>
                    </div>

                    <q-list v-else bordered separator>
                        <q-item v-for="s in solicitudes" :key="s.id" clickable @click="abrirFicha(s)">
                            <q-item-section avatar>
                                <q-icon :name="iconoEstado(s.estado)" :color="colorEstado(s.estado)" />
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>
                                    {{ s.solicitante }}
                                    <span v-if="s.caracter" class="text-caption text-grey"> · {{ s.caracter }}</span>
                                </q-item-label>
                                <q-item-label caption>
                                    Recibida {{ s.fecha_recepcion }} ·
                                    {{ s.fecha_limite ? 'límite ' + s.fecha_limite : 'sin plazo' }} ·
                                    {{ (s.apartados || []).length }} apartado(s)
                                </q-item-label>
                                <q-item-label v-if="s.notas" caption class="text-grey-7">{{ s.notas }}</q-item-label>
                            </q-item-section>
                            <q-item-section side>
                                <q-badge :color="colorEstado(s.estado)" :label="etiquetaEstado(s.estado)" outline />
                                <q-btn
                                    flat
                                    dense
                                    round
                                    size="sm"
                                    icon="delete"
                                    color="negative"
                                    class="q-mt-xs"
                                    @click.stop="confirmarEliminar(s)"
                                />
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-dialog>

        <!-- Alta / edición de una solicitud ------------------------------------>
        <q-dialog v-model="dialogoFicha">
            <q-card style="min-width: 420px; max-width: 620px">
                <q-card-section class="row items-center">
                    <div class="text-h6">{{ ficha.id ? 'Editar solicitud' : 'Nueva solicitud' }}</div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 65vh" class="scroll">
                    <div class="row q-col-gutter-sm">
                        <q-input
                            class="col-12"
                            outlined
                            dense
                            v-model="form.solicitante"
                            label="Solicitante *"
                        />
                        <q-input
                            class="col-12"
                            outlined
                            dense
                            v-model="form.caracter"
                            label="Carácter (accionista, socio, autoridad…)"
                        />
                        <q-input
                            class="col-12 col-md-4"
                            outlined
                            dense
                            type="date"
                            v-model="form.fecha_recepcion"
                            label="Fecha de recepción *"
                        />
                        <q-input
                            class="col-12 col-md-4"
                            outlined
                            dense
                            type="number"
                            min="0"
                            v-model.number="form.plazo_dias"
                            label="Plazo (días)"
                        />
                        <q-input
                            class="col-12 col-md-4"
                            outlined
                            dense
                            type="date"
                            v-model="form.fecha_limite"
                            label="Fecha límite"
                            hint="Vacío = se calcula sola con recepción + plazo"
                        />
                        <q-select
                            class="col-12"
                            outlined
                            dense
                            v-model="form.estado"
                            :options="opcionesEstado"
                            emit-value
                            map-options
                            label="Estado"
                        />

                        <div class="col-12">
                            <div class="text-caption text-grey q-mb-xs">Apartados solicitados *</div>
                            <div class="row q-col-gutter-xs">
                                <div v-for="ap in APARTADOS" :key="ap.clave" class="col-6 col-md-4">
                                    <q-checkbox
                                        dense
                                        :model-value="form.apartados.includes(ap.clave)"
                                        :label="ap.clave + ' — ' + ap.nombre"
                                        @update:model-value="toggleApartado(ap.clave)"
                                    />
                                </div>
                            </div>
                        </div>

                        <q-input
                            class="col-12"
                            outlined
                            dense
                            type="textarea"
                            v-model="form.notas"
                            label="Notas"
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
/** Los 14 apartados I..XIV, mismo orden que `CatalogoSeeder`. */
const APARTADOS = [
    { clave: 'I', nombre: 'Documentación corporativa y societaria' },
    { clave: 'II', nombre: 'Información financiera y contable' },
    { clave: 'III', nombre: 'Información fiscal' },
    { clave: 'IV', nombre: 'Cuentas por cobrar, por pagar y flujo' },
    { clave: 'V', nombre: 'Activos, bienes e infraestructura' },
    { clave: 'VI', nombre: 'Contratos y relaciones comerciales' },
    { clave: 'VII', nombre: 'Personal y recursos humanos' },
    { clave: 'VIII', nombre: 'Activos digitales y tecnológicos' },
    { clave: 'IX', nombre: 'Marcas y propiedad intelectual' },
    { clave: 'X', nombre: 'Proveedores estratégicos y contactos operativos' },
    { clave: 'XI', nombre: 'Cuentas bancarias, firmas y productos financieros' },
    { clave: 'XII', nombre: 'Confidencialidad, conservación y no retención' },
    { clave: 'XIII', nombre: 'Títulos de concesión, permisos y derechos de uso' },
    { clave: 'XIV', nombre: 'Reserva de derechos y trazabilidad' },
];

/**
 * Bandeja de solicitudes de información recibidas (Fase 5a, item #758,
 * apartado XIV): quién pidió qué apartados, cuándo y con qué plazo.
 * Punto de entrada: botón "Gestionar solicitudes" de la tarjeta del concepto
 * "Registro de solicitudes de información recibidas" en `DcExpediente`.
 */
export default {
    name: 'DcSolicitudes',

    data() {
        return {
            APARTADOS,
            dialogo: false,
            dialogoFicha: false,
            cargando: false,
            guardando: false,
            solicitudes: [],
            filtroEstado: null,
            ficha: {},
            form: this.formVacio(),
            estadosLabel: {
                recibida: 'Recibida',
                en_preparacion: 'En preparación',
                entregada: 'Entregada',
                rechazada: 'Rechazada',
            },
        };
    },

    computed: {
        opcionesEstado() {
            return Object.entries(this.estadosLabel).map(([value, label]) => ({ value, label }));
        },
    },

    methods: {
        /** Punto de entrada: tarjeta "Registro de solicitudes de información recibidas". */
        abrir() {
            this.dialogo = true;
            this.cargar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtroEstado) params.estado = this.filtroEstado;
                const { data } = await axios.get('/documentacion-corporativa/api/solicitudes', { params });
                this.solicitudes = data.data;
            } catch (e) {
                this.aviso('No se pudo cargar las solicitudes.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        formVacio() {
            return {
                solicitante: '',
                caracter: '',
                fecha_recepcion: '',
                plazo_dias: null,
                fecha_limite: '',
                apartados: [],
                estado: 'recibida',
                notas: '',
            };
        },

        abrirFicha(solicitud) {
            if (solicitud) {
                this.ficha = solicitud;
                this.form = {
                    solicitante: solicitud.solicitante,
                    caracter: solicitud.caracter || '',
                    fecha_recepcion: solicitud.fecha_recepcion || '',
                    plazo_dias: solicitud.plazo_dias,
                    fecha_limite: solicitud.fecha_limite || '',
                    apartados: [...(solicitud.apartados || [])],
                    estado: solicitud.estado,
                    notas: solicitud.notas || '',
                };
            } else {
                this.ficha = {};
                this.form = this.formVacio();
            }
            this.dialogoFicha = true;
        },

        toggleApartado(clave) {
            const idx = this.form.apartados.indexOf(clave);
            if (idx === -1) {
                this.form.apartados.push(clave);
            } else {
                this.form.apartados.splice(idx, 1);
            }
        },

        async guardarFicha() {
            if (!this.form.solicitante || !this.form.fecha_recepcion || this.form.apartados.length === 0) {
                this.aviso('Solicitante, fecha de recepción y al menos un apartado son obligatorios.', 'warning');
                return;
            }

            this.guardando = true;
            try {
                const payload = { ...this.form, fecha_limite: this.form.fecha_limite || null };
                if (this.ficha.id) {
                    await axios.put(`/documentacion-corporativa/api/solicitudes/${this.ficha.id}`, payload);
                } else {
                    await axios.post('/documentacion-corporativa/api/solicitudes', payload);
                }
                this.aviso('Solicitud guardada.', 'positive');
                this.dialogoFicha = false;
                this.$emit('guardado');
                if (this.dialogo) this.cargar();
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo guardar la solicitud.', 'negative');
            } finally {
                this.guardando = false;
            }
        },

        confirmarEliminar(solicitud) {
            this.$q.dialog({
                title: 'Eliminar solicitud',
                message: `¿Eliminar la solicitud de "${solicitud.solicitante}"?`,
                cancel: true,
                persistent: true,
            }).onOk(() => this.eliminar(solicitud));
        },

        async eliminar(solicitud) {
            try {
                await axios.delete(`/documentacion-corporativa/api/solicitudes/${solicitud.id}`);
                this.aviso('Solicitud eliminada.', 'positive');
                this.$emit('guardado');
                this.cargar();
            } catch (e) {
                this.aviso('No se pudo eliminar la solicitud.', 'negative');
            }
        },

        iconoEstado(e) {
            return {
                recibida: 'mail',
                en_preparacion: 'autorenew',
                entregada: 'check_circle',
                rechazada: 'block',
            }[e] || 'help_outline';
        },

        colorEstado(e) {
            return {
                recibida: 'warning',
                en_preparacion: 'primary',
                entregada: 'positive',
                rechazada: 'grey-6',
            }[e] || 'grey-6';
        },

        etiquetaEstado(e) {
            return this.estadosLabel[e] || e;
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
