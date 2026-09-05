<template>
    <q-list
        class="q-pa-xs q-my-xs shadow-3 bg-white"
        :class="device.parent_id ? '' : 'map-device'"
        dense
        :id="`${device.type}-${device.id}`"
        :style="{
            left: device.position_x + 'px',
            top: device.position_y + 'px',
        }"
    >
        <q-expansion-item
            class="bg-grey-5 map-device-header"
            :id="`${device.type}-header-${device.id}`"
            v-model="expanded"
            @after-show="emits('redraw')"
            @after-hide="emits('redraw')"
        >
            <template v-slot:header>
                <q-item-section
                    @click.stop="(ev) => ev.preventDefault()"
                    class="draggable"
                >
                    <q-item-label lines="1" class="draggable">
                        {{ device.name }}
                    </q-item-label>
                </q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <form-router-component
                        :object="device"
                        @update="(data) => emits('update', data)"
                    />
                </q-item-section>
                <q-item-section avatar>
                    <change-connections-state :ports="device.ports"
                /></q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <q-btn
                        round
                        size="sm"
                        color="danger"
                        icon="delete"
                        @click.stop="showConfirm = true"
                        ><q-tooltip class="bg-danger"
                            >Eliminar</q-tooltip
                        ></q-btn
                    >
                </q-item-section>
            </template>
            <q-card v-if="expanded">
                <q-card-section class="q-pa-none">
                    <q-list dense
                        ><q-item>
                            <q-item-section>
                                <q-slider
                                    v-model="slider"
                                    :min="0"
                                    :max="2"
                                    snap
                                    markers
                                    @update:model-value="
                                        (val) => {
                                            currentSize = avaiablesSizes.find(
                                                (s) => s.value === val
                                            ).name;
                                            emits('redraw');
                                        }
                                    "
                                />
                            </q-item-section>
                        </q-item>
                        <q-item v-if="portsConsole.length > 0">
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos de consola
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in portsConsole"
                                :key="`port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :id="port.element_id"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>
                        <q-item v-if="ports1GB.length > 0">
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos sfp
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in ports1GB"
                                :key="`port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :id="port.element_id"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>
                        <q-item v-if="ports10GB.length > 0">
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos sfp+
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in ports10GB"
                                :key="`port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :id="port.element_id"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>
                        <q-item v-if="ports100GB.length > 0">
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos sfp++
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in ports100GB"
                                :key="`port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :id="port.element_id"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>
                        <template v-if="groupedPorts.length > 0"
                            ><q-item>
                                <q-item-section>
                                    <q-item-label
                                        :style="{
                                            'font-size': `${10 + slider * 2}px`,
                                        }"
                                    >
                                        Puertos eth
                                    </q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item
                                v-for="(row, rowIndex) in groupedPorts"
                                :key="`row-${rowIndex}`"
                            >
                                <q-item-section
                                    avatar
                                    v-for="(port, portIndex) in row"
                                    :key="`port-${portIndex}`"
                                    style="padding: 2px !important"
                                >
                                    <q-btn
                                        :size="currentSize"
                                        dense
                                        padding="6px"
                                        class="shadow-3"
                                        :label="port.name"
                                        :color="port.color"
                                        :text-color="port.textColor"
                                        :style="port.border"
                                        :class="{
                                            'map-port-active': port.selected,
                                        }"
                                        :id="port.element_id"
                                        :disable="
                                            getConnectionByPort(port) !== null
                                        "
                                        @click.stop="emits('toggle', port)"
                                    />
                                </q-item-section>
                            </q-item>
                        </template>
                    </q-list>
                </q-card-section> </q-card
        ></q-expansion-item>
    </q-list>

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar este dispositivo"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import { destroyDevice } from "../../helper/devices-request";
import FormRouterComponent from "./FormRouterComponent.vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import {
    getColorFromPort,
    avaiablesSizes,
    getConnectionByPort,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "RouterComponent",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
});

const emits = defineEmits(["removed", "toggle", "update", "redraw"]);
const showConfirm = ref(false);

const slider = ref(0);
const currentSize = ref("xs");
const expanded = ref(true);

onMounted(() => {
    slider.value = 0;
    currentSize.value = "xs";
    expanded.value = true;
});

const portsConsole = computed(() => {
    const ports = props.device.ports.filter((p) => p.type === "console");
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
});

const ports1GB = computed(() => {
    const ports = props.device.ports.filter((p) => p.transfer === 1);
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
});

const ports10GB = computed(() => {
    const ports = props.device.ports.filter((p) => p.transfer === 10);
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
});

const ports100GB = computed(() => {
    const ports = props.device.ports.filter((p) => p.transfer === 100);
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
});

const groupedPorts = computed(() => {
    const ports = props.device.ports.filter(
        (p) => p.transfer === null && p.type !== "console"
    );
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    const result = [];
    const pointsPerRow = 12;
    for (let i = 0; i < ports.length; i += pointsPerRow) {
        result.push(ports.slice(i, i + pointsPerRow));
    }
    return result;
});

const destroy = async () => {
    showLoading();
    let result = await destroyDevice(props.device.id);
    hideLoading();
    if (result) {
        emits("removed", result);
        message("Dispositivo eliminado correctamente", "success");
    } else {
        message("No se ha podido eliminar este dispositivo", "error");
    }
    showConfirm.value = false;
};
</script>
