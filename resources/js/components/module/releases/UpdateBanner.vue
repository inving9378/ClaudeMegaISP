<template>
    <!-- Contenedor: solo se renderiza en consumidoras (enabled=true). En el publicador
         (.11, updates.enabled=false) el componente no muestra nada, igual que antes. -->
    <div v-if="enabled" class="update-banner-wrap">
        <!-- Banner PERSISTENTE de deploy en curso / recién terminado.
             Se re-engancha solo al cargar el dashboard (sobrevive a reloads y lo ve
             cualquier admin), con % y paso actual. Clic en "Ver detalle" reabre el modal. -->
        <div
            v-if="activeDeploy"
            class="update-banner alert d-flex align-items-center gap-3 mb-3 shadow-sm"
            :class="activeBannerClass"
            role="alert"
        >
            <div class="flex-shrink-0">
                <span v-if="activeRunning" class="spinner-border spinner-border-sm"></span>
                <i v-else-if="activeDeploy.status === 'success'" class="bi bi-check-circle-fill fs-5 text-success"></i>
                <i v-else class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
            </div>

            <div class="flex-grow-1 min-width-0">
                <strong v-if="activeRunning">
                    Aplicando actualización<span v-if="activeDeploy.version"> — {{ activeDeploy.version }}</span>…
                </strong>
                <strong v-else-if="activeDeploy.status === 'success'" class="text-success">
                    Actualización aplicada<span v-if="activeDeploy.version"> — {{ activeDeploy.version }}</span>
                </strong>
                <template v-else>
                    <strong class="text-danger">La actualización falló</strong>
                    <div
                        v-if="activeDeploy.errorMessage"
                        class="small text-danger mt-1"
                        style="white-space:pre-wrap;word-break:break-word"
                    >{{ activeDeploy.errorMessage }}</div>
                    <div v-else class="small text-danger mt-1">Revisa el detalle.</div>
                </template>

                <div v-if="activeRunning" class="mt-1">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>{{ activeDeploy.currentStep || 'Iniciando…' }}</span>
                        <span>{{ activeDeploy.percent }}%<span v-if="activeDeploy.progressText"> · {{ activeDeploy.progressText }}</span></span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:6px;background:#e9ecef">
                        <div
                            class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                            :style="{ width: activeDeploy.percent + '%', transition: 'width 0.6s ease' }"
                        ></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-shrink-0 align-items-start">
                <button v-if="activeDeploy.status === 'success'" class="btn btn-sm btn-success text-white" @click="reloadPage">
                    <i class="bi bi-arrow-clockwise me-1"></i> Recargar
                </button>
                <button class="btn btn-sm" :class="activeRunning ? 'btn-primary' : 'btn-outline-secondary'" @click="reopenModal">
                    <i class="bi bi-eye me-1"></i> Ver detalle
                </button>
                <button v-if="!activeRunning" type="button" class="btn-close" @click="dismissActive" aria-label="Cerrar"></button>
            </div>
        </div>

        <!-- Banner de actualización disponible -->
        <div v-if="updateAvailable" class="update-banner alert alert-info alert-dismissible d-flex align-items-center gap-3 mb-3 shadow-sm" role="alert">
            <!-- Icono -->
            <div class="flex-shrink-0">
                <i class="bi bi-arrow-up-circle-fill fs-4 text-info"></i>
            </div>

            <!-- Texto -->
            <div class="flex-grow-1">
                <strong>Actualización disponible — {{ release.tag }}</strong>
                <span v-if="release.name && release.name !== release.tag" class="text-muted ms-1">{{ release.name }}</span>
                <div v-if="release.published_at" class="small text-muted">
                    Publicada {{ formatDate(release.published_at) }}
                </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex gap-2 flex-shrink-0">
                <button
                    v-if="release.body"
                    class="btn btn-sm btn-outline-info"
                    @click="showChangelog = true"
                >
                    <i class="bi bi-list-ul me-1"></i> Ver mejoras
                </button>

                <button
                    class="btn btn-sm btn-info text-white"
                    :disabled="applying"
                    @click="applyUpdate"
                >
                    <span v-if="applying" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-cloud-download me-1"></i>
                    {{ applying ? 'Iniciando…' : 'Actualizar ahora' }}
                </button>
            </div>

            <button type="button" class="btn-close ms-1" @click="dismiss" aria-label="Cerrar"></button>
        </div>

        <!-- Botón de chequeo manual on-demand (visible aunque no haya update) -->
        <div v-if="showCheckButton" class="d-flex align-items-center gap-2 mb-3">
            <button
                class="btn btn-sm btn-outline-secondary"
                :disabled="checking"
                @click="checkNow"
            >
                <span v-if="checking" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-arrow-repeat me-1"></i>
                {{ checking ? 'Buscando…' : 'Buscar actualizaciones' }}
            </button>
            <span v-if="upToDate" class="small text-success">
                <i class="bi bi-check-circle me-1"></i> Estás al día
            </span>
        </div>

        <!-- Modal de mejoras -->
        <div v-if="showChangelog" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title fw-semibold">
                            <i class="bi bi-stars me-2 text-info"></i>
                            Mejoras en {{ release.tag }}
                        </h6>
                        <button type="button" class="btn-close" @click="showChangelog = false"></button>
                    </div>
                    <div class="modal-body">
                        <pre class="small text-wrap" style="white-space:pre-wrap;word-break:break-word">{{ release.body }}</pre>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-sm" @click="showChangelog = false">Cerrar</button>
                        <button
                            class="btn btn-info btn-sm text-white"
                            :disabled="applying"
                            @click="showChangelog = false; applyUpdate()"
                        >
                            <i class="bi bi-cloud-download me-1"></i> Actualizar ahora
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de progreso (reutiliza el componente existente) -->
        <deploy-progress-modal
            ref="progressModal"
            @closed="onDeployClosed"
        ></deploy-progress-modal>
    </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "UpdateBanner",

    setup() {
        const enabled         = ref(false);
        const showCheckButton = ref(false);
        const updateAvailable = ref(false);
        const release         = ref({});
        const showChangelog   = ref(false);
        const applying        = ref(false);
        const progressModal   = ref(null);
        const checking        = ref(false);
        const upToDate        = ref(false);

        // ── Deploy en curso (auto-reenganche + banner persistente) ──────────────
        const activeDeploy = ref(null); // { id, version, status, percent, currentStep, progressText }
        let activePollTimer = null;
        let autoOpenedId    = null;     // evita re-abrir el modal en cada poll/reload

        const activeRunning = computed(() =>
            activeDeploy.value && ["pending", "running"].includes(activeDeploy.value.status)
        );

        const activeBannerClass = computed(() => {
            if (!activeDeploy.value) return "";
            if (activeDeploy.value.status === "success") return "alert-success";
            if (["failed", "rolled_back"].includes(activeDeploy.value.status)) return "alert-danger";
            return "alert-primary";
        });

        // Deriva %, paso actual y texto de progreso desde los steps del status.
        const deriveActive = (data) => {
            const steps   = data.steps ?? [];
            const done    = steps.filter(s => ["success", "failed", "skipped"].includes(s.status)).length;
            const running = steps.find(s => s.status === "running");
            return {
                status:       data.status,
                percent:      steps.length ? Math.min(100, Math.round(((done + (running ? 0.5 : 0)) / steps.length) * 100)) : 0,
                currentStep:  running ? running.name : null,
                progressText: steps.length ? `${done}/${steps.length} pasos` : "",
                errorMessage: data.error_message ?? null,
            };
        };

        const pollActive = async () => {
            if (!activeDeploy.value) return;
            try {
                const { data } = await axios.get(`/releases/deployment/${activeDeploy.value.id}/status`);
                activeDeploy.value = { ...activeDeploy.value, ...deriveActive(data) };
                if (["success", "failed", "rolled_back"].includes(data.status)) {
                    clearTimeout(activePollTimer);
                    activePollTimer = null;
                    return; // el banner queda mostrando el estado final
                }
            } catch {
                // silencioso
            }
            activePollTimer = setTimeout(pollActive, 2500);
        };

        const startActive = (id, version, status = "running") => {
            if (!id) return;
            activeDeploy.value = { id, version, status, percent: 0, currentStep: null, progressText: "" };
            clearTimeout(activePollTimer);
            pollActive();
        };

        const reopenModal  = () => { if (activeDeploy.value) progressModal.value?.open(activeDeploy.value.id, activeDeploy.value.version); };
        const reloadPage   = () => window.location.reload();
        const dismissActive = () => { clearTimeout(activePollTimer); activePollTimer = null; activeDeploy.value = null; };

        // Aplica el payload de /status o /check (mismo shape) al estado del componente.
        const applyStatus = (data) => {
            enabled.value         = !!data.enabled;
            showCheckButton.value = !!data.show_check_button;
            if (data.update_available && data.release) {
                updateAvailable.value = true;
                release.value = data.release;
            } else {
                updateAvailable.value = false;
            }
        };

        onMounted(async () => {
            try {
                const { data } = await axios.get("/api/updates/status");
                applyStatus(data);

                // Hay un deploy aplicándose ahora mismo → re-engancharse: banner + abrir el
                // modal una sola vez (el v-if de enabled ya renderizó el modal en este tick).
                if (data.active_deployment) {
                    const ad = data.active_deployment;
                    startActive(ad.id, ad.version, ad.status);
                    if (autoOpenedId !== ad.id) {
                        autoOpenedId = ad.id;
                        await nextTick();
                        progressModal.value?.open(ad.id, ad.version);
                    }
                }
            } catch {
                // Silencioso: si el endpoint falla no rompemos el dashboard
            }
        });

        // Botón "Buscar actualizaciones": fuerza el chequeo on-demand sin esperar el cron.
        const checkNow = async () => {
            if (checking.value) return;
            checking.value = true;
            upToDate.value = false;
            try {
                const { data } = await axios.post("/api/updates/check");
                applyStatus(data);
                if (!data.update_available) {
                    upToDate.value = true;
                    setTimeout(() => { upToDate.value = false; }, 4000);
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo consultar actualizaciones.", "error");
            } finally {
                checking.value = false;
            }
        };

        const applyUpdate = async () => {
            if (applying.value) return;
            applying.value = true;
            try {
                const { data } = await axios.post("/api/updates/apply");
                if (data.success) {
                    updateAvailable.value = false;
                    autoOpenedId = data.deployment_id;          // ya lo abrimos aquí → no re-abrir en el reenganche
                    startActive(data.deployment_id, data.version, "running");
                    progressModal.value?.open(data.deployment_id, data.version);
                } else {
                    Swal.fire("Error", data.message || "No se pudo iniciar la actualización.", "error");
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo iniciar la actualización.", "error");
            } finally {
                applying.value = false;
            }
        };

        const onDeployClosed = (status) => {
            if (status === "success") {
                Swal.fire({
                    icon: "success",
                    title: "¡Actualización completada!",
                    text: "La instancia se actualizó correctamente. Recarga la página para ver los cambios.",
                    confirmButtonText: "Recargar",
                }).then(() => window.location.reload());
            }
        };

        const dismiss = () => {
            updateAvailable.value = false;
        };

        const formatDate = (iso) => {
            try {
                return new Date(iso).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
            } catch {
                return iso;
            }
        };

        return {
            enabled, showCheckButton, updateAvailable, release, showChangelog, applying, progressModal, checking, upToDate,
            checkNow, applyUpdate, onDeployClosed, dismiss, formatDate,
            activeDeploy, activeRunning, activeBannerClass, reopenModal, reloadPage, dismissActive,
        };
    },
};
</script>
