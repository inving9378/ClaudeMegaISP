import { ref } from 'vue';
import axios from 'axios';

export function useInsights(view) {
    const insights = ref([]);
    const loading  = ref(false);
    const source   = ref(null);

    async function fetchInsights(period) {
        loading.value = true;
        try {
            const url = period
                ? `/warroom/api/insights/${view}/${period}`
                : `/warroom/api/insights/${view}`;
            const { data } = await axios.get(url);
            insights.value = data.insights ?? [];
            source.value   = data.source;
        } catch {
            insights.value = [];
        } finally {
            loading.value = false;
        }
    }

    return { insights, loading, source, fetchInsights };
}
