import { computed, ref } from "vue";
import { sincronizeRoutes } from "./useNodeMap";
import { colors } from "../components/module/maps/helper/mapUtils";

export const devices = ref([]);
export const connections = ref([]);
export const routes = ref([]);
export const inputs = ref(0);
export const enableMultiConnections = ref(false);

export const hideAllMenu = ref(false);
export const avaiablesSizes = [
    { name: "xs", value: 0 },
    { name: "sm", value: 1 },
    { name: "md", value: 2 },
];
export const fromSelected = ref(null);
export const fromSelected1 = ref(null);
export const toSelected = ref(null);

export const setDefaultConnections = (data = null) => {
    devices.value = data?.devices ?? [];
    connections.value = data?.connections ?? [];
    routes.value = data?.routes ?? [];
    inputs.value = data?.inputs ?? 0;
    setContinuosConnections();
    sincronizeRoutes();
};

export const racks = computed(() => {
    return devices.value.filter((d) => d.type === "rack");
});

export const addDevice = (device) => {
    if (device) {
        devices.value.push(device);
    }
};

export const getAttributesConnection = (
    port1,
    port2,
    layer_id = null,
    data = null
) => {
    let inPort = null,
        outPort = null;
    if (port1.type === "in") {
        inPort = port1;
        outPort = port2;
    } else {
        inPort = port2;
        outPort = port1;
    }
    const connection_type = getConnectionType(port1, port2);
    const isFromFiber = isFiber(outPort);
    const isToFiber = isFiber(inPort);
    return {
        from_id: outPort.id,
        from_type: outPort.model_type,
        from_element: outPort.element_id,
        to_id: inPort.id,
        to_type: inPort.model_type,
        to_element: inPort.element_id,
        connection_type,
        from_route_id: isFromFiber ? outPort.element_id.split("-")[3] : null,
        to_route_id: isToFiber ? inPort.element_id.split("-")[3] : null,
        from_input: isFromFiber ? outPort.current_input : 0,
        to_input: isToFiber ? inPort.current_input : 0,
        layer_id,
        data: data,
    };
};

export const getDeviceFromPort = (port) => {
    let isFiberPort = isFiber(port);
    if (isFiberPort) {
        return routes.value.find(
            (d) => d.id === port.fiber_id && d.route_id === port.route_id
        );
    }
    return devices.value.find((d) => d.id === port.device_id);
};

export const getPortsFromDevice = (device) => {
    if (device.type === "polyline") {
        return device.fibers;
    }
    return device.ports;
};

export const getAvaiablesConnectionsPerRange = (
    layer_id = null,
    data = null
) => {
    let connections = [],
        fromPorts = [],
        toPorts = [];
    let device = getDeviceFromPort(fromSelected.value);
    let ports = getPortsFromDevice(device);
    let f = ports.findIndex((p) => p.id === fromSelected.value.id);
    let t = ports.findIndex((p) => p.id === fromSelected1.value.id);
    if (f > t) {
        let temp = t;
        t = f;
        f = temp;
    }
    for (let i = f; i <= t; i++) {
        fromPorts.push(ports[i]);
    }
    device = getDeviceFromPort(toSelected.value);
    ports = getPortsFromDevice(device);
    f = ports.findIndex((p) => p.id === toSelected.value.id);
    for (let i = f; i < f + fromPorts.length; i++) {
        toPorts.push(ports[i]);
    }
    for (let i = 0; i < fromPorts.length; i++) {
        connections.push(
            getAttributesConnection(fromPorts[i], toPorts[i], layer_id, data)
        );
    }
    return connections;
};

export const isOlt = (port) => {
    return port.device?.type === "olt";
};

export const isOutPort = (port) => {
    return port.type === "out";
};

export const isFiber = (port) => {
    return port.model_type === "App\\Models\\MapFiber";
};

export const isFiberOltConnection = (from, to) => {
    if (
        (isFiber(from) && to["device"]?.type === "olt") ||
        (isFiber(to) && from["device"]?.type === "olt")
    ) {
        return true;
    }
    return false;
};

export const getColorFromPort = (port) => {
    const conn = getConnectionByPort(port);
    if (conn) {
        if (conn.connection_type === "fiber-to-port") {
            const fiber = isFiber(conn.from) ? conn.from : conn.to;
            const bufferColor = colors[fiber.buffer - 1];
            return {
                color: fiber.color,
                textColor:
                    colors.find((c) => c.css === fiber.color)?.text ?? "white",
                border: `border: 4px solid ${bufferColor.hex} !important; margin-top: 1px !important; padding: 2px !important`,
            };
        }
        return {
            color: "primary",
            textColor: "white",
            border: null,
        };
    }
    return {
        color: "grey-1",
        textColor: "black",
        border: null,
    };
};

export const getConnectionByPort = (port) => {
    const conn = isFiber(port)
        ? connections.value.find(
              (c) =>
                  (c.from_id === port.id &&
                      c.from_type === port.model_type &&
                      c.from_input === port.current_input) ||
                  (c.to_id === port.id &&
                      c.to_type === port.model_type &&
                      c.to_input === port.current_input)
          )
        : connections.value.find(
              (c) =>
                  (c.from_id === port.id && c.from_type === port.model_type) ||
                  (c.to_id === port.id && c.to_type === port.model_type)
          );
    return conn ?? null;
};

export const isAvaiableConnection = (port) => {
    return getConnectionByPort(port) === null;
};

export const getAvaiablesConnections = (layer_id = null, data = null) => {
    let connections = [];
    fromSelected.value.fibers.forEach((f) => {
        if (isAvaiableConnection(f)) {
            const to = toSelected.value.fibers.find(
                (t) =>
                    t.parent_buffer === f.parent_buffer &&
                    t.buffer === f.buffer &&
                    t.number === f.number
            );
            if (to && isAvaiableConnection(to)) {
                connections.push({
                    ...getAttributesConnection(f, to, layer_id, data),
                });
            }
        }
    });
    return connections;
};

export const validateConnection = (from, to) => {
    const fromId = from.element_id;
    const fromModel = from.model_type;
    //const fromType = from.type;
    const toId = to.element_id;
    const toModel = to.model_type;
    //const toType = to.type;
    if (fromId === toId && fromModel === toModel) {
        return {
            valid: false,
            msg: null,
        };
    }
    if (
        !isFiber(from) &&
        !isFiber(to) &&
        from.device_type === to.device_type &&
        from.device_type === "organizer" &&
        from.device_id === to.device_id
    ) {
        return {
            valid: false,
            msg: "No se pueden conectar dos puertos del mismo organizador",
        };
    }
    return {
        valid: true,
        msg: "ok",
    };
};

export const getConnectionType = (from, to) => {
    if (
        from.model_type === "App\\Models\\MapFiber" &&
        to.model_type === "App\\Models\\MapFiber"
    ) {
        return "fiber-to-fiber";
    }
    if (
        from.model_type === "App\\Models\\MapDevicePort" &&
        to.model_type === "App\\Models\\MapDevicePort"
    ) {
        return "port-to-port";
    }
    return "fiber-to-port";
};

export const redrawConnections = async (container) => {
    await connections.value.forEach((c) => {
        if (!Object.keys(c).includes("visible")) {
            c["visible"] = true;
        }
        c["path"] = getPathFromConnection(c, container);
    });
};

export const getPathFromConnection = (connection, container) => {
    if (connection.visible) {
        const fromPort = connection.from;
        const toPort = connection.to;

        if (!fromPort || !toPort) return "";

        const fromDevice =
            devices.value.find((s) => s.id === fromPort.device_id) ??
            routes.value.find((s) => s.id === fromPort.fiber_id);
        const toDevice =
            devices.value.find((s) => s.id === toPort.device_id) ??
            routes.value.find((s) => s.id === toPort.fiber_id);

        const fromDirection =
            fromDevice.type === "polyline" ? "direction" : "orientation";
        const toDirection =
            toDevice.type === "polyline" ? "direction" : "orientation";

        const fromEl = document.getElementById(connection.from_element);
        const fromRect = fromEl ? fromEl.getBoundingClientRect() : null;
        const toEl = document.getElementById(connection.to_element);
        const toRect = toEl ? toEl.getBoundingClientRect() : null;

        if (fromRect && toRect) {
            const containerRect = container.value.getBoundingClientRect();
            let startX =
                fromRect.left - containerRect.left + fromRect.width / 2;
            let startY =
                fromRect.top - containerRect.top + fromRect.height / 2;
            let endX = toRect.left - containerRect.left + toRect.width / 2;
            let endY = toRect.top - containerRect.top + toRect.height / 2;

            if (startX < endX) {
                startX += 10;
                endX -= 10;
            }
            else {
                startX -= 10;
                endX += 10;
            }

            let ctrlX1 = startX + (endX - startX) / 2;
            let ctrlY1 = startY;
            let ctrlX2 = startX + (endX - startX) / 2;
            let ctrlY2 = endY;

            console.log(startX, endX);
            

            return `M${startX}, ${startY} C${ctrlX1},${ctrlY1} ${ctrlX2},${ctrlY2} ${endX},${endY}`;
        } else {
            return null;
        }
    }
    return null;
};

export const disableConnections = (ports, state) => {
    ports.forEach((p) => {
        const conn = getConnectionByPort(p);
        if (conn) {
            conn.visible = state;
        }
    });
};

export const changePortState = (port, connected) => {
    const p =
        devices.value.find((d) => d.id === port.device_id) ??
        routes.value.find((d) => d.id === port.fiber_id);
    if (p) {
        const currentPort =
            p.ports?.find((pp) => pp.id === port.id) ??
            p.fibers?.find((pp) => pp.id === port.id);
        if (currentPort) {
            currentPort.connected = connected;
            currentPort.selected = false;
        }
    }
};

export const setNullablePorts = () => {
    if (toSelected.value) {
        toSelected.value["selected"] = false;
    }
    if (fromSelected.value) {
        fromSelected.value["selected"] = false;
    }
    if (fromSelected1.value) {
        fromSelected1.value["selected"] = false;
    }
    toSelected.value = null;
    fromSelected.value = null;
    fromSelected1.value = null;
};

export const unselectAllPort = () => {
    setNullablePorts();
    routes.value.forEach((d) => {
        d.fibers.forEach((p) => {
            p.selected = false;
        });
    });
    devices.value.forEach((d) => {
        d.ports.forEach((p) => {
            p.selected = false;
        });
    });
};

const setContinuosConnections = () => {
    routes.value.forEach((r) => {
        if (!r["checked"]) {
            r["checked"] = true;
            const counterpart = getCounterpart(r);
            if (counterpart && !counterpart["checked"]) {
                counterpart["checked"] = true;
                r.fibers.forEach((f, index) => {
                    const from = f;
                    const to = counterpart.fibers[index];
                    if (!from.current_cut && !to.current_cut) {
                        const con1 = getConnectionByPort(from);
                        const con2 = getConnectionByPort(to);
                        if (!con1 && !con2) {
                            connections.value.push({
                                id: null,
                                from_type: "App\\Models\\MapFiber",
                                from_id: from.id,
                                from_input: from.current_input,
                                to_type: "App\\Models\\MapFiber",
                                to_id: to.id,
                                to_input: to.current_input,
                                from_element: `polyline-port-${from.id}-${r.route_id}`,
                                to_element: `polyline-port-${to.id}-${counterpart.route_id}`,
                                from_route_id: r.route_id,
                                to_route_id: counterpart.route_id,
                                connection_type: "fiber-to-fiber",
                                type: "default",
                                color: null,
                                width: 4,
                                animate: "default",
                                from: f,
                                to: counterpart.fibers[index],
                            });
                        }
                    }
                });
            }
        }
    });
};

export const acceptedFiberConnection = (route, fiber) => {
    if (fiber.current_cut?.state === "open") {
        return true;
    } else {
        const con = getConnectionByPort(fiber);
        if (con) {
            return false;
        }
        return true;
    }
};

export const getCounterpart = (route) => {
    const found = routes.value.find(
        (r) => r.id === route.id && r.current_input !== route.current_input
    );
    return found ?? null;
};

export const getDataFromType = (type, index) => {
    let s = null;
    switch (type) {
        case "devices":
            s = devices.value[index];
            break;
        case "routes":
            s = routes.value[index];
            break;
        case "racks":
            s = racks.value[index];
            break;
        default:
            s = null;
            break;
    }
    return s;
};

export function useMapConnections() {
    return {
        devices,
        connections,
        routes,
        racks,
        setDefaultConnections,
        getDataFromType,
    };
}
