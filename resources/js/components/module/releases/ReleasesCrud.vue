<template>
    <div
        class="modal fade"
        id="releaseModal"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Version</h6>
                </div>
                <div class="modal-body m-0">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <!-- #versionado-2026-09-08 — estado del cálculo del número de versión.
                             ERROR (rojo): el resolver no pudo calcular (ni remoto ni tags locales) →
                             se explica el motivo en vez de dejar el campo mudo. AVISO (ámbar): el
                             número salió de tags locales sin confirmar con GitHub — usable, pero se ve. -->
                        <div v-if="!id && versionError"
                             class="alert alert-danger py-2 px-3 mt-1 mb-2 d-flex align-items-start"
                             style="font-size:0.85rem;">
                            <i class="bi bi-x-octagon-fill me-2 mt-1"></i>
                            <span>No se pudo calcular el número de versión: {{ versionError }}</span>
                        </div>
                        <div v-if="!id && versionAviso"
                             class="alert alert-warning py-2 px-3 mt-1 mb-2 d-flex align-items-start"
                             style="font-size:0.85rem;">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                            <span>{{ versionAviso }}</span>
                        </div>

                        <!-- Armado de versión (#933) — candidatos integrados a main desde el último
                             tag: marcar/desmarcar cuáles entran en ESTA versión. Lo no marcado queda
                             disponible para la siguiente. Solo en creación. -->
                        <div v-if="!id" class="px-1 pb-2">
                            <hr class="my-2" />
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label mb-0 fw-semibold text-muted small">
                                    <i class="bi bi-tags me-1"></i> Armado de versión
                                    <span class="fw-normal">({{ marcadosCount }} marcado{{ marcadosCount === 1 ? '' : 's' }} de {{ candidatos.length }} candidatos)</span>
                                </label>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="verificarDependencias"
                                    :disabled="checkingDeps || marcadosCount === 0"
                                >
                                    <span v-if="checkingDeps" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-shield-check me-1"></i>
                                    Verificar dependencias
                                </button>
                            </div>
                            <p class="text-muted small mb-2" style="font-size:0.8rem;">
                                Sin marcar nada, la versión se corta con TODO lo integrado a main desde el último tag (comportamiento actual, sin cambio).
                            </p>

                            <div v-if="loadingCandidatos" class="text-center py-2">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                            <div v-else-if="candidatos.length === 0" class="text-muted small fst-italic">
                                No hay items integrados a main desde el último tag.
                            </div>
                            <div v-else class="candidatos-lista border rounded p-2" style="max-height:220px;overflow-y:auto;">
                                <div
                                    v-for="c in candidatos"
                                    :key="c.id"
                                    class="form-check d-flex align-items-start py-1"
                                >
                                    <input
                                        class="form-check-input mt-1 me-2"
                                        type="checkbox"
                                        :id="'cand-' + c.id"
                                        :checked="c.marcado_version"
                                        :disabled="marcandoId === c.id"
                                        @change="toggleMarcado(c)"
                                    />
                                    <label class="form-check-label small" :for="'cand-' + c.id">
                                        <span class="text-muted">#{{ c.id }}</span>
                                        {{ c.title }}
                                        <span v-if="c.modulo" class="badge bg-light text-dark border ms-1">{{ c.modulo }}</span>
                                    </label>
                                </div>
                            </div>

                            <div v-if="dependenciasChecked" class="mt-2">
                                <div v-if="dependencias.length === 0" class="alert alert-success py-2 px-3 mb-0" style="font-size:0.85rem;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Sin dependencias detectadas entre lo marcado.
                                </div>
                                <div v-else class="alert alert-warning py-2 px-3 mb-0" style="font-size:0.85rem;">
                                    <div class="fw-semibold mb-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        {{ dependencias.length }} posible{{ dependencias.length === 1 ? '' : 's' }} problema{{ dependencias.length === 1 ? '' : 's' }} de cherry-pick — revisa antes de cortar la versión:
                                    </div>
                                    <ul class="mb-0 ps-3">
                                        <li v-for="(v, idx) in dependencias" :key="idx">{{ v.detalle }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de resumen IA — solo en creación -->
                        <div v-if="!id" class="px-1 pb-2">
                            <hr class="my-2" />
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label mb-0 fw-semibold text-muted small">
                                    <i class="bi bi-stars me-1"></i> Resumen de cambios
                                    <span class="fw-normal">(opcional · editable · se publicará en "Ver más")</span>
                                </label>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="generateChangelog"
                                    :disabled="aiLoading"
                                >
                                    <span v-if="aiLoading" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="bi bi-stars me-1"></i>
                                    {{ aiLoading ? 'Generando… (puede tardar 1-3 min)' : 'Generar resumen con IA (opcional, tarda 1-3 min)' }}
                                </button>
                            </div>
                            <div
                                v-if="aiTruncationNotice"
                                class="alert alert-warning py-2 px-3 mb-2 d-flex align-items-start"
                                style="font-size:0.85rem;"
                            >
                                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                <span>{{ aiTruncationNotice }}</span>
                            </div>
                            <textarea
                                v-model="aiDescription"
                                class="form-control form-control-sm"
                                rows="5"
                                placeholder="Haz clic en «Generar automáticamente» para que la IA analice los commits desde la versión anterior…"
                                style="font-size:0.85rem;resize:vertical;"
                            ></textarea>
                        </div>

                        <div class="form-group text-center mt-3">
                            <a
                                class="btn btn-secondary me-3"
                                href="javascript:void(0)"
                                @click="closeModal"
                                >Cerrar</a
                            >

                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any() || versionVacia"
                                :title="versionVacia ? 'Captura o espera el número de versión antes de crear' : ''"
                            >
                                {{ textoBotonGuardar }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, computed, nextTick, onMounted } from "vue";
import ComponentFormDefault from "../../ComponentFormDefault.vue";
import { requestFieldsByModule } from "../../../helpers/Request";
import Form from "../../../helpers/Form";
import { showLoading, hideLoading } from "../../../helpers/loading";
import Swal from "sweetalert2";

export default {
    name: "ReleasesCrud",
    emits: ["save", "deploy-started"],
    components: { ComponentFormDefault },
    props: {
        id: {
            type: [String, Number],
            default: null,
        },
    },
    setup(props, { emit }) {
        const fieldsJson    = ref({});
        const dataForm      = reactive({ data: new Form({}) });
        const loading       = ref(false);
        const aiDescription = ref('');
        const aiLoading     = ref(false);
        // Armado de versión (#933) — candidatos desde el último tag + detector de dependencias.
        const candidatos          = ref([]);
        const loadingCandidatos   = ref(false);
        const marcandoId          = ref(null);
        const dependencias        = ref([]);
        const dependenciasChecked = ref(false);
        const checkingDeps        = ref(false);
        const marcadosCount = computed(() => candidatos.value.filter((c) => c.marcado_version).length);
        // Aviso "imposible de ignorar" cuando el resumen generado no cubrió todo el rango de
        // commits (item roadmap #892) — antes el truncamiento era silencioso.
        const aiTruncationNotice = ref('');
        // Fase A #9990624 (A.3) — el botón principal es "Crear versión"; el resumen IA es aparte
        // y opcional. En edición dice "Guardar cambios".
        const textoBotonGuardar = computed(() => (props.id ? 'Guardar cambios' : 'Crear versión'));
        // #versionado-2026-09-08 — aviso ámbar (número desde tags locales) y error rojo (no se pudo
        // calcular) del cálculo de versión. Y el guard del botón: sin número no se puede crear.
        const versionAviso = ref('');
        const versionError = ref('');
        const versionVacia = computed(() => ! props.id && ! String(dataForm.data?.['version'] ?? '').trim());

        const requestEditedFieldsById = async (module, id) => {
            let fields = {};
            await axios
                .post(`/fields-by-module/${id}`, { module })
                .then((response) => {
                    fields = response.data;
                });
            return fields;
        };
        const load = async (id = null) => {
            try {
                showLoading("Cargando versión...");
                if (id) {
                    // modo edición
                    fieldsJson.value = await requestEditedFieldsById(
                        "Release",
                        id
                    );
                    fieldsJson.value.version.include = false;
                    dataForm.data = new Form(fieldsJson.value);

                } else {
                    fieldsJson.value = await requestFieldsByModule("Release");
                    // (A) Campo NO renderizado: registra ai_description para que
                    // Form.data() lo serialice (antes se asignaba suelto y se perdía).
                    fieldsJson.value.ai_description = {
                        field: "ai_description",
                        type: "hidden",
                        include: false,
                        value: "",
                    };
                    dataForm.data = new Form(fieldsJson.value);
                    // (B.1) Pre-llenar la versión sugerida por regla (editable).
                    // La versión la pone la regla, NO la IA.
                    versionAviso.value = '';
                    versionError.value = '';
                    try {
                        const { data } = await axios.get("/releases/next-version");
                        if (data.success && data.version) {
                            dataForm.data["version"] = data.version;
                            // #versionado-2026-09-08 — si el número salió de tags locales (no se
                            // pudo confirmar con GitHub), el backend manda un aviso: mostrarlo.
                            versionAviso.value = data.aviso || '';
                        }
                    } catch (e) {
                        // Fail-closed: no hay número. En vez de dejar el campo mudo, mostrar el
                        // motivo en español (condición #4). El backend manda 503 + message.
                        versionError.value =
                            e.response?.data?.message ||
                            'No se pudo contactar al servidor para calcular el número. Reintenta o captúralo a mano.';
                    }
                    loadCandidatos();
                }
            } catch (e) {
                Swal.fire(
                    "Error",
                    "No se pudo cargar la versión seleccionada",
                    "error"
                );
            } finally {
                hideLoading();
            }
        };

        const closeModal = () => {
            dataForm.data.reset();
            loading.value   = false;
            aiDescription.value = '';
            aiTruncationNotice.value = '';
            candidatos.value = [];
            dependencias.value = [];
            dependenciasChecked.value = false;
            nextTick(() => {
                window.bootstrap.Modal.getInstance(document.getElementById('releaseModal'))?.hide();
            });
        };

        const loadCandidatos = async () => {
            loadingCandidatos.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/integracion/version-candidatos');
                candidatos.value = data.items || [];
            } catch (e) {
                candidatos.value = [];
            } finally {
                loadingCandidatos.value = false;
            }
        };

        const toggleMarcado = async (item) => {
            marcandoId.value = item.id;
            dependenciasChecked.value = false; // el estado marcado cambió: la última verificación quedó obsoleta
            try {
                const { data } = await axios.post('/api/roadmap/integracion/marcar-version', { id: item.id });
                item.marcado_version = data.marcado_version;
            } catch (e) {
                Swal.fire('Error', e.response?.data?.error || 'No se pudo marcar/desmarcar el item.', 'error');
            } finally {
                marcandoId.value = null;
            }
        };

        const verificarDependencias = async () => {
            checkingDeps.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/integracion/version-dependencias');
                dependencias.value = data.violaciones || [];
                dependenciasChecked.value = true;
            } catch (e) {
                Swal.fire('Error', 'No se pudieron verificar las dependencias.', 'error');
            } finally {
                checkingDeps.value = false;
            }
        };

        // Item roadmap #9990626 (Fase 2+3 de #9990624) — antes esto esperaba una sola respuesta
        // síncrona que podía tardar 2-4 min con rangos grandes de commits. Ahora el backend
        // solo encola el job y devuelve un request_id de inmediato; aquí se hace polling cada
        // 4s (tope 5 min) hasta que el job deje el resultado listo. "Guardar" sigue sin
        // depender de aiLoading (ver :disabled del botón), así que esto solo bloquea el botón
        // "Generar automáticamente" mientras espera, nunca el guardado de la versión.
        const CHANGELOG_POLL_INTERVAL_MS = 4000;
        const CHANGELOG_POLL_TIMEOUT_MS  = 5 * 60 * 1000;

        const generateChangelog = async () => {
            aiLoading.value = true;
            aiTruncationNotice.value = '';
            try {
                const version = dataForm.data['version'] || '';
                const { data } = await axios.post('/releases/generate-changelog', { version });
                if (!data.success || !data.request_id) {
                    Swal.fire('Error', data.message || 'No se pudo iniciar la generación del resumen.', 'error');
                    aiLoading.value = false;
                    return;
                }
                await pollChangelogStatus(data.request_id);
            } catch (e) {
                Swal.fire('Error', e.response?.data?.message || 'No se pudo generar el resumen.', 'error');
                aiLoading.value = false;
            }
        };

        const pollChangelogStatus = (requestId) => {
            const startedAt = Date.now();
            return new Promise((resolve) => {
                const tick = async () => {
                    try {
                        const { data } = await axios.get(`/releases/generate-changelog/${requestId}`);
                        if (data.status === 'listo') {
                            // (B.2) Llena Título + Resumen + Mejoras (sobrescribe, sin confirmación).
                            // NO toca la versión. Si el JSON vino mal formado, el backend ya dejó
                            // título/resumen vacíos y el texto crudo en improvements.
                            dataForm.data['title']   = data.title || '';
                            dataForm.data['summary'] = data.summary || '';
                            aiDescription.value      = data.improvements || '';
                            // Si el backend no pudo cubrir todo el rango de commits, mostrarlo de
                            // forma imposible de ignorar (item roadmap #892).
                            aiTruncationNotice.value = data.truncado ? (data.aviso_truncamiento || '') : '';
                            aiLoading.value = false;
                            return resolve();
                        }
                        if (data.status === 'error') {
                            // Fase A #9990624 (A.4) — aviso amable, sin exponer el error técnico
                            // (data.message puede traer códigos HTTP/detalles de la API). La versión
                            // se puede crear igual; el resumen es opcional.
                            Swal.fire('El resumen no quedó listo', 'El resumen automático no quedó listo; puedes escribirlo tú o generarlo después. Igual puedes crear la versión.', 'info');
                            aiLoading.value = false;
                            return resolve();
                        }
                        // status === 'pendiente' -> seguir esperando, salvo que se acabe el tope.
                        if (Date.now() - startedAt > CHANGELOG_POLL_TIMEOUT_MS) {
                            Swal.fire('Aviso', 'La generación está tardando más de lo normal. Puedes seguir esperando o escribir el resumen a mano.', 'warning');
                            aiLoading.value = false;
                            return resolve();
                        }
                        setTimeout(tick, CHANGELOG_POLL_INTERVAL_MS);
                    } catch (e) {
                        // Fase A #9990624 (A.4) — no exponer el error técnico de red/servidor.
                        Swal.fire('El resumen no quedó listo', 'No se pudo consultar el estado del resumen automático; puedes escribirlo tú o intentarlo después. Igual puedes crear la versión.', 'info');
                        aiLoading.value = false;
                        resolve();
                    }
                };
                tick();
            });
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            showLoading("Guardando...");
            loading.value = true;
            const url = props.id
                ? `/releases/update/${props.id}`
                : `/releases/store`;

            // Adjuntar el resumen IA si fue generado (solo en creación)
            if (!props.id && aiDescription.value.trim()) {
                dataForm.data['ai_description'] = aiDescription.value.trim();
            }

            // Fase A #9990624 (A.4) — la versión se crea SIEMPRE, aunque el resumen no exista
            // (el resumen IA es opcional y puede haber fallado/tardado). Si nadie escribió ni
            // generó un resumen, se pone uno genérico para que la versión no quede sin descripción;
            // Irving puede editarlo o generarlo después.
            if (!props.id && !String(dataForm.data['summary'] || '').trim()) {
                const v = dataForm.data['version'] || 'nueva';
                dataForm.data['summary'] =
                    `Versión ${v}. El resumen automático no quedó listo; puedes escribirlo o generarlo con IA después.`;
            }

            dataForm.data
                .submit("post", url, "reset")
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        closeModal();
                        emit("save", response.model);
                        // Si la respuesta trae deployment_id es una release nueva → abrir modal deploy
                        if (response.deployment_id) {
                            emit("deploy-started", {
                                deploymentId: response.deployment_id,
                                version: response.model?.version,
                            });
                        } else {
                            Swal.fire("Éxito", message, "success");
                        }
                    } else {
                        Swal.fire("Error", message, "error");
                        loading.value = false;
                        hideLoading();
                    }
                })
                .catch((error) => {
                    console.log(error);
                    loading.value = false;
                    hideLoading();
                    // Form.submit() rechaza con error.response.data (el JSON body),
                    // no con el error axios completo. Si tiene .errors → validación
                    // inline ya marcada; si tiene .message → mostrarlo.
                    if (error?.errors) return;
                    Swal.fire(
                        "Error",
                        error?.message || "Ocurrió un error inesperado.",
                        "error"
                    );
                })
                .finally(() => {
                    hideLoading();
                    loading.value = false;
                });
        };

        return {
            load,
            fieldsJson,
            dataForm,
            closeModal,
            updateThisField,
            clearError,
            onSubmit,
            loading,
            aiDescription,
            aiLoading,
            aiTruncationNotice,
            textoBotonGuardar,
            versionAviso,
            versionError,
            versionVacia,
            generateChangelog,
            candidatos,
            loadingCandidatos,
            marcandoId,
            marcadosCount,
            dependencias,
            dependenciasChecked,
            checkingDeps,
            toggleMarcado,
            verificarDependencias,
        };
    },
};
</script>

<style scoped>
.ck-editor__editable_inline {
    min-height: 150px;
}
</style>
