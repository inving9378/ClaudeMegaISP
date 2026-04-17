<template>
    <chart-card title="Actividades recientes" :padding="padding">
        <template #chart>
            <div id="activities" style="min-height: 390px; overflow-y: auto">
                <div class="overflow-hidden">
                    <div class="card-body px-0">
                        <div
                            class="px-3"
                            data-simplebar
                            style="height: 390px; overflow-y: auto"
                        >
                            <ul class="list-unstyled activity-wid mb-0">
                                <li
                                    v-for="(item, key) in data"
                                    :key="`activity-${item.id}`"
                                    :class="`activity-list ${
                                        data.length - 1 != key
                                            ? 'activity-border'
                                            : ''
                                    }`"
                                >
                                    <div class="activity-icon avatar-md">
                                        <span
                                            class="avatar-title bg-soft-warning text-warning rounded-circle"
                                        >
                                            <i
                                                class="bx bx-bitcoin font-size-24"
                                            ></i>
                                        </span>
                                    </div>
                                    <div class="timeline-list-item">
                                        <div class="d-flex">
                                            <div
                                                class="flex-grow-1 overflow-hidden me-4"
                                            >
                                                <h5 class="font-size-14 mb-1">
                                                    {{ item.date }}
                                                </h5>
                                                <p
                                                    class="text-truncate text-muted font-size-13"
                                                >
                                                    {{ item.text }}
                                                </p>
                                            </div>
                                            <div
                                                class="flex-shrink-0 text-end me-3"
                                            ></div>

                                            <div
                                                class="flex-shrink-0 text-end"
                                            ></div>
                                        </div>
                                    </div>
                                </li>
                                <li v-if="data.length === 0 && !showLoading">
                                    <div
                                        class="alert alert-warning"
                                        role="alert"
                                    >
                                        ¡No hay actividades para mostrar!
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <q-inner-loading :showing="showLoading" color="primary" />
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { defineProps, onMounted, ref } from "vue";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { getActivities } from "../statistics/helper/request";

const props = defineProps({
    user: {
        type: Number,
        default: null,
    },
    padding: String,
});

const data = ref([]);
const showLoading = ref(false);

onMounted(() => {
    getData();
});

const getData = async () => {
    showLoading.value = true;
    let response = await getActivities(props.user);
    data.value = response ? response : [];
    showLoading.value = false;
};
</script>
