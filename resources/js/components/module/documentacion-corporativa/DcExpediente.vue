<template>
    <div class="q-pa-md">
        <!-- Encabezado ------------------------------------------------------->
        <div class="row items-center q-mb-md">
            <div>
                <div class="text-h6">Documentación Corporativa</div>
                <div class="text-caption text-grey">
                    Expediente corporativo y societario · {{ empresa.razon_social || empresaEtiqueta }}
                    <span v-if="empresa.rfc"> · RFC {{ empresa.rfc }}</span>
                </div>
            </div>
            <q-space />

            <q-select
                v-if="mostrarSelector"
                v-model="empresaSeleccionada"
                :options="opcionesEmpresa"
                option-value="id"
                option-label="etiqueta"
                emit-value
                map-options
                dense
                outlined
                style="min-width: 240px"
                class="q-mr-sm"
                label="Empresa"
                @update:model-value="cambiarEmpresa"
            />

            <q-btn
                flat
                dense
                icon="inbox"
                color="primary"
                label="Bandeja de pendientes"
                class="q-mr-sm"
                @click="$refs.pendientes.abrir()"
            />

            <q-btn
                flat
                dense
                icon="refresh"
                color="primary"
                :loading="loading"
                @click="cargar(true)"
            >
                <q-tooltip>Recalcular completitud</q-tooltip>
            </q-btn>
        </div>

        <!-- Tablero global --------------------------------------------------->
        <div class="row q-col-gutter-md q-mb-lg">
            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-clipboard-check" style="font-size:26px" :style="{ color: colorSemaforo(global.semaforo) }"></i>
                        <div class="text-h4 text-weight-bold" :style="{ color: colorSemaforo(global.semaforo) }">
                            {{ global.medible === false ? '—' : global.porcentaje + '%' }}
                        </div>
                        <div class="text-subtitle2">Completitud del expediente</div>
                        <div class="text-caption text-grey">
                            {{ global.resueltos }} de {{ global.obligatorios }} conceptos obligatorios
                        </div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-folder2-open" style="font-size:26px;color:#0057A8"></i>
                        <div class="text-h4 text-weight-bold text-primary">{{ apartados.length }}</div>
                        <div class="text-subtitle2">Apartados visibles</div>
                        <div class="text-caption text-grey">de 14 en la solicitud</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section class="text-center">
                        <i class="bi bi-exclamation-triangle" style="font-size:26px;color:#c62828"></i>
                        <div class="text-h4 text-weight-bold" style="color:#c62828">{{ totalFaltantes }}</div>
                        <div class="text-subtitle2">Conceptos faltantes</div>
                        <div class="text-caption text-grey">obligatorios sin resolver</div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <!-- Índice de apartados ---------------------------------------------->
        <div v-if="!loading && apartados.length === 0" class="text-center q-pa-xl text-grey">
            <i class="bi bi-folder-x" style="font-size:34px"></i>
            <div class="q-mt-sm">No tienes permiso para ver ningún apartado de este expediente.</div>
        </div>

        <div class="row q-col-gutter-md">
            <div
                v-for="ap in apartados"
                :key="ap.clave"
                class="col-12 col-md-6 col-lg-4"
            >
                <q-card
                    flat
                    bordered
                    class="dc-card cursor-pointer full-height"
                    :style="{ borderLeft: '4px solid ' + colorSemaforo(ap.semaforo) }"
                    @click="abrirApartado(ap)"
                >
                    <q-card-section>
                        <div class="row items-start no-wrap">
                            <div class="dc-clave">{{ ap.clave }}</div>
                            <div class="col q-ml-sm">
                                <div class="text-subtitle2 text-weight-medium">{{ ap.nombre }}</div>
                                <div class="text-caption text-grey dc-desc">{{ ap.descripcion }}</div>
                            </div>
                            <div
                                class="text-h6 text-weight-bold q-ml-sm"
                                :style="{ color: colorSemaforo(ap.semaforo) }"
                            >
                                {{ ap.medible === false ? '—' : ap.porcentaje + '%' }}
                                <q-tooltip v-if="ap.medible === false">
                                    Este apartado no tiene conceptos obligatorios: no hay porcentaje que medir.
                                </q-tooltip>
                            </div>
                        </div>

                        <q-linear-progress
                            :value="ap.medible === false ? 0 : ap.porcentaje / 100"
                            :color="colorQuasar(ap.semaforo)"
                            size="6px"
                            rounded
                            class="q-mt-sm"
                        />

                        <div class="row items-center q-mt-sm text-caption text-grey">
                            <div>
                                {{ ap.conceptos_total }} conceptos ·
                                <span v-if="ap.medible === false">sin obligatorios que medir</span>
                                <span v-else>{{ ap.resueltos }}/{{ ap.obligatorios }} obligatorios</span>
                            </div>
                            <q-space />
                            <q-badge
                                v-if="ap.faltantes.length"
                                color="red-5"
                                :label="ap.faltantes.length + ' faltan'"
                            />
                            <q-badge
                                v-if="ap.clave === 'XIII' && alertasXIII"
                                class="q-ml-xs"
                                :color="alertasXIII.vencidas > 0 ? 'negative' : (alertasXIII.total > 0 ? 'warning' : 'positive')"
                                :label="badgeAlertasXIII"
                            />
                        </div>

                        <div class="q-mt-xs text-caption text-grey">
                            <i class="bi bi-person"></i>
                            <span v-if="ap.responsables.nombres.length">
                                {{ ap.responsables.nombres.join(', ') }}
                            </span>
                            <span v-else>Sin responsable asignado</span>
                        </div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <!-- Detalle del apartado --------------------------------------------->
        <q-dialog v-model="dialogo" full-width>
            <q-card style="max-width: 1100px">
                <q-card-section class="row items-center">
                    <div>
                        <div class="text-h6">
                            <span class="dc-clave dc-clave--sm">{{ detalle.clave }}</span>
                            {{ detalle.nombre }}
                        </div>
                        <div class="text-caption text-grey">{{ detalle.descripcion }}</div>
                    </div>
                    <q-space />
                    <div
                        class="text-h5 text-weight-bold q-mr-md"
                        :style="{ color: colorSemaforo(detalle.semaforo) }"
                    >{{ detalle.medible === false ? '—' : detalle.porcentaje + '%' }}</div>
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 65vh" class="scroll">
                    <q-inner-loading :showing="cargandoDetalle">
                        <q-spinner size="34px" color="primary" />
                    </q-inner-loading>

                    <q-list separator v-if="!cargandoDetalle">
                        <q-item v-for="c in detalle.conceptos" :key="c.id">
                            <q-item-section avatar top>
                                <q-icon
                                    :name="iconoEstado(c.estado)"
                                    :color="colorEstado(c.estado)"
                                    size="22px"
                                />
                            </q-item-section>

                            <q-item-section>
                                <q-item-label>
                                    {{ c.nombre }}
                                    <q-badge
                                        v-if="c.obligatorio"
                                        color="grey-7"
                                        class="q-ml-xs"
                                        label="obligatorio"
                                    />
                                    <q-badge
                                        v-if="c.confidencialidad !== 'interna'"
                                        :color="c.confidencialidad === 'critica' ? 'red-8' : 'orange-8'"
                                        class="q-ml-xs"
                                        :label="c.confidencialidad"
                                    />
                                </q-item-label>
                                <q-item-label caption v-if="c.mensaje">{{ c.mensaje }}</q-item-label>
                                <q-item-label caption class="text-grey-6">
                                    resolvedor: {{ c.tipo_resolvedor }}
                                    <span v-if="c.periodicidad"> · revisión {{ c.periodicidad }}</span>
                                    <span v-if="c.base_legal"> · {{ c.base_legal }}</span>
                                </q-item-label>
                            </q-item-section>

                            <q-item-section side>
                                <q-badge
                                    :color="colorEstado(c.estado)"
                                    :label="etiquetaEstado(c.estado)"
                                    outline
                                />
                                <q-btn
                                    v-if="c.estado === 'sin_fuente'"
                                    flat
                                    dense
                                    size="sm"
                                    icon="person_add"
                                    color="primary"
                                    class="q-mt-xs"
                                    :label="c.metricas && c.metricas.pendiente_id ? 'Editar pendiente' : 'Asignar responsable'"
                                    @click="$refs.pendientes.abrirParaConcepto(c)"
                                />
                                <q-btn
                                    v-if="esRegistroGestionable(c)"
                                    flat
                                    dense
                                    size="sm"
                                    icon="table_view"
                                    color="primary"
                                    class="q-mt-xs"
                                    :label="'Gestionar registros' + (c.metricas && c.metricas.registros !== undefined ? ' (' + c.metricas.registros + ')' : '')"
                                    @click="$refs.registros.abrirParaConcepto(c)"
                                />
                                <q-btn
                                    v-if="esPlantillaGenerable(c)"
                                    flat
                                    dense
                                    size="sm"
                                    icon="description"
                                    color="primary"
                                    class="q-mt-xs"
                                    :loading="generandoSlug === c.slug"
                                    label="Generar documento"
                                    @click="generarDocumento(c)"
                                />
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-dialog>

        <q-inner-loading :showing="loading">
            <q-spinner size="40px" color="primary" />
        </q-inner-loading>

        <!-- Apartado XIII tiene vista dedicada (calendario regulatorio + ficha
             con pagos), no el diálogo genérico de conceptos de arriba. -->
        <dc-concesiones
            ref="concesiones"
            @calendario-cargado="actualizarAlertasXIII"
        />

        <!-- Bandeja de pendientes (Fase 2b): lista global + alta/edición desde
             la tarjeta "sin fuente" de un concepto. -->
        <dc-pendientes-bandeja ref="pendientes" @guardado="alGuardarPendiente" />

        <!-- Registros estructurados (Fase 2c): alta/edición desde la tarjeta de
             un concepto tipo "inventario" (accionistas, capital, actas, poderes,
             contratos). -->
        <dc-registros ref="registros" @guardado="alGuardarPendiente" />
    </div>
</template>

<script>
export default {
    name: 'DcExpediente',

    props: {
        empresaId: { type: Number, default: 0 },
        empresaEtiqueta: { type: String, default: '' },
        mostrarSelector: { type: Boolean, default: false },
    },

    data() {
        return {
            loading: false,
            cargandoDetalle: false,
            dialogo: false,
            empresa: {},
            opcionesEmpresa: [],
            empresaSeleccionada: this.empresaId,
            apartados: [],
            global: { porcentaje: 0, resueltos: 0, obligatorios: 0, semaforo: 'rojo' },
            detalle: { clave: '', nombre: '', descripcion: '', porcentaje: 0, semaforo: 'rojo', conceptos: [] },
            // Semáforo del calendario regulatorio (apartado XIII), aparte de la
            // completitud genérica: aquí lo urgente es la vigencia, no si el
            // registro existe. null mientras no se ha cargado.
            alertasXIII: null,
            // slug del concepto cuyo documento se está generando (spinner del botón).
            generandoSlug: null,
        };
    },

    computed: {
        totalFaltantes() {
            return this.apartados.reduce((n, a) => n + a.faltantes.length, 0);
        },

        badgeAlertasXIII() {
            if (!this.alertasXIII) return '';
            if (this.alertasXIII.vencidas > 0) {
                return this.alertasXIII.vencidas + (this.alertasXIII.vencidas > 1 ? ' vencidas' : ' vencida');
            }
            if (this.alertasXIII.total > 0) {
                return this.alertasXIII.total + ' por vencer';
            }
            return 'al día';
        },
    },

    mounted() {
        this.cargar();
    },

    methods: {
        async cargar(refrescar = false) {
            this.loading = true;
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/tablero', {
                    params: refrescar ? { refrescar: 1 } : {},
                });
                this.empresa = data.empresa;
                this.opcionesEmpresa = data.empresas;
                this.empresaSeleccionada = data.empresa.id;
                this.apartados = data.apartados;
                this.global = data.global;

                if (this.apartados.some((a) => a.clave === 'XIII')) {
                    this.cargarAlertasXIII();
                }
            } catch (e) {
                this.aviso('No se pudo cargar el expediente.', 'negative');
            } finally {
                this.loading = false;
            }
        },

        async cargarAlertasXIII() {
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/concesiones/calendario');
                this.actualizarAlertasXIII(data);
            } catch (e) {
                // Sin permiso de ver el apartado XIII u otro fallo: sin badge, sin ruido.
                this.alertasXIII = null;
            }
        },

        actualizarAlertasXIII(data) {
            this.alertasXIII = {
                total: data.total_alertas,
                vencidas: (data.vigencias.vencidas || []).length,
            };
        },

        async abrirApartado(ap) {
            if (ap.clave === 'XIII') {
                this.$refs.concesiones.abrir();
                return;
            }

            this.detalle = { ...ap, conceptos: [] };
            this.dialogo = true;
            this.cargandoDetalle = true;
            try {
                const { data } = await axios.get(
                    `/documentacion-corporativa/api/apartado/${ap.clave}`
                );
                this.detalle = data;
            } catch (e) {
                this.aviso('No se pudo abrir el apartado.', 'negative');
                this.dialogo = false;
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Un pendiente cambió (nuevo/editado): refresca lo que esté abierto. */
        async alGuardarPendiente() {
            if (this.dialogo && this.detalle.clave) {
                try {
                    const { data } = await axios.get(
                        `/documentacion-corporativa/api/apartado/${this.detalle.clave}`
                    );
                    this.detalle = data;
                } catch (e) {
                    // El modal conserva los datos previos; no interrumpe al usuario.
                }
            }
            this.cargar();
        },

        async cambiarEmpresa(id) {
            try {
                await axios.post('/documentacion-corporativa/api/empresa', { empresa_id: id });
                await this.cargar(true);
            } catch (e) {
                this.aviso('No se pudo cambiar de empresa.', 'negative');
            }
        },

        colorSemaforo(s) {
            return { verde: '#07703a', amarillo: '#b25e00', rojo: '#c62828', gris: '#78909c' }[s] || '#c62828';
        },

        colorQuasar(s) {
            return { verde: 'positive', amarillo: 'warning', rojo: 'negative', gris: 'grey-5' }[s] || 'negative';
        },

        colorEstado(e) {
            return {
                resuelto: 'positive',
                parcial: 'warning',
                vacio: 'grey-6',
                sin_fuente: 'grey-6',
            }[e] || 'grey-6';
        },

        iconoEstado(e) {
            return {
                resuelto: 'check_circle',
                parcial: 'error_outline',
                vacio: 'inbox',
                sin_fuente: 'help_outline',
            }[e] || 'help_outline';
        },

        /** Conceptos "inventario" cuya tabla ya tiene CRUD propio (Fase 2c, item #736). */
        esRegistroGestionable(c) {
            const TABLAS_CON_CRUD = ['dc_accionistas', 'dc_capital_variaciones', 'dc_actas', 'dc_poderes', 'dc_contratos'];
            return c.tipo_resolvedor === 'inventario' && c.metricas && TABLAS_CON_CRUD.includes(c.metricas.tabla);
        },

        /**
         * Conceptos tipo "plantilla" con plantilla YA asignada (Fase 2d, item #737).
         * `estado === 'sin_fuente'` es justo el caso sin plantilla asignada todavía
         * (ver `PlantillaResolver::resolver`); en ese caso no hay nada que generar.
         */
        esPlantillaGenerable(c) {
            return c.tipo_resolvedor === 'plantilla' && c.estado !== 'sin_fuente';
        },

        async generarDocumento(c) {
            this.generandoSlug = c.slug;
            try {
                await axios.post(`/documentacion-corporativa/api/concepto/${c.slug}/generar`);
                this.aviso('Documento generado correctamente.', 'positive');
                await this.alGuardarPendiente();
            } catch (e) {
                const mensaje = e.response && e.response.data && e.response.data.message
                    ? e.response.data.message
                    : 'No se pudo generar el documento.';
                this.aviso(mensaje, 'negative');
            } finally {
                this.generandoSlug = null;
            }
        },

        etiquetaEstado(e) {
            return {
                resuelto: 'resuelto',
                parcial: 'parcial',
                vacio: 'sin registros',
                sin_fuente: 'sin fuente configurada',
            }[e] || e;
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>

<style scoped>
.dc-clave {
    font-weight: 700;
    font-size: 18px;
    line-height: 1;
    min-width: 44px;
    color: #0057a8;
}
.dc-clave--sm {
    font-size: 15px;
    margin-right: 6px;
}
.dc-desc {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.dc-card:hover {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
}
</style>
