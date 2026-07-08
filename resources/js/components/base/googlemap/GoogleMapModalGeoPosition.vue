<template>
    <google-map
        v-if="ikey"
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
import { fetchGoogleMapsConfig } from "../../../helpers/googleMapsConfig";

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
        // La key viene del servidor (BD/env), no del bundle. `ikey` arranca null
        // y el <google-map> se monta (v-if) cuando llega la key real.
        const ikey = ref(null);
        const markerOptions = reactive({ position: center, label: 'L', title: 'Lugar' });

        onMounted(async () => {
            const cfg = await fetchGoogleMapsConfig();
            if (cfg?.api_key) ikey.value = cfg.api_key;
        });

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
