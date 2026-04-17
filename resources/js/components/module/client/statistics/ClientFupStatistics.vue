<template>
    <q-card flat bordered class="q-mt-md border-radius-lg shadow-1">
        <!-- Header -->
        <q-card-section class="items-center q-py-sm bg-white d-flex justify-content-start">
            <q-icon name="info_outline" color="grey-8" size="20px" class="q-mr-sm"/>
            <div class="text-subtitle2 text-weight-bold text-grey-9">Estadísticas de FUP</div>
        </q-card-section>

        <q-separator/>

        <!-- Tabla -->
        <q-card-section class="q-pa-none">
            <q-table
                :rows="rows"
                :columns="columns"
                row-key="periodo"
                flat
                dense
                hide-pagination
                :loading="loading"
                class="fup-table"
            >
                <!-- Formato para celdas de Tráfico -->
                <template v-slot:body-cell-down="props">
                    <q-td :props="props">
                        {{ props.value }} / 0.00
                    </q-td>
                </template>

                <template v-slot:body-cell-up="props">
                    <q-td :props="props">
                        {{ props.value }} / 0.00
                    </q-td>
                </template>
            </q-table>
        </q-card-section>
    </q-card>
</template>

<script>
import {ref, onMounted} from 'vue';
import axios from 'axios';

export default {
    name: 'ClientFupStatistics',
    props: ['clientId'],
    setup(props) {
        const loading = ref(false);
        const rows = ref([]);

        const columns = [
            {name: 'periodo', label: '', field: 'periodo', align: 'left', classes: 'text-weight-bold'},
            {name: 'down', label: 'Tráfico / Descarga adicional MB', field: 'down', align: 'left'},
            {name: 'up', label: 'Tráfico / Subida de bonificación (MB)', field: 'up', align: 'left'},
            {name: 'time', label: 'Tiempo en línea', field: 'time', align: 'right'},
        ];

        const fetchFupData = async () => {
            loading.value = true;
            try {
                const res = await axios.get(`/cliente/statistics/get-fup-stats/${props.clientId}`);
                if (res.data.success) {
                    rows.value = res.data.data;
                }
            } catch (e) {
                console.error("Error cargando FUP", e);
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchFupData);

        return {rows, columns, loading};
    }
};
</script>

<style scoped>
.border-radius-lg {
    border-radius: 8px;
}

.fup-table :deep(thead tr th) {
    font-weight: bold;
    color: #333;
    border-bottom-width: 1px;
}

.fup-table :deep(tbody tr td) {
    height: 40px;
}

/* Estilo para la primera columna de etiquetas */
.fup-table :deep(tbody tr td:first-child) {
    background-color: #fdfdfd;
    width: 100px;
}
</style>
