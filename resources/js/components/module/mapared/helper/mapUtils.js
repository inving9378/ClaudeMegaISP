import { ref, reactive, nextTick } from "vue";
import L from "leaflet";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";

import "leaflet-notifications/css/leaflet-notifications.css";
import "leaflet-notifications/js/leaflet-notifications.js";

import * as turf from "@turf/turf";
import {
    addClientToServiceBox,
    destroyObject,
    updateCoordinates,
} from "./request";
import Swal from "sweetalert2";
import { message } from "../../../../helpers/toastMsg";

import Permission from "../../../../helpers/Permission";

import { isFullScreen } from "../../../../composables/useFullScreen";
import {
    allNodes,
    currentNode,
    getNodeByKey,
    nodeMap,
    tickedNodes,
} from "../../../../composables/useNodeMap";
import { eq, isEqual } from "lodash";
import { hideLoading, showLoading } from "../../../../helpers/loading";
import { updateMarkersDistanceFromRoute } from "./layers-request";

const permissions = reactive({
    data: new Permission({}),
});

export const dialogs = ref({
    folder: null,
    region: false,
    route: false,
    junction_box: false,
    service_box: false,
    pack: false,
    cupboard: false,
    source: false,
    pole: false,
    client: false,
    building: false,
    site: false,
    note: false,
    client_to_project: false,
    elements_in_serie: false,
    kmz: false,
    junction_box_config: false,
    service_box_config: false,
    cupboard_config: false,
    site_config: false,
});
export const currentObject = ref(null);
export const currentMarker = ref(null);
let layerEditing = null;
export const hasLayerEdit = ref(false);
export const showDialogConfirm = ref(false);

export const drawnItems = L.markerClusterGroup({
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
    zoomToBoundsOnClick: true,
    maxClusterRadius: 80,
    disableClusteringAtZoom: 17,
});

export const drawClientsServiceBox = new L.FeatureGroup();
export const layerClientServiceBox = ref(null);

export const errorValidation = () => {
    message("rectifique los errores", "error");
};

export const reloadProjects = ref(false);
export const removedObject = ref(null);

export const awesomeColors = [
    { name: "red", hex: "#d93b3b", rgb: "rgb(217, 59, 59)" },
    { name: "darkred", hex: "#b52b2b", rgb: "rgb(181, 43, 43)" },
    { name: "orange", hex: "#f0ad4e", rgb: "rgb(240, 173, 78)" },
    { name: "green", hex: "#5cb85c", rgb: "rgb(92, 184, 92)" },
    { name: "darkgreen", hex: "#3d8b3d", rgb: "rgb(61, 139, 61)" },
    { name: "blue", hex: "#5bc0de", rgb: "rgb(91, 192, 222)" },
    { name: "purple", hex: "#9467bd", rgb: "rgb(148, 103, 189)" },
    { name: "darkpurple", hex: "#7a4e9e", rgb: "rgb(122, 78, 158)" },
    { name: "pink", hex: "#d633c0", rgb: "rgb(214, 51, 192)" },
    { name: "cadetblue", hex: "#5f9ea0", rgb: "rgb(95, 158, 160)" },
    { name: "white", hex: "#ffffff", rgb: "rgb(255, 255, 255)" },
    { name: "gray", hex: "#808080", rgb: "rgb(128, 128, 128)" },
    { name: "lightgray", hex: "#c0c0c0", rgb: "rgb(192, 192, 192)" },
    { name: "black", hex: "#000000", rgb: "rgb(0, 0, 0)" },
];

export const colors = [
    {
        css: "blue-7",
        hex: "#1e88e5",
        text: "white",
    },
    {
        css: "orange-7",
        hex: "#fb8c00",
        text: "white",
    },
    {
        css: "green-7",
        hex: "#43a047",
        text: "white",
    },
    {
        css: "brown-7",
        hex: "#6d4c41",
        text: "white",
    },
    {
        css: "grey-5",
        hex: "#bdbdbd",
        text: "black",
    },
    {
        css: "white",
        hex: "#fff",
        text: "black",
    },
    {
        css: "red-7",
        hex: "#e53935",
        text: "white",
    },
    {
        css: "black",
        hex: "#000",
        text: "white",
    },
    {
        css: "yellow-7",
        hex: "#fdd835",
        text: "black",
    },
    {
        css: "purple-7",
        hex: "#8e24aa",
        text: "white",
    },
    {
        css: "pink-4",
        hex: "#f06292",
        text: "white",
    },
    {
        css: "indigo-11",
        hex: "#8c9eff",
        text: "black",
    },
];

export const menuOptions = [
    {
        text: "Carpeta",
        icon: "mdi-folder",
        element: null,
        dialog: "folder",
        route: "projects",
        label: "name",
    },
    {
        text: "Región",
        icon: "mdi-vector-polygon",
        element: "leaflet-draw-draw-polygon",
        dialog: "region",
        route: "regions",
        label: "name",
    },
    {
        text: "Ruta",
        icon: "mdi-chart-timeline-variant",
        element: "leaflet-draw-draw-polyline",
        dialog: "route",
        route: "routes",
        label: "name",
    },
    {
        text: "Site",
        icon: "mdi-warehouse",
        element: "leaflet-draw-draw-marker",
        dialog: "site",
        route: "sites",
        label: "name",
    },
    {
        text: "Caja de empalme",
        icon: "mdi-package-variant-closed",
        element: "leaflet-draw-draw-marker",
        dialog: "junction_box",
        route: "junctionboxs",
        label: "name",
    },
    {
        text: "Caja de servicio",
        icon: "mdi-package",
        element: "leaflet-draw-draw-marker",
        dialog: "service_box",
        route: "serviceboxs",
        label: "name",
    },
    {
        text: "Pack",
        icon: "mdi-nas",
        element: "leaflet-draw-draw-marker",
        dialog: "pack",
        route: "packs",
        label: "name",
    },
    {
        text: "Armario",
        icon: "mdi-cupboard-outline",
        element: "leaflet-draw-draw-marker",
        dialog: "cupboard",
        route: "cupboards",
        label: "name",
    },
    {
        text: "Fuente",
        icon: "mdi-flash-outline",
        element: "leaflet-draw-draw-marker",
        dialog: "source",
        route: "sources",
        label: "name",
    },
    {
        text: "Poste",
        icon: "mdi-currency-mnt",
        element: "leaflet-draw-draw-marker",
        dialog: "pole",
        route: "poles",
        label: "name",
    },
    // {
    //     text: "Cliente",
    //     icon: "mdi-account",
    //     element: "leaflet-draw-draw-marker",
    //     dialog: "client",
    //     route: "clients",
    //     label: "name",
    // },
    {
        text: "Edificio",
        icon: "mdi-office-building",
        element: "leaflet-draw-draw-marker",
        dialog: "building",
        route: "buildings",
        label: "name",
    },
    {
        text: "Nota",
        icon: "mdi-message-bulleted",
        element: "leaflet-draw-draw-marker",
        dialog: "note",
        route: "notes",
        label: "name",
    },
    {
        text: "Elementos en serie",
        icon: "mdi-plus-box-multiple",
        element: "leaflet-draw-draw-marker",
        dialog: "elements_in_serie",
        route: "notes",
        label: "name",
    },
];

export const titleLayers = {
    service_box: "Caja de servicio",
    route: "Ruta",
    junction_box: "Caja de empalme",
    pack: "Pack",
    cupboard: "Armario",
    source: "Fuente",
    pole: "Poste",
    client: "Cliente",
    building: "Edificio",
    note: "Nota",
    region: "Región",
    site: "Site",
    kmz: "KMZ",
};

export const objectProperties = {
    name: "Nombre",
    description: "Descripción",
    group: "Grupo",
    ownid: "ID propio",
    grantid: "ID de concesión",
    monthly_rent: "Alquiler mensual",
    ipv4: "IPv4",
    ipv6: "IPv6",
    mac: "Dirección física",
    fibers_amount: "Cantidad de fibras",
    distance: "Distancia (m)",
};

export const excludesProperties = [
    "id",
    "key",
    "coords",
    "description",
    "weight",
    "client_id",
    "level",
    "text_node",
    "classification",
    "parent_id",
    "is_layer",
    "icon",
    "icon_color",
    "dialog",
    "data",
    "properties",
    "type",
    "color",
    "layers",
    "children",
    "parent_key",
];

let clientToBoxService = null;

const getDefaulColor = () => {
    return { name: "blue", hex: "#5bc0de", rgb: "rgb(91, 192, 222)" };
};

const getColorByHex = (hex) => {
    const color = awesomeColors.find((c) => c.hex === hex);
    return color ? color : getDefaulColor();
};

export const addAllPermissions = (perms) => {
    permissions.data = perms;
};

export const getLayerByKeyProperty = (key) => {
    let layer = null;
    drawnItems.eachLayer((l) => {
        if (l.properties?.key === key) {
            layer = l;
        }
    });
    return layer;
};

export const createLayerFromObject = (obj, searched = false) => {
    let layer = null;
    let { type, coords } = obj;
    if (type === "marker") {
        layer = L.marker(coords);
    } else if (type === "polyline") {
        layer = L.polyline(coords);
    } else if (type === "polygon") {
        layer = L.polygon(coords);
    }

    updateLayerFromObject(layer, obj, searched);

    layer.on("click", async function (e) {
        const layer = e.target;
        currentMarker.value = layer.properties;
        if (obj.dialog === "service_box" && clientToBoxService) {
            const data = await addClientToServiceBox(
                clientToBoxService.properties.id,
                layer.properties.id
            );
            if (data) {
                clientToBoxService.properties.line = data;
                layer.properties.line = data;
                resetLayer(clientToBoxService);
                const line = L.polyline(
                    [[data.client_coords, data.box_coords]],
                    {
                        weight: 2,
                        color: "black",
                    }
                );
                line.properties = data;
                line.addTo(drawClientsServiceBox);
            }
        }
    });

    layer.on("dragend", function (event) {
        saveCoordinates(layer);
    });

    return layer;
};

export const setContextMenu = (layer, obj) => {
    let content = [];

    if (obj.classification === "kmz") {
        return;
    }

    if (
        permissions.data.canView(`maps_${obj.dialog}_edit`) &&
        obj.dialog !== "client"
    ) {
        content.push(
            {
                text: "Cambiar ubicación",
                iconCls: `mdi mdi-map`,
                callback: function () {
                    if (layerEditing) {
                        if (layerEditing instanceof L.Polyline) {
                            layerEditing.setLatLngs(
                                layerEditing.properties.coords
                            );
                        } else {
                            layerEditing.setLatLng(
                                layerEditing.properties.coords
                            );
                        }
                        setContextMenu(layerEditing, layerEditing.properties);
                        layerEditing.disableEdit();
                    }
                    showRoutesLayer(layer.properties);
                    layer.enableEdit();
                    layerEditing = layer;
                    hasLayerEdit.value = true;
                    layer.bindContextMenu({
                        contextmenu: true,
                        contextmenuInheritItems: false,
                        contextmenuItems: [
                            {
                                text: "Guardar",
                                iconCls: `mdi mdi-content-save-outline`,
                                callback: function () {
                                    saveCoordinates(layer);
                                },
                            },
                            {
                                text: "Cancelar",
                                iconCls: `mdi mdi-cancel`,
                                callback: function () {
                                    resetCoordinates(layer);
                                },
                            },
                        ],
                    });
                },
            },
            {
                text: "Editar datos",
                iconCls: `mdi mdi-pencil-outline`,
                callback: function () {
                    currentObject.value = obj;
                    dialogs.value[obj.dialog] = true;
                },
            }
        );
        if (obj.dialog === "route") {
            content.push({
                text: "Recalcular distancia de marcadores",
                iconCls: `mdi mdi-pencil-ruler`,
                callback: async function () {
                    const nodes = allNodes.value.filter(
                        (n) => n.is_layer && n.layers?.includes(obj.id)
                    );
                    let distances = [];
                    for (let i = 0; i < nodes.length; i++) {
                        let dist = await getMarkerDistanceInRoute(
                            nodes[i],
                            obj
                        );
                        if (dist) {
                            distances.push(dist);
                        }
                    }
                    if (distances.length > 0) {
                        showLoading();
                        const result = await updateMarkersDistanceFromRoute(
                            obj.id,
                            distances
                        );
                        hideLoading();
                        if (result) {
                            distances.forEach(async (d) => {
                                let key = `layer-${d.marker}`;
                                if (!tickedNodes.value.includes(key)) {
                                    tickedNodes.value.push(key);
                                }
                            });
                            message("Distancias recalculadas correctamente");
                        } else {
                            message(
                                "Error al recalcular las distancias; consulte al administrador",
                                "error"
                            );
                        }
                    } else {
                        message(
                            "No se encontraron marcadores asociados a esta ruta",
                            "info"
                        );
                    }
                },
            });
        }
    }
    if (
        ["service_box", "site", "cupboard", "junction_box"].includes(obj.dialog)
    ) {
        content.push({
            text: "Configurar",
            iconCls: `mdi mdi-cogs`,
            callback: function () {
                currentObject.value = obj;
                dialogs.value[`${obj.dialog}_config`] = true;
            },
        });
    } else if (obj.dialog === "client") {
        content.push({
            text: "Ir al cliente",
            iconCls: `mdi mdi-reply-outline`,
            callback: function () {
                window.open(
                    `/cliente/editar/${obj.properties.client_id}`,
                    "_blank"
                );
            },
        });
    }
    if (
        permissions.data.canView(`maps_${obj.dialog}_remove`) &&
        obj.dialog !== "client"
    ) {
        content.push({
            text: "Eliminar",
            iconCls: `mdi mdi-delete-outline`,
            callback: function (e) {
                Swal.fire({
                    title: "Confirmación!",
                    text: `Seguro que deseas eliminar este(a) ${
                        titleLayers[layer.properties.dialog]
                    }?`,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Si",
                    cancelButtonText: "No",
                    target: isFullScreen.value ? "#map" : "body",
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        let data = await destroyObject(layer.properties);
                        if (data) {
                            removedObject.value = data;
                            if (drawnItems.hasLayer(layer)) {
                                drawnItems.removeLayer(layer);
                                message(
                                    `${
                                        titleLayers[layer.properties.dialog]
                                    } eliminado(a) correctamente`,
                                    "success"
                                );
                            }
                        } else {
                            message(
                                `Error al tratar de eliminar este(a) ${
                                    titleLayers[layer.properties.dialog]
                                }`,
                                "error"
                            );
                        }
                    }
                });
            },
        });
    }
    layer.bindContextMenu({
        contextmenu: true,
        contextmenuInheritItems: false,
        contextmenuItems: content,
    });
};

export const updateLayerFromObject = (layer, obj, searched = false) => {
    layer.properties = obj;
    const { color, icon_color, icon, type } = obj;
    if (type === "marker") {
        const markerColor = getColorByHex(color);
        layer.setIcon(
            searched
                ? L.icon({
                      iconUrl: "/images/pin.png",
                      iconSize: [38, 38],
                      iconAnchor: [10, 45],
                      className: "z-index-marker",
                  })
                : L.AwesomeMarkers.icon({
                      icon: icon,
                      markerColor: markerColor.name,
                      iconColor: icon_color ?? "#FFFFFF",
                      prefix: "mdi",
                  })
        );
    } else if (type === "polyline" || type === "polygon") {
        if (searched) {
            layer.setStyle({
                color: "red",
                weight: 5,
                opacity: 0.7,
                dashArray: "5, 5",
                lineJoin: "round",
            });
        } else {
            layer.setStyle({
                color: color,
            });
        }
    }
    if (isTooltipOpen(layer)) {
        openTooltips(layer);
    }
    let popup = layer.getPopup();
    if (popup) {
        popup.setContent(getContentPopupFromObject(obj));
    } else {
        layer.bindPopup(getContentPopupFromObject(obj));
    }
    if (!layerEditing || layerEditing.id !== layer.id) {
        setContextMenu(layer, obj);
    }
    layer.bindTooltip(obj.text_node);
};

export const getLayerType = (layer) => {
    if (layer instanceof L.Polygon) return "polygon";
    if (layer instanceof L.Polyline) return "polyline";
    if (layer instanceof L.Marker) return "marker";
    if (layer instanceof L.Circle) return "circle";
    if (layer instanceof L.Rectangle) return "rectangle";
    if (layer instanceof L.CircleMarker) return "circlemarker";
    return "unknown";
};

export const openTooltipsFromGoup = (group, open = true) => {
    group.eachLayer((layer) => {
        openTooltips(layer, open);
    });
};

export const openTooltips = (layer, open = true) => {
    if (open) {
        const { text_node, type } = layer.properties;
        layer.bindTooltip(text_node, {
            permanent: true,
            direction: "top",
            offset: [0, type === "marker" ? -30 : 0],
        });
    } else {
        layer.closeTooltip();
    }
};

export const isTooltipOpen = (layer) => {
    return !!layer.getTooltip()?.isOpen();
};

export const getContentPopupFromObject = (object) => {
    let html = `<h5>${object.text}</h5><hr style="height: 2px; margin: 5px 0px;">`;
    const properties = object.properties;
    for (const key in properties) {
        if (!excludesProperties.includes(key)) {
            html = `${html} <b class="q-py-xs"">${objectProperties[key]}: </b>${
                properties[key] ?? "..."
            } <br/>`;
        }
    }
    return html;
};

export const getLayersInPolygon = async (polygon) => {
    let layersInside = [];
    var polyTurf = polygon.toGeoJSON();
    const type = getLayerType(polygon);
    for (let key in nodeMap.value) {
        const layer = nodeMap.value[key];
        if (layer.is_layer) {
            const inside = await layerInsideInPolygon(
                layer,
                polyTurf,
                type === "circle" ? polygon : null
            );
            if (inside) {
                layersInside.push(layer);
            }
        }
    }
    return layersInside;
};

export const layerInsideInPolygon = (layer, polyTurf, circle = null) => {
    if (layer.type === "marker") {
        var point = turf.point([layer.coords.lng, layer.coords.lat]);
        if (circle) {
            const circlePolygon = turf.circle(
                [circle.getLatLng().lng, circle.getLatLng().lat],
                circle.getRadius(),
                { units: "meters", steps: 64 }
            );
            return turf.booleanContains(circlePolygon, point);
        }
        return turf.booleanPointInPolygon(point, polyTurf);
    } else if (layer.type === "polyline") {
        try {
            if (circle) {
                const circlePolygon = turf.circle(
                    [circle.getLatLng().lng, circle.getLatLng().lat],
                    circle.getRadius(),
                    { units: "meters", steps: 64 }
                );
                const allPointsInside = layer.coords.every((c) => {
                    const point = turf.point([c.lng, c.lat]);
                    return turf.booleanContains(circlePolygon, point);
                });
                return allPointsInside;
            }
            const allPointsInside = layer.coords.every((c) => {
                const point = turf.point([c.lng, c.lat]);
                return turf.booleanPointInPolygon(point, polyTurf);
            });
            return allPointsInside;
        } catch (error) {
            return false;
        }
    }
    return false;
};

export const showRoutesLayer = (obj) => {
    if (obj.layers) {
        const routes = [...new Set(obj.layers)];
        routes.forEach((r) => {
            const key = `layer-${r}`;
            if (!tickedNodes.value.includes(key)) {
                tickedNodes.value.push(key);
            }
        });
    }
};

const getInsidePoint = (pointCoords, polylineCoords) => {
    return polylineCoords.findIndex((c) => isEqual(c, pointCoords));
};

export const getRoutesByLayer = async (layer) => {
    let updatedRoutes = [];
    if (layer.properties.layers) {
        const markerPosition = layer.getLatLng();
        const markerGeoJSON = turf.point([
            markerPosition.lng,
            markerPosition.lat,
        ]);
        const routes = [...new Set(layer.properties.layers)];
        for (let i = 0; i < routes.length; i++) {
            const key = `layer-${routes[i]}`;
            const line = getNodeByKey(key);
            if (line) {
                const l = getLayerByKeyProperty(key);
                const polylineCoords = l.getLatLngs();
                let newCoordinates = [...polylineCoords];
                let inside = getInsidePoint(
                    layer.properties.coords,
                    line.coords
                );
                if (inside !== -1) {
                    newCoordinates[inside] = markerPosition;
                } else {
                    const polylineGeoJSON = turf.lineString(
                        polylineCoords.map((p) => [p.lng, p.lat])
                    );
                    const nearest = await getNearest(
                        polylineGeoJSON,
                        markerGeoJSON
                    );
                    const { index } = nearest.properties;
                    newCoordinates = [
                        ...polylineCoords.slice(0, index + 1),
                        markerPosition,
                        ...polylineCoords.slice(index + 1),
                    ];
                }

                l.setLatLngs(newCoordinates);

                const coordinates = getGeometryCoords(l);
                const distance = getDistance(l.toGeoJSON());

                Object.assign(line, {
                    coords: coordinates,
                    distance: distance,
                });
                updatedRoutes.push({
                    id: line.id,
                    coords: coordinates,
                    distance,
                });
            }
        }
    }
    return updatedRoutes;
};

const saveCoordinates = async (layer) => {
    let coords = await getGeometryCoords(layer);
    let updatesRoutes = await getRoutesByLayer(layer);
    const data = await updateCoordinates(layer.properties.id, {
        coords: coords,
        distance: getDistance(layer.toGeoJSON()),
        updatesRoutes,
    });
    if (data) {
        currentNode.value = data;
        message(
            `${
                titleLayers[layer.properties.dialog]
            } cambiado(a) de ubicación correctamente`,
            "success"
        );
        layer.disableEdit();
        layerEditing = null;
        hasLayerEdit.value = false;
    } else {
        message(
            `No se ha podido cambiar la ubicación de este(a) ${
                titleLayers[layer.properties.dialog]
            }`,
            "error"
        );
        resetCoordinates(layer);
    }
};

const getGeometryCoords = (geometry) => {
    if (geometry instanceof L.Marker || geometry instanceof L.Circle) {
        const latlng = geometry.getLatLng();
        return { lat: latlng.lat, lng: latlng.lng };
    } else if (
        geometry instanceof L.Polyline ||
        geometry instanceof L.Polygon
    ) {
        let latlngs = geometry.getLatLngs();
        if (Array.isArray(latlngs[0])) {
            latlngs = latlngs[0];
        }
        return latlngs.map((ll) => ({ lat: ll.lat, lng: ll.lng }));
    } else if (
        geometry instanceof L.Rectangle ||
        geometry instanceof L.Polygon
    ) {
        const bounds = geometry.getBounds();
        return [
            { lat: bounds.getNorthEast().lat, lng: bounds.getNorthEast().lng },
            { lat: bounds.getNorthEast().lat, lng: bounds.getSouthWest().lng },
            { lat: bounds.getSouthWest().lat, lng: bounds.getSouthWest().lng },
            { lat: bounds.getSouthWest().lat, lng: bounds.getNorthEast().lng },
        ];
    }
    return null;
};

const resetCoordinates = (layer) => {
    if (layer instanceof L.Polyline) {
        layer.setLatLngs(layer.properties.coords);
    } else {
        layer.setLatLng(layer.properties.coords);
    }
    message(
        `${
            titleLayers[layer.properties.dialog]
        } restaurado(a) a su estado anterior`,
        "success"
    );
    resetLayer(layer);
};

const resetLayer = (layer) => {
    setContextMenu(layer, layer.properties);
    layer.disableEdit();
    layerEditing = null;
    hasLayerEdit.value = false;
    clientToBoxService = null;
};

const getClientBox = (data) => {
    let layer = null;
    drawClientsServiceBox.eachLayer((l) => {
        if (
            l.properties?.client_id === data.client_id &&
            l.properties?.box_id === data.box_id
        ) {
            layer = l;
        }
    });
    return layer;
};

const getDistance = (geoJSON, units = "meters") => {
    return (
        Math.round(
            turf.length(geoJSON, {
                units,
            }) * 100
        ) / 100
    );
};

const getNearest = async (polylineGeoJSON, markerGeoJSON, units = "meters") => {
    let nearest = await turf.nearestPointOnLine(
        polylineGeoJSON,
        markerGeoJSON,
        {
            units,
        }
    );
    return nearest;
};

export const getNearbyRoutes = async (m) => {
    let routes = [];
    const marker = L.marker(m.coords);
    const markerGeoJSON = marker.toGeoJSON();
    let routesErrors = [];
    for (const key in nodeMap.value) {
        const object = nodeMap.value[key];
        let { dialog, coords } = object;
        if (dialog === "route" && Array.isArray(coords)) {
            try {
                const polyline = await L.polyline(coords);
                let nearest = await getNearest(
                    polyline.toGeoJSON(),
                    markerGeoJSON
                );
                if (nearest.properties.dist <= 200) {
                    let newCoordinates = [...coords];
                    let inside = getInsidePoint(m.coords, coords);
                    if (inside === -1) {
                        const { index } = nearest.properties;
                        newCoordinates = [
                            ...coords.slice(0, index + 1),
                            m.coords,
                            ...coords.slice(index + 1),
                        ];
                    }
                    polyline.setLatLngs(newCoordinates);
                    let polylineGeoJSON = polyline.toGeoJSON();
                    const distance = await getMarkerDistanceInRoute(m, {
                        coords: newCoordinates,
                    });
                    routes.push({
                        route: nodeMap.value[key],
                        distancePoint: distance?.distance ?? 0,
                        newCoordinates: getGeometryCoords(polyline),
                        newDistance: getDistance(polylineGeoJSON),
                    });
                }
            } catch (error) {
                console.log(`La ruta ${object.text_node} hay que corregirla`);
                continue;
            }
        }
    }
    return routes;
};

const getMarkerDistanceInRoute = async (marker, route) => {
    const inside = getInsidePoint(marker.coords, route.coords);
    if (inside !== -1) {
        let start = turf.point([route.coords[0].lng, route.coords[0].lat]),
            end = turf.point([marker.coords.lng, marker.coords.lat]),
            polyline = turf.lineString(route.coords.map((p) => [p.lng, p.lat])),
            lineToMarker = turf.lineSlice(start, end, polyline);
        return {
            marker: marker.id,
            distance: getDistance(lineToMarker),
        };
    }
    return null;
};
