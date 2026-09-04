<template>
    <div>
        <!-- Calendario / lista ------------------------------------------------->
        <q-dialog v-model="dialogo" full-width>
            <q-card style="max-width: 1200px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        <i class="bi bi-shield-check"></i>
                        Apartado XIII — Concesiones y calendario regulatorio
                    </div>
                    <q-space />
                    <q-btn
                        v-if="tab === 'lista'"
                        color="primary"
                        icon="add"
                        label="Nueva concesión"
                        dense
                        class="q-mr-sm"
                        @click="nuevaConcesion"
                    />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-tabs v-model="tab" dense align="left" class="text-primary" active-color="primary">
                    <q-tab name="calendario" label="Calendario regulatorio" />
                    <q-tab name="lista" label="Todas las concesiones" />
                </q-tabs>

                <q-separator />

                <q-card-section style="max-height: 68vh" class="scroll">
                    <q-inner-loading :showing="cargando">
                        <q-spinner size="34px" color="primary" />
                    </q-inner-loading>

                    <!-- CALENDARIO -->
                    <div v-if="tab === 'calendario' && !cargando">
                        <div v-if="calendario.total_alertas === 0" class="text-center text-grey q-pa-lg">
                            <i class="bi bi-check-circle" style="font-size: 30px; color: #07703a"></i>
                            <div class="q-mt-sm">Ninguna concesión vence en los próximos 90 días.</div>
                        </div>

                        <template v-for="grupo in gruposCalendario" :key="grupo.key">
                            <div v-if="(calendario.vigencias[grupo.key] || []).length">
                                <div class="text-subtitle2 q-mt-md q-mb-xs" :style="{ color: grupo.color }">
                                    <i :class="grupo.icon"></i> {{ grupo.label }}
                                    ({{ calendario.vigencias[grupo.key].length }})
                                </div>
                                <q-list bordered separator>
                                    <q-item
                                        v-for="c in calendario.vigencias[grupo.key]"
                                        :key="c.id"
                                        clickable
                                        @click="abrirFicha(c.id)"
                                    >
                                        <q-item-section avatar>
                                            <q-icon name="event_busy" :color="colorQuasar(c.semaforo)" />
                                        </q-item-section>
                                        <q-item-section>
                                            <q-item-label>
                                                {{ etiquetaTipo(c.tipo) }}
                                                <span v-if="c.folio"> · {{ c.folio }}</span>
                                            </q-item-label>
                                            <q-item-label caption>
                                                {{ c.autoridad || 'Sin autoridad registrada' }} · vence
                                                {{ c.vigencia_fin }} ({{ c.dias_para_vencer }} días) ·
                                                {{ etiquetaEstadoTramite(c.estado_tramite) }}
                                            </q-item-label>
                                        </q-item-section>
                                        <q-item-section side>
                                            <q-badge
                                                :color="colorQuasar(c.semaforo)"
                                                :label="c.responsable || 'sin responsable'"
                                                outline
                                            />
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                            </div>
                        </template>

                        <div v-if="calendario.pagos_pendientes && calendario.pagos_pendientes.length" class="q-mt-lg">
                            <div class="text-subtitle2 q-mb-xs">
                                <i class="bi bi-cash-coin"></i> Pagos próximos a vencer
                            </div>
                            <q-list bordered separator>
                                <q-item
                                    v-for="p in calendario.pagos_pendientes"
                                    :key="p.id"
                                    clickable
                                    @click="abrirFicha(p.concesion_id)"
                                >
                                    <q-item-section avatar>
                                        <q-icon name="payments" :color="p.vencido ? 'negative' : 'warning'" />
                                    </q-item-section>
                                    <q-item-section>
                                        <q-item-label>
                                            {{ p.concepto }}
                                            <span v-if="p.periodo"> · {{ p.periodo }}</span>
                                        </q-item-label>
                                        <q-item-label caption>
                                            {{ p.concesion }} · vence {{ p.fecha_vencimiento }}
                                            <span v-if="p.monto"> · ${{ p.monto }}</span>
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-badge
                                            :color="p.vencido ? 'negative' : 'warning'"
                                            :label="p.vencido ? 'vencido' : 'pendiente'"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </div>
                    </div>

                    <!-- LISTA -->
                    <div v-if="tab === 'lista' && !cargando">
                        <div class="row q-col-gutter-sm q-mb-md">
                            <q-select
                                class="col-12 col-md-4"
                                dense
                                outlined
                                clearable
                                v-model="filtroTipo"
                                :options="opcionesTipo"
                                emit-value
                                map-options
                                label="Tipo"
                                @update:model-value="cargarLista"
                            />
                            <q-select
                                class="col-12 col-md-4"
                                dense
                                outlined
                                clearable
                                v-model="filtroEstado"
                                :options="opcionesEstado"
                                emit-value
                                map-options
                                label="Estado del trámite"
                                @update:model-value="cargarLista"
                            />
                        </div>

                        <div v-if="lista.length === 0" class="text-center text-grey q-pa-lg">
                            <i class="bi bi-inboxes" style="font-size: 30px"></i>
                            <div class="q-mt-sm">
                                No hay concesiones registradas{{ filtroTipo || filtroEstado ? ' con ese filtro' : '' }}.
                            </div>
                        </div>

                        <q-list v-else bordered separator>
                            <q-item v-for="c in lista" :key="c.id" clickable @click="abrirFicha(c.id)">
                                <q-item-section avatar>
                                    <q-icon name="description" :color="colorQuasar(c.semaforo)" />
                                </q-item-section>
                                <q-item-section>
                                    <q-item-label>
                                        {{ etiquetaTipo(c.tipo) }}
                                        <span v-if="c.folio"> · {{ c.folio }}</span>
                                    </q-item-label>
                                    <q-item-label caption>
                                        {{ c.autoridad || 'Sin autoridad' }} · vence {{ c.vigencia_fin }} ·
                                        {{ etiquetaEstadoTramite(c.estado_tramite) }} · resp.
                                        {{ c.responsable ? c.responsable.name : '—' }}
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section side>
                                    <q-badge :color="colorQuasar(c.semaforo)" :label="c.semaforo" />
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </div>
                </q-card-section>
            </q-card>
        </q-dialog>

        <!-- Ficha de una concesión --------------------------------------------->
        <q-dialog v-model="dialogoFicha" full-width>
            <q-card style="max-width: 900px">
                <q-card-section class="row items-center">
                    <div class="text-h6">
                        {{ ficha.id ? 'Concesión #' + ficha.id : 'Nueva concesión' }}
                    </div>
                    <q-badge
                        v-if="ficha.id"
                        :color="colorQuasar(ficha.semaforo)"
                        :label="ficha.semaforo"
                        class="q-ml-sm"
                    />
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 65vh" class="scroll">
                    <q-inner-loading :showing="cargandoFicha">
                        <q-spinner size="30px" color="primary" />
                    </q-inner-loading>

                    <div class="row q-col-gutter-sm">
                        <q-select
                            class="col-12 col-md-6"
                            outlined
                            dense
                            v-model="form.tipo"
                            :options="opcionesTipo"
                            emit-value
                            map-options
                            label="Tipo *"
                        />
                        <q-select
                            class="col-12 col-md-6"
                            outlined
                            dense
                            v-model="form.estado_tramite"
                            :options="opcionesEstado"
                            emit-value
                            map-options
                            label="Estado del trámite"
                        />
                        <q-input class="col-12 col-md-6" outlined dense v-model="form.autoridad" label="Autoridad" />
                        <q-input class="col-12 col-md-6" outlined dense v-model="form.folio" label="Folio" />
                        <q-input
                            class="col-12 col-md-6"
                            outlined
                            dense
                            type="date"
                            v-model="form.fecha_otorgamiento"
                            label="Fecha de otorgamiento"
                        />
                        <q-input
                            class="col-12 col-md-6"
                            outlined
                            dense
                            type="date"
                            v-model="form.vigencia_fin"
                            label="Vigencia hasta *"
                        />
                        <q-select
                            class="col-12"
                            outlined
                            dense
                            use-input
                            v-model="form.responsable_user_id"
                            :options="responsablesOpciones"
                            option-value="id"
                            option-label="name"
                            emit-value
                            map-options
                            label="Responsable *"
                            @filter="buscarResponsables"
                        />
                        <q-input
                            class="col-12"
                            outlined
                            dense
                            type="textarea"
                            v-model="form.objeto"
                            label="Objeto"
                        />
                        <q-input
                            class="col-12"
                            outlined
                            dense
                            type="textarea"
                            v-model="form.obligaciones"
                            label="Obligaciones"
                        />
                    </div>

                    <div class="text-right q-mt-sm">
                        <q-btn color="primary" label="Guardar" :loading="guardando" @click="guardarFicha" />
                    </div>

                    <template v-if="ficha.id">
                        <q-separator class="q-my-md" />
                        <div class="text-subtitle2 q-mb-xs">Pagos y obligaciones</div>

                        <q-list bordered separator v-if="ficha.pagos && ficha.pagos.length">
                            <q-item v-for="p in ficha.pagos" :key="p.id">
                                <q-item-section>
                                    <q-item-label>
                                        {{ p.concepto }}
                                        <span v-if="p.periodo"> · {{ p.periodo }}</span>
                                    </q-item-label>
                                    <q-item-label caption>
                                        <span v-if="p.monto">${{ p.monto }} · </span>
                                        vence {{ p.fecha_vencimiento || 'sin fecha' }}
                                        <span v-if="p.pagado"> · pagado {{ p.fecha_pago }}</span>
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section side>
                                    <q-badge v-if="p.pagado" color="positive" label="pagado" />
                                    <q-btn
                                        v-else
                                        dense
                                        flat
                                        color="primary"
                                        label="Marcar pagado"
                                        @click="marcarPagado(p.id)"
                                    />
                                </q-item-section>
                            </q-item>
                        </q-list>
                        <div v-else class="text-caption text-grey q-mb-sm">Sin pagos registrados.</div>

                        <div class="row q-col-gutter-sm q-mt-sm items-end">
                            <q-input class="col-6 col-md-3" dense outlined v-model="nuevoPago.concepto" label="Concepto" />
                            <q-input class="col-6 col-md-2" dense outlined v-model="nuevoPago.periodo" label="Periodo" />
                            <q-input
                                class="col-6 col-md-2"
                                dense
                                outlined
                                type="number"
                                v-model.number="nuevoPago.monto"
                                label="Monto"
                            />
                            <q-input
                                class="col-6 col-md-3"
                                dense
                                outlined
                                type="date"
                                v-model="nuevoPago.fecha_vencimiento"
                                label="Vence"
                            />
                            <q-btn
                                class="col-12 col-md-2"
                                dense
                                color="primary"
                                icon="add"
                                label="Agregar"
                                :disable="!nuevoPago.concepto"
                                @click="agregarPago"
                            />
                        </div>
                    </template>
                </q-card-section>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
/**
 * Apartado XIII, vista dedicada: el chequeo genérico de "¿existe el registro?"
 * (DcExpediente) no alcanza aquí — este apartado necesita el calendario de
 * vigencias (90/60/30/7 días) y la ficha con sus pagos, así que vive aparte
 * en vez de reusar el diálogo genérico de conceptos.
 */
export default {
    name: 'DcConcesiones',

    data() {
        return {
            dialogo: false,
            dialogoFicha: false,
            tab: 'calendario',
            cargando: false,
            cargandoFicha: false,
            guardando: false,
            calendario: { umbrales: [], vigencias: {}, pagos_pendientes: [], total_alertas: 0 },
            lista: [],
            filtroTipo: null,
            filtroEstado: null,
            ficha: {},
            form: this.formVacio(),
            responsablesOpciones: [],
            nuevoPago: { concepto: '', periodo: '', monto: null, fecha_vencimiento: '', fecha_pago: '' },
            gruposCalendario: [
                { key: 'vencidas', label: 'Vencidas', color: '#c62828', icon: 'bi bi-x-octagon' },
                { key: 7, label: 'Vencen en 7 días', color: '#c62828', icon: 'bi bi-exclamation-octagon' },
                { key: 30, label: 'Vencen en 30 días', color: '#b25e00', icon: 'bi bi-exclamation-triangle' },
                { key: 60, label: 'Vencen en 60 días', color: '#b25e00', icon: 'bi bi-exclamation-triangle' },
                { key: 90, label: 'Vencen en 90 días', color: '#7c6f00', icon: 'bi bi-info-circle' },
            ],
            tiposLabel: {
                titulo_concesion: 'Título de concesión',
                permiso: 'Permiso',
                autorizacion: 'Autorización',
                derecho_via: 'Derecho de vía',
                convenio_infraestructura: 'Convenio de infraestructura',
                arrendamiento_sitio: 'Arrendamiento de sitio',
            },
            estadosLabel: {
                vigente: 'Vigente',
                en_renovacion: 'En renovación',
                en_tramite: 'En trámite',
                vencido: 'Vencido',
            },
        };
    },

    computed: {
        opcionesTipo() {
            return Object.entries(this.tiposLabel).map(([value, label]) => ({ value, label }));
        },
        opcionesEstado() {
            return Object.entries(this.estadosLabel).map(([value, label]) => ({ value, label }));
        },
    },

    watch: {
        tab(val) {
            if (val === 'lista' && this.lista.length === 0) {
                this.cargarLista();
            }
        },
    },

    methods: {
        /** Punto de entrada: lo llama DcExpediente al abrir el apartado XIII. */
        abrir() {
            this.dialogo = true;
            this.tab = 'calendario';
            this.cargarCalendario();
        },

        async cargarCalendario() {
            this.cargando = true;
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/concesiones/calendario');
                this.calendario = data;
                this.$emit('calendario-cargado', data);
            } catch (e) {
                this.aviso('No se pudo cargar el calendario regulatorio.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        async cargarLista() {
            this.cargando = true;
            try {
                const params = {};
                if (this.filtroTipo) params.tipo = this.filtroTipo;
                if (this.filtroEstado) params.estado_tramite = this.filtroEstado;
                const { data } = await axios.get('/documentacion-corporativa/api/concesiones', { params });
                this.lista = data.data;
            } catch (e) {
                this.aviso('No se pudo cargar la lista de concesiones.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        formVacio() {
            return {
                tipo: 'permiso',
                autoridad: '',
                folio: '',
                objeto: '',
                fecha_otorgamiento: '',
                vigencia_fin: '',
                obligaciones: '',
                responsable_user_id: null,
                estado_tramite: 'vigente',
            };
        },

        nuevaConcesion() {
            this.ficha = {};
            this.form = this.formVacio();
            this.responsablesOpciones = [];
            this.nuevoPago = { concepto: '', periodo: '', monto: null, fecha_vencimiento: '', fecha_pago: '' };
            this.dialogoFicha = true;
        },

        async abrirFicha(id) {
            this.dialogoFicha = true;
            this.cargandoFicha = true;
            try {
                const { data } = await axios.get(`/documentacion-corporativa/api/concesiones/${id}`);
                this.ficha = data;
                this.form = {
                    tipo: data.tipo,
                    autoridad: data.autoridad || '',
                    folio: data.folio || '',
                    objeto: data.objeto || '',
                    fecha_otorgamiento: data.fecha_otorgamiento || '',
                    vigencia_fin: data.vigencia_fin || '',
                    obligaciones: data.obligaciones || '',
                    responsable_user_id: data.responsable_user_id,
                    estado_tramite: data.estado_tramite,
                };
                this.responsablesOpciones = data.responsable
                    ? [{ id: data.responsable.id, name: data.responsable.name }]
                    : [];
            } catch (e) {
                this.aviso('No se pudo abrir la concesión.', 'negative');
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
                        `/documentacion-corporativa/api/concesiones/${this.ficha.id}`,
                        this.form
                    );
                    this.ficha = { ...this.ficha, ...data };
                } else {
                    const { data } = await axios.post('/documentacion-corporativa/api/concesiones', this.form);
                    this.ficha = data;
                }
                this.aviso('Concesión guardada.', 'positive');
                this.cargarCalendario();
                if (this.tab === 'lista') this.cargarLista();
            } catch (e) {
                this.aviso(e.response?.data?.message || 'No se pudo guardar la concesión.', 'negative');
            } finally {
                this.guardando = false;
            }
        },

        async agregarPago() {
            try {
                const { data } = await axios.post(
                    `/documentacion-corporativa/api/concesiones/${this.ficha.id}/pagos`,
                    this.nuevoPago
                );
                if (!this.ficha.pagos) this.ficha.pagos = [];
                this.ficha.pagos.unshift(data);
                this.nuevoPago = { concepto: '', periodo: '', monto: null, fecha_vencimiento: '', fecha_pago: '' };
                this.cargarCalendario();
            } catch (e) {
                this.aviso('No se pudo agregar el pago.', 'negative');
            }
        },

        async marcarPagado(pagoId) {
            try {
                const { data } = await axios.put(
                    `/documentacion-corporativa/api/concesiones/${this.ficha.id}/pagos/${pagoId}/pagar`
                );
                const idx = this.ficha.pagos.findIndex((p) => p.id === pagoId);
                if (idx !== -1) this.ficha.pagos.splice(idx, 1, data);
                this.cargarCalendario();
            } catch (e) {
                this.aviso('No se pudo marcar el pago como cubierto.', 'negative');
            }
        },

        async buscarResponsables(val, update) {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/concesiones/data/responsables', {
                    params: { search: val },
                });
                update(() => {
                    this.responsablesOpciones = data;
                });
            } catch (e) {
                update(() => {});
            }
        },

        etiquetaTipo(t) {
            return this.tiposLabel[t] || t;
        },

        etiquetaEstadoTramite(e) {
            return this.estadosLabel[e] || e;
        },

        colorQuasar(s) {
            return { verde: 'positive', amarillo: 'warning', rojo: 'negative', gris: 'grey-5' }[s] || 'grey-5';
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
