<template>
    <q-list
        dense
        class="q-pa-xs q-my-xs shadow-3 bg-white"
        :class="cls"
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
                    <q-btn
                        round
                        size="sm"
                        color="primary"
                        icon="compare_arrows"
                        @click="changeDirection"
                        ><q-tooltip>Invertir</q-tooltip></q-btn
                    >
                </q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <form-splitter-component
                        :object="device"
                        @updated="(obj) => emits('update', obj)"
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
                        <template v-for="port in currentPorts" :key="port.id">
                            <q-item
                                style="
                                    padding: 0;
                                    margin-left: -29px;
                                    margin-right: -20px;
                                    margin-top: 8px !important;
                                "
                                class="q-gutter-x-xs"
                                v-if="device.orientation === 'right'"
                            >
                                <template v-if="port.type === 'in'">
                                    <q-item-section avatar>
                                        <q-btn
                                            :id="port.element_id"
                                            :label="port.name"
                                            :size="currentSize"
                                            :color="port.color"
                                            :style="port.border"
                                            :text-color="port.textColor"
                                            class="port shadow-3"
                                            padding="6px"
                                            :class="
                                                port.selected
                                                    ? 'map-port-active'
                                                    : null
                                            "
                                            :disable="
                                                getConnectionByPort(port) !==
                                                null
                                            "
                                            @click.stop="emits('toggle', port)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <port-note-component
                                            :object="port"
                                            :has-edit="hasEdit"
                                            :size="currentSize"
                                            @save="(note) => (port.note = note)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <change-connections-state
                                            padding="5px"
                                            :round="false"
                                            :ports="[port]"
                                            :size="currentSize"
                                    /></q-item-section>
                                </template>
                                <template v-else>
                                    <q-item-section />
                                    <q-item-section avatar>
                                        <change-connections-state
                                            padding="5px"
                                            :round="false"
                                            :ports="[port]"
                                            :size="currentSize"
                                    /></q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <port-note-component
                                            :object="port"
                                            :has-edit="hasEdit"
                                            :size="currentSize"
                                            @save="(note) => (port.note = note)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <q-btn
                                            :id="port.element_id"
                                            :label="port.name"
                                            :size="currentSize"
                                            :color="port.color"
                                            :style="port.border"
                                            :text-color="port.textColor"
                                            class="port shadow-3"
                                            padding="6px"
                                            :class="
                                                port.selected
                                                    ? 'map-port-active'
                                                    : null
                                            "
                                            :disable="
                                                getConnectionByPort(port) !==
                                                null
                                            "
                                            @click.stop="emits('toggle', port)"
                                        />
                                    </q-item-section>
                                </template>
                            </q-item>
                            <q-item
                                class="q-gutter-x-xs"
                                dense
                                style="
                                    margin-left: -29px;
                                    margin-right: -20px;
                                    margin-top: 8px !important;
                                "
                                v-else
                            >
                                <template v-if="port.type === 'in'">
                                    <q-item-section />
                                    <q-item-section avatar>
                                        <change-connections-state
                                            padding="5px"
                                            :round="false"
                                            :ports="[port]"
                                            :size="currentSize"
                                    /></q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <port-note-component
                                            :object="port"
                                            :has-edit="hasEdit"
                                            :size="currentSize"
                                            @save="(note) => (port.note = note)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <q-btn
                                            :id="port.element_id"
                                            :label="port.name"
                                            :size="currentSize"
                                            :color="port.color"
                                            :style="port.border"
                                            :text-color="port.textColor"
                                            class="port shadow-3"
                                            padding="6px"
                                            :class="
                                                port.selected
                                                    ? 'map-port-active'
                                                    : null
                                            "
                                            :disable="
                                                getConnectionByPort(port) !==
                                                null
                                            "
                                            @click.stop="emits('toggle', port)"
                                        />
                                    </q-item-section>
                                </template>
                                <template v-else>
                                    <q-item-section avatar>
                                        <q-btn
                                            :id="port.element_id"
                                            :label="port.name"
                                            :size="currentSize"
                                            :color="port.color"
                                            :style="port.border"
                                            :text-color="port.textColor"
                                            class="port shadow-3"
                                            padding="6px"
                                            :class="
                                                port.selected
                                                    ? 'map-port-active'
                                                    : null
                                            "
                                            :disable="
                                                getConnectionByPort(port) !==
                                                null
                                            "
                                            @click.stop="emits('toggle', port)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar style="padding: 0">
                                        <port-note-component
                                            :object="port"
                                            :has-edit="hasEdit"
                                            :size="currentSize"
                                            @save="(note) => (port.note = note)"
                                        />
                                    </q-item-section>
                                    <q-item-section avatar>
                                        <change-connections-state
                                            padding="5px"
                                            :round="false"
                                            :ports="[port]"
                                            :size="currentSize"
                                    /></q-item-section>
                                </template>
                            </q-item>
                        </template>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-expansion-item>
    </q-list>

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar este dispositivo"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import FormSplitterComponent from "./FormSplitterComponent.vue";
import PortNoteComponent from "../PortNoteComponent.vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { destroyDevice, saveDevice } from "../../helper/devices-request";
import { message } from "../../../../../helpers/toastMsg";
import { computed, onMounted, ref } from "vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import {
    getConnectionByPort,
    getColorFromPort,
    avaiablesSizes,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "SplitterComponent",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
    cls: String,
});

const emits = defineEmits(["update", "removed", "toggle", "redraw"]);
const showConfirm = ref(false);

const slider = ref(0);
const currentSize = ref("xs");
const expanded = ref(true);

onMounted(() => {
    slider.value = 0;
    currentSize.value = "xs";
    expanded.value = true;
});

const currentPorts = computed(() => {
    const ports = props.device.ports;
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
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

const changeDirection = async () => {
    showLoading();
    const result = await saveDevice({
        id: props.device.id,
        orientation: props.device.orientation === "left" ? "right" : "left",
    });
    hideLoading();
    if (result) {
        emits("update", result);
    }
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
