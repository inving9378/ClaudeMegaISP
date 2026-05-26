<template>
    <div class="megafamilia-tareas mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-tasks me-2 text-primary"></i>
                Tareas MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button id="btn-nueva-tarea" class="btn btn-sm btn-primary" @click="openCreate">
                    <i class="fa fa-plus me-1"></i> Nueva Tarea
                </button>
            </div>
        </div>

        <!-- Filtro perfil -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Filtrar por perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm" @change="fetch">
                            <option value="">Todos los perfiles</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanban 4 columnas -->
        <div v-if="loading && !hasAny" class="text-center py-5 text-muted">Cargando…</div>
        <div v-else class="kanban mf-kanban">
            <div v-for="col in columns" :key="col.key" class="kanban-col" :class="`kanban-col-${col.key}`">
                <header class="kanban-header">
                    <span class="kanban-title">
                        <i :class="['fa', col.icon, 'me-1']"></i>
                        {{ col.label }}
                    </span>
                    <span class="badge bg-light text-dark">{{ (tasksBy[col.key] || []).length }}</span>
                </header>
                <div class="kanban-body">
                    <div v-if="!tasksBy[col.key]?.length" class="text-muted small text-center py-3">
                        Sin tareas
                    </div>
                    <div
                        v-for="t in (tasksBy[col.key] || [])"
                        :key="t.id"
                        class="task-card"
                        :class="priorityClass(t.priority)"
                        @click="openDetail(t)">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong class="task-title">{{ t.title }}</strong>
                            <span v-if="t.priority" class="badge ms-1" :class="priorityBadge(t.priority)">
                                {{ t.priority }}
                            </span>
                        </div>
                        <p v-if="t.description" class="task-desc small text-muted mb-1">{{ t.description }}</p>
                        <div class="task-meta">
                            <span class="badge bg-info-subtle text-info">
                                <i class="fa fa-child me-1"></i>{{ t.profile?.name || '—' }}
                            </span>
                            <span class="badge bg-light text-dark mf-tarea-recompensa">{{ rewardLabel(t) }}</span>
                            <span v-if="t.due_date" class="text-muted small">
                                <i class="fa fa-calendar me-1"></i>{{ formatDate(t.due_date) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Modal crear -->
        <div v-if="form.open" class="mf-modal-overlay" @click.self="form.open = false">
            <div class="mf-modal-panel" style="max-width:600px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Nueva tarea</h5>
                    <button class="btn-close" @click="form.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Perfil <span class="text-danger">*</span></label>
                            <select v-model.number="form.profile_id" class="form-select">
                                <option :value="null">Selecciona…</option>
                                <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select v-model="form.priority" class="form-select">
                                <option value="baja">Baja</option>
                                <option value="media">Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input v-model="form.title" type="text" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea v-model="form.description" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha límite</label>
                            <input v-model="form.due_date" type="date" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo recompensa <span class="text-danger">*</span></label>
                            <select v-model="form.reward_type" class="form-select">
                                <option value="time_extra">Tiempo extra</option>
                                <option value="app_unlock">Desbloqueo de app</option>
                                <option value="points">Puntos</option>
                                <option value="badge">Medalla</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor recompensa</label>
                            <input v-model.number="form.reward_value" type="number" min="0" class="form-control"
                                   :placeholder="rewardValuePlaceholder" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Detalle (nombre app/medalla)</label>
                            <input v-model="form.reward_detail" type="text" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="form.open = false">Cancelar</button>
                    <button class="btn btn-primary" :disabled="!canCreate || acting" @click="createTask">
                        <i class="fa fa-save me-1"></i> Crear tarea
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal detalle -->
        <div v-if="detail.open" class="mf-modal-overlay" @click.self="detail.open = false">
            <div class="mf-modal-panel" style="max-width:580px;">
                <div class="mf-modal-header">
                    <div>
                        <h5 class="m-0">{{ detail.task?.title }}</h5>
                        <small class="text-muted">{{ detail.task?.profile?.name }}</small>
                    </div>
                    <button class="btn-close" @click="detail.open = false"></button>
                </div>
                <div v-if="detail.task" class="mf-modal-body">
                    <p class="small">{{ detail.task.description || 'Sin descripción.' }}</p>

                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8"><span class="badge" :class="statusBadge(detail.task.status)">{{ statusLabel(detail.task.status) }}</span></dd>

                        <dt class="col-sm-4">Recompensa</dt>
                        <dd class="col-sm-8">{{ rewardLabel(detail.task) }}</dd>

                        <dt class="col-sm-4">Prioridad</dt>
                        <dd class="col-sm-8"><span class="badge" :class="priorityBadge(detail.task.priority)">{{ detail.task.priority || 'media' }}</span></dd>

                        <dt class="col-sm-4">Fecha límite</dt>
                        <dd class="col-sm-8">{{ formatDate(detail.task.due_date) || '—' }}</dd>

                        <dt v-if="detail.task.completed_at" class="col-sm-4">Completada</dt>
                        <dd v-if="detail.task.completed_at" class="col-sm-8">{{ formatDate(detail.task.completed_at) }}</dd>

                        <dt v-if="detail.task.approved_at" class="col-sm-4">Aprobada</dt>
                        <dd v-if="detail.task.approved_at" class="col-sm-8">{{ formatDate(detail.task.approved_at) }}</dd>

                        <dt v-if="detail.task.notes" class="col-sm-4">Notas</dt>
                        <dd v-if="detail.task.notes" class="col-sm-8">{{ detail.task.notes }}</dd>
                    </dl>

                    <div v-if="detail.task.photo_proof" class="mb-2">
                        <strong class="small">Comprobante:</strong>
                        <img :src="detail.task.photo_proof" alt="proof" class="proof-img mt-1" />
                    </div>
                </div>
                <footer v-if="detail.task" class="mf-modal-footer">
                    <div class="d-flex flex-wrap gap-2 justify-content-end w-100">
                        <button
                            v-if="detail.task.status === 'pending'"
                            class="btn btn-sm btn-outline-danger"
                            :disabled="acting"
                            @click="destroyTask">
                            <i class="fa fa-trash me-1"></i> Eliminar
                        </button>
                        <button
                            v-if="detail.task.status === 'completed'"
                            class="btn btn-sm btn-danger"
                            :disabled="acting"
                            @click="rejectTask">
                            <i class="fa fa-times me-1"></i> Rechazar
                        </button>
                        <button
                            v-if="detail.task.status === 'completed'"
                            class="btn btn-sm btn-success"
                            :disabled="acting"
                            @click="approveTask">
                            <i class="fa fa-check me-1"></i> Aprobar y dar recompensa
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const COLUMNS = [
    { key: 'pending',   label: 'Pendientes',   icon: 'fa-hourglass-half' },
    { key: 'completed', label: 'Por aprobar',  icon: 'fa-clipboard-check' },
    { key: 'approved',  label: 'Aprobadas',    icon: 'fa-check-circle' },
    { key: 'rejected',  label: 'Rechazadas',   icon: 'fa-times-circle' },
];

const REWARD_LABELS = {
    time_extra: 'Tiempo extra',
    app_unlock: 'Desbloqueo app',
    points: 'Puntos',
    badge: 'Medalla',
};

const STATUS_LABEL = { pending: 'Pendiente', completed: 'Por aprobar', approved: 'Aprobada', rejected: 'Rechazada' };
const STATUS_BADGE = {
    pending: 'bg-secondary', completed: 'bg-info text-dark',
    approved: 'bg-success', rejected: 'bg-danger',
};
const PRIORITY_BADGE = {
    baja:  'bg-light text-dark',
    media: 'bg-info-subtle text-info',
    alta:  'bg-danger-subtle text-danger',
};

function blankForm() {
    return {
        open: false, profile_id: null, title: '', description: '',
        due_date: '', priority: 'media',
        reward_type: 'points', reward_value: 10, reward_detail: '',
    };
}

export default {
    name: 'MegaFamiliaTareas',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            acting: false,
            tasksBy: {},
            profiles: [],
            filters: { profile_id: '' },
            form: blankForm(),
            detail: { open: false, task: null },
            columns: COLUMNS,
        };
    },
    computed: {
        canCreate() {
            return !!(this.form.profile_id && this.form.title && this.form.reward_type);
        },
        hasAny() {
            return Object.values(this.tasksBy).some(arr => (arr || []).length > 0);
        },
        rewardValuePlaceholder() {
            return ({
                time_extra: 'minutos',
                app_unlock: '0 (usar Detalle para nombre)',
                points: 'cantidad',
                badge: '0',
            })[this.form.reward_type] || '';
        },
    },
    mounted() {
        this.fetch();
        if (shouldShowTour('tareas')) setTimeout(() => startTour('tareas'), 1200);
    },
    methods: {
        launchTour() { startTour('tareas'); },
        async fetch() {
            this.loading = true;
            try {
                const params = {};
                if (this.filters.profile_id) params.profile_id = this.filters.profile_id;
                const { data } = await axios.get(`${this.baseUrl}/tareas/data`, { params });
                this.tasksBy = data.by_status || {};
                this.profiles = data.profiles || [];
            } catch (e) {
                console.error('[MF/Tareas]', e);
            } finally {
                this.loading = false;
            }
        },
        openCreate() { this.form = { ...blankForm(), open: true }; },
        openDetail(task) { this.detail = { open: true, task: { ...task } }; },
        async createTask() {
            this.acting = true;
            try {
                const payload = {
                    profile_id: this.form.profile_id,
                    title: this.form.title,
                    description: this.form.description,
                    due_date: this.form.due_date || null,
                    priority: this.form.priority,
                    reward_type: this.form.reward_type,
                    reward_value: this.form.reward_value || 0,
                    reward_detail: this.form.reward_detail,
                };
                await axios.post(`${this.baseUrl}/tareas`, payload, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.form.open = false;
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async approveTask() {
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/tareas/${this.detail.task.id}/approve`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.detail.open = false;
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async rejectTask() {
            const reason = prompt('Motivo del rechazo (opcional):') || '';
            this.acting = true;
            try {
                await axios.post(
                    `${this.baseUrl}/tareas/${this.detail.task.id}/reject`,
                    { reason },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                this.detail.open = false;
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async destroyTask() {
            if (!confirm('¿Eliminar esta tarea pendiente?')) return;
            this.acting = true;
            try {
                await axios.delete(`${this.baseUrl}/tareas/${this.detail.task.id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.detail.open = false;
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        rewardLabel(t) {
            const base = REWARD_LABELS[t.reward_type] || t.reward_type;
            if (t.reward_type === 'time_extra') return `${base}: ${t.reward_value} min`;
            if (t.reward_type === 'points')     return `${base}: ${t.reward_value}`;
            if (t.reward_type === 'app_unlock') return `${base}: ${t.reward_detail || 'app'}`;
            return base;
        },
        statusLabel(s) { return STATUS_LABEL[s] || s; },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        priorityBadge(p) { return PRIORITY_BADGE[p] || 'bg-light text-dark'; },
        priorityClass(p) { return ({ alta: 'task-prio-high', media: '', baja: 'task-prio-low' })[p] || ''; },
        formatDate(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>

<style scoped>
.kanban { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
@media (max-width: 991.98px) {
    .kanban { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575.98px) {
    .kanban { grid-template-columns: 1fr; }
}

.kanban-col { background: #f8f9fa; border-radius: 8px; padding: 0.5rem; min-height: 200px; }
.kanban-col-pending { border-top: 3px solid #6c757d; }
.kanban-col-completed { border-top: 3px solid #0dcaf0; }
.kanban-col-approved { border-top: 3px solid #198754; }
.kanban-col-rejected { border-top: 3px solid #dc3545; }

.kanban-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.25rem 0.5rem 0.5rem; border-bottom: 1px solid #dee2e6;
    margin-bottom: 0.5rem;
}
.kanban-title { font-weight: 600; font-size: 14px; color: #495057; }

.kanban-body { max-height: 65vh; overflow-y: auto; padding-right: 4px; }

.task-card {
    background: #fff; border-radius: 6px; padding: 0.6rem 0.7rem;
    margin-bottom: 0.5rem; cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-left: 3px solid #adb5bd;
    transition: transform 0.1s;
}
.task-card:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,0.1); }
.task-card.task-prio-high { border-left-color: #dc3545; }
.task-card.task-prio-low { border-left-color: #adb5bd; }

.task-title { font-size: 14px; }
.task-desc { font-size: 12px; }
.task-meta { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; font-size: 11px; }

.proof-img { max-width: 100%; max-height: 240px; border-radius: 6px; border: 1px solid #e9ecef; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; flex: 1; }
.mf-modal-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
