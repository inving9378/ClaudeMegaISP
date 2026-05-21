<template>
    <div class="smart-import-wrapper p-3">
        <q-stepper
            v-model="step"
            ref="stepper"
            color="primary"
            animated
            flat
            header-nav
        >
            <q-step
                :name="1"
                title="Subir archivo"
                icon="cloud_upload"
                :done="step > 1"
            >
                <div class="row q-col-gutter-md">
                    <div class="col-12 col-md-8">
                        <q-uploader
                            ref="uploader"
                            label="Arrastra un archivo .sql, .json, .xlsx, .csv o .zip"
                            :url="uploadUrl"
                            field-name="file"
                            :headers="uploadHeaders"
                            accept=".sql,.json,.xlsx,.xls,.csv,.zip"
                            :max-files="1"
                            :max-file-size="104857600"
                            auto-upload
                            color="primary"
                            class="full-width"
                            @uploaded="onUploaded"
                            @failed="onUploadFailed"
                            @rejected="onRejected"
                        />
                        <div v-if="analyzing" class="q-mt-md">
                            <q-linear-progress indeterminate color="primary" />
                            <div class="text-caption q-mt-sm text-grey-7">
                                Analizando estructura con IA...
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card flat bordered class="q-pa-md bg-grey-1">
                            <div class="text-subtitle2 q-mb-sm">
                                <i class="fas fa-info-circle text-primary"></i>
                                Formatos aceptados
                            </div>
                            <ul class="q-pl-md q-mb-none text-caption text-grey-8">
                                <li>.sql — parsea INSERT INTO</li>
                                <li>.json — mapea por nombre de tabla</li>
                                <li>.xlsx/.csv — detecta módulo por columnas</li>
                                <li>.zip — múltiples archivos</li>
                            </ul>
                        </q-card>
                    </div>
                </div>
            </q-step>

            <q-step
                :name="2"
                title="Reporte y conflictos"
                icon="fact_check"
                :done="step > 2"
            >
                <div v-if="report.length === 0" class="text-grey-7 q-pa-md">
                    Sin datos analizados todavía.
                </div>
                <div v-else>
                    <div class="row q-mb-md items-center q-gutter-sm">
                        <q-btn
                            color="purple"
                            icon="auto_awesome"
                            label="Resolver todo con IA"
                            :loading="aiLoading"
                            :disable="!hasConflicts"
                            @click="resolveAllWithAI"
                        />
                        <q-btn
                            flat
                            color="primary"
                            icon="refresh"
                            label="Recalcular conflictos"
                            @click="loadPreview(false)"
                        />
                        <q-space />
                        <q-chip dense color="grey-3">
                            Total filas: {{ totalRows }}
                        </q-chip>
                    </div>

                    <q-table
                        :rows="report"
                        :columns="reportColumns"
                        row-key="table"
                        flat
                        dense
                        bordered
                        :pagination="{ rowsPerPage: 15 }"
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
                                    style="min-width: 170px"
                                />
                            </q-td>
                        </template>
                    </q-table>

                    <div v-if="hasConflicts" class="q-mt-md">
                        <div class="text-subtitle2 q-mb-sm">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Conflictos detectados ({{ conflictCount }})
                        </div>
                        <q-list bordered separator>
                            <q-expansion-item
                                v-for="(items, table) in conflicts"
                                :key="table"
                                :label="`${table} (${items.length})`"
                                expand-separator
                                icon="warning"
                            >
                                <q-list dense>
                                    <q-item
                                        v-for="item in items"
                                        :key="`${table}-${item.index}`"
                                    >
                                        <q-item-section>
                                            <q-item-label>
                                                <strong>Fila #{{ item.index + 1 }}</strong> —
                                                coincide en
                                                <q-chip
                                                    v-for="k in item.matched"
                                                    :key="k"
                                                    dense
                                                    color="orange-2"
                                                    text-color="orange-10"
                                                    >{{ k }}</q-chip
                                                >
                                            </q-item-label>
                                            <q-item-label caption>
                                                <div class="row q-col-gutter-sm">
                                                    <div class="col-6">
                                                        <div class="text-grey-7">
                                                            Existente
                                                        </div>
                                                        <pre class="conflict-pre">{{ pretty(item.existing) }}</pre>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-grey-7">
                                                            Nuevo
                                                        </div>
                                                        <pre class="conflict-pre">{{ pretty(item.incoming) }}</pre>
                                                    </div>
                                                </div>
                                            </q-item-label>
                                            <q-item-label
                                                v-if="aiRecommendations[table] && aiRecommendations[table][item.index]"
                                            >
                                                <q-chip
                                                    color="purple-2"
                                                    text-color="purple-10"
                                                    icon="auto_awesome"
                                                >
                                                    IA sugiere:
                                                    <strong class="q-ml-xs">
                                                        {{ aiRecommendations[table][item.index].accion }}
                                                    </strong>
                                                </q-chip>
                                                <span class="text-caption text-grey-7 q-ml-sm">
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
                                                style="min-width: 170px"
                                                @update:model-value="onPerRowChange(table, item)"
                                            />
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                            </q-expansion-item>
                        </q-list>
                    </div>

                    <div class="q-mt-md text-right">
                        <q-btn
                            color="primary"
                            icon="play_arrow"
                            label="Ejecutar importación"
                            :loading="executing"
                            @click="execute"
                        />
                    </div>
                </div>
            </q-step>

            <q-step
                :name="3"
                title="Ejecución"
                icon="play_circle"
            >
                <div v-if="!jobId" class="text-grey-7">
                    Esperando ejecución...
                </div>
                <div v-else>
                    <div class="row items-center q-mb-md">
                        <div class="col">
                            <q-linear-progress
                                :value="(jobStatus.progress || 0) / 100"
                                :color="jobStateColor"
                                size="md"
                                stripe
                            />
                            <div class="text-caption q-mt-sm">
                                Estado: <strong>{{ jobStatus.state }}</strong> —
                                {{ jobStatus.progress || 0 }}%
                                <span v-if="jobStatus.current">
                                    · Tabla actual:
                                    <q-chip dense color="blue-2">{{ jobStatus.current }}</q-chip>
                                </span>
                            </div>
                        </div>
                    </div>

                    <q-card flat bordered>
                        <div class="text-subtitle2 q-pa-sm bg-grey-2">
                            Log de ejecución
                        </div>
                        <q-scroll-area style="height: 320px">
                            <pre class="log-pre">{{ (jobStatus.log || []).join('\n') }}</pre>
                        </q-scroll-area>
                    </q-card>

                    <div
                        v-if="jobStatus.state === 'completed' && jobStatus.totals"
                        class="row q-col-gutter-md q-mt-md"
                    >
                        <div class="col">
                            <q-card flat bordered class="text-center q-pa-md bg-green-1">
                                <div class="text-h4 text-positive">
                                    {{ jobStatus.totals.imported }}
                                </div>
                                <div class="text-caption">Importados</div>
                            </q-card>
                        </div>
                        <div class="col">
                            <q-card flat bordered class="text-center q-pa-md bg-grey-2">
                                <div class="text-h4">
                                    {{ jobStatus.totals.skipped }}
                                </div>
                                <div class="text-caption">Omitidos</div>
                            </q-card>
                        </div>
                        <div class="col">
                            <q-card flat bordered class="text-center q-pa-md bg-red-1">
                                <div class="text-h4 text-negative">
                                    {{ jobStatus.totals.errors }}
                                </div>
                                <div class="text-caption">Errores</div>
                            </q-card>
                        </div>
                    </div>

                    <div class="q-mt-md text-right">
                        <q-btn
                            v-if="jobStatus.state === 'completed' || jobStatus.state === 'failed'"
                            color="primary"
                            icon="restart_alt"
                            label="Nueva importación"
                            @click="reset"
                        />
                    </div>
                </div>
            </q-step>
        </q-stepper>
    </div>
</template>

<script>
import { computed, onBeforeUnmount, reactive, ref } from "vue";

export default {
    name: "SmartImport",
    setup() {
        const step = ref(1);
        const stepper = ref(null);
        const analyzing = ref(false);
        const aiLoading = ref(false);
        const executing = ref(false);

        const token = ref(null);
        const report = ref([]);
        const totalRows = ref(0);
        const conflicts = ref({});
        const aiRecommendations = reactive({});

        const jobId = ref(null);
        const jobStatus = ref({ state: "idle", progress: 0, log: [] });
        let pollTimer = null;

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

        const actionOptions = [
            { label: "Insertar (omitir conflictos)", value: "skip" },
            { label: "Reemplazar al conflictar", value: "replace" },
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
                    if (!defaultAction[r.table]) defaultAction[r.table] = "skip";
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
                "Archivo rechazado. Verifica formato (sql/json/xlsx/csv/zip) y tamaño máximo 100MB."
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
                    { token: token.value, options }
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

        function startPolling() {
            stopPolling();
            pollTimer = setInterval(async () => {
                try {
                    const { data } = await axios.get(
                        `/configuracion/smart-import/status/${jobId.value}`
                    );
                    if (data.success) {
                        jobStatus.value = data.status;
                        if (
                            ["completed", "failed"].includes(data.status.state)
                        ) {
                            stopPolling();
                        }
                    }
                } catch (e) {
                    /* swallow — reintentos automáticos */
                }
            }, 2000);
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
            Object.keys(conflicts.value).forEach((k) => delete conflicts.value[k]);
            Object.keys(aiRecommendations).forEach((k) => delete aiRecommendations[k]);
            Object.keys(defaultAction).forEach((k) => delete defaultAction[k]);
            Object.keys(perRowAction).forEach((k) => delete perRowAction[k]);
            jobId.value = null;
            jobStatus.value = { state: "idle", progress: 0, log: [] };
        }

        onBeforeUnmount(() => stopPolling());

        return {
            step,
            stepper,
            analyzing,
            aiLoading,
            executing,
            token,
            report,
            totalRows,
            conflicts,
            aiRecommendations,
            jobId,
            jobStatus,
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
.conflict-pre {
    background: #f5f5f5;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 11px;
    max-height: 160px;
    overflow: auto;
    margin: 0;
}
.log-pre {
    background: #0f172a;
    color: #cbd5e1;
    padding: 12px;
    font-size: 12px;
    font-family: "JetBrains Mono", monospace;
    margin: 0;
    min-height: 100%;
}
</style>
