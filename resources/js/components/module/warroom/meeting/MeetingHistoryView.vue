<template>
    <div class="mhv-wrap">

        <!-- ── Header + botón cerrar ────────────────────────────────────────── -->
        <div class="mhv-header">
            <div class="mhv-header-left">
                <i class="ti ti-archive me-2"></i>
                <span class="mhv-title">Actas guardadas</span>
            </div>
            <button class="mhv-close" @click="$emit('close')"><i class="ti ti-x"></i></button>
        </div>

        <!-- ── Filtros ──────────────────────────────────────────────────────── -->
        <div class="mhv-filters" v-show="!selectedMeeting">
            <input
                v-model="filters.search"
                class="mhv-input"
                placeholder="Buscar por nombre…"
                @input="debouncedLoad"
            />
            <input type="date" v-model="filters.from" class="mhv-input mhv-input-date" @change="loadList" />
            <span class="mhv-filter-sep">—</span>
            <input type="date" v-model="filters.to"   class="mhv-input mhv-input-date" @change="loadList" />
            <select v-model="filters.type" class="mhv-select" @change="loadList">
                <option value="">Todos los tipos</option>
                <option value="ordinaria">Ordinaria</option>
                <option value="acta">Acta</option>
            </select>
        </div>

        <!-- ── Lista de juntas ─────────────────────────────────────────────── -->
        <div v-if="!selectedMeeting" class="mhv-list-wrap">

            <div v-if="loading" class="mhv-loading">
                <div class="mhv-skeleton" v-for="i in 4" :key="i"></div>
            </div>

            <div v-else-if="meetings.length === 0" class="mhv-empty">
                <i class="ti ti-archive-off"></i>
                <span>Sin actas registradas</span>
            </div>

            <div
                v-else
                v-for="m in meetings"
                :key="m.id"
                class="mhv-item"
                @click="openDetail(m.id)"
            >
                <div class="mhv-item-left">
                    <span class="mhv-item-type" :class="`mhv-type-${m.meeting_type}`">
                        {{ m.meeting_type === 'acta' ? 'Acta' : 'Ordinaria' }}
                    </span>
                    <div class="mhv-item-info">
                        <span class="mhv-item-name">{{ m.name }}</span>
                        <span class="mhv-item-meta">
                            {{ formatDate(m.ended_at) }}
                            <template v-if="m.moderator"> · {{ m.moderator.name.split(' ')[0] }}</template>
                            <template v-if="m.duration_actual_minutes"> · {{ m.duration_actual_minutes }}m</template>
                        </span>
                    </div>
                </div>
                <i class="ti ti-chevron-right mhv-item-arrow"></i>
            </div>
        </div>

        <!-- ── Detalle de junta ────────────────────────────────────────────── -->
        <div v-else class="mhv-detail">

            <!-- Back button -->
            <button class="mhv-back" @click="selectedMeeting = null">
                <i class="ti ti-arrow-left me-1"></i>Volver
            </button>

            <div v-if="detailLoading" class="mhv-loading">
                <div class="mhv-skeleton mhv-skeleton-lg" v-for="i in 3" :key="i"></div>
            </div>

            <template v-else-if="detail">

                <!-- ── Resumen ──────────────────────────────────────────────── -->
                <div class="mhv-detail-header">
                    <div class="mhv-detail-title">{{ detail.meeting.name }}</div>
                    <div class="mhv-detail-sub">
                        <span class="mhv-item-type" :class="`mhv-type-${detail.meeting.meeting_type}`">
                            {{ detail.meeting.meeting_type === 'acta' ? 'Acta' : 'Ordinaria' }}
                        </span>
                        <span>{{ formatDate(detail.meeting.ended_at) }}</span>
                        <template v-if="detail.moderator">
                            <span>·</span><span>{{ detail.moderator.name }}</span>
                        </template>
                    </div>
                    <!-- Stats -->
                    <div class="mhv-stats">
                        <div class="mhv-stat">
                            <span class="mhv-stat-val">{{ detail.summary.duration_actual_minutes }}m</span>
                            <span class="mhv-stat-lbl">Duración real</span>
                        </div>
                        <div class="mhv-stat">
                            <span class="mhv-stat-val">{{ detail.summary.sections_completed }}</span>
                            <span class="mhv-stat-lbl">Secciones</span>
                        </div>
                        <div class="mhv-stat">
                            <span class="mhv-stat-val">{{ detail.summary.action_items_count }}</span>
                            <span class="mhv-stat-lbl">Acuerdos</span>
                        </div>
                        <div class="mhv-stat" v-if="detail.summary.attendance_rate != null">
                            <span class="mhv-stat-val">{{ detail.summary.attendance_rate }}%</span>
                            <span class="mhv-stat-lbl">Asistencia</span>
                        </div>
                    </div>
                </div>

                <!-- ── Secciones ───────────────────────────────────────────── -->
                <div class="mhv-section-block" v-if="detail.sections?.length">
                    <div class="mhv-section-title"><i class="ti ti-list-check me-1"></i>Secciones</div>
                    <div class="mhv-sections">
                        <div
                            v-for="sec in detail.sections"
                            :key="sec.id"
                            class="mhv-sec-row"
                            :class="`mhv-sec-${sec.status}`"
                        >
                            <i class="mhv-sec-icon ti"
                               :class="sec.status === 'completed' ? 'ti-check' : sec.status === 'skipped' ? 'ti-minus' : 'ti-circle'"
                            ></i>
                            <span class="mhv-sec-label">{{ sectionLabel(sec.section_key) }}</span>
                            <span class="mhv-sec-time" v-if="sec.time_actual_seconds">
                                {{ Math.round(sec.time_actual_seconds / 60) }}m
                            </span>
                            <span class="mhv-sec-planned">/ {{ Math.round((sec.time_planned_seconds ?? 0) / 60) }}m plan</span>
                        </div>
                    </div>
                </div>

                <!-- ── Q&A / Notas ─────────────────────────────────────────── -->
                <div class="mhv-section-block" v-if="detail.notes?.length">
                    <div class="mhv-section-title"><i class="ti ti-message-question me-1"></i>Preguntas y notas</div>
                    <div class="mhv-notes">
                        <div
                            v-for="note in detail.notes"
                            :key="note.id"
                            class="mhv-note"
                            :class="`mhv-note-${note.kind}`"
                        >
                            <div class="mhv-note-header">
                                <span class="mhv-note-kind">{{ kindLabel(note.kind) }}</span>
                                <span class="mhv-note-section" v-if="note.section_key">{{ sectionLabel(note.section_key) }}</span>
                                <span class="mhv-note-author">{{ note.author?.name ?? '—' }}</span>
                                <span class="mhv-note-time">{{ formatTime(note.created_at) }}</span>
                            </div>
                            <div class="mhv-note-body">{{ note.body }}</div>
                        </div>
                    </div>
                </div>

                <!-- ── Acuerdos ────────────────────────────────────────────── -->
                <div class="mhv-section-block" v-if="detail.action_items?.length">
                    <div class="mhv-section-title"><i class="ti ti-checkbox me-1"></i>Acuerdos y tareas</div>
                    <div class="mhv-actions">
                        <div v-for="item in detail.action_items" :key="item.id" class="mhv-action-row">
                            <span class="mhv-action-dot" :class="`mhv-prio-${item.priority}`"></span>
                            <span class="mhv-action-desc">{{ item.description }}</span>
                            <span class="mhv-action-status" :class="`mhv-status-${item.status}`">{{ item.status }}</span>
                            <span class="mhv-action-assignee" v-if="item.assignee">{{ item.assignee.name.split(' ')[0] }}</span>
                            <span class="mhv-action-deadline" v-if="item.deadline">📅 {{ item.deadline }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Asistentes ──────────────────────────────────────────── -->
                <div class="mhv-section-block" v-if="detail.attendees?.length">
                    <div class="mhv-section-title"><i class="ti ti-users me-1"></i>Asistentes</div>
                    <div class="mhv-attendees">
                        <span
                            v-for="att in detail.attendees"
                            :key="att.user?.id"
                            class="mhv-attendee-chip"
                            :class="{ 'mhv-att-present': att.present, 'mhv-att-absent': !att.present }"
                        >
                            <i class="ti" :class="att.present ? 'ti-circle-check' : 'ti-circle-x'"></i>
                            {{ att.user?.name ?? '—' }}
                        </span>
                    </div>
                </div>

            </template>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';

defineEmits(['close']);

// ── State ──────────────────────────────────────────────────────────────────────
const meetings       = ref([]);
const loading        = ref(false);
const selectedMeeting = ref(null);
const detail         = ref(null);
const detailLoading  = ref(false);

const filters = reactive({ from: '', to: '', type: '', search: '' });

// ── Load list ──────────────────────────────────────────────────────────────────
async function loadList() {
    loading.value = true;
    try {
        const params = {};
        if (filters.from)   params.from = filters.from;
        if (filters.to)     params.to   = filters.to;
        if (filters.type)   params.type = filters.type;
        const { data } = await axios.get('/warroom/api/meetings', { params });
        // Client-side search filter
        const q = filters.search.trim().toLowerCase();
        meetings.value = q
            ? data.filter(m => m.name.toLowerCase().includes(q))
            : data;
    } catch { meetings.value = []; } finally {
        loading.value = false;
    }
}

// Debounce for search input
let searchTimer = null;
function debouncedLoad() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 300);
}

// ── Load detail ────────────────────────────────────────────────────────────────
async function openDetail(id) {
    selectedMeeting.value = id;
    detail.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(`/warroom/api/meetings/${id}/detalle`);
        detail.value = data;
    } catch { detail.value = null; } finally {
        detailLoading.value = false;
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const SECTION_LABELS = {
    resumen:     'Resumen ejecutivo',
    finanzas:    'Finanzas',
    operaciones: 'Operaciones',
    ventas:      'Ventas y Embajadores',
    red:         'Red e Infraestructura',
    marketing:   'Marketing',
    talento:     'Talento',
};
function sectionLabel(key) { return SECTION_LABELS[key] ?? key; }

function kindLabel(kind) {
    return { nota: 'Nota', pregunta: 'Pregunta', respuesta: 'Respuesta' }[kind] ?? kind;
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

// Load on mount
loadList();
</script>

<style scoped>
.mhv-wrap {
    background: #0c0c1e;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 400px;
    max-height: 80vh;
    width: 560px;
    max-width: 95vw;
    overflow: hidden;
    color: #e8e8f0;
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.mhv-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}
.mhv-header-left { display: flex; align-items: center; }
.mhv-title { font-size: 14px; font-weight: 600; color: #e8e8f0; }
.mhv-close {
    background: none; border: none; color: #6b6b85;
    cursor: pointer; font-size: 16px; padding: 2px;
    transition: color 0.15s;
}
.mhv-close:hover { color: #e8e8f0; }

/* ── Filtros ─────────────────────────────────────────────────────────────── */
.mhv-filters {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
    flex-shrink: 0;
}
.mhv-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 11px;
    padding: 4px 8px;
    outline: none;
    flex: 1;
    min-width: 90px;
}
.mhv-input-date { flex: 0 0 110px; }
.mhv-input:focus { border-color: #534AB7; }
.mhv-select {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 11px;
    padding: 4px 8px;
    flex: 0 0 130px;
    outline: none;
}
.mhv-filter-sep { color: #4a4a6a; font-size: 11px; }

/* ── Lista ───────────────────────────────────────────────────────────────── */
.mhv-list-wrap { flex: 1; overflow-y: auto; padding: 6px 8px; }

.mhv-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.1s;
    gap: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.mhv-item:hover { background: rgba(255,255,255,0.05); }
.mhv-item-left { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }

.mhv-item-type {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 4px;
    padding: 2px 6px;
    flex-shrink: 0;
    font-weight: 600;
}
.mhv-type-ordinaria { background: rgba(83,74,183,0.25); color: #a89ff5; }
.mhv-type-acta      { background: rgba(29,158,117,0.25); color: #5ddbb3; }

.mhv-item-info { display: flex; flex-direction: column; min-width: 0; }
.mhv-item-name { font-size: 12px; color: #e8e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mhv-item-meta { font-size: 10px; color: #6b6b85; margin-top: 2px; }
.mhv-item-arrow { color: #4a4a6a; font-size: 12px; flex-shrink: 0; }

/* Loading / empty */
.mhv-loading { display: flex; flex-direction: column; gap: 8px; padding: 10px; }
.mhv-skeleton {
    height: 44px;
    background: rgba(255,255,255,0.05);
    border-radius: 6px;
    animation: mhv-pulse 1.2s ease-in-out infinite;
}
.mhv-skeleton-lg { height: 80px; }
@keyframes mhv-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.45; } }

.mhv-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 40px;
    color: #4a4a6a;
    font-size: 12px;
}
.mhv-empty i { font-size: 28px; }

/* ── Detalle ─────────────────────────────────────────────────────────────── */
.mhv-detail { flex: 1; overflow-y: auto; padding: 10px 14px; }

.mhv-back {
    background: none;
    border: none;
    color: #534AB7;
    font-size: 12px;
    cursor: pointer;
    padding: 0;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
}
.mhv-back:hover { color: #a89ff5; }

.mhv-detail-header {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 12px;
}
.mhv-detail-title { font-size: 15px; font-weight: 600; color: #e8e8f0; margin-bottom: 6px; }
.mhv-detail-sub {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #9896b8;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.mhv-stats { display: flex; gap: 16px; flex-wrap: wrap; }
.mhv-stat {
    display: flex; flex-direction: column; align-items: center;
    background: rgba(255,255,255,0.04);
    border-radius: 6px;
    padding: 6px 12px;
    min-width: 60px;
}
.mhv-stat-val { font-size: 18px; font-weight: 600; color: #fff; line-height: 1; }
.mhv-stat-lbl { font-size: 9px; color: #6b6b85; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 3px; }

/* Bloque de sección */
.mhv-section-block {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 7px;
    padding: 10px 12px;
    margin-bottom: 10px;
}
.mhv-section-title {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b6b85;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}

/* Secciones */
.mhv-sections { display: flex; flex-direction: column; gap: 4px; }
.mhv-sec-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; color: #9896b8; padding: 2px 0;
}
.mhv-sec-completed .mhv-sec-icon { color: #1D9E75; }
.mhv-sec-skipped { color: #4a4a6a; text-decoration: line-through; }
.mhv-sec-icon { font-size: 11px; width: 14px; text-align: center; }
.mhv-sec-label { flex: 1; }
.mhv-sec-time { font-weight: 600; color: #e8e8f0; }
.mhv-sec-planned { font-size: 10px; color: #4a4a6a; }

/* Notas */
.mhv-notes { display: flex; flex-direction: column; gap: 6px; }
.mhv-note {
    background: rgba(255,255,255,0.03);
    border-left: 2px solid transparent;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 11px;
}
.mhv-note-pregunta  { border-left-color: #534AB7; }
.mhv-note-respuesta { border-left-color: #1D9E75; }
.mhv-note-nota      { border-left-color: #BA7517; }
.mhv-note-header {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 3px; flex-wrap: wrap;
}
.mhv-note-kind    { font-size: 9px; text-transform: uppercase; color: #6b6b85; }
.mhv-note-section { font-size: 9px; color: #534AB7; background: rgba(83,74,183,0.1); border-radius: 3px; padding: 1px 5px; }
.mhv-note-author  { font-size: 9px; color: #9896b8; }
.mhv-note-time    { font-size: 9px; color: #4a4a6a; margin-left: auto; }
.mhv-note-body    { color: #c0c0e0; line-height: 1.35; word-break: break-word; }

/* Acuerdos */
.mhv-actions { display: flex; flex-direction: column; gap: 4px; }
.mhv-action-row {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 11px; color: #9896b8; padding: 3px 0;
}
.mhv-action-dot {
    width: 6px; height: 6px; border-radius: 50%; margin-top: 4px; flex-shrink: 0;
}
.mhv-prio-high   { background: #e05252; }
.mhv-prio-critico { background: #e05252; }
.mhv-prio-medium { background: #f5c47a; }
.mhv-prio-alto   { background: #f5c47a; }
.mhv-prio-low    { background: #5ddbb3; }
.mhv-action-desc { flex: 1; line-height: 1.35; color: #c0c0e0; }
.mhv-action-status {
    font-size: 9px; text-transform: uppercase; border-radius: 3px; padding: 1px 5px; flex-shrink: 0;
}
.mhv-status-pending  { background: rgba(255,255,255,0.06); color: #6b6b85; }
.mhv-status-done     { background: rgba(29,158,117,0.15); color: #5ddbb3; }
.mhv-status-in_progress { background: rgba(83,74,183,0.15); color: #a89ff5; }
.mhv-action-assignee {
    font-size: 9px; color: #534AB7; background: rgba(83,74,183,0.15);
    border-radius: 3px; padding: 1px 5px; flex-shrink: 0;
}
.mhv-action-deadline { font-size: 9px; color: #6b6b85; }

/* Asistentes */
.mhv-attendees { display: flex; flex-wrap: wrap; gap: 5px; }
.mhv-attendee-chip {
    display: flex; align-items: center; gap: 4px;
    font-size: 10px; border-radius: 12px; padding: 3px 8px;
}
.mhv-att-present { background: rgba(29,158,117,0.1); color: #5ddbb3; }
.mhv-att-absent  { background: rgba(224,82,82,0.1);  color: #e05252; }
</style>
