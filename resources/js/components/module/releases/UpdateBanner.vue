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
                <template v-if="release.installed_tag && release.jump_count > 1">
                    <strong>
                        Estás en {{ release.installed_tag }} · vas a aplicar {{ release.tag }} · incluye {{ release.jump_count }} versiones
                    </strong>
                </template>
                <template v-else>
                    <strong>Actualización disponible — {{ release.tag }}</strong>
                    <span v-if="release.name && release.name !== release.tag" class="text-muted ms-1">{{ release.name }}</span>
                </template>
                <div v-if="release.published_at" class="small text-muted">
                    Publicada {{ formatDate(release.published_at) }}
                </div>
                <!-- Aviso destacado de salto grande (item #9990672, punto 4) -->
                <div v-if="release.is_big_jump" class="small text-warning fw-semibold mt-1">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Salto de {{ release.jump_count }} versiones — revisa el detalle antes de actualizar.
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
            <!-- "No se pudo verificar" ≠ "estás al día": ver item #529. -->
            <span v-if="checkFailed" class="small text-warning" :title="checkError || ''">
                <i class="bi bi-exclamation-triangle me-1"></i> No se pudo verificar
            </span>
        </div>

        <!-- Modal de mejoras -->
        <div v-if="showChangelog" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title fw-semibold">
                            <i class="bi bi-stars me-2 text-info"></i>
                            <template v-if="release.jump_count > 1">Mejoras de {{ release.installed_tag }} a {{ release.tag }}</template>
                            <template v-else>Mejoras en {{ release.tag }}</template>
                        </h6>
                        <button type="button" class="btn-close" @click="showChangelog = false"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Aviso de salto grande + conteo de migraciones del rango completo (#9990672) -->
                        <div v-if="release.is_big_jump" class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Este salto incluye {{ release.jump_count }} versiones intermedias.
                        </div>
                        <div v-if="release.migrations" class="small text-muted mb-3">
                            <i class="bi bi-database-fill-gear me-1"></i>
                            <template v-if="release.migrations.unknown">No se pudo determinar el número de migraciones pendientes.</template>
                            <template v-else-if="release.migrations.count === 0">Sin migraciones pendientes en este rango.</template>
                            <template v-else>{{ release.migrations.count }} migración(es) pendiente(s) en todo el rango.</template>
                        </div>

                        <!-- Changelog acumulado, agrupado por versión (más viejo primero) -->
                        <template v-if="release.versions && release.versions.length">
                            <div v-for="v in release.versions" :key="v.tag" class="mb-3 pb-3 border-bottom">
                                <h6 class="fw-semibold mb-1">
                                    {{ v.tag }}
                                    <span v-if="v.name && v.name !== v.tag" class="text-muted ms-1 fw-normal">— {{ v.name }}</span>
                                </h6>
                                <!-- #9991213 — `body_html` viene RENDERIZADO y SANEADO del backend (markdown→CommonMark, mismo ReleaseNotesRenderer de #9991208); nunca body crudo. -->
                                <div class="small release-notes mb-0" v-html="v.body_html !== undefined ? v.body_html : ''"></div>
                            </div>

                            <div v-if="manualStepsList.length" class="mt-3">
                                <h6 class="fw-semibold"><i class="bi bi-list-check me-1"></i> Pasos manuales acumulados</h6>
                                <div v-for="m in manualStepsList" :key="m.tag" class="small mb-2">
                                    <strong>{{ m.tag }}:</strong>
                                    <pre class="small text-wrap mb-0" style="white-space:pre-wrap;word-break:break-word">{{ m.steps }}</pre>
                                </div>
                            </div>
                        </template>
                        <div v-else class="small release-notes" v-html="release.body_html !== undefined ? release.body_html : ''"></div>
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
        const checkFailed     = ref(false);   // no se pudo consultar GitHub (≠ estás al día)
        const checkError      = ref(null);    // motivo, como tooltip del aviso

        // ── Deploy en curso (auto-reenganche + banner persistente) ──────────────
        const activeDeploy = ref(null); // { id, version, status, percent, currentStep, progressText }
        let activePollTimer = null;
        let autoOpenedId    = null;     // evita re-abrir el modal en cada poll/reload

        const activeRunning = computed(() =>
            activeDeploy.value && ["pending", "running"].includes(activeDeploy.value.status)
        );

        // Pasos manuales acumulados del rango instalada..destino (item #9990672, punto 5).
        const manualStepsList = computed(() => release.value.manual_steps || []);

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
            checkFailed.value     = !!data.check_failed;
            checkError.value      = data.check_error || null;
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
                // Solo se afirma "estás al día" cuando la consulta SÍ pudo hacerse.
                if (!data.update_available && !data.check_failed) {
                    upToDate.value = true;
                    setTimeout(() => { upToDate.value = false; }, 4000);
                }
                if (data.check_failed) {
                    setTimeout(() => { checkFailed.value = false; }, 8000);
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
            checkFailed, checkError, manualStepsList,
            checkNow, applyUpdate, onDeployClosed, dismiss, formatDate,
            activeDeploy, activeRunning, activeBannerClass, reopenModal, reloadPage, dismissActive,
        };
    },
};
</script>
