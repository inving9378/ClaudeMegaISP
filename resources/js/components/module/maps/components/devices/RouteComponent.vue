<template>
    <q-list
        dense
        class="map-device dense q-pa-xs q-my-xs shadow-3 bg-white"
        :style="{
            left: route.position_x + 'px',
            top: route.position_y + 'px',
        }"
        :id="`polyline-${route.route_id}`"
    >
        <q-item
            class="bg-grey-5 map-device-header"
            style="padding: 6px"
            :id="`polyline-header-${route.route_id}`"
        >
            <q-item-section class="draggable">
                <q-item-label lines="1" class="draggable">
                    {{ `E${route.current_input}: ${route.text_node}` }}
                </q-item-label>
            </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <q-btn
                    round
                    size="sm"
                    color="primary"
                    icon="compare_arrows"
                    @click="changeDirection"
                    ><q-tooltip>Invertir</q-tooltip></q-btn
                >
            </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <q-btn
                    round
                    size="sm"
                    color="primary"
                    icon="mdi-playlist-check"
                    :class="
                        !enableMultiConnections && route?.selected
                            ? 'map-port-active'
                            : null
                    "
                    @click="emits('fusion-all')"
                    v-if="
                        route?.fibers.filter((f) => isAvaiableConnection(f))
                            .length > 0
                    "
                    ><q-tooltip>Empalmar toda la fibra</q-tooltip></q-btn
                >
                <q-btn
                    round
                    size="sm"
                    color="primary"
                    disable
                    icon="mdi-playlist-check"
                    v-else
                />
            </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <cut-fiber
                    :ports="route?.fibers"
                    :layer="layer"
                    :route="route"
                    @update="(data) => emits('update', data)"
                />
            </q-item-section>
            <q-item-section avatar>
                <change-connections-state :ports="route?.fibers" />
            </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <q-btn
                    round
                    size="sm"
                    color="danger"
                    icon="delete"
                    @click="showConfirm = true"
                    ><q-tooltip class="bg-danger">Eliminar</q-tooltip></q-btn
                >
            </q-item-section>
        </q-item>
        <template v-if="config.mainBuffers.length > 0">
            <q-item
                dense
                v-for="(mf, indexMf) in config.mainBuffers"
                :key="`main-buffer-${mf}-route-${route.route_id}`"
                :class="`bg-${colors[indexMf].css}`"
                clickable
                ><q-item-section
                    avatar
                    style="margin-right: 8px"
                    v-if="route.direction === 'right'"
                >
                    <span
                        style="
                            position: absolute;
                            top: 0;
                            bottom: 0;
                            height: 100% !important;
                            width: 2px;
                            color: white;
                            background-color: wheat;
                        "
                    ></span>
                </q-item-section>
                <q-item-section class="shadow-3">
                    <q-item
                        dense
                        v-for="(buffer, indexBuffer) in config.buffers.filter(
                            (b) => b.mainBuffer === mf
                        )"
                        :key="`buffer-${indexBuffer}-route-${route.route_id}`"
                        :class="`bg-${buffer.color}`"
                        clickable
                        style="padding: 0; min-height: 38px"
                    >
                        <template v-if="route.direction === 'left'">
                            <q-item-section
                                avatar
                                style="margin-left: -24px; padding: 0"
                                v-if="route.direction === 'left'"
                            >
                                <template
                                    v-if="
                                        config.active !==
                                        `main_${buffer.mainBuffer}_buffer_${buffer.name}`
                                    "
                                >
                                    <q-btn
                                        v-for="fiber in buffer.fibers"
                                        :key="fiber.element_id"
                                        :id="fiber.element_id"
                                        size="xs"
                                        dense
                                        padding="0px"
                                        :color="colors[fiber.number - 1].css"
                                        style="
                                            width: 24px !important;
                                            min-height: 3.2px !important;
                                        "
                                    ></q-btn>
                                </template>
                            </q-item-section>
                            <q-item-section avatar class="q-mx-sm">
                                <change-connections-state
                                    :ports="buffer.fibers"
                                    size="8px"
                                />
                            </q-item-section>
                            <q-item-section avatar v-if="hasEdit">
                                <cut-fiber
                                    :ports="buffer.fibers"
                                    :layer="layer"
                                    :route="route"
                                    size="8px"
                                    @update="(data) => emits('update', data)"
                                />
                            </q-item-section>
                        </template>
                        <q-item-section
                            class="text-center text-bold"
                            :id="`main-${buffer.mainBuffer}-buffer-${buffer.name}-fiber-${route.route_id}`"
                            @click="onBufferClick(buffer)"
                        >
                            <buffer-route
                                :buffer="buffer"
                                :route="route"
                                :layer="layer"
                                :show="
                                    config.active ===
                                    `main_${buffer.mainBuffer}_buffer_${buffer.name}`
                                "
                                @redraw="emits('redraw')"
                                @toggle="(f) => emits('toggle', f)"
                                @update="(data) => emits('update', data)"
                            />
                        </q-item-section>
                        <template v-if="route.direction === 'right'">
                            <q-item-section
                                avatar
                                style="padding: 0"
                                v-if="hasEdit"
                            >
                                <cut-fiber
                                    :ports="buffer.fibers"
                                    :layer="layer"
                                    :route="route"
                                    size="8px"
                                    @update="(data) => emits('update', data)"
                                />
                            </q-item-section>
                            <q-item-section
                                avatar
                                style="padding: 0"
                                class="q-mx-sm"
                            >
                                <change-connections-state
                                    :ports="buffer.fibers"
                                    size="8px"
                                />
                            </q-item-section>
                            <q-item-section
                                avatar
                                style="margin-right: -24px; padding: 0"
                            >
                                <template
                                    v-if="
                                        config.active !==
                                        `main_${buffer.mainBuffer}_buffer_${buffer.name}`
                                    "
                                >
                                    <q-btn
                                        v-for="fiber in buffer.fibers"
                                        :key="fiber.element_id"
                                        :id="fiber.element_id"
                                        size="xs"
                                        dense
                                        padding="0px"
                                        :color="colors[fiber.number - 1].css"
                                        style="
                                            width: 24px !important;
                                            min-height: 3.2px !important;
                                        "
                                    ></q-btn>
                                </template>
                            </q-item-section>
                        </template>
                    </q-item> </q-item-section
                ><q-item-section avatar v-if="route.direction === 'left'">
                    <span
                        style="
                            position: absolute;
                            top: 0;
                            bottom: 0;
                            height: 100% !important;
                            width: 2px;
                            color: white;
                            background-color: wheat;
                        "
                    ></span>
                </q-item-section>
            </q-item>
        </template>
        <template v-else>
            <q-item
                dense
                v-for="(buffer, indexBuffer) in config.buffers"
                :key="`buffer-${indexBuffer}-route-${route.route_id}`"
                :class="`bg-${buffer.color}`"
                clickable
                style="padding: 0; min-height: 38px"
            >
                <template v-if="route.direction === 'left'">
                    <q-item-section
                        avatar
                        style="margin-left: -24px; padding: 0"
                    >
                        <template
                            v-if="
                                config.active !== `main_1_buffer_${buffer.name}`
                            "
                        >
                            <q-btn
                                v-for="fiber in buffer.fibers"
                                :key="fiber.element_id"
                                :id="fiber.element_id"
                                size="xs"
                                dense
                                padding="0px"
                                :color="colors[fiber.number - 1].css"
                                style="
                                    width: 24px !important;
                                    min-height: 3.2px !important;
                                "
                            ></q-btn>
                        </template>
                    </q-item-section>
                    <q-item-section avatar class="q-ml-sm">
                        <change-connections-state
                            :ports="buffer.fibers"
                            size="8px"
                        />
                    </q-item-section>
                    <q-item-section avatar class="q-ml-sm" v-if="hasEdit">
                        <cut-fiber
                            :ports="buffer.fibers"
                            :layer="layer"
                            :route="route"
                            size="8px"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </template>
                <q-item-section
                    class="text-center text-bold"
                    :id="`main-1-buffer-${buffer.name}-fiber-${route.route_id}`"
                    @click="onBufferClick(buffer)"
                >
                    <buffer-route
                        :buffer="buffer"
                        :route="route"
                        :layer="layer"
                        :show="config.active === `main_1_buffer_${buffer.name}`"
                        @update="(data) => emits('update', data)"
                        @redraw="emits('redraw')"
                        @toggle="(f) => emits('toggle', f)"
                    />
                </q-item-section>
                <template v-if="route.direction === 'right'">
                    <q-item-section avatar style="padding: 0" v-if="hasEdit">
                        <cut-fiber
                            :ports="buffer.fibers"
                            :layer="layer"
                            :route="route"
                            size="8px"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                    <q-item-section avatar style="padding: 0" class="q-mx-sm">
                        <change-connections-state
                            :ports="buffer.fibers"
                            size="8px"
                        />
                    </q-item-section>
                    <q-item-section
                        avatar
                        style="margin-right: -24px; padding: 0"
                    >
                        <template
                            v-if="!config[`main_1_buffer_${buffer.name}`]"
                        >
                            <q-btn
                                v-for="fiber in buffer.fibers"
                                :key="fiber.element_id"
                                :id="fiber.element_id"
                                size="xs"
                                dense
                                padding="0px"
                                :color="colors[fiber.number - 1].css"
                                style="
                                    width: 24px !important;
                                    min-height: 3.2px !important;
                                "
                            ></q-btn>
                        </template>
                    </q-item-section>
                </template>
            </q-item>
        </template>
    </q-list>

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar esta troncal"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import {
    changeRoutePosition,
    unassignRoute,
} from "../../helper/layers-request";
import { colors } from "../../helper/mapUtils";
import { nextTick, onBeforeMount, ref, watch } from "vue";
import BufferRoute from "./BufferRoute.vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import CutFiber from "../others/CutFiber.vue";
import {
    isAvaiableConnection,
    hideAllMenu,
    enableMultiConnections,
} from "../../../../../composables/useMapConnections";
import DialogConfirm from "../others/DialogConfirm.vue";

defineOptions({
    name: "RouteComponent",
});

const props = defineProps({
    route: Object,
    layer: Object,
    hasEdit: Boolean,
});

const emits = defineEmits([
    "unassigned",
    "toggle",
    "change-direction",
    "redraw",
    "fusion-all",
    "update",
]);
const showConfirm = ref(false);

const config = ref({
    mainBuffers: [],
    buffers: [],
    active: null,
});

onBeforeMount(async () => {
    await updateBuffers();
    config.value.active = null;
    config.value.buffers.forEach((b) => {
        config.value[`main_${b.mainBuffer}_buffer_${b.name}`] = false;
    });
});

watch(
    () => props.route.fibers,
    () => {
        updateBuffers();
    },
    {
        deep: true,
    }
);

const updateBuffers = () => {
    const fibers = props.route.fibers ?? [];
    let list = [];
    const hasSubbuffers = fibers.length > 96;
    nextTick(() => {
        fibers.forEach((f) => {
            f["connected"] = !isAvaiableConnection(f);
            f["route_id"] = props.route.route_id || null;
        });
    });
    if (hasSubbuffers) {
        const mainBuffers = [...new Set(fibers.map((f) => f.parent_buffer))];
        config.value.mainBuffers = mainBuffers;
        mainBuffers.forEach((parent_buffer) => {
            const fibersInBuffer = fibers.filter(
                (f) => f.parent_buffer === parent_buffer
            );
            const buffers = [...new Set(fibersInBuffer.map((f) => f.buffer))];
            buffers.forEach((buffer) => {
                const fibersInSubbuffer = fibersInBuffer.filter(
                    (f) => f.buffer === buffer
                );
                list.push({
                    name: buffer,
                    color: colors[(buffer - 1) % 12].css,
                    fibers: fibersInSubbuffer,
                    isSubbuffer: true,
                    mainBuffer: parent_buffer,
                });
            });
        });
    } else {
        let differents = [...new Set(fibers.map((f) => f.buffer))];
        differents.forEach((d) => {
            list.push({
                name: d,
                color: colors[(d - 1) % 12].css,
                fibers: fibers.filter((f) => f.buffer === d),
                isSubbuffer: false,
                mainBuffer: 1,
            });
        });
    }
    config.value.buffers = list;
};

watch(hideAllMenu, async (n) => {
    if (n) {
        const { active } = config.value;
        if (active) {
            config.value[active] = false;
            config.value.active = null;
        }
        nextTick(() => {
            hideAllMenu.value = false;
        });
    }
});

const changeDirection = async () => {
    showLoading();
    const direction = props.route.direction === "left" ? "right" : "left";
    const result = await changeRoutePosition(props.route.route_id, {
        direction,
    });
    hideLoading();
    if (result) {
        emits("change-direction", direction);
    }
};

const destroy = async () => {
    showLoading();
    let result = await unassignRoute(props.route.route_id);
    hideLoading();
    if (result) {
        emits("unassigned", result);
        message("Ruta eliminada correctamente", "success");
    } else {
        message("No se ha podido eliminar esta ruta", "error");
    }
    showConfirm.value = false;
};

const onBufferClick = async (buffer) => {
    const comenclature = `main_${buffer.mainBuffer}_buffer_${buffer.name}`;
    config.value.active =
        config.value?.active === comenclature ? null : comenclature;
};
</script>
<style scoped>
.organizer-ports {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 5px;
    margin: 20px;
}
</style>
