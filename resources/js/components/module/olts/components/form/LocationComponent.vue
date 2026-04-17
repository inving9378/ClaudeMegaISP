<template>
    <div id="map" style="height: 300px; width: 100%"></div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";
import "leaflet-mouse-position/src/L.Control.MousePosition.css";
import "leaflet-mouse-position/src/L.Control.MousePosition.js";
import "leaflet-control-geocoder/dist/Control.Geocoder.css";
import "leaflet-control-geocoder";

defineOptions({
    name: "LocationComponent",
});

const props = defineProps({
    coordinates: Object,
    show: Boolean,
});

const emits = defineEmits(["change-location"]);

let map = null;
let drawnItems = null;
const currentMarker = ref(null);

onMounted(() => {
    initMap();
});

watch(
    currentMarker,
    (n) => {
        const latlng = n?._latlng ?? null;
        emits("change-location", {
            lat: latlng?.lat ?? null,
            lng: latlng?.lng ?? null,
        });
    },
    {
        deep: true,
    }
);

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
        currentMarker.value = e.layer;
        drawnItems.addLayer(currentMarker.value);
    });

    map.on(L.Draw.Event.EDITED, function (e) {
        let layers = e.layers;
        layers.eachLayer(function (layer) {
            currentMarker.value = layer;
        });
    });

    map.on(L.Draw.Event.DELETED, function (e) {
        currentMarker.value = null;
    });

    if (props.coordinates) {
        let { lat, lng } = props.coordinates;
        if (lat && lng) {
            currentMarker.value = L.marker(
                {
                    lat,
                    lng,
                },
                {
                    icon: L.AwesomeMarkers.icon({
                        icon: "map-marker",
                        markerColor: "blue",
                        prefix: "fa",
                    }),
                }
            );
            currentMarker.value.addTo(drawnItems);
            map.fitBounds(drawnItems.getBounds());
        }
    }
};
</script>
