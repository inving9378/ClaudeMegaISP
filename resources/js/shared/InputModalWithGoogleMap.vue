<template>
    <div :class="`form-group row`">
        <label
            for="inputGoogleMap"
            class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end"
        ></label>
        <div class="input-group col-sm-12 col-md-8">
            <input
                id="inputGoogleMap"
                type="text"
                :class="{ 'form-control': true }"
                v-model="currentCoordinates"
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

    <q-dialog v-model="dialog" persistent @show="initMap" @hide="emits('hide')">
        <q-card flat>
            <q-card-section>
                <div class="text-h6">Seleccione la ubicación</div>
            </q-card-section>
            <q-separator />
            <q-card-section style="width: 520px">
                <div id="map" style="height: 540px; width: 100%"></div>

                <div class="row">
                    <div class="col-sm-12 col-xl-4">
                        <div class="mb-2">
                            <label>Dirección</label>
                            <span class="form-control">{{
                                addressClient
                            }}</span>
                        </div>
                        <div>
                            <label>Coordenadas</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model="coordinates"
                                disabled
                            />
                        </div>
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

defineOptions({
    name: "LeafletMap",
});

const props = defineProps({
    position: String | Object,
    address: String,
    show: Boolean,
});

const emits = defineEmits(["change-coordinate", "hide"]);

let map = null;
const dialog = ref(false);
let drawnItems = null;
let currentMarker = null;

const currentCoordinates = ref(null);
const coordinates = ref(null);
const addressClient = ref();

onBeforeMount(() => {
    if (props.position) {
        let _latlng = props.position.split(",");
        currentCoordinates.value = `${_latlng[0].trim()}, ${_latlng[1].trim()}`;
    }
});

watch(
    () => props.address,
    (address) => {
        addressClient.value = address;
    }
);

watch(
    () => props.position,
    (n) => {
        if (n) {
            let _latlng = n.split(",");
            currentCoordinates.value = `${_latlng[0].trim()}, ${_latlng[1].trim()}`;
        } else {
            currentCoordinates.value = null;
        }
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

    if (currentCoordinates.value) {
        let _latlng = currentCoordinates.value.split(",");
        coordinates.value = props.position;
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
    currentCoordinates.value = coords;
    emits("change-coordinate", coords);
    dialog.value = false;
};
</script>

<style scoped></style>
