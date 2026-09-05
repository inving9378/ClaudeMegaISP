<template>
    <q-card
        ref="menuRef"
        :id="`menu-main-${buffer.mainBuffer}-buffer-${buffer.name}-fiber-${route.route_id}`"
        style="position: absolute; z-index: 9; min-width: 120px"
        :style="menuStyle"
        v-if="menu"
    >
        <q-card-section style="padding: 0">
            <q-list dense style="padding: 0" :class="`bg-${buffer.color}`">
                <q-item
                    v-for="fiber in buffer.fibers"
                    :key="fiber.element_id"
                    style="padding: 0"
                >
                    <q-item-section> </q-item-section>
                    <q-item-section avatar style="padding: 0">
                        <cut-fiber
                            :ports="[fiber]"
                            :layer="layer"
                            :route="route"
                            size="8px"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                    <q-item-section avatar style="padding: 0" class="q-mx-sm">
                        <change-connections-state :ports="[fiber]" size="8px" />
                    </q-item-section>
                    <q-item-section avatar style="padding: 0">
                        <q-btn
                            :id="fiber.element_id"
                            :disable="
                                !acceptedFiberConnection(props.route, fiber)
                            "
                            size="xs"
                            dense
                            :color="colors[fiber.number - 1].css"
                            :text-color="
                                colors[fiber.number - 1].css === 'white'
                                    ? 'black'
                                    : 'white'
                            "
                            :class="fiber.selected ? 'map-port-active' : null"
                            style="width: 24px !important; min-height: 24px"
                            @click.stop="emits('toggle', fiber)"
                        ></q-btn>
                    </q-item-section>
                </q-item>
            </q-list>
        </q-card-section>
    </q-card>
</template>

<script setup>
import { colors } from "../../helper/mapUtils";
import { computed, nextTick, ref, watch } from "vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import CutFiber from "../others/CutFiber.vue";
import { acceptedFiberConnection } from "../../../../../composables/useMapConnections";

defineOptions({
    name: "RouteComponent",
});

const props = defineProps({
    route: Object,
    layer: Object,
    buffer: Object,
    hasEdit: Boolean,
    show: Boolean,
});

const emits = defineEmits(["toggle", "redraw", "update"]);

const menu = ref(false);
const menuStyle = ref({});
const maxMenuHeight = 400;
watch(
    () => props.show,
    async (n) => {
        menu.value = n;
        await nextTick(() => {
            updatePosition();
        });
        emits("redraw", n);
    }
);

const bufferId = computed(() => {
    return `main-${props.buffer.mainBuffer}-buffer-${props.buffer.name}-fiber-${props.route.route_id}`;
});

const updatePosition = () => {
    const trigger = document.getElementById(bufferId.value);
    const menu = document.getElementById(`menu-${bufferId.value}`);
    const menuWidth = 120;

    const boundary = document.querySelector(".map-workspace-container");
    if (trigger && menu && boundary) {
        const triggerRect = trigger.getBoundingClientRect();
        const boundaryRect = boundary.getBoundingClientRect();
        let menuHeight = menu.scrollHeight;
        const spaceAbove = triggerRect.top - boundaryRect.top;
        const spaceBelow = boundaryRect.bottom - triggerRect.bottom;
        const spaceLeft = triggerRect.left - boundaryRect.left;
        const spaceRight = boundaryRect.right - triggerRect.right;
        let position = {};
        const adjustMenuHeight = (availableSpace) => {
            if (menuHeight > availableSpace) {
                if (availableSpace > maxMenuHeight) {
                    menuHeight = maxMenuHeight;
                } else {
                    menuHeight = availableSpace - 20;
                }
            }
            return menuHeight;
        };
        if (spaceRight >= menuWidth + 20) {
            menuHeight = adjustMenuHeight(
                Math.min(spaceBelow, spaceAbove) * 2 + triggerRect.height
            );
            position.top =
                triggerRect.top + triggerRect.height / 2 - menuHeight / 2;
            position.left = triggerRect.right + 10;
            if (position.top < boundaryRect.top + 10) {
                position.top = boundaryRect.top + 10;
            } else if (position.top + menuHeight > boundaryRect.bottom - 10) {
                position.top = boundaryRect.bottom - menuHeight - 10;
            }
        } else if (spaceLeft >= menuWidth + 20) {
            menuHeight = adjustMenuHeight(
                Math.min(spaceBelow, spaceAbove) * 2 + triggerRect.height
            );
            position.top =
                triggerRect.top + triggerRect.height / 2 - menuHeight / 2;
            position.left = triggerRect.left - menuWidth - 10;
            if (position.top < boundaryRect.top + 10) {
                position.top = boundaryRect.top + 10;
            } else if (position.top + menuHeight > boundaryRect.bottom - 10) {
                position.top = boundaryRect.bottom - menuHeight - 10;
            }
        } else {
            if (spaceBelow >= menuHeight + 20 || spaceBelow > spaceAbove) {
                menuHeight = adjustMenuHeight(spaceBelow);
                position.top = triggerRect.bottom + 10;
                position.left =
                    triggerRect.left + triggerRect.width / 2 - menuWidth / 2;
                if (position.left < boundaryRect.left + 10) {
                    position.left = boundaryRect.left + 10;
                } else if (
                    position.left + menuWidth >
                    boundaryRect.right - 10
                ) {
                    position.left = boundaryRect.right - menuWidth - 10;
                }
            } else {
                menuHeight = adjustMenuHeight(spaceAbove);
                position.top = triggerRect.top - menuHeight - 10;
                position.left =
                    triggerRect.left + triggerRect.width / 2 - menuWidth / 2;
                if (position.left < boundaryRect.left + 10) {
                    position.left = boundaryRect.left + 10;
                } else if (
                    position.left + menuWidth >
                    boundaryRect.right - 10
                ) {
                    position.left = boundaryRect.right - menuWidth - 10;
                }
            }
        }

        menuStyle.value = {
            position: "fixed",
            top: `${position.top}px`,
            left: `${position.left}px`,
            width: `${menuWidth}px`,
            height: `${menuHeight}px`,
            zIndex: 1000,
        };
    }
};
</script>
