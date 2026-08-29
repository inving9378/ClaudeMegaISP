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
                v-hasPermission="'documentacion-corporativa.bitacora.view'"
                flat
                dense
                icon="history"
                color="primary"
                label="Bitácora de accesos"
                class="q-mr-sm"
                @click="$refs.bitacora.abrir()"
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

                                <!-- Repositorio documental (Fase 2a, item #767): subir,
                                     versionar y descargar sólo para conceptos tipo 'documento'. -->
                                <div v-if="c.tipo_resolvedor === 'documento'" class="q-mt-sm dc-repo">
                                    <div
                                        class="dc-dropzone"
                                        :class="{ 'dc-dropzone--over': dragOverConceptoId === c.id }"
                                        @dragover.prevent="dragOverConceptoId = c.id"
                                        @dragleave.prevent="dragOverConceptoId = null"
                                        @drop.prevent="onDrop($event, c)"
                                    >
                                        <q-btn
                                            v-hasPermission="'documentacion-corporativa.documento.upload'"
                                            size="sm"
                                            dense
                                            outline
                                            color="primary"
                                            icon="upload"
                                            label="Subir documento"
                                            :loading="subiendoConceptoId === c.id"
                                            @click="abrirSelector(c)"
                                        />
                                        <span class="text-caption text-grey q-ml-sm">o arrastra el archivo aquí</span>
                                    </div>

                                    <q-list v-if="c.datos && c.datos.length" dense bordered separator class="q-mt-xs">
                                        <q-item v-for="d in c.datos" :key="d.id" dense>
                                            <q-item-section>
                                                <q-item-label class="text-caption">
                                                    {{ d.archivo }}
                                                    <q-badge color="grey-7" class="q-ml-xs" :label="'v' + d.version" />
                                                    <q-badge
                                                        v-if="d.estado !== 'vigente'"
                                                        :color="d.estado === 'vencido' ? 'negative' : 'warning'"
                                                        class="q-ml-xs"
                                                        :label="d.estado === 'vencido' ? 'vencido' : 'por vencer'"
                                                    />
                                                </q-item-label>
                                                <q-item-label caption class="text-grey-6" v-if="d.vigencia_fin">
                                                    vigencia hasta {{ d.vigencia_fin }}
                                                </q-item-label>
                                            </q-item-section>

                                            <q-item-section side>
                                                <div class="row no-wrap q-gutter-xs">
                                                    <q-btn
                                                        v-hasPermission="'documentacion-corporativa.documento.upload'"
                                                        dense flat round size="sm" icon="upload_file"
                                                        @click="abrirSelector(c, d)"
                                                    >
                                                        <q-tooltip>Subir nueva versión</q-tooltip>
                                                    </q-btn>
                                                    <q-btn dense flat round size="sm" icon="history" @click="verVersiones(d)">
                                                        <q-tooltip>Ver versiones</q-tooltip>
                                                    </q-btn>
                                                    <q-btn dense flat round size="sm" icon="download" @click="descargarDocumento(d.id)">
                                                        <q-tooltip>Descargar</q-tooltip>
                                                    </q-btn>
                                                    <q-btn
                                                        v-hasPermission="'documentacion-corporativa.documento.delete'"
                                                        dense flat round size="sm" icon="delete" color="negative"
                                                        @click="eliminarDocumento(d)"
                                                    >
                                                        <q-tooltip>Eliminar</q-tooltip>
                                                    </q-btn>
                                                </div>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </div>
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
                                    v-if="esMapaGestionable(c)"
                                    flat
                                    dense
                                    size="sm"
                                    icon="map"
                                    color="primary"
                                    class="q-mt-xs"
                                    label="Ver mapa"
                                    @click="$refs.mapaActivos.abrirParaConcepto(c)"
                                />
                                <q-btn
                                    v-if="esSolicitudesGestionable(c)"
                                    flat
                                    dense
                                    size="sm"
                                    icon="mail"
                                    color="primary"
                                    class="q-mt-xs"
                                    :label="'Gestionar solicitudes' + (c.metricas && c.metricas.registros !== undefined ? ' (' + c.metricas.registros + ')' : '')"
                                    @click="$refs.solicitudes.abrir()"
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

        <!-- Input de archivo único, reusado por todos los conceptos tipo
             'documento': evita un <input> por fila. `documentoActivo` decide
             si la subida crea un documento nuevo (null) o una versión nueva
             (id del documento sobre el que se hizo clic). -->
        <input
            ref="inputArchivo"
            type="file"
            style="display: none"
            @change="onArchivoSeleccionado"
        />

        <!-- Timeline de versiones de un documento -------------------------->
        <q-dialog v-model="dialogoVersiones">
            <q-card style="min-width: 420px; max-width: 600px">
                <q-card-section class="row items-center">
                    <div class="text-subtitle1">
                        Versiones — {{ versionesInfo.documento ? versionesInfo.documento.archivo_nombre_original : '' }}
                    </div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup />
                </q-card-section>

                <q-separator />

                <q-card-section style="max-height: 55vh" class="scroll">
                    <q-inner-loading :showing="cargandoVersiones">
                        <q-spinner size="30px" color="primary" />
                    </q-inner-loading>

                    <q-timeline v-if="!cargandoVersiones" color="primary">
                        <q-timeline-entry
                            v-for="v in versionesInfo.versiones"
                            :key="v.id"
                            :title="'Versión ' + v.version"
                            :subtitle="formatoFecha(v.created_at) + (v.subido_por ? ' · ' + v.subido_por.name : '')"
                        >
                            <div class="text-caption text-grey">
                                {{ v.archivo_nombre_original }} · {{ formatoBytes(v.bytes) }}
                            </div>
                            <div v-if="v.nota_cambio" class="text-caption q-mt-xs">{{ v.nota_cambio }}</div>
                            <q-btn
                                class="q-mt-xs"
                                size="sm"
                                dense
                                outline
                                color="primary"
                                icon="download"
                                label="Descargar esta versión"
                                @click="descargarDocumento(versionesInfo.documento.id, v.version)"
                            />
                        </q-timeline-entry>
                    </q-timeline>
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
             contratos, activos, activos digitales, inventario de accesos). -->
        <dc-registros ref="registros" @guardado="alGuardarPendiente" />

        <!-- Mapa Leaflet (Fase 3.3): conceptos de dc_activos con config.mapa=true
             (torres, postería, fibra, redes troncales, centros de distribución,
             almacenes y bodegas). -->
        <dc-activos-mapa ref="mapaActivos" />

        <!-- Solicitudes de información recibidas (Fase 5a, item #758) — desde
             la tarjeta del concepto "Registro de solicitudes de información
             recibidas" (apartado XIV). -->
        <dc-solicitudes ref="solicitudes" @guardado="alGuardarPendiente" />

        <!-- Bitácora consultable/exportable (Fase 5c, item #760). -->
        <dc-bitacora ref="bitacora" />
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

            // Repositorio documental (Fase 2a, item #767).
            conceptoActivo: null,
            documentoActivo: null,
            dragOverConceptoId: null,
            subiendoConceptoId: null,
            dialogoVersiones: false,
            cargandoVersiones: false,
            versionesInfo: { documento: null, versiones: [] },
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

        /** Conceptos "inventario" cuya tabla ya tiene CRUD propio (Fase 2c/3.3, items #736/#752). */
        esRegistroGestionable(c) {
            const TABLAS_CON_CRUD = [
                'dc_accionistas', 'dc_capital_variaciones', 'dc_actas', 'dc_poderes', 'dc_contratos',
                'dc_activos', 'dc_activos_digitales', 'dc_inventario_accesos',
            ];
            return c.tipo_resolvedor === 'inventario' && c.metricas && TABLAS_CON_CRUD.includes(c.metricas.tabla);
        },

        /** Conceptos de dc_activos con mapa Leaflet (Fase 3.3, item #752/#783). */
        esMapaGestionable(c) {
            return !!(c.metricas && c.metricas.mapa === true);
        },

        /** Concepto "Registro de solicitudes de información recibidas" (Fase 5a, item #758). */
        esSolicitudesGestionable(c) {
            return c.tipo_resolvedor === 'inventario' && c.metricas && c.metricas.tabla === 'dc_solicitudes';
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

        // ---- Repositorio documental (Fase 2a, item #767) -------------------

        /** Abre el selector de archivo. Sin `documento` = documento nuevo; con `documento` = versión nueva sobre ese. */
        abrirSelector(concepto, documento = null) {
            this.conceptoActivo = concepto;
            this.documentoActivo = documento;
            this.$refs.inputArchivo.value = '';
            this.$refs.inputArchivo.click();
        },

        onArchivoSeleccionado(evento) {
            const archivo = evento.target.files && evento.target.files[0];
            if (archivo) {
                this.subirArchivo(archivo);
            }
        },

        onDrop(evento, concepto) {
            this.dragOverConceptoId = null;
            const archivo = evento.dataTransfer.files && evento.dataTransfer.files[0];
            if (!archivo) return;
            this.conceptoActivo = concepto;
            this.documentoActivo = null;
            this.subirArchivo(archivo);
        },

        async subirArchivo(archivo) {
            const concepto = this.conceptoActivo;
            if (!concepto) return;

            this.subiendoConceptoId = concepto.id;

            const formData = new FormData();
            formData.append('archivo', archivo);
            formData.append('concepto_id', concepto.id);
            if (this.documentoActivo) {
                formData.append('documento_id', this.documentoActivo.id);
            }

            try {
                await axios.post('/documentacion-corporativa/api/documentos', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.aviso('Documento subido correctamente.', 'positive');
                await this.alGuardarPendiente();
            } catch (e) {
                const mensaje = e.response && e.response.data && e.response.data.message
                    ? e.response.data.message
                    : 'No se pudo subir el documento.';
                this.aviso(mensaje, 'negative');
            } finally {
                this.subiendoConceptoId = null;
                this.conceptoActivo = null;
                this.documentoActivo = null;
            }
        },

        async verVersiones(documento) {
            this.dialogoVersiones = true;
            this.cargandoVersiones = true;
            try {
                const { data } = await axios.get(
                    `/documentacion-corporativa/api/documentos/${documento.id}/versiones`
                );
                this.versionesInfo = data;
            } catch (e) {
                this.aviso('No se pudieron cargar las versiones.', 'negative');
                this.dialogoVersiones = false;
            } finally {
                this.cargandoVersiones = false;
            }
        },

        descargarDocumento(documentoId, version = null) {
            const base = `/documentacion-corporativa/api/documentos/${documentoId}`;
            const url = version ? `${base}/versiones/${version}/descargar` : `${base}/descargar`;
            window.open(url, '_blank');
        },

        async eliminarDocumento(documento) {
            if (!confirm(`¿Eliminar "${documento.archivo}"? Podrás verlo en la papelera de datos, no en esta vista.`)) {
                return;
            }
            try {
                await axios.delete(`/documentacion-corporativa/api/documentos/${documento.id}`);
                this.aviso('Documento eliminado.', 'positive');
                await this.alGuardarPendiente();
            } catch (e) {
                const mensaje = e.response && e.response.data && e.response.data.message
                    ? e.response.data.message
                    : 'No se pudo eliminar el documento.';
                this.aviso(mensaje, 'negative');
            }
        },

        formatoBytes(bytes) {
            if (!bytes) return '0 B';
            const unidades = ['B', 'KB', 'MB', 'GB'];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), unidades.length - 1);
            return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${unidades[i]}`;
        },

        formatoFecha(fecha) {
            if (!fecha) return '';
            return new Date(fecha).toLocaleString('es-MX', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
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
.dc-dropzone {
    border: 1px dashed #b0bec5;
    border-radius: 6px;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    transition: border-color 0.15s, background-color 0.15s;
}
.dc-dropzone--over {
    border-color: #0057a8;
    background-color: rgba(0, 87, 168, 0.06);
}
</style>
