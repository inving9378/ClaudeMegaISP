<template>
    <q-btn
        flat
        no-caps
        :label="object ? 'Editar' : 'Agregar troncal'"
        color="primary"
    >
        <q-menu
            v-model="menu"
            style="width: 300px"
            anchor="top right"
            self="top left"
            transition-show="scale"
            transition-hide="scale"
        >
            <q-card>
                <q-card-section class="q-pb-none">
                    <q-form greedy ref="form">
                        <template v-if="object">
                            <label for="object-route" class="q-mt-md"
                                >Ruta</label
                            >
                            <q-input
                                v-model.number="data.route"
                                type="text"
                                for="object-route"
                                outlined
                                dense
                                readonly
                                disable
                            />

                            <label
                                for="object-calculate_distance"
                                class="q-mt-md"
                                >Distancia calculada</label
                            >
                            <q-input
                                v-model.number="data.calculate_distance"
                                type="number"
                                for="object-calculate_distance"
                                outlined
                                dense
                                readonly
                                disable
                            />

                            <label for="object-real_distance" class="q-mt-md"
                                >Distancia real en metros</label
                            >
                            <q-input
                                :rules="[(val) => rules.required(val)]"
                                v-model.number="data.real_distance"
                                type="number"
                                for="object-real_distance"
                                outlined
                                dense
                            />
                        </template>
                        <avaiables-routes-component
                            :layer="layer"
                            :multiple="false"
                            :selected-routes="selectedRoutes"
                            @update="(val) => (data = val)"
                            v-else
                        />
                    </q-form>
                </q-card-section>
                <q-card-actions align="center" class="no-gutter-x">
                    <q-btn
                        no-caps
                        color="primary"
                        label="Guardar"
                        class="q-mx-sm"
                        @click="save"
                    />
                    <q-btn no-caps label="Cancelar" @click="menu = false" />
                </q-card-actions>
            </q-card>
        </q-menu>
    </q-btn>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import { createInput, updateInput } from "../../helper/layers-request";
import AvaiablesRoutesComponent from "./AvaiablesRoutesComponent.vue";
import { rules } from "../../../../../helpers/validations";

defineOptions({
    name: "MenuRoute",
});

const props = defineProps({
    layer: Object,
    inputs: Number,
    currentInput: Number,
    object: Object,
    icon: {
        type: String,
        default: "mdi-chart-timeline-variant",
    },
    size: {
        type: String,
        default: "sm",
    },
    flat: Boolean,
    label: String,
    selectedRoutes: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["update"]);

const form = ref(null);
const menu = ref(false);
const data = ref({
    route: null,
    calculate_distance: 0,
    real_distance: 0,
});

onMounted(() => {
    setDefaultValues();
});

watch(
    () => props.object,
    () => {
        setDefaultValues();
    },
    {
        deep: true,
    }
);

const setDefaultValues = () => {
    if (props.object) {
        const { text_node, real_distance, calculate_distance } =
            props.object.route;
        data.value = {
            route: text_node,
            calculate_distance,
            real_distance,
        };
    } else {
        data.value = {
            route: null,
            calculate_distance: 0,
            real_distance: 0,
        };
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            if (props.object) {
                update();
            } else {
                create();
            }
        } else {
            errorValidation();
        }
    });
};

const create = async () => {
    showLoading();
    const inputs = props.currentInput;
    const result = await createInput(props.layer.id, {
        inputs,
        ...data.value,
        update_layer: inputs > props.inputs,
    });
    hideLoading();
    if (result) {
        message("Entrada configurada correctamente", "success");
        emits("update", result);
        menu.value = false;
    } else {
        message(
            "Error al tratar de agregar la entrada; consulte al administrador",
            "error"
        );
    }
};

const update = async () => {
    showLoading();
    const result = await updateInput(props.object.route.route_id, {
        ...data.value,
    });
    hideLoading();
    if (result) {
        message("Entrada modificada correctamente", "success");
        emits("update", result);
        menu.value = false;
    } else {
        message(
            "Error al tratar de modificar la entrada; consulte al administrador",
            "error"
        );
    }
};
</script>
