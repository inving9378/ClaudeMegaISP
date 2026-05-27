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
                title="Subir dump"
                icon="cloud_upload"
                :done="step > 1"
            >
                <div class="row q-col-gutter-md">
                    <div class="col-12 col-md-8">
                        <q-uploader
                            ref="uploader"
                            label="Arrastra un archivo .sql o .zip con dumps SQL"
                            :url="uploadUrl"
                            field-name="file"
                            :headers="uploadHeaders"
                            accept=".sql,.zip"
                            :max-files="1"
                            :max-file-size="2147483648"
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
                                Analizando dump SQL...
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card flat bordered class="q-pa-md bg-grey-1">
                            <div class="text-subtitle2 q-mb-sm">
                                <i class="fas fa-info-circle text-primary"></i>
                                Alcance actual
                            </div>
                            <ul class="q-pl-md q-mb-none text-caption text-grey-8">
                                <li>.sql</li>
                                <li>.zip con archivos .sql</li>
                                <li>modos globales y override por tabla</li>
                                <li>creación de tablas faltantes desde CREATE TABLE</li>
                            </ul>
                        </q-card>
                    </div>
                </div>
            </q-step>

            <q-step
                :name="2"
                title="Plan de importación"
                icon="schema"
                :done="step > 2"
            >
                <div v-if="report.length === 0" class="text-grey-7 q-pa-md">
                    Sin datos analizados todavía.
                </div>
                <div v-else>
                    <div class="row q-col-gutter-md q-mb-md">
                        <div class="col-12 col-md-4">
                            <q-card flat bordered class="q-pa-md">
                                <div class="text-caption text-grey-7 q-mb-xs">
                                    Modo general
                                </div>
                                <q-select
                                    v-model="globalMode"
                                    :options="modeOptions"
                                    emit-value
                                    map-options
                                    outlined
                                    dense
                                />
                                <div class="text-caption text-grey-7 q-mt-sm">
                                    Se aplica por defecto a todas las tablas.
                                </div>
                                <div class="q-mt-sm">
                                    <q-btn
                                        color="primary"
                                        flat
                                        dense
                                        icon="playlist_add_check"
                                        label="Aplicar a todas"
                                        @click="applyGlobalMode"
                                    />
                                </div>
                            </q-card>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="row q-col-gutter-md">
                                <div class="col-6 col-md-3">
                                    <q-card flat bordered class="q-pa-md bg-blue-1 text-center">
                                        <div class="text-h5">{{ totalRows }}</div>
                                        <div class="text-caption">Filas detectadas</div>
                                    </q-card>
                                </div>
                                <div class="col-6 col-md-3">
                                    <q-card flat bordered class="q-pa-md bg-grey-2 text-center">
                                        <div class="text-h5">{{ report.length }}</div>
                                        <div class="text-caption">Tablas</div>
                                    </q-card>
                                </div>
                                <div class="col-6 col-md-3">
                                    <q-card flat bordered class="q-pa-md bg-orange-1 text-center">
                                        <div class="text-h5">{{ missingTargetCount }}</div>
                                        <div class="text-caption">Tablas faltantes</div>
                                    </q-card>
                                </div>
                                <div class="col-6 col-md-3">
                                    <q-card flat bordered class="q-pa-md bg-red-1 text-center">
                                        <div class="text-h5">{{ noModelCount }}</div>
                                        <div class="text-caption">Sin modelo</div>
                                    </q-card>
                                </div>
                            </div>
                        </div>
                    </div>

                    <q-banner
                        v-if="structuralWarningCount > 0"
                        rounded
                        class="bg-orange-1 text-grey-9 q-mb-md"
                    >
                        Se detectaron {{ structuralWarningCount }} advertencias estructurales.
                        Revisa tablas faltantes, columnas nuevas y tablas sin modelo
                        antes de ejecutar.
                    </q-banner>

                    <q-table
                        :rows="report"
                        :columns="reportColumns"
                        row-key="table"
                        flat
                        dense
                        bordered
                        :pagination="{ rowsPerPage: 20 }"
                    >
                        <template v-slot:body-cell-target="props">
                            <q-td :props="props">
                                <q-badge
                                    :color="props.row.target_exists ? 'positive' : 'orange'"
                                    :label="props.row.target_exists ? 'Existe' : 'Se creará'"
                                />
                            </q-td>
                        </template>

                        <template v-slot:body-cell-status="props">
                            <q-td :props="props">
                                <q-badge
                                    :color="rowStatusColor(props.row)"
                                    :label="rowStatusLabel(props.row)"
                                />
                            </q-td>
                        </template>

                        <template v-slot:body-cell-warnings="props">
                            <q-td :props="props">
                                <div class="row q-gutter-xs">
                                    <q-chip
                                        v-for="warning in props.row.warnings || []"
                                        :key="`${props.row.table}-${warning}`"
                                        dense
                                        color="orange-2"
                                        text-color="orange-10"
                                    >
                                        {{ warningLabel(warning) }}
                                    </q-chip>
                                </div>
                            </q-td>
                        </template>

                        <template v-slot:body-cell-action="props">
                            <q-td :props="props">
                                <q-select
                                    v-model="tableMode[props.row.table]"
                                    :options="modeOptions"
                                    dense
                                    outlined
                                    emit-value
                                    map-options
                                    style="min-width: 210px"
                                />
                            </q-td>
                        </template>
                    </q-table>

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
                            <pre class="log-pre">{{ (jobStatus.log || []).join("\n") }}</pre>
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
        const executing = ref(false);

        const token = ref(null);
        const report = ref([]);
        const totalRows = ref(0);
        const globalMode = ref("smart");
        const tableMode = reactive({});

        const jobId = ref(null);
        const jobStatus = ref({ state: "idle", progress: 0, log: [] });
        let pollTimer = null;

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

        const modeOptions = [
            { label: "Resolver inteligentemente", value: "smart" },
            { label: "Reemplazar por fuente", value: "force_source" },
            { label: "Omitir si ya existe", value: "skip_existing" },
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
            { name: "target", label: "Destino", field: "target_exists", align: "center" },
            { name: "status", label: "Estado", field: "warnings", align: "center" },
            { name: "warnings", label: "Advertencias", field: "warnings", align: "left" },
            { name: "action", label: "Modo", field: "table", align: "center" },
        ];

        const missingTargetCount = computed(
            () => report.value.filter((row) => !row.target_exists).length
        );
        const noModelCount = computed(
            () => report.value.filter((row) => !row.has_model).length
        );
        const structuralWarningCount = computed(() =>
            report.value.reduce(
                (carry, row) => carry + ((row.warnings || []).length > 0 ? 1 : 0),
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

        function warningLabel(code) {
            const labels = {
                tabla_destino_inexistente: "Tabla faltante",
                sin_modelo_asociado: "Sin modelo",
                sin_regla_explicita: "Sin regla explícita",
                sin_create_table: "Sin CREATE TABLE",
                columnas_faltantes_en_destino: "Columnas nuevas",
                columnas_extra_en_destino: "Columnas extra en destino",
            };
            return labels[code] || code;
        }

        function rowStatusColor(row) {
            if (!row.target_exists) return "orange";
            if ((row.warnings || []).length > 0) return "warning";
            return "positive";
        }

        function rowStatusLabel(row) {
            if (!row.target_exists) return "Tabla nueva";
            if ((row.warnings || []).length > 0) return "Revisar";
            return "Listo";
        }

        function applyGlobalMode() {
            report.value.forEach((row) => {
                tableMode[row.table] = globalMode.value;
            });
        }

        function ensureTableModes() {
            report.value.forEach((row) => {
                if (!tableMode[row.table]) {
                    tableMode[row.table] = globalMode.value;
                }
            });
        }

        async function onUploaded(info) {
            analyzing.value = true;
            try {
                const resp = JSON.parse(info.xhr.response);
                if (!resp.success) {
                    throw new Error(resp.message || "Error al analizar");
                }
                token.value = resp.token;
                report.value = resp.report || [];
                totalRows.value = resp.total_rows || 0;
                ensureTableModes();
                await loadPreview();
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
                "Archivo rechazado. Verifica formato (sql/zip) y tamaño máximo 2GB."
            );
        }

        async function loadPreview() {
            if (!token.value) return;
            try {
                const { data } = await axios.post("/configuracion/smart-import/preview", {
                    token: token.value,
                });
                if (!data.success) throw new Error(data.message);
                report.value = data.report || report.value;
                ensureTableModes();
            } catch (e) {
                console.error(e);
                alert("No se pudo obtener el preview: " + e.message);
            }
        }

        async function execute() {
            executing.value = true;
            try {
                const tableModes = {};
                report.value.forEach((row) => {
                    tableModes[row.table] = {
                        mode: tableMode[row.table] || globalMode.value,
                    };
                });

                const { data } = await axios.post(
                    "/configuracion/smart-import/execute",
                    {
                        token: token.value,
                        options: {
                            global_mode: globalMode.value,
                            table_modes: tableModes,
                        },
                    }
                );
                if (!data.success) throw new Error(data.message);
                jobId.value = data.job_id;
                step.value = 3;
                startPolling();
            } catch (e) {
                console.error(e);
                alert("No se pudo iniciar la importación: " + e.message);
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
                        if (["completed", "failed"].includes(data.status.state)) {
                            stopPolling();
                        }
                    }
                } catch (e) {
                    /* retry automático */
                }
            }, 5000);
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
            globalMode.value = "smart";
            Object.keys(tableMode).forEach((key) => delete tableMode[key]);
            jobId.value = null;
            jobStatus.value = { state: "idle", progress: 0, log: [] };
        }

        onBeforeUnmount(() => stopPolling());

        return {
            step,
            stepper,
            analyzing,
            executing,
            token,
            report,
            totalRows,
            globalMode,
            tableMode,
            uploadUrl,
            uploadHeaders,
            modeOptions,
            reportColumns,
            missingTargetCount,
            noModelCount,
            structuralWarningCount,
            jobId,
            jobStatus,
            jobStateColor,
            warningLabel,
            rowStatusColor,
            rowStatusLabel,
            applyGlobalMode,
            onUploaded,
            onUploadFailed,
            onRejected,
            execute,
            reset,
        };
    },
};
</script>

<style scoped>
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
