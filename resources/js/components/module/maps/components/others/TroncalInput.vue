<template>
    <q-card
        v-for="t in allInputs"
        :key="`col-${t.name}`"
        class="my-card q-mt-md"
    >
        <q-card-section class="q-pa-xs">
            <div class="text-h6 text-center">{{ t.name }}</div>
        </q-card-section>

        <template v-if="t.route">
            <q-separator />
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section>
                        <q-item-label class="text-center">
                            {{ t.route.text_node }}
                        </q-item-label>
                        <q-item-label
                            caption
                            class="text-bold text-center q-pt-xs"
                        >
                            Distancia
                        </q-item-label>
                        <q-item-label caption>
                            Total: {{ t.route.distance }} m</q-item-label
                        >
                        <q-item-label caption>
                            Calculada:
                            {{ t.route.calculate_distance }} m</q-item-label
                        ><q-item-label caption>
                            Real: {{ t.route.real_distance }} m</q-item-label
                        >
                    </q-item-section>
                </q-item>
            </q-card-section>
        </template>

        <q-separator />

        <q-card-actions align="around" v-if="hasEdit">
            <template v-if="t.route">
                <menu-route
                    :layer="layer"
                    :current-input="t.input"
                    :inputs="inputs"
                    :selected-routes="routes"
                    :object="t"
                    @update="(data) => emits('update', data)"
                /><q-btn
                    flat
                    no-caps
                    label="Eliminar"
                    color="danger"
                    @click="
                        {
                            route = t.route;
                            showConfirm = true;
                        }
                    "
                /> </template
            ><menu-route
                :layer="layer"
                :current-input="t.input"
                :inputs="inputs"
                :selected-routes="routes"
                @update="(data) => emits('update', data)"
                v-else
            />
        </q-card-actions>
    </q-card>
    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar esta troncal"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { computed, ref } from "vue";
import MenuRoute from "./MenuRoute.vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import { unassignRoute } from "../../helper/layers-request";
import DialogConfirm from "./DialogConfirm.vue";
import { inputs, routes } from "../../../../../composables/useMapConnections";

defineOptions({
    name: "TroncalInput",
});

const props = defineProps({
    layer: Object,
    position: {
        type: String,
        default: "left",
    },
    hasEdit: Boolean,
});

const emits = defineEmits(["update"]);

const showConfirm = ref(false);
const route = ref(null);

const allInputs = computed(() => {
    let temp = [];
    for (let i = 1; i <= inputs.value; i++) {
        if (props.position === "left") {
            if (i % 2 !== 0) {
                const route =
                    routes.value.find((r) => r.current_input === i) || null;
                temp.push({
                    name: `ENTRADA ${i}`,
                    route,
                    input: i,
                });
            }
        } else {
            if (i % 2 === 0) {
                const route =
                    routes.value.find((r) => r.current_input === i) || null;
                temp.push({
                    name: `ENTRADA ${i}`,
                    route,
                    input: i,
                });
            }
        }
    }
    return temp;
});

const destroy = async () => {
    showLoading();
    let result = await unassignRoute(route.value.route_id);
    hideLoading();
    if (result) {
        emits("update", result);
        message("Ruta eliminada correctamente", "success");
    } else {
        message("No se ha podido eliminar esta ruta", "error");
    }
    showConfirm.value = false;
};
</script>
