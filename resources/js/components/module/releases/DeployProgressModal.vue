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
                        <span v-if="version" class="badge bg-white bg-opacity-25 text-white ms-auto fw-normal">
                            {{ version }}
                        </span>
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
                            class="deploy-step d-flex align-items-start mb-2 p-3 rounded-3"
                            :class="stepRowClass(step)"
                        >
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

                                <!-- Output del paso -->
                                <div v-if="step.output">
                                    <!-- Siempre visible si falló -->
                                    <pre
                                        v-if="step.status === 'failed' || outputVisible[step.key]"
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
import { ref, computed, watch } from "vue";
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
        const startedAt     = ref(null);
        const finishedAt    = ref(null);
        const durationSecs  = ref(null);
        const outputVisible = ref({});
        const retrying      = ref(false);

        let pollTimer = null;

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
            if (overallStatus.value === "success")  return "bg-success";
            if (isFailed.value)                      return "bg-danger";
            return "bg-primary";
        });

        const summaryBg = computed(() =>
            overallStatus.value === "success" ? "bg-success bg-opacity-10" : "bg-danger bg-opacity-10"
        );

        const headerTitle = computed(() => {
            if (overallStatus.value === "success")  return "Deploy completado exitosamente";
            if (overallStatus.value === "failed")   return "Deploy fallido";
            if (overallStatus.value === "rolled_back") return "Deploy revertido";
            return "Desplegando versión...";
        });

        const totalDuration = computed(() => {
            if (!durationSecs.value) return null;
            const s = durationSecs.value;
            if (s < 60) return `${s}s`;
            return `${Math.floor(s / 60)}m ${s % 60}s`;
        });

        // ── Métodos ───────────────────────────────────────────────────────────

        const poll = async () => {
            if (!props.deploymentId) return;
            try {
                const { data } = await axios.get(
                    `/releases/deployment/${props.deploymentId}/status`
                );
                overallStatus.value = data.status;
                steps.value         = data.steps ?? [];
                errorMessage.value  = data.error_message;
                startedAt.value     = data.started_at;
                finishedAt.value    = data.finished_at;
                durationSecs.value  = data.duration_seconds;
            } catch (e) {
                console.error("Error al obtener estado del deploy:", e);
            }

            if (isDone.value) {
                clearTimeout(pollTimer);
            } else {
                pollTimer = setTimeout(poll, 2500);
            }
        };

        const open = () => {
            overallStatus.value = "pending";
            steps.value         = [];
            errorMessage.value  = null;
            durationSecs.value  = null;
            outputVisible.value = {};
            clearTimeout(pollTimer);
            $("#deployProgressModal").modal("show");
            poll();
        };

        const close = () => {
            clearTimeout(pollTimer);
            $("#deployProgressModal").modal("hide");
            emit("closed", overallStatus.value);
        };

        const retry = async () => {
            if (!props.deploymentId) return;
            retrying.value = true;
            try {
                const { data } = await axios.post(
                    `/releases/deployment/${props.deploymentId}/retry`
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
            outputVisible.value = {
                ...outputVisible.value,
                [key]: !outputVisible.value[key],
            };
        };

        // ── Helpers ───────────────────────────────────────────────────────────

        const formatDuration = (ms) => {
            if (ms < 1000) return `${ms}ms`;
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
            outputVisible, retrying,
            isRunning, isDone, isFailed,
            headerBg, summaryBg, headerTitle, totalDuration,
            open, close, retry,
            toggleOutput, formatDuration, stepRowClass, stepTextClass,
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
