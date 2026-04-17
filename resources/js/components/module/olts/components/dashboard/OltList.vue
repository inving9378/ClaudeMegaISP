<template>
    <q-list bordered>
        <q-item class="bg-dark text-white">
            <q-item-section avatar>
                <q-icon name="mdi-server-network" class="q-mr-sm" />
            </q-item-section>
            <q-item-section> OLTs </q-item-section>
            <q-item-section avatar>
                <q-btn flat no-caps>
                    {{ current?.name ?? "Todas" }}
                    <q-icon
                        :name="
                            menu
                                ? 'mdi-menu-up-outline'
                                : 'mdi-menu-down-outline'
                        "
                    />
                    <q-menu v-model="menu" style="width: 200px">
                        <q-list dense>
                            <q-item clickable @click="onChange(null)">
                                <q-item-section>
                                    <q-item-label> Todas </q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item
                                v-for="o in olts"
                                :key="`olt-${o.id}`"
                                clickable
                                :class="{
                                    'text-primary': o.id === current?.id,
                                }"
                                @click="onChange(o.id)"
                            >
                                <q-item-section>
                                    <q-item-label lines="1">
                                        {{ o.id }} - {{ o.name }}
                                    </q-item-label>
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </q-menu>
                </q-btn>
            </q-item-section>
        </q-item>
        <q-list bordered separator class="q-ma-md">
            <template v-if="current">
                <q-item>
                    <q-item-section avatar>
                        <q-icon name="mdi-cogs" class="q-mr-xs" />
                    </q-item-section>
                    <q-item-section>
                        <q-item-label>
                            {{ current.name }}
                        </q-item-label>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-item-label>
                            {{ current.uptime }},
                            <span :class="`text-${current.env_temp_cls}`">{{
                                current.env_temp
                            }}</span>
                        </q-item-label>
                    </q-item-section>
                </q-item>
            </template>
            <template v-else>
                <q-item
                    v-for="o in olts"
                    :key="`olt-${o.id}`"
                    clickable
                    @click="onChange(o.id)"
                >
                    <q-item-section avatar>
                        <q-icon name="mdi-cogs" class="q-mr-xs" />
                    </q-item-section>
                    <q-item-section>
                        <q-item-label>
                            {{ o.name }}
                        </q-item-label>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-item-label>
                            {{ o.uptime }},
                            <span :class="`text-${o.env_temp_cls}`">{{
                                o.env_temp
                            }}</span>
                        </q-item-label>
                    </q-item-section>
                </q-item>
            </template>
        </q-list>
    </q-list>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { getOLTs } from "../../helper/request";
import { message } from "../../../../../helpers/toastMsg";

defineOptions({
    name: "OltList",
});

const emits = defineEmits(["change"]);

const olts = ref([]);

const loading = ref(false);
const current = ref(null);
const menu = ref(false);
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
    if (result.success) {
        olts.value = result.rows;
    } else {
        message(result.message, "error");
    }
    loading.value = false;
};

const onChange = (val) => {
    current.value = val ? olts.value.find((o) => o.id === val) : null;
    emits("change", val);
};
</script>
