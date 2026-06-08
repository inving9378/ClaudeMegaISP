<template>
    <div class="wr-meeting-bar">

        <!-- ── Fila 1: estado + nombre + countdown + timer total ───────────────── -->
        <div class="wr-mbar-top">
            <div class="wr-mbar-live">
                <span class="wr-mbar-dot"></span>
                EN CURSO
            </div>
            <div class="wr-mbar-name">{{ meeting.name }}</div>

            <!-- Reloj en retroceso (Frente 3) -->
            <div class="wr-mbar-countdown" :class="countdownClass">
                <i class="ti me-1" :class="countdownSeconds < 0 ? 'ti-alarm' : 'ti-hourglass'"></i>
                {{ formatCountdown(countdownSeconds) }}
                <span class="wr-mbar-countdown-label">
                    {{ countdownSeconds < 0 ? 'excedente' : (countdownSeconds <= 300 ? 'último tramo' : 'restante') }}
                </span>
            </div>

            <div class="wr-mbar-total-timer">
                <i class="ti ti-clock me-1"></i>
                {{ formatTime(elapsedTotalSeconds) }}
                <span class="wr-mbar-slash"> / {{ totalPlannedStr }}</span>
            </div>
            <div class="wr-mbar-eta">ETA {{ etaString }}</div>
            <div class="wr-mbar-attendance">
                <i class="ti ti-users me-1"></i>
                {{ attendeesPresent }}/{{ attendeesTotal }}
            </div>
            <div class="wr-mbar-tasks-count">
                <i class="ti ti-checkbox me-1"></i>
                {{ actionItemsCount }} tareas
            </div>
        </div>

        <!-- ── Fila 2: sección actual + controles ────────────────────────────── -->
        <div class="wr-mbar-middle">

            <!-- Sección actual -->
            <div class="wr-mbar-section-box">
                <div class="wr-mbar-section-name">
                    <i :class="`ti ${sectionIcon} me-1`"></i>
                    {{ sectionLabel }}
                    <span v-if="currentSection?.presenter" class="wr-mbar-presenter">
                        · {{ currentSection.presenter.name }}
                    </span>
                </div>
                <div class="wr-mbar-section-timer" :class="`text-${sectionColor}`">
                    {{ formatTime(elapsedSectionSeconds) }}
                    <span class="wr-mbar-section-planned"> / {{ formatMins(currentSection?.time_planned_seconds) }}</span>
                </div>
                <q-linear-progress
                    :value="Math.min(sectionProgress / 100, 1)"
                    :color="sectionColor"
                    :track-color="sectionColor === 'negative' ? 'red-2' : 'grey-8'"
                    rounded size="6px"
                    class="wr-mbar-progress"
                />
                <div class="wr-mbar-progress-hint" :class="`text-${sectionColor}`">
                    <template v-if="sectionProgress < 100">
                        {{ formatTime(Math.max(0, (currentSection?.time_planned_seconds ?? 0) - elapsedSectionSeconds)) }} restante
                    </template>
                    <template v-else>
                        ⚡ {{ Math.round(sectionProgress) }}% del tiempo asignado
                    </template>
                </div>
            </div>

            <!-- Controles del moderador -->
            <div class="wr-mbar-controls">
                <q-btn flat dense icon="ti ti-arrow-left" label="Anterior"
                    color="grey-5" @click="$emit('previous-section')"
                    :disable="loading || !hasPrevious"
                    class="wr-mbar-btn"
                />
                <q-btn flat dense
                    :icon="meeting.status === 'paused' ? 'ti ti-player-play' : 'ti ti-player-pause'"
                    :label="meeting.status === 'paused' ? 'Reanudar' : 'Pausar'"
                    :color="meeting.status === 'paused' ? 'positive' : 'warning'"
                    @click="$emit('pause')"
                    :disable="loading"
                    class="wr-mbar-btn"
                />
                <q-btn flat dense icon="ti ti-arrow-right" label="Siguiente"
                    color="info" @click="$emit('next-section')"
                    :disable="loading"
                    class="wr-mbar-btn"
                />
                <q-btn flat dense icon="ti ti-flag" label="Finalizar junta"
                    color="negative" @click="$emit('end')"
                    :disable="loading"
                    class="wr-mbar-btn wr-mbar-btn-end"
                />
            </div>
        </div>

        <!-- ── Fila 3: agenda de secciones ───────────────────────────────────── -->
        <div class="wr-mbar-agenda">
            <div
                v-for="(sec, idx) in orderedSections"
                :key="sec.id"
                class="wr-mbar-agenda-item"
                :class="{
                    'wr-mbar-sec-done':    sec.status === 'completed',
                    'wr-mbar-sec-active':  sec.status === 'in_progress',
                    'wr-mbar-sec-skipped': sec.status === 'skipped',
                }"
            >
                <span class="wr-mbar-sec-num">{{ idx + 1 }}</span>
                <i :class="`ti ${SECTION_META[sec.section_key]?.icon ?? 'ti-circle'}`" class="wr-mbar-sec-icon"></i>
                <span class="wr-mbar-sec-name">{{ SECTION_META[sec.section_key]?.label ?? sec.section_key }}</span>
                <span class="wr-mbar-sec-time">
                    <template v-if="sec.status === 'completed'">
                        {{ formatMins(sec.time_actual_seconds) }}
                        <span :class="sec.time_actual_seconds > sec.time_planned_seconds ? 'wr-mbar-overtime' : 'wr-mbar-ontime'">
                            ({{ Math.round(sec.time_actual_seconds / Math.max(sec.time_planned_seconds, 1) * 100) }}%)
                        </span>
                    </template>
                    <template v-else>
                        {{ Math.floor(sec.time_planned_seconds / 60) }}m
                    </template>
                </span>
            </div>
        </div>

        <!-- ── Bloque IA (aparece solo cuando hay sugerencia) ────────────────── -->
        <div v-if="aiSuggestion" class="wr-mbar-ai-block">
            <span class="wr-mbar-ai-badge">IA</span>
            <span class="wr-mbar-ai-text">{{ aiSuggestion }}</span>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    meeting:               { type: Object,  required: true },
    currentSection:        { type: Object,  default: null },
    elapsedTotalSeconds:   { type: Number,  default: 0 },
    elapsedSectionSeconds: { type: Number,  default: 0 },
    sectionProgress:       { type: Number,  default: 0 },
    sectionColor:          { type: String,  default: 'positive' },
    countdownSeconds:      { type: Number,  default: 0 },
    aiSuggestion:          { type: String,  default: null },
    loading:               { type: Boolean, default: false },
});

defineEmits(['next-section', 'previous-section', 'pause', 'end']);

const SECTION_META = {
    resumen:     { label: 'Resumen ejecutivo',     icon: 'ti-target-arrow' },
    finanzas:    { label: 'Finanzas',              icon: 'ti-coin' },
    operaciones: { label: 'Operaciones',           icon: 'ti-tools' },
    ventas:      { label: 'Ventas y Embajadores',  icon: 'ti-trending-up' },
    red:         { label: 'Red e Infraestructura', icon: 'ti-network' },
    marketing:   { label: 'Marketing',             icon: 'ti-brand-whatsapp' },
    talento:     { label: 'Talento',               icon: 'ti-users' },
};

const sectionLabel = computed(() => SECTION_META[props.currentSection?.section_key]?.label ?? 'Junta en curso');
const sectionIcon  = computed(() => SECTION_META[props.currentSection?.section_key]?.icon  ?? 'ti-broadcast');

const orderedSections = computed(() =>
    [...(props.meeting.sections ?? [])].sort((a, b) => a.order_position - b.order_position)
);

const hasPrevious = computed(() => {
    const cur = props.currentSection;
    if (!cur) return false;
    return orderedSections.value.some(s => s.order_position < cur.order_position);
});

const totalPlannedSeconds = computed(() =>
    (props.meeting.sections ?? []).reduce((s, sec) => s + (sec.time_planned_seconds ?? 0), 0)
);
const totalPlannedStr = computed(() => formatMins(totalPlannedSeconds.value));

// ── Countdown ─────────────────────────────────────────────────────────────
const WARNING_THRESHOLD = 5 * 60; // últimos 5 minutos → ámbar

const countdownClass = computed(() => {
    const s = props.countdownSeconds;
    if (s < 0)              return 'wr-countdown-overtime'; // rojo
    if (s <= WARNING_THRESHOLD) return 'wr-countdown-warn';  // ámbar
    return 'wr-countdown-ok';                                // verde
});

function formatCountdown(seconds) {
    const abs = Math.abs(seconds);
    const m   = Math.floor(abs / 60).toString().padStart(2, '0');
    const s   = (abs % 60).toString().padStart(2, '0');
    return seconds < 0 ? `+${m}:${s}` : `${m}:${s}`;
}

const etaString = computed(() => {
    const start = new Date(props.meeting.started_at);
    const eta   = new Date(start.getTime() + totalPlannedSeconds.value * 1000);
    return eta.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
});

const attendeesTotal   = computed(() => props.meeting.attendees?.length ?? 0);
const attendeesPresent = computed(() => props.meeting.attendees?.filter(a => a.present).length ?? 0);
const actionItemsCount = computed(() => props.meeting.action_items?.length ?? 0);

function formatTime(s) {
    const m = Math.floor(s / 60).toString().padStart(2, '0');
    const sec = (s % 60).toString().padStart(2, '0');
    return `${m}:${sec}`;
}

function formatMins(totalSeconds) {
    if (!totalSeconds) return '0:00';
    return Math.floor(totalSeconds / 60) + ':' + ((totalSeconds % 60).toString().padStart(2, '0'));
}
</script>

<style>
/* ── Meeting Bar completa ──────────────────────────────────────────────────── */
.warroom-container .wr-meeting-bar {
    background: rgba(83,74,183,0.12);
    border: 1px solid rgba(83,74,183,0.35);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Fila 1: estado + nombre + timers */
.warroom-container .wr-mbar-top {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.warroom-container .wr-mbar-live {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    color: #e05252;
}

.warroom-container .wr-mbar-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #e05252;
    animation: wr-pulse 1.5s ease-in-out infinite;
}

.warroom-container .wr-mbar-name {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    flex: 1;
    min-width: 100px;
}

.warroom-container .wr-mbar-total-timer {
    font-size: 15px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: #fff;
}
.warroom-container .wr-mbar-slash { color: #6b6b85; font-weight: 400; }

.warroom-container .wr-mbar-eta,
.warroom-container .wr-mbar-attendance,
.warroom-container .wr-mbar-tasks-count {
    font-size: 11px;
    color: #9999b0;
    display: flex;
    align-items: center;
}

/* Fila 2: sección + controles */
.warroom-container .wr-mbar-middle {
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.warroom-container .wr-mbar-section-box {
    flex: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 6px;
    padding: 8px 12px;
}

.warroom-container .wr-mbar-section-name {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
}

.warroom-container .wr-mbar-presenter { font-size: 11px; font-weight: 400; color: #9999b0; }

.warroom-container .wr-mbar-section-timer {
    font-size: 22px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    margin-bottom: 6px;
}
.warroom-container .wr-mbar-section-planned { font-size: 13px; color: #6b6b85; font-weight: 400; }

.warroom-container .wr-mbar-progress { margin-bottom: 4px; }

.warroom-container .wr-mbar-progress-hint { font-size: 11px; }

/* Countdown (Frente 3) */
.warroom-container .wr-mbar-countdown {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 19px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.5px;
    transition: color 0.5s;
    flex-shrink: 0;
}
.warroom-container .wr-mbar-countdown-label {
    font-size: 10px;
    font-weight: 400;
    margin-left: 2px;
    opacity: 0.75;
    align-self: flex-end;
    margin-bottom: 2px;
}
.warroom-container .wr-countdown-ok       { color: #1D9E75; }
.warroom-container .wr-countdown-warn     { color: #EF9F27; animation: wr-pulse 1.8s ease-in-out infinite; }
.warroom-container .wr-countdown-overtime { color: #e05252; animation: wr-pulse 1s   ease-in-out infinite; }

/* Controles */
.warroom-container .wr-mbar-controls {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-shrink: 0;
}

.warroom-container .wr-mbar-btn { justify-content: flex-start; min-width: 110px; font-size: 12px; }
.warroom-container .wr-mbar-btn-end { font-weight: 600; }

/* Agenda */
.warroom-container .wr-mbar-agenda {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.warroom-container .wr-mbar-agenda-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.03);
    color: #6b6b85;
}

.warroom-container .wr-mbar-sec-done    { border-color: rgba(29,158,117,0.4); color: #1D9E75; }
.warroom-container .wr-mbar-sec-active  { border-color: #534AB7; background: rgba(83,74,183,0.15); color: #fff; }
.warroom-container .wr-mbar-sec-skipped { opacity: 0.35; }

.warroom-container .wr-mbar-sec-num  { font-weight: 700; }
.warroom-container .wr-mbar-sec-name { max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.warroom-container .wr-mbar-sec-time { font-size: 10px; margin-left: 2px; color: inherit; opacity: 0.8; }
.warroom-container .wr-mbar-overtime { color: #e05252; }
.warroom-container .wr-mbar-ontime   { color: #1D9E75; }

/* Bloque IA */
.warroom-container .wr-mbar-ai-block {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: rgba(83,74,183,0.15);
    border: 1px solid rgba(83,74,183,0.5);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12px;
    color: #c5bfff;
    animation: wr-fadein 0.4s ease;
}

.warroom-container .wr-mbar-ai-badge {
    background: #534AB7;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 1px 5px;
    border-radius: 3px;
    flex-shrink: 0;
    margin-top: 1px;
}

@keyframes wr-fadein {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
