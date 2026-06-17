<template>
    <div
        class="modal fade"
        id="deployProgressModal"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">

                <!-- Header dinámico según estado -->
                <div class="modal-header py-3" :class="headerBg">
                    <div class="d-flex align-items-center w-100">
                        <span class="me-2 fs-5">
                            <span v-if="isRunning" class="spinner-border spinner-border-sm text-white" role="status"></span>
                            <i v-else-if="overallStatus === 'success'" class="bi bi-check-circle-fill text-white"></i>
                            <i v-else-if="isFailed" class="bi bi-exclamation-triangle-fill text-white"></i>
                            <i v-else class="bi bi-rocket-takeoff text-white"></i>
                        </span>
                        <h6 class="mb-0 text-white fw-semibold">{{ headerTitle }}</h6>
                        <span v-if="isRunning && elapsedSeconds > 0" class="badge bg-white bg-opacity-25 text-white ms-2 fw-normal font-monospace">
                            <i class="bi bi-stopwatch me-1"></i>{{ elapsedFormatted }}
                        </span>
                        <span v-if="activeVersion" class="badge bg-white bg-opacity-25 text-white ms-auto fw-normal">
                            {{ activeVersion }}
                        </span>
                    </div>
                </div>

                <!-- Barra de progreso general -->
                <div v-if="steps.length > 0" class="px-4 pt-3 pb-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">{{ progressLabel }}</small>
                        <small class="fw-semibold" :class="progressTextClass">{{ progressPercent }}%</small>
                    </div>
                    <div class="progress" style="height:8px;border-radius:6px;background:#e9ecef">
                        <div
                            class="progress-bar"
                            :class="progressBarClass"
                            role="progressbar"
                            :style="{ width: progressPercent + '%', transition: 'width 0.6s ease' }"
                        ></div>
                    </div>
                </div>

                <!-- Body: lista de pasos -->
                <div class="modal-body px-4 py-3">

                    <!-- Estado inicial: esperando que el worker tome el job -->
                    <div v-if="steps.length === 0" class="text-center py-4 text-muted">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="mb-0">Iniciando deploy, esperando worker...</p>
                    </div>

                    <div v-else>
                        <div
                            v-for="step in steps"
                            :key="step.key"
                            class="deploy-step mb-2 rounded-3"
                            :class="stepRowClass(step)"
                        >
                            <!-- Fila principal del paso -->
                            <div class="d-flex align-items-start p-3">
                                <!-- Icono de estado -->
                                <div class="step-icon me-3 flex-shrink-0" style="width:22px;margin-top:2px">
                                    <div v-if="step.status === 'pending'" class="rounded-circle bg-secondary" style="width:10px;height:10px;margin-top:4px;opacity:.5"></div>
                                    <div v-else-if="step.status === 'running'" class="spinner-border spinner-border-sm text-primary" role="status" style="width:18px;height:18px"></div>
                                    <i v-else-if="step.status === 'success'" class="bi bi-check-circle-fill text-success" style="font-size:18px"></i>
                                    <i v-else-if="step.status === 'failed'"  class="bi bi-x-circle-fill text-danger"   style="font-size:18px"></i>
                                    <i v-else-if="step.status === 'skipped'" class="bi bi-dash-circle text-muted"       style="font-size:18px"></i>
                                </div>

                                <!-- Contenido -->
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold" :class="stepTextClass(step)">
                                            {{ step.name }}
                                        </span>
                                        <small v-if="step.duration_ms > 0" class="text-muted ms-2 flex-shrink-0">
                                            {{ formatDuration(step.duration_ms) }}
                                        </small>
                                        <small v-else-if="step.status === 'running'" class="text-primary ms-2 flex-shrink-0">
                                            corriendo...
                                        </small>
                                        <small v-else-if="step.status === 'pending'" class="text-muted ms-2 flex-shrink-0">
                                            pendiente
                                        </small>
                                    </div>

                                    <!-- Output del paso (solo texto plano, no para remote_deploy con sub-pasos) -->
                                    <div v-if="step.output && !remoteSubSteps(step).length">
                                        <p v-if="step.status === 'running'" class="small text-primary mb-1 mt-2">
                                            <i class="bi bi-terminal me-1"></i> Consola — archivos en proceso
                                        </p>
                                        <pre
                                            v-if="step.status === 'failed' || step.status === 'running' || outputVisible[step.key]"
                                            class="bg-dark text-light p-2 rounded small mb-1 mt-2"
                                            style="max-height:160px;overflow-y:auto;font-size:11px;white-space:pre-wrap;word-break:break-all"
                                        >{{ step.output }}</pre>
                                        <a
                                            v-if="step.status === 'success'"
                                            href="#"
                                            class="small text-muted"
                                            @click.prevent="toggleOutput(step.key)"
                                        >
                                            <i class="bi bi-terminal me-1"></i>
                                            {{ outputVisible[step.key] ? 'Ocultar salida' : 'Ver salida' }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-pasos del servidor remoto (solo para remote_deploy) -->
                            <div v-if="remoteSubSteps(step).length" class="px-3 pb-3">
                                <div class="border-start border-2 border-primary border-opacity-25 ps-3">
                                    <div
                                        v-for="sub in remoteSubSteps(step)"
                                        :key="sub.key"
                                        class="d-flex align-items-center py-1"
                                    >
                                        <!-- Mini-icono -->
                                        <div class="me-2 flex-shrink-0" style="width:16px">
                                            <div v-if="sub.status === 'pending'" class="rounded-circle bg-secondary" style="width:8px;height:8px;opacity:.4"></div>
                                            <div v-else-if="sub.status === 'running'" class="spinner-border spinner-border-sm text-primary" role="status" style="width:14px;height:14px;border-width:2px"></div>
                                            <i v-else-if="sub.status === 'success'" class="bi bi-check-circle-fill text-success" style="font-size:14px"></i>
                                            <i v-else-if="sub.status === 'failed'"  class="bi bi-x-circle-fill text-danger"      style="font-size:14px"></i>
                                            <i v-else-if="sub.status === 'skipped'" class="bi bi-dash-circle text-muted"          style="font-size:14px"></i>
                                        </div>

                                        <!-- Nombre y duración -->
                                        <span class="small flex-grow-1" :class="sub.status === 'pending' ? 'text-muted' : sub.status === 'failed' ? 'text-danger' : ''">
                                            {{ sub.name }}
                                        </span>
                                        <small v-if="sub.duration_ms > 0" class="text-muted ms-2 flex-shrink-0">
                                            {{ formatDuration(sub.duration_ms) }}
                                        </small>
                                        <small v-else-if="sub.status === 'running'" class="text-primary ms-2 flex-shrink-0">
                                            corriendo...
                                        </small>

                                        <!-- Output de sub-paso fallido -->
                                        <div v-if="sub.output && sub.status === 'failed'" class="w-100 mt-1">
                                            <pre class="bg-dark text-light p-2 rounded small mb-0" style="max-height:100px;overflow-y:auto;font-size:10px;white-space:pre-wrap;word-break:break-all">{{ sub.output }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Resumen final -->
                    <div v-if="isDone" class="mt-3 p-3 rounded-3 d-flex align-items-center" :class="summaryBg">
                        <template v-if="overallStatus === 'success'">
                            <i class="bi bi-rocket-takeoff-fill me-2 text-success fs-5"></i>
                            <div>
                                <strong class="text-success">Deploy completado exitosamente</strong>
                                <span v-if="totalDuration" class="text-muted small ms-2">en {{ totalDuration }}</span>
                            </div>
                        </template>
                        <template v-else>
                            <i class="bi bi-exclamation-triangle-fill me-2 text-danger fs-5"></i>
                            <div>
                                <strong class="text-danger">Deploy fallido</strong>
                                <p v-if="errorMessage" class="mb-0 mt-1 small text-muted">{{ errorMessage }}</p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer border-top-0 pt-0">
                    <div v-if="isFailed" class="me-auto">
                        <button class="btn btn-sm btn-outline-warning" @click="retry" :disabled="retrying">
                            <span v-if="retrying" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-arrow-clockwise me-1"></i>
                            Reintentar
                        </button>
                    </div>
                    <button v-if="!isRunning && steps.length > 0" class="btn btn-primary px-4" @click="close">
                        <i class="bi bi-check2 me-1"></i> Cerrar
                    </button>
                    <button v-else class="btn btn-secondary px-4" disabled>
                        <span class="spinner-border spinner-border-sm me-2"></span> Desplegando...
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "DeployProgressModal",

    props: {
        deploymentId: { type: Number, default: null },
        version:      { type: String,  default: null },
    },

    emits: ["closed"],

    setup(props, { emit }) {
        const overallStatus = ref("pending");
        const steps         = ref([]);
        const errorMessage  = ref(null);
        const durationSecs  = ref(null);
        const outputVisible = ref({});
        const retrying      = ref(false);

        // Estado interno: NO depender de los props, que se actualizan en el
        // siguiente tick. open() recibe el id/version directo del padre para
        // que el primer poll() salga sí o sí (evita el spinner infinito).
        const activeId      = ref(props.deploymentId);
        const activeVersion = ref(props.version);

        let pollTimer  = null;
        let clockTimer = null;

        const elapsedSeconds = ref(0);
        const startedAtTime  = ref(null); // milliseconds timestamp

        // ── Computed ─────────────────────────────────────────────────────────

        const isRunning = computed(() =>
            overallStatus.value === "pending" || overallStatus.value === "running"
        );

        const isDone = computed(() =>
            ["success", "failed", "rolled_back"].includes(overallStatus.value)
        );

        const isFailed = computed(() =>
            ["failed", "rolled_back"].includes(overallStatus.value)
        );

        const headerBg = computed(() => {
            if (overallStatus.value === "success") return "bg-success";
            if (isFailed.value)                    return "bg-danger";
            return "bg-primary";
        });

        const summaryBg = computed(() =>
            overallStatus.value === "success" ? "bg-success bg-opacity-10" : "bg-danger bg-opacity-10"
        );

        const headerTitle = computed(() => {
            if (overallStatus.value === "success")     return "Deploy completado exitosamente";
            if (overallStatus.value === "failed")      return "Deploy fallido";
            if (overallStatus.value === "rolled_back") return "Deploy revertido";
            return "Desplegando versión...";
        });

        const totalDuration = computed(() => {
            if (!durationSecs.value) return null;
            const s = durationSecs.value;
            if (s < 60) return `${s}s`;
            return `${Math.floor(s / 60)}m ${s % 60}s`;
        });

        // — Progreso general —
        const progressPercent = computed(() => {
            const all = steps.value;
            if (!all.length) return 0;
            const done    = all.filter(s => ["success", "failed", "skipped"].includes(s.status)).length;
            const running = all.filter(s => s.status === "running").length;
            // Paso en curso cuenta como la mitad
            return Math.min(100, Math.round(((done + running * 0.5) / all.length) * 100));
        });

        const progressLabel = computed(() => {
            if (overallStatus.value === "success") return "Completado";
            if (isFailed.value)                    return "Falló";
            const done  = steps.value.filter(s => ["success", "failed", "skipped"].includes(s.status)).length;
            const total = steps.value.length;
            const current = steps.value.find(s => s.status === "running");
            return current
                ? `Paso ${done + 1} de ${total} — ${current.name}`
                : `${done} de ${total} pasos`;
        });

        const progressBarClass = computed(() => {
            if (overallStatus.value === "success") return "bg-success";
            if (isFailed.value)                    return "bg-danger";
            return "bg-primary progress-bar-striped progress-bar-animated";
        });

        const progressTextClass = computed(() => {
            if (overallStatus.value === "success") return "text-success";
            if (isFailed.value)                    return "text-danger";
            return "text-primary";
        });

        const elapsedFormatted = computed(() => {
            const s = elapsedSeconds.value;
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
            return `${m}:${String(sec).padStart(2, '0')}`;
        });

        const tickClock = () => {
            if (!startedAtTime.value) return;
            elapsedSeconds.value = Math.floor((Date.now() - startedAtTime.value) / 1000);
        };

        // ── Métodos ───────────────────────────────────────────────────────────

        const poll = async () => {
            if (!activeId.value) return;
            try {
                const { data } = await axios.get(
                    `/releases/deployment/${activeId.value}/status`
                );
                overallStatus.value = data.status;
                steps.value         = data.steps ?? [];
                errorMessage.value  = data.error_message;
                durationSecs.value  = data.duration_seconds;
                if (data.started_at && !startedAtTime.value) {
                    startedAtTime.value = new Date(data.started_at).getTime();
                }
            } catch (e) {
                console.error("Error al obtener estado del deploy:", e);
            }

            if (isDone.value) {
                clearTimeout(pollTimer);
                clearInterval(clockTimer);
            } else {
                pollTimer = setTimeout(poll, 2500);
            }
        };

        const open = (id = null, ver = null) => {
            // Tomar el id/version del argumento (inmediato) o caer al prop.
            activeId.value      = id ?? props.deploymentId;
            activeVersion.value = ver ?? props.version;
            overallStatus.value = "pending";
            steps.value         = [];
            errorMessage.value  = null;
            durationSecs.value  = null;
            outputVisible.value = {};
            clearTimeout(pollTimer);
            clearInterval(clockTimer);
            elapsedSeconds.value = 0;
            startedAtTime.value  = null;
            clockTimer = setInterval(tickClock, 1000);
            $("#deployProgressModal").modal("show");
            poll();
        };

        const close = () => {
            clearTimeout(pollTimer);
            clearInterval(clockTimer);
            $("#deployProgressModal").modal("hide");
            emit("closed", overallStatus.value);
        };

        const retry = async () => {
            if (!activeId.value) return;
            retrying.value = true;
            try {
                const { data } = await axios.post(
                    `/releases/deployment/${activeId.value}/retry`
                );
                if (data.success) {
                    overallStatus.value = "pending";
                    steps.value         = [];
                    errorMessage.value  = null;
                    poll();
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo reiniciar el deploy.", "error");
            } finally {
                retrying.value = false;
            }
        };

        const toggleOutput = (key) => {
            outputVisible.value = { ...outputVisible.value, [key]: !outputVisible.value[key] };
        };

        // Extrae los sub-pasos remotos del output JSON del paso remote_deploy
        const remoteSubSteps = (step) => {
            if (step.key !== "remote_deploy" || !step.output) return [];
            try {
                const data = JSON.parse(step.output);
                return Array.isArray(data.steps) ? data.steps : [];
            } catch {
                return [];
            }
        };

        // ── Helpers ───────────────────────────────────────────────────────────

        const formatDuration = (ms) => {
            if (ms < 1000)  return `${ms}ms`;
            if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
            const m = Math.floor(ms / 60000);
            const s = Math.floor((ms % 60000) / 1000);
            return `${m}m ${s}s`;
        };

        const stepRowClass = (step) => {
            if (step.status === "running") return "bg-primary bg-opacity-10 border border-primary border-opacity-25";
            if (step.status === "success") return "bg-success bg-opacity-10";
            if (step.status === "failed")  return "bg-danger bg-opacity-10 border border-danger border-opacity-25";
            return "bg-light";
        };

        const stepTextClass = (step) => {
            if (step.status === "pending") return "text-muted";
            if (step.status === "failed")  return "text-danger";
            return "";
        };

        return {
            overallStatus, steps, errorMessage, durationSecs,
            outputVisible, retrying, activeVersion,
            isRunning, isDone, isFailed,
            headerBg, summaryBg, headerTitle, totalDuration,
            progressPercent, progressLabel, progressBarClass, progressTextClass,
            elapsedSeconds, elapsedFormatted,
            open, close, retry,
            remoteSubSteps, toggleOutput, formatDuration, stepRowClass, stepTextClass,
        };
    },
};
</script>

<style scoped>
.deploy-step {
    transition: background-color 0.3s ease;
}
.min-width-0 {
    min-width: 0;
}
</style>
