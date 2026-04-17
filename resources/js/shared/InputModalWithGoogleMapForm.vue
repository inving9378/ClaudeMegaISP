<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'} `"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field} input-group`">
            <input
                :id="property.field"
                type="text"
                :class="{ 'form-control': true }"
                v-model="coordinates"
                disabled
            />
            <div class="input-group-append">
                <span
                    class="input-group-text bg-primary text-white"
                    :id="`field1`"
                    style="height: 100%"
                    ><i
                        class="fa fa-map cursor-pointer"
                        @click="dialog = true"
                    ></i
                ></span>
            </div>
        </div>
    </div>
    <q-dialog v-model="dialog" persistent @show="initMap">
        <q-card flat>
            <q-card-section>
                <div class="text-h6">Seleccione la ubicación</div>
            </q-card-section>
            <q-separator />
            <q-card-section style="width: 520px">
                <div id="map" style="height: 540px; width: 100%"></div>
                <div class="row q-pt-md">
                    <div class="col">
                        <label for="coordinates">Coordenadas</label>
                        <input
                            type="text"
                            class="form-control"
                            v-model="currentCoordinates"
                            id="coordinates"
                        />
                    </div>
                </div>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Aplicar"
                    color="primary"
                    class="q-mr-sm"
                    no-caps
                    @click="saveCoordinates"
                />
                <q-btn label="Cancelar" no-caps @click="dialog = false" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { onBeforeMount, ref, watch } from "vue";
import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers.css";
import "leaflet.awesome-markers/dist/leaflet.awesome-markers";
import "leaflet-mouse-position/src/L.Control.MousePosition.css";
import "leaflet-mouse-position/src/L.Control.MousePosition.js";
import "leaflet-control-geocoder/dist/Control.Geocoder.css";
import "leaflet-control-geocoder";
import { message } from "../helpers/toastMsg";

defineOptions({
    name: "InputModalWithGoogleMapForm",
});

const props = defineProps({
    errors: {
        type: Object,
        default: {},
    },
    property: Object,
    modelValue: String,
});

const emits = defineEmits(["update-field", "click"]);

const currentCoordinates = ref(null);

const coordinates = ref(null);
let map = null;
const dialog = ref(false);
let drawnItems = null;
let currentMarker = null;

onBeforeMount(() => {
    coordinates.value = props.modelValue ?? null;
});

watch(currentCoordinates, (n) => {
    if (n) {
        const latLng = n.split(",");
        if (
            latLng.length === 2 &&
            !isNaN(parseFloat(latLng[0])) &&
            !isNaN(parseFloat(latLng[1]))
        ) {
            if (currentMarker) {
                currentMarker.setLatLng({
                    lat: parseFloat(latLng[0]),
                    lng: parseFloat(latLng[1]),
                });
                map.fitBounds(drawnItems.getBounds());
            } else {
                createMarker(n);
            }
        } else {
            if (currentMarker) {
                drawnItems.removeLayer(currentMarker);
                currentMarker = null;
            }
        }
    } else {
        if (currentMarker) {
            drawnItems.removeLayer(currentMarker);
            currentMarker = null;
        }
    }
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
        updateData();
    });

    map.on(L.Draw.Event.EDITED, function (e) {
        let layers = e.layers;
        layers.eachLayer(function (layer) {
            currentMarker = layer;
        });
        updateData();
    });

    map.on(L.Draw.Event.DELETED, function (e) {
        currentMarker = null;
        updateData();
    });

    if (coordinates.value) {
        createMarker(coordinates.value);
    }
};

const updateData = () => {
    if (currentMarker) {
        const { lat, lng } = currentMarker._latlng;
        currentCoordinates.value = `${lat},${lng}`;
    } else {
        currentCoordinates.value = null;
    }
};

const createMarker = (coordinates) => {
    if (coordinates) {
        let _latlng = coordinates.split(",");
        if (_latlng.length === 2) {
            currentMarker = L.marker(
                {
                    lat: parseFloat(_latlng[0]),
                    lng: parseFloat(_latlng[1]),
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
    }
    updateData();
};

const saveCoordinates = () => {
    let coords = null;
    if (currentMarker) {
        const latlng = currentMarker._latlng;
        coords = `${latlng.lat}, ${latlng.lng}`;
        coordinates.value = coords;
        emits("update-field", { value: coords, field: props.property.field });
        dialog.value = false;
    } else {
        message("Debe especificar la ubicación", "error");
    }
};
</script>

<style scoped></style>
