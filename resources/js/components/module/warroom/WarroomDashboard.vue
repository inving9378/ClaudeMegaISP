<template>
    <div class="warroom-container">

        <!-- ── Meeting Control Bar (cuando hay junta activa) ─────────────────── -->
        <MeetingControlBar
            v-if="meeting?.status === 'in_progress' || meeting?.status === 'paused'"
            :meeting="meeting"
            :current-section="currentSection"
            :elapsed-total-seconds="elapsedTotalSeconds"
            :elapsed-section-seconds="elapsedSectionSeconds"
            :section-progress="sectionProgress"
            :section-color="sectionColor"
            :ai-suggestion="aiSuggestion"
            :loading="meetingLoading"
            @next-section="nextSection"
            @previous-section="previousSection"
            @pause="pause"
            @end="confirmEnd"
        />

        <!-- ── Header ────────────────────────────────────────────────────────── -->
        <div class="wr-header">
            <div class="wr-header-left">
                <h4 class="wr-title">
                    <i class="ti ti-target-arrow me-2"></i>
                    War Room Ejecutivo
                </h4>
                <PeriodSelector v-model="currentPeriod" />
            </div>
            <div class="wr-header-right">
                <button
                    v-if="!meeting"
                    class="wr-btn-start"
                    @click="setupOpen = true"
                    :disabled="meetingLoading"
                >
                    <i class="ti ti-broadcast me-1"></i>
                    Iniciar junta
                </button>
                <span v-else class="wr-meeting-badge">
                    <i class="ti ti-radio-active me-1"></i>
                    Junta en curso
                </span>
            </div>
        </div>

        <!-- ── Tabs de secciones ──────────────────────────────────────────────── -->
        <q-tabs
            v-model="currentView"
            align="left"
            dense
            class="wr-tabs"
            active-color="white"
            indicator-color="white"
        >
            <q-tab name="resumen"     :class="['wr-tab-resumen',     currentView==='resumen'     ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-target-arrow me-1"></i>Resumen</span>
            </q-tab>
            <q-tab name="finanzas"    :class="['wr-tab-finanzas',    currentView==='finanzas'    ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-coin me-1"></i>Finanzas</span>
            </q-tab>
            <q-tab name="operaciones" :class="['wr-tab-operaciones', currentView==='operaciones' ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-tools me-1"></i>Operaciones</span>
            </q-tab>
            <q-tab name="ventas"      :class="['wr-tab-ventas',      currentView==='ventas'      ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-trending-up me-1"></i>Ventas</span>
            </q-tab>
            <q-tab name="red"         :class="['wr-tab-red',         currentView==='red'         ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-network me-1"></i>Red</span>
            </q-tab>
            <q-tab name="marketing"   :class="['wr-tab-marketing',   currentView==='marketing'   ? 'wr-tab-active':'']">
                <span class="wr-tab-inner"><i class="ti ti-brand-whatsapp me-1"></i>Marketing</span>
            </q-tab>
        </q-tabs>

        <!-- ── Contenido de cada vista ────────────────────────────────────────── -->
        <q-tab-panels v-model="currentView" animated class="wr-panels">
            <q-tab-panel name="resumen"     class="wr-panel-content">
                <ViewResumen     :period="currentPeriod" :active-meeting-id="meeting?.id ?? null" />
            </q-tab-panel>
            <q-tab-panel name="finanzas"    class="wr-panel-content">
                <ViewFinanzas    :period="currentPeriod" />
            </q-tab-panel>
            <q-tab-panel name="operaciones" class="wr-panel-content">
                <ViewOperaciones :period="currentPeriod" />
            </q-tab-panel>
            <q-tab-panel name="ventas"      class="wr-panel-content">
                <ViewVentas      :period="currentPeriod" />
            </q-tab-panel>
            <q-tab-panel name="red"         class="wr-panel-content">
                <ViewRed         :period="currentPeriod" />
            </q-tab-panel>
            <q-tab-panel name="marketing"   class="wr-panel-content">
                <ViewMarketing   :period="currentPeriod" />
            </q-tab-panel>
        </q-tab-panels>

        <!-- ── Setup modal ───────────────────────────────────────────────────── -->
        <MeetingSetup
            v-model="setupOpen"
            @started="onMeetingStarted"
            @cancel="setupOpen = false"
        />

        <!-- ── Confirm end dialog ────────────────────────────────────────────── -->
        <q-dialog v-model="confirmEndOpen">
            <q-card class="wr-dialog">
                <q-card-section>
                    <div class="text-h6">¿Finalizar la junta?</div>
                    <p class="q-mt-sm text-grey-6">
                        Duración: {{ formatTime(elapsedTotalSeconds) }}.
                        Todas las secciones se cerrarán y se generará el resumen.
                    </p>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="negative" label="Finalizar junta" @click="doEnd" :loading="meetingLoading" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- ── QuickCreate modal (Ctrl+Shift+T) ─────────────────────────────── -->
        <ActionItemQuickCreate
            v-if="meeting"
            v-model="quickCreateOpen"
            :meeting="meeting"
            :current-section="currentSection"
            :users="warRoomUsers"
            @created="onActionItemCreated"
        />

        <!-- ── Summary modal ─────────────────────────────────────────────────── -->
        <MeetingSummary
            v-if="summaryData"
            v-model="summaryOpen"
            :data="summaryData"
            @close="closeSummary"
        />

    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useMeeting } from './composables/useMeeting.js';
import MeetingControlBar from './meeting/MeetingControlBar.vue';
import MeetingSetup from './meeting/MeetingSetup.vue';
import MeetingSummary from './meeting/MeetingSummary.vue';
import ActionItemQuickCreate from './meeting/ActionItemQuickCreate.vue';
import PeriodSelector from './shared/PeriodSelector.vue';
import ViewResumen from './views/ViewResumen.vue';
import ViewFinanzas from './views/ViewFinanzas.vue';
import ViewOperaciones from './views/ViewOperaciones.vue';
import ViewVentas from './views/ViewVentas.vue';
import ViewRed from './views/ViewRed.vue';
import ViewMarketing from './views/ViewMarketing.vue';

defineProps({
    csrfToken: String,
    baseUrl:   String,
});

// ── Estado de período y tab ──────────────────────────────────────────────────
const now           = new Date();
const currentPeriod = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
const currentView   = ref('resumen');

// ── Meeting ──────────────────────────────────────────────────────────────────
const {
    meeting, loading: meetingLoading, currentSection, aiSuggestion,
    elapsedTotalSeconds, elapsedSectionSeconds,
    sectionProgress, sectionColor,
    nextSection, previousSection, pause, end, loadActive, formatTime,
} = useMeeting();

// ── Auto-switch tab cuando el moderador avanza de sección ───────────────────
watch(() => meeting.value?.current_section_key, newKey => {
    if (newKey) currentView.value = newKey;
});

// ── Setup modal ──────────────────────────────────────────────────────────────
const setupOpen = ref(false);

function onMeetingStarted(data) {
    meeting.value = data;
    elapsedTotalSeconds.value = 0;
    elapsedSectionSeconds.value = 0;
    if (data.current_section_key) currentView.value = data.current_section_key;
}

// ── Confirm end + summary ────────────────────────────────────────────────────
const confirmEndOpen = ref(false);
const summaryOpen    = ref(false);
const summaryData    = ref(null);

function confirmEnd() { confirmEndOpen.value = true; }

async function doEnd() {
    confirmEndOpen.value = false;
    const result = await end();
    if (result) {
        summaryData.value = result;
        summaryOpen.value = true;
    }
}

function closeSummary() {
    summaryOpen.value = false;
    summaryData.value = null;
}

// ── ActionItem QuickCreate + Ctrl+Shift+T ────────────────────────────────────
const quickCreateOpen = ref(false);
const warRoomUsers    = ref([]);

async function loadUsers() {
    try {
        const { data } = await axios.get('/warroom/api/users');
        warRoomUsers.value = data;
    } catch { /* silent */ }
}

function handleKeydown(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'T' && meeting.value) {
        e.preventDefault();
        quickCreateOpen.value = true;
    }
}

function onActionItemCreated(item) {
    // El observer ya crea la Task y envía WhatsApp; solo necesitamos recargar
    loadActive();
}

onMounted(() => {
    loadUsers();
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   WAR ROOM — Dark mode scoped a .warroom-container
   Nunca modifica estilos fuera de este contenedor.
═══════════════════════════════════════════════════════════════════════════ */
.warroom-container {
    --wr-bg:              #0f0f1a;
    --wr-surface:         rgba(255,255,255,0.04);
    --wr-surface-hover:   rgba(255,255,255,0.07);
    --wr-border:          rgba(255,255,255,0.08);
    --wr-text:            #e8e8f0;
    --wr-text-muted:      #9999b0;
    --wr-text-dim:        #6b6b85;

    --wr-green:   #1D9E75;
    --wr-blue:    #185FA5;
    --wr-purple:  #534AB7;
    --wr-orange:  #BA7517;
    --wr-pink:    #D4537E;

    background: var(--wr-bg);
    min-height: 100vh;
    padding: 12px 16px;
    color: var(--wr-text);
    font-family: inherit;
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.warroom-container .wr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}

.warroom-container .wr-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.warroom-container .wr-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--wr-text);
    margin: 0;
}

/* ── Tabs ─────────────────────────────────────────────────────────────── */
.warroom-container .wr-tabs {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--wr-border);
    border-radius: 8px 8px 0 0;
}

.warroom-container .wr-tabs .q-tab {
    color: var(--wr-text-muted);
    font-size: 12px;
    letter-spacing: 0.3px;
    min-height: 40px;
}

.warroom-container .wr-tabs .q-tab--active {
    color: #fff;
}

.warroom-container .wr-tab-inner {
    display: flex;
    align-items: center;
    font-size: 12px;
}

.warroom-container .wr-panels {
    background: transparent;
}

.warroom-container .wr-panel-content {
    background: transparent;
    padding: 14px 0;
}

/* ── KPI Grid ─────────────────────────────────────────────────────────── */
.warroom-container .wr-kpi-grid {
    display: grid;
    gap: 10px;
}

.warroom-container .wr-kpi-grid-1 { grid-template-columns: 1fr; }
.warroom-container .wr-kpi-grid-2 { grid-template-columns: repeat(2, 1fr); }
.warroom-container .wr-kpi-grid-3 { grid-template-columns: repeat(3, 1fr); }

/* ── KPI Card ─────────────────────────────────────────────────────────── */
.warroom-container .wr-kpi-card {
    background: var(--wr-surface);
    border: 1px solid var(--wr-border);
    border-radius: 8px;
    padding: 10px 14px;
    transition: background 0.15s;
    position: relative;
    overflow: hidden;
}

.warroom-container .wr-kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 3px; height: 100%;
    border-radius: 8px 0 0 8px;
}

.warroom-container .wr-kpi-card.accent-green::before  { background: var(--wr-green); }
.warroom-container .wr-kpi-card.accent-blue::before   { background: var(--wr-blue); }
.warroom-container .wr-kpi-card.accent-purple::before { background: var(--wr-purple); }
.warroom-container .wr-kpi-card.accent-orange::before { background: var(--wr-orange); }
.warroom-container .wr-kpi-card.accent-pink::before   { background: var(--wr-pink); }

.warroom-container .wr-kpi-card:hover { background: var(--wr-surface-hover); }

.warroom-container .wr-kpi-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.warroom-container .wr-kpi-icon { color: var(--wr-text-muted); font-size: 13px; }
.warroom-container .wr-kpi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--wr-text-muted); }

.warroom-container .wr-kpi-value {
    font-size: 22px;
    font-weight: 500;
    color: #fff;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}

.warroom-container .wr-kpi-card.wr-kpi-hero .wr-kpi-value {
    font-size: 28px;
}

.warroom-container .wr-kpi-delta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    margin-top: 3px;
}

.warroom-container .wr-kpi-prev { color: var(--wr-text-dim); }

/* Skeleton sizes */
.warroom-container .wr-skel-label  { height: 10px; }
.warroom-container .wr-skel-value  { height: 28px; }
.warroom-container .wr-skel-delta  { height: 11px; }

/* ── Delta colors ─────────────────────────────────────────────────────── */
.warroom-container .wr-delta-up      { color: #1D9E75; }
.warroom-container .wr-delta-down    { color: #e05252; }
.warroom-container .wr-delta-neutral { color: var(--wr-text-dim); }

/* ── Period selector ──────────────────────────────────────────────────── */
.warroom-container .wr-period-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}

.warroom-container .wr-period-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--wr-text);
    min-width: 110px;
    text-align: center;
}

.warroom-container .wr-period-btn {
    background: var(--wr-surface);
    border: 1px solid var(--wr-border);
    border-radius: 4px;
    color: var(--wr-text-muted);
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.12s;
}

.warroom-container .wr-period-btn:hover:not(:disabled) {
    background: var(--wr-surface-hover);
    color: var(--wr-text);
}

.warroom-container .wr-period-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* ── Start button ─────────────────────────────────────────────────────── */
.warroom-container .wr-btn-start {
    background: var(--wr-purple);
    border: none;
    border-radius: 6px;
    color: #fff;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex; align-items: center;
    transition: opacity 0.15s;
}

.warroom-container .wr-btn-start:hover:not(:disabled) { opacity: 0.85; }
.warroom-container .wr-btn-start:disabled { opacity: 0.4; cursor: not-allowed; }

.warroom-container .wr-meeting-badge {
    background: rgba(29,158,117,0.15);
    border: 1px solid var(--wr-green);
    color: var(--wr-green);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
    animation: wr-pulse 2s ease-in-out infinite;
}

@keyframes wr-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.65; }
}

/* ── Insights block ───────────────────────────────────────────────────── */
.warroom-container .wr-insights-block {
    border-left: 3px solid #EF9F27;
    border-radius: 6px;
    padding: 12px 14px;
    background: rgba(239,159,39,0.06);
}

.warroom-container .wr-insights-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #EF9F27;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.warroom-container .wr-insights-badge-ai {
    background: rgba(239,159,39,0.2);
    border: 1px solid #EF9F27;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 9px;
}

.warroom-container .wr-insights-list {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.warroom-container .wr-insights-list li {
    font-size: 12px;
    color: var(--wr-text);
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.warroom-container .wr-insight-positivo   i { color: var(--wr-green); }
.warroom-container .wr-insight-atencion   i { color: #e05252; }
.warroom-container .wr-insight-oportunidad i { color: #EF9F27; }

.warroom-container .wr-insights-empty {
    font-size: 12px;
    color: var(--wr-text-dim);
}

.warroom-container .wr-insights-regen {
    color: #EF9F27;
    text-decoration: underline;
    cursor: pointer;
    margin-left: 6px;
}

/* ── Action items ─────────────────────────────────────────────────────── */
.warroom-container .wr-action-items {
    background: var(--wr-surface);
    border: 1px solid var(--wr-border);
    border-radius: 8px;
    padding: 12px;
}

.warroom-container .wr-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--wr-text-muted);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.warroom-container .wr-btn-add {
    background: transparent;
    border: 1px solid var(--wr-border);
    border-radius: 4px;
    color: var(--wr-text-muted);
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 12px;
    margin-left: auto;
}

.warroom-container .wr-btn-add:hover { color: #fff; border-color: var(--wr-text-muted); }

.warroom-container .wr-action-form {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--wr-border);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 10px;
}

.warroom-container .wr-action-textarea {
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--wr-border);
    color: var(--wr-text);
    font-size: 13px;
    resize: none;
    padding: 4px 0;
    outline: none;
}

.warroom-container .wr-action-form-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.warroom-container .wr-action-select,
.warroom-container .wr-action-date {
    background: var(--wr-surface);
    border: 1px solid var(--wr-border);
    border-radius: 4px;
    color: var(--wr-text);
    font-size: 12px;
    padding: 3px 6px;
}

.warroom-container .wr-btn-submit,
.warroom-container .wr-btn-cancel {
    border: none;
    border-radius: 4px;
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 13px;
}

.warroom-container .wr-btn-submit { background: var(--wr-green); color: #fff; }
.warroom-container .wr-btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }
.warroom-container .wr-btn-cancel { background: rgba(255,255,255,0.08); color: var(--wr-text-muted); }

.warroom-container .wr-action-list {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.warroom-container .wr-action-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid var(--wr-border);
}

.warroom-container .wr-action-item:last-child { border-bottom: none; }

.warroom-container .wr-action-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}

.warroom-container .dot-critico     { background: #e05252; }
.warroom-container .dot-alto        { background: #EF9F27; }
.warroom-container .dot-medio       { background: #EF9F27; opacity: 0.6; }
.warroom-container .dot-oportunidad { background: var(--wr-green); }
.warroom-container .dot-estrategico { background: var(--wr-blue); }

.warroom-container .wr-action-body { flex: 1; min-width: 0; }
.warroom-container .wr-action-desc { font-size: 13px; color: var(--wr-text); }
.warroom-container .wr-action-meta { display: flex; gap: 10px; margin-top: 3px; flex-wrap: wrap; }
.warroom-container .wr-action-assignee,
.warroom-container .wr-action-deadline {
    font-size: 11px;
    color: var(--wr-text-dim);
    display: flex; align-items: center; gap: 3px;
}

.warroom-container .wr-action-priority-badge {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    border: 1px solid currentColor;
}

.warroom-container .pbadge-critico     { color: #e05252; }
.warroom-container .pbadge-alto        { color: #EF9F27; }
.warroom-container .pbadge-medio       { color: var(--wr-text-dim); }
.warroom-container .pbadge-oportunidad { color: var(--wr-green); }
.warroom-container .pbadge-estrategico { color: var(--wr-blue); }

.warroom-container .wr-action-status {
    font-size: 10px;
    flex-shrink: 0;
    margin-top: 3px;
    color: var(--wr-text-dim);
}

.warroom-container .wr-action-item-skeleton { padding: 6px 0; border-bottom: 1px solid var(--wr-border); }
.warroom-container .wr-action-empty { font-size: 12px; color: var(--wr-text-dim); padding: 4px 0; }

/* ── Meeting Control Bar ──────────────────────────────────────────────── */
.warroom-container .wr-meeting-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(83,74,183,0.15);
    border: 1px solid rgba(83,74,183,0.4);
    border-radius: 8px;
    padding: 8px 14px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.warroom-container .wr-meeting-section {
    display: flex;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    color: #fff;
    min-width: 160px;
}

.warroom-container .wr-meeting-timer-block {
    flex: 1;
    min-width: 160px;
}

.warroom-container .wr-meeting-time-section {
    font-size: 20px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.warroom-container .wr-meeting-time-planned {
    font-size: 13px;
    color: var(--wr-text-muted);
    margin-left: 4px;
}

.warroom-container .wr-meeting-progress { margin-top: 4px; }

.warroom-container .wr-meeting-total-time {
    font-size: 12px;
    color: var(--wr-text-muted);
}

.warroom-container .wr-meeting-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}

/* ── Generic panels and tables ───────────────────────────────────────── */
.warroom-container .wr-panel {
    background: var(--wr-surface);
    border: 1px solid var(--wr-border);
    border-radius: 8px;
    padding: 12px;
}

.warroom-container .wr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.warroom-container .wr-table th {
    color: var(--wr-text-muted);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 4px 6px;
    border-bottom: 1px solid var(--wr-border);
}

.warroom-container .wr-table td {
    padding: 5px 6px;
    color: var(--wr-text);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

.warroom-container .wr-text-danger { color: #e05252 !important; }
.warroom-container .wr-empty { font-size: 12px; color: var(--wr-text-dim); }
.warroom-container .wr-empty-muted { font-size: 12px; color: var(--wr-text-dim); }

/* ── Cashflow ─────────────────────────────────────────────────────────── */
.warroom-container .wr-cashflow-list { display: flex; flex-direction: column; gap: 6px; }
.warroom-container .wr-cashflow-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px;
}
.warroom-container .wr-cashflow-date { color: var(--wr-text-muted); width: 55px; flex-shrink: 0; }
.warroom-container .wr-cashflow-bar-wrap { flex: 1; background: rgba(255,255,255,0.06); border-radius: 2px; height: 6px; }
.warroom-container .wr-cashflow-bar { height: 6px; background: var(--wr-green); border-radius: 2px; transition: width 0.4s; }
.warroom-container .wr-cashflow-amount { color: var(--wr-text); font-weight: 500; min-width: 80px; text-align: right; }
.warroom-container .wr-cashflow-count { color: var(--wr-text-dim); width: 45px; text-align: right; }

/* ── Status bars ──────────────────────────────────────────────────────── */
.warroom-container .wr-status-bars { display: flex; flex-direction: column; gap: 6px; }
.warroom-container .wr-status-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px;
}
.warroom-container .wr-status-label { color: var(--wr-text-muted); width: 80px; flex-shrink: 0; }
.warroom-container .wr-status-bar-wrap { flex: 1; background: rgba(255,255,255,0.06); border-radius: 2px; height: 6px; }
.warroom-container .wr-status-bar { height: 6px; background: var(--wr-orange); border-radius: 2px; transition: width 0.4s; }
.warroom-container .wr-status-count { color: var(--wr-text); width: 30px; text-align: right; }

/* ── Priority chips ───────────────────────────────────────────────────── */
.warroom-container .wr-priority-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.warroom-container .wr-priority-chip {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--wr-border);
    border-radius: 4px;
    padding: 4px 10px;
    font-size: 12px;
    display: flex; align-items: center; gap: 8px;
}
.warroom-container .wr-prio-label { color: var(--wr-text-muted); }
.warroom-container .wr-prio-count { color: #fff; font-weight: 500; }

/* ── Dialog ───────────────────────────────────────────────────────────── */
.warroom-container .wr-dialog {
    background: #1a1a2e;
    color: var(--wr-text);
    border: 1px solid var(--wr-border);
    min-width: 320px;
}

/* ── Skeleton override for dark theme ─────────────────────────────────── */
.warroom-container .q-skeleton {
    background: rgba(255,255,255,0.08) !important;
}
</style>
