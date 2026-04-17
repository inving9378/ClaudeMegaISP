<template>
    <q-img
        :src="image"
        :fit="fit"
        spinner-color="primary"
        spinner-size="82px"
        :style="{
            'max-width': maxWidth ?? '100%',
        }"
    >
        <template v-slot:loading>
            <div class="text-subtitle1 text-white">Obteniendo...</div>
        </template>
        <template v-slot:error>
            <div class="text-subtitle1 text-danger">
                {{ error }}
            </div>
        </template>
    </q-img>
    <q-inner-loading
        :showing="loading"
        label="Obteniendo datos..."
        label-class="text-teal"
        label-style="font-size: 1.1em"
    />
</template>

<script setup>
import { onMounted, ref } from "vue";
import { getOLTData } from "../../helper/request";

defineOptions({
    name: "ImageComponent",
});

const props = defineProps({
    onu: Object,
    fit: {
        type: String,
        default: "fill",
    },
    maxWidth: String,
    url: String,
});

const image = ref("/images/desconocida.png");
const loading = ref(false);
const error = ref(null);

onMounted(async () => {
    if (props.url) {
        loading.value = true;
        const result = await getOLTData(props.url);
        loading.value = false;
        image.value = result.image ?? "/images/desconocida.png";
        error.value = result.message ?? "Error al cargar la información";
    }
});
</script>
