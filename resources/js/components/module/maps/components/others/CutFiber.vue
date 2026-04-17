<template>
    <q-btn
        :padding="padding"
        :round="round"
        :size="size"
        color="danger"
        icon="content_cut"
        @click.stop="showConfirm = true"
        v-if="hasEnable"
        ><q-tooltip
            >Cortar
            {{ ports.length > 1 ? "conexiones" : "conexión" }}</q-tooltip
        ></q-btn
    ><q-btn
        :padding="padding"
        :round="round"
        :size="size"
        color="danger"
        icon="content_cut"
        disable
        v-else
    />

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas realizar este corte"
        @yes="cut"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { ref, computed } from "vue";
import { cutConnections } from "../../helper/connections-request";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import DialogConfirm from "./DialogConfirm.vue";
import {
    getConnectionByPort,
    getCounterpart,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "CutFiber",
});

const props = defineProps({
    layer: Object,
    route: Object,
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

const emits = defineEmits(["update"]);

const showConfirm = ref(false);

const hasEnable = computed(() => {
    const ports = props.ports;
    const allCuts =
        ports.filter((p) => p.current_cut !== null).length >= ports.length;
    const existConnections =
        ports.filter((p) => getConnectionByPort(p) !== null).length > 0;
    const counterpart = getCounterpart(props.route);
    return existConnections || (!allCuts && counterpart);
});

const cut = async () => {
    showLoading();
    const cuts = [];
    const removed = [];
    const updated = [];
    const counterpart = getCounterpart(props.route);

    props.ports.forEach((p) => {
        const connected = getConnectionByPort(p);
        if (connected && connected.id !== null) {
            removed.push(connected.id);
        }
        if (counterpart) {
            if (!p.current_cut) {
                cuts.push({
                    fiber_id: p.id,
                    current_input: p.current_input,
                    state: "open",
                    layer_id: props.layer.id,
                    route_id: props.route.route_id,
                });
            } else {
                updated.push(p.current_cut.id);
            }
        }
    });
    if (counterpart) {
        const ports = counterpart.fibers;
        const ids = cuts.map((f) => f.fiber_id);
        ports.forEach((p) => {
            if (ids.includes(p.id)) {
                if (!p.current_cut) {
                    let state = "open";
                    const connected = getConnectionByPort(p);
                    if (connected) {
                        const connected1 = getConnectionByPort(
                            props.ports.find((p1) => p1.id === p.id)
                        );
                        if (connected1) {
                            state =
                                connected1.id === connected.id
                                    ? "open"
                                    : "close";
                        } else {
                            state = "close";
                        }
                    }
                    cuts.push({
                        fiber_id: p.id,
                        current_input: p.current_input,
                        state,
                        layer_id: props.layer.id,
                        route_id: counterpart.route_id,
                    });
                }
            }
        });
    }
    let result = await cutConnections(props.layer.id, cuts, removed, updated);
    hideLoading();
    if (result) {
        emits("update", result);
        if (cuts.length > 0 && removed.length > 0) {
            message("Cortes/Conexiones creados correctamente");
        } else if (cuts.length > 0) {
            message("Cortes creados correctamente");
        } else {
            message("Conexiones eliminadas correctamente");
        }
    } else {
        message("No se ha podido realizar la operación solicitada", "error");
    }
    showConfirm.value = false;
};
</script>
