<template>
    <q-dialog v-model="open" persistent>
        <q-card class="wr-summary-card">

            <q-card-section class="wr-summary-header">
                <div class="wr-summary-title">
                    <i class="ti ti-flag-check me-2"></i>
                    Junta finalizada
                </div>
                <div class="wr-summary-duration">
                    {{ meeting.name }}
                    <span class="wr-summary-dur-detail">
                        {{ summary.duration_actual_minutes }} min
                        <span :class="summary.duration_delta_pct > 10 ? 'wr-dur-over' : 'wr-dur-ok'">
                            ({{ summary.duration_delta_pct > 0 ? '+' : '' }}{{ summary.duration_delta_pct }}% vs planeado)
                        </span>
                    </span>
                </div>
            </q-card-section>

            <q-card-section class="wr-summary-body">

                <!-- KPIs de la junta -->
                <div class="wr-summary-kpis">
                    <div class="wr-summary-kpi">
                        <div class="wr-summary-kpi-value">{{ summary.action_items_count }}</div>
                        <div class="wr-summary-kpi-label">Tareas creadas</div>
                    </div>
                    <div class="wr-summary-kpi">
                        <div class="wr-summary-kpi-value">{{ summary.sections_completed }}</div>
                        <div class="wr-summary-kpi-label">Secciones completadas</div>
                    </div>
                    <div class="wr-summary-kpi">
                        <div class="wr-summary-kpi-value">{{ summary.attendance_rate }}%</div>
                        <div class="wr-summary-kpi-label">Asistencia</div>
                    </div>
                    <div class="wr-summary-kpi" v-if="summary.sections_skipped > 0">
                        <div class="wr-summary-kpi-value wr-dur-over">{{ summary.sections_skipped }}</div>
                        <div class="wr-summary-kpi-label">Secciones omitidas</div>
                    </div>
                </div>

                <!-- Tabla de secciones -->
                <div class="wr-summary-block mt-3">
                    <div class="wr-setup-section-title">
                        <i class="ti ti-list me-1"></i>Secciones
                    </div>
                    <table class="wr-table">
                        <thead>
                            <tr>
                                <th>Sección</th>
                                <th>Planeado</th>
                                <th>Real</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sec in orderedSections" :key="sec.id">
                                <td>{{ SECTION_LABELS[sec.section_key] ?? sec.section_key }}</td>
                                <td>{{ Math.floor(sec.time_planned_seconds / 60) }}m</td>
                                <td v-if="sec.status === 'completed'">{{ Math.floor(sec.time_actual_seconds / 60) }}m {{ sec.time_actual_seconds % 60 }}s</td>
                                <td v-else class="text-muted">—</td>
                                <td v-if="sec.status === 'completed'">
                                    <span :class="sec.time_actual_seconds > sec.time_planned_seconds ? 'wr-dur-over' : 'wr-dur-ok'">
                                        {{ Math.round(sec.time_actual_seconds / Math.max(sec.time_planned_seconds,1) * 100) }}%
                                    </span>
                                </td>
                                <td v-else class="text-muted">{{ sec.status === 'skipped' ? 'Omitida' : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tareas por prioridad -->
                <div class="wr-summary-block mt-3" v-if="summary.action_items_count > 0">
                    <div class="wr-setup-section-title">
                        <i class="ti ti-checkbox me-1"></i>Distribución de tareas
                    </div>
                    <div class="wr-summary-priorities">
                        <div v-for="(count, prio) in summary.action_items_by_priority" :key="prio"
                            class="wr-summary-prio-chip" :class="`pbadge-${prio}`">
                            {{ prio }}: <strong>{{ count }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Listado de tareas agrupadas por asignado -->
                <div class="wr-summary-block mt-3" v-if="meeting.action_items?.length">
                    <div class="wr-setup-section-title">
                        <i class="ti ti-users me-1"></i>Tareas por asignado
                    </div>
                    <div v-for="(tasks, userName) in tasksByUser" :key="userName" class="wr-summary-user-group">
                        <div class="wr-summary-user-header">{{ userName }}</div>
                        <div v-for="t in tasks" :key="t.id" class="wr-summary-task-row">
                            <span class="wr-action-dot" :class="`dot-${t.priority}`"></span>
                            <span class="wr-summary-task-desc">{{ t.description }}</span>
                            <span v-if="t.deadline" class="wr-summary-task-dl">{{ t.deadline }}</span>
                        </div>
                    </div>
                </div>

            </q-card-section>

            <q-card-actions class="wr-setup-footer">
                <q-btn flat label="Cerrar" @click="$emit('close')" class="wr-setup-btn-cancel" />
            </q-card-actions>

        </q-card>
    </q-dialog>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    data:       { type: Object,  required: true },
});

const emit = defineEmits(['update:modelValue', 'close']);

const open = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
});

const SECTION_LABELS = {
    resumen:     'Resumen ejecutivo',
    finanzas:    'Finanzas',
    operaciones: 'Operaciones',
    ventas:      'Ventas',
    red:         'Red',
    marketing:   'Marketing',
};

const meeting = computed(() => props.data.meeting ?? {});
const summary = computed(() => props.data.summary ?? {});

const orderedSections = computed(() =>
    [...(meeting.value.sections ?? [])].sort((a, b) => a.order_position - b.order_position)
);

const tasksByUser = computed(() => {
    const items = meeting.value.action_items ?? [];
    const groups = {};
    items.forEach(t => {
        const name = t.assignee?.name ?? 'Sin asignar';
        if (!groups[name]) groups[name] = [];
        groups[name].push(t);
    });
    return groups;
});
</script>

<style>
.wr-summary-card {
    background: #0f0f1a;
    color: #e8e8f0;
    border: 1px solid rgba(255,255,255,0.08);
    width: 640px;
    max-width: 96vw;
    max-height: 90vh;
    border-radius: 10px;
}

.wr-summary-header {
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 14px 20px;
}

.wr-summary-title {
    font-size: 16px;
    font-weight: 600;
    color: #1D9E75;
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.wr-summary-duration { font-size: 13px; color: #9999b0; }
.wr-summary-dur-detail { margin-left: 8px; }
.wr-dur-over { color: #e05252; }
.wr-dur-ok   { color: #1D9E75; }

.wr-summary-body {
    padding: 14px 20px;
    overflow-y: auto;
    max-height: calc(90vh - 160px);
}

.wr-summary-kpis {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.wr-summary-kpi {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 10px 16px;
    text-align: center;
    min-width: 80px;
}

.wr-summary-kpi-value { font-size: 26px; font-weight: 600; color: #fff; }
.wr-summary-kpi-label { font-size: 10px; color: #9999b0; text-transform: uppercase; letter-spacing: 0.5px; }

.wr-summary-block { }

.wr-summary-priorities {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.wr-summary-prio-chip {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 10px;
    border: 1px solid currentColor;
}

.wr-summary-user-group { margin-bottom: 10px; }
.wr-summary-user-header {
    font-size: 11px;
    font-weight: 600;
    color: #9999b0;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}

.wr-summary-task-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
}

.wr-summary-task-desc { flex: 1; color: #e8e8f0; }
.wr-summary-task-dl   { color: #6b6b85; font-size: 11px; flex-shrink: 0; }
.text-muted { color: #6b6b85; }
</style>
