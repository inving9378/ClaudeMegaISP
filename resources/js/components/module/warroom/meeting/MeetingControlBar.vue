<template>
    <div class="wr-meeting-bar" :class="{ 'wr-meeting-bar-paused': meeting.status === 'paused' }">

        <!-- Indicador live -->
        <span class="wr-mbar-dot"></span>
        <span class="wr-mbar-live-label">{{ meeting.status === 'paused' ? 'PAUSADO' : 'EN CURSO' }}</span>

        <span class="wr-mbar-sep">·</span>

        <!-- Nombre de junta -->
        <span class="wr-mbar-name">{{ meeting.name }}</span>

        <span class="wr-mbar-sep">·</span>

        <!-- Sección actual -->
        <span class="wr-mbar-section">
            <i :class="`ti ${sectionIcon} me-1`"></i>{{ sectionLabel }}
        </span>

        <!-- Spacer -->
        <span class="wr-mbar-spacer"></span>

        <!-- Reloj total elapsed -->
        <span class="wr-mbar-timer" title="Tiempo transcurrido">
            <i class="ti ti-clock me-1"></i>{{ formatTime(elapsedTotalSeconds) }}
        </span>

        <!-- Countdown total -->
        <span class="wr-mbar-countdown" :class="countdownClass" title="Tiempo restante de la agenda">
            <i class="ti me-1" :class="countdownSeconds < 0 ? 'ti-alarm' : 'ti-hourglass'"></i>
            {{ formatCountdown(countdownSeconds) }}
        </span>

        <!-- Controles compactos -->
        <button class="wr-mbar-ctrl" @click="$emit('previous-section')"
            :disabled="loading" title="Punto anterior">
            <i class="ti ti-chevron-left"></i>
        </button>
        <button class="wr-mbar-ctrl" @click="$emit('pause')"
            :disabled="loading" :title="meeting.status === 'paused' ? 'Reanudar' : 'Pausar'">
            <i :class="`ti ${meeting.status === 'paused' ? 'ti-player-play' : 'ti-player-pause'}`"></i>
        </button>
        <button class="wr-mbar-ctrl wr-mbar-ctrl-next" @click="$emit('next-section')"
            :disabled="loading" title="Siguiente punto">
            <i class="ti ti-chevron-right me-1"></i>Siguiente
        </button>
        <button class="wr-mbar-ctrl wr-mbar-ctrl-end" @click="$emit('end')"
            :disabled="loading" title="Finalizar junta">
            <i class="ti ti-flag-3"></i>
        </button>

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

const WARNING_THRESHOLD = 5 * 60;
const countdownClass = computed(() => {
    const s = props.countdownSeconds;
    if (s < 0)                return 'wr-mbar-countdown-overtime';
    if (s <= WARNING_THRESHOLD) return 'wr-mbar-countdown-warn';
    return 'wr-mbar-countdown-ok';
});

function formatCountdown(seconds) {
    const abs = Math.abs(seconds);
    const m   = Math.floor(abs / 60).toString().padStart(2, '0');
    const s   = (abs % 60).toString().padStart(2, '0');
    return seconds < 0 ? `+${m}:${s}` : `${m}:${s}`;
}

function formatTime(s) {
    const m   = Math.floor(s / 60).toString().padStart(2, '0');
    const sec = (s % 60).toString().padStart(2, '0');
    return `${m}:${sec}`;
}
</script>

<style>
/* ── Meeting Bar compacta (una sola línea) ────────────────────────────────── */
.warroom-container .wr-meeting-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(83,74,183,0.10);
    border: 1px solid rgba(83,74,183,0.30);
    border-radius: 6px;
    padding: 5px 12px;
    margin-bottom: 10px;
    min-height: 36px;
    flex-wrap: nowrap;
    overflow: hidden;
}

.warroom-container .wr-meeting-bar-paused {
    background: rgba(186,117,23,0.10);
    border-color: rgba(186,117,23,0.35);
}

.warroom-container .wr-mbar-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #e05252;
    flex-shrink: 0;
    animation: wr-pulse 1.5s ease-in-out infinite;
}
.warroom-container .wr-meeting-bar-paused .wr-mbar-dot {
    background: #BA7517;
    animation: none;
}

.warroom-container .wr-mbar-live-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #e05252;
    flex-shrink: 0;
    white-space: nowrap;
}
.warroom-container .wr-meeting-bar-paused .wr-mbar-live-label { color: #BA7517; }

.warroom-container .wr-mbar-sep {
    color: rgba(255,255,255,0.2);
    font-size: 13px;
    flex-shrink: 0;
}

.warroom-container .wr-mbar-name {
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
    flex-shrink: 1;
}

.warroom-container .wr-mbar-section {
    font-size: 12px;
    color: #c5bfff;
    display: flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
    flex-shrink: 1;
}

.warroom-container .wr-mbar-spacer { flex: 1; min-width: 0; }

.warroom-container .wr-mbar-timer {
    font-size: 13px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: #9999b0;
    display: flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}

.warroom-container .wr-mbar-countdown {
    font-size: 14px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    display: flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}
.warroom-container .wr-mbar-countdown-ok       { color: #1D9E75; }
.warroom-container .wr-mbar-countdown-warn     { color: #EF9F27; }
.warroom-container .wr-mbar-countdown-overtime { color: #e05252; animation: wr-pulse 1s ease-in-out infinite; }

/* Botones de control compactos */
.warroom-container .wr-mbar-ctrl {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 5px;
    color: #9999b0;
    padding: 3px 7px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.12s;
    white-space: nowrap;
    flex-shrink: 0;
}
.warroom-container .wr-mbar-ctrl:hover:not(:disabled) {
    background: rgba(255,255,255,0.12);
    color: #fff;
}
.warroom-container .wr-mbar-ctrl:disabled { opacity: 0.35; cursor: not-allowed; }

.warroom-container .wr-mbar-ctrl-next {
    background: rgba(83,74,183,0.2);
    border-color: rgba(83,74,183,0.4);
    color: #c5bfff;
    font-weight: 500;
}
.warroom-container .wr-mbar-ctrl-next:hover:not(:disabled) {
    background: rgba(83,74,183,0.35);
    color: #fff;
}

.warroom-container .wr-mbar-ctrl-end {
    background: rgba(224,82,82,0.10);
    border-color: rgba(224,82,82,0.35);
    color: #e88;
}
.warroom-container .wr-mbar-ctrl-end:hover:not(:disabled) {
    background: rgba(224,82,82,0.22);
    color: #ffaaaa;
}
</style>
