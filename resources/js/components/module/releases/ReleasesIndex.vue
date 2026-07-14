<template>
    <div class="container my-5 release-timeline">

        <!-- ── Encabezado: la Torre de control es el contenedor/hub ── -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-broadcast-pin fs-4"></i>
            <h1 class="h4 fw-bold mb-0">Torre de control</h1>
        </div>

        <!-- ── Sub-secciones de la Torre ── -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'panorama' }" href="#" @click.prevent="tab = 'panorama'">
                    <i class="bi bi-speedometer2 me-1"></i> Panorama
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'roadmap' }" href="#" @click.prevent="tab = 'roadmap'">
                    <i class="bi bi-map me-1"></i> Hoja de ruta
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'terminales' }" href="#" @click.prevent="tab = 'terminales'">
                    <i class="bi bi-terminal me-1"></i> Terminales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'integracion' }" href="#" @click.prevent="tab = 'integracion'">
                    <i class="bi bi-diagram-3 me-1"></i> Integración
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'historial' }" href="#" @click.prevent="tab = 'historial'">
                    <i class="bi bi-clock-history me-1"></i> Historial de versiones
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: tab === 'reporte' }" href="#" @click.prevent="tab = 'reporte'">
                    <i class="bi bi-clipboard-data me-1"></i> Reporte
                </a>
            </li>
        </ul>

        <!-- ── Tab: Reporte ── -->
        <audit-report v-if="tab === 'reporte'" />

        <!-- ── Tab: Hoja de ruta ── -->
        <roadmap-tab v-if="tab === 'roadmap'" />

        <!-- ── Sub-sección: Panorama (dashboard de la Torre) ── -->
        <torre-control v-if="tab === 'panorama'" />

        <!-- ── Sub-sección: Terminales en vivo (rejilla por sesión, #350) ── -->
        <torre-terminales v-if="tab === 'terminales'" />

        <!-- ── Sub-sección: Integración / Ramas ── -->
        <integracion-ramas v-if="tab === 'integracion'" />

        <!-- ── Tab: Historial ── -->
        <template v-if="tab === 'historial'">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="fw-bold">Historial de versiones</h2>
            <button
                class="btn btn-primary px-3 py-2 fw-semibold"
                type="button"
                @click="showModal()"
                v-if="hasPermission.data.canView('release_add_release')"
            >
                + Nueva versión
            </button>
        </div>

        <!-- Timeline -->
        <div class="timeline-wrapper position-relative">
            <div
                v-for="(release, index) in releases"
                :key="release.id"
                class="timeline-item d-flex align-items-start mb-5"
                :class="index % 2 === 0 ? 'flex-row' : 'flex-row-reverse'"
            >
                <div
                    class="timeline-line position-absolute top-0 start-50 translate-middle-x"
                ></div>
                <div class="timeline-dot position-relative bg-primary"></div>

                <div
                    class="timeline-card shadow-sm rounded-4 p-4"
                    :class="
                        index % 2 === 0
                            ? 'ms-auto text-start'
                            : 'me-auto text-start'
                    "
                >
                    <h4
                        class="fw-bold text-dark mb-2 cursor-pointer"
                        @click="copyToClipboard(release.version)"
                        title="Haz clic para copiar"
                    >
                        {{ release.version }}
                        <i
                            v-if="copiedVersion === release.version"
                            class="bi bi-check2 text-success ms-2"
                        ></i>
                    </h4>

                    <hr />

                    <!-- Título en línea -->
                    <div class="d-flex align-items-center mb-2">
                        <strong class="me-2">Título:</strong>
                        <p class="text-muted">{{ release.title }}</p>
                    </div>

                    <!-- Descripción debajo -->
                    <div>
                        <strong>Descripción:</strong>
                        <p class="text-muted mb-3">{{ release.summary }}</p>
                    </div>
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <small class="fw-semibold text-secondary">
                            {{ formatDate(release.release_date) }}
                        </small>
                        <div>
                            <button
                                v-if="release.tag_exists === false && hasPermission.data.canView('release_add_release')"
                                class="btn btn-sm btn-outline-warning me-2"
                                @click="redeploy(release)"
                                :disabled="redeployingId === release.id"
                                title="Esta versión está registrada pero su tag no existe en git: no se publicó. Re-lanza el deploy."
                            >
                                <span v-if="redeployingId === release.id" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-arrow-clockwise me-1"></i>
                                Re-desplegar
                            </button>
                            <button
                                class="btn btn-sm btn-outline-secondary me-2"
                                @click="showModal(release)"
                                v-if="
                                    hasPermission.data.canView(
                                        'release_edit_release'
                                    )
                                "
                            >
                                Editar
                            </button>
                            <button
                                class="btn btn-sm btn-outline-primary"
                                @click="goToVersion(release)"
                            >
                                Ver más
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loader -->
        <div v-if="isLoading" class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Cargando más versiones...</p>
        </div>

        <releases-crud
            ref="crudModal"
            :id="currentId"
            @save="refreshList"
            @deploy-started="onDeployStarted"
        />
        <deploy-progress-modal
            ref="deployModal"
            :deployment-id="activeDeploymentId"
            :version="activeDeploymentVersion"
            @closed="onDeployClosed"
        />
        </template><!-- /tab historial -->

    </div>
</template>

<script>
import { ref, onMounted, onBeforeUnmount, reactive } from "vue";
import axios from "axios";
import ReleasesCrud from "./ReleasesCrud.vue";
import AuditReport from "./torre-control/AuditReport.vue";
import RoadmapTab from "./torre-control/RoadmapTab.vue";
import TorreControl from "./torre-control/TorreControl.vue";
import TorreTerminales from "./torre-control/TorreTerminales.vue";
import IntegracionRamas from "./torre-control/IntegracionRamas.vue";
import DeployProgressModal from "./DeployProgressModal.vue";
import Swal from "sweetalert2";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

export default {
    name: "ReleasesIndex",
    components: { ReleasesCrud, AuditReport, RoadmapTab, TorreControl, TorreTerminales, IntegracionRamas, DeployProgressModal },
    props: {
        releases: { type: String },
        next_page_url: { type: String },
    },
    setup(props) {
        const tab = ref('panorama');
        const releases = ref(JSON.parse(props.releases));
        const nextPageUrl = ref(props.next_page_url);
        const isLoading = ref(false);

        const crudModal = ref(null);
        const deployModal = ref(null);
        const currentId = ref(null);
        const activeDeploymentId = ref(null);
        const activeDeploymentVersion = ref(null);
        const redeployingId = ref(null);
        const copiedVersion = ref(null);
        const hasPermission = reactive({
            data: new Permission({}),
        });

        const handleScroll = async () => {
            const scrollBottom =
                window.innerHeight + window.scrollY >=
                document.body.offsetHeight - 300;

            if (scrollBottom && nextPageUrl.value && !isLoading.value) {
                await loadMore();
            }
        };

        const loadMore = async () => {
            if (!nextPageUrl.value) return;
            isLoading.value = true;
            try {
                const { data } = await axios.get(nextPageUrl.value);
                releases.value.push(...data.data);
                nextPageUrl.value = data.next_page_url;
            } catch (e) {
                console.error("Error cargando más versiones", e);
            } finally {
                isLoading.value = false;
            }
        };

        const showModal = async (release = null) => {
            currentId.value = release ? release.id : null;
            await crudModal.value.load(currentId.value);
            $("#releaseModal").modal("show");
        };

        const refreshList = (saved) => {
            const i = releases.value.findIndex((r) => r.id === saved.id);
            if (i !== -1) releases.value[i] = saved;
            else releases.value.unshift(saved);
        };

        const formatDate = (date) =>
            new Date(date).toLocaleDateString("es-ES", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });

        const onDeployStarted = ({ deploymentId, version }) => {
            activeDeploymentId.value = deploymentId;
            activeDeploymentVersion.value = version;
            // Pasar id/version directo: el prop del modal se actualiza recién
            // en el próximo tick, así el primer poll() arranca de inmediato.
            deployModal.value.open(deploymentId, version);
        };

        const onDeployClosed = (finalStatus) => {
            // Si el re-deploy terminó OK, el tag ya existe → oculta el botón sin recargar.
            if (finalStatus === "success" && activeDeploymentVersion.value) {
                const r = releases.value.find(
                    (x) => x.version === activeDeploymentVersion.value
                );
                if (r) r.tag_exists = true;
            }
            activeDeploymentId.value = null;
            activeDeploymentVersion.value = null;
        };

        const redeploy = async (release) => {
            const confirm = await Swal.fire({
                icon: "warning",
                title: `¿Re-desplegar ${release.version}?`,
                html: "Esta versión está registrada pero <strong>su tag no existe en git</strong> (no se publicó).<br>Se re-lanzará el pipeline: commit, tag, push a GitHub y deploy a <strong>producción</strong>.",
                showCancelButton: true,
                confirmButtonText: "Sí, re-desplegar",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#d33",
            });
            if (!confirm.isConfirmed) return;

            redeployingId.value = release.id;
            try {
                const { data } = await axios.post(
                    `/releases/${release.id}/redeploy`
                );
                if (data.success) {
                    onDeployStarted({
                        deploymentId: data.deployment_id,
                        version: data.version,
                    });
                } else {
                    Swal.fire("No se pudo", data.message || "Error", "error");
                }
            } catch (e) {
                Swal.fire(
                    "Error",
                    e.response?.data?.message || "Error al re-desplegar",
                    "error"
                );
            } finally {
                redeployingId.value = null;
            }
        };

        const goToVersion = (release) => {
            window.location.href = `/releases/${release.version}`;
        };

        onMounted(async () => {
            window.addEventListener("scroll", handleScroll);
            hasPermission.data = new Permission(await allViewHasPermission());
        });

        onBeforeUnmount(() =>
            window.removeEventListener("scroll", handleScroll)
        );

        const copyToClipboard = async (text) => {
            try {
                await navigator.clipboard.writeText(text);
                copiedVersion.value = text;

                // Mensaje temporal con SweetAlert (opcional)
                Swal.fire({
                    toast: true,
                    icon: "success",
                    title: "Versión copiada al portapapeles",
                    timer: 1500,
                    position: "bottom-end",
                    showConfirmButton: false,
                });

                // Quitar el check después de un momento
                setTimeout(() => (copiedVersion.value = null), 2000);
            } catch (err) {
                console.error("Error al copiar:", err);
                Swal.fire(
                    "Error",
                    err.message || "Hubo un error al copiar la versión",
                    "error"
                );
            }
        };

        return {
            tab,
            releases,
            crudModal,
            deployModal,
            currentId,
            activeDeploymentId,
            activeDeploymentVersion,
            redeployingId,
            redeploy,
            showModal,
            refreshList,
            onDeployStarted,
            onDeployClosed,
            formatDate,
            goToVersion,
            isLoading,
            copyToClipboard,
            copiedVersion,
            hasPermission,
        };
    },
};
</script>

<style scoped></style>
