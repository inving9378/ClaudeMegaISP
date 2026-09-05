<template>
    <q-dialog
        v-model="dialog"
        persistent
        full-width
        full-height
        @before-show="onShowDialog"
        @hide="onHide"
    >
        <q-layout
            view="hHh LpR lFr"
            container
            class="bg-white"
            style="height: 500px"
        >
            <q-header>
                <q-toolbar>
                    <q-toolbar-title
                        >Configurar site {{ object.name }}</q-toolbar-title
                    >
                    <q-btn
                        icon="close"
                        flat
                        round
                        dense
                        @click="dialog = false"
                    />
                </q-toolbar>

                <q-toolbar class="text-primary bg-grey-3 q-gutter-xs">
                    <q-btn
                        round
                        dense
                        icon="menu"
                        color="primary"
                        @click="drawerLeft = !drawerLeft"
                        v-if="tabDevice !== 'marker-information'"
                        ><q-tooltip>{{
                            drawerLeft
                                ? "Ocultar entradas de la izquierda"
                                : "Mostrar entradas de la izquierda"
                        }}</q-tooltip></q-btn
                    >
                    <q-btn
                        @click="loadData"
                        color="primary"
                        icon="mdi-sync"
                        round
                        size="11px"
                        v-if="tabDevice === 'marker-config'"
                        ><q-tooltip>Actualizar</q-tooltip></q-btn
                    ><q-btn
                        round
                        dense
                        icon="door_back"
                        color="primary"
                        @click="createDevice"
                        v-if="hasEdit && tabDevice === 'marker-config'"
                        ><q-tooltip> Agregar rack </q-tooltip></q-btn
                    >
                    <q-btn
                        :color="enableMultiConnections ? 'positive' : 'grey'"
                        icon="mdi-format-list-checkbox"
                        round
                        size="11px"
                        @click="
                            {
                                unselectAllPort();
                                enableMultiConnections =
                                    !enableMultiConnections;
                            }
                        "
                        v-if="hasEdit && tabDevice === 'marker-config'"
                        ><q-tooltip
                            >{{
                                enableMultiConnections
                                    ? "Deshabilitar"
                                    : "Habilitar"
                            }}
                            conexión múltiple</q-tooltip
                        ></q-btn
                    >
                    <q-space style="width: auto !important" />
                    <q-tabs
                        v-model="tabDevice"
                        dense
                        shrink
                        stretch
                        @update:model-value="onTabChange"
                    >
                        <q-tab
                            no-caps
                            name="marker-information"
                            label="Información"
                            style="width: auto"
                        />
                        <q-tab
                            no-caps
                            name="marker-config"
                            label="Dispositivos y conexiones"
                            style="width: auto"
                        />
                    </q-tabs>
                    <q-btn
                        round
                        dense
                        icon="menu"
                        color="primary"
                        @click="drawerRight = !drawerRight"
                        v-if="tabDevice !== 'marker-information'"
                    >
                        <q-tooltip>{{
                            drawerRight
                                ? "Ocultar entradas de la derecha"
                                : "Mostrar entradas de la derecha"
                        }}</q-tooltip></q-btn
                    >
                </q-toolbar>
            </q-header>

            <q-drawer
                v-model="drawerLeft"
                :width="200"
                class="bg-grey-3"
                v-show="tabDevice !== 'marker-information'"
            >
                <q-scroll-area class="fit">
                    <div class="q-pa-sm">
                        <troncal-input
                            :layer="object"
                            :has-edit="hasEdit"
                            position="left"
                            @update="update"
                        />
                    </div>
                </q-scroll-area>
            </q-drawer>

            <q-drawer
                v-model="drawerRight"
                side="right"
                :width="200"
                class="bg-grey-3"
                v-show="tabDevice !== 'marker-information'"
            >
                <q-scroll-area class="fit">
                    <div class="q-pa-sm">
                        <troncal-input
                            :layer="object"
                            :has-edit="hasEdit"
                            position="right"
                            @update="update"
                        />
                    </div>
                </q-scroll-area>
            </q-drawer>

            <q-page-container>
                <q-page>
                    <div
                        class="column marker-information q-pa-xl"
                        style="margin-right: 0px"
                        v-show="tabDevice === 'marker-information'"
                    >
                        <div class="self-center" style="width: 500px">
                            <site-component
                                :in-dialog="false"
                                :object="object"
                                :has-edit="hasEdit"
                            />
                        </div>
                    </div>
                    <div
                        class="map-workspace-container"
                        ref="workspace"
                        v-show="tabDevice === 'marker-config'"
                    >
                        <template
                            v-for="(device, index) in routes"
                            :key="`route-${device.id}`"
                        >
                            <div
                                @mousedown="startDrag($event, index, 'routes')"
                            >
                                <route-component
                                    :route="device"
                                    :layer="object"
                                    :has-edit="hasEdit"
                                    @update="update"
                                    @unassigned="update"
                                    @toggle="togglePort"
                                    @change-direction="
                                        (direction) =>
                                            onChangeDirectionRoute(
                                                device,
                                                direction
                                            )
                                    "
                                    @redraw="onChangeStateMenu"
                                    @fusion-all="fusionAll(device)"
                                />
                            </div>
                        </template>

                        <div
                            v-for="(obj, index) in racks"
                            :key="'device-' + obj.id"
                            class="map-device shadow-3 rounded-borders"
                            :style="{
                                left: obj.position_x + 'px',
                                top: obj.position_y + 'px',
                            }"
                            @mousedown="startDrag($event, index, 'racks')"
                        >
                            <q-item class="map-splitter-header">
                                <q-item-section class="draggable">
                                    {{ obj.name }} - {{ index + 1 }}
                                </q-item-section>
                                <q-item-section avatar v-if="hasEdit">
                                    <q-btn
                                        round
                                        icon="add"
                                        color="primary"
                                        size="sm"
                                    >
                                        <q-menu ref="menuOptionsRack">
                                            <q-list dense>
                                                <form-organizer-component
                                                    :parent_id="obj.id"
                                                    :layer_id="object.id"
                                                    @update="onCreateDevice"
                                                />
                                                <form-router-component
                                                    :parent_id="obj.id"
                                                    :layer_id="object.id"
                                                    @update="onCreateDevice"
                                                />
                                                <form-switch-component
                                                    :parent_id="obj.id"
                                                    :layer_id="object.id"
                                                    @update="onCreateDevice"
                                                />
                                                <form-olt-component
                                                    :parent_id="obj.id"
                                                    :layer_id="object.id"
                                                    @update="onCreateDevice"
                                                />
                                                <form-splitter-component
                                                    :box="object"
                                                    :parent_id="obj.id"
                                                    @created="onCreateDevice"
                                                />
                                            </q-list>
                                        </q-menu>
                                    </q-btn>
                                </q-item-section>
                                <q-item-section avatar v-if="hasEdit">
                                    <q-btn
                                        round
                                        size="sm"
                                        color="danger"
                                        icon="delete"
                                        @click="
                                            {
                                                currentDevice = obj;
                                                showConfirm = true;
                                            }
                                        "
                                        ><q-tooltip class="bg-danger"
                                            >Eliminar</q-tooltip
                                        ></q-btn
                                    >
                                </q-item-section>
                            </q-item>

                            <q-item
                                class="bg-white"
                                v-if="
                                    devices.filter((d) => d.type !== 'rack')
                                        .length > 0
                                "
                            >
                                <q-item-section
                                    ><router-component
                                        v-for="device in devices.filter(
                                            (d) =>
                                                d.type === 'router' &&
                                                d.parent_id === obj.id
                                        )"
                                        :key="`router-${device.id}`"
                                        :device="device"
                                        :has-edit="hasEdit"
                                        @toggle="togglePort"
                                        @redraw="onRedraw"
                                        @removed="update"
                                        @update="update"
                                    />
                                    <switch-component
                                        v-for="device in devices.filter(
                                            (d) =>
                                                d.type === 'switch' &&
                                                d.parent_id === obj.id
                                        )"
                                        :key="`switch-${device.id}`"
                                        :device="device"
                                        :has-edit="hasEdit"
                                        @toggle="togglePort"
                                        @redraw="onRedraw"
                                        @removed="update"
                                        @update="update"
                                    />
                                    <organizer-component
                                        v-for="device in devices.filter(
                                            (d) =>
                                                d.type === 'organizer' &&
                                                d.parent_id === obj.id
                                        )"
                                        :key="`organizer-${device.id}`"
                                        :device="device"
                                        :has-edit="hasEdit"
                                        @change-direction="
                                            onChangeOrganizerView(device)
                                        "
                                        @toggle="togglePort"
                                        @redraw="onRedraw"
                                        @removed="update"
                                        @update="update"
                                    />
                                    <olt-component
                                        v-for="device in devices.filter(
                                            (d) =>
                                                d.type === 'olt' &&
                                                d.parent_id === obj.id
                                        )"
                                        :key="`olt-${device.id}`"
                                        :device="device"
                                        :has-edit="hasEdit"
                                        @toggle="togglePort"
                                        @redraw="onRedraw"
                                        @removed="update"
                                        @update="update"
                                    />

                                    <splitter-component
                                        v-for="device in devices.filter(
                                            (d) =>
                                                d.type === 'splitter' &&
                                                d.parent_id === obj.id
                                        )"
                                        :key="`olt-${device.id}`"
                                        :device="device"
                                        :has-edit="hasEdit"
                                        @toggle="togglePort"
                                        @redraw="onRedraw"
                                        @removed="update"
                                        @update="update"
                                    />
                                </q-item-section>
                            </q-item>
                        </div>
                        <svg-connections
                            :has-edit="hasEdit"
                            @removed="update"
                        />
                    </div>
                </q-page>

                <q-page-scroller position="bottom" class="text-center">
                    <q-btn
                        fab
                        size="xs"
                        icon="keyboard_arrow_up"
                        color="primary"
                    />
                </q-page-scroller>
            </q-page-container>
        </q-layout>
    </q-dialog>

    <form-junction
        :show="showJunction"
        @hide="onHideJunction"
        @save="(data) => createConnection(data)"
    />

    <form-zone
        :show="showZone"
        @hide="onHideZone"
        @save="(data) => createConnection(data)"
    />

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar este dispositivo"
        @yes="removeDevice"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from "vue";
import { dom } from "../../../../../../../public/plugins/quasar/js/quasar.umd.prod";
import { message } from "../../../../../helpers/toastMsg";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import FormRouterComponent from "../devices/FormRouterComponent.vue";
import FormOrganizerComponent from "../devices/FormOrganizerComponent.vue";
import FormSwitchComponent from "../devices/FormSwitchComponent.vue";
import OrganizerComponent from "../devices/OrganizerComponent.vue";
import RouterComponent from "../devices/RouterComponent.vue";
import SwitchComponent from "../devices/SwitchComponent.vue";
import FormOltComponent from "../devices/FormOltComponent.vue";
import OltComponent from "../devices/OltComponent.vue";
import RouteComponent from "../devices/RouteComponent.vue";
import SvgConnections from "../others/SvgConnections.vue";
import FormJunction from "../devices/FormJunction.vue";
import FormZone from "../devices/FormZone.vue";
import SiteComponent from "../SiteComponent.vue";
import TroncalInput from "../others/TroncalInput.vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import FormSplitterComponent from "../devices/FormSplitterComponent.vue";
import SplitterComponent from "../devices/SplitterComponent.vue";
import {
    changeRoutePosition,
    getLayerConfig,
} from "../../helper/layers-request";
import { destroyDevice, saveDevice } from "../../helper/devices-request";
import {
    getAttributesConnection,
    getConnectionType,
    redrawConnections,
    validateConnection,
    hideAllMenu,
    fromSelected,
    fromSelected1,
    toSelected,
    getAvaiablesConnections,
    isOlt,
    isOutPort,
    getAvaiablesConnectionsPerRange,
    getDeviceFromPort,
    getPortsFromDevice,
    unselectAllPort,
    isAvaiableConnection,
    setNullablePorts,
    addDevice,
    devices,
    getDataFromType,
    routes,
    setDefaultConnections,
    racks,
    enableMultiConnections,
} from "../../../../../composables/useMapConnections";
import {
    saveConnection,
    saveConnectionMultiple,
} from "../../helper/connections-request";
import {
    getFromLocalStorage,
    setToLocalStorage,
} from "../../../../../composables/useLocalStorage";
import { currentNode } from "../../../../../composables/useNodeMap";

defineOptions({
    name: "SiteConfiguration",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    hasEdit: Boolean,
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);
const showConfirm = ref(false);
const currentDevice = ref(null);
const dialog = ref(false);
const workspace = ref(null);
let dragging = null;
const menuOptionsRack = ref(null);

const tabDevice = ref("marker-information");

const { ready } = dom;

const minX = 20;
const minY = 20;
const showJunction = ref(false);
const showZone = ref(false);
let totalSelected = 0;
const drawerLeft = ref(true);
const drawerRight = ref(true);

ready(function () {
    document.addEventListener("mousemove", handleMouseMove);
    document.addEventListener("mouseup", handleMouseUp);
});

onUnmounted(() => {
    if (props.hasEdit) {
        document.removeEventListener("mousemove", handleMouseMove);
        document.removeEventListener("mouseup", handleMouseUp);
    }
});

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const onShowDialog = () => {
    loadData();
    let tab = JSON.parse(getFromLocalStorage("tab-config")) ?? null;
    tabDevice.value =
        tab && tab.type === "site" && tab.key === props.object.key
            ? tab.tab
            : "marker-information";
    setToLocalStorage("dialog-config", "site_config");
    setToLocalStorage("layer-config", JSON.stringify(props.object));
    currentNode.value = props.object;
};

const onHide = () => {
    emits("hide");
    setDefaultConnections();
    setToLocalStorage("dialog-config", null);
    setToLocalStorage("layer-config", null);
    currentNode.value = null;
};

const createDevice = async () => {
    showLoading();
    const object = await saveDevice({
        name: "Rack",
        layer_id: props.object.id,
        position_x: 20,
        position_y: 20,
        type: "rack",
    });
    hideLoading();
    if (object) {
        addDevice(object);
        message("Rack agregado correctamente", "success");
    } else {
        message(
            "No se ha podido agregar un nuevo rack. Consulte al administrador",
            "error"
        );
    }
};

const onCreateDevice = (device) => {
    addDevice(device);
    nextTick(() => {
        redrawConnections(workspace);
    });
};

const startDrag = (e, index, type) => {
    if (!e.target.classList.contains("draggable")) return;
    e.preventDefault();
    const s = getDataFromType(type, index);
    dragging = {
        type,
        index,
        startX: e.clientX,
        startY: e.clientY,
        originalX: s.position_x,
        originalY: s.position_y,
    };
};

const handleMouseMove = (e) => {
    if (dragging && props.hasEdit) {
        const dx = e.clientX - dragging.startX;
        const dy = e.clientY - dragging.startY;
        const object = getDataFromType(dragging.type, dragging.index);
        object.position_x = Math.max(minX, dragging.originalX + dx);
        object.position_y = Math.max(minY, dragging.originalY + dy);
        redrawConnections(workspace);
    }
};

const handleMouseUp = () => {
    if (props.hasEdit) {
        const s = dragging
            ? getDataFromType(dragging.type, dragging.index) ?? null
            : null;
        if (
            s !== null &&
            (s.position_x !== dragging.originalX ||
                s.position_y !== dragging.originalY)
        ) {
            if (dragging.type === "racks") {
                saveDevice(s);
            } else {
                changeRoutePosition(s.route_id, {
                    position_x: s.position_x,
                    position_y: s.position_y,
                });
            }
        }
        dragging = null;
    }
};

const loadData = async () => {
    showLoading();
    const result = await getLayerConfig(props.object.id);
    hideLoading();
    update(result);
};

const removeDevice = async () => {
    showLoading();
    let result = await destroyDevice(currentDevice.value.id);
    hideLoading();
    if (result) {
        update(result);
        message("Rack eliminado correctamente", "success");
    } else {
        message("No se ha podido eliminar este rack", "error");
    }
    showConfirm.value = false;
};

const update = async (data) => {
    setDefaultConnections(data);
    await nextTick(() => {
        redrawConnections(workspace);
    });
    setNullablePorts();
};

const fusionAll = async (fiber) => {
    if (!props.hasEdit) {
        return;
    }
    enableMultiConnections.value = false;
    if (
        !fromSelected.value ||
        fromSelected.value.model_type !== "App\\Models\\MapLayer"
    ) {
        if (fromSelected.value) {
            fromSelected.value.selected = false;
        }
        fiber["selected"] = true;
        fromSelected.value = fiber;
    } else {
        if (fromSelected.value.route_id === fiber.route_id) {
            fiber.selected = !fiber.selected;
        } else {
            if (!fromSelected.value.selected) {
                fiber["selected"] = true;
                fromSelected.value = fiber;
            } else {
                fiber.selected = true;
                toSelected.value = fiber;
                showJunction.value = true;
            }
        }
    }
};

const togglePort = async (port) => {
    if (!props.hasEdit) {
        return;
    }
    if (enableMultiConnections.value) {
        if (!fromSelected.value) {
            port.selected = true;
            fromSelected.value = port;
        } else if (!fromSelected1.value) {
            const startDevice = getDeviceFromPort(fromSelected.value);
            const endDevice = getDeviceFromPort(port);
            if (
                startDevice.type !== endDevice.type ||
                startDevice.id !== endDevice.id ||
                (startDevice.type === "polyline" &&
                    endDevice.type === "polyline" &&
                    startDevice.route_id !== endDevice.route_id)
            ) {
                message(
                    "El puerto a seleccionar debe pertenecer al dispositivo del puerto seleccionado anteriormente",
                    "error"
                );
                return;
            } else if (
                startDevice.type === endDevice.type &&
                startDevice.id === endDevice.id &&
                fromSelected.value.id === port.id
            ) {
                fromSelected.value = null;
                port.selected = false;
                return;
            }
            const ports = getPortsFromDevice(startDevice);
            let f = ports.findIndex((p) => p.id === fromSelected.value.id);
            let t = ports.findIndex((p) => p.id === port.id);
            if (f > t) {
                let temp = t;
                t = f;
                f = temp;
            }
            for (let i = f; i <= t; i++) {
                if (!isAvaiableConnection(ports[i])) {
                    message(
                        "Operación no permitida; puertos intermedios no disponibles en el origen",
                        "error"
                    );
                    unselectAllPort();
                    return;
                }
                ports[i].selected = true;
            }
            fromSelected1.value = port;
            totalSelected = t - f + 1;
        } else if (!toSelected.value) {
            const startDevice = getDeviceFromPort(fromSelected.value);
            const endDevice = getDeviceFromPort(port);
            if (
                (startDevice.type === endDevice.type &&
                    startDevice.id === endDevice.id &&
                    startDevice.type !== "polyline") ||
                (startDevice.type === endDevice.type &&
                    startDevice.id === endDevice.id &&
                    startDevice.type === "polyline" &&
                    startDevice.route_id === endDevice.route_id)
            ) {
                message(
                    "El puerto a seleccionar no debe pertenecer al dispositivo del puerto seleccionado anteriormente",
                    "error"
                );
                return;
            }
            const ports = getPortsFromDevice(endDevice);
            let f = ports.findIndex((p) => p.id === port.id);
            for (let i = f; i < f + totalSelected; i++) {
                if (!ports[i]) {
                    message(
                        "Operación no permitida; los puertos destinos no coinciden con la cantidad de puertos origen",
                        "error"
                    );
                    return;
                } else if (!isAvaiableConnection(ports[i])) {
                    message(
                        "Operación no permitida; puertos intermedios no disponibles en el destino",
                        "error"
                    );
                    return;
                }
            }
            toSelected.value = port;
        }
        if (fromSelected.value && fromSelected1.value && toSelected.value) {
            createConnection();
        }
    } else {
        if (
            !fromSelected.value ||
            fromSelected.value.model_type === "App\\Models\\MapLayer"
        ) {
            if (fromSelected.value) {
                fromSelected.value.selected = false;
            }
            port["selected"] = true;
            fromSelected.value = port;
        } else {
            if (fromSelected.value.element_id === port.element_id) {
                port.selected = !port.selected;
            } else {
                if (!fromSelected.value.selected) {
                    port["selected"] = true;
                    fromSelected.value = port;
                } else {
                    const { valid, msg } = validateConnection(
                        fromSelected.value,
                        port
                    );
                    if (valid) {
                        const connectionType = getConnectionType(
                            fromSelected.value,
                            port
                        );
                        port.selected = true;
                        toSelected.value = port;
                        if (connectionType === "fiber-to-fiber") {
                            showJunction.value = true;
                        } else if (
                            (isOlt(fromSelected.value) &&
                                isOutPort(fromSelected.value)) ||
                            (isOlt(toSelected.value) &&
                                isOutPort(toSelected.value))
                        ) {
                            showZone.value = true;
                        } else {
                            createConnection();
                        }
                    } else {
                        if (msg !== null) {
                            message(msg, "error");
                        } else {
                            fromSelected.value.selected = false;
                        }
                        port.selected = false;
                    }
                }
            }
        }
    }
};

const onHideJunction = () => {
    showJunction.value = false;
    if (toSelected.value) {
        toSelected.value.selected = false;
    }
};

const onHideZone = () => {
    showZone.value = false;
    if (toSelected.value) {
        toSelected.value.selected = false;
    }
};

const createConnection = async (data = null) => {
    showLoading();
    let result;
    const multiple = fromSelected.value?.model_type === "App\\Models\\MapLayer";
    if (enableMultiConnections.value) {
        const avaiables = getAvaiablesConnectionsPerRange(
            props.object.id,
            data
        );
        if (avaiables.length === 0) {
            hideLoading();
            return;
        }
        result = await saveConnectionMultiple(props.object.id, avaiables);
    } else if (multiple) {
        const avaiables = getAvaiablesConnections(props.object.id, data);
        if (avaiables.length === 0) {
            message("No existen conexiones disponibles entre estas troncales");
            hideLoading();
            return;
        }
        result = await saveConnectionMultiple(props.object.id, avaiables);
    } else {
        result = await saveConnection(
            getAttributesConnection(
                fromSelected.value,
                toSelected.value,
                props.object.id,
                data
            )
        );
    }
    hideLoading();
    if (result) {
        update(result);
        message(
            multiple || enableMultiConnections
                ? "Conexiones guardadas correctamente"
                : "Conexión guardada correctamente"
        );
        showJunction.value = false;
        showZone.value = false;
    } else {
        message(
            multiple
                ? "Error al tratar de crear estas conexiones"
                : "Error al tratar de crear esta conexión",
            "error"
        );
    }
};

const onChangeDirectionRoute = async (route, direction) => {
    route.direction = direction;
    await nextTick(() => {
        redrawConnections(workspace);
    });
};

const onChangeOrganizerView = async (device) => {
    device.active = device.active === "in" ? "out" : "in";
    await nextTick(() => {
        redrawConnections(workspace);
    });
};

const onRedraw = async () => {
    await nextTick(() => {
        redrawConnections(workspace);
    });
};

const onChangeStateMenu = async () => {
    redrawConnections(workspace);
};

const onTabChange = async () => {
    setConfigToStorage();
    hideAllMenu.value = true;
    await nextTick(() => {
        redrawConnections(workspace);
    });
};

const setConfigToStorage = () => {
    setToLocalStorage(
        "tab-config",
        JSON.stringify({
            tab: tabDevice.value,
            charole: null,
            type: "site",
            key: props.object.key,
        })
    );
};
</script>

<style scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
</style>
