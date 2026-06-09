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
            :countdown-seconds="countdownSeconds"
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
                <button class="wr-btn-history" @click="historyOpen = true" title="Ver actas guardadas">
                    <i class="ti ti-archive me-1"></i>
                    Actas
                </button>
                <button
                    v-if="!meeting"
                    class="wr-btn-start"
                    @click="setupOpen = true"
                    :disabled="meetingLoading"
                >
                    <i class="ti ti-broadcast me-1"></i>
                    Iniciar junta
                </button>
                <template v-else>
                    <button
                        class="wr-meeting-badge wr-meeting-badge-btn"
                        @click="panelOpen = !panelOpen"
                        :title="panelOpen ? 'Ocultar panel de junta' : 'Abrir panel de junta'"
                    >
                        <i class="ti ti-radio-active me-1"></i>
                        Junta en curso
                        <i class="ti ms-1" :class="panelOpen ? 'ti-layout-sidebar-right-collapse' : 'ti-layout-sidebar-right-expand'"></i>
                    </button>
                    <button class="wr-btn-end-meeting" @click="confirmEnd" :disabled="meetingLoading" title="Finalizar junta">
                        <i class="ti ti-flag-3 me-1"></i>
                        Finalizar
                    </button>
                </template>
            </div>
        </div>

        <!-- ── Tabs de secciones (flex-wrap — todas visibles) ───────────────── -->
        <div class="wr-tabs-nav">
            <button
                v-for="tab in TABS"
                :key="tab.name"
                class="wr-tab-btn"
                :class="[`wr-tab-${tab.name}`, { 'wr-tab-active': currentView === tab.name }]"
                @click="currentView = tab.name"
            >
                <i :class="`ti ${tab.icon}`"></i>
                <span class="wr-tab-label">{{ tab.label }}</span>
            </button>
        </div>

        <!-- ── Cuerpo: contenido + panel lateral ────────────────────────────────── -->
        <div class="wr-body">
            <!-- Contenido de cada vista -->
            <div class="wr-main-content">
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
                    <q-tab-panel name="talento"     class="wr-panel-content">
                        <ViewTalento     :period="currentPeriod" />
                    </q-tab-panel>
                    <q-tab-panel name="desempeno"   class="wr-panel-content">
                        <ViewDesempeno   :period="currentPeriod" />
                    </q-tab-panel>
                </q-tab-panels>
            </div>

            <!-- Panel lateral de junta en vivo -->
            <MeetingLivePanel
                v-if="meeting?.status === 'in_progress' || meeting?.status === 'paused'"
                :visible="panelOpen"
                :meeting="meeting"
                :current-section="currentSection"
                :elapsed-section-seconds="elapsedSectionSeconds"
                :section-progress="sectionProgress"
                :loading="meetingLoading"
                @close="panelOpen = false"
                @next-section="nextSection"
                @prev-section="previousSection"
                @end="confirmEnd"
            />
        </div>

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

        <!-- ── Historial de actas ─────────────────────────────────────────────── -->
        <q-dialog v-model="historyOpen">
            <MeetingHistoryView @close="historyOpen = false" />
        </q-dialog>

    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useMeeting } from './composables/useMeeting.js';
import MeetingControlBar from './meeting/MeetingControlBar.vue';
import MeetingSetup from './meeting/MeetingSetup.vue';
import MeetingLivePanel from './meeting/MeetingLivePanel.vue';
import MeetingHistoryView from './meeting/MeetingHistoryView.vue';
import MeetingSummary from './meeting/MeetingSummary.vue';
import ActionItemQuickCreate from './meeting/ActionItemQuickCreate.vue';
import PeriodSelector from './shared/PeriodSelector.vue';
import ViewResumen from './views/ViewResumen.vue';
import ViewFinanzas from './views/ViewFinanzas.vue';
import ViewOperaciones from './views/ViewOperaciones.vue';
import ViewVentas from './views/ViewVentas.vue';
import ViewRed from './views/ViewRed.vue';
import ViewMarketing from './views/ViewMarketing.vue';
import ViewTalento from './views/ViewTalento.vue';
import ViewDesempeno from './views/ViewDesempeno.vue';

defineProps({
    csrfToken: String,
    baseUrl:   String,
});

// ── Definición de tabs ───────────────────────────────────────────────────────
const TABS = [
    { name: 'resumen',     icon: 'ti-target-arrow', label: 'Resumen' },
    { name: 'finanzas',    icon: 'ti-coin',          label: 'Finanzas' },
    { name: 'operaciones', icon: 'ti-tools',          label: 'Operaciones' },
    { name: 'ventas',      icon: 'ti-trending-up',    label: 'Ventas' },
    { name: 'red',         icon: 'ti-network',        label: 'Red' },
    { name: 'marketing',   icon: 'ti-brand-whatsapp', label: 'Marketing' },
    { name: 'talento',    icon: 'ti-users',          label: 'Talento' },
    { name: 'desempeno',  icon: 'ti-chart-bar',       label: 'Desempeño' },
];

// ── Estado de período y tab ──────────────────────────────────────────────────
const now           = new Date();
const currentPeriod = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
const currentView   = ref('resumen');

// ── Meeting ──────────────────────────────────────────────────────────────────
const {
    meeting, loading: meetingLoading, currentSection, aiSuggestion,
    elapsedTotalSeconds, elapsedSectionSeconds,
    sectionProgress, sectionColor, countdownSeconds,
    nextSection, previousSection, pause, end, loadActive, formatTime,
} = useMeeting();

// ── Auto-switch tab cuando el moderador avanza de sección ───────────────────
watch(() => meeting.value?.current_section_key, newKey => {
    if (newKey) currentView.value = newKey;
});

// ── Historial de actas ───────────────────────────────────────────────────────
const historyOpen = ref(false);

// ── Panel lateral vivo ───────────────────────────────────────────────────────
const panelOpen = ref(false);

// Abrir panel automáticamente cuando la junta pasa a in_progress
watch(() => meeting.value?.status, (status) => {
    if (status === 'in_progress') panelOpen.value = true;
    if (!status || status === 'ended') panelOpen.value = false;
}, { immediate: true });

// ── Setup modal ──────────────────────────────────────────────────────────────
const setupOpen = ref(false);

function onMeetingStarted(data) {
    meeting.value = data;
    panelOpen.value = true;
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
    try {
        confirmEndOpen.value = false;
        const result = await end();
        if (result) {
            summaryData.value = result;
            summaryOpen.value = true;
        }
    } catch (err) {
        console.error('[WarRoom] doEnd falló:', err?.response?.data ?? err);
        alert('No se pudo finalizar la junta. Revisa la consola para más detalles.');
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

/* ── Tabs personalizados (flex-wrap — todos visibles) ─────────────────── */
.warroom-container .wr-tabs-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--wr-border);
    border-radius: 8px 8px 0 0;
    padding: 5px 8px;
}

.warroom-container .wr-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: transparent;
    border: none;
    border-radius: 5px;
    padding: 6px 12px;
    font-size: 12px;
    letter-spacing: 0.3px;
    color: var(--wr-text-muted);
    cursor: pointer;
    white-space: nowrap;
    position: relative;
    transition: color 0.15s, background 0.15s;
}

.warroom-container .wr-tab-btn:hover {
    background: rgba(255,255,255,0.06);
    color: var(--wr-text);
}

/* Indicador: línea inferior en la pestaña activa */
.warroom-container .wr-tab-btn.wr-tab-active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 10px;
    right: 10px;
    height: 2px;
    border-radius: 2px 2px 0 0;
    transition: background 0.2s;
}

/* Acento de color por sección (línea + texto) */
.warroom-container .wr-tab-btn.wr-tab-resumen.wr-tab-active     { color: #c5bfff; }
.warroom-container .wr-tab-btn.wr-tab-resumen.wr-tab-active::after     { background: #8b80f8; }
.warroom-container .wr-tab-btn.wr-tab-finanzas.wr-tab-active    { color: #5ddbb3; }
.warroom-container .wr-tab-btn.wr-tab-finanzas.wr-tab-active::after    { background: #1D9E75; }
.warroom-container .wr-tab-btn.wr-tab-operaciones.wr-tab-active { color: #f5c47a; }
.warroom-container .wr-tab-btn.wr-tab-operaciones.wr-tab-active::after { background: #EF9F27; }
.warroom-container .wr-tab-btn.wr-tab-ventas.wr-tab-active      { color: #80b8f0; }
.warroom-container .wr-tab-btn.wr-tab-ventas.wr-tab-active::after      { background: #4d9ee8; }
.warroom-container .wr-tab-btn.wr-tab-red.wr-tab-active         { color: #5ddbb3; }
.warroom-container .wr-tab-btn.wr-tab-red.wr-tab-active::after         { background: #35c4a0; }
.warroom-container .wr-tab-btn.wr-tab-marketing.wr-tab-active   { color: #f0a3be; }
.warroom-container .wr-tab-btn.wr-tab-marketing.wr-tab-active::after   { background: #e87aaa; }
.warroom-container .wr-tab-btn.wr-tab-talento.wr-tab-active     { color: #f5d47a; }
.warroom-container .wr-tab-btn.wr-tab-talento.wr-tab-active::after     { background: #f5c842; }
.warroom-container .wr-tab-btn.wr-tab-desempeno.wr-tab-active   { color: #a5d4ff; }
.warroom-container .wr-tab-btn.wr-tab-desempeno.wr-tab-active::after   { background: #4da8e0; }

/* ── Body flex (contenido + panel lateral) ───────────────────────────── */
.warroom-container .wr-body {
    display: flex;
    align-items: flex-start;
    gap: 0;
}

.warroom-container .wr-main-content {
    flex: 1;
    min-width: 0;
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
.warroom-container .wr-kpi-grid-4 { grid-template-columns: repeat(4, 1fr); }

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
.warroom-container .wr-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.warroom-container .wr-btn-history {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: var(--wr-text-muted);
    padding: 5px 12px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.15s;
}
.warroom-container .wr-btn-history:hover {
    background: rgba(255,255,255,0.10);
    color: var(--wr-text);
}

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

.warroom-container .wr-btn-end-meeting {
    background: rgba(224,82,82,0.12);
    border: 1px solid rgba(224,82,82,0.4);
    border-radius: 6px;
    color: #e88;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    display: flex; align-items: center;
    transition: all 0.15s;
}
.warroom-container .wr-btn-end-meeting:hover:not(:disabled) {
    background: rgba(224,82,82,0.22);
    color: #ffaaaa;
}
.warroom-container .wr-btn-end-meeting:disabled { opacity: 0.4; cursor: not-allowed; }

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

.warroom-container .wr-meeting-badge-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: background 0.15s, opacity 0.15s;
}

.warroom-container .wr-meeting-badge-btn:hover {
    background: rgba(29,158,117,0.25);
    opacity: 1;
    animation: none;
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
