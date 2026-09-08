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
                v-hasPermission="'documentacion-corporativa.offboarding.ver'"
                flat
                dense
                icon="person_remove"
                color="primary"
                label="Checklist de offboarding"
                class="q-mr-sm"
                @click="$refs.offboarding.abrir()"
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
                icon="fact_check"
                color="primary"
                label="Acuse de avance"
                class="q-mr-sm"
                :loading="exportandoAcuse"
                @click="exportarAcuse"
            >
                <q-tooltip>Genera el acuse de avance en PDF (evidencia para la mesa directiva)</q-tooltip>
            </q-btn>

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

        <!-- Tablero global — 5 KPIs (decisión de Irving, item #9990531 q4) --->
        <div class="row q-col-gutter-sm q-mb-md">
            <div class="col-6 col-sm">
                <q-card flat bordered>
                    <q-card-section class="text-center q-pa-sm">
                        <div class="text-h5 text-weight-bold" :style="{ color: colorSemaforo(global.semaforo) }">
                            {{ global.medible === false ? '—' : global.porcentaje + '%' }}
                        </div>
                        <div class="text-caption text-grey">Avance global</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-6 col-sm">
                <q-card flat bordered>
                    <q-card-section class="text-center q-pa-sm">
                        <div class="text-h5 text-weight-bold text-positive">{{ global.al_dia || 0 }}/{{ apartados.length }}</div>
                        <div class="text-caption text-grey">Apartados al día</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-6 col-sm">
                <q-card flat bordered>
                    <q-card-section class="text-center q-pa-sm">
                        <div class="text-h5 text-weight-bold text-warning">{{ global.en_proceso || 0 }}</div>
                        <div class="text-caption text-grey">En proceso</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-6 col-sm">
                <q-card flat bordered>
                    <q-card-section class="text-center q-pa-sm">
                        <div class="text-h5 text-weight-bold text-grey-8">{{ global.sin_iniciar || 0 }}</div>
                        <div class="text-caption text-grey">Sin iniciar</div>
                    </q-card-section>
                </q-card>
            </div>

            <div class="col-6 col-sm">
                <q-card flat bordered>
                    <q-card-section class="text-center q-pa-sm relative-position">
                        <q-btn
                            v-if="empresa.puede_editar_plazo"
                            flat
                            dense
                            round
                            size="sm"
                            icon="edit_calendar"
                            color="grey-7"
                            class="absolute-top-right q-ma-xs"
                            style="z-index: 1"
                            @click="abrirEditarPlazo"
                        >
                            <q-tooltip>Capturar/editar fecha de inicio del plazo (180 días hábiles)</q-tooltip>
                        </q-btn>

                        <div
                            class="text-h5 text-weight-bold"
                            :class="plazoVencidoBool ? 'text-negative' : 'text-grey-8'"
                        >
                            {{ diasRestantesSinDato ? 'N/D' : global.dias_restantes }}
                            <q-tooltip v-if="diasRestantesSinDato">
                                Sin fecha de inicio del plazo de 180 días hábiles registrada todavía.
                            </q-tooltip>
                            <q-tooltip v-else-if="global.fecha_limite_plazo">
                                Vence el {{ global.fecha_limite_plazo }}{{ plazoVencidoBool ? ' · plazo vencido' : '' }}
                            </q-tooltip>
                        </div>
                        <div class="text-caption text-grey">Días restantes</div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <!-- Captura de fecha_inicio_plazo (Fase 3, item #9990575): dato legal
             (fecha del oficio de la mesa directiva), nunca inventado — se
             captura solo si un admin lo tiene a la mano, y puede limpiarse. -->
        <q-dialog v-model="dialogoPlazo" persistent>
            <q-card style="min-width: 380px; max-width: 460px">
                <q-card-section class="row items-center">
                    <div class="text-subtitle1">Fecha de inicio del plazo (180 días hábiles)</div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup :disable="guardandoPlazo" />
                </q-card-section>

                <q-separator />

                <q-card-section>
                    <div class="text-caption text-grey q-mb-sm">
                        Es la fecha del oficio de la mesa directiva que activa el plazo. Captúrala
                        solo si la tienes a la mano — no se debe inventar. Puedes dejarla en blanco
                        para quitarla si se capturó por error.
                    </div>

                    <q-input
                        v-model="fechaInicioPlazoInput"
                        type="date"
                        outlined
                        dense
                        label="Fecha del oficio"
                        clearable
                        :error="!!erroresPlazo"
                        :error-message="erroresPlazo"
                        @update:model-value="erroresPlazo = null"
                    />
                </q-card-section>

                <q-separator />

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup :disable="guardandoPlazo" />
                    <q-btn
                        unelevated
                        color="primary"
                        label="Guardar"
                        :loading="guardandoPlazo"
                        @click="guardarPlazo"
                    />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Filtros por estado + toggle Tarjetas/Lista (Fase B, item #9990531) -->
        <div class="row items-center q-gutter-sm q-mb-md">
            <q-chip
                v-for="f in filtrosEstado"
                :key="f.valor"
                clickable
                :outline="estadoFiltro !== f.valor"
                :color="estadoFiltro === f.valor ? f.color : 'grey-4'"
                :text-color="estadoFiltro === f.valor ? 'white' : 'grey-8'"
                @click="estadoFiltro = f.valor"
            >
                {{ f.etiqueta }} ({{ f.valor === 'todos' ? apartados.length : apartados.filter((a) => a.estado === f.valor).length }})
            </q-chip>

            <q-space />

            <q-btn-toggle
                v-model="vistaTablero"
                dense
                no-caps
                unelevated
                toggle-color="primary"
                color="white"
                text-color="grey-8"
                :options="[
                    { label: 'Tarjetas', value: 'tarjetas', icon: 'grid_view' },
                    { label: 'Lista', value: 'lista', icon: 'view_list' },
                ]"
            />
        </div>

        <!-- Índice de apartados ---------------------------------------------->
        <div v-if="!loading && apartados.length === 0" class="text-center q-pa-xl text-grey">
            <i class="bi bi-folder-x" style="font-size:34px"></i>
            <div class="q-mt-sm">No tienes permiso para ver ningún apartado de este expediente.</div>
        </div>

        <div v-else-if="!loading && apartadosFiltrados.length === 0" class="text-center q-pa-xl text-grey">
            <i class="bi bi-filter-circle" style="font-size:34px"></i>
            <div class="q-mt-sm">Ningún apartado coincide con el filtro seleccionado.</div>
        </div>

        <!-- Vista Tarjetas: entra al detalle de un vistazo. -->
        <div v-else-if="vistaTablero === 'tarjetas'" class="dc-grid">
            <q-card
                v-for="ap in apartadosFiltrados"
                :key="ap.clave"
                flat
                bordered
                class="dc-card cursor-pointer"
                :style="{ borderLeft: '4px solid ' + colorSemaforo(ap.semaforo) }"
                @click="abrirApartado(ap)"
            >
                <q-card-section class="dc-card__body">
                    <div class="row items-center no-wrap">
                        <q-chip dense square color="blue-1" text-color="primary" class="text-weight-bold">{{ ap.clave }}</q-chip>
                        <q-space />
                        <q-badge :color="colorEstadoApartado(ap.estado)" :label="etiquetaEstadoApartado(ap.estado)" />
                    </div>

                    <div class="dc-card__titulo q-mt-sm">{{ ap.nombre }}</div>

                    <div class="row items-center q-mt-sm text-caption text-grey">
                        <div>{{ ap.resueltos }}/{{ ap.obligatorios }} conceptos</div>
                        <q-space />
                        <div class="text-weight-bold" :style="{ color: colorSemaforo(ap.semaforo) }">
                            {{ ap.medible === false ? '—' : ap.porcentaje + '%' }}
                        </div>
                    </div>

                    <q-linear-progress
                        :value="ap.medible === false ? 0 : ap.porcentaje / 100"
                        :color="colorQuasar(ap.semaforo)"
                        size="6px"
                        rounded
                        class="q-mt-xs"
                    />

                    <div class="text-caption q-mt-sm" :class="ap.medible !== false && ap.faltantes.length ? 'text-negative' : 'text-positive'">
                        <span v-if="ap.medible === false">Sin obligatorios que medir</span>
                        <span v-else-if="ap.faltantes.length">Faltan {{ ap.faltantes.length }} de {{ ap.obligatorios }} obligatorios</span>
                        <span v-else>{{ ap.obligatorios }} de {{ ap.obligatorios }} completos</span>
                    </div>

                    <div v-if="ap.clave === 'XIII' && alertasXIII" class="q-mt-xs">
                        <q-badge
                            :color="alertasXIII.vencidas > 0 ? 'negative' : (alertasXIII.total > 0 ? 'warning' : 'positive')"
                            :label="badgeAlertasXIII"
                        />
                    </div>

                    <q-separator class="dc-card__separador" />

                    <div class="dc-card__pie row items-center">
                        <template v-if="ap.responsables.nombres.length">
                            <q-avatar size="22px" color="blue-1" text-color="primary" class="text-caption">
                                {{ iniciales(ap.responsables.nombres[0]) }}
                            </q-avatar>
                            <div class="text-caption text-grey q-ml-xs ellipsis">
                                {{ ap.responsables.nombres.join(', ') }}
                            </div>
                        </template>
                        <q-btn
                            v-else
                            flat
                            dense
                            no-caps
                            size="sm"
                            icon="person_add"
                            color="primary"
                            label="Asignar responsable"
                            @click.stop="abrirApartado(ap)"
                        />
                        <q-space />
                        <q-icon name="chevron_right" color="grey-6" />
                    </div>
                </q-card-section>
            </q-card>
        </div>

        <!-- Vista Lista: para administrar, ordenable. -->
        <q-table
            v-else
            flat
            bordered
            :rows="apartadosFiltrados"
            :columns="columnasLista"
            row-key="clave"
            :pagination="{ rowsPerPage: 0, sortBy: 'porcentaje', descending: false }"
            hide-pagination
            class="cursor-pointer"
            @row-click="(evt, row) => abrirApartado(row)"
        >
            <template #body-cell-estado="props">
                <q-td :props="props">
                    <q-badge :color="colorEstadoApartado(props.row.estado)" :label="etiquetaEstadoApartado(props.row.estado)" />
                </q-td>
            </template>
            <template #body-cell-porcentaje="props">
                <q-td :props="props">
                    {{ props.row.medible === false ? '—' : props.row.porcentaje + '%' }}
                </q-td>
            </template>
            <template #body-cell-responsable="props">
                <q-td :props="props">
                    <span v-if="props.row.responsables.nombres.length">{{ props.row.responsables.nombres.join(', ') }}</span>
                    <span v-else class="text-grey">Sin responsable</span>
                </q-td>
            </template>
            <template #body-cell-movimiento="props">
                <q-td :props="props">{{ diasSinMovimientoTexto(props.row) }}</q-td>
            </template>
        </q-table>

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

                    <!-- Exportación agregada del apartado (Fase 1.5c, item #787,
                         backend #785): TODOS los conceptos ya resueltos en un solo
                         archivo, PDF o Excel. -->
                    <q-btn-dropdown
                        flat
                        dense
                        icon="download"
                        label="Exportar apartado"
                        color="primary"
                        class="q-mr-sm"
                        :loading="!!exportandoApartado"
                    >
                        <q-list>
                            <q-item clickable v-close-popup @click="exportarApartado('pdf')">
                                <q-item-section avatar><q-icon name="picture_as_pdf" /></q-item-section>
                                <q-item-section>PDF</q-item-section>
                            </q-item>
                            <q-item clickable v-close-popup @click="exportarApartado('excel')">
                                <q-item-section avatar><q-icon name="table_view" /></q-item-section>
                                <q-item-section>Excel</q-item-section>
                            </q-item>
                        </q-list>
                    </q-btn-dropdown>

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
                                <!-- Detalle nominal de cartera (Fase 1.5c, item #787,
                                     backend #786): expone identidad de clientes, por
                                     eso pide permiso de descarga + justificación. -->
                                <q-btn
                                    v-if="esCarteraNominal(c)"
                                    v-hasPermission="'documentacion-corporativa.documento.download'"
                                    flat
                                    dense
                                    size="sm"
                                    icon="badge"
                                    color="primary"
                                    class="q-mt-xs"
                                    label="Ver detalle nominal"
                                    @click="abrirDetalleNominal()"
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

        <!-- Justificación para el detalle nominal de cartera (Fase 1.5c, item #787,
             backend #786) — regla LFPDPPP: no se genera el archivo sin motivo. -->
        <q-dialog v-model="dialogoNominal" persistent>
            <q-card style="min-width: 420px; max-width: 520px">
                <q-card-section class="row items-center">
                    <div class="text-subtitle1">Detalle nominal — Cartera de clientes</div>
                    <q-space />
                    <q-btn flat dense icon="close" v-close-popup :disable="exportandoNominal" />
                </q-card-section>

                <q-separator />

                <q-card-section>
                    <div class="text-caption text-grey q-mb-sm">
                        Este archivo incluye nombre y saldo de cada cliente con adeudo. Indica el
                        motivo de la consulta antes de generarlo.
                    </div>

                    <q-input
                        v-model="justificacionNominal"
                        type="textarea"
                        autogrow
                        outlined
                        dense
                        label="Justificación *"
                        :error="!!erroresNominal"
                        :error-message="erroresNominal"
                        @update:model-value="erroresNominal = null"
                    />

                    <q-option-group
                        v-model="formatoNominal"
                        :options="[
                            { label: 'Excel', value: 'excel' },
                            { label: 'PDF', value: 'pdf' },
                        ]"
                        color="primary"
                        inline
                        dense
                        class="q-mt-sm"
                    />
                </q-card-section>

                <q-separator />

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup :disable="exportandoNominal" />
                    <q-btn
                        unelevated
                        color="primary"
                        label="Confirmar y descargar"
                        :loading="exportandoNominal"
                        @click="confirmarDetalleNominal"
                    />
                </q-card-actions>
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

        <!-- Checklist de offboarding (Fase 5d-1, apartado XII, item #815). -->
        <dc-offboarding ref="offboarding" />
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
            global: {
                porcentaje: 0, resueltos: 0, obligatorios: 0, semaforo: 'rojo',
                al_dia: 0, en_proceso: 0, sin_iniciar: 0, dias_restantes: null,
            },
            detalle: { clave: '', nombre: '', descripcion: '', porcentaje: 0, semaforo: 'rojo', conceptos: [] },

            // Tablero — Fase B (item #9990531): filtro por estado + toggle de vista.
            vistaTablero: 'tarjetas',
            estadoFiltro: 'todos',
            filtrosEstado: [
                { valor: 'todos', etiqueta: 'Todos', color: 'primary' },
                { valor: 'sin_iniciar', etiqueta: 'Sin iniciar', color: 'grey-7' },
                { valor: 'en_proceso', etiqueta: 'En proceso', color: 'warning' },
                { valor: 'al_dia', etiqueta: 'Al día', color: 'positive' },
            ],
            columnasLista: [
                { name: 'clave', label: 'Apartado', field: 'clave', align: 'left', sortable: true },
                { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left', sortable: true },
                { name: 'estado', label: 'Estado', field: 'estado', align: 'left', sortable: true },
                { name: 'porcentaje', label: '%', field: 'porcentaje', align: 'right', sortable: true },
                { name: 'faltantes', label: 'Obligatorios faltantes', field: (row) => row.faltantes.length, align: 'right', sortable: true },
                { name: 'responsable', label: 'Responsable', field: () => '', align: 'left' },
                { name: 'movimiento', label: 'Días sin movimiento', field: 'dias_sin_movimiento', align: 'right', sortable: true },
            ],
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

            // Exportación de apartado + detalle nominal (Fase 1.5c, item #787).
            exportandoApartado: null,
            dialogoNominal: false,
            justificacionNominal: '',
            formatoNominal: 'excel',
            erroresNominal: null,
            exportandoNominal: false,

            // Acuse de avance con corte a fecha (item #9990551).
            exportandoAcuse: false,

            // Captura de fecha_inicio_plazo — plazo de 180 días hábiles (Fase 3, item #9990575).
            dialogoPlazo: false,
            fechaInicioPlazoInput: null,
            erroresPlazo: null,
            guardandoPlazo: false,
        };
    },

    computed: {
        totalFaltantes() {
            return this.apartados.reduce((n, a) => n + a.faltantes.length, 0);
        },

        /** Apartados visibles tras el filtro por estado, con "días sin movimiento" ya calculado (Fase B, item #9990531). */
        apartadosFiltrados() {
            const base = this.estadoFiltro === 'todos'
                ? this.apartados
                : this.apartados.filter((a) => a.estado === this.estadoFiltro);

            return base.map((a) => ({ ...a, dias_sin_movimiento: this.diasSinMovimiento(a) }));
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

        /** Sin fecha_inicio_plazo capturada: sigue siendo 'N/D', NO un bug (Fase 3, item #9990575). */
        diasRestantesSinDato() {
            return this.global.dias_restantes === null || this.global.dias_restantes === undefined;
        },

        plazoVencidoBool() {
            return this.global.plazo_vencido === true;
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

        // ---- Captura de fecha_inicio_plazo — plazo 180d hábiles (Fase 3, item #9990575) --

        abrirEditarPlazo() {
            this.fechaInicioPlazoInput = this.empresa.fecha_inicio_plazo || null;
            this.erroresPlazo = null;
            this.dialogoPlazo = true;
        },

        async guardarPlazo() {
            this.guardandoPlazo = true;
            try {
                await axios.put('/documentacion-corporativa/api/empresa/plazo', {
                    fecha_inicio_plazo: this.fechaInicioPlazoInput || null,
                });
                this.dialogoPlazo = false;
                this.aviso('Fecha de inicio del plazo guardada.', 'positive');
                await this.cargar(true);
            } catch (e) {
                this.erroresPlazo = (e.response && e.response.data && e.response.data.message)
                    || 'No se pudo guardar la fecha.';
            } finally {
                this.guardandoPlazo = false;
            }
        },

        colorSemaforo(s) {
            return { verde: '#07703a', amarillo: '#b25e00', rojo: '#c62828', gris: '#78909c' }[s] || '#c62828';
        },

        colorQuasar(s) {
            return { verde: 'positive', amarillo: 'warning', rojo: 'negative', gris: 'grey-5' }[s] || 'negative';
        },

        /** Estado del APARTADO (3 valores, decisión de Irving #9990531 q2) — no confundir con el estado del CONCEPTO (colorEstado/etiquetaEstado, más abajo). */
        colorEstadoApartado(estado) {
            return { al_dia: 'positive', en_proceso: 'warning', sin_iniciar: 'grey-7' }[estado] || 'grey-7';
        },

        etiquetaEstadoApartado(estado) {
            return { al_dia: 'Al día', en_proceso: 'En proceso', sin_iniciar: 'Sin iniciar' }[estado] || estado;
        },

        /** Iniciales (máx. 2) para el avatar del responsable en la tarjeta. */
        iniciales(nombre) {
            if (!nombre) return '';
            return nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
        },

        /** Días desde `fecha_ultima_actualizacion` (documento subido o pendiente tocado). null = sin datos. */
        diasSinMovimiento(ap) {
            if (!ap.fecha_ultima_actualizacion) return null;
            const ms = Date.now() - new Date(ap.fecha_ultima_actualizacion.replace(' ', 'T')).getTime();
            return Math.max(0, Math.floor(ms / 86400000));
        },

        diasSinMovimientoTexto(ap) {
            const dias = ap.dias_sin_movimiento;
            if (dias === null || dias === undefined) return 'Sin datos';
            return dias + (dias === 1 ? ' día' : ' días');
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

        // ---- Exportación de apartado + detalle nominal (Fase 1.5c, item #787) --

        /** Sólo el concepto "Cartera de clientes" del apartado IV (item #786). */
        esCarteraNominal(c) {
            return this.detalle.clave === 'IV' && c.slug === 'cartera-de-clientes';
        },

        async exportarApartado(formato) {
            if (!this.detalle.clave || this.exportandoApartado) return;
            this.exportandoApartado = formato;
            try {
                const response = await axios.get(
                    `/documentacion-corporativa/api/apartado/${this.detalle.clave}/exportar`,
                    { params: { formato }, responseType: 'blob' }
                );
                const ext = formato === 'excel' ? 'xlsx' : 'pdf';
                this.descargarBlob(response, `dc-apartado-${this.detalle.clave.toLowerCase()}.${ext}`);
            } catch (e) {
                this.aviso(await this.mensajeErrorBlob(e, 'No se pudo exportar el apartado.'), 'negative');
            } finally {
                this.exportandoApartado = null;
            }
        },

        abrirDetalleNominal() {
            this.justificacionNominal = '';
            this.formatoNominal = 'excel';
            this.erroresNominal = null;
            this.dialogoNominal = true;
        },

        async confirmarDetalleNominal() {
            const justificacion = (this.justificacionNominal || '').trim();
            if (!justificacion) {
                this.erroresNominal = 'La justificación es obligatoria.';
                return;
            }

            this.exportandoNominal = true;
            try {
                const response = await axios.get(
                    '/documentacion-corporativa/api/apartado/iv/cartera/detalle-nominal',
                    { params: { justificacion, formato: this.formatoNominal }, responseType: 'blob' }
                );
                const ext = this.formatoNominal === 'excel' ? 'xlsx' : 'pdf';
                this.descargarBlob(response, `dc-cartera-detalle-nominal.${ext}`);
                this.dialogoNominal = false;
            } catch (e) {
                // 422 (falta justificación) o 403 (sin permiso, por si el botón
                // igual llegó a mostrarse): mensaje inline, el modal NO se cierra.
                this.erroresNominal = await this.mensajeErrorBlob(e, 'No se pudo generar el detalle nominal.');
            } finally {
                this.exportandoNominal = false;
            }
        },

        /** Acuse de avance global en PDF, con corte al día de hoy (item #9990551). */
        async exportarAcuse() {
            if (this.exportandoAcuse) return;
            this.exportandoAcuse = true;
            try {
                const response = await axios.get(
                    '/documentacion-corporativa/api/acuse/exportar',
                    { responseType: 'blob' }
                );
                this.descargarBlob(response, 'dc-acuse-avance.pdf');
            } catch (e) {
                this.aviso(await this.mensajeErrorBlob(e, 'No se pudo generar el acuse de avance.'), 'negative');
            } finally {
                this.exportandoAcuse = false;
            }
        },

        /** El backend responde JSON de error pero `responseType: 'blob'` lo envuelve en un Blob. */
        async mensajeErrorBlob(e, fallback) {
            const data = e.response && e.response.data;
            if (data instanceof Blob) {
                try {
                    const json = JSON.parse(await data.text());
                    if (json && json.message) return json.message;
                } catch (err) {
                    // No era JSON: se queda con el fallback.
                }
            } else if (data && data.message) {
                return data.message;
            }
            return fallback;
        },

        /** Descarga un blob de axios como archivo, usando el filename del header si viene. */
        descargarBlob(response, filenameFallback) {
            const disposition = response.headers && response.headers['content-disposition'];
            let filename = filenameFallback;
            if (disposition) {
                const match = disposition.match(/filename="?([^"; ]+)"?/i);
                if (match && match[1]) filename = match[1];
            }
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
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

/* Fase A (item #9990531) — el título se desbordaba fuera de la tarjeta,
   partido a un carácter por renglón: `.col` de Quasar en un `.row` no
   encoge por debajo del contenido sin `min-width:0` explícito. Grid propio
   en vez de row/col + min-width:0 en cada hijo lo corrige de raíz. */
.dc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    align-items: stretch;
}
.dc-card {
    min-width: 0;
    display: flex;
    flex-direction: column;
    height: auto;
}
.dc-card__body {
    min-width: 0;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.dc-card__titulo {
    min-width: 0;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 14px;
    font-weight: 500;
}
.dc-card__separador {
    margin-top: auto;
}
.dc-card__pie {
    padding-top: 8px;
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
