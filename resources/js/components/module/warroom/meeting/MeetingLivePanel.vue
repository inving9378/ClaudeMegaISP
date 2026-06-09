<template>
    <div class="wlp-panel">

        <!-- ══ ESTADO IDLE: sin junta activa ══════════════════════════════════ -->
        <template v-if="!meeting">
            <div class="wlp-header">
                <div class="wlp-header-left">
                    <span class="wlp-type-badge wlp-type-ordinaria">War Room</span>
                    <span class="wlp-meeting-name">Sin junta activa</span>
                </div>
            </div>

            <div class="wlp-clock-section">
                <div class="wlp-clock wlp-clock-idle">{{ idleClock }}</div>
                <div class="wlp-clock-label">Hora actual</div>
            </div>

            <div class="wlp-block">
                <div class="wlp-block-title"><i class="ti ti-list-check me-1"></i>Agenda disponible</div>
                <div class="wlp-agenda">
                    <div v-for="sec in IDLE_SECTIONS" :key="sec.key" class="wlp-agenda-item wlp-agenda-idle">
                        <span class="wlp-agenda-icon"><i class="ti ti-circle"></i></span>
                        <span class="wlp-agenda-label">{{ sec.label }}</span>
                        <span class="wlp-agenda-mins">{{ sec.mins }}m</span>
                    </div>
                </div>
            </div>

            <div class="wlp-block wlp-block-grow wlp-idle-cta">
                <p class="wlp-idle-hint">Inicia una junta para controlar tiempos, tomar notas y registrar acuerdos.</p>
                <button class="wlp-btn-start-meeting" @click="$emit('start-meeting')">
                    <i class="ti ti-broadcast me-1"></i>
                    Iniciar junta
                </button>
            </div>
        </template>

        <!-- ══ ESTADO ACTIVO: junta en curso ═══════════════════════════════════ -->
        <template v-else>

            <!-- ── Header ──────────────────────────────────────────────────── -->
            <div class="wlp-header">
                <div class="wlp-header-left">
                    <span class="wlp-type-badge" :class="`wlp-type-${meeting?.meeting_type ?? 'ordinaria'}`">
                        {{ meeting?.meeting_type === 'acta' ? 'Acta' : 'Ordinaria' }}
                    </span>
                    <span class="wlp-meeting-name">{{ meeting?.name ?? 'Junta en curso' }}</span>
                </div>
            </div>

            <!-- ── Reloj por tema ──────────────────────────────────────────── -->
            <div class="wlp-clock-section">
                <div
                    class="wlp-clock"
                    :class="{
                        'wlp-clock-warning': sectionSecondsLeft <= 20 && sectionSecondsLeft > 0,
                        'wlp-clock-over':    sectionSecondsLeft <= 0,
                    }"
                >
                    <template v-if="sectionSecondsLeft > 0">
                        {{ formatClock(sectionSecondsLeft) }}
                    </template>
                    <template v-else>
                        +{{ formatClock(-sectionSecondsLeft) }}
                    </template>
                </div>
                <div class="wlp-clock-label">
                    <template v-if="sectionSecondsLeft > 0">Tiempo restante</template>
                    <template v-else><span class="wlp-overtime-label">Sobretiempo</span></template>
                </div>
                <div class="wlp-clock-section-name" v-if="currentSection">
                    {{ sectionLabel(currentSection.section_key) }}
                </div>
                <!-- Progress bar -->
                <div class="wlp-progress-bar">
                    <div
                        class="wlp-progress-fill"
                        :class="{ 'wlp-progress-over': sectionSecondsLeft <= 0 }"
                        :style="{ width: Math.min(100, sectionProgress) + '%' }"
                    ></div>
                </div>
            </div>

            <!-- ── Puntos a tratar ─────────────────────────────────────────── -->
            <div class="wlp-block">
                <div class="wlp-block-title"><i class="ti ti-list-check me-1"></i>Puntos a tratar</div>
                <div class="wlp-agenda">
                    <div
                        v-for="sec in meeting?.sections ?? []"
                        :key="sec.id"
                        class="wlp-agenda-item"
                        :class="{
                            'wlp-agenda-current':   sec.section_key === meeting.current_section_key,
                            'wlp-agenda-done':      sec.status === 'completed',
                            'wlp-agenda-skipped':   sec.status === 'skipped',
                        }"
                    >
                        <span class="wlp-agenda-icon">
                            <i v-if="sec.status === 'completed'" class="ti ti-check"></i>
                            <i v-else-if="sec.status === 'skipped'" class="ti ti-minus"></i>
                            <i v-else-if="sec.section_key === meeting.current_section_key" class="ti ti-player-play-filled"></i>
                            <i v-else class="ti ti-circle"></i>
                        </span>
                        <span class="wlp-agenda-label">{{ sectionLabel(sec.section_key) }}</span>
                        <span class="wlp-agenda-mins">{{ Math.round((sec.time_planned_seconds ?? 0) / 60) }}m</span>
                    </div>
                </div>
            </div>

            <!-- ── Q&A / Notas ─────────────────────────────────────────────── -->
            <div class="wlp-block wlp-block-grow">
                <div class="wlp-block-title"><i class="ti ti-message-question me-1"></i>Preguntas y notas</div>

                <!-- Lista de notas -->
                <div class="wlp-notes-list" ref="notesListEl">
                    <div v-if="notes.length === 0" class="wlp-notes-empty">Sin notas aún.</div>
                    <div
                        v-for="note in notes"
                        :key="note.id"
                        class="wlp-note"
                        :class="`wlp-note-${note.kind}`"
                    >
                        <div class="wlp-note-header">
                            <span class="wlp-note-kind">{{ kindLabel(note.kind) }}</span>
                            <span class="wlp-note-author">{{ note.author?.name ?? '—' }}</span>
                        </div>
                        <div class="wlp-note-body">{{ note.body }}</div>
                    </div>
                </div>

                <!-- Formulario agregar nota -->
                <div class="wlp-note-form">
                    <div class="wlp-note-kind-btns">
                        <button
                            v-for="k in NOTE_KINDS" :key="k.value"
                            class="wlp-nkbtn"
                            :class="{ active: newNote.kind === k.value }"
                            @click="newNote.kind = k.value"
                        >{{ k.label }}</button>
                    </div>
                    <div class="wlp-note-input-row">
                        <textarea
                            v-model="newNote.body"
                            class="wlp-note-textarea"
                            :placeholder="newNote.kind === 'pregunta' ? 'Escribe la pregunta…' : newNote.kind === 'respuesta' ? 'Escribe la respuesta…' : 'Escribe una nota…'"
                            rows="2"
                            @keydown.enter.ctrl.prevent="submitNote"
                        ></textarea>
                        <button class="wlp-note-send" @click="submitNote" :disabled="!newNote.body.trim() || noteSending">
                            <i class="ti ti-send"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Acuerdos / Action items ─────────────────────────────────── -->
            <div class="wlp-block" v-if="actionItems.length">
                <div class="wlp-block-title"><i class="ti ti-checkbox me-1"></i>Acuerdos ({{ actionItems.length }})</div>
                <div class="wlp-actions-list">
                    <div v-for="item in actionItems" :key="item.id" class="wlp-action-item">
                        <span class="wlp-action-dot" :class="`wlp-prio-${item.priority}`"></span>
                        <span class="wlp-action-text">{{ item.description }}</span>
                        <span class="wlp-action-assignee" v-if="item.assignee">{{ item.assignee.name.split(' ')[0] }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Controles de navegación ─────────────────────────────────── -->
            <div class="wlp-controls">
                <button class="wlp-ctrl-btn wlp-ctrl-prev" @click="$emit('prev-section')" :disabled="loading" title="Punto anterior">
                    <i class="ti ti-chevron-left"></i>
                </button>
                <button class="wlp-ctrl-btn wlp-ctrl-next" @click="$emit('next-section')" :disabled="loading">
                    <i class="ti ti-chevron-right me-1"></i>Siguiente punto
                </button>
                <button class="wlp-ctrl-btn wlp-ctrl-end" @click="$emit('end')" :disabled="loading" title="Finalizar junta">
                    <i class="ti ti-flag"></i>
                </button>
            </div>

        </template><!-- /v-else activo -->

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    meeting:              { type: Object,  default: null },
    currentSection:       { type: Object,  default: null },
    elapsedSectionSeconds:{ type: Number,  default: 0 },
    sectionProgress:      { type: Number,  default: 0 },
    loading:              { type: Boolean, default: false },
});

const emit = defineEmits(['start-meeting', 'next-section', 'prev-section', 'end']);

// ── Reloj idle ─────────────────────────────────────────────────────────────────
const idleClock = ref('');
let idleTimer = null;

function updateIdleClock() {
    const now = new Date();
    const h = now.getHours().toString().padStart(2, '0');
    const m = now.getMinutes().toString().padStart(2, '0');
    const s = now.getSeconds().toString().padStart(2, '0');
    idleClock.value = `${h}:${m}:${s}`;
}

onMounted(() => {
    updateIdleClock();
    idleTimer = setInterval(updateIdleClock, 1000);
});
onBeforeUnmount(() => clearInterval(idleTimer));

const IDLE_SECTIONS = [
    { key: 'resumen',     label: 'Resumen ejecutivo',      mins: 5  },
    { key: 'finanzas',    label: 'Finanzas',               mins: 10 },
    { key: 'operaciones', label: 'Operaciones',            mins: 10 },
    { key: 'ventas',      label: 'Ventas y Embajadores',   mins: 10 },
    { key: 'red',         label: 'Red e Infraestructura',  mins: 5  },
    { key: 'talento',     label: 'Talento',                mins: 5  },
];

// ── Notes state ────────────────────────────────────────────────────────────────
const notes      = ref([]);
const noteSending = ref(false);
const notesListEl = ref(null);
const NOTE_KINDS = [
    { value: 'nota',      label: 'Nota' },
    { value: 'pregunta',  label: 'Pregunta' },
    { value: 'respuesta', label: 'Respuesta' },
];
const newNote = ref({ kind: 'nota', body: '' });

// ── Action items ───────────────────────────────────────────────────────────────
const actionItems = computed(() => props.meeting?.action_items ?? props.meeting?.actionItems ?? []);

// ── Sección countdown ──────────────────────────────────────────────────────────
const sectionSecondsLeft = computed(() => {
    const planned = props.currentSection?.time_planned_seconds ?? 0;
    return planned - props.elapsedSectionSeconds;
});

// Alarma Web Audio al llegar a 0
let alarmFired = false;
watch(sectionSecondsLeft, (val, prev) => {
    if (prev > 0 && val <= 0 && !alarmFired) {
        alarmFired = true;
        playBeep();
    }
    if (val > 5) alarmFired = false;
});

function playBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.6);
    } catch { /* silencioso si AudioContext no disponible */ }
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

function sectionLabel(key) {
    return SECTION_LABELS[key] ?? key;
}

function kindLabel(kind) {
    return { nota: 'Nota', pregunta: '?', respuesta: '↩' }[kind] ?? kind;
}

function formatClock(seconds) {
    const m = Math.floor(Math.abs(seconds) / 60).toString().padStart(2, '0');
    const s = (Math.abs(seconds) % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

// ── Cargar notas cuando la junta cambia ────────────────────────────────────────
watch(() => props.meeting?.id, async (id) => {
    if (!id) { notes.value = []; return; }
    await loadNotes(id);
}, { immediate: true });

async function loadNotes(meetingId) {
    try {
        const { data } = await axios.get(`/warroom/api/meetings/${meetingId}/notes`);
        notes.value = data ?? [];
    } catch { notes.value = []; }
}

async function submitNote() {
    if (!newNote.value.body.trim() || !props.meeting?.id) return;
    noteSending.value = true;
    try {
        const { data } = await axios.post(`/warroom/api/meetings/${props.meeting.id}/notes`, {
            kind:       newNote.value.kind,
            body:       newNote.value.body.trim(),
            section_id: props.currentSection?.id ?? null,
        });
        notes.value.push(data);
        newNote.value.body = '';
        await nextTick();
        if (notesListEl.value) {
            notesListEl.value.scrollTop = notesListEl.value.scrollHeight;
        }
    } catch { /* silent */ } finally {
        noteSending.value = false;
    }
}
</script>

<style scoped>
/* ── Panel container ──────────────────────────────────────────────────────── */
.wlp-panel {
    width: 290px;
    min-width: 290px;
    flex-shrink: 0;
    background: #0c0c1e;
    border-left: 1px solid rgba(255,255,255,0.08);
    border-radius: 0 0 8px 0;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    overflow-x: hidden;
    align-self: flex-start;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.wlp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    gap: 6px;
    flex-shrink: 0;
}
.wlp-header-left  { display: flex; align-items: center; gap: 6px; min-width: 0; }
.wlp-meeting-name { font-size: 12px; color: #c0c0e0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.wlp-type-badge {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 4px;
    padding: 2px 6px;
    flex-shrink: 0;
    font-weight: 600;
}
.wlp-type-ordinaria { background: rgba(83,74,183,0.25); color: #a89ff5; }
.wlp-type-acta      { background: rgba(29,158,117,0.25); color: #5ddbb3; }

.wlp-close {
    background: none; border: none; color: #6b6b85;
    cursor: pointer; font-size: 14px; padding: 2px; flex-shrink: 0;
    transition: color 0.15s;
}
.wlp-close:hover { color: #e8e8f0; }

/* ── Reloj ────────────────────────────────────────────────────────────────── */
.wlp-clock-section {
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: center;
    flex-shrink: 0;
}
.wlp-clock {
    font-size: 42px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: 2px;
    font-variant-numeric: tabular-nums;
    transition: color 0.3s, font-size 0.3s;
    line-height: 1;
}
.wlp-clock-warning { color: #f5c47a; font-size: 48px; }
.wlp-clock-over    { color: #e05252; font-size: 48px; }

.wlp-clock-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b6b85;
    margin-top: 4px;
}
.wlp-overtime-label { color: #e05252; }

.wlp-clock-section-name {
    font-size: 11px;
    color: #9896b8;
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wlp-progress-bar {
    height: 3px;
    background: rgba(255,255,255,0.08);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}
.wlp-progress-fill {
    height: 100%;
    background: #534AB7;
    border-radius: 2px;
    transition: width 1s linear, background 0.3s;
}
.wlp-progress-over { background: #e05252; }

/* ── Bloques genéricos ────────────────────────────────────────────────────── */
.wlp-block {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    flex-shrink: 0;
}
.wlp-block-grow {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}
.wlp-block-title {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b6b85;
    margin-bottom: 8px;
}

/* ── Agenda ───────────────────────────────────────────────────────────────── */
.wlp-agenda { display: flex; flex-direction: column; gap: 3px; }
.wlp-agenda-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 3px 4px;
    border-radius: 4px;
    font-size: 11px;
    color: #9896b8;
    transition: background 0.1s;
}
.wlp-agenda-current {
    background: rgba(83,74,183,0.2);
    color: #e8e8f0;
    font-weight: 500;
}
.wlp-agenda-done    { color: #4a4a6a; text-decoration: line-through; }
.wlp-agenda-skipped { color: #4a4a6a; opacity: 0.6; }
.wlp-agenda-icon    { width: 14px; text-align: center; flex-shrink: 0; font-size: 10px; }
.wlp-agenda-label   { flex: 1; }
.wlp-agenda-mins    { font-size: 10px; color: #4a4a6a; flex-shrink: 0; }

/* ── Notas / Q&A ──────────────────────────────────────────────────────────── */
.wlp-notes-list {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 8px;
    min-height: 60px;
    max-height: 160px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.wlp-notes-empty { font-size: 11px; color: #4a4a6a; text-align: center; padding: 10px 0; }

.wlp-note {
    background: rgba(255,255,255,0.03);
    border-left: 2px solid transparent;
    border-radius: 4px;
    padding: 5px 8px;
    font-size: 11px;
}
.wlp-note-pregunta  { border-left-color: #534AB7; }
.wlp-note-respuesta { border-left-color: #1D9E75; }
.wlp-note-nota      { border-left-color: #BA7517; }

.wlp-note-header {
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 2px;
}
.wlp-note-kind {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #6b6b85;
}
.wlp-note-author { font-size: 9px; color: #534AB7; margin-left: auto; }
.wlp-note-body   { color: #c0c0e0; line-height: 1.35; word-break: break-word; }

/* Formulario nota */
.wlp-note-form { flex-shrink: 0; }
.wlp-note-kind-btns { display: flex; gap: 4px; margin-bottom: 5px; }
.wlp-nkbtn {
    flex: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    color: #6b6b85;
    font-size: 10px;
    padding: 3px 4px;
    cursor: pointer;
    transition: all 0.12s;
    text-align: center;
}
.wlp-nkbtn.active { background: rgba(83,74,183,0.2); border-color: #534AB7; color: #c5bfff; }

.wlp-note-input-row { display: flex; gap: 5px; align-items: flex-end; }
.wlp-note-textarea {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 11px;
    padding: 5px 8px;
    resize: none;
    outline: none;
    min-height: 42px;
    max-height: 80px;
    font-family: inherit;
    line-height: 1.4;
}
.wlp-note-textarea:focus { border-color: #534AB7; }
.wlp-note-send {
    background: #534AB7;
    border: none;
    border-radius: 5px;
    color: #fff;
    font-size: 14px;
    padding: 7px 9px;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
    align-self: flex-end;
}
.wlp-note-send:hover { background: #6258d3; }
.wlp-note-send:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Action items ─────────────────────────────────────────────────────────── */
.wlp-actions-list { display: flex; flex-direction: column; gap: 3px; }
.wlp-action-item  {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 11px; color: #9896b8; padding: 2px 0;
}
.wlp-action-dot {
    width: 6px; height: 6px; border-radius: 50%; margin-top: 4px; flex-shrink: 0;
}
.wlp-prio-high   { background: #e05252; }
.wlp-prio-medium { background: #f5c47a; }
.wlp-prio-low    { background: #5ddbb3; }
.wlp-action-text    { flex: 1; line-height: 1.3; }
.wlp-action-assignee {
    font-size: 9px; color: #534AB7; background: rgba(83,74,183,0.15);
    border-radius: 3px; padding: 1px 5px; flex-shrink: 0;
}

/* ── Controles de navegación ──────────────────────────────────────────────── */
.wlp-controls {
    display: flex;
    gap: 5px;
    padding: 10px 12px;
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}
.wlp-ctrl-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #9896b8;
    font-size: 12px;
    padding: 6px 8px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex; align-items: center;
}
.wlp-ctrl-btn:hover { background: rgba(255,255,255,0.10); color: #e8e8f0; }
.wlp-ctrl-btn:disabled { opacity: 0.35; cursor: not-allowed; }

.wlp-ctrl-next {
    flex: 1;
    justify-content: center;
    background: rgba(83,74,183,0.2);
    border-color: rgba(83,74,183,0.4);
    color: #c5bfff;
    font-weight: 500;
}
.wlp-ctrl-next:hover { background: rgba(83,74,183,0.35); color: #fff; }

.wlp-ctrl-end {
    background: rgba(224,82,82,0.1);
    border-color: rgba(224,82,82,0.3);
    color: #e05252;
}
.wlp-ctrl-end:hover { background: rgba(224,82,82,0.2); }

/* ── Estado idle ──────────────────────────────────────────────────────────── */
.wlp-clock-idle {
    color: #6b6b85;
    font-size: 32px;
    font-weight: 400;
    letter-spacing: 3px;
}
.wlp-agenda-idle { opacity: 0.45; }
.wlp-block-grow  { flex: 1; display: flex; flex-direction: column; }
.wlp-idle-cta    { justify-content: flex-end; align-items: center; padding-bottom: 20px; }
.wlp-idle-hint {
    font-size: 11px;
    color: #6b6b85;
    text-align: center;
    line-height: 1.5;
    margin-bottom: 14px;
    padding: 0 4px;
}
.wlp-btn-start-meeting {
    width: 100%;
    background: rgba(83,74,183,0.2);
    border: 1px solid rgba(83,74,183,0.5);
    border-radius: 7px;
    color: #c5bfff;
    font-size: 13px;
    font-weight: 500;
    padding: 9px 12px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wlp-btn-start-meeting:hover {
    background: rgba(83,74,183,0.35);
    color: #fff;
    border-color: #534AB7;
}
</style>
