<template>
    <q-list
        class="q-pa-xs q-my-xs shadow-3 bg-white"
        :class="device.parent_id ? '' : 'map-device map-organizer'"
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
                        ({{ device.active === "in" ? "VF" : "VT" }})
                    </q-item-label>
                </q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <form-organizer-component
                        :object="device"
                        @update="(data) => emits('update', data)"
                    />
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
                        @click.stop="onChangeDirection"
                        ><q-tooltip class="bg-primary"
                            >Intercambiar vista</q-tooltip
                        ></q-btn
                    >
                </q-item-section>
                <q-item-section avatar>
                    <change-connections-state :ports="device.ports" />
                </q-item-section>
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
                    <q-list dense>
                        <q-item>
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
                        <q-item
                            v-for="(row, rowIndex) in groupedPorts"
                            :key="`row-${rowIndex}`"
                        >
                            <q-item-section
                                avatar
                                v-for="port in row"
                                :key="`port-organizer-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :label="port.name"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :color="port.color"
                                    :style="port.border"
                                    :text-color="port.textColor"
                                    :id="port.element_id"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>
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
import { computed, onMounted, ref } from "vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import { destroyDevice } from "../../helper/devices-request";
import FormOrganizerComponent from "./FormOrganizerComponent.vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import {
    getConnectionByPort,
    getColorFromPort,
    avaiablesSizes,
} from "../../../../../composables/useMapConnections";
defineOptions({
    name: "OrganizerComponent",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
});

const emits = defineEmits([
    "removed",
    "toggle",
    "change-direction",
    "update",
    "redraw",
]);
const showConfirm = ref(false);

const slider = ref(0);
const currentSize = ref("xs");
const expanded = ref(true);

onMounted(() => {
    slider.value = 0;
    currentSize.value = "xs";
    expanded.value = true;
});

const groupedPorts = computed(() => {
    const ports = props.device.ports.filter(
        (p) => p.type === props.device.active
    );
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    const result = [];
    const pointsPerRow = props.device.data.columns;
    for (let i = 0; i < ports.length; i += pointsPerRow) {
        result.push(ports.slice(i, i + pointsPerRow));
    }
    return result;
});

const onChangeDirection = (device) => {
    emits("change-direction");
};

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
