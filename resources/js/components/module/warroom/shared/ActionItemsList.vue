<template>
    <div class="wr-action-items">
        <div class="wr-section-title d-flex justify-content-between align-items-center">
            <span><i class="ti ti-list-check me-1"></i> Planes de ataque</span>
            <button
                v-if="editable"
                class="wr-btn-add"
                @click="showForm = !showForm"
                title="Nueva tarea"
            >
                <i class="ti ti-plus"></i>
            </button>
        </div>

        <!-- Quick create form -->
        <div v-if="showForm && editable" class="wr-action-form">
            <textarea
                v-model="newItem.description"
                class="wr-action-textarea"
                placeholder="Descripción de la tarea..."
                rows="2"
                @keydown.ctrl.enter="submit"
            ></textarea>
            <div class="wr-action-form-row">
                <select v-model="newItem.priority" class="wr-action-select">
                    <option value="critico">🔴 Crítico</option>
                    <option value="alto">🟠 Alto</option>
                    <option value="medio">🟡 Medio</option>
                    <option value="oportunidad">🟢 Oportunidad</option>
                    <option value="estrategico">🔵 Estratégico</option>
                </select>
                <input
                    v-model="newItem.deadline"
                    type="date"
                    class="wr-action-date"
                    :min="today"
                />
                <button class="wr-btn-submit" @click="submit" :disabled="!newItem.description.trim() || saving">
                    <i class="ti ti-check"></i>
                </button>
                <button class="wr-btn-cancel" @click="showForm = false">
                    <i class="ti ti-x"></i>
                </button>
            </div>
        </div>

        <!-- Skeleton -->
        <template v-if="loading">
            <div v-for="i in 3" :key="i" class="wr-action-item-skeleton">
                <q-skeleton type="text" width="80%" />
                <q-skeleton type="text" width="40%" class="mt-1" />
            </div>
        </template>

        <!-- Empty state -->
        <div v-else-if="items.length === 0" class="wr-action-empty">
            Sin tareas registradas para esta sección.
        </div>

        <!-- Items -->
        <ul v-else class="wr-action-list">
            <li
                v-for="item in items"
                :key="item.id"
                class="wr-action-item"
                :class="`priority-${item.priority}`"
            >
                <span class="wr-action-dot" :class="`dot-${item.priority}`"></span>
                <div class="wr-action-body">
                    <div class="wr-action-desc">{{ item.description }}</div>
                    <div class="wr-action-meta">
                        <span v-if="item.assignee?.name" class="wr-action-assignee">
                            <i class="ti ti-user"></i> {{ item.assignee.name }}
                        </span>
                        <span v-if="item.deadline" class="wr-action-deadline">
                            <i class="ti ti-calendar"></i> {{ formatDeadline(item.deadline) }}
                        </span>
                        <span class="wr-action-priority-badge" :class="`pbadge-${item.priority}`">
                            {{ priorityLabel(item.priority) }}
                        </span>
                    </div>
                </div>
                <span class="wr-action-status" :class="`status-${item.status}`">
                    {{ statusLabel(item.status) }}
                </span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    items:      { type: Array, default: () => [] },
    loading:    { type: Boolean, default: false },
    editable:   { type: Boolean, default: false },
    meetingId:  { type: Number, default: null },
    sectionKey: { type: String, default: 'resumen' },
});

const emit = defineEmits(['item-created']);

const showForm = ref(false);
const saving   = ref(false);
const newItem  = ref({ description: '', priority: 'medio', deadline: '' });

const today = computed(() => new Date().toISOString().split('T')[0]);

async function submit() {
    if (!newItem.value.description.trim() || !props.meetingId) return;
    saving.value = true;
    try {
        const { data } = await axios.post('/warroom/api/action-items', {
            meeting_id:   props.meetingId,
            section_key:  props.sectionKey,
            description:  newItem.value.description.trim(),
            priority:     newItem.value.priority,
            deadline:     newItem.value.deadline || null,
        });
        emit('item-created', data);
        newItem.value = { description: '', priority: 'medio', deadline: '' };
        showForm.value = false;
    } catch (e) {
        console.error('[ActionItems] Error creando tarea:', e);
    } finally {
        saving.value = false;
    }
}

function priorityLabel(p) {
    return { critico: 'Crítico', alto: 'Alto', medio: 'Medio', oportunidad: 'Oportunidad', estrategico: 'Estratégico' }[p] ?? p;
}

function statusLabel(s) {
    return { pending: 'Pendiente', in_progress: 'En curso', completed: 'Listo', cancelled: 'Cancelado' }[s] ?? s;
}

function formatDeadline(d) {
    if (!d) return '';
    const date = new Date(d + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}
</script>
