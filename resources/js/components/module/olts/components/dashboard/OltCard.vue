<template>
    <q-card>
        <q-item>
            <q-item-section>
                <q-item-label class="text-h6"> Estado de OLTs </q-item-label>
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
            <template v-if="loading && olts.length === 0">
                <div class="row q-col-gutter-sm">
                    <div
                        v-for="s in 3"
                        :key="s"
                        class="col-xs-12 col-sm-6 col-md-3 col-lg-4 col-xl-4"
                    >
                        <q-skeleton type="QInput" />
                    </div>
                </div>
            </template>

            <div class="row q-col-gutter-sm" v-else>
                <div
                    v-for="object in olts"
                    :key="object.id"
                    class="col-xs-12 col-sm-6 col-md-3 col-lg-4 col-xl-4"
                >
                    <q-card flat bordered class="my-card">
                        <q-item>
                            <q-item-section avatar>
                                <q-icon
                                    name="router"
                                    :color="object.env_temp_cls"
                                    size="md"
                                />
                            </q-item-section>

                            <q-item-section>
                                <q-item-label class="text-bold">{{
                                    object.name
                                }}</q-item-label>
                                <q-item-label caption>{{
                                    object.ip
                                }}</q-item-label>
                            </q-item-section>

                            <q-item-section side>
                                <div
                                    class="text-subtitle1 text-bold"
                                    :class="`text-${object.env_temp_cls}`"
                                >
                                    {{ object.env_temp }}
                                </div>
                            </q-item-section>

                            <q-item-section side>
                                <q-badge
                                    :color="object.active ? 'positive' : 'grey'"
                                >
                                    Active
                                </q-badge>
                            </q-item-section>
                        </q-item>

                        <q-separator />

                        <q-card-section class="row text-center">
                            <div class="col">
                                <div class="text-caption text-grey">
                                    Tiempo encendida
                                </div>
                                <div class="text-subtitle3">
                                    {{ object.uptime }}
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-caption text-grey">
                                    &Uacute;ltima sincronización
                                </div>
                                <div class="text-subtitle3">
                                    {{ object.last_synced_at_humans }}
                                </div>
                            </div>
                        </q-card-section>
                    </q-card>
                </div>
            </div>
        </q-item-section>
    </q-card>
</template>

<script setup>
import moment from "moment/moment";
import { onMounted, onUnmounted, ref } from "vue";
import { getOLTData, getOLTs } from "../../helper/request";

defineOptions({
    name: "OltCard",
});

const olts = ref([]);
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
    const result = await getOLTs({ force });
    if (result) {
        olts.value = result.rows;
    }
    loading.value = false;
};
</script>
