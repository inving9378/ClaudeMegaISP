<template>
    <div id="map" style="height: 540px; width: 100%"></div>
</template>

<script setup>
import { onMounted, ref } from "vue";

import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";
import "leaflet-mouse-position/src/L.Control.MousePosition.css";
import "leaflet-mouse-position/src/L.Control.MousePosition.js";
import "leaflet-control-geocoder/dist/Control.Geocoder.css";
import "leaflet-control-geocoder";

defineOptions({
    name: "LeafletMap",
});

const props = defineProps({
    coordinates: String | Object,
    field: {
        type: String,
        default: "map",
    },
    show: Boolean,
});

const emits = defineEmits(["getCoordenate", "change-coordinate", "hide"]);

let map = null;
const dialog = ref(false);
let drawnItems = null;
let currentMarker = null;

onMounted(() => {
    initMap();
});

const initMap = () => {
    map = L.map("map").setView([23.6345, -102.5528], 5);
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
    });

    map.on(L.Draw.Event.EDITED, function (e) {
        let layers = e.layers;
        layers.eachLayer(function (layer) {
            currentMarker = layer;
            layers.stopListening();
            return;
        });
    });

    map.on(L.Draw.Event.DELETED, function (e) {
        currentMarker = null;
    });

    if (props.coordinates) {
        let _latlng = props.coordinates.split(",");
        currentMarker = L.marker(
            {
                lat: _latlng[0].trim(),
                lng: _latlng[1].trim(),
            },
            {
                icon: L.AwesomeMarkers.icon({
                    icon: "map-marker",
                    markerColor: "blue",
                    prefix: "fa",
                }),
            }
        );
        currentMarker.addTo(drawnItems);
        map.fitBounds(drawnItems.getBounds());
    }
};

const saveCoordinates = () => {
    let coords = null;
    if (currentMarker) {
        const latlng = currentMarker._latlng;
        coords = `${latlng.lat}, ${latlng.lng}`;
    }
    emits("change-coordinate", coords);
    dialog.value = false;
};
</script>

<style>
#map {
    width: 100%;
    height: 500px;
}
</style>
