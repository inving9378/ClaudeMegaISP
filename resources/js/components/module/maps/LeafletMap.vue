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

import { getClientsWithoutProject, getMap, saveObject } from "./helper/request";

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
let baseLayers = null;
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

onBeforeMount(async () => {
    serverData = await getMap();
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

    L.control.layers(baseLayers).addTo(map);

    map.on("baselayerchange", function (e) {
        const newLayer = e.layer;
        const newMaxZoom = newLayer.options.maxZoom ?? 19;
        map.setMaxZoom(newMaxZoom);
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

    // await initGoogleMapsLayer(data);
    // onGoogleMapsReady();
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

const initGoogleMapsLayer = (data) => {
    if (data.api_key) {
        return new Promise((resolve) => {
            const script = document.createElement("script");
            script.src = `https://maps.googleapis.com/maps/api/js?key=${data.api_key}`;
            script.async = true;
            script.defer = true;
            script.onload = () => {
                const googleLayer = L.gridLayer.googleMutant({
                    type: "hybrid",
                    maxZoom: 24,
                    apiKey: data.api_key,
                });
                baseLayers["Satelital"] = googleLayer;
                resolve(googleLayer);
            };
            document.head.appendChild(script);
        });
    }
    return null;
};

const onGoogleMapsReady = () => {
    if (Object.keys(baseLayers).length > 1) {
        L.control.groupedLayers(baseLayers).addTo(map);
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
</style>
