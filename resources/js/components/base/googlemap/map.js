import { ref } from "vue";
var marker;

export const valCoordenates = ref();
export const map = ref();

export const initMap = (lat, lng, init = true, field) => {
    if (map.value) {
        let coordenada = new google.maps.LatLng(lat, lng);
        map.value.setCenter(coordenada);
    }

    if (!map.value) {
        map.value = new google.maps.Map(document.getElementById(field), {
            center: { lat: lat, lng: lng },
            zoom: 16,
        });
    }

    if (map.value) {
        // Elimina el marcador existente, si lo hay
        if (marker) {
            marker.setMap(null);
        }

        // Crea un nuevo marcador en la ubicación del clic
        let myLatLng = { lat, lng };
        marker = new google.maps.Marker({
            position: myLatLng,
            map: map.value,
            title: "Ubicacion",
        });
    }

    if (init) {
        // Agrega el evento de clic al mapa
        map.value.addListener("click", function (event) {
            // Obtiene las coordenadas del clic
            var latLng = event.latLng;
            valCoordenates.value = {
                lat: latLng.lat(),
                lng: latLng.lng(),
            };
            // Elimina el marcador existente, si lo hay
            if (marker) {
                marker.setMap(null);
            }
            marker = new google.maps.Marker({
                position: latLng,
                map: map.value,
                title: "Ubicacion",
            });
        });
    }
};
