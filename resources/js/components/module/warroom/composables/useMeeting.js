import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export function useMeeting() {
    const meeting               = ref(null);
    const elapsedTotalSeconds   = ref(0);
    const elapsedSectionSeconds = ref(0);
    const loading               = ref(false);
    const aiSuggestion          = ref(null);

    let timer             = null;
    let suggestionTimer   = null;

    // ── Carga de junta activa ────────────────────────────────────────────────

    async function loadActive() {
        try {
            const { data } = await axios.get('/warroom/api/meetings/active');
            meeting.value = data;
            if (data?.started_at) {
                elapsedTotalSeconds.value = Math.max(
                    0,
                    Math.floor((Date.now() - new Date(data.started_at).getTime()) / 1000)
                );
            }
            // Si current_section_started_at es null, inicia en 0 (timer contará desde aquí)
            elapsedSectionSeconds.value = data?.current_section_started_at
                ? Math.max(0, Math.floor((Date.now() - new Date(data.current_section_started_at).getTime()) / 1000))
                : 0;
        } catch {
            meeting.value = null;
        }
    }

    // ── Timer ────────────────────────────────────────────────────────────────

    function startTimer() {
        if (timer) clearInterval(timer);
        timer = setInterval(() => {
            if (meeting.value?.status === 'in_progress') {
                elapsedTotalSeconds.value++;
                elapsedSectionSeconds.value++;
            }
        }, 1000);
    }

    // ── Polling sugerencias IA ───────────────────────────────────────────────

    function startSuggestionPolling() {
        if (suggestionTimer) clearInterval(suggestionTimer);
        suggestionTimer = setInterval(async () => {
            if (!meeting.value?.id || meeting.value.status !== 'in_progress') return;
            try {
                const { data } = await axios.get(`/warroom/api/meetings/${meeting.value.id}/suggestion`);
                aiSuggestion.value = data.suggestion || null;
            } catch { /* silent */ }
        }, 30_000);
    }

    // ── Controles de sección ─────────────────────────────────────────────────

    function resetSectionClock(meetingData) {
        const ts = meetingData?.current_section_started_at;
        elapsedSectionSeconds.value = ts
            ? Math.max(0, Math.floor((Date.now() - new Date(ts).getTime()) / 1000))
            : 0;
    }

    async function nextSection() {
        if (!meeting.value) return;
        loading.value = true;
        try {
            const { data } = await axios.post(`/warroom/api/meetings/${meeting.value.id}/section/next`);
            if (data.meeting) {
                meeting.value = data.meeting;
                if (data.summary) return data; // junta finalizada automáticamente
                resetSectionClock(data.meeting);
            } else {
                meeting.value = data;
                resetSectionClock(data);
            }
            aiSuggestion.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function previousSection() {
        if (!meeting.value) return;
        loading.value = true;
        try {
            const { data } = await axios.post(`/warroom/api/meetings/${meeting.value.id}/section/previous`);
            meeting.value = data;
            resetSectionClock(data);
            aiSuggestion.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function pause() {
        if (!meeting.value) return;
        const { data } = await axios.post(`/warroom/api/meetings/${meeting.value.id}/pause`);
        meeting.value = data;
    }

    async function resume() {
        if (!meeting.value) return;
        const { data } = await axios.post(`/warroom/api/meetings/${meeting.value.id}/resume`);
        meeting.value = data;
    }

    async function end() {
        if (!meeting.value?.id) {
            console.error('[useMeeting] end() sin meeting activo', meeting.value);
            return;
        }
        loading.value = true;
        try {
            const { data } = await axios.post(`/warroom/api/meetings/${meeting.value.id}/end`);
            meeting.value = null;
            aiSuggestion.value = null;
            elapsedTotalSeconds.value   = 0;
            elapsedSectionSeconds.value = 0;
            return data; // { meeting, summary }
        } catch (err) {
            console.error('[WarRoom] Error al finalizar junta:', err?.response?.data ?? err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // ── Computeds ────────────────────────────────────────────────────────────

    const currentSection = computed(() => {
        if (!meeting.value?.current_section_key) return null;
        return meeting.value.sections?.find(
            s => s.section_key === meeting.value.current_section_key
        ) ?? null;
    });

    const sectionProgress = computed(() => {
        if (!currentSection.value || currentSection.value.time_planned_seconds <= 0) return 0;
        return Math.min(
            120, // permite hasta 120% para mostrar overflow
            (elapsedSectionSeconds.value / currentSection.value.time_planned_seconds) * 100
        );
    });

    const sectionColor = computed(() => {
        const p = sectionProgress.value;
        if (p < 80)  return 'positive';
        if (p < 100) return 'warning';
        return 'negative';
    });

    // Cuenta regresiva total: positivo = tiempo restante, negativo = excedente
    const countdownSeconds = computed(() => {
        if (!meeting.value?.sections) return 0;
        const totalPlanned = meeting.value.sections.reduce(
            (sum, s) => sum + (s.time_planned_seconds ?? 0), 0
        );
        return totalPlanned - elapsedTotalSeconds.value;
    });

    const etaEnd = computed(() => {
        if (!meeting.value?.sections) return null;
        const totalPlanned = meeting.value.sections.reduce(
            (sum, s) => sum + (s.time_planned_seconds ?? 0), 0
        );
        const startMs = new Date(meeting.value.started_at).getTime();
        const etaMs   = startMs + totalPlanned * 1000;
        const etaDate = new Date(etaMs);
        return etaDate.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    });

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    onMounted(async () => {
        await loadActive();
        startTimer();
        startSuggestionPolling();
    });

    onUnmounted(() => {
        if (timer)           clearInterval(timer);
        if (suggestionTimer) clearInterval(suggestionTimer);
    });

    return {
        meeting, loading, currentSection, aiSuggestion,
        elapsedTotalSeconds, elapsedSectionSeconds,
        sectionProgress, sectionColor, countdownSeconds, etaEnd,
        nextSection, previousSection, pause, resume, end, loadActive, formatTime,
    };
}
