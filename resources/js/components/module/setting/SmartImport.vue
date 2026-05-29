<template>
    <div class="smart-import-wrapper" :class="{ 'is-dark': darkMode }">
        <div class="smart-import-shell">
            <div class="smart-import-hero">
                <div class="smart-import-hero__copy">
                    <div class="smart-import-hero__eyebrow">
                        Smart Import
                    </div>
                    <div class="smart-import-hero__title">
                        Importación guiada de dumps SQL
                    </div>
                    <div class="smart-import-hero__description">
                        Carga el archivo, revisa el plan por tabla y sigue la
                        ejecución desde un flujo más claro y ordenado.
                    </div>
                </div>
                <div class="smart-import-hero__meta">
                    <div class="smart-import-meta-pill">
                        <q-icon name="route" size="16px" />
                        <span>3 pasos</span>
                    </div>
                    <div class="smart-import-meta-pill">
                        <q-icon name="dataset" size="16px" />
                        <span>Preview antes de importar</span>
                    </div>
                </div>
            </div>

            <q-stepper
                v-model="step"
                ref="stepper"
                color="primary"
                animated
                flat
                header-nav
                class="smart-import-stepper"
                :dark="darkMode"
            >
                <q-step
                    :name="1"
                    title="Subir dump"
                    icon="cloud_upload"
                    active-icon="cloud_upload"
                    done-icon="task_alt"
                    caption="Carga .sql o .zip"
                    :done="step > 1"
                    class="smart-import-step"
                    :dark="darkMode"
                >
                    <div class="smart-step-body">
                        <div class="smart-step-intro q-mb-lg">
                            <div class="smart-step-intro__eyebrow">Paso 1</div>
                            <div class="smart-step-intro__title">
                                Sube el dump que quieres importar
                            </div>
                            <div class="smart-step-intro__description">
                                El sistema analiza primero la estructura para
                                que puedas decidir cómo tratar cada tabla antes
                                de ejecutar cambios.
                            </div>
                        </div>

                        <div class="row q-col-gutter-lg">
                            <div class="col-12 col-lg-8">
                                <div class="smart-import-surface smart-import-upload-panel">
                                    <div
                                        class="smart-import-dropzone"
                                        :class="{
                                            'smart-import-dropzone--active': dragActive,
                                            'smart-import-dropzone--has-file': selectedFile,
                                            'smart-import-dropzone--error': uploadError
                                        }"
                                        @click="openFilePicker"
                                        @dragover.prevent="dragActive = true"
                                        @dragleave="dragActive = false"
                                        @drop.prevent="onDrop"
                                    >
                                        <input
                                            ref="fileInput"
                                            type="file"
                                            accept=".sql,.zip"
                                            class="smart-import-dropzone__input"
                                            @change="onFileChange"
                                            @click.stop
                                        />

                                        <div v-if="uploadError" class="smart-import-dropzone__content">
                                            <q-icon name="error_outline" size="36px" color="negative" />
                                            <div class="smart-import-dropzone__title">
                                                Error en la subida
                                            </div>
                                            <div class="smart-import-dropzone__hint">
                                                {{ uploadError }}
                                            </div>
                                        </div>

                                        <div v-else-if="analyzing" class="smart-import-dropzone__content">
                                            <q-spinner size="36px" color="primary" />
                                            <div class="smart-import-dropzone__title">
                                                Analizando dump SQL…
                                            </div>
                                            <div class="smart-import-dropzone__hint">
                                                Detectando tablas y estructura del archivo
                                            </div>
                                        </div>

                                        <div v-else-if="selectedFile && uploading" class="smart-import-dropzone__content">
                                            <q-spinner size="36px" color="primary" />
                                            <div class="smart-import-dropzone__title">
                                                Subiendo archivo…
                                            </div>
                                            <div class="smart-import-dropzone__hint">
                                                {{ selectedFile.name }} — {{ formatFileSize(selectedFile.size) }}
                                            </div>
                                        </div>

                                        <div v-else-if="selectedFile" class="smart-import-dropzone__content">
                                            <q-icon name="check_circle" size="36px" color="positive" />
                                            <div class="smart-import-dropzone__title">
                                                {{ selectedFile.name }}
                                            </div>
                                            <div class="smart-import-dropzone__hint">
                                                {{ formatFileSize(selectedFile.size) }} · Toca para cambiar
                                            </div>
                                        </div>

                                        <div v-else class="smart-import-dropzone__content">
                                            <q-icon name="cloud_upload" size="36px" color="primary" class="smart-import-dropzone__icon" />
                                            <div class="smart-import-dropzone__title">
                                                Arrastra un archivo .sql o .zip
                                            </div>
                                            <div class="smart-import-dropzone__hint">
                                                o haz clic para seleccionar
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4">
                                <q-card flat bordered class="smart-import-surface smart-import-fill smart-import-sidecard" :dark="darkMode">
                                    <div class="smart-import-sidecard__title q-mb-sm">
                                        <i class="fas fa-info-circle text-primary"></i>
                                        Alcance actual
                                    </div>
                                    <ul class="smart-import-bullet-list text-caption">
                                        <li>.sql</li>
                                        <li>.zip con archivos .sql</li>
                                        <li>modos globales y override por tabla</li>
                                        <li>creación de tablas faltantes desde CREATE TABLE</li>
                                    </ul>
                                </q-card>
                            </div>
                        </div>
                    </div>
                </q-step>

                <q-step
                    :name="2"
                    title="Plan de importación"
                    icon="tune"
                    active-icon="tune"
                    done-icon="task_alt"
                    caption="Configura reglas por tabla"
                    :done="step > 2"
                    class="smart-import-step"
                    :dark="darkMode"
                >
                    <div class="smart-step-body">
                        <div class="smart-step-intro q-mb-lg">
                            <div class="smart-step-intro__eyebrow">Paso 2</div>
                            <div class="smart-step-intro__title">
                                Revisa el plan antes de ejecutar
                            </div>
                            <div class="smart-step-intro__description">
                                Ajusta el modo general o define overrides por
                                tabla con base en las advertencias detectadas.
                            </div>
                        </div>

                        <div v-if="report.length === 0" class="smart-import-empty">
                            <q-icon name="fact_check" size="28px" class="q-mb-sm" />
                            <div class="smart-import-empty__title">
                                Sin datos analizados todavía
                            </div>
                            <div class="smart-import-empty__description">
                                Primero sube un archivo en el paso anterior para
                                generar el preview de importación.
                            </div>
                        </div>
                        <div v-else>
                            <div class="row q-col-gutter-lg q-mb-lg">
                                <div class="col-12 col-lg-4">
                                    <q-card flat bordered class="smart-import-surface smart-import-fill smart-import-control-card" :dark="darkMode">
                                        <div class="text-caption text-grey-7 q-mb-xs">
                                            Modo general
                                        </div>
                                        <div class="smart-import-control-card__title q-mb-md">
                                            Regla predeterminada para todas las tablas
                                        </div>
                                        <q-select
                                            v-model="globalMode"
                                            :options="modeOptions"
                                            emit-value
                                            map-options
                                            outlined
                                            dense
                                            :dark="darkMode"
                                        />
                                        <div class="text-caption text-grey-7 q-mt-sm">
                                            Se aplica por defecto a todas las tablas.
                                        </div>
                                        <div class="q-mt-md">
                                            <q-btn
                                                color="primary"
                                                unelevated
                                                icon="playlist_add_check"
                                                label="Aplicar a todas"
                                                @click="applyGlobalMode"
                                            />
                                        </div>
                                    </q-card>
                                </div>
                                <div class="col-12 col-lg-8">
                                    <div class="row q-col-gutter-md">
                                        <div class="col-6 col-md-3">
                                            <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--blue text-center" :dark="darkMode">
                                                <div class="text-h5">{{ totalRows }}</div>
                                                <div class="text-caption">Filas detectadas</div>
                                            </q-card>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--slate text-center" :dark="darkMode">
                                                <div class="text-h5">{{ report.length }}</div>
                                                <div class="text-caption">Tablas</div>
                                            </q-card>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--amber text-center" :dark="darkMode">
                                                <div class="text-h5">{{ missingTargetCount }}</div>
                                                <div class="text-caption">Tablas faltantes</div>
                                            </q-card>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--rose text-center" :dark="darkMode">
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
                                class="smart-import-warning-banner q-mb-md"
                                :dark="darkMode"
                            >
                                Se detectaron {{ structuralWarningCount }} advertencias estructurales.
                                Revisa tablas faltantes, columnas nuevas y tablas sin modelo
                                antes de ejecutar.
                            </q-banner>

                            <div class="smart-import-table-wrap">
                                <q-table
                                    :rows="report"
                                    :columns="reportColumns"
                                    row-key="table"
                                    flat
                                    dense
                                    bordered
                                    class="smart-import-table"
                                    :pagination="{ rowsPerPage: 20 }"
                                    :dark="darkMode"
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
                                                    :dark="darkMode"
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
                                                :dark="darkMode"
                                            />
                                        </q-td>
                                    </template>
                                </q-table>
                            </div>

                            <div class="q-mt-lg text-right">
                                <q-btn
                                    color="primary"
                                    unelevated
                                    icon="play_arrow"
                                    label="Ejecutar importación"
                                    :loading="executing"
                                    @click="execute"
                                />
                            </div>
                        </div>
                    </div>
                </q-step>

                <q-step
                    :name="3"
                    title="Ejecución"
                    icon="play_circle"
                    active-icon="play_circle"
                    done-icon="task_alt"
                    caption="Monitorea el progreso"
                    class="smart-import-step"
                    :dark="darkMode"
                >
                    <div class="smart-step-body">
                        <div class="smart-step-intro q-mb-lg">
                            <div class="smart-step-intro__eyebrow">Paso 3</div>
                            <div class="smart-step-intro__title">
                                Sigue la ejecución en tiempo real
                            </div>
                            <div class="smart-step-intro__description">
                                Cuando el job arranque verás el progreso, la
                                tabla actual y el log consolidado.
                            </div>
                        </div>

                        <div v-if="!jobId" class="smart-import-empty">
                            <q-icon name="pending_actions" size="28px" class="q-mb-sm" />
                            <div class="smart-import-empty__title">
                                Esperando ejecución
                            </div>
                            <div class="smart-import-empty__description">
                                Inicia la importación desde el paso anterior para
                                activar el monitoreo.
                            </div>
                        </div>
                        <div v-else>
                            <q-card flat bordered class="smart-import-surface q-mb-md" :dark="darkMode">
                                <div class="smart-import-progress">
                                    <div class="smart-import-progress__header">
                                        <div class="smart-import-progress__title">
                                            Progreso del job
                                        </div>
                                        <div class="smart-import-progress__state">
                                            {{ jobStatus.progress || 0 }}%
                                        </div>
                                    </div>
                                    <q-linear-progress
                                        :value="(jobStatus.progress || 0) / 100"
                                        :color="jobStateColor"
                                        size="12px"
                                        stripe
                                        :dark="darkMode"
                                    />
                                    <div class="text-caption q-mt-sm">
                                        Estado: <strong>{{ jobStatus.state }}</strong>
                                        <span v-if="jobStatus.current">
                                            · Tabla actual:
                                            <q-chip dense color="blue-2" text-color="blue-10" :dark="darkMode">{{ jobStatus.current }}</q-chip>
                                        </span>
                                    </div>
                                </div>
                            </q-card>

                            <q-card flat bordered class="smart-import-surface smart-import-log-card" :dark="darkMode">
                                <div class="smart-import-log-card__header">
                                    Log de ejecución
                                </div>
                                <q-scroll-area style="height: 320px" :dark="darkMode">
                                    <pre class="log-pre">{{ (jobStatus.log || []).join("\n") }}</pre>
                                </q-scroll-area>
                            </q-card>

                            <div
                                v-if="jobStatus.state === 'completed' && jobStatus.totals"
                                class="row q-col-gutter-md q-mt-md"
                            >
                                <div class="col-12 col-md-4">
                                    <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--green text-center" :dark="darkMode">
                                        <div class="text-h4 text-positive">
                                            {{ jobStatus.totals.imported }}
                                        </div>
                                        <div class="text-caption">Importados</div>
                                    </q-card>
                                </div>
                                <div class="col-12 col-md-4">
                                    <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--slate text-center" :dark="darkMode">
                                        <div class="text-h4">
                                            {{ jobStatus.totals.skipped }}
                                        </div>
                                        <div class="text-caption">Omitidos</div>
                                    </q-card>
                                </div>
                                <div class="col-12 col-md-4">
                                    <q-card flat bordered class="smart-import-metric-card smart-import-metric-card--rose text-center" :dark="darkMode">
                                        <div class="text-h4 text-negative">
                                            {{ jobStatus.totals.errors }}
                                        </div>
                                        <div class="text-caption">Errores</div>
                                    </q-card>
                                </div>
                            </div>

                            <div class="q-mt-lg text-right">
                                <q-btn
                                    v-if="jobStatus.state === 'completed' || jobStatus.state === 'failed'"
                                    color="primary"
                                    unelevated
                                    icon="restart_alt"
                                    label="Nueva importación"
                                    @click="reset"
                                />
                            </div>
                        </div>
                    </div>
                </q-step>
            </q-stepper>
        </div>
    </div>
</template>

<script>
import { computed, onBeforeUnmount, reactive, ref } from "vue";
import { darkMode } from "../../../hook/appConfig";

export default {
    name: "SmartImport",
    setup() {
        const step = ref(1);
        const stepper = ref(null);
        const analyzing = ref(false);
        const uploading = ref(false);
        const executing = ref(false);
        const selectedFile = ref(null);
        const dragActive = ref(false);
        const uploadError = ref(null);
        const fileInput = ref(null);

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
            { name: "records", label: "Registros", field: "records", align: "right" },
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

        function openFilePicker() {
            if (uploading.value || analyzing.value) return;
            fileInput.value?.click();
        }

        function onFileChange(event) {
            const file = event.target.files?.[0];
            if (file) startUpload(file);
            event.target.value = '';
        }

        function onDrop(event) {
            dragActive.value = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) startUpload(file);
        }

        async function startUpload(file) {
            uploadError.value = null;
            selectedFile.value = file;
            uploading.value = true;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const { data } = await axios.post(uploadUrl, formData, {
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!data.success) {
                    throw new Error(data.message || 'Error al analizar');
                }

                token.value = data.token;
                report.value = data.report || [];
                totalRows.value = data.total_rows || 0;
                ensureTableModes();
                uploading.value = false;
                analyzing.value = true;
                await loadPreview();
                analyzing.value = false;
                step.value = 2;
            } catch (e) {
                console.error(e);
                uploadError.value = e.message || 'Error al subir el archivo';
                uploading.value = false;
            }
        }

        function onRejected() {
            uploadError.value = 'Formato no soportado. Usa archivos .sql o .zip.';
        }

        function onUploadStart() {
            uploading.value = true;
        }

        function onUploadFinish() {
            uploading.value = false;
        }

        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return "0 B";
            const units = ["B", "KB", "MB", "GB"];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const val = bytes / Math.pow(1024, i);
            return val.toFixed(i > 0 ? 1 : 0) + " " + units[i];
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
            selectedFile.value = null;
            uploadError.value = null;
            dragActive.value = false;
            uploading.value = false;
            analyzing.value = false;
        }

        onBeforeUnmount(() => stopPolling());

        return {
            darkMode,
            step,
            stepper,
            analyzing,
            uploading,
            executing,
            selectedFile,
            dragActive,
            uploadError,
            fileInput,
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
            openFilePicker,
            onFileChange,
            onDrop,
            onRejected,
            onUploadStart,
            onUploadFinish,
            formatFileSize,
            execute,
            reset,
        };
    },
};
</script>

<style scoped>
.smart-import-wrapper {
    --si-panel: rgba(255, 255, 255, 0.96);
    --si-border: #dbe4f0;
    --si-border-strong: #c3d2e6;
    --si-text: #0f172a;
    --si-muted: #64748b;
    --si-primary: #2563eb;
    --si-table-header: #eff6ff;
    --si-table-alt: rgba(248, 250, 252, 0.75);
    --si-metric-blue: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
    --si-metric-slate: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
    --si-metric-amber: linear-gradient(180deg, #fffbeb 0%, #fde68a 100%);
    --si-metric-rose: linear-gradient(180deg, #fff1f2 0%, #fecdd3 100%);
    --si-metric-green: linear-gradient(180deg, #f0fdf4 0%, #bbf7d0 100%);
    --si-warning-bg: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
    --si-warning-border: #facc15;
    --si-warning-text: #9a3412;
    --si-empty-bg: rgba(255, 255, 255, 0.78);
    --si-log-bg: #0f172a;
    --si-log-text: #cbd5e1;
    --si-stepper-content: rgba(255, 255, 255, 0.84);
    --si-stepper-tab: rgba(255, 255, 255, 0.76);
    --si-stepper-tab-active: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
    --si-stepper-tab-done: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%);
    --si-stepper-dot: #dbeafe;
    --si-stepper-dot-text: #1d4ed8;
    --si-stepper-dot-active: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    --si-stepper-dot-done: #16a34a;
    --si-hero-eyebrow: #e2ecff;
    --si-hero-eyebrow-text: #1d4ed8;
    --si-meta-pill-bg: rgba(255, 255, 255, 0.88);
    --si-meta-pill-border: rgba(37, 99, 235, 0.15);
    --si-meta-pill-text: #1e3a8a;
    --si-log-header-bg: #f8fafc;
    --si-upload-header: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    --si-upload-header-text: #1e3a8a;
    color: var(--si-text);
}

.smart-import-wrapper.is-dark {
    --si-bg: linear-gradient(180deg, #1a1f24 0%, #161b20 100%);
    --si-panel: rgba(32, 44, 51, 0.96);
    --si-border: #2e3a41;
    --si-border-strong: #3b484f;
    --si-text: #e5e7eb;
    --si-muted: #9ca3af;
    --si-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
    --si-table-header: #1a2633;
    --si-table-alt: rgba(32, 44, 51, 0.8);
    --si-metric-blue: linear-gradient(180deg, #1e3a5f 0%, #1a2d4a 100%);
    --si-metric-slate: linear-gradient(180deg, #1f2a30 0%, #253138 100%);
    --si-metric-amber: linear-gradient(180deg, #3d2e15 0%, #4a3818 100%);
    --si-metric-rose: linear-gradient(180deg, #3d1a1f 0%, #4a2026 100%);
    --si-metric-green: linear-gradient(180deg, #14382a 0%, #1a4535 100%);
    --si-warning-bg: linear-gradient(180deg, #2a2215 0%, #332a18 100%);
    --si-warning-border: #7d5d1a;
    --si-warning-text: #f5d78a;
    --si-empty-bg: rgba(32, 44, 51, 0.85);
    --si-log-bg: #0a0d11;
    --si-log-text: #9ca3af;
    --si-stepper-content: rgba(32, 44, 51, 0.9);
    --si-stepper-tab: rgba(32, 44, 51, 0.8);
    --si-stepper-tab-active: linear-gradient(180deg, #1e3a5f 0%, #1a2d4a 100%);
    --si-stepper-tab-done: linear-gradient(180deg, #14382a 0%, #1a4535 100%);
    --si-stepper-dot: #1a2d4a;
    --si-stepper-dot-text: #93c5fd;
    --si-stepper-dot-active: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    --si-stepper-dot-done: #16a34a;
    --si-hero-eyebrow: #1a2d4a;
    --si-hero-eyebrow-text: #93c5fd;
    --si-meta-pill-bg: rgba(32, 44, 51, 0.9);
    --si-meta-pill-border: rgba(96, 165, 250, 0.2);
    --si-meta-pill-text: #93c5fd;
    --si-log-header-bg: #1a1f24;
    --si-upload-header: linear-gradient(135deg, #1a2d4a 0%, #1e3a5f 100%);
    --si-upload-header-text: #93c5fd;
}

.smart-import-shell {
    background: var(--si-bg);
    border: 1px solid var(--si-border);
    border-radius: 28px;
    padding: 20px;
    box-shadow: var(--si-shadow);
}

.smart-import-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    padding: 2px 2px 0;
}

.smart-import-hero__copy {
    max-width: 720px;
}

.smart-import-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--si-hero-eyebrow);
    color: var(--si-hero-eyebrow-text);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.smart-import-hero__title {
    font-size: 24px;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -0.03em;
}

.smart-import-hero__description {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.55;
    color: var(--si-muted);
}

.smart-import-hero__meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.smart-import-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid var(--si-meta-pill-border);
    border-radius: 999px;
    background: var(--si-meta-pill-bg);
    color: var(--si-meta-pill-text);
    font-size: 11px;
    font-weight: 600;
}

.smart-step-body {
    padding-top: 6px;
}

.smart-step-intro__eyebrow {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--si-primary);
    margin-bottom: 8px;
}

.smart-step-intro__title {
    font-size: 22px;
    line-height: 1.15;
    font-weight: 800;
    color: var(--si-text);
}

.smart-step-intro__description {
    margin-top: 8px;
    max-width: 720px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--si-muted);
}

.smart-import-surface {
    background: var(--si-panel);
    border: 1px solid var(--si-border);
    border-radius: 22px;
    padding: 20px;
    box-shadow: 0 14px 32px rgba(148, 163, 184, 0.12);
}

.smart-import-upload-panel,
.smart-import-fill {
    min-height: 100%;
}

.smart-import-sidecard__title,
.smart-import-control-card__title,
.smart-import-progress__title,
.smart-import-log-card__header {
    font-size: 16px;
    line-height: 1.3;
    font-weight: 700;
    color: var(--si-text);
}

.smart-import-bullet-list {
    padding-left: 18px;
    margin: 0;
}

.smart-import-bullet-list li + li {
    margin-top: 10px;
}

.smart-import-control-card .q-btn {
    border-radius: 12px;
}

.smart-import-metric-card {
    border-radius: 20px;
    padding: 18px 14px;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 12px 28px rgba(148, 163, 184, 0.12);
}

.smart-import-metric-card--blue {
    background: var(--si-metric-blue);
}

.smart-import-metric-card--slate {
    background: var(--si-metric-slate);
}

.smart-import-metric-card--amber {
    background: var(--si-metric-amber);
}

.smart-import-metric-card--rose {
    background: var(--si-metric-rose);
}

.smart-import-metric-card--green {
    background: var(--si-metric-green);
}

.smart-import-warning-banner {
    border: 1px solid var(--si-warning-border);
    background: var(--si-warning-bg);
    color: var(--si-warning-text);
}

.smart-import-table-wrap {
    overflow: hidden;
    border: 1px solid var(--si-border);
    border-radius: 22px;
    background: var(--si-panel);
    box-shadow: 0 16px 38px rgba(148, 163, 184, 0.12);
}

.smart-import-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 42px 20px;
    border: 1px dashed var(--si-border-strong);
    border-radius: 24px;
    background: var(--si-empty-bg);
    color: var(--si-muted);
    text-align: center;
}

.smart-import-empty__title {
    font-size: 16px;
    font-weight: 700;
    color: var(--si-text);
}

.smart-import-empty__description {
    max-width: 480px;
    font-size: 13px;
    line-height: 1.6;
}

.smart-import-progress__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.smart-import-progress__state {
    font-size: 18px;
    font-weight: 800;
    color: var(--si-primary);
}

.smart-import-log-card {
    overflow: hidden;
    padding: 0;
}

.smart-import-log-card__header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--si-border);
    background: var(--si-log-header-bg);
}

:deep(.smart-import-stepper) {
    background: transparent;
    box-shadow: none;
}

:deep(.smart-import-stepper .q-stepper__header) {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 24px;
    padding: 0;
    box-shadow: none;
}

:deep(.smart-import-stepper .q-stepper__line) {
    display: none;
}

:deep(.smart-import-stepper .q-stepper__tab) {
    justify-content: flex-start;
    align-items: center;
    min-height: 104px;
    padding: 16px 18px;
    border: 1px solid var(--si-border);
    border-radius: 24px;
    background: var(--si-stepper-tab);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    box-shadow: 0 10px 24px rgba(148, 163, 184, 0.1);
}

:deep(.smart-import-stepper .q-stepper__tab:hover) {
    transform: translateY(-1px);
    box-shadow: 0 16px 34px rgba(148, 163, 184, 0.15);
}

:deep(.smart-import-stepper .q-stepper__tab--active) {
    border-color: rgba(37, 99, 235, 0.35);
    background: var(--si-stepper-tab-active);
    box-shadow: 0 18px 36px rgba(37, 99, 235, 0.16);
}

:deep(.smart-import-stepper .q-stepper__tab--done) {
    border-color: rgba(34, 197, 94, 0.22);
    background: var(--si-stepper-tab-done);
}

:deep(.smart-import-stepper .q-stepper__dot) {
    width: 52px;
    min-width: 52px;
    height: 52px;
    margin-right: 14px;
    background: var(--si-stepper-dot);
    color: var(--si-stepper-dot-text);
    box-shadow: none;
}

:deep(.smart-import-stepper .q-stepper__dot .q-icon) {
    font-size: 24px;
}

:deep(.smart-import-stepper .q-stepper__tab--active .q-stepper__dot) {
    background: var(--si-stepper-dot-active);
    color: #fff;
    transform: scale(1.04);
}

:deep(.smart-import-stepper .q-stepper__tab--done .q-stepper__dot) {
    background: var(--si-stepper-dot-done);
    color: #fff;
}

:deep(.smart-import-stepper .q-stepper__label) {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 52px;
}

:deep(.smart-import-stepper .q-stepper__title) {
    font-size: 15px;
    line-height: 1.2;
    font-weight: 700;
    white-space: normal;
}

:deep(.smart-import-stepper .q-stepper__caption) {
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.4;
    color: var(--si-muted);
    white-space: normal;
}

:deep(.smart-import-stepper .q-stepper__content) {
    border: 1px solid var(--si-border);
    border-radius: 28px;
    background: var(--si-stepper-content);
    box-shadow: 0 18px 36px rgba(148, 163, 184, 0.12);
}

:deep(.smart-import-stepper .q-stepper__step-inner) {
    padding: 28px;
}

.smart-import-dropzone {
    position: relative;
    border: 2px dashed var(--si-border-strong);
    border-radius: 16px;
    padding: 40px 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, transform 0.15s ease;
    background: var(--si-panel);
    overflow: hidden;
}

.smart-import-dropzone:hover {
    border-color: var(--si-primary);
    background: var(--si-stepper-tab);
}

.smart-import-dropzone--active {
    border-color: var(--si-primary);
    background: var(--si-stepper-tab-active);
    transform: scale(1.005);
}

.smart-import-dropzone--has-file {
    border-style: solid;
    border-color: rgba(34, 197, 94, 0.4);
}

.smart-import-dropzone--error {
    border-color: rgba(239, 68, 68, 0.5);
}

.smart-import-dropzone__input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.smart-import-dropzone__content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    pointer-events: none;
    position: relative;
    z-index: 1;
}

.smart-import-dropzone__icon {
    animation: smart-pulse 2.5s ease-in-out infinite;
}

@keyframes smart-pulse {
    0%, 100% { transform: translateY(0); opacity: 1; }
    50% { transform: translateY(-4px); opacity: 0.7; }
}

.smart-import-dropzone__title {
    font-size: 15px;
    font-weight: 700;
    color: var(--si-text);
    line-height: 1.3;
}

.smart-import-dropzone__hint {
    font-size: 12px;
    color: var(--si-muted);
    line-height: 1.5;
}

.smart-import-dropzone--has-file .smart-import-dropzone__input {
    cursor: pointer;
}

:deep(.smart-import-table thead tr) {
    background: var(--si-table-header);
}

:deep(.smart-import-table thead th) {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

:deep(.smart-import-table tbody tr:nth-child(even)) {
    background: var(--si-table-alt);
}

.log-pre {
    background: var(--si-log-bg);
    color: var(--si-log-text);
    padding: 16px 18px;
    font-size: 12px;
    font-family: "JetBrains Mono", monospace;
    line-height: 1.6;
    margin: 0;
    min-height: 100%;
}

@media (max-width: 1023px) {
    .smart-import-shell {
        padding: 16px;
        border-radius: 24px;
    }

    .smart-import-hero {
        flex-direction: column;
    }

    .smart-import-hero__meta {
        justify-content: flex-start;
    }

    :deep(.smart-import-stepper .q-stepper__header) {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 599px) {
    .smart-import-wrapper {
        padding: 12px !important;
    }

    .smart-import-shell {
        padding: 14px;
        border-radius: 20px;
    }

    .smart-import-hero__title {
        font-size: 22px;
    }

    .smart-step-intro__title {
        font-size: 20px;
    }

    :deep(.smart-import-stepper .q-stepper__step-inner) {
        padding: 18px;
    }

    :deep(.smart-import-stepper .q-stepper__tab) {
        min-height: 96px;
        padding: 14px;
    }

    :deep(.smart-import-stepper .q-stepper__dot) {
        width: 46px;
        min-width: 46px;
        height: 46px;
    }

    :deep(.smart-import-stepper .q-stepper__dot .q-icon) {
        font-size: 21px;
    }
}
</style>
