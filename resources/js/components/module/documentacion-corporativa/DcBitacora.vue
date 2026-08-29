<template>
    <q-dialog v-model="dialogo" full-width>
        <q-card style="max-width: 1200px">
            <q-card-section class="row items-center">
                <div class="text-h6">
                    <i class="bi bi-journal-text"></i>
                    Bitácora de accesos
                </div>
                <q-space />
                <q-btn
                    v-hasPermission="'documentacion-corporativa.bitacora.export'"
                    flat
                    dense
                    icon="download"
                    color="primary"
                    label="Exportar CSV"
                    class="q-mr-sm"
                    @click="exportar"
                />
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div class="row q-col-gutter-sm">
                    <q-select
                        v-if="empresasOpciones.length > 1"
                        class="col-12 col-md-2"
                        dense
                        outlined
                        v-model="filtros.empresa_id"
                        :options="empresasOpciones"
                        option-value="id"
                        option-label="etiqueta"
                        emit-value
                        map-options
                        label="Empresa"
                        @update:model-value="alCambiarEmpresa"
                    />
                    <q-select
                        class="col-12 col-md-3"
                        dense
                        outlined
                        clearable
                        v-model="filtros.user_id"
                        :options="usuariosOpciones"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        label="Usuario"
                        @update:model-value="alFiltrar"
                    />
                    <q-select
                        class="col-12 col-md-2"
                        dense
                        outlined
                        clearable
                        v-model="filtros.accion"
                        :options="opcionesAccion"
                        emit-value
                        map-options
                        label="Acción"
                        @update:model-value="alFiltrar"
                    />
                    <q-input
                        class="col-6 col-md-2"
                        dense
                        outlined
                        type="date"
                        v-model="filtros.fecha_desde"
                        label="Desde"
                        @update:model-value="alFiltrar"
                    />
                    <q-input
                        class="col-6 col-md-2"
                        dense
                        outlined
                        type="date"
                        v-model="filtros.fecha_hasta"
                        label="Hasta"
                        @update:model-value="alFiltrar"
                    />
                    <div class="col-12 col-md-1 flex items-center">
                        <q-btn flat dense round icon="filter_alt_off" @click="limpiarFiltros">
                            <q-tooltip>Limpiar filtros</q-tooltip>
                        </q-btn>
                    </div>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section style="max-height: 55vh" class="scroll">
                <q-inner-loading :showing="cargando">
                    <q-spinner size="34px" color="primary" />
                </q-inner-loading>

                <div v-if="!cargando && registros.length === 0" class="text-center text-grey q-pa-lg">
                    <i class="bi bi-journal-x" style="font-size: 30px"></i>
                    <div class="q-mt-sm">No hay registros de bitácora con ese filtro.</div>
                </div>

                <q-list v-else bordered separator>
                    <q-item v-for="r in registros" :key="r.id">
                        <q-item-section avatar>
                            <q-icon :name="iconoAccion(r.accion)" :color="colorAccion(r.accion)" />
                        </q-item-section>
                        <q-item-section>
                            <q-item-label>
                                {{ r.usuario ? nombreUsuario(r.usuario) : 'Sistema' }}
                                <q-badge outline :color="colorAccion(r.accion)" class="q-ml-xs" :label="etiquetaAccion(r.accion)" />
                            </q-item-label>
                            <q-item-label caption>
                                {{ formatoFecha(r.created_at) }}
                                <span v-if="r.apartado_clave"> · apartado {{ r.apartado_clave }}</span>
                                <span v-if="r.documento_nombre"> · {{ r.documento_nombre }}</span>
                            </q-item-label>
                        </q-item-section>
                        <q-item-section side>
                            <div class="text-caption text-grey">{{ r.ip || '—' }}</div>
                        </q-item-section>
                    </q-item>
                </q-list>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <div class="text-caption text-grey q-mr-md" v-if="meta.total">
                    {{ registros.length }} de {{ meta.total }} registros
                </div>
                <q-pagination
                    v-if="meta.last_page > 1"
                    v-model="pagina"
                    :max="meta.last_page"
                    :max-pages="6"
                    boundary-numbers
                    direction-links
                    @update:model-value="cargar"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script>
/**
 * Bitácora consultable/exportable (Fase 5c, item roadmap #760). Consume
 * `dc_accesos_log` — de solo lectura, la escritura la sigue haciendo
 * `BitacoraService` en el resto del módulo, en el momento en que se sirve el
 * contenido. Exportar reusa `BitacoraService::exportar()` del lado del
 * servidor: la propia descarga del CSV queda registrada en la bitácora.
 */
export default {
    name: 'DcBitacora',

    data() {
        return {
            dialogo: false,
            cargando: false,
            registros: [],
            meta: { total: 0, last_page: 1 },
            pagina: 1,
            empresasOpciones: [],
            usuariosOpciones: [],
            filtros: {
                empresa_id: null,
                user_id: null,
                accion: null,
                fecha_desde: '',
                fecha_hasta: '',
            },
            accionesLabel: {
                ver: 'Ver',
                descargar: 'Descargar',
                exportar: 'Exportar',
                imprimir: 'Imprimir',
            },
        };
    },

    computed: {
        opcionesAccion() {
            return Object.entries(this.accionesLabel).map(([value, label]) => ({ value, label }));
        },
    },

    methods: {
        /** Punto de entrada: botón "Bitácora de accesos" del header. */
        async abrir() {
            this.dialogo = true;
            if (this.empresasOpciones.length === 0) {
                await this.cargarEmpresas();
            }
            await this.cargarUsuarios();
            await this.cargar();
        },

        async cargarEmpresas() {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/bitacora/data/empresas');
                this.empresasOpciones = data;
                if (!this.filtros.empresa_id && data.length) {
                    this.filtros.empresa_id = data[0].id;
                }
            } catch (e) {
                this.empresasOpciones = [];
            }
        },

        async cargarUsuarios() {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/bitacora/data/usuarios', {
                    params: { empresa_id: this.filtros.empresa_id },
                });
                this.usuariosOpciones = data;
            } catch (e) {
                this.usuariosOpciones = [];
            }
        },

        alFiltrar() {
            this.pagina = 1;
            this.cargar();
        },

        async alCambiarEmpresa() {
            this.filtros.user_id = null;
            await this.cargarUsuarios();
            this.alFiltrar();
        },

        limpiarFiltros() {
            this.filtros.user_id = null;
            this.filtros.accion = null;
            this.filtros.fecha_desde = '';
            this.filtros.fecha_hasta = '';
            this.alFiltrar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/bitacora', {
                    params: { ...this.filtros, page: this.pagina },
                });
                this.registros = data.data;
                this.meta = { total: data.total, last_page: data.last_page };
            } catch (e) {
                this.aviso('No se pudo cargar la bitácora.', 'negative');
            } finally {
                this.cargando = false;
            }
        },

        exportar() {
            const params = new URLSearchParams();
            Object.entries(this.filtros).forEach(([k, v]) => {
                if (v !== null && v !== '') params.append(k, v);
            });
            window.open(`/documentacion-corporativa/api/bitacora/exportar?${params.toString()}`, '_blank');
        },

        nombreUsuario(u) {
            return [u.name, u.father_last_name, u.mother_last_name].filter(Boolean).join(' ');
        },

        iconoAccion(a) {
            return {
                ver: 'visibility',
                descargar: 'download',
                exportar: 'ios_share',
                imprimir: 'print',
            }[a] || 'help_outline';
        },

        colorAccion(a) {
            return {
                ver: 'grey-7',
                descargar: 'primary',
                exportar: 'deep-orange',
                imprimir: 'blue-grey',
            }[a] || 'grey-6';
        },

        etiquetaAccion(a) {
            return this.accionesLabel[a] || a;
        },

        formatoFecha(fecha) {
            if (!fecha) return '';
            return new Date(fecha).toLocaleString('es-MX', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>
