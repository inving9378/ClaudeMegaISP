<template>
    <div class="smart-import-wrapper">

        <!-- Encabezado de página -->
        <div class="si-page-header q-px-lg q-pt-lg q-pb-md">
            <div class="row items-center q-gutter-sm">
                <div class="si-header-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div>
                    <div class="text-h6 text-weight-bold si-title">Importación Inteligente</div>
                    <div class="text-caption text-grey-6">
                        Importa datos desde SQL, JSON, XLSX, CSV o ZIP con detección automática de conflictos
                    </div>
                </div>
            </div>
        </div>

        <!-- Stepper principal -->
        <div class="q-px-lg q-pb-lg">
            <q-stepper
                v-model="step"
                ref="stepper"
                color="primary"
                animated
                flat
                header-nav
                class="si-stepper"
            >
                <!-- ─── PASO 1: Subir archivo ─── -->
                <q-step
                    :name="1"
                    title="Subir archivo"
                    icon="cloud_upload"
                    :done="step > 1"
                >
                    <div class="row q-col-gutter-lg q-mt-sm">
                        <!-- Uploader -->
                        <div class="col-12 col-md-8">
                            <q-card flat bordered class="si-upload-card">
                                <q-card-section class="q-pa-lg">
                                    <div class="text-subtitle1 text-weight-medium q-mb-md">
                                        <i class="fas fa-upload text-primary q-mr-sm"></i>
                                        Selecciona o arrastra el archivo
                                    </div>
                                    <q-uploader
                                        ref="uploader"
                                        label="Arrastra tu archivo aquí o haz clic para seleccionar"
                                        :url="uploadUrl"
                                        field-name="file"
                                        :headers="uploadHeaders"
                                        accept=".sql,.json,.xlsx,.xls,.csv,.zip"
                                        :max-files="1"
                                        :max-file-size="2147483648"
                                        auto-upload
                                        color="primary"
                                        class="full-width si-uploader"
                                        @uploaded="onUploaded"
                                        @failed="onUploadFailed"
                                        @rejected="onRejected"
                                    />
                                </q-card-section>
                            </q-card>

                            <!-- Progreso de análisis -->
                            <q-card v-if="analyzing" flat bordered class="q-mt-md si-analyzing-card">
                                <q-card-section class="row items-center q-gutter-md q-pa-md">
                                    <q-circular-progress
                                        indeterminate
                                        size="32px"
                                        color="primary"
                                    />
                                    <div>
                                        <div class="text-body2 text-weight-medium">Analizando estructura del archivo...</div>
                                        <div class="text-caption text-grey-6">
                                            Para archivos grandes (500 MB+) esto puede tardar un minuto
                                        </div>
                                    </div>
                                </q-card-section>
                            </q-card>
                        </div>

                        <!-- Panel de formatos -->
                        <div class="col-12 col-md-4">
                            <q-card flat bordered class="si-formats-card">
                                <q-card-section>
                                    <div class="text-subtitle2 text-weight-medium q-mb-md">
                                        <i class="fas fa-info-circle text-primary q-mr-xs"></i>
                                        Formatos aceptados
                                    </div>
                                    <q-list dense>
                                        <q-item v-for="fmt in formatList" :key="fmt.ext" class="q-px-none">
                                            <q-item-section avatar style="min-width:36px">
                                                <q-badge
                                                    :color="fmt.color"
                                                    :label="fmt.ext"
                                                    class="text-weight-bold"
                                                />
                                            </q-item-section>
                                            <q-item-section>
                                                <q-item-label class="text-caption text-grey-8">
                                                    {{ fmt.desc }}
                                                </q-item-label>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-card-section>

                                <q-separator />

                                <q-card-section class="q-pa-sm">
                                    <div class="text-caption text-grey-6 q-px-sm">
                                        <i class="fas fa-shield-alt q-mr-xs"></i>
                                        Tamaño máximo: <strong>2 GB</strong><br>
                                        <i class="fas fa-memory q-mr-xs"></i>
                                        Dumps grandes: streaming carácter a carácter
                                    </div>
                                </q-card-section>
                            </q-card>
                        </div>
                    </div>
                </q-step>

                <!-- ─── PASO 2: Reporte y conflictos ─── -->
                <q-step
                    :name="2"
                    title="Reporte y conflictos"
                    icon="fact_check"
                    :done="step > 2"
                >
                    <div v-if="report.length === 0" class="si-empty-state q-pa-xl text-center">
                        <i class="fas fa-inbox si-empty-icon text-grey-4"></i>
                        <div class="text-h6 text-grey-5 q-mt-md">Sin datos analizados</div>
                        <div class="text-caption text-grey-5">Vuelve al paso anterior y sube un archivo</div>
                    </div>

                    <div v-else>
                        <!-- Toolbar de acciones -->
                        <div class="row items-center q-mb-md q-col-gutter-sm">
                            <div class="col-auto">
                                <q-btn
                                    color="deep-purple"
                                    icon="auto_awesome"
                                    label="Resolver con IA"
                                    :loading="aiLoading"
                                    :disable="!hasConflicts"
                                    unelevated
                                    size="sm"
                                    @click="resolveAllWithAI"
                                />
                            </div>
                            <div class="col-auto">
                                <q-btn
                                    flat
                                    color="primary"
                                    icon="refresh"
                                    label="Recalcular"
                                    size="sm"
                                    @click="loadPreview(false)"
                                />
                            </div>
                            <q-space />
                            <div class="col-auto">
                                <div class="row q-gutter-sm">
                                    <q-chip dense color="blue-1" text-color="blue-9" icon="table_rows">
                                        {{ report.length }} tablas
                                    </q-chip>
                                    <q-chip dense color="grey-3" text-color="grey-8" icon="format_list_numbered">
                                        {{ totalRows.toLocaleString() }} filas
                                    </q-chip>
                                    <q-chip
                                        v-if="hasConflicts"
                                        dense
                                        color="orange-1"
                                        text-color="orange-9"
                                        icon="warning"
                                    >
                                        {{ conflictCount }} conflictos
                                    </q-chip>
                                </div>
                            </div>
                        </div>

                        <!-- Toggle: limpiar datos antes de importar -->
                        <q-card
                            flat
                            bordered
                            class="q-mb-md"
                            :class="truncateBefore ? 'si-danger-card' : 'si-option-card'"
                        >
                            <q-card-section class="q-pa-md">
                                <div class="row items-start q-gutter-md">
                                    <div class="col-auto">
                                        <q-toggle
                                            v-model="truncateBefore"
                                            color="negative"
                                            keep-color
                                        />
                                    </div>
                                    <div class="col">
                                        <div class="row items-center q-gutter-xs">
                                            <span class="text-body2 text-weight-medium">
                                                Limpiar datos preexistentes antes de importar
                                            </span>
                                            <q-icon name="help_outline" color="grey-5" size="16px">
                                                <q-tooltip max-width="300px" class="text-caption">
                                                    Antes de importar cada tabla se crea automáticamente
                                                    una tabla de backup (bk_…) con los datos actuales y
                                                    se hace TRUNCATE de la original. Útil para migración
                                                    desde producción. No aplica a tablas vacías.
                                                </q-tooltip>
                                            </q-icon>
                                        </div>
                                        <div
                                            v-if="truncateBefore"
                                            class="text-caption text-negative q-mt-xs"
                                        >
                                            <i class="fas fa-exclamation-triangle q-mr-xs"></i>
                                            Se crearán tablas <strong>bk_YYMMDD_…</strong> con los datos
                                            actuales y se vaciarán las tablas destino antes de importar.
                                        </div>
                                        <div v-else class="text-caption text-grey-6 q-mt-xs">
                                            Desactivado — los registros existentes se procesarán según
                                            la acción configurada por tabla (reemplazar / omitir / duplicar).
                                        </div>
                                    </div>
                                </div>
                            </q-card-section>
                        </q-card>

                        <!-- Tabla de reporte -->
                        <q-table
                            :rows="report"
                            :columns="reportColumns"
                            row-key="table"
                            flat
                            dense
                            bordered
                            :pagination="{ rowsPerPage: 20 }"
                            class="si-report-table"
                        >
                            <template v-slot:body-cell-status="props">
                                <q-td :props="props">
                                    <q-badge
                                        :color="rowStatusColor(props.row)"
                                        :label="rowStatusLabel(props.row)"
                                    />
                                </q-td>
                            </template>
                            <template v-slot:body-cell-action="props">
                                <q-td :props="props">
                                    <q-select
                                        v-model="defaultAction[props.row.table]"
                                        :options="actionOptions"
                                        dense
                                        outlined
                                        emit-value
                                        map-options
                                        style="min-width: 180px"
                                        :disable="truncateBefore"
                                    />
                                </q-td>
                            </template>
                        </q-table>

                        <!-- Detalle de conflictos -->
                        <div v-if="hasConflicts" class="q-mt-lg">
                            <div class="row items-center q-mb-sm q-gutter-xs">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                <span class="text-subtitle2">
                                    Conflictos detectados ({{ conflictCount }})
                                </span>
                            </div>
                            <q-list bordered separator class="rounded-borders">
                                <q-expansion-item
                                    v-for="(items, table) in conflicts"
                                    :key="table"
                                    :label="`${table}  ·  ${items.length} conflicto(s)`"
                                    expand-separator
                                    icon="warning"
                                    header-class="text-weight-medium"
                                >
                                    <q-list dense class="q-pl-md">
                                        <q-item
                                            v-for="item in items"
                                            :key="`${table}-${item.index}`"
                                            class="q-py-sm"
                                        >
                                            <q-item-section>
                                                <q-item-label>
                                                    <strong>Fila #{{ item.index + 1 }}</strong>
                                                    <span class="text-grey-6 q-mx-xs">·</span>
                                                    coincide en
                                                    <q-chip
                                                        v-for="k in item.matched"
                                                        :key="k"
                                                        dense
                                                        color="orange-2"
                                                        text-color="orange-10"
                                                        size="sm"
                                                    >{{ k }}</q-chip>
                                                </q-item-label>
                                                <q-item-label caption>
                                                    <div class="row q-col-gutter-sm q-mt-xs">
                                                        <div class="col-6">
                                                            <div class="text-caption text-grey-6 q-mb-xs">
                                                                <i class="fas fa-database q-mr-xs"></i>Existente
                                                            </div>
                                                            <pre class="conflict-pre">{{ pretty(item.existing) }}</pre>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="text-caption text-grey-6 q-mb-xs">
                                                                <i class="fas fa-file-import q-mr-xs"></i>Nuevo
                                                            </div>
                                                            <pre class="conflict-pre">{{ pretty(item.incoming) }}</pre>
                                                        </div>
                                                    </div>
                                                </q-item-label>
                                                <q-item-label
                                                    v-if="aiRecommendations[table]?.[item.index]"
                                                    class="q-mt-xs"
                                                >
                                                    <q-chip color="purple-1" text-color="purple-9" icon="auto_awesome" size="sm">
                                                        IA: <strong class="q-ml-xs">{{ aiRecommendations[table][item.index].accion }}</strong>
                                                    </q-chip>
                                                    <span class="text-caption text-grey-6 q-ml-sm">
                                                        {{ aiRecommendations[table][item.index].razon }}
                                                    </span>
                                                </q-item-label>
                                            </q-item-section>
                                            <q-item-section side>
                                                <q-select
                                                    v-model="perRowAction[table][item.index]"
                                                    :options="perRowOptions"
                                                    dense
                                                    outlined
                                                    emit-value
                                                    map-options
                                                    style="min-width: 160px"
                                                    @update:model-value="onPerRowChange(table, item)"
                                                />
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-expansion-item>
                            </q-list>
                        </div>

                        <!-- Botón ejecutar -->
                        <div class="row justify-end q-mt-lg">
                            <q-btn
                                color="primary"
                                icon="play_arrow"
                                label="Ejecutar importación"
                                :loading="executing"
                                unelevated
                                size="md"
                                padding="sm xl"
                                @click="execute"
                            >
                                <template v-slot:loading>
                                    <q-spinner-hourglass class="q-mr-sm" />
                                    Encolando...
                                </template>
                            </q-btn>
                        </div>
                    </div>
                </q-step>

                <!-- ─── PASO 3: Ejecución ─── -->
                <q-step
                    :name="3"
                    title="Ejecución"
                    icon="play_circle"
                >
                    <div v-if="!jobId" class="text-grey-6 q-pa-xl text-center">
                        <q-circular-progress indeterminate size="40px" color="primary" class="q-mb-md" />
                        <div class="text-body2">Iniciando importación...</div>
                    </div>

                    <div v-else>
                        <!-- Barra de progreso principal -->
                        <q-card flat bordered class="q-mb-md si-progress-card">
                            <q-card-section class="q-pa-md">
                                <div class="row items-center q-mb-xs">
                                    <div class="col">
                                        <span class="text-caption text-grey-6 text-uppercase text-weight-bold">Estado</span>
                                        <q-badge :color="jobStateColor" :label="jobStateLabel" class="q-ml-sm" />
                                        <span v-if="jobStatus.current" class="q-ml-sm text-caption text-grey-7">
                                            · procesando
                                            <q-chip dense color="blue-1" text-color="blue-9" size="sm">
                                                {{ jobStatus.current }}
                                            </q-chip>
                                        </span>
                                    </div>
                                    <div class="col-auto row items-center q-gutter-sm">
                                        <!-- Contador tablas -->
                                        <span
                                            v-if="jobStatus.tables && jobStatus.tables.length"
                                            class="text-caption text-grey-6"
                                        >
                                            {{ tablesProcessed }}/{{ jobStatus.tables.length }} tablas
                                        </span>
                                        <div class="text-h6 text-weight-bold" :class="`text-${jobStateColor}`">
                                            {{ jobStatus.progress || 0 }}%
                                        </div>
                                    </div>
                                </div>
                                <q-linear-progress
                                    :value="(jobStatus.progress || 0) / 100"
                                    :color="jobStateColor"
                                    size="12px"
                                    rounded
                                    :stripe="jobStatus.state === 'running'"
                                    class="rounded-borders"
                                />
                                <!-- Tiempo transcurrido -->
                                <div class="text-caption text-grey-5 q-mt-xs text-right">
                                    {{ elapsedText }}
                                </div>
                            </q-card-section>
                        </q-card>

                        <!-- Log de ejecución con auto-scroll -->
                        <q-card flat bordered class="q-mb-md">
                            <div class="row items-center q-pa-sm bg-grey-1 si-log-header">
                                <i class="fas fa-terminal text-grey-6 q-mr-sm"></i>
                                <span class="text-caption text-weight-bold text-grey-7 text-uppercase">
                                    Log de ejecución
                                </span>
                                <q-space />
                                <q-spinner-dots v-if="jobStatus.state === 'running'" color="primary" size="18px" class="q-mr-sm" />
                                <q-badge v-if="jobStatus.log" color="grey-4" text-color="grey-8">
                                    {{ jobStatus.log.length }} líneas
                                </q-badge>
                            </div>
                            <div ref="logContainer" class="log-scroll-area">
                                <pre class="log-pre">{{ (jobStatus.log || []).join('\n') }}</pre>
                            </div>
                        </q-card>

                        <!-- Resultado final -->
                        <div
                            v-if="jobStatus.state === 'completed' && jobStatus.totals"
                            class="row q-col-gutter-md q-mb-md"
                        >
                            <div class="col-12 col-sm-4">
                                <q-card flat bordered class="text-center q-pa-md si-stat-card si-stat-success">
                                    <div class="text-h3 text-positive text-weight-bold">
                                        {{ (jobStatus.totals.imported || 0).toLocaleString() }}
                                    </div>
                                    <div class="text-caption text-grey-6 q-mt-xs">
                                        <i class="fas fa-check-circle text-positive q-mr-xs"></i>
                                        Importados
                                    </div>
                                </q-card>
                            </div>
                            <div class="col-12 col-sm-4">
                                <q-card flat bordered class="text-center q-pa-md si-stat-card si-stat-neutral">
                                    <div class="text-h3 text-grey-7 text-weight-bold">
                                        {{ (jobStatus.totals.skipped || 0).toLocaleString() }}
                                    </div>
                                    <div class="text-caption text-grey-6 q-mt-xs">
                                        <i class="fas fa-minus-circle text-grey-5 q-mr-xs"></i>
                                        Omitidos
                                    </div>
                                </q-card>
                            </div>
                            <div class="col-12 col-sm-4">
                                <q-card flat bordered class="text-center q-pa-md si-stat-card si-stat-error">
                                    <div class="text-h3 text-negative text-weight-bold">
                                        {{ (jobStatus.totals.errors || 0).toLocaleString() }}
                                    </div>
                                    <div class="text-caption text-grey-6 q-mt-xs">
                                        <i class="fas fa-times-circle text-negative q-mr-xs"></i>
                                        Errores
                                    </div>
                                </q-card>
                            </div>
                        </div>

                        <!-- Error detalle -->
                        <q-banner
                            v-if="jobStatus.state === 'failed'"
                            dense
                            rounded
                            class="bg-negative text-white q-mb-md"
                            icon="error"
                        >
                            {{ jobStatus.error || 'La importación falló. Revisa el log para más detalles.' }}
                        </q-banner>

                        <div class="row justify-end">
                            <q-btn
                                v-if="jobStatus.state === 'completed' || jobStatus.state === 'failed'"
                                color="primary"
                                icon="restart_alt"
                                label="Nueva importación"
                                unelevated
                                @click="reset"
                            />
                        </div>
                    </div>
                </q-step>
            </q-stepper>
        </div>
    </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from "vue";

export default {
    name: "SmartImport",
    setup() {
        const step = ref(1);
        const stepper = ref(null);
        const analyzing = ref(false);
        const aiLoading = ref(false);
        const executing = ref(false);
        const truncateBefore = ref(false);

        const token = ref(null);
        const report = ref([]);
        const totalRows = ref(0);
        const conflicts = ref({});
        const aiRecommendations = reactive({});

        const jobId = ref(null);
        const jobStatus = ref({ state: "idle", progress: 0, log: [] });
        const logContainer = ref(null);
        let pollTimer = null;
        let jobStartedAt = null;
        const elapsedText = ref('');

        const defaultAction = reactive({});
        const perRowAction = reactive({});

        const csrf =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") || "";
        const uploadUrl = "/configuracion/smart-import/upload";
        const uploadHeaders = [
            { name: "X-CSRF-TOKEN", value: csrf },
            { name: "X-Requested-With", value: "XMLHttpRequest" },
            { name: "Accept", value: "application/json" },
        ];

        const formatList = [
            { ext: '.sql',  color: 'blue-7',   desc: 'Parsea sentencias INSERT INTO' },
            { ext: '.json', color: 'green-7',  desc: 'Mapea por nombre de tabla' },
            { ext: '.xlsx', color: 'teal-7',   desc: 'Detecta módulo por columnas' },
            { ext: '.csv',  color: 'orange-7', desc: 'Detecta módulo por columnas' },
            { ext: '.zip',  color: 'purple-7', desc: 'Múltiples archivos combinados' },
        ];

        const actionOptions = [
            { label: "Reemplazar al conflictar", value: "replace" },
            { label: "Insertar (omitir conflictos)", value: "skip" },
            { label: "Duplicar al conflictar", value: "duplicate" },
        ];
        const perRowOptions = [
            { label: "Omitir", value: "skip" },
            { label: "Reemplazar", value: "replace" },
            { label: "Duplicar", value: "duplicate" },
        ];

        const reportColumns = [
            { name: "module", label: "Módulo", field: "module", align: "left" },
            { name: "table", label: "Tabla", field: "table", align: "left" },
            {
                name: "records",
                label: "Registros",
                field: "records",
                align: "right",
            },
            { name: "status", label: "Estado", field: "table", align: "center" },
            { name: "action", label: "Acción", field: "table", align: "center" },
        ];

        const hasConflicts = computed(
            () => Object.keys(conflicts.value).length > 0
        );
        const conflictCount = computed(() =>
            Object.values(conflicts.value).reduce(
                (acc, arr) => acc + arr.length,
                0
            )
        );
        const jobStateColor = computed(() => {
            const map = {
                queued: "grey",
                running: "primary",
                completed: "positive",
                failed: "negative",
            };
            return map[jobStatus.value.state] || "grey";
        });

        const jobStateLabel = computed(() => {
            const map = {
                queued: "En cola",
                running: "Ejecutando",
                completed: "Completado",
                failed: "Fallido",
                unknown: "Desconocido",
            };
            return map[jobStatus.value.state] || jobStatus.value.state;
        });

        const tablesProcessed = computed(() => {
            const tables = jobStatus.value.tables || [];
            const current = jobStatus.value.current;
            if (!current) return 0;
            const idx = tables.indexOf(current);
            return idx >= 0 ? idx : 0;
        });

        function rowStatusColor(row) {
            if (!row.known) return "negative";
            const c = conflicts.value[row.table];
            if (c && c.length) return "warning";
            return "positive";
        }
        function rowStatusLabel(row) {
            if (!row.known) return "Tabla no mapeada";
            const c = conflicts.value[row.table];
            if (c && c.length) return `${c.length} conflicto(s)`;
            return "Listo";
        }
        function pretty(obj) {
            try {
                return JSON.stringify(obj, null, 2);
            } catch (e) {
                return String(obj);
            }
        }

        async function onUploaded(info) {
            try {
                const resp = JSON.parse(info.xhr.response);
                if (!resp.success) {
                    throw new Error(resp.message || "Error al analizar");
                }
                token.value = resp.token;
                report.value = resp.report || [];
                totalRows.value = resp.total_rows || 0;
                report.value.forEach((r) => {
                    if (!defaultAction[r.table]) defaultAction[r.table] = "replace";
                });
                await loadPreview(false);
                step.value = 2;
            } catch (e) {
                console.error(e);
                alert("Error al analizar el archivo: " + e.message);
            } finally {
                analyzing.value = false;
            }
        }
        function onUploadFailed(info) {
            analyzing.value = false;
            const msg = info?.xhr?.response || "Falló la subida del archivo.";
            alert(msg);
        }
        function onRejected() {
            alert(
                "Archivo rechazado. Verifica formato (sql/json/xlsx/csv/zip) y tamaño máximo 2GB."
            );
        }

        async function loadPreview(withAI = false) {
            if (!token.value) return;
            try {
                const { data } = await axios.post(
                    "/configuracion/smart-import/preview",
                    { token: token.value, with_ai: withAI ? 1 : 0 }
                );
                if (!data.success) throw new Error(data.message);
                conflicts.value = data.conflicts || {};
                Object.keys(conflicts.value).forEach((table) => {
                    if (!perRowAction[table]) perRowAction[table] = {};
                    conflicts.value[table].forEach((item) => {
                        if (perRowAction[table][item.index] === undefined) {
                            perRowAction[table][item.index] =
                                defaultAction[table] || "skip";
                        }
                    });
                });
                if (data.ai_recommendations) {
                    Object.assign(aiRecommendations, data.ai_recommendations);
                    // Aplica la sugerencia de IA al selector por fila.
                    Object.entries(data.ai_recommendations).forEach(
                        ([table, perIdx]) => {
                            Object.entries(perIdx).forEach(([idx, rec]) => {
                                const map = {
                                    omitir: "skip",
                                    reemplazar: "replace",
                                    duplicar: "duplicate",
                                };
                                if (map[rec.accion]) {
                                    if (!perRowAction[table])
                                        perRowAction[table] = {};
                                    perRowAction[table][idx] = map[rec.accion];
                                }
                            });
                        }
                    );
                }
            } catch (e) {
                console.error(e);
                alert("No se pudo obtener el preview: " + e.message);
            }
        }

        async function resolveAllWithAI() {
            aiLoading.value = true;
            try {
                await loadPreview(true);
            } finally {
                aiLoading.value = false;
            }
        }

        function onPerRowChange() { /* no-op: el v-model basta */ }

        async function execute() {
            executing.value = true;
            try {
                const options = {};
                report.value.forEach((r) => {
                    options[r.table] = {
                        action: defaultAction[r.table] || "skip",
                        conflicts: perRowAction[r.table] || {},
                    };
                });
                const { data } = await axios.post(
                    "/configuracion/smart-import/execute",
                    { token: token.value, options, truncate_before: truncateBefore.value }
                );
                if (!data.success) throw new Error(data.message);
                jobId.value = data.job_id;
                step.value = 3;
                startPolling();
            } catch (e) {
                console.error(e);
                alert("No se pudo encolar la importación: " + e.message);
            } finally {
                executing.value = false;
            }
        }

        function scrollLogToBottom() {
            nextTick(() => {
                const el = logContainer.value;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }

        function updateElapsed() {
            if (!jobStartedAt) { elapsedText.value = ''; return; }
            const secs = Math.floor((Date.now() - jobStartedAt) / 1000);
            if (secs < 60) {
                elapsedText.value = `${secs}s transcurridos`;
            } else {
                const m = Math.floor(secs / 60);
                const s = secs % 60;
                elapsedText.value = `${m}m ${s}s transcurridos`;
            }
        }

        function startPolling() {
            stopPolling();
            jobStartedAt = Date.now();
            // Polling adaptativo: cada 1s mientras corre, se detiene al terminar
            pollTimer = setInterval(async () => {
                updateElapsed();
                try {
                    const { data } = await axios.get(
                        `/configuracion/smart-import/status/${jobId.value}`
                    );
                    if (data.success) {
                        const prev = jobStatus.value.log?.length || 0;
                        jobStatus.value = data.status;
                        // Auto-scroll si llegaron líneas nuevas al log
                        if ((data.status.log?.length || 0) > prev) {
                            scrollLogToBottom();
                        }
                        if (["completed", "failed"].includes(data.status.state)) {
                            stopPolling();
                            updateElapsed();
                            scrollLogToBottom();
                        }
                    }
                } catch (e) {
                    /* swallow — reintentos automáticos */
                }
            }, 1000);
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function reset() {
            stopPolling();
            step.value = 1;
            token.value = null;
            report.value = [];
            totalRows.value = 0;
            truncateBefore.value = false;
            Object.keys(conflicts.value).forEach((k) => delete conflicts.value[k]);
            Object.keys(aiRecommendations).forEach((k) => delete aiRecommendations[k]);
            Object.keys(defaultAction).forEach((k) => delete defaultAction[k]);
            Object.keys(perRowAction).forEach((k) => delete perRowAction[k]);
            jobId.value = null;
            jobStatus.value = { state: "idle", progress: 0, log: [] };
            jobStartedAt = null;
            elapsedText.value = '';
        }

        onBeforeUnmount(() => stopPolling());

        return {
            step,
            stepper,
            analyzing,
            aiLoading,
            executing,
            truncateBefore,
            formatList,
            token,
            report,
            totalRows,
            conflicts,
            aiRecommendations,
            jobId,
            jobStatus,
            logContainer,
            elapsedText,
            defaultAction,
            perRowAction,
            uploadUrl,
            uploadHeaders,
            actionOptions,
            perRowOptions,
            reportColumns,
            hasConflicts,
            conflictCount,
            jobStateColor,
            jobStateLabel,
            tablesProcessed,
            rowStatusColor,
            rowStatusLabel,
            pretty,
            onUploaded,
            onUploadFailed,
            onRejected,
            loadPreview,
            resolveAllWithAI,
            onPerRowChange,
            execute,
            reset,
        };
    },
};
</script>

<style scoped>
/* ── Variables que se adaptan a dark/light ── */
.smart-import-wrapper {
    --si-border: rgba(128,128,128,0.2);
    --si-header-bg: rgba(128,128,128,0.06);
    --si-danger-bg: rgba(229, 57, 53, 0.08);
    --si-danger-border: rgba(229, 57, 53, 0.35);
    --si-option-bg: rgba(128,128,128,0.05);
    --si-stat-success-bg: rgba(76, 175, 80, 0.1);
    --si-stat-neutral-bg: rgba(128,128,128,0.08);
    --si-stat-error-bg: rgba(229, 57, 53, 0.08);
    min-height: 100%;
}

/* ── Encabezado ── */
.si-page-header {
    border-bottom: 1px solid var(--si-border);
    margin-bottom: 0;
}
.si-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.si-title { line-height: 1.2; }

/* ── Stepper: fix texto cortado ── */
.si-stepper :deep(.q-stepper__header) {
    background: var(--si-header-bg);
    border-radius: 10px;
    padding: 4px 8px;
    margin-bottom: 16px;
    border: 1px solid var(--si-border);
}
.si-stepper :deep(.q-stepper__tab)   { padding: 12px 16px; }
.si-stepper :deep(.q-stepper__label) { padding-left: 4px; }
.si-stepper :deep(.q-stepper__dot)   { margin-right: 8px; }
.si-stepper :deep(.q-stepper) { background: transparent !important; }
.si-stepper :deep(.q-stepper__content) { background: transparent !important; }

/* ── Cards generales ── */
.si-upload-card {
    border-radius: 10px;
    transition: box-shadow 0.2s;
}
.si-upload-card:hover {
    box-shadow: 0 2px 12px rgba(25,118,210,0.12);
}
.si-uploader :deep(.q-uploader__header) {
    border-radius: 8px 8px 0 0;
}
.si-uploader :deep(.q-uploader__list),
.si-uploader :deep(.q-uploader) {
    background: transparent !important;
    min-height: 60px;
}
.si-formats-card {
    border-radius: 10px;
    height: 100%;
}
.si-analyzing-card {
    border-radius: 10px;
    border-color: rgba(25,118,210,0.3);
    background: rgba(25,118,210,0.08);
}

/* ── Toggle limpiar ── */
.si-option-card {
    border-radius: 10px;
    background: var(--si-option-bg);
    border-color: var(--si-border) !important;
}
.si-danger-card {
    border-radius: 10px;
    background: var(--si-danger-bg);
    border-color: var(--si-danger-border) !important;
}

/* ── Tabla de reporte ── */
.si-report-table { border-radius: 10px; }
.si-report-table :deep(thead tr th) {
    background: var(--si-header-bg);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* ── Progreso ── */
.si-progress-card { border-radius: 10px; }

/* ── Log ── */
.si-log-header {
    border-radius: 10px 10px 0 0;
    border-bottom: 1px solid var(--si-border);
    background: var(--si-header-bg);
}
.log-scroll-area {
    height: 360px;
    overflow-y: auto;
    background: #0d1117;
    border-radius: 0 0 10px 10px;
    scroll-behavior: smooth;
}
.log-pre {
    color: #8b949e;
    padding: 14px 16px;
    font-size: 12px;
    font-family: "Consolas", "JetBrains Mono", "Fira Code", monospace;
    margin: 0;
    line-height: 1.7;
    white-space: pre-wrap;
    word-break: break-all;
}

/* ── Stats finales ── */
.si-stat-card {
    border-radius: 10px;
    transition: transform 0.15s;
}
.si-stat-card:hover { transform: translateY(-2px); }
.si-stat-success { background: var(--si-stat-success-bg) !important; border-color: rgba(76,175,80,0.3) !important; }
.si-stat-neutral { background: var(--si-stat-neutral-bg) !important; }
.si-stat-error   { background: var(--si-stat-error-bg) !important;   border-color: rgba(229,57,53,0.3) !important; }

/* ── Estado vacío ── */
.si-empty-state { padding: 60px 0; }
.si-empty-icon  { font-size: 64px; }

/* ── Conflictos ── */
.conflict-pre {
    background: var(--si-header-bg);
    border: 1px solid var(--si-border);
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 11px;
    max-height: 140px;
    overflow: auto;
    margin: 0;
    font-family: "Consolas", monospace;
}
</style>

<!-- Global: uploader — fondo transparente + layout interno correcto -->
<style>
.smart-import-wrapper .q-uploader,
.smart-import-wrapper .q-uploader__list {
    background: transparent !important;
}
/* Fix: nombre de archivo aparecía en vertical por contenedor sin ancho */
.smart-import-wrapper .q-uploader__list {
    display: flex;
    flex-direction: column;
    min-height: 48px;
    padding: 4px 0;
}
.smart-import-wrapper .q-uploader__file {
    display: flex;
    flex-direction: row;
    align-items: center;
    width: 100%;
    padding: 4px 8px;
    min-width: 0;
}
.smart-import-wrapper .q-uploader__file-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    flex: 1;
}
</style>
