<template>
    <q-select
        v-model="model"
        :options="currentOptions"
        :option-label="optionLabel"
        :option-value="optionValue"
        :multiple="multiple"
        :loading="loading"
        :for="name"
        :use-chips="multiple"
        :rules="required ? [(val) => val !== null || 'Requerido'] : null"
        :placeholder="model === null && setDefault ? 'Ninguno' : ''"
        emit-value
        map-options
        outlined
        dense
        options-dense
        :clearable="clearable"
        autogrow
        behavior="menu"
        hide-bottom-space
        @filter="filterFn"
        @popup-show="focusSearch"
        @update:model-value="(val) => emits('change', name, val)"
    >
        <template v-slot:before-options v-if="filterable">
            <div class="column q-pa-sm">
                <q-input
                    ref="searchRef"
                    v-model="searchQuery"
                    placeholder="Buscar..."
                    outlined
                    dense
                    autofocus
                    clearable
                    @update:model-value="onSearch"
                >
                    <template v-slot:append>
                        <q-icon name="search" />
                    </template>
                </q-input>
            </div>
        </template>
        <template v-slot:no-option>
            <div class="q-pa-sm bg-white">
                <q-input
                    v-model="searchQuery"
                    placeholder="Buscar..."
                    outlined
                    dense
                    autofocus
                    clearable
                    @update:model-value="onSearch"
                    v-if="filterable"
                >
                    <template v-slot:append>
                        <q-icon name="search" />
                    </template>
                </q-input>
                <div class="text-grey text-center q-pa-sm">
                    No se encontraron resultados
                </div>
            </div>
        </template>
        <template v-slot:option="{ itemProps, opt, selected }">
            <q-item v-bind="itemProps">
                <q-item-section avatar side v-if="checkedSelected">
                    <q-icon
                        :name="
                            selected
                                ? 'mdi-checkbox-marked'
                                : 'mdi-square-outline'
                        "
                        :color="selected ? 'primary' : 'grey'"
                        class="q-mr-sm"
                        v-if="multiple"
                    />
                    <q-icon
                        :name="
                            selected ? 'check_circle' : 'radio_button_unchecked'
                        "
                        :color="selected ? 'primary' : 'grey'"
                        class="q-mr-sm"
                        v-else
                    />
                </q-item-section>
                <q-item-section>
                    <q-item-label>{{ opt[optionLabel] }}</q-item-label>
                </q-item-section>
                <q-item-section
                    avatar
                    v-if="opt['count'] !== null && opt['count'] !== undefined"
                >
                    <q-badge outline align="middle" color="primary">
                        {{ opt.count }}
                    </q-badge>
                </q-item-section>
            </q-item>
        </template>
        <template v-slot:hint v-if="required">
            <q-item-label lines="1"> Requerido </q-item-label>
        </template>
    </q-select>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "SelectFormComponent",
});

const props = defineProps({
    name: String,
    optionLabel: {
        type: String,
        default: "label",
    },
    optionValue: {
        type: String,
        default: "value",
    },
    modelValue: String | Number | Boolean | Array,
    multiple: {
        type: Boolean,
        default: false,
    },
    filterable: {
        type: Boolean,
        default: true,
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    required: {
        type: Boolean,
        default: false,
    },
    params: {
        type: Object,
        default: {},
    },
    options: {
        type: Array,
        default: [],
    },
    checkedSelected: {
        type: Boolean,
        default: true,
    },
    setDefault: Boolean,
    loading: Boolean,
});

const emits = defineEmits(["change"]);

const model = ref(null);
const currentOptions = ref([]);
let allOptions = [];

const searchQuery = ref("");
const searchRef = ref(null);

onMounted(async () => {
    allOptions = props.options;
    currentOptions.value = props.options;
    model.value = props.modelValue ?? null;
});

watch(
    () => props.modelValue,
    (n) => {
        model.value = n;
    }
);

watch(
    () => props.options,
    (n) => {
        allOptions = n;
        currentOptions.value = n;
    },
    { deep: true }
);

const filterFn = async (val, update, abort) => {
    update();
};

const onSearch = (val) => {
    if (val) {
        const needle = val.toString().toLowerCase() ?? val;
        currentOptions.value = allOptions.filter((v) =>
            v[props.optionLabel]
                ? v[props.optionLabel]
                      .toString()
                      .toLowerCase()
                      .indexOf(needle) > -1
                : v.toString().toLowerCase().indexOf(needle) > -1
        );
    } else {
        currentOptions.value = allOptions;
    }
};

const focusSearch = () => {
    setTimeout(() => {
        if (searchRef.value) searchRef.value.focus();
    }, 0);
};
</script>
