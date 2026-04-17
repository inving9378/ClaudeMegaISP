<template>
    <div class="row">
        <div
            class="col col-auto text-right"
            style="padding: 8px 0px !important; min-width: 53px !important"
        >
            <label :for="name">{{ label }}</label>
        </div>
        <div class="col">
            <q-select
                v-model="model"
                :options="options"
                :option-label="optionLabel"
                :option-value="optionValue"
                :multiple="multiple"
                :loading="loading"
                :for="name"
                emit-value
                map-options
                outlined
                dense
                options-dense
                clearable
                behavior="menu"
                @filter="filterFn"
                @filter-abort="abortFilterFn"
                @update:model-value="(val) => emits('change', name, val)"
                @popup-show="isMenuOpen = true"
                @popup-hide="isMenuOpen = false"
            >
                <template v-slot:option="{ itemProps, opt, selected }">
                    <q-item v-bind="itemProps">
                        <q-item-section avatar side>
                            <q-icon
                                :name="
                                    selected
                                        ? 'check_circle'
                                        : 'radio_button_unchecked'
                                "
                                :color="selected ? 'primary' : 'grey'"
                                class="q-mr-sm"
                            />
                        </q-item-section>
                        <q-item-section>
                            <q-item-label>{{ opt[optionLabel] }}</q-item-label>
                        </q-item-section>
                        <q-item-section
                            avatar
                            v-if="
                                opt['count'] !== null &&
                                opt['count'] !== undefined
                            "
                        >
                            <q-badge outline align="middle" color="primary">
                                {{ opt.count }}
                            </q-badge>
                        </q-item-section>
                    </q-item>
                </template>
            </q-select>
        </div>
    </div>
</template>

<script setup>
import axios from "axios";
import { cloneDeep, isEqual, keys } from "lodash";
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "SelectComponent",
});

const props = defineProps({
    name: String,
    optionLabel: String,
    optionValue: String,
    label: String,
    modelValue: String | Number | Boolean,
    multiple: {
        type: Boolean,
        default: true,
    },
    filters: {
        type: Object,
        default: {},
    },
});

const emits = defineEmits(["change"]);

const model = ref(null);
const lastFiltersUsed = ref(null);
const options = ref([]);
const loading = ref(false);
const isMenuOpen = ref(false);
let currentFilters = ref({});

onMounted(async () => {
    if (props.modelValue) {
        const data = await loadData();
        options.value = data;
        model.value = props.modelValue;
    }
});

watch(
    () => props.modelValue,
    async (n) => {
        if (!isMenuOpen || options.value.length === 0) {
            const data = await loadData();
            options.value = data;
            model.value = n;
        }
    }
);

const filterFn = async (val, update, abort) => {
    if (isMenuOpen.value && options.value.length > 0) {
        update();
        return;
    }

    currentFilters = cloneDeep(props.filters || {});
    if (props.name && currentFilters[props.name]) {
        delete currentFilters[props.name];
    }

    const externalFiltersChanged = !isEqual(
        lastFiltersUsed.value,
        currentFilters
    );

    if (options.value.length > 0 && !externalFiltersChanged) {
        update();
        return;
    }

    try {
        const data = await loadData();
        lastFiltersUsed.value = currentFilters;
        update(() => {
            options.value = data;
        });
    } catch (e) {
        abort();
    }
};

const abortFilterFn = () => {
    // console.log('delayed filter aborted')
};

const loadData = async () => {
    if (props.optionLabel && props.optionValue) {
        loading.value = true;
        const result = await axios.post("/olts/onus/nomenclatures", {
            label: props.optionLabel,
            value: props.optionValue,
            filters:
                Object.keys(currentFilters).length > 0 ? currentFilters : null,
        });
        loading.value = false;
        return result?.data ?? [];
    }
    return [];
};
</script>
