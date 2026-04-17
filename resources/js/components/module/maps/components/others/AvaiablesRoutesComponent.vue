<template>
    <label for="object-route" :class="cls">Ruta</label>
    <q-select
        v-model="data.route"
        for="object-route"
        :rules="[(val) => rules.required(val)]"
        outlined
        dense
        options-dense
        emit-value
        map-options
        use-input
        input-debounce="0"
        :use-chips="multiple"
        :multiple="multiple"
        :options="options"
        :option-disable="
            (item) =>
                selectedRoutes.filter((s) => s.id === item.value).length >= 2
        "
        :loading="loading"
        @filter="filterFn"
        @update:model-value="onChangeRute"
    >
        <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps">
                <q-item-section>
                    <q-item-label>{{ scope.opt.label }}</q-item-label>
                    <q-item-label caption
                        >Longitud de la troncal:
                    </q-item-label>
                    <q-item-label caption
                        >Total: {{ scope.opt.distance }} m</q-item-label
                    >
                    <q-item-label caption
                        >Hasta el marcador actual:
                        {{ scope.opt.distancePoint }} m</q-item-label
                    >
                </q-item-section>
            </q-item>
        </template>
    </q-select>

    <label for="object-calculate_distance">Distancia calculada</label>
    <q-input
        v-model.number="data.calculate_distance"
        type="number"
        for="object-calculate_distance"
        outlined
        dense
        readonly
        disable
        @update:model-value="() => emits('update', data)"
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
        @update:model-value="() => emits('update', data)"
    />

    <template v-if="layer['id'] === null || layer['id'] === undefined">
        <label for="object-input">Entrada</label>
        <q-select
            v-model="data.input"
            for="object-input"
            :rules="[(val) => rules.required(val)]"
            outlined
            dense
            options-dense
            emit-value
            map-options
            :options="inputOptions"
            :option-disable="
                (opt) =>
                    layer.selected_routes
                        .map((r) => r.input)
                        .includes(opt.value)
            "
            @update:model-value="() => emits('update', data)"
        />
    </template>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { rules } from "../../../../../helpers/validations";
import { getNearbyRoutes } from "../../helper/mapUtils";

defineOptions({
    name: "AvaiablesRoutesComponent",
});

const props = defineProps({
    layer: Object,
    cls: String,
    multiple: {
        type: Boolean,
        default: true,
    },
    clear: Boolean,
    selectedRoutes: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["update", "clear"]);

const data = ref({
    route: null,
    calculate_distance: 0,
    real_distance: 0,
    input: null,
    text: null,
    coords: null,
});
const loading = ref(false);
const options = ref([]);
const allOptions = ref([]);
const inputOptions = ref([
    {
        label: 1,
        value: 1,
    },
    {
        label: 2,
        value: 2,
    },
    {
        label: 3,
        value: 3,
    },
    {
        label: 4,
        value: 4,
    },
    {
        label: 5,
        value: 5,
    },
    {
        label: 6,
        value: 6,
    },
]);

onMounted(() => {
    getRoutes();
});

watch(
    () => props.layer?.selected_routes,
    (n) => {
        data.value.route = n && n.length === 0 ? null : n;
    }
);

watch(
    () => props.clear,
    (n) => {
        if (n) {
            data.value = {
                route: null,
                total_distance: 0,
                calculate_distance: 0,
                real_distance: 0,
                input: null,
                text: null,
                coords: null,
            };
            emits("clear");
        }
    }
);

const getRoutes = async () => {
    loading.value = true;
    const result = await getNearbyRoutes(props.layer);
    loading.value = false;
    if (result) {
        options.value = result.map((r) => {
            return {
                label: r.route.text_node,
                value: r.route.id,
                distance: r.newDistance,
                distancePoint: r.distancePoint,
                newCoordinates: r.newCoordinates,
            };
        });
        let temp = options.value;
        allOptions.value = temp;
    }
};

const onChangeRute = (val) => {
    const distance = options.value.find((o) => o.value === val);
    data.value.calculate_distance = distance?.distancePoint ?? 0;
    data.value.total_distance = distance?.distance ?? 0;
    data.value.text = distance?.label ?? null;
    data.value.coords = distance?.newCoordinates ?? null;
    const found = props.selectedRoutes.find((s) => s.id === val);
    data.value.real_distance = found?.real_distance ?? 0;
    emits("update", data.value);
};

const filterFn = (val, update, abort) => {
    setTimeout(() => {
        update(
            () => {
                if (val === "") {
                    options.value = allOptions.value;
                } else {
                    const needle = val.toLowerCase();
                    options.value = allOptions.value.filter((v) =>
                        v.label
                            ? v.label.toLowerCase().indexOf(needle) > -1
                            : v.toLowerCase().indexOf(needle) > -1
                    );
                }
            },
            (ref) => {
                if (
                    val !== "" &&
                    ref.options.length > 0 &&
                    ref.getOptionIndex() === -1
                ) {
                    ref.moveOptionSelection(1, true);
                    ref.toggleOption(ref.options[ref.optionIndex], true);
                }
            }
        );
    }, 100);
};
</script>

<style scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
</style>
