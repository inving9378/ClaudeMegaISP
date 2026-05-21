<template>
    <div class="import-export-history-wrapper p-3">
        <div class="text-h5 q-mb-md">
            <i class="fas fa-history text-primary"></i>
            Historial de Importaciones / Exportaciones
        </div>

        <!-- Métricas rápidas -->
        <div class="row q-col-gutter-md q-mb-md">
            <div class="col-6 col-md-3">
                <q-card flat bordered class="q-pa-md text-center">
                    <div class="text-h4">{{ stats.total }}</div>
                    <div class="text-caption text-grey-7">Total operaciones</div>
                </q-card>
            </div>
            <div class="col-6 col-md-3">
                <q-card flat bordered class="q-pa-md text-center bg-green-1">
                    <div class="text-h4 text-positive">{{ stats.completed }}</div>
                    <div class="text-caption">Exitosas</div>
                </q-card>
            </div>
            <div class="col-6 col-md-3">
                <q-card flat bordered class="q-pa-md text-center bg-red-1">
                    <div class="text-h4 text-negative">{{ stats.failed }}</div>
                    <div class="text-caption">Fallidas</div>
                </q-card>
            </div>
            <div class="col-6 col-md-3">
                <q-card flat bordered class="q-pa-md text-center bg-blue-1">
                    <div class="text-h4 text-primary">{{ stats.processing }}</div>
                    <div class="text-caption">En proceso</div>
                </q-card>
            </div>
        </div>

        <!-- Tabs filtro -->
        <q-tabs
            v-model="activeFilter"
            dense
            class="text-grey q-mb-sm"
            active-color="primary"
            indicator-color="primary"
            align="left"
            narrow-indicator
        >
            <q-tab name="all" label="Todos" />
            <q-tab name="import" icon="cloud_upload" label="Importaciones" />
            <q-tab name="export" icon="cloud_download" label="Exportaciones" />
            <q-space />
            <q-btn
                flat
                dense
                icon="refresh"
                :loading="loading"
                @click="loadHistory"
            >
                <q-tooltip>Recargar</q-tooltip>
            </q-btn>
        </q-tabs>

        <q-separator />

        <!-- Tabla -->
        <q-table
            :rows="filteredLogs"
            :columns="columns"
            row-key="id"
            flat
            bordered
            :loading="loading"
            :pagination="{ rowsPerPage: 15, sortBy: 'created_at', descending: true }"
            no-data-label="Sin registros todavía."
        >
            <template v-slot:body="props">
                <q-tr
                    :props="props"
                    class="cursor-pointer"
                    @click="toggleExpand(props.row.id)"
                >
                    <q-td key="created_at" :props="props">
                        <div>{{ formatDate(props.row.created_at) }}</div>
                        <div class="text-caption text-grey-7">
                            {{ formatTime(props.row.created_at) }}
                        </div>
                    </q-td>
                    <q-td key="type" :props="props">
                        <q-chip
                            dense
                            square
                            :color="
                                props.row.type === 'import'
                                    ? 'blue-2'
                                    : 'purple-2'
                            "
                            :text-color="
                                props.row.type === 'import'
                                    ? 'blue-10'
                                    : 'purple-10'
                            "
                            :icon="
                                props.row.type === 'import'
                                    ? 'cloud_upload'
                                    : 'cloud_download'
                            "
                        >
                            {{ props.row.type.toUpperCase() }}
                        </q-chip>
                        <div
                            v-if="props.row.format"
                            class="text-caption text-grey-7 q-mt-xs"
                        >
                            .{{ props.row.format }}
                        </div>
                    </q-td>
                    <q-td key="summary" :props="props">
                        <span v-if="props.row.type === 'export'">
                            {{
                                (props.row.modules_selected || []).length
                            }}
                            módulo(s)
                        </span>
                        <span v-else>
                            {{ props.row.filename || "—" }}
                        </span>
                    </q-td>
                    <q-td key="status" :props="props">
                        <q-badge
                            :color="statusMeta(props.row.status).color"
                            class="q-pa-xs"
                        >
                            <q-icon
                                :name="statusMeta(props.row.status).icon"
                                size="14px"
                                class="q-mr-xs"
                            />
                            {{ statusMeta(props.row.status).label }}
                        </q-badge>
                        <div
                            v-if="isActiveStatus(props.row.status)"
                            class="q-mt-xs"
                            style="min-width: 90px"
                        >
                            <q-linear-progress
                                indeterminate
                                size="6px"
                                color="primary"
                            />
                        </div>
                        <div
                            v-else-if="props.row.status === 'completed'"
                            class="text-caption text-grey-7 q-mt-xs"
                        >
                            {{ props.row.records_processed || 0 }} OK ·
                            {{ props.row.records_failed || 0 }} err
                        </div>
                    </q-td>
                    <q-td key="actions" :props="props" @click.stop>
                        <q-btn
                            v-if="
                                props.row.type === 'export' &&
                                props.row.status === 'completed' &&
                                props.row.output_path
                            "
                            flat
                            dense
                            icon="download"
                            color="primary"
                            @click="downloadFile(props.row)"
                        >
                            <q-tooltip>Descargar</q-tooltip>
                        </q-btn>
                        <q-btn
                            v-if="props.row.error_message"
                            flat
                            dense
                            icon="error_outline"
                            color="negative"
                            @click="showError(props.row)"
                        >
                            <q-tooltip>Ver error</q-tooltip>
                        </q-btn>
                        <q-btn
                            flat
                            dense
                            icon="delete"
                            color="negative"
                            @click="confirmDelete(props.row)"
                        >
                            <q-tooltip>Eliminar</q-tooltip>
                        </q-btn>
                        <q-btn
                            flat
                            dense
                            :icon="
                                expandedRow === props.row.id
                                    ? 'expand_less'
                                    : 'expand_more'
                            "
                            @click="toggleExpand(props.row.id)"
                        />
                    </q-td>
                </q-tr>

                <!-- Fila expandida -->
                <q-tr
                    v-if="expandedRow === props.row.id"
                    :props="props"
                    no-hover
                >
                    <q-td colspan="100%" class="bg-grey-1">
                        <div class="q-pa-sm">
                            <!-- Detalle IMPORT: ai_analysis -->
                            <div
                                v-if="
                                    props.row.type === 'import' &&
                                    props.row.ai_analysis
                                "
                            >
                                <div class="text-subtitle2 q-mb-sm">
                                    <i
                                        class="fas fa-robot text-purple"
                                    ></i>
                                    Análisis del archivo
                                </div>
                                <div class="row q-col-gutter-sm">
                                    <div
                                        v-if="props.row.ai_analysis.format"
                                        class="col-auto"
                                    >
                                        <q-chip dense color="blue-2">
                                            Formato:
                                            {{ props.row.ai_analysis.format }}
                                        </q-chip>
                                    </div>
                                    <div
                                        v-if="props.row.ai_analysis.total_rows"
                                        class="col-auto"
                                    >
                                        <q-chip dense color="green-2">
                                            {{
                                                props.row.ai_analysis.total_rows
                                            }}
                                            filas detectadas
                                        </q-chip>
                                    </div>
                                </div>
                                <div
                                    v-if="
                                        (
                                            props.row.ai_analysis.report || []
                                        ).length
                                    "
                                    class="q-mt-sm"
                                >
                                    <div class="text-caption q-mb-xs">
                                        Tablas detectadas:
                                    </div>
                                    <q-chip
                                        v-for="r in props.row.ai_analysis
                                            .report"
                                        :key="r.table"
                                        dense
                                        :color="
                                            r.known ? 'grey-3' : 'red-2'
                                        "
                                    >
                                        {{ r.module }} · {{ r.table }} ({{
                                            r.records
                                        }})
                                    </q-chip>
                                </div>
                            </div>

                            <!-- Detalle EXPORT -->
                            <div v-if="props.row.type === 'export'">
                                <div class="text-subtitle2 q-mb-sm">
                                    <i
                                        class="fas fa-layer-group text-primary"
                                    ></i>
                                    Módulos exportados
                                </div>
                                <q-chip
                                    v-for="m in props.row.modules_selected ||
                                    []"
                                    :key="m"
                                    dense
                                    color="primary"
                                    text-color="white"
                                >
                                    {{ m }}
                                </q-chip>
                                <div
                                    v-if="props.row.encrypted"
                                    class="q-mt-sm"
                                >
                                    <q-chip
                                        dense
                                        color="orange"
                                        text-color="white"
                                        icon="lock"
                                    >
                                        AES-256
                                    </q-chip>
                                </div>
                            </div>

                            <!-- Metadatos -->
                            <div class="q-mt-sm text-caption text-grey-7">
                                <span v-if="props.row.admin_user">
                                    Por: <strong>{{
                                        props.row.admin_user
                                    }}</strong>
                                </span>
                                <span
                                    v-if="props.row.job_id"
                                    class="q-ml-md"
                                >
                                    Job: <code>{{ props.row.job_id }}</code>
                                </span>
                            </div>
                        </div>
                    </q-td>
                </q-tr>
            </template>

            <template v-slot:bottom-row>
                <q-tr>
                    <q-td colspan="100%" class="text-caption text-grey-7">
                        Mostrando {{ filteredLogs.length }} de
                        {{ logs.length }} registros
                    </q-td>
                </q-tr>
            </template>
        </q-table>

        <!-- Diálogo de error -->
        <q-dialog v-model="errorDialog">
            <q-card style="min-width: 500px; max-width: 90vw">
                <q-card-section class="bg-red-1 text-red-10">
                    <div class="text-h6">
                        <i class="fas fa-exclamation-triangle"></i>
                        Detalle del error
                    </div>
                </q-card-section>
                <q-card-section>
                    <pre class="error-pre">{{ errorText }}</pre>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cerrar" v-close-popup />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Diálogo de confirmación de borrado -->
        <q-dialog v-model="deleteDialog" persistent>
            <q-card style="min-width: 350px">
                <q-card-section class="row items-center">
                    <q-icon
                        name="warning"
                        color="negative"
                        size="md"
                        class="q-mr-md"
                    />
                    <span class="text-body1">
                        ¿Eliminar registro #{{ pendingDelete?.id }} y su
                        archivo asociado?
                    </span>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn
                        flat
                        color="negative"
                        label="Eliminar"
                        :loading="deleting"
                        @click="doDelete"
                    />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";

const STATUS_META = {
    pending:    { label: "Pendiente",  color: "grey-6",   icon: "schedule" },
    queued:     { label: "Encolada",   color: "grey-6",   icon: "schedule" },
    running:    { label: "Procesando", color: "blue-6",   icon: "autorenew" },
    processing: { label: "Procesando", color: "blue-6",   icon: "autorenew" },
    completed:  { label: "Completada", color: "positive", icon: "check_circle" },
    failed:     { label: "Fallida",    color: "negative", icon: "error" },
    rolled_back:{ label: "Revertida",  color: "warning",  icon: "undo" },
};

const ACTIVE_STATUSES = ["pending", "queued", "running", "processing"];

export default {
    name: "ImportExportHistory",
    setup() {
        const logs = ref([]);
        const loading = ref(false);
        const activeFilter = ref("all");
        const expandedRow = ref(null);
        const errorDialog = ref(false);
        const errorText = ref("");
        const deleteDialog = ref(false);
        const pendingDelete = ref(null);
        const deleting = ref(false);
        const pollingIntervals = reactive({});

        const columns = [
            { name: "created_at", label: "Fecha", field: "created_at", align: "left", sortable: true },
            { name: "type", label: "Tipo", field: "type", align: "left" },
            { name: "summary", label: "Detalle", field: "filename", align: "left" },
            { name: "status", label: "Estado", field: "status", align: "left" },
            { name: "actions", label: "Acciones", field: "id", align: "right" },
        ];

        const filteredLogs = computed(() => {
            if (activeFilter.value === "all") return logs.value;
            return logs.value.filter((l) => l.type === activeFilter.value);
        });

        const stats = computed(() => ({
            total: logs.value.length,
            completed: logs.value.filter((l) => l.status === "completed").length,
            failed: logs.value.filter((l) =>
                ["failed", "rolled_back"].includes(l.status)
            ).length,
            processing: logs.value.filter((l) =>
                ACTIVE_STATUSES.includes(l.status)
            ).length,
        }));

        function statusMeta(status) {
            return (
                STATUS_META[status] || {
                    label: status || "—",
                    color: "grey",
                    icon: "help_outline",
                }
            );
        }

        function isActiveStatus(status) {
            return ACTIVE_STATUSES.includes(status);
        }

        function formatDate(iso) {
            if (!iso) return "—";
            const d = new Date(iso);
            return d.toLocaleDateString("es-MX", {
                day: "2-digit",
                month: "2-digit",
                year: "2-digit",
            });
        }

        function formatTime(iso) {
            if (!iso) return "";
            const d = new Date(iso);
            return d.toLocaleTimeString("es-MX", {
                hour: "2-digit",
                minute: "2-digit",
            });
        }

        async function loadHistory() {
            loading.value = true;
            try {
                const { data } = await axios.get(
                    "/configuracion/smart-import-export/history",
                    { params: { limit: 100 } }
                );
                if (data.success) {
                    logs.value = data.logs || [];
                    startPollingForActive();
                }
            } catch (e) {
                console.error("loadHistory:", e);
                alert("No se pudo cargar el historial.");
            } finally {
                loading.value = false;
            }
        }

        function toggleExpand(id) {
            expandedRow.value = expandedRow.value === id ? null : id;
        }

        function showError(log) {
            errorText.value = log.error_message || "(sin detalle)";
            errorDialog.value = true;
        }

        function confirmDelete(log) {
            pendingDelete.value = log;
            deleteDialog.value = true;
        }

        async function doDelete() {
            if (!pendingDelete.value) return;
            deleting.value = true;
            try {
                await axios.delete(
                    `/configuracion/smart-import-export/log/${pendingDelete.value.id}`
                );
                logs.value = logs.value.filter(
                    (l) => l.id !== pendingDelete.value.id
                );
                stopPolling(pendingDelete.value.id);
            } catch (e) {
                console.error("delete:", e);
                alert("No se pudo eliminar el registro.");
            } finally {
                deleting.value = false;
                deleteDialog.value = false;
                pendingDelete.value = null;
            }
        }

        function downloadFile(log) {
            if (!log.output_path) {
                alert("Este export ya no tiene archivo disponible.");
                return;
            }
            window.location.href = `/configuracion/smart-import-export/log/${log.id}/download`;
        }

        function startPollingForActive() {
            logs.value
                .filter((l) => isActiveStatus(l.status))
                .filter((l) => l.job_id && !pollingIntervals[l.id])
                .forEach((l) => {
                    pollingIntervals[l.id] = setInterval(
                        () => pollOne(l),
                        3000
                    );
                });
        }

        async function pollOne(log) {
            if (!log.job_id) return;
            try {
                const { data } = await axios.get(
                    `/configuracion/smart-import/status/${log.job_id}`
                );
                if (!data.success) return;
                const state = data.status?.state;
                if (!state) return;
                // Mapea state del job → status del log
                const stateMap = {
                    queued: "running",
                    running: "running",
                    completed: "completed",
                    failed: "failed",
                };
                const newStatus = stateMap[state] || log.status;
                const target = logs.value.find((l) => l.id === log.id);
                if (target) {
                    target.status = newStatus;
                    if (data.status?.totals) {
                        target.records_processed =
                            data.status.totals.imported || 0;
                        target.records_failed =
                            data.status.totals.errors || 0;
                    }
                }
                if (!ACTIVE_STATUSES.includes(newStatus)) {
                    stopPolling(log.id);
                }
            } catch (e) {
                /* swallow — reintentos automáticos */
            }
        }

        function stopPolling(id) {
            if (pollingIntervals[id]) {
                clearInterval(pollingIntervals[id]);
                delete pollingIntervals[id];
            }
        }

        function clearAllPolling() {
            Object.keys(pollingIntervals).forEach((id) => stopPolling(id));
        }

        onMounted(loadHistory);
        onBeforeUnmount(clearAllPolling);

        return {
            logs,
            loading,
            activeFilter,
            expandedRow,
            errorDialog,
            errorText,
            deleteDialog,
            pendingDelete,
            deleting,
            columns,
            filteredLogs,
            stats,
            statusMeta,
            isActiveStatus,
            formatDate,
            formatTime,
            loadHistory,
            toggleExpand,
            showError,
            confirmDelete,
            doDelete,
            downloadFile,
        };
    },
};
</script>

<style scoped>
.error-pre {
    background: #1e1e1e;
    color: #ff6b6b;
    padding: 12px;
    border-radius: 4px;
    font-family: "JetBrains Mono", "Courier New", monospace;
    font-size: 12px;
    max-height: 400px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
