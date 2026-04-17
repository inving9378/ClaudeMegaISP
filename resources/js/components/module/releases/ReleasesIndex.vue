<template>
    <div class="container my-5 release-timeline">
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

        <releases-crud ref="crudModal" :id="currentId" @save="refreshList" />
    </div>
</template>

<script>
import { ref, onMounted, onBeforeUnmount, reactive } from "vue";
import axios from "axios";
import ReleasesCrud from "./ReleasesCrud.vue";
import Swal from "sweetalert2";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

export default {
    name: "ReleasesIndex",
    components: { ReleasesCrud },
    props: {
        releases: { type: String },
        next_page_url: { type: String },
    },
    setup(props) {
        const releases = ref(JSON.parse(props.releases));
        const nextPageUrl = ref(props.next_page_url);
        const isLoading = ref(false);

        const crudModal = ref(null);
        const currentId = ref(null);
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
            releases,
            crudModal,
            currentId,
            showModal,
            refreshList,
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
