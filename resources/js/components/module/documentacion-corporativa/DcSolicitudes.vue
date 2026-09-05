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
                    <q-btn
                        v-if="ficha.id && form.estado !== 'rechazada'"
                        v-hasPermission="'documentacion-corporativa.entrega.create'"
                        flat
                        color="secondary"
                        icon="inventory_2"
                        label="Armar entrega"
                        @click="abrirEntrega()"
                    />
                    <q-space />
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" label="Guardar" :loading="guardando" @click="guardarFicha" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Armar entrega desde una solicitud (Fase 5c.2b, item #835) --------->
        <q-dialog v-model="dialogoEntrega">
            <q-card style="min-width: 480px; max-width: 720px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        <i class="bi bi-box-seam"></i>
                        Armar entrega — {{ ficha.solicitante }}
                    </div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 70vh" class="scroll">
                    <div class="text-caption text-grey q-mb-sm">
                        Recibida {{ ficha.fecha_recepcion }} ·
                        {{ ficha.fecha_limite ? 'límite ' + ficha.fecha_limite : 'sin plazo' }} ·
                        apartados pedidos: {{ (ficha.apartados || []).join(', ') }}
                    </div>

                    <div class="text-subtitle2 q-mb-xs">Apartados a incluir</div>
                    <q-inner-loading :showing="cargandoApartados">
                        <q-spinner size="24px" color="primary" />
                    </q-inner-loading>
                    <div v-if="!cargandoApartados" class="row q-col-gutter-xs q-mb-md">
                        <div v-for="ap in apartadosVisibles" :key="ap.clave" class="col-6 col-md-4">
                            <q-checkbox
                                dense
                                :model-value="entregaForm.apartados.includes(ap.clave)"
                                :label="ap.clave + ' — ' + ap.nombre"
                                @update:model-value="toggleApartadoEntrega(ap.clave)"
                            />
                        </div>
                        <div v-if="apartadosVisibles.length === 0" class="col-12 text-grey text-caption">
                            No tienes permiso para ver ningún apartado.
                        </div>
                    </div>

                    <q-select
                        outlined
                        dense
                        class="q-mb-md"
                        v-model="entregaForm.nivel_detalle"
                        :options="opcionesNivelDetalle"
                        emit-value
                        map-options
                        label="Nivel de detalle"
                    />

                    <q-btn
                        color="primary"
                        icon="add_box"
                        label="Generar"
                        :loading="generandoEntrega"
                        @click="generarEntrega"
                    />

                    <q-separator class="q-my-md" />

                    <div class="text-subtitle2 q-mb-xs">Entregas de esta solicitud</div>
                    <q-inner-loading :showing="cargandoEntregas">
                        <q-spinner size="24px" color="primary" />
                    </q-inner-loading>

                    <div v-if="!cargandoEntregas && entregas.length === 0" class="text-grey text-caption">
                        Aún no se ha armado ninguna entrega para esta solicitud.
                    </div>

                    <q-list v-else bordered separator>
                        <q-item v-for="e in entregas" :key="e.id">
                            <q-item-section avatar>
                                <q-icon :name="iconoEstadoEntrega(e.estado)" :color="colorEstadoEntrega(e.estado)" />
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>
                                    Entrega #{{ e.id }} · {{ (e.apartados || []).join(', ') }}
                                </q-item-label>
                                <q-item-label caption>
                                    {{ e.fecha_entrega || 'sin fecha' }} ·
                                    {{ e.descargas_zip_count }} descarga(s) ZIP ·
                                    {{ e.descargas_acta_count }} descarga(s) acta
                                </q-item-label>
                                <q-item-label v-if="e.estado === 'fallida' && e.error" caption class="text-negative">
                                    {{ e.error }}
                                </q-item-label>
                            </q-item-section>
                            <q-item-section side v-if="e.estado === 'generada'">
                                <div class="row q-gutter-xs">
                                    <q-btn dense flat icon="download" label="ZIP" @click="descargarZip(e.id)" />
                                    <q-btn dense flat icon="description" label="Acta" @click="descargarActa(e.id)" />
                                </div>
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
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
            // Armar entrega (Fase 5c.2b, item #835) ---------------------------
            dialogoEntrega: false,
            apartadosVisibles: [],
            cargandoApartados: false,
            entregaForm: { apartados: [], nivel_detalle: 'agregado' },
            generandoEntrega: false,
            entregas: [],
            cargandoEntregas: false,
        };
    },

    computed: {
        opcionesEstado() {
            return Object.entries(this.estadosLabel).map(([value, label]) => ({ value, label }));
        },

        opcionesNivelDetalle() {
            return [
                { value: 'agregado', label: 'Agregado (solo métricas)' },
                { value: 'detallado', label: 'Detallado (CSV)' },
                { value: 'integro', label: 'Íntegro (CSV + PDF)' },
            ];
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

        // Armar entrega (Fase 5c.2b, item #835) -------------------------------

        /** Abre "armar entrega" para la solicitud actual (this.ficha, ya abierta en el dialogo de edición). */
        abrirEntrega() {
            this.entregaForm = { apartados: [...(this.ficha.apartados || [])], nivel_detalle: 'agregado' };
            this.dialogoEntrega = true;
            this.cargarApartadosVisibles();
            this.cargarEntregas();
        },

        /** Reusa el tablero (ya filtrado por permiso) en vez de duplicar la lógica de permisos aquí. */
        async cargarApartadosVisibles() {
            this.cargandoApartados = true;
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/tablero');
                this.apartadosVisibles = data.apartados || [];
            } catch (e) {
                this.aviso('No se pudieron cargar los apartados visibles.', 'negative');
            } finally {
                this.cargandoApartados = false;
            }
        },

        toggleApartadoEntrega(clave) {
            const idx = this.entregaForm.apartados.indexOf(clave);
            if (idx === -1) {
                this.entregaForm.apartados.push(clave);
            } else {
                this.entregaForm.apartados.splice(idx, 1);
            }
        },

        async generarEntrega() {
            if (this.entregaForm.apartados.length === 0) {
                this.aviso('Selecciona al menos un apartado.', 'warning');
                return;
            }

            this.generandoEntrega = true;
            try {
                await axios.post(`/documentacion-corporativa/api/solicitudes/${this.ficha.id}/entregas`, this.entregaForm);
                this.aviso('Entrega generada.', 'positive');
                await this.cargarEntregas();
                if (this.dialogo) this.cargar();
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo generar la entrega.', 'negative');
            } finally {
                this.generandoEntrega = false;
            }
        },

        async cargarEntregas() {
            this.cargandoEntregas = true;
            try {
                const { data } = await axios.get(`/documentacion-corporativa/api/solicitudes/${this.ficha.id}/entregas`);
                this.entregas = data.data || [];
            } catch (e) {
                this.aviso('No se pudieron cargar las entregas.', 'negative');
            } finally {
                this.cargandoEntregas = false;
            }
        },

        descargarZip(entregaId) {
            window.open(`/documentacion-corporativa/api/entregas/${entregaId}/zip`, '_blank');
        },

        descargarActa(entregaId) {
            window.open(`/documentacion-corporativa/api/entregas/${entregaId}/acta`, '_blank');
        },

        iconoEstadoEntrega(e) {
            return {
                generando: 'autorenew',
                generada: 'check_circle',
                fallida: 'error',
            }[e] || 'help_outline';
        },

        colorEstadoEntrega(e) {
            return {
                generando: 'primary',
                generada: 'positive',
                fallida: 'negative',
            }[e] || 'grey-6';
        },
    },
};
</script>
