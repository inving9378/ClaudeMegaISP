import { ref } from 'vue';
import axios from 'axios';

export function useInsights(view) {
    const insights = ref([]);
    const loading  = ref(false);
    const source   = ref(null);
    const status   = ref('ready'); // 'ready' | 'generating'

    let pollTimer = null;

    async function fetchInsights(period) {
        clearTimeout(pollTimer);
        loading.value = true;
        try {
            const url = period
                ? `/warroom/api/insights/${view}/${period}`
                : `/warroom/api/insights/${view}`;
            const { data } = await axios.get(url);
            insights.value = data.insights ?? [];
            source.value   = data.source;
            status.value   = data.status ?? 'ready';

            // Si está generando en background, reintentar una vez en 8s
            if (status.value === 'generating') {
                pollTimer = setTimeout(() => fetchInsights(period), 8000);
            }
        } catch {
            insights.value = [];
            status.value   = 'ready';
        } finally {
            loading.value = false;
        }
    }

    return { insights, loading, source, status, fetchInsights };
}
