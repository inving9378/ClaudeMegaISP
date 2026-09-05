<template>
    <q-btn
        :padding="padding"
        :round="round"
        :size="size"
        :color="show ? 'dark' : 'primary'"
        :icon="show ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        @click.stop="
            () => {
                show = !show;
                disableConnections(ports, show);
            }
        "
        v-if="avaiable"
        ><q-tooltip
            >{{ show ? "Ocultar" : "Mostrar" }}
            {{ ports.length > 1 ? "conexiones" : "conexión" }}</q-tooltip
        ></q-btn
    >
    <q-btn
        :padding="padding"
        :round="round"
        :size="size"
        disable
        color="dark"
        icon="mdi-eye-off-outline"
        v-else
    />
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import {
    disableConnections,
    isAvaiableConnection,
    connections,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "ChangeConnectionsState",
});

const props = defineProps({
    ports: {
        type: Array,
        defaul: [],
    },
    size: {
        type: String,
        default: "sm",
    },
    round: {
        type: Boolean,
        default: true,
    },
    padding: String,
});

const show = ref(true);

onMounted(() => {
    updateState();
});

watch(
    connections,
    (n) => {
        updateState();
    },
    {
        deep: true,
    }
);

const updateState = () => {
    const visibles = connections.value.filter((c) => c.visible);
    if (visibles.length === 0) {
        show.value = false;
    } else {
        let v = 0;
        for (let i = 0; i < props.ports.length; i++) {
            const port = props.ports[i];
            const conn = visibles.find(
                (c) =>
                    (c.from_id === port.id &&
                        c.from_type === port.model_type) ||
                    (c.to_id === port.id && c.to_type === port.model_type)
            );
            if (conn) {
                v++;
            }
        }
        if (v === props.ports.filter((p) => !isAvaiableConnection(p)).length) {
            show.value = true;
        } else {
            show.value = false;
        }
    }
};

const avaiable = computed(() => {
    for (let i = 0; i < props.ports.length; i++) {
        if (!isAvaiableConnection(props.ports[i])) {
            return true;
        }
    }
    return false;
});
</script>
