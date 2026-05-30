import { ref } from 'vue';
import axios from 'axios';

export function useKpis(view) {
    const kpis    = ref(null);
    const loading = ref(false);
    const error   = ref(null);

    async function fetchKpis(period) {
        loading.value = true;
        error.value   = null;
        try {
            const url = period
                ? `/warroom/api/kpis/${view}/${period}`
                : `/warroom/api/kpis/${view}`;
            const { data } = await axios.get(url);
            kpis.value = data;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Error al cargar KPIs';
        } finally {
            loading.value = false;
        }
    }

    return { kpis, loading, error, fetchKpis };
}
