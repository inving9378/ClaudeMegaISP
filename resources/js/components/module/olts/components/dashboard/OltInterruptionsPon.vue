<template>
    <q-card class="q-mt-md">
        <q-item>
            <q-item-section>
                <q-item-label class="text-h6">
                    Interrupciones PONs
                </q-item-label>
            </q-item-section>
            <q-item-section avatar>
                <q-btn-dropdown
                    color="primary"
                    label="Actualizción"
                    no-caps
                    split
                    :loading="loading"
                    @click="() => loadData(false)"
                >
                    <q-list dense>
                        <q-item clickable @click="() => loadData(false)">
                            <q-item-section>
                                <q-item-label>Local</q-item-label>
                            </q-item-section>
                        </q-item>
                        <q-item clickable @click="() => loadData(true)">
                            <q-item-section>
                                <q-item-label> Desde API SmartOLT</q-item-label>
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-btn-dropdown>
            </q-item-section>
        </q-item>
        <q-separator />
        <q-item-section class="q-pa-md">
            <template v-if="loading && objects.length === 0">
                <q-markup-table>
                    <thead>
                        <tr>
                            <th class="text-left">
                                <q-skeleton animation="blink" type="text" />
                            </th>
                            <th class="text-right">
                                <q-skeleton animation="blink" type="text" />
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="n in 3" :key="n">
                            <td class="text-left">
                                <q-skeleton animation="blink" type="text" />
                            </td>
                            <td class="text-right">
                                <q-skeleton animation="blink" type="text" />
                            </td>
                        </tr>
                    </tbody>
                </q-markup-table>
            </template>
            <q-list separator v-else>
                <q-item
                    v-for="obj in objects"
                    :key="`interruption-olt-${obj.id}`"
                >
                    <q-item-section avatar>
                        <q-icon
                            name="report_problem"
                            :color="
                                obj.interruption_count > 0
                                    ? 'warning'
                                    : 'grey-4'
                            "
                        />
                    </q-item-section>

                    <q-item-section>
                        <q-item-label>{{ obj.name }}</q-item-label>
                        <q-item-label caption
                            >Sincronizado: {{ obj.last_sync }}</q-item-label
                        >
                    </q-item-section>

                    <q-item-section side>
                        <q-badge
                            :color="
                                obj.interruption_count > 0
                                    ? 'negative'
                                    : 'positive'
                            "
                            :outline="obj.interruption_count === 0"
                        >
                            {{ obj.interruption_count }} PON caídos
                        </q-badge>
                    </q-item-section>
                </q-item>

                <q-item
                    v-if="objects.length === 0 && !loading"
                    class="text-center text-grey"
                >
                    <q-item-section>No hay datos disponibles</q-item-section>
                </q-item>
            </q-list>
        </q-item-section>
    </q-card>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { getOLTData } from "../../helper/request";

defineOptions({
    name: "OltInterruptionsPon",
});

const objects = ref([]);
const loading = ref(false);
let timer;

onMounted(() => {
    loadData();
    timer = setInterval(loadData, 60000);
});

onUnmounted(() => {
    clearInterval(timer);
});

const loadData = async (force = false) => {
    loading.value = true;
    const result = await getOLTData("olt-dashboard-interruptions", { force });
    if (result) {
        objects.value = result;
    }
    loading.value = false;
};
</script>
