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
                        >Configurar caja de servicio
                        {{ object.name }}</q-toolbar-title
                    >
                    <q-btn
                        no-caps
                        flat
                        dense
                        icon="link"
                        label="Empalmes"
                        @click="showEmpalmes = true"
                        ><q-tooltip>Uniones de hilos de este NAP</q-tooltip></q-btn
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
                        v-if="tabCharoles !== 'marker-information'"
                        ><q-tooltip>{{
                            drawerLeft
                                ? "Ocultar entradas de la izquierda"
                                : "Mostrar entradas de la izquierda"
                        }}</q-tooltip></q-btn
                    >
                    <q-btn
                        @click="loadConfig"
                        color="primary"
                        icon="mdi-sync"
                        round
                        style="font-size: 11px"
                        v-if="tabCharoles !== 'marker-information'"
                        ><q-tooltip>Actualizar</q-tooltip></q-btn
                    >

                    <form-splitter-component
                        :box="object"
                        :parent_id="currentCharole?.id ?? null"
                        :btn="true"
                        @created="(d) => devices.push(d)"
                        v-if="hasEdit && tabCharoles !== 'marker-information'"
                    />

                    <client-to-service-box-component
                        :object="object"
                        :parent_id="currentCharole?.id ?? null"
                        @change="onChangeClients"
                        v-if="hasEdit && tabCharoles !== 'marker-information'"
                    />
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
                        v-if="hasEdit && tabCharoles !== 'marker-information'"
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
                        v-model="tabCharoles"
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
                            v-for="(charole, index) in charoles"
                            :key="`charole-${charole.id}`"
                            :name="`charole-${charole.id}`"
                            :label="`Charola-${index + 1}`"
                            style="width: auto"
                            @click="currentCharole = charole"
                        />
                        <q-btn
                            no-caps
                            icon="add"
                            flat
                            class="q-mx-md"
                            @click="createCharole"
                            v-if="hasEdit"
                        >
                            <q-tooltip>Adicionar charola</q-tooltip>
                        </q-btn>
                    </q-tabs>
                    <q-btn
                        round
                        dense
                        icon="menu"
                        color="primary"
                        @click="drawerRight = !drawerRight"
                        v-if="tabCharoles !== 'marker-information'"
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
                v-show="tabCharoles !== 'marker-information'"
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
                v-show="tabCharoles !== 'marker-information'"
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
                        v-show="tabCharoles === 'marker-information'"
                    >
                        <div class="self-center" style="width: 500px">
                            <box-service-component
                                :in-dialog="false"
                                :object="object"
                                :has-edit="hasEdit"
                            />
                        </div>
                    </div>
                    <div
                        class="map-workspace-container"
                        ref="workspace"
                        v-show="tabCharoles !== 'marker-information'"
                    >
                        <div
                            class="potencia-caja-badge"
                            :class="cajaColor"
                            style="position: absolute; top: 6px; left: 8px; z-index: 5; padding: 4px 10px; border-radius: 6px; font-weight: 600; background: rgba(255,255,255,0.92); color: #222; border: 1px solid #999"
                        >
                            <span v-if="potencia?.potencia_caja_dbm != null">
                                Caja: {{ potencia.potencia_caja_dbm.toFixed(1) }} dBm
                            </span>
                            <span v-else>Caja: sin lecturas</span>
                            <q-btn
                                flat
                                round
                                dense
                                size="xs"
                                icon="refresh"
                                :loading="cargandoPotencia"
                                @click.stop="cargarPotencia(true)"
                            />
                            <q-btn
                                flat
                                round
                                dense
                                size="xs"
                                icon="show_chart"
                                @click.stop="abrirHistorial"
                            >
                                <q-tooltip>Historial de potencia</q-tooltip>
                            </q-btn>
                            <q-tooltip v-if="potencia?.potencia_caja_dbm != null">
                                Cliente más débil
                                {{ potencia.cliente_mas_debil_dbm.toFixed(1) }} +
                                {{ potencia.margen_db }} dB ·
                                {{ potencia.con_lectura }} con lectura,
                                {{ potencia.sin_onu }} sin ONU
                            </q-tooltip>
                        </div>
                        <q-dialog v-model="historialAbierto">
                            <q-card style="min-width: 360px">
                                <q-card-section class="text-subtitle1">
                                    Historial de potencia de la caja
                                </q-card-section>
                                <q-card-section>
                                    <q-btn-toggle
                                        v-model="historialDias"
                                        dense
                                        no-caps
                                        toggle-color="primary"
                                        :options="[
                                            { label: '30 días', value: 30 },
                                            { label: '90 días', value: 90 },
                                        ]"
                                        @update:model-value="cargarHistorial"
                                    />
                                    <div
                                        v-if="historial?.puntos?.length"
                                        class="q-mt-md"
                                    >
                                        <svg viewBox="0 0 300 100" width="100%" height="120">
                                            <polyline
                                                :points="historialPuntosSvg"
                                                fill="none"
                                                stroke="#1976d2"
                                                stroke-width="2"
                                            />
                                        </svg>
                                        <div>
                                            Actual:
                                            {{ historial.potencia_actual_dbm.toFixed(1) }} dBm
                                        </div>
                                        <div v-if="historial.bajo_db != null">
                                            <span
                                                v-if="historial.bajo_db > 0"
                                                class="text-negative"
                                            >
                                                Bajó {{ historial.bajo_db }} dB desde la instalación
                                            </span>
                                            <span v-else>
                                                Sin pérdida desde la instalación
                                                ({{ historial.bajo_db }} dB)
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="q-mt-md text-grey">
                                        Sin lecturas en este periodo.
                                    </div>
                                </q-card-section>
                            </q-card>
                        </q-dialog>
                        <template
                            v-for="device in routes"
                            :key="`route-${device.id}`"
                        >
                            <div @mousedown="startDrag($event, device)">
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
                        <template
                            v-for="device in devices.filter(
                                (d) => d.parent_id === currentCharole.id
                            )"
                            :key="'device-' + device.id"
                        >
                            <div @mousedown="startDrag($event, device)">
                                <splitter-component
                                    :device="device"
                                    :has-edit="hasEdit"
                                    cls="map-device"
                                    @toggle="togglePort"
                                    @redraw="onRedraw"
                                    @removed="update"
                                    @update="update"
                                    v-if="device.type === 'splitter'"
                                />
                                <client-component
                                    :potencia="potenciaDe(device)"
                                    :device="device"
                                    :has-edit="hasEdit"
                                    @toggle="togglePort"
                                    @update="update"
                                    v-else-if="device.type === 'client'"
                                />
                            </div>
                        </template>
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

            <q-footer
                elevated
                class="bg-grey-3 text-black"
                v-if="
                    dropOut &&
                    tabCharoles !== 'marker-information' &&
                    showFooter
                "
            >
                <drop-out
                    :device="dropOut"
                    :has-edit="hasEdit"
                    @update="update"
                />
            </q-footer>
        </q-layout>
    </q-dialog>

    <form-junction
        :show="showJunction"
        @hide="onHideJunction"
        @save="(data) => createConnection(data)"
    />

    <empalme-config-dialog
        :show="showEmpalmes"
        :object="object"
        :has-edit="hasEdit"
        @hide="showEmpalmes = false"
    />
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from "vue";
import FormSplitterComponent from "../devices/FormSplitterComponent.vue";
import SplitterComponent from "../devices/SplitterComponent.vue";
import ClientComponent from "../devices/ClientComponent.vue";
import ClientToServiceBoxComponent from "../ClientToServiceBoxComponent.vue";
import RouteComponent from "../devices/RouteComponent.vue";
import SvgConnections from "../others/SvgConnections.vue";
import FormJunction from "../devices/FormJunction.vue";
import BoxServiceComponent from "../BoxServiceComponent.vue";
import TroncalInput from "../others/TroncalInput.vue";
import DropOut from "../devices/DropOut.vue";
import EmpalmeConfigDialog from "./EmpalmeConfigDialog.vue";
import { dom } from "../../../../../../../public/plugins/quasar/js/quasar.umd.prod";
import { message } from "../../../../../helpers/toastMsg";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import {
    saveConnection,
    saveConnectionMultiple,
} from "../../helper/connections-request";
import {
    changeRoutePosition,
    getLayerConfig,
} from "../../helper/layers-request";
import { saveDevice } from "../../helper/devices-request";
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
    unselectAllPort,
    isAvaiableConnection,
    getPortsFromDevice,
    getDeviceFromPort,
    getAvaiablesConnectionsPerRange,
    setNullablePorts,
    addDevice,
    devices,
    inputs,
    routes,
    setDefaultConnections,
    enableMultiConnections,
    isLayerModel,
} from "../../../../../composables/useMapConnections";
import {
    getFromLocalStorage,
    setToLocalStorage,
} from "../../../../../composables/useLocalStorage";
import { currentNode } from "../../../../../composables/useNodeMap";

defineOptions({
    name: "ServiceBoxConfiguration",
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

const showFooter = ref(true);

const dialog = ref(false);
const workspace = ref(null);
let dragging = null;

const minX = 20;
const minY = 20;
const showJunction = ref(false);
const tabCharoles = ref("marker-information");
const currentCharole = ref(null);
let totalSelected = 0;
const drawerLeft = ref(true);
const drawerRight = ref(true);
const showEmpalmes = ref(false);

const { ready } = dom;

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

watch(currentCharole, async () => {
    setConfigToStorage();
});

const dropOut = computed(() => {
    return devices.value.find((d) => d.type === "drop") || null;
});

const charoles = computed(() => {
    const list = devices.value.filter((d) => d.type === "charole");
    if (currentCharole.value === null) {
        if (list.length > 0) {
            currentCharole.value = list[0];
        }
    }
    return list;
});

const onShowDialog = async () => {
    let tab = JSON.parse(getFromLocalStorage("tab-config")) ?? null;
    const hasStoredTab =
        tab && tab.type === "service_box" && tab.key === props.object.key;
    tabCharoles.value = hasStoredTab ? tab.tab : "marker-information";
    currentCharole.value = hasStoredTab ? tab.charole : null;
    setToLocalStorage("dialog-config", "service_box_config");
    setToLocalStorage("layer-config", JSON.stringify(props.object));
    currentNode.value = props.object;
    await loadConfig();
    cargarPotencia(true);
    if (!hasStoredTab && charoles.value.length > 0) {
        tabCharoles.value = `charole-${charoles.value[0].id}`;
    }
};

const onChangeClients = (change) => {
    if (change) {
        loadConfig();
    }
};

const onHide = () => {
    setDefaultConnections();
    emits("hide");
    setToLocalStorage("dialog-config", null);
    setToLocalStorage("layer-config", null);
    currentNode.value = null;
};

watch(tabCharoles, async (n) => {
    if (n) {
        if (n === "marker-information") {
            drawerLeft.value = false;
            drawerRight.value = false;
        }
        await nextTick(() => {
            redrawConnections(workspace);
        });
    }
});

const createCharole = async () => {
    showLoading();
    const result = await saveDevice({
        name: "Charole",
        layer_id: props.object.id,
        position_x: 20,
        position_y: 20,
        type: "charole",
    });
    hideLoading();
    if (result) {
        currentCharole.value = result;
        addDevice(currentCharole.value);
        tabCharoles.value = `charole-${currentCharole.value.id}`;
        message("Charola adicionada correctamente", "success");
    } else {
        message(
            "Error al tratar de agregar la charola; consulte al administrador",
            "error"
        );
    }
};

const potencia = ref(null);
const cargandoPotencia = ref(false);

const cargarPotencia = async (enVivo = true) => {
    cargandoPotencia.value = true;
    try {
        const { data } = await axios.get(
            `/mapa-red/api/service-box/${props.object.id}/potencia`,
            { params: enVivo ? {} : { sin_vivo: 1 } }
        );
        potencia.value = data;
    } catch (e) {
        potencia.value = null;
    } finally {
        cargandoPotencia.value = false;
    }
};

const historialAbierto = ref(false);
const historialDias = ref(30);
const historial = ref(null);

const cargarHistorial = async () => {
    try {
        const { data } = await axios.get(
            `/mapa-red/api/naps/${props.object.id}/potencia-historial`,
            { params: { dias: historialDias.value } }
        );
        historial.value = data;
    } catch (e) {
        historial.value = null;
    }
};

const abrirHistorial = () => {
    historialAbierto.value = true;
    cargarHistorial();
};

// Polilínea 300x100; más arriba = mejor señal (más cercano a cero).
const historialPuntosSvg = computed(() => {
    const pts = historial.value?.puntos ?? [];
    if (!pts.length) return "";
    const vals = pts.map((p) => p.minima_dbm);
    const min = Math.min(...vals);
    const max = Math.max(...vals);
    const rango = max - min || 1;
    return pts
        .map((p, i) => {
            const x = pts.length === 1 ? 150 : (i / (pts.length - 1)) * 300;
            const y = 95 - ((p.minima_dbm - min) / rango) * 90;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(" ");
});

const potenciaDe = (device) => {
    const portId = device.ports?.[0]?.id;
    return potencia.value?.clientes?.find((c) => c.port_id === portId) ?? null;
};

const cajaColor = computed(() => {
    const v = potencia.value?.potencia_caja_dbm;
    if (v == null) return "";
    return v <= -27 ? "text-negative" : v <= -24 ? "text-warning" : "";
});

const loadConfig = async () => {
    showLoading();
    const result = await getLayerConfig(props.object.id);
    hideLoading();
    if (result) {
        update(result);
    } else {
        message(
            "Error al tratar de obtener la configuración. Consulte al administrador",
            "error"
        );
    }
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
        !isLayerModel(fromSelected.value)
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
            isLayerModel(fromSelected.value)
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

const createConnection = async (data = null) => {
    showLoading();
    let result;
    const multiple = isLayerModel(fromSelected.value);
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
            multiple
                ? "Conexiones guardadas correctamente"
                : "Conexión guardada correctamente"
        );
        showJunction.value = false;
    } else {
        message(
            multiple
                ? "Error al tratar de crear estas conexiones"
                : "Error al tratar de crear esta conexión",
            "error"
        );
    }
};

const startDrag = (e, device) => {
    if (!e.target.classList.contains("draggable")) return;
    e.preventDefault();
    dragging = {
        device,
        startX: e.clientX,
        startY: e.clientY,
        originalX: device.position_x,
        originalY: device.position_y,
    };
};

const handleMouseMove = (e) => {
    if (dragging && props.hasEdit) {
        const dx = e.clientX - dragging.startX;
        const dy = e.clientY - dragging.startY;
        const object = dragging.device;
        object.position_x = Math.max(minX, dragging.originalX + dx);
        object.position_y = Math.max(minY, dragging.originalY + dy);
        redrawConnections(workspace);
    }
};

const handleMouseUp = () => {
    if (props.hasEdit) {
        const device = dragging?.device ?? null;
        if (
            device &&
            (device.position_x !== dragging.originalX ||
                device.position_y !== dragging.originalY)
        ) {
            if (device.type === "polyline") {
                changeRoutePosition(device.route_id, {
                    position_x: device.position_x,
                    position_y: device.position_y,
                });
            } else {
                saveDevice(device);
            }
        }
        dragging = null;
    }
};

const onChangeDirectionRoute = async (route, direction) => {
    route.direction = direction;
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

const onTabChange = async (val) => {
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
            tab: tabCharoles.value,
            charole: currentCharole.value,
            type: "service_box",
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
