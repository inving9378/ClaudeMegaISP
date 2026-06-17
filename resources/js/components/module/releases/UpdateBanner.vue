<template>
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
</template>

<script>
import { ref, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "UpdateBanner",

    setup() {
        const updateAvailable = ref(false);
        const release         = ref({});
        const showChangelog   = ref(false);
        const applying        = ref(false);
        const dismissed       = ref(false);
        const progressModal   = ref(null);

        onMounted(async () => {
            try {
                const { data } = await axios.get("/api/updates/status");
                if (data.update_available && data.release) {
                    updateAvailable.value = true;
                    release.value = data.release;
                }
            } catch {
                // Silencioso: si el endpoint falla no rompemos el dashboard
            }
        });

        const applyUpdate = async () => {
            if (applying.value) return;
            applying.value = true;
            try {
                const { data } = await axios.post("/api/updates/apply");
                if (data.success) {
                    updateAvailable.value = false;
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

        return { updateAvailable, release, showChangelog, applying, progressModal, applyUpdate, onDeployClosed, dismiss, formatDate };
    },
};
</script>
