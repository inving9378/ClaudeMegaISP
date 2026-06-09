<template>
    <div
        class="modal fade"
        id="crearTareaModal"
        ref="modalEl"
        data-bs-backdrop="static"
        data-keyboard="false"
        tabindex="-1"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title mb-0">Crear tarea desde solicitud</h5>
                        <small class="text-muted">
                            Origen: Ticket #{{ ticket.id }} — {{ ticket.topic }}
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        :disabled="saving"
                        @click="close"
                    ></button>
                </div>

                <div class="modal-body">
                    <!-- Banner informativo del ticket -->
                    <div
                        class="alert alert-light border mb-3"
                        style="border-left: 4px solid #185fa5"
                    >
                        <div class="row small">
                            <div class="col-md-6">
                                <strong>Cliente:</strong> {{ ticket.reporter || ('Lead #' + ticket.customer_lead) }}
                            </div>
                            <div class="col-md-3">
                                <strong>Teléfono:</strong> {{ ticket.phone || '—' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Estado:</strong> {{ ticket.estado || '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Título -->
                        <div class="col-12">
                            <label class="form-label">
                                Título de la tarea
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.title }"
                                placeholder="Descripción breve del trabajo a realizar"
                            />
                            <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                        </div>

                        <!-- Proyecto -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Proyecto
                                <span class="text-danger">*</span>
                            </label>
                            <select
                                v-model="form.project_id"
                                class="form-select"
                                :class="{ 'is-invalid': errors.project_id }"
                                :disabled="loadingOptions"
                            >
                                <option value="">
                                    {{ loadingOptions ? 'Cargando...' : 'Seleccionar proyecto...' }}
                                </option>
                                <option
                                    v-for="p in projects"
                                    :key="p.id"
                                    :value="p.id"
                                >{{ p.text }}</option>
                            </select>
                            <div v-if="errors.project_id" class="invalid-feedback">{{ errors.project_id }}</div>
                        </div>

                        <!-- Asignado a -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Asignado a
                                <span class="text-danger">*</span>
                                <small v-if="loadingUsers" class="text-muted ms-1">
                                    <i class="fa fa-spinner fa-spin"></i> filtrando...
                                </small>
                            </label>
                            <select
                                v-model="form.assigned_to"
                                class="form-select"
                                :class="{ 'is-invalid': errors.assigned_to }"
                                :disabled="loadingOptions || loadingUsers"
                            >
                                <option value="">
                                    {{ (loadingOptions || loadingUsers) ? 'Cargando...' : 'Seleccionar técnico...' }}
                                </option>
                                <option
                                    v-for="u in users"
                                    :key="u.id"
                                    :value="u.id"
                                >{{ u.name || u.text }}</option>
                            </select>
                            <small
                                v-if="!loadingUsers && users.length === 0 && form.project_id"
                                class="text-warning"
                            >Sin equipos configurados — asignar manualmente tras guardar.</small>
                            <div v-if="errors.assigned_to" class="invalid-feedback">{{ errors.assigned_to }}</div>
                        </div>

                        <!-- Tipo de tarea -->
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select v-model="form.tipo" class="form-select">
                                <option value="interna">Interna (escritorio)</option>
                                <option value="campo">Campo (visita a sitio)</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-4">
                            <label class="form-label">Estado inicial</label>
                            <select v-model="form.status" class="form-select">
                                <option value="ToDo">Por hacer</option>
                                <option value="InProgress">En curso</option>
                            </select>
                        </div>

                        <!-- Prioridad -->
                        <div class="col-md-4">
                            <label class="form-label">Prioridad</label>
                            <select v-model="form.priority" class="form-select">
                                <option value="Alta">Alta</option>
                                <option value="Media">Media</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>

                        <!-- Fecha programada -->
                        <div class="col-md-6">
                            <label class="form-label">Fecha programada</label>
                            <input
                                v-model="form.start_date"
                                type="date"
                                class="form-control"
                            />
                        </div>

                        <!-- Hora -->
                        <div class="col-md-6">
                            <label class="form-label">Hora</label>
                            <input
                                v-model="form.start_time"
                                type="time"
                                class="form-control"
                            />
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label">Descripción / instrucciones</label>
                            <textarea
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                                placeholder="Detalle del trabajo a realizar..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        :disabled="saving"
                        @click="close"
                    >Cancelar</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canSave || saving"
                        @click="save"
                    >
                        <span v-if="saving">
                            <i class="fa fa-spinner fa-spin me-1"></i> Guardando...
                        </span>
                        <span v-else>
                            <i class="fa fa-check me-1"></i> Crear tarea
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, watch, onMounted, nextTick } from "vue";

// Mapeo ticket.priority (INT) → task.priority (string)
// Tickets: 1=Urgente, 2=Alta, 3=Normal, 4=Baja (ver AssignedTicket.vue)
// Tareas: Alta / Media / Baja
const TICKET_TO_TASK_PRIORITY = { 1: "Alta", 2: "Alta", 3: "Media", 4: "Baja" };

export default {
    name: "CrearTareaModal",
    props: {
        modelValue: { type: Boolean, default: false },
        ticket: { type: Object, default: () => ({}) },
        threads: { type: Array, default: () => [] },
    },
    emits: ["update:modelValue", "created"],
    setup(props, { emit }) {
        const modalEl = ref(null);
        const projects = ref([]);
        const users = ref([]);
        const saving = ref(false);
        const loadingOptions = ref(true);
        const loadingUsers = ref(false);
        const errors = reactive({});

        const form = reactive({
            title: "",
            description: "",
            project_id: "",
            assigned_to: "",
            tipo: "interna",
            status: "ToDo",
            priority: "Media",
            start_date: "",
            start_time: "",
            ticket_id: null,
            client_main_information_id: null,
            template_verification: null,
            estimated_time: null,
        });

        function fillFromTicket() {
            const t = props.ticket;
            form.title = `[Ticket #${t.id}] ${t.topic || ""}`;

            // Descripción: mensaje del primer thread (lo que escribió el cliente),
            // o texto genérico de fallback si el thread está vacío.
            const firstMsg = props.threads?.[0]?.message;
            form.description = firstMsg
                ? `${firstMsg}\n\n— Generada desde Ticket #${t.id}`
                : `Generada desde solicitud de soporte.\n\nReferencia: Ticket #${t.id}`;

            form.ticket_id = t.id ? parseInt(t.id) : null;
            form.client_main_information_id = t.customer_lead || null;

            // Mapeo correcto: tickets.priority INT → tasks.priority string
            // 1=Urgente→Alta, 2=Alta→Alta, 3=Normal→Media, 4=Baja→Baja
            form.priority = TICKET_TO_TASK_PRIORITY[t.priority] || "Media";

            // Heurística tipo: campo si el topic menciona trabajo físico
            const topic = (t.topic || "").toLowerCase();
            form.tipo = /instal|rotur|visita|campo|fibra|cableado/.test(topic)
                ? "campo"
                : "interna";

            // Heredar asignado del ticket si existe
            if (t.assigned_to) {
                form.assigned_to = String(t.assigned_to);
            }

            // Limpiar errores al pre-llenar
            Object.keys(errors).forEach((k) => delete errors[k]);
        }

        async function loadOptions() {
            loadingOptions.value = true;
            try {
                const [pRes, uRes] = await Promise.all([
                    axios.post("/get-options-select", {
                        model: "App\\Models\\Project",
                        id: "id",
                        text: "title",
                    }),
                    axios.post("/get-options-select", {
                        model: "App\\Models\\User",
                        id: "id",
                        text: "name",
                        scope: "notClientRole",
                    }),
                ]);
                projects.value = Object.entries(pRes.data).map(([id, text]) => ({
                    id,
                    text,
                }));
                users.value = Object.entries(uRes.data).map(([id, text]) => ({
                    id,
                    text,
                }));
            } catch (e) {
                console.error("CrearTareaModal: error cargando opciones", e);
            } finally {
                loadingOptions.value = false;
            }
        }

        function close() {
            if (!saving.value) {
                $("#crearTareaModal").modal("hide");
            }
        }

        watch(
            () => props.modelValue,
            async (open) => {
                if (open) {
                    fillFromTicket();
                    await nextTick();
                    $("#crearTareaModal").modal("show");
                } else {
                    $("#crearTareaModal").modal("hide");
                }
            }
        );

        // Filtrar "Asignar a" según los equipos del proyecto elegido.
        // Fallback: si el proyecto no tiene equipos → muestra todos (notClientRole).
        watch(
            () => form.project_id,
            async (newId, oldId) => {
                if (!newId || newId === oldId) return;
                loadingUsers.value = true;
                try {
                    const { data } = await axios.get(
                        `/scheduling/project/${newId}/users`
                    );
                    users.value = data;
                    // Si el asignado actual ya no está en la lista, limpiar
                    if (
                        form.assigned_to &&
                        !data.find((u) => String(u.id) === String(form.assigned_to))
                    ) {
                        form.assigned_to = "";
                    }
                } catch (e) {
                    console.error("CrearTareaModal: error filtrando usuarios", e);
                    // Dejar la lista actual como fallback
                } finally {
                    loadingUsers.value = false;
                }
            }
        );

        onMounted(() => {
            $("#crearTareaModal").on("hidden.bs.modal", () => {
                emit("update:modelValue", false);
            });
            loadOptions();
        });

        const canSave = computed(
            () =>
                form.title.trim().length > 2 &&
                form.project_id !== "" &&
                form.assigned_to !== ""
        );

        async function save() {
            if (!canSave.value || saving.value) return;

            // Limpiar errores
            Object.keys(errors).forEach((k) => delete errors[k]);
            saving.value = true;

            try {
                const payload = {
                    ...form,
                    assigned_to: form.assigned_to ? [form.assigned_to] : [],
                };
                const { data } = await axios.post("/scheduling/task/add", payload);
                $("#crearTareaModal").modal("hide");
                emit("created", data);
            } catch (e) {
                if (e.response?.status === 422) {
                    const errs = e.response.data.errors || {};
                    Object.assign(errors, errs);
                    // Mostrar el primer error como mensaje
                    const first = Object.values(errs)[0];
                    if (first) toastr.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toastr.error(
                        e.response?.data?.message || e.message || "Error al crear la tarea"
                    );
                }
            } finally {
                saving.value = false;
            }
        }

        return {
            modalEl,
            projects,
            users,
            saving,
            loadingOptions,
            loadingUsers,
            errors,
            form,
            canSave,
            save,
            close,
        };
    },
};
</script>
