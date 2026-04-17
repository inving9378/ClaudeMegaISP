<template>
    <label for="object-color"
        >Color de fondo
        <q-badge
            :style="{
                'background-color': currentColor,
            }"
            >{{ currentColor }}</q-badge
        ></label
    >
    <q-color
        v-model="currentColor"
        no-header
        no-footer
        default-view="palette"
        class="no-gutter-x"
        :disable="disable"
        :palette="awesomeColors.map((c) => c.hex)"
        @update:model-value="(val) => emits('change-color', val)"
    />

    <label for="object-icon_color" class="q-mt-md"
        >Color de imagen
        <q-badge
            :style="{
                'background-color': currentColorIcon,
            }"
            >{{ currentColorIcon }}</q-badge
        ></label
    >
    <q-color
        v-model="currentColorIcon"
        no-header
        no-footer
        default-view="palette"
        class="no-gutter-x"
        :disable="disable"
        @update:model-value="(val) => emits('change-icon-color', val)"
    />

    <div class="column items-center q-pt-md">
        <i
            class="fa fa-map-marker fa-2x"
            :style="{
                color: currentColor,
                'font-size': '48px',
            }"
        ></i>
        <i
            class="mdi mdi-circle"
            :style="{
                color: currentColorIcon,
                'margin-top': '-47px',
                'font-size': '25px',
            }"
        ></i>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { awesomeColors } from "../helper/mapUtils";

defineOptions({
    name: "AwesomeMarkerIcon",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    disable: {
        type: Boolean,
        default: false,
    },
    color: String,
    iconColor: String,
});

const emits = defineEmits(["change-color", "change-icon-color"]);

const currentColor = ref(null);
const currentColorIcon = ref(null);

onMounted(() => {
    setDefaultData();
});

const setDefaultData = () => {
    currentColor.value = props.color ?? "#5bc0de";
    currentColorIcon.value = props.iconColor ?? "#ffffff";
};
</script>
