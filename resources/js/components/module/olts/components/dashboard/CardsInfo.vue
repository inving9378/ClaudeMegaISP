<template>
    <q-card class="bg-transparent" flat>
        <q-card-section class="q-pa-none">
            <div class="row q-col-gutter-sm">
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <q-list
                        dense
                        :style="{
                            border: `1px solid ${getCssVar(
                                'primary'
                            )} !important`,
                        }"
                    >
                        <q-item
                            class="bg-primary"
                            clickable
                            @click="() => emits('change-tab', 'unconfigured')"
                        >
                            <q-item-section side class="q-mr-md text-white">
                                <q-icon
                                    name="fas fa-magic"
                                    color="white"
                                    size="44px"
                                ></q-icon>
                            </q-item-section>
                            <q-item-section />
                            <q-item-section
                                avatar
                                class="q-pa-md q-ml-none text-white"
                            >
                                <q-item-label
                                    class="text-white text-h6 text-weight-bolder"
                                    >{{ data.waiting ?? 0 }}</q-item-label
                                >
                                <q-item-label
                                    >Esperando autorización</q-item-label
                                >
                            </q-item-section>
                        </q-item>
                        <q-item style="padding: 0">
                            <q-item-section>
                                <q-item-label class="text-center text-primary"
                                    >D:0</q-item-label
                                >
                            </q-item-section>
                            <q-item-section>
                                <q-item-label class="text-center text-primary"
                                    >Resync:0</q-item-label
                                >
                            </q-item-section>
                            <q-item-section>
                                <q-item-label class="text-center text-primary"
                                    >New: {{ data.waiting ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                        </q-item>
                    </q-list>
                    <q-inner-loading :showing="loading" color="primary" />
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <q-list
                        dense
                        :style="{
                            border: `1px solid ${getCssVar(
                                'positive'
                            )} !important`,
                        }"
                    >
                        <q-item
                            class="bg-positive"
                            clickable
                            @click="
                                () =>
                                    emits('change-tab', 'configured', {
                                        status: 'Online',
                                    })
                            "
                        >
                            <q-item-section side class="q-mr-md text-white">
                                <q-icon
                                    name="mdi-dip-switch fa-rotate-90"
                                    color="white"
                                    size="44px"
                                ></q-icon>
                            </q-item-section>
                            <q-item-section />
                            <q-item-section
                                avatar
                                class="q-pa-md q-ml-none text-white"
                            >
                                <q-item-label
                                    class="text-white text-h6 text-weight-bolder"
                                    >{{ data.online ?? 0 }}</q-item-label
                                >
                                <q-item-label>En línea</q-item-label>
                            </q-item-section>
                        </q-item>
                        <q-item
                            style="padding: 0"
                            clickable
                            @click="() => emits('change-tab', 'configured')"
                        >
                            <q-item-section>
                                <q-item-label class="text-center text-positive"
                                    >Total autorizadas:
                                    {{ totalAutorized }}</q-item-label
                                >
                            </q-item-section>
                        </q-item>
                    </q-list>
                    <q-inner-loading :showing="loading" color="primary" />
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <q-list
                        dense
                        :style="{
                            border: `1px solid ${getCssVar('dark')} !important`,
                        }"
                    >
                        <q-item
                            class="bg-dark"
                            clickable
                            @click="
                                () =>
                                    emits('change-tab', 'configured', {
                                        status: 'Offline',
                                    })
                            "
                        >
                            <q-item-section side class="q-mr-md text-white">
                                <q-icon
                                    name="mdi-close-thick"
                                    color="white"
                                    size="44px"
                                ></q-icon>
                            </q-item-section>
                            <q-item-section />
                            <q-item-section
                                avatar
                                class="q-pa-md q-ml-none text-white"
                            >
                                <q-item-label
                                    class="text-white text-h6 text-weight-bolder"
                                    >{{ totalOffline }}</q-item-label
                                >
                                <q-item-label>Fuera de línea</q-item-label>
                            </q-item-section>
                        </q-item>
                        <q-item style="padding: 0">
                            <q-item-section>
                                <q-item-label
                                    class="text-center text-dark cursor-pointer"
                                    @click="
                                        () =>
                                            emits('change-tab', 'configured', {
                                                status: 'Power fail',
                                            })
                                    "
                                    >PwrFail:
                                    {{ data.powerfail ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                            <q-item-section>
                                <q-item-label
                                    class="text-center text-dark cursor-pointer"
                                    @click="
                                        () =>
                                            emits('change-tab', 'configured', {
                                                status: 'LOS',
                                            })
                                    "
                                    >LoS: {{ data.los ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                            <q-item-section>
                                <q-item-label
                                    class="text-center text-dark cursor-pointer"
                                    @click="
                                        () =>
                                            emits('change-tab', 'configured', {
                                                status: 'Offline',
                                            })
                                    "
                                    >N/A: {{ data.offline ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                        </q-item>
                    </q-list>
                    <q-inner-loading :showing="loading" color="primary" />
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <q-list
                        dense
                        :style="{
                            border: `1px solid ${getCssVar(
                                'warning'
                            )} !important`,
                        }"
                    >
                        <q-item
                            class="bg-warning"
                            clickable
                            @click="
                                () =>
                                    emits('change-tab', 'diagnostics', {
                                        signal: ['Warning', 'Critical'],
                                    })
                            "
                        >
                            <q-item-section side class="q-mr-md text-white">
                                <q-icon
                                    name="mdi-close-thick"
                                    color="white"
                                    size="44px"
                                ></q-icon>
                            </q-item-section>
                            <q-item-section />
                            <q-item-section
                                avatar
                                class="q-pa-md q-ml-none text-white"
                            >
                                <q-item-label
                                    class="text-white text-h6 text-weight-bolder"
                                    >{{ badSignals }}</q-item-label
                                >
                                <q-item-label>Señales malas</q-item-label>
                            </q-item-section>
                        </q-item>
                        <q-item style="padding: 0">
                            <q-item-section>
                                <q-item-label
                                    class="text-center text-warning cursor-pointer"
                                    @click="
                                        () =>
                                            emits('change-tab', 'diagnostics', {
                                                signal: 'Warning',
                                            })
                                    "
                                    >Warning:
                                    {{ data.warning ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                            <q-item-section>
                                <q-item-label
                                    class="text-center text-warning cursor-pointer"
                                    @click="
                                        () =>
                                            emits('change-tab', 'diagnostics', {
                                                signal: 'Critical',
                                            })
                                    "
                                    >Critical:
                                    {{ data.critical ?? 0 }}</q-item-label
                                >
                            </q-item-section>
                        </q-item>
                    </q-list>
                    <q-inner-loading :showing="loading" color="primary" />
                </div>
            </div>
        </q-card-section>
    </q-card>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { getCssVar } from "../../../../../../../public/plugins/quasar/js/quasar.umd.prod";
import { getOLTData } from "../../helper/request";

defineOptions({
    name: "CardsInfo",
});

const props = defineProps({
    oltId: Number,
});

const emits = defineEmits(["change-tab"]);

const loading = ref(false);
let timer;

const data = ref({
    critical: 0,
    los: 0,
    na: 0,
    offline: 0,
    online: 0,
    powerfail: 0,
    waiting: 0,
    warning: 0,
});

onMounted(() => {
    loadData();
    timer = setInterval(loadData, 60000);
});

onUnmounted(() => {
    clearInterval(timer);
});

watch(
    () => props.oltId,
    () => {
        loadData();
    }
);

const totalAutorized = computed(() => {
    return (data.value.online ?? 0) + totalOffline.value + (data.value.na ?? 0);
});

const badSignals = computed(() => {
    return (data.value.warning ?? 0) + (data.value.critical ?? 0);
});

const totalOffline = computed(() => {
    return (
        (data.value.offline ?? 0) +
        (data.value.los ?? 0) +
        (data.value.powerfail ?? 0)
    );
});

const loadData = async (force = false) => {
    loading.value = true;
    const result = await getOLTData(
        props.oltId
            ? `/olts/dashboard-onus-status/${props.oltId}`
            : "/olts/dashboard-onus-status",
        { force }
    );
    if (result) {
        data.value = result;
    }
    loading.value = false;
};
</script>
