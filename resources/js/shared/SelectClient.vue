<template>
    <div>
        <label for="client"> Cliente </label>{{ modelValue }}
        <q-select
            v-model="model"
            outlined
            dense
            options-dense
            clearable
            :name="name"
            :multiple="multiple"
            :options="currentOptions"
            :use-input="true"
            :use-chips="multiple"
            :loading="loading"
            :option-label="optionLabel"
            :option-value="optionValue"
            :rules="required ? [(val) => !!val || 'Requerido'] : null"
            input-debounce="0"
            emit-value
            map-options
            class="full-width"
            style="padding-right: 0px"
            @filter="filterFn"
            @update:model-value="updateModel"
        >
            <template v-slot:selected-item="scope">
                <q-item-label lines="1" style="margin-top: 5px">{{
                    scope.opt[optionLabel]
                }}</q-item-label>
            </template>
        </q-select>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick, onBeforeMount } from "vue";

const props = defineProps({
    name: String,
    modelValue: Number | String,
    multiple: {
        type: Boolean,
        default: false,
    },
    chips: {
        type: Boolean,
        default: true,
    },
    required: {
        type: Boolean,
        default: false,
    },
    options: {
        type: Array,
        default: [],
    },
    loadFromServer: Boolean,
    optionLabel: {
        type: String,
        default: "name",
    },
    optionValue: {
        type: String,
        default: "id",
    },
});

const emits = defineEmits(["change"]);

const model = ref(null);
const currentOptions = ref([]);
let allOptions = [];
const loading = ref(false);

onBeforeMount(() => {
    model.value = props.modelValue;
});

onMounted(async () => {
    setData();
});

watch(
    () => props.options,
    (n) => {
        setData();
        allOptions.value = n;
    }
);

const setData = () => {
    allOptions = props.options;
    currentOptions.value = allOptions;
    if (props.modelValue) {
        model.value =
            allOptions.find((o) => o[props.optionValue] === props.modelValue) ??
            null;
    }
};

const filterFn = (val, update, abort) => {
    update(
        () => {
            if (val === "") {
                currentOptions.value = allOptions;
            } else {
                const needle = val.toLowerCase();
                currentOptions.value = allOptions.value.filter((v) =>
                    v[props.optionLabel]
                        ? v[props.optionLabel].toLowerCase().indexOf(needle) >
                          -1
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
};

const updateModel = (val) => {
    let data = null;
    if (val) {
        data = allOptions.find((o) => o[props.optionValue] === val);
    }
    emits("change", data);
};
</script>
