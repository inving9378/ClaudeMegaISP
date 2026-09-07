<template>
    <div id="fullscreen-map" class="bg-white">
        <q-splitter
            v-model="splitterModel"
            unit="px"
            style="height: 100%"
            separator-style="width: 8px"
            :class="darkMode ? 'bg-grey-10 text-white' : null"
        >
            <template v-slot:before>
                <projects-component
                    :reload="reloadProjects"
                    :removedObject="removedObject"
                    :permissons="permissons"
                    :width="splitterModel"
                    @selected="(project) => (currentProject = project)"
                    @loaded="onRealoadedProject"
                    @draw-layers="drawLayers"
                    @new-component="onNewComponent"
                    @edit-component="onEditComponent"
                    @destroy-component="onDestroyComponent"
                    @show-on-map="showElementOnMap"
                    @trazar-ruta="trazarRutaEnlace"
                />
            </template>
            <template v-slot:separator>
                <q-btn flat color="primary" round icon="drag_indicator" />
            </template>
            <template v-slot:after>
                <div id="map" style="height: 83vh; width: 100%"></div>
                <div id="tooltip"></div>

                <q-inner-loading :showing="showLoading" />
            </template>
        </q-splitter>
    </div>

    <region-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.region"
        @hide="onDialogHide"
    />

    <KMZComponent
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.kmz"
        @hide="onDialogHide"
    />

    <route-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.route"
        @hide="onDialogHide"
    />

    <building-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.building"
        @hide="onDialogHide"
    />

    <client-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.client"
        @hide="onDialogHide"
    />

    <box-junction-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.junction_box"
        @updated="(obj) => (currentNode = obj)"
        @created="onCreatedObject"
        @hide="onDialogHide"
    />

    <box-service-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.service_box"
        @updated="(obj) => (currentNode = obj)"
        @created="onCreatedObject"
        @hide="onDialogHide"
    />

    <site-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.site"
        @updated="(obj) => (currentNode = obj)"
        @created="onCreatedObject"
        @hide="onDialogHide"
    />

    <cupboard-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.cupboard"
        @updated="(obj) => (currentNode = obj)"
        @created="onCreatedObject"
        @hide="onDialogHide"
    />

    <note-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.note"
        @hide="onDialogHide"
    />

    <pack-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.pack"
        @hide="onDialogHide"
    />

    <pole-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.pole"
        @hide="onDialogHide"
    />

    <source-component
        :object="currentObject"
        :layer="currentLayerOptions"
        :project="currentProject ?? null"
        :show="dialogs.source"
        @hide="onDialogHide"
    />

    <client-to-project-component
        :projects="projects"
        :object="currentObject"
        :show="dialogs.client_to_project"
        @updated="reloadProjects = true"
        @hide="onDialogHide"
    />

    <folder-component
        :project="currentProject"
        :object="currentObject"
        :show="dialogs.folder"
        @updated="reloadProjects = true"
        @hide="dialogs.folder = false"
    />

    <objects-in-serie-component
        :project="currentProject"
        :show="dialogs.elements_in_serie"
        :currentName="currentName"
        :permissons="permissons"
        @start="
            (object) => {
                currentObject = object;
                addInSerie = true;
            }
        "
        @end="
            () => {
                currentObject = null;
                addInSerie = false;
            }
        "
        @hide="
            () => {
                currentObject = null;
                dialogs.elements_in_serie = false;
                addInSerie = false;
                objectCurrentType = false;
                originalName = null;
                currentName = null;
            }
        "
    />

    <dialog-component
        :show="showDialogConfirm"
        @cancel="showDialogConfirm = false"
    />

    <service-box-configuration
        :object="currentObject"
        :show="dialogs.service_box_config"
        :has-edit="permissons.data.canView('maps_service_box_edit')"
        @hide="
            () => {
                dialogs.service_box_config = false;
                currentObject = null;
            }
        "
    />

    <junction-box-configuration
        :object="currentObject"
        :show="dialogs.junction_box_config"
        :has-edit="permissons.data.canView('maps_junction_box_edit')"
        @hide="
            () => {
                dialogs.junction_box_config = false;
                currentObject = null;
            }
        "
    />

    <site-configuration
        :object="currentObject"
        :show="dialogs.site_config"
        :has-edit="permissons.data.canView('maps_site_edit')"
        @hide="
            () => {
                dialogs.site_config = false;
                currentObject = null;
            }
        "
    />

    <rack-configuration
        :object="currentObject"
        :show="dialogs.cupboard_config"
        :has-edit="permissons.data.canView('maps_cupboard_edit')"
        @hide="
            () => {
                dialogs.cupboard_config = false;
                currentObject = null;
            }
        "
    />

    <q-dialog
        v-model="showDialog"
        persistent
        transition-show="scale"
        transition-hide="scale"
    >
        <q-card class="q-pa-lg" style="width: 450px; max-width: 80vw">
            <q-card-section class="text-center">
                <q-icon name="fa fa-download" color="primary" size="5rem" />
                <div class="text-h5 q-mt-md">
                    Seleccione una de las siguientes opciones
                </div>
                <div class="text-subtitle1 text-grey-7 q-mt-sm"></div>
            </q-card-section>
            <q-card-actions align="around" class="q-mt-lg">
                <q-btn
                    no-caps
                    label="KML"
                    color="primary"
                    :loading="loadingExport.kml"
                    :disable="loadingExport.loading"
                    @click="exportToKml(true)"
                />
                <q-btn
                    no-caps
                    label="KMZ"
                    color="primary"
                    :loading="loadingExport.kmz"
                    :disable="loadingExport.loading"
                    @click="exportToKmz()"
                />
                <q-btn
                    no-caps
                    label="PDF"
                    color="primary"
                    :loading="loadingExport.pdf"
                    :disable="loadingExport.loading"
                    @click="exportToPdf()"
                />
                <q-btn
                    no-caps
                    label="Imagen"
                    color="primary"
                    :loading="loadingExport.img"
                    :disable="loadingExport.loading"
                    @click="exportToImage()"
                />
                <q-btn
                    no-caps
                    label="Cancelar"
                    color="grey"
                    @click="showDialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, onMounted, watch, reactive, nextTick, onBeforeMount } from "vue";
import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet-minimap/dist/Control.MiniMap.min.css";
import MiniMap from "leaflet-minimap";
import "leaflet-draw";
import "leaflet-draw/dist/leaflet.draw.css";
import "leaflet-fullscreen/dist/leaflet.fullscreen.css";
import "leaflet-fullscreen/dist/Leaflet.fullscreen";

import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";

import "leaflet-sidebar-v2/css/leaflet-sidebar.min.css";
import "leaflet-sidebar-v2/js/leaflet-sidebar.min.js";

import "leaflet-mouse-position/src/L.Control.MousePosition.css";
import "leaflet-mouse-position/src/L.Control.MousePosition.js";

import "leaflet-control-geocoder/dist/Control.Geocoder.css";
import "leaflet-control-geocoder";

import "leaflet-easybutton/src/easy-button.css";
import "leaflet-easybutton/src/easy-button.js";

import MarkerClusterGroup from "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";

import "leaflet-contextmenu";
import "leaflet-contextmenu/dist/leaflet.contextmenu.min.css";

import html2canvas from "html2canvas";
import "leaflet-editable";
import "leaflet.export";

import "leaflet-cascade-buttons/src/L.cascadeButtons.css";
import "leaflet-cascade-buttons/src/L.cascadeButtons.js";

import "leaflet.gridlayer.googlemutant";

import "leaflet-groupedlayercontrol";

import tokml from "@maphubs/tokml";
import { jsPDF } from "jspdf";

import { distance, length } from "@turf/turf";

import ProjectsComponent from "./components/ProjectsComponent.vue";
import KMZComponent from "./components/KMZComponent.vue";
import RegionComponent from "./components/RegionComponent.vue";
import RouteComponent from "./components/RouteComponent.vue";
import ClientComponent from "./components/ClientComponent.vue";
import BuildingComponent from "./components/BuildingComponent.vue";
import BoxJunctionComponent from "./components/BoxJunctionComponent.vue";
import BoxServiceComponent from "./components/BoxServiceComponent.vue";
import CupboardComponent from "./components/CupboardComponent.vue";
import NoteComponent from "./components/NoteComponent.vue";
import PackComponent from "./components/PackComponent.vue";
import PoleComponent from "./components/PoleComponent.vue";
import SourceComponent from "./components/SourceComponent.vue";
import ClientToProjectComponent from "./components/ClientToProjectComponent.vue";
import FolderComponent from "./components/FolderComponent.vue";
import ObjectsInSerieComponent from "./components/ObjectsInSerieComponent.vue";
import DialogComponent from "./components/DialogComponent.vue";
import ServiceBoxConfiguration from "./components/configuration/ServiceBoxConfiguration.vue";
import SiteComponent from "./components/SiteComponent.vue";
import SiteConfiguration from "./components/configuration/SiteConfiguration.vue";
import RackConfiguration from "./components/configuration/RackConfiguration.vue";
import JunctionBoxConfiguration from "./components/configuration/JunctionBoxConfiguration.vue";

import { darkMode } from "../../../hook/appConfig";

import { getClientsWithoutProject, getMapRenderConfig, saveObject } from "./helper/request";
import { getOcupacionLote } from "./helper/naps-request";
import { getTrazoEnlace } from "./helper/enlaces-request";
import { getCoberturaCapa } from "./helper/cobertura-request";

import Swal from "sweetalert2";
import {
    createLayerFromObject,
    getLayersInPolygon,
    openTooltips,
    openTooltipsFromGoup,
    updateLayerFromObject,
    menuOptions,
    dialogs,
    currentObject,
    reloadProjects,
    removedObject,
    drawnItems,
    hasLayerEdit,
    addAllPermissions,
    showDialogConfirm,
    drawClientsServiceBox,
    getLayerByKeyProperty,
    objectProperties,
    excludesProperties,
    titleLayers,
    currentMarker,
} from "./helper/mapUtils";

import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";
import { useQuasar } from "../../../../../public/plugins/quasar/js/quasar.umd.prod";

import { useFullScreen } from "../../../composables/useFullScreen";
import { message } from "../../../helpers/toastMsg";
import {
    getFromLocalStorage,
    setToLocalStorage,
} from "../../../composables/useLocalStorage";
import {
    currentNode,
    getNodeByKey,
    tickedNodes,
    selectedNodeId,
} from "../../../composables/useNodeMap";
import JSZip from "jszip";

defineOptions({
    name: "LeafletMap",
});

const { setFullScreen } = useFullScreen();

const permissons = reactive({
    data: new Permission({}),
});

const showDialog = ref(false);

const $q = useQuasar();

const splitterModel = ref(350);

let map = null;
// MR-16 Fase 2a (item roadmap #9990495): capa dedicada para el trazo de ruta a OLT
// (dibujada bajo demanda al hacer clic en "Trazar ruta a OLT" de un enlace de servicio).
let trazoLayer = null;
// MR-26 Fase 4 (item roadmap #9990525): capa de cobertura comercial en vivo (Fase 1, #9990522),
// independiente de `drawnItems` (no es un objeto de BD con `dialog`, se recalcula en cada fetch).
let coberturaLayer = null;
const projects = ref([]);
let searchLayers = null;
let clientsLayers = null;
let currentClientLayer = null;
let drawControl = null;
const drawLayer = ref(null);
let layerType = null;
let tooltip = null;

const currentProject = ref(null);
const showLoading = ref(false);

const objectCurrentType = ref(null);
const currentLayerOptions = ref(null);

let showTooltips = false;

const addInSerie = ref(false);
const currentName = ref(null);
const originalName = ref(null);

let fullscreenBtns = null;

let serverData = null;

const loadingExport = ref({
    kml: false,
    kmz: false,
    pdf: false,
    img: false,
    loading: false,
});

// MR-22 Fase 2 (item roadmap #9990458) — panel de capas encendibles + render dependiente de zoom.
// Decisiones ya tomadas por Irving: estado inicial por capa (q3), umbrales de zoom drops>=16 /
// clientes>=17 (q2), reusar el clustering ya existente (q1, sin reconstruirlo).
// Mapeo capa lógica → `dialog` real del layer (único dato disponible hoy para distinguir tipos).
// "drops" (acometida NAP→cliente) no es todavía una entidad propia en mapared_layers — verificado
// contra la BD piloto real (0 filas con un dialog dedicado a drop): queda como capa reservada,
// cableada en el panel y en el umbral de zoom, sin marcadores hasta que esa entidad exista.
const CAPAS_MAPA_RED = [
    { key: "olt", label: "OLT", icon: "mdi-warehouse", dialogs: ["site"] },
    { key: "troncales", label: "Troncales", icon: "mdi-chart-timeline-variant", dialogs: ["route"] },
    { key: "mufas", label: "Mufas", icon: "mdi-package-variant-closed", dialogs: ["junction_box"] },
    { key: "naps", label: "NAPs", icon: "mdi-package", dialogs: ["service_box"] },
    { key: "drops", label: "Drops", icon: "mdi-vector-line", dialogs: [] },
    { key: "clientes", label: "Clientes", icon: "mdi-account", dialogs: ["client"] },
    { key: "postes", label: "Postes", icon: "mdi-currency-mnt", dialogs: ["pole"] },
    // MR-26 Fase 4 (#9990525): ya NO reusa dialog='region' (ese dialog es de zonas/polígonos
    // genéricos importados de KMZ, sin relación con cobertura — 0 filas reales lo usaban así).
    // Cobertura ahora es una capa calculada en vivo (GeoJSON propio, ver cargarCapaCobertura()).
    { key: "cobertura", label: "Cobertura", icon: "mdi-vector-polygon", dialogs: [] },
];

const CAPA_ZOOM_MIN = { drops: 16, clientes: 17 };

const DIALOG_A_CAPA = CAPAS_MAPA_RED.reduce((acc, capa) => {
    capa.dialogs.forEach((d) => (acc[d] = capa.key));
    return acc;
}, {});

const capasEncendidas = reactive({
    olt: true,
    troncales: true,
    mufas: true,
    naps: true,
    drops: false,
    clientes: false,
    postes: false,
    cobertura: false,
});

const capaVisiblePorEstado = (capaKey) => {
    if (!capasEncendidas[capaKey]) {
        return false;
    }
    const zoomMin = CAPA_ZOOM_MIN[capaKey];
    if (zoomMin != null && map && map.getZoom() < zoomMin) {
        return false;
    }
    return true;
};

const aplicarVisibilidadPorCapa = (layer) => {
    const dialogLayer = layer.properties?.dialog;
    if (dialogLayer === "service_box") {
        // MR-20 (aplicarFiltroACapa) ya compone el toggle "naps" del panel con el filtro de
        // puertos libres; no duplicar la decisión de opacidad aquí.
        aplicarFiltroACapa(layer);
        return;
    }
    const capaKey = DIALOG_A_CAPA[dialogLayer];
    if (!capaKey) {
        return; // fuera del alcance del panel (kmz/note/cupboard/building/pack/source/folder)
    }
    if (layer.properties && layer.properties._capaBaseCaptured === undefined) {
        layer.properties._capaBaseCaptured = true;
        layer.properties._capaBaseOpacity = layer.options?.opacity ?? 1;
        layer.properties._capaBaseFillOpacity = layer.options?.fillOpacity;
    }
    const visible = capaVisiblePorEstado(capaKey);
    if (
        typeof layer.setStyle === "function" &&
        layer.properties?._capaBaseFillOpacity !== undefined
    ) {
        layer.setStyle({
            opacity: visible ? layer.properties._capaBaseOpacity : 0,
            fillOpacity: visible ? layer.properties._capaBaseFillOpacity : 0,
        });
    } else if (typeof layer.setOpacity === "function") {
        layer.setOpacity(visible ? (layer.properties?._capaBaseOpacity ?? 1) : 0);
    }
};

const aplicarVisibilidadCapas = () => {
    if (!drawnItems) {
        return;
    }
    drawnItems.eachLayer((layer) => aplicarVisibilidadPorCapa(layer));
};

watch(capasEncendidas, () => {
    aplicarVisibilidadCapas();
});

// MR-26 Fase 4 (item roadmap #9990525) — capa "Cobertura" en vivo (Fase 1, #9990522). Se
// refetch cada vez que se enciende el toggle (no se cachea) para que ocupar el último puerto
// libre de una NAP la haga desaparecer al re-encender la capa (DoD del item padre #962).
const cargarCapaCobertura = async () => {
    if (!coberturaLayer) {
        return;
    }
    coberturaLayer.clearLayers();
    const geojson = await getCoberturaCapa();
    if (!geojson || !Array.isArray(geojson.features)) {
        return;
    }
    L.geoJSON(geojson, {
        style: {
            color: "#00c853",
            weight: 1,
            fillColor: "#00c853",
            fillOpacity: 0.15,
        },
        onEachFeature: (feature, layer) => {
            const { nombre, puertos_libres } = feature.properties ?? {};
            layer.bindPopup(
                `<b>${nombre ?? "NAP"}</b><br>Puertos libres: ${puertos_libres ?? "?"}`
            );
        },
    }).addTo(coberturaLayer);
};

watch(
    () => capasEncendidas.cobertura,
    (encendida) => {
        if (encendida) {
            cargarCapaCobertura();
        } else if (coberturaLayer) {
            coberturaLayer.clearLayers();
        }
    }
);

const crearControlCapas = () => {
    const CapasControl = L.Control.extend({
        options: { position: "topright" },
        onAdd: function () {
            const container = L.DomUtil.create(
                "div",
                "leaflet-bar capas-panel"
            );
            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);

            const header = L.DomUtil.create(
                "div",
                "capas-panel__header",
                container
            );
            header.innerHTML =
                '<i class="mdi mdi-layers-outline"></i><span>Capas</span><i class="mdi mdi-chevron-up capas-panel__chevron"></i>';
            const body = L.DomUtil.create("div", "capas-panel__body", container);

            CAPAS_MAPA_RED.forEach((capa) => {
                const row = L.DomUtil.create("label", "capas-panel__row", body);
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.checked = capasEncendidas[capa.key];
                checkbox.addEventListener("change", () => {
                    capasEncendidas[capa.key] = checkbox.checked;
                });
                row.appendChild(checkbox);
                const text = document.createElement("span");
                const zoomMin = CAPA_ZOOM_MIN[capa.key];
                text.innerHTML = `<i class="mdi ${capa.icon}"></i> ${capa.label}${
                    zoomMin ? ` <small>(zoom&nbsp;≥&nbsp;${zoomMin})</small>` : ""
                }`;
                row.appendChild(text);
            });

            header.addEventListener("click", () => {
                const abierto = body.style.display !== "none";
                body.style.display = abierto ? "none" : "flex";
                header
                    .querySelector(".capas-panel__chevron")
                    ?.classList.toggle("mdi-chevron-up", !abierto);
                header
                    .querySelector(".capas-panel__chevron")
                    ?.classList.toggle("mdi-chevron-down", abierto);
            });

            return container;
        },
    });
    new CapasControl().addTo(map);
};

onBeforeMount(async () => {
    serverData = await getMapRenderConfig();
    const script = document.createElement("script");
    script.src = `https://maps.googleapis.com/maps/api/js?key=${serverData.api_key}`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
});

onMounted(async () => {
    permissons.data = new Permission(await allViewHasPermission());
    addAllPermissions(permissons.data);
    initMap();
});

watch(drawLayer, (n) => {
    currentLayerOptions.value =
        n && currentProject.value
            ? {
                  project_id: currentProject.value.id,
                  leflet_id: n._leaflet_id,
                  color: layerType === "marker" ? "#5bc0de" : n.options.color,
                  icon_color: layerType === "marker" ? "#FFFFFF" : null,
                  coords: layerType === "marker" ? n._latlng : n._latlngs,
                  type: layerType,
                  weight: n.options.weight,
                  distance:
                      Math.round(
                          length(n.toGeoJSON(), { units: "meters" }) * 100
                      ) / 100,
                  ...objectCurrentType.value,
              }
            : null;
});

// MR-22 Fase 3a (item roadmap #9990515): `currentMarker` (mapUtils.js) ya se actualiza en
// cada click sobre una capa del mapa (createLayerFromObject) — aquí solo se deriva el id
// (campo `key`) del nodo seleccionado, para que deriveThreeLevelTree() y el panel lateral de
// la siguiente fase lo consuman sin depender del objeto completo de la capa.
watch(currentMarker, (marker) => {
    selectedNodeId.value = marker?.key ?? null;
});

watch(addInSerie, (n) => {
    if (n) {
        document.getElementsByClassName("leaflet-draw-draw-marker")[0].click();
    } else {
        const btn = document.querySelector(
            ".leaflet-draw-actions a:first-child"
        );
        if (btn) {
            btn.click();
        }
    }
});

const initMap = async () => {
    let latitude = null,
        longitude = null,
        zoom = null;
    if (serverData) {
        latitude = parseFloat(serverData.latitude);
        longitude = parseFloat(serverData.longitude);
        zoom = serverData.zoom;
    }

    map = L.map("map", {
        center: [latitude ?? 23.6345, longitude ?? -102.5528],
        zoom: zoom ?? 5,
        minZoom: 5,
        maxZoom: 19,
        zoomControl: false,
    });

    const lastZoom = getFromLocalStorage("map-zoom");
    const mapCenter = getFromLocalStorage("map-center");
    if (lastZoom && mapCenter) {
        map.setView(JSON.parse(mapCenter), lastZoom);
    }

    const osmLayer = L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            crossOrigin: true,
        }
    );

    const googleSatLayer = L.gridLayer.googleMutant({
        type: "hybrid",
        maxZoom: 21,
        attribution:
            'Map data &copy; <a href="https://www.google.com/maps">Google</a>',
    });

    const baseLayers = {
        "Open Street Map": osmLayer,
        "Google Satélite": googleSatLayer,
    };

    osmLayer.addTo(map);

    // MR-16 Fase 2a (#9990495): capa togglable con el trazo de ruta a OLT del enlace
    // seleccionado (vacía hasta que se pida un trazo).
    trazoLayer = L.layerGroup().addTo(map);

    // MR-26 Fase 4 (#9990525): capa de cobertura comercial, controlada por el checkbox
    // "Cobertura" del panel propio (capas-panel), no por este control nativo de Leaflet.
    coberturaLayer = L.layerGroup().addTo(map);
    if (capasEncendidas.cobertura) {
        cargarCapaCobertura();
    }

    L.control
        .layers(baseLayers, { "Trazo a OLT": trazoLayer })
        .addTo(map);

    crearControlCapas();

    map.on("baselayerchange", function (e) {
        const newLayer = e.layer;
        const newMaxZoom = newLayer.options.maxZoom ?? 19;
        map.setMaxZoom(newMaxZoom);
    });

    // MR-22 Fase 2 (#9990458): render dependiente de zoom (drops>=16 / clientes>=17), independiente
    // del toggle manual del panel de capas — ambas condiciones deben cumplirse a la vez.
    map.on("zoomend", function () {
        aplicarVisibilidadCapas();
    });

    map.contextmenu.enable();

    map.on("contextmenu", function (e) {
        L.DomEvent.stopPropagation(e);
        if (
            !currentProject.value ||
            currentProject.value.classification !== "project" ||
            addInSerie.value ||
            hasLayerEdit.value
        ) {
            map.contextmenu.hide();
        }
    });

    setActionsToMap();

    L.control
        .zoom({
            position: "topleft",
            zoomInText: "+",
            zoomInTitle: "Acercar",
            zoomOutText: "-",
            zoomOutTitle: "Alejar",
        })
        .addTo(map);

    fullscreenBtns = L.easyButton({
        states: [
            {
                stateName: "show-fullscreen",
                icon: "fa-expand",
                title: "Pantalla completa",
                onClick: function (btn, map) {
                    $q.fullscreen
                        .toggle(document.getElementById("fullscreen-map"))
                        .then(() => {
                            document.getElementById("map").style.height = `${
                                $q.screen.height + 125
                            }px`;
                            btn.state("hide-fullscreen");
                            setFullScreen(true);
                        })
                        .catch((err) => {
                            alert(err);
                        });
                },
            },
            {
                stateName: "hide-fullscreen",
                icon: "fa-compress",
                title: "Salir de pantalla completa",
                onClick: function (btn, map) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen();
                    } else if (document.webkitCancelFullScreen) {
                        document.webkitCancelFullScreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                    document.getElementById("map").style.height = "83vh";
                    btn.state("show-fullscreen");
                    setFullScreen(false);
                },
            },
        ],
    }).addTo(map);

    L.control.mousePosition().addTo(map);

    let geocoder = L.Control.geocoder({
        geocoder: L.Control.Geocoder.nominatim({
            geocodingQueryParams: {
                countrycodes: "mx",
                "accept-language": "es",
                bounded: 1,
                viewbox: "-118.453,14.388,-86.493,32.718",
            },
        }),
        placeholder: "Buscar dirección en México...",
        errorMessage: "Dirección no encontrada",
        defaultMarkGeocode: false,
    }).addTo(map);

    geocoder.on("markgeocode", function (e) {
        const { center, name, bbox } = e.geocode;
        L.marker(center, {
            icon: L.AwesomeMarkers.icon({
                icon: "map-marker",
                markerColor: "blue",
                prefix: "fa",
            }),
        })
            .addTo(drawnItems)
            .bindPopup(name)
            .openPopup();
        map.fitBounds(bbox, {
            padding: [50, 50],
            maxZoom: 16,
        });
    });

    const miniMapLayer = L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            attribution: "&copy; OpenStreetMap contributors",
        }
    );

    let miniMap = new MiniMap(miniMapLayer, {
        toggleDisplay: true,
        position: "bottomright",
        width: 150,
        height: 150,
    }).addTo(map);

    map.addLayer(drawnItems);

    clientsLayers = new L.markerClusterGroup();
    map.addLayer(clientsLayers);

    map.addLayer(drawClientsServiceBox);

    searchLayers = new L.FeatureGroup();
    map.addLayer(searchLayers);

    var OriginalDrawMarker = L.Draw.Marker;
    L.Draw.Marker = OriginalDrawMarker.extend({
        addHooks: function () {
            OriginalDrawMarker.prototype.addHooks.call(this);
            this.options.icon = L.AwesomeMarkers.icon({
                icon: objectCurrentType.value?.icon ?? "circle",
                markerColor: "blue",
                prefix: objectCurrentType.value ? "mdi" : "fa",
            });
        },
    });

    map.editTools = new L.Editable(map);

    tooltip = L.DomUtil.get("tooltip");

    map.on(
        "editable:vertex:ctrlclick editable:vertex:metakeyclick",
        function (e) {
            e.vertex.continue();
        }
    );

    map.on("moveend", function () {
        setToLocalStorage("map-zoom", map.getZoom());
        setToLocalStorage("map-center", JSON.stringify(map.getCenter()));
    });

    drawControl = new L.Control.Draw({
        position: "topright",
        draw: {
            polyline: {
                shapeOptions: {
                    clickable: false,
                    color: "#6666ff",
                    opacity: 1,
                },
            },
            circle: {
                shapeOptions: {
                    clickable: false,
                    color: "#6666ff",
                    opacity: 1,
                },
            },
            circlemarker: false,
            polygon: {
                allowIntersection: false,
                drawError: {
                    color: "#b00b00",
                    message:
                        "<strong>Error:</strong> ¡Las formas no pueden intersecarse!",
                },
                shapeOptions: {
                    clickable: false,
                    color: "#6666ff",
                    opacity: 1,
                },
            },
            rectangle: {
                shapeOptions: {
                    clickable: false,
                    color: "#6666ff",
                    opacity: 1,
                },
            },
            marker: {
                icon: new L.Icon.Default(),
            },
        },
        edit: false,
    });

    map.addControl(drawControl);

    localLang();

    map.on(L.Draw.Event.CREATED, async function (e) {
        if (addInSerie.value) {
            let { color, data, icon_color, project_id, type, start } =
                currentObject.value;
            objectCurrentType.value = type;
            if (!originalName.value) {
                originalName.value = data.name;
            }
            let name = `${data.name} ${start}`;
            const object = await saveObject({
                color,
                icon_color,
                project_id,
                ...type,
                type: "marker",
                coords: e.layer.getLatLng(),
                data: {
                    name,
                },
            });
            if (object) {
                currentNode.value = object;
                const layer = createLayerFromObject(object);
                drawnItems.addLayer(layer);
                message(`${type.text} adicionado(a) correctamente`);
                currentObject.value.start++;
                currentName.value = `${originalName.value} ${currentObject.value.start}`;
                document
                    .getElementsByClassName("leaflet-draw-draw-marker")[0]
                    .click();
            } else {
                message(
                    `Ha ocurrido un error al tratar de agregar este(a) ${type.text}`,
                    "error"
                );
            }
        } else {
            layerType = e.layerType;
            drawLayer.value = e.layer;
            if (objectCurrentType.value?.dialog) {
                dialogs.value[objectCurrentType.value.dialog] = true;
                map.addLayer(drawLayer.value);
            } else {
                if (
                    layerType === "circle" ||
                    layerType === "rectangle" ||
                    layerType === "polygon"
                ) {
                    searchLayers.clearLayers();
                    searchLayers.addLayer(drawLayer.value);
                    drawLayersInPolygon(e.layer);
                }
            }
        }
    });

    L.easyButton({
        states: [
            {
                stateName: "show-description",
                icon: "fa-eye",
                title: "Mostrar descripción de las capas",
                onClick: function (btn, map) {
                    showTooltips = true;
                    openTooltipsFromGoup(drawnItems, showTooltips);
                    openTooltipsFromGoup(clientsLayers, showTooltips);
                    btn.state("hide-description");
                },
            },
            {
                stateName: "hide-description",
                icon: "fa-eye-slash",
                title: "Ocultar descripción de las capas",
                onClick: function (btn, map) {
                    showTooltips = false;
                    openTooltipsFromGoup(drawnItems, showTooltips);
                    openTooltipsFromGoup(clientsLayers, showTooltips);
                    btn.state("show-description");
                },
            },
        ],
    }).addTo(map);

    L.easyButton({
        position: "topleft",
        states: [
            {
                stateName: "center",
                icon: "fa-crosshairs",
                title: "Centrar mapa con las capas actuales",
                onClick: function () {
                    if (Object.keys(map._layers).length > 6) {
                        const bounds = new L.LatLngBounds();
                        map.eachLayer((layer) => {
                            if (layer.getBounds) {
                                bounds.extend(layer.getBounds());
                            } else if (layer.getLatLng) {
                                bounds.extend(layer.getLatLng());
                            }
                        });
                        map.fitBounds(bounds);
                    } else {
                        message("No existen capas en el mapa", "info");
                    }
                },
            },
        ],
    }).addTo(map);

    // L.easyButton({
    //     states: [
    //         {
    //             stateName: "show-clients",
    //             icon: "fa-user",
    //             title: "Mostrar clientes sin proyecto asignado",
    //             onClick: async function (btn) {
    //                 showLoading.value = true;
    //                 const clients = await getClientsWithoutProject();
    //                 clientsLayers.clearLayers();
    //                 clients.forEach((c) => {
    //                     let layer = L.marker([c.lat, c.lng], {
    //                         icon: L.AwesomeMarkers.icon({
    //                             icon: "fa-user-times",
    //                             markerColor: "red",
    //                             iconColor: "#FFFFFF",
    //                             prefix: "fa",
    //                         }),
    //                     });
    //                     c["type"] = "marker";
    //                     layer.properties = c;
    //                     layer.addTo(clientsLayers);
    //                     layer.bindTooltip(c.text_node, {
    //                         direction: "top",
    //                         offset: [0, -30],
    //                     });
    //                     layer.on("dblclick", function (e) {
    //                         Swal.fire({
    //                             title: "¡Info!",
    //                             text: "Desea agregar el cliente a un proyecto?",
    //                             icon: "question",
    //                             showCancelButton: true,
    //                             confirmButtonColor: "#3085d6",
    //                             cancelButtonColor: "#d33",
    //                             confirmButtonText: "Si",
    //                             cancelButtonText: "No",
    //                         }).then((result) => {
    //                             if (result.isConfirmed) {
    //                                 currentObject.value = c;
    //                                 dialogs.value.client_to_project = true;
    //                                 currentClientLayer = layer;
    //                             }
    //                         });
    //                     });
    //                     btn.state("hide-clients");
    //                     showLoading.value = false;
    //                 });
    //             },
    //         },
    //         {
    //             stateName: "hide-clients",
    //             icon: "fa-user-slash",
    //             title: "Ocultar clientes sin proyecto asignado",
    //             onClick: async function (btn) {
    //                 clientsLayers.clearLayers();
    //                 btn.state("show-clients");
    //             },
    //         },
    //     ],
    // }).addTo(map);

    L.easyButton({
        states: [
            {
                stateName: "clear",
                icon: "fa-eraser",
                title: "Limpiar búsquedas",
                onClick: function () {
                    searchLayers.clearLayers();
                },
            },
        ],
    }).addTo(map);

    L.easyButton({
        states: [
            {
                stateName: "kml-export",
                icon: "fa-download",
                title: "Exportar mapa",
                onClick: function () {
                    showDialog.value = true;
                },
            },
        ],
    }).addTo(map);

    // MR-20 (item #956) — filtro "solo NAPs con puertos libres" (decisión q2: cliente-side,
    // sobre los marcadores ya cargados, sin round-trip).
    L.easyButton({
        states: [
            {
                stateName: "solo-naps-libres-off",
                icon: "fa-filter",
                title: "Mostrar solo NAPs con puertos libres",
                onClick: function (btn) {
                    toggleFiltroPuertosLibres(true);
                    btn.state("solo-naps-libres-on");
                },
            },
            {
                stateName: "solo-naps-libres-on",
                icon: "fa-filter",
                title: "Mostrar todas las NAPs",
                onClick: function (btn) {
                    toggleFiltroPuertosLibres(false);
                    btn.state("solo-naps-libres-off");
                },
            },
        ],
    }).addTo(map);

    map.on("click", async function (e) {
        if (addInSerie.value) {
            let { color, data, icon_color, project_id, type, start } =
                currentObject.value;
            objectCurrentType.value = type;
            if (!originalName.value) {
                originalName.value = data.name;
            }
            let name = `${data.name} ${start}`;
            const object = await saveObject({
                color,
                icon_color,
                project_id,
                ...type,
                type: "marker",
                coords: e.latlng,
                data: {
                    name,
                },
            });
            if (object) {
                currentNode.value = object;
                const layer = createLayerFromObject(object);
                drawnItems.addLayer(layer);
                message(`${type.text} adicionado(a) correctamente`);
                currentObject.value.start++;
                currentName.value = `${originalName.value} ${currentObject.value.start}`;
            } else {
                message(
                    `Ha ocurrido un error al tratar de agregar este(a) ${type.text}`
                );
            }
        }
    });

    document.addEventListener("fullscreenchange", handleFullscreenChange);
    document.addEventListener("webkitfullscreenchange", handleFullscreenChange);
    document.addEventListener("mozfullscreenchange", handleFullscreenChange);
    document.addEventListener("MSFullscreenChange", handleFullscreenChange);

    reloadProjects.value = true;
};

const handleFullscreenChange = () => {
    if (
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.mozFullScreenElement ||
        document.msFullscreenElement
    ) {
        fullscreenBtns.state("hide-fullscreen");
        setFullScreen(true);
    } else {
        fullscreenBtns.state("show-fullscreen");
        setFullScreen(false);
    }
};

const setActionsToMap = () => {
    let options = menuOptions.filter((m) =>
        permissons.data.canView(`maps_${m.dialog}_add`)
    );
    if (options.length > 0) {
        let inSerie = menuOptions.find((m) => m.dialog === "elements_in_serie");
        let existInSerie = options.find(
            (o) => o.dialog === "elements_in_serie"
        );
        if (!existInSerie) {
            options.push(inSerie);
        }
    }
    let actionsBar = [];

    options.forEach((m) => {
        actionsBar.push({
            icon: `mdi ${m.icon}`,
            ignoreActiveState: true,
            title: m.text,
            command: async () => {
                if (
                    !currentProject.value ||
                    currentProject.value.classification !== "project"
                ) {
                    message(
                        "Debe seleccionar un proyecto a partir de la ruta Meganet/Proyectos",
                        "info"
                    );
                } else if (addInSerie.value) {
                    message(
                        "Operación no permitida mientras se se esté adicionando en serie",
                        "info"
                    );
                } else if (hasLayerEdit.value) {
                    message("Operación no permitida en edición", "info");
                } else {
                    onNewComponent(currentProject.value, m);
                }
            },
        });
        if (m.dialog !== "route") {
            map.contextmenu.addItem({
                text: m.text,
                iconCls: `mdi ${m.icon}`,
                enabled: currentProject.value !== null,
                callback: function (e) {
                    objectCurrentType.value = m;
                    if (
                        m.dialog !== "elements_in_serie" &&
                        m.dialog !== "folder"
                    ) {
                        layerType =
                            m.dialog === "route" ? "polylinea" : "marker";
                        let marker = L.marker(e.latlng, {
                            draggable: true,
                            icon: L.AwesomeMarkers.icon({
                                icon: objectCurrentType.value?.icon ?? "circle",
                                markerColor: "blue",
                                prefix: objectCurrentType.value ? "mdi" : "fa",
                            }),
                        });
                        drawLayer.value = marker;
                        map.addLayer(drawLayer.value);
                    }
                    if (m.dialog) {
                        dialogs.value[m.dialog] = true;
                    }
                },
            });
        }
    });

    new L.cascadeButtons(actionsBar, {
        position: "topleft",
        direction: "horizontal",
    }).addTo(map);
};

const drawLayersInPolygon = async (polygon) => {
    const layers = await getLayersInPolygon(polygon);
    layers.forEach((l) => {
        const layer = createLayerFromObject(l, true);
        layer.addTo(searchLayers);
    });
};

const localLang = () => {
    L.drawLocal = {
        draw: {
            toolbar: {
                actions: {
                    title: "Cancelar dibujo",
                    text: "Cancelar",
                },
                finish: {
                    title: "Terminar dibujo",
                    text: "Terminar",
                },
                undo: {
                    title: "Eliminar último punto",
                    text: "Eliminar último punto",
                },
                buttons: {
                    polyline: "Dibujar polilínea",
                    polygon: "Dibujar polígono",
                    rectangle: "Dibujar rectángulo",
                    circle: "Dibujar círculo",
                    marker: "Dibujar marcador",
                    circlemarker: "Dibujar marcador circular",
                },
            },
            handlers: {
                circle: {
                    tooltip: {
                        start: "Haz clic y arrastra para dibujar círculo.",
                    },
                    radius: "Radio",
                },
                circlemarker: {
                    tooltip: {
                        start: "Haz clic para colocar marcador circular.",
                    },
                },
                marker: {
                    tooltip: {
                        start: "Haz clic para colocar marcador.",
                    },
                },
                polygon: {
                    tooltip: {
                        start: "Haz clic para empezar a dibujar.",
                        cont: "Haz clic para continuar dibujando.",
                        end: "Haz clic en el primer punto para cerrar.",
                    },
                },
                polyline: {
                    error: "<strong>Error:</strong> los bordes no pueden cruzarse.",
                    tooltip: {
                        start: "Haz clic para empezar a dibujar.",
                        cont: "Haz clic para continuar dibujando.",
                        end: "Haz clic en el último punto para terminar.",
                    },
                },
                rectangle: {
                    tooltip: {
                        start: "Haz clic y arrastra para dibujar rectángulo.",
                    },
                },
            },
        },
        edit: {
            toolbar: {
                actions: {
                    save: {
                        title: "Guardar cambios",
                        text: "Guardar",
                    },
                    cancel: {
                        title: "Cancelar edición",
                        text: "Cancelar",
                    },
                    clearAll: {
                        title: "Limpiar todo",
                        text: "Limpiar",
                    },
                },
                buttons: {
                    edit: "Editar capas",
                    editDisabled: "No hay capas para editar",
                    remove: "Eliminar capas",
                    removeDisabled: "No hay capas para eliminar",
                },
            },
            handlers: {
                edit: {
                    tooltip: {
                        text: "Arrastra los vértices para editar.",
                        subtext: "Haz clic en Cancelar para deshacer.",
                    },
                },
                remove: {
                    tooltip: {
                        text: "Haz clic en una figura para eliminarla.",
                    },
                },
            },
        },
    };
};

const showElementOnMap = (object) => {
    if (object.type === "marker") {
        map.setView(object.coords, 18);
    } else {
        map.fitBounds(object.coords);
    }
};

// MR-16 Fase 2a (item roadmap #9990495): dibuja la ruta física del enlace hasta la OLT
// (backend Fase 1, #9990468). Trazo parcial = dibuja lo que haya + aviso visible del
// motivo_corte, nunca falla en silencio (decisión ya tomada por Irving).
const trazarRutaEnlace = async (enlaceId) => {
    const trazo = await getTrazoEnlace(enlaceId);
    if (!trazo) {
        message("No se pudo obtener el trazo de este enlace", "error");
        return;
    }

    trazoLayer.clearLayers();

    const puntos = (trazo.elementos ?? [])
        .filter((el) => el.posicion)
        .map((el) => [el.posicion.lat, el.posicion.lng]);

    if (puntos.length >= 2) {
        L.polyline(puntos, {
            color: "#7dd3fc",
            weight: 4,
            opacity: 0.85,
        }).addTo(trazoLayer);
        map.fitBounds(puntos);
    }

    if (!trazo.completa) {
        message(
            trazo.motivo_corte ?? "El trazo no pudo completarse hasta la OLT",
            "warning"
        );
    }
};

const onRealoadedProject = (list) => {
    reloadProjects.value = false;
    projects.value = list;
};

const onCreatedObject = (obj, assiggned_routes = []) => {
    currentNode.value = obj;
    assiggned_routes.forEach((r) => {
        let key = `layer-${r.route}`;
        const node = getNodeByKey(key);
        if (node) {
            Object.assign(node, {
                coords: r.coords,
                distance: r.total_distance,
            });
            node.coords = r.coords;
            const l = getLayerByKeyProperty(key);
            if (l) {
                l.setLatLngs(r.coords);
                Object.assign(l.properties, {
                    coords: r.coords,
                    distance: r.total_distance,
                });
            } else {
                tickedNodes.value.push(key);
            }
        }
    });
};

const onNewComponent = (project, type) => {
    objectCurrentType.value = type;
    currentProject.value = project;
    if (type.dialog === "folder" || type.dialog === "elements_in_serie") {
        dialogs.value[type.dialog] = true;
    } else {
        document.getElementsByClassName(type.element)[0].click();
    }
};

const onEditComponent = (object) => {
    currentObject.value = object;
    dialogs.value[object.dialog] = true;
};

const onDestroyComponent = (object) => {
    if (object.coords) {
        removeLayerByKey(object.key);
    } else {
        removeLayerOnCascade(object);
    }
};

const removeLayerOnCascade = (object) => {
    object.children.forEach((o) => {
        if (o.coords) {
            removeLayerByKey(o.key);
        } else {
            removeLayerOnCascade(o);
        }
    });
};

// MR-20 (item roadmap #956) — semáforo de ocupación de puertos por NAP (D16).
// Fuente de datos: MapaRedNapOcupacionService (mismo servicio del dashboard D16, decisión q1).
const MAPA_RED_LAYER_MODEL = "App\\Modules\\Addons\\MapaRed\\Models\\MapaRedLayer";
const soloNapsConPuertosLibres = ref(false);
const ocupacionSemaforoColor = {
    gris: "gray",
    amarillo: "beige",
    naranja: "orange",
    rojo: "red",
};

const aplicarFiltroACapa = (layer) => {
    if (layer.properties?.dialog !== "service_box" || typeof layer.setOpacity !== "function") {
        return;
    }
    // MR-22 Fase 2 (#9990458): el toggle "naps" del panel de capas manda primero; si está apagado
    // no hay nada que reconciliar con el filtro de puertos libres.
    if (!capaVisiblePorEstado("naps")) {
        layer.setOpacity(0);
        return;
    }
    const ocupacion = layer.properties.ocupacion;
    if (!soloNapsConPuertosLibres.value || !ocupacion) {
        layer.setOpacity(1);
        return;
    }
    const tienePuertosLibres = ocupacion.puertos_usados < ocupacion.puertos_totales;
    layer.setOpacity(tienePuertosLibres ? 1 : 0);
};

const toggleFiltroPuertosLibres = (activo) => {
    soloNapsConPuertosLibres.value = activo;
    drawnItems.eachLayer((layer) => aplicarFiltroACapa(layer));
};

// Color del marcador según la escala D16 + tooltip "usados/totales (%)" (decisión q3: hover mínimo).
const aplicarSemaforoOcupacion = (layer, ocupacion) => {
    if (!layer || !ocupacion || layer.properties?.dialog !== "service_box") {
        return;
    }
    if (!layer.properties.text_node_base) {
        layer.properties.text_node_base = layer.properties.text_node;
    }
    layer.properties.ocupacion = ocupacion;
    if (layer.properties.type === "marker" && typeof layer.setIcon === "function") {
        layer.setIcon(
            L.AwesomeMarkers.icon({
                icon: layer.properties.icon,
                markerColor: ocupacionSemaforoColor[ocupacion.semaforo] ?? "gray",
                iconColor: layer.properties.icon_color ?? "#FFFFFF",
                prefix: "mdi",
            })
        );
    }
    layer.properties.text_node = `${layer.properties.text_node_base} · ${ocupacion.puertos_usados}/${ocupacion.puertos_totales} puertos (${ocupacion.porcentaje}%)`;
    layer.bindTooltip(layer.properties.text_node);
    aplicarFiltroACapa(layer);
};

// Lote (decisión q1): una sola llamada por tanda de `drawLayers`, no una por marcador.
const aplicarOcupacionNaps = async (nodes) => {
    const napIds = nodes
        .filter((o) => o.dialog === "service_box" && o.id != null)
        .map((o) => o.id);
    if (napIds.length === 0) {
        return;
    }
    const ocupacionPorId = await getOcupacionLote(MAPA_RED_LAYER_MODEL, napIds);
    if (!ocupacionPorId) {
        return;
    }
    napIds.forEach((id) => {
        const layer = getLayerByKeyProperty(`layer-${id}`);
        if (layer && ocupacionPorId[id]) {
            aplicarSemaforoOcupacion(layer, ocupacionPorId[id]);
        }
    });
};

const drawLayers = (selectedLayers, noSelectedLayers = []) => {
    processInBatches(selectedLayers, 500, (batch) => {
        batch.forEach((o) => {
            if (o.coords) {
                let layer = getLayerByKeyProperty(o.key);
                if (!layer) {
                    layer = createLayerFromObject(o);
                    layer.addTo(drawnItems);
                } else {
                    updateLayerFromObject(layer, o);
                }
                if (showTooltips) {
                    openTooltips(layer, showTooltips);
                }
                layer.on("dblclick", function (e) {
                    if (o.dialog === "client") {
                        window.open(
                            `/cliente/editar/${o.properties.client_id}`,
                            "_blank"
                        );
                    } else if (!hasLayerEdit.value) {
                        currentObject.value = o;
                        dialogs.value[`${o.dialog}_config`] = true;
                    }
                });
                layer.on("contextmenu", function (e) {
                    L.DomEvent.stopPropagation(e);
                    layer.options.contextmenu = addInSerie.value;
                });
                return layer;
            }
        });
    }).then(() => {
        aplicarOcupacionNaps(selectedLayers);
        aplicarVisibilidadCapas();
    });
    noSelectedLayers.forEach((key) => {
        removeLayerByKey(key);
    });
};

const processInBatches = (data, batchSize, processBatch, delay = 0) => {
    return new Promise((resolve) => {
        let index = 0;
        function processNextBatch() {
            const batch = data.slice(index, index + batchSize);
            processBatch(batch);
            index += batchSize;
            if (index < data.length) {
                if (delay > 0) {
                    setTimeout(processNextBatch, delay);
                } else {
                    setTimeout(processNextBatch, 0);
                }
            } else {
                resolve();
            }
        }
        processNextBatch();
    });
};

const removeLayerByKey = (key) => {
    let layer = getLayerByKeyProperty(key);
    if (layer) {
        drawnItems.removeLayer(layer);
    }
};

const setDefaultData = () => {
    for (const key in dialogs.value) {
        dialogs.value[key] = false;
    }
    currentObject.value = null;
    objectCurrentType.value = null;
    if (drawLayer.value) {
        map.removeLayer(drawLayer.value);
    }
    drawLayer.value = null;
    currentClientLayer = null;
};

const onDialogHide = (object) => {
    if (object.id) {
        drawLayers([object]);
        if (currentClientLayer) {
            clientsLayers.removeLayer(currentClientLayer);
        }
    }
    setDefaultData();
};

const getGeoJsonFeatureCollection = () => {
    const geojsonFeatureCollection = {
        type: "FeatureCollection",
        features: [],
    };
    drawnItems.eachLayer(function (layer) {
        let props = layer.properties,
            properties = {
                name: props.name,
            },
            type = props.type,
            descripcionHtml =
                '<div style="font-family: Arial, sans-serif;"><table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';

        for (const key in props) {
            if (key !== "name" && !excludesProperties.includes(key)) {
                descripcionHtml += `<tr><td><strong>${
                    key === "text" ? "Tipo" : objectProperties[key]
                }:</strong></td><td>${props[key]}</td></tr>`;
            }
        }
        if (type === "polyline") {
            descripcionHtml += `<tr><td><strong>Fibras:</strong></td><td>${props.data.fibers_amount}</td></tr>`;
        }
        descripcionHtml += "</table></div>";
        properties["description"] = descripcionHtml;

        if (layer.options) {
            // --- Estilo de Línea/Contorno (Stroke) ---

            // Leaflet usa 'color' para el trazo y 'opacity' para la opacidad del trazo.
            const strokeColor = layer.options.color || "#3388ff";
            const strokeOpacity =
                layer.options.opacity !== undefined
                    ? layer.options.opacity
                    : 1.0;
            const strokeWeight =
                layer.options.weight !== undefined ? layer.options.weight : 3;

            // Propiedades GeoJSON que tokml mapea a KML <LineStyle>
            properties.stroke = toKmlColor(strokeColor, strokeOpacity);
            properties["stroke-width"] = strokeWeight;

            // --- Estilo de Relleno (Fill) ---
            if (type === "polygon" || type === "circle") {
                // Leaflet usa 'fillColor' y 'fillOpacity'
                const fillColor = layer.options.fillColor || strokeColor;
                const fillOpacity =
                    layer.options.fillOpacity !== undefined
                        ? layer.options.fillOpacity
                        : 0.2;

                // Propiedades GeoJSON que tokml mapea a KML <PolyStyle>
                properties.fill = toKmlColor(fillColor, fillOpacity);

                // KML requiere la etiqueta <fill> para habilitar el relleno.
                // Esto se habilita si hay color de relleno, pero tokml lo infiere de 'fill' y 'fill-opacity'.
            }

            // --- Estilo de Marcador (Marker) ---
            if (type === "marker") {
                // Puedes asignar un color fijo o basado en datos a 'marker-color'
                properties["marker-color"] =
                    properties["marker-color"] || "#ff0000";
            }
        }
        if (type === "marker") {
            const latlng = layer.getLatLng();
            const feature = {
                type: "Feature",
                properties: properties,
                geometry: {
                    type: "Point",
                    coordinates: [latlng.lng, latlng.lat],
                },
            };
            geojsonFeatureCollection.features.push(feature);
        }
        if (type === "polyline") {
            const latlngs = layer.getLatLngs();
            const coordinates = latlngs.map((ll) => [ll.lng, ll.lat]);
            const feature = {
                type: "Feature",
                properties: properties,
                geometry: {
                    type: "LineString",
                    coordinates: coordinates,
                },
            };
            geojsonFeatureCollection.features.push(feature);
        } else if (type === "polygon") {
            const latlngs = layer.getLatLngs();
            const coordinates = latlngs.map((ring) =>
                Array.isArray(ring)
                    ? ring.map((ll) => [ll.lng, ll.lat])
                    : [ring].map((ll) => [ll.lng, ll.lat])
            );
            const feature = {
                type: "Feature",
                properties: properties,
                geometry: {
                    type: "Polygon",
                    coordinates: coordinates,
                },
            };
            geojsonFeatureCollection.features.push(feature);
        } else if (type === "circle") {
            const center = layer.getLatLng();
            const radius = layer.getRadius();
            properties.radius = radius;
            const feature = {
                type: "Feature",
                properties: properties,
                geometry: {
                    type: "Polygon",
                    coordinates: generarPoligonoParaCirculo(center, radius),
                },
            };
            geojsonFeatureCollection.features.push(feature);
        }
    });
    return geojsonFeatureCollection;
};

const updateLoadingExport = (e, load) => {
    loadingExport.value[e] = load;
    loadingExport.value.loading = load;
};

const exportToKml = async () => {
    updateLoadingExport("kml", true);
    const kmlString = tokml(getGeoJsonFeatureCollection());
    const blob = new Blob([kmlString], {
        type: "application/vnd.google-earth.kml+xml",
    });
    const data = URL.createObjectURL(blob);
    exportMap(data, "mapa.kml");
    updateLoadingExport("kml", false);
};

const exportToKmz = async () => {
    updateLoadingExport("kmz", true);
    loadingExport.value.loading = true;
    const kmlString = tokml(getGeoJsonFeatureCollection());
    const zip = new JSZip();
    zip.file("doc.kml", kmlString);
    const blob = await zip.generateAsync({ type: "blob" });
    const data = URL.createObjectURL(blob);
    exportMap(data, "mapa.kmz");
    updateLoadingExport("kmz", false);
};

const exportToImage = () => {
    updateLoadingExport("img", true);
    const mapPane = map.getPanes().mapPane;
    const originalParent = mapPane.parentNode;
    const clipper = document.createElement("div");
    const mapSize = map.getSize();
    clipper.style.position = "absolute";
    clipper.style.top = "0px";
    clipper.style.left = "0px";
    clipper.style.width = mapSize.x + "px";
    clipper.style.height = mapSize.y + "px";
    clipper.style.overflow = "hidden";
    originalParent.appendChild(clipper);
    clipper.appendChild(mapPane);
    domtoimage
        .toPng(clipper)
        .then(function (dataUrl) {
            exportMap(dataUrl, "mapa.png");
        })
        .catch(function (error) {
            console.error("Oops, algo salió mal!", error);
            alert(
                "No se pudo exportar el mapa. Revisa la consola para más detalles."
            );
        })
        .finally(() => {
            updateLoadingExport("img", false);
            originalParent.appendChild(mapPane);
            originalParent.removeChild(clipper);
        });
};

const exportToPdf = () => {
    updateLoadingExport("pdf", true);
    const mapPane = map.getPanes().mapPane;
    const originalParent = mapPane.parentNode;
    const clipper = document.createElement("div");
    const mapSize = map.getSize();

    clipper.style.position = "absolute";
    clipper.style.top = "0px";
    clipper.style.left = "0px";
    clipper.style.width = mapSize.x + "px";
    clipper.style.height = mapSize.y + "px";
    clipper.style.overflow = "hidden";

    originalParent.appendChild(clipper);
    clipper.appendChild(mapPane);

    domtoimage
        .toPng(clipper)
        .then(function (dataUrl) {
            const pdf = new jsPDF({
                orientation: "landscape",
                unit: "mm",
                format: "a4",
            });
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = 10;
            const pdfWidth = pageWidth - margin * 2;
            const pdfHeight = pageHeight - margin * 2;
            const mapWidth = mapSize.x;
            const mapHeight = mapSize.y;
            const mapRatio = mapWidth / mapHeight;
            const pdfRatio = pdfWidth / pdfHeight;
            let imgWidth, imgHeight;
            if (mapRatio > pdfRatio) {
                imgWidth = pdfWidth;
                imgHeight = pdfWidth / mapRatio;
            } else {
                imgHeight = pdfHeight;
                imgWidth = pdfHeight * mapRatio;
            }
            const x = (pageWidth - imgWidth) / 2;
            const y = (pageHeight - imgHeight) / 2;
            pdf.addImage(dataUrl, "PNG", x, y, imgWidth, imgHeight);
            pdf.save("mapa.pdf");
        })
        .finally(() => {
            updateLoadingExport("pdf", false);
            originalParent.appendChild(mapPane);
            originalParent.removeChild(clipper);
        });
};

const exportMap = (data, name) => {
    const link = document.createElement("a");
    link.href = data;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(data);
};

const toKmlColor = (hexColor, opacity = 1) => {
    if (!hexColor || hexColor.length !== 7 || hexColor.charAt(0) !== "#") {
        return "ff000000";
    }
    const red = hexColor.slice(1, 3);
    const green = hexColor.slice(3, 5);
    const blue = hexColor.slice(5, 7);
    const alphaDecimal = Math.round(opacity * 255);
    const alpha = alphaDecimal.toString(16).padStart(2, "0");
    return alpha + blue + green + red;
};
</script>
<style scope>
.no-gutter-x > * {
    margin-left: 0px !important;
}
.q-checkbox.row {
    width: auto;
    padding-left: 5px !important;
}
.q-tree__node-header.row,
.q-tree__node-header-content.row {
    --bs-gutter-x: auto !important;
    margin-right: 0px !important;
}

.awesome-marker i {
    margin-top: 7px !important;
    font-size: 20px !important;
}
.z-index-marker {
    z-index: 9999 !important;
}
.q-inner-loading {
    z-index: 9999;
}
.leaflet-vertex-icon.leaflet-marker-draggable,
.leaflet-middle-icon.leaflet-marker-draggable {
    background: blue;
    border-radius: 50%;
    border: 2px solid white !important;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
    width: 12px !important;
    height: 12px !important;
    margin-top: -6px !important;
    margin-left: -6px !important;
}

.leaflet-middle-icon {
    opacity: 0.6;
}
.leaflet-marker-draggable {
    background-color: rgba(254, 87, 161, 0.1);
    border: 4px dashed blue;
    -webkit-border-radius: 4px;
    border-radius: 4px;
    box-sizing: content-box;
    margin-left: -21px !important;
    margin-top: -46px !important;
}
#tooltip {
    display: none;
    position: absolute;
    background: #666;
    color: white;
    opacity: 0.5;
    border: 1px dashed #999;
    font-family: sans-serif;
    font-size: 14px;
    line-height: 20px;
    z-index: 1000;
}
.leaflet-control-cascadeButtons .vertical button {
    font-size: 16px !important;
}
#fullscreen-map .leaflet-draw-actions,
#fullscreen-map .leaflet-draw-draw-polyline,
#fullscreen-map .leaflet-draw-draw-marker {
    display: none !important;
}

#sidebar-laeflet-map {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    background: white;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    resize: horizontal;
    overflow: auto;
    transition: none;
}

.leaflet-sidebar-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    cursor: col-resize;
    background: rgba(0, 0, 0, 0.1);
    z-index: 99 !important;
}
.leaflet-sidebar-pane.active {
    min-width: 50px !important;
}
.leaflet-touch .leaflet-control-layers-toggle {
    width: 30px !important;
    height: 30px !important;
}
.leaflet-control-layers-list {
    height: 57px !important;
}
.marker-cluster span {
    line-height: 30px;
    color: #000 !important;
}
.easy-button-button span {
    color: #000 !important;
}

/* MR-22 Fase 2 (item roadmap #9990458) — panel de capas encendibles. */
.capas-panel {
    background: white;
    min-width: 175px;
    font-size: 12px;
}
.capas-panel__header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    cursor: pointer;
    font-weight: 600;
}
.capas-panel__header .capas-panel__chevron {
    margin-left: auto;
}
.capas-panel__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 8px 8px;
    border-top: 1px solid #ddd;
}
.capas-panel__row {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 2px 0;
}
.capas-panel__row small {
    color: #888;
}
body.body--dark .capas-panel {
    background: #1d1d1d;
    color: #fff;
}
body.body--dark .capas-panel__body {
    border-top-color: #444;
}
</style>
