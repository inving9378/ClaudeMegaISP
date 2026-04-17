<template>
    <q-card class="olt-card q-mt-md">
        <q-item>
            <q-item-section>
                <q-item-label class="text-h6"> Estado ONUs </q-item-label>
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
                    v-for="olt in olts"
                    :key="olt.id"
                    class="col-xs-12 col-sm-6 col-md-3 col-lg-4 col-xl-4"
                >
                    <q-card flat bordered class="my-card">
                        <q-item>
                            <q-item-section avatar>
                                <q-icon
                                    name="router"
                                    color="primary"
                                    size="md"
                                />
                            </q-item-section>

                            <q-item-section>
                                <q-item-label class="text-bold">{{
                                    olt.name
                                }}</q-item-label>
                            </q-item-section>

                            <q-item-section side>
                                <q-badge
                                    :color="getHealthColor(olt)"
                                    class="q-pa-xs"
                                >
                                    {{ calculateEfficiency(olt) }}% Online
                                </q-badge>
                            </q-item-section>
                        </q-item>

                        <q-separator />
                        <q-card-section>
                            <div class="row q-mb-sm items-center">
                                <div class="col-12">
                                    <q-linear-progress
                                        size="15px"
                                        :value="
                                            olt.online_count / olt.total_onus
                                        "
                                        color="positive"
                                        track-color="negative"
                                        class="rounded-borders"
                                    />
                                </div>
                            </div>

                            <div class="row text-center q-mt-md">
                                <div class="col">
                                    <div class="text-caption text-grey-7">
                                        TOTAL
                                    </div>
                                    <div class="text-h6 text-weight-medium">
                                        {{ olt.total_onus }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-positive text-weight-bold"
                                    >
                                        ONLINE
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.online_count }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-negative text-weight-bold"
                                    >
                                        OFFLINE
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.offline_count }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-negative text-weight-bold"
                                    >
                                        POWER FAIL
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.power_fail_count }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-warning text-weight-bold"
                                    >
                                        PENDIENTE
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.unconfigured_count }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-brown text-weight-bold"
                                    >
                                        LOS
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.los_count }}
                                    </div>
                                </div>

                                <q-separator vertical inset />

                                <div class="col">
                                    <div
                                        class="text-caption text-grey text-weight-bold"
                                    >
                                        NO DEFINIDO
                                    </div>
                                    <div class="text-h6">
                                        {{ olt.undefined_count }}
                                    </div>
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
import { ref, onMounted, onUnmounted } from "vue";
import { getOLTData } from "../../helper/request";

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
    const result = await getOLTData("/olts/dashboard-onus-status", { force });
    if (result) {
        olts.value = result;
    }
    loading.value = false;
};
const calculateEfficiency = (olt) => {
    if (!olt.total_onus) return 0;
    return ((olt.online_count / olt.total_onus) * 100).toFixed(1);
};

const getHealthColor = (olt) => {
    const percent = (olt.online_count / olt.total_onus) * 100;
    if (percent > 90) return "positive";
    if (percent > 70) return "warning";
    return "negative";
};
</script>

<style scoped>
.olt-card {
    transition: transform 0.2s ease-in-out;
    border-radius: 12px;
}
.olt-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
</style>
