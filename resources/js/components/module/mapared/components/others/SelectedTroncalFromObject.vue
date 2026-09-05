<template>
    <q-list dense separator>
        <q-item v-for="t in routes" :key="`route-${t.route}-input-${t.input}`"
            ><q-item-section>
                <q-item-label>
                    {{ t.text }} (Entrada {{ t.input }})
                </q-item-label>
                <q-item-label caption class="text-bold text-center q-pt-xs">
                    Distancia
                </q-item-label>
                <q-item-label caption>
                    Total:
                    {{ t.total_distance }} m</q-item-label
                >
                <q-item-label caption>
                    Calculada:
                    {{ t.calculate_distance }}
                    m</q-item-label
                ><q-item-label caption>
                    Real:
                    {{ t.real_distance }} m</q-item-label
                >
            </q-item-section>
            <q-item-section avatar side>
                <q-btn
                    round
                    size="sm"
                    color="danger"
                    icon="delete"
                    @click="emits('destroy', t.input)"
                />
            </q-item-section>
        </q-item>
    </q-list>
</template>

<script setup>
defineOptions({
    name: "SelectedTroncalFromObject",
});

const props = defineProps({
    routes: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["destroy"]);
</script>
