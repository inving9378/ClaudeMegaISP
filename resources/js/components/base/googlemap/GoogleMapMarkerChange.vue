<template>
    <google-map
        :api-key="ikey"
        style="width: 100%; height: 500px"
        :center="center"
        :zoom="15"
    >
        <Marker
            :key="center"
            :options="markerOptions"
        />
    </google-map>
</template>

<script>
import { defineComponent, ref, reactive, onMounted } from "vue";
import { GoogleMap, Marker } from "vue3-google-map";

export default defineComponent({
    components: { GoogleMap, Marker },
    props: {
        latitude: {
            type: Number,
            defaults: 24.2321511,
        },
        longitude: {
            type: Number,
            defaults: -102.8257218,
        },
    },
    setup(props) {
        const center = ref({ lat: props.latitude, lng: props.longitude });
        const ikey = process.env.MIX_VUE_APP_GOOGLEMAPS_KEY;
        const markerOptions = reactive({ position: center, label: 'L', title: 'Lugar' });

        const clickedMarker = (e) => {
            if (e)
                center.value = {
                    lat: e.latLng.lat(),
                    lng: e.latLng.lng()
                }
        }

        const dblclick = (e) => {
        }

        return {
            ikey,
            center,
            markerOptions,
            clickedMarker,
            dblclick
        };
    },
});
</script>
