<template>
    <div>
        <div class="row">
            <div class="col">
                <div id="map" style="height: 640px; width: 100%"></div>
            </div>
        </div>
        <div class="row q-py-md">
            <div class="col">
                <div class="form-group">
                    <label for="name">Latitud</label>
                    <input
                        type="number"
                        class="form-control"
                        disabled
                        v-model="mapsForm.latitude"
                    />
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label for="name">Longitud</label>
                    <input
                        type="number"
                        class="form-control"
                        disabled
                        v-model="mapsForm.longitude"
                    />
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label for="name">Zoom</label>
                    <input
                        type="number"
                        class="form-control"
                        disabled
                        v-model="mapsForm.zoom"
                    />
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label for="name">Api Key</label>
                    <input
                        type="text"
                        class="form-control"
                        v-model="mapsForm.api_key"
                    />
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 q-pt-md">
                <button
                    class="btn btn-secondary px-4 shadow-none"
                    type="submit"
                    @click="handleCancel"
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-primary px-4 shadow-none"
                    type="submit"
                    @click="handleSave"
                >
                    Guardar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";
import "leaflet-mouse-position/src/L.Control.MousePosition.css";
import "leaflet-mouse-position/src/L.Control.MousePosition.js";
import "leaflet-control-geocoder/dist/Control.Geocoder.css";
import "leaflet-control-geocoder";
import { updateMap, getMap } from "./helper/request";
import Swal from "sweetalert2";

defineOptions({
    name: "LeafletMap",
});

const emits = defineEmits(["save"]);
let map = null;
const dialog = ref(false);
let drawnItems = null;
let currentMarker = null;

const currentCoordinates = ref(null);
const coordinates = ref(null);
const addressClient = ref();

const mapsForm = ref({
    id: undefined,
    zoom: 16,
    api_key: null,
    latitude: null,
    longitude: null,
});

const initMap = async () => {
    await loadFormFromDb();
    const { latitude, longitude, zoom } = mapsForm.value;

    map = L.map("map").setView(
        [latitude ?? 23.6345, longitude ?? -102.5528],
        zoom ?? 12
    );
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    map.addControl(
        new L.Control.Fullscreen({
            position: "topleft",
            title: {
                false: "Pantalla completa",
                true: "Salir de pantalla completa",
            },
        })
    );

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
            .addTo(map)
            .bindPopup(name)
            .openPopup();
        map.fitBounds(bbox, {
            padding: [50, 50],
            maxZoom: 16,
        });
    });

    drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var OriginalDrawMarker = L.Draw.Marker;
    L.Draw.Marker = OriginalDrawMarker.extend({
        addHooks: function () {
            OriginalDrawMarker.prototype.addHooks.call(this);
            this.options.icon = L.AwesomeMarkers.icon({
                icon: "circle",
                markerColor: "blue",
                prefix: "mdi",
            });
        },
    });

    let drawControl = new L.Control.Draw({
        position: "topright",
        draw: {
            polyline: false,
            circle: false,
            circlemarker: false,
            polygon: false,
            rectangle: false,
            marker: {
                icon: new L.Icon.Default(),
            },
        },
        edit: {
            featureGroup: drawnItems,
            remove: true,
        },
    });

    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.clearLayers();
        currentMarker = e.layer;
        drawnItems.addLayer(currentMarker);
        const latlng = currentMarker._latlng;
        coordinates.value = `${latlng.lat}, ${latlng.lng}`;
    });

    map.on(L.Draw.Event.EDITED, function (e) {
        let layers = e.layers;
        layers.eachLayer(function (layer) {
            currentMarker = layer;
        });
        const latlng = currentMarker._latlng;
        coordinates.value = `${latlng.lat}, ${latlng.lng}`;
    });

    map.on(L.Draw.Event.DELETED, function (e) {
        currentMarker = null;
        coordinates.value = null;
    });

    map.on("moveend", updateDataFromMap);
    map.on("zoomend", updateDataFromMap);
    map.on("resize", updateDataFromMap);

    window.addEventListener("resize", function () {
        setTimeout(updateDataFromMap, 100);
    });
};

const updateDataFromMap = () => {
    const { lat, lng } = map.getCenter();
    mapsForm.value.latitude = lat;
    mapsForm.value.longitude = lng;
    mapsForm.value.zoom = map.getZoom();
};

function updateForm(map) {
    mapsForm.value.id = map?.id ?? null;
    mapsForm.value.latitude = map?.latitude ? parseFloat(map.latitude) : null;
    mapsForm.value.longitude = map?.longitude
        ? parseFloat(map.longitude)
        : null;
    mapsForm.value.zoom = map?.zoom ?? null;
    mapsForm.value.api_key = map?.api_key ?? null;
}

const handleSave = async () => {
    try {
        await updateMap(mapsForm.value.id, {
            latitude: mapsForm.value.latitude.toString(),
            longitude: mapsForm.value.longitude.toString(),
            zoom: mapsForm.value.zoom,
            api_key: mapsForm.value.api_key,
        });
        Swal.fire(
            "¡Actualizado!",
            "Posición guardada correctamente",
            "success"
        );
    } catch (error) {
        console.log(error);

        Swal.fire(
            "¡Error!",
            "Sucedió un error al guardar la posición actual",
            "error"
        );
    }
};

const handleCancel = () => {
    loadFormFromDb();
};

onMounted(async () => {
    initMap();
});

async function loadFormFromDb() {
    const map = await getMap();
    updateForm(map);
}
</script>
