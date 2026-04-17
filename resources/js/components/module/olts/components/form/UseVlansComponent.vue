<template>
    <div class="row q-my-sm">
        <label class="col-12 col-sm-3 text-right self-end"></label>
        <div class="col-12 col-sm-9 object-field">
            <q-checkbox
                v-model="useSvlan"
                label="Usar SVLAN-ID"
                @update:model-value="
                    (val) => {
                        model.svlan = null;
                        model.tag_transform_mode = val ? 'translate' : null;
                        emits('change', model);
                    }
                "
            />
        </div>
    </div>

    <template v-if="useSvlan">
        <div class="row q-my-sm">
            <label class="col-12 col-sm-3 text-right col-form-label" for="svlan"
                >SVLAN-ID</label
            >
            <div class="col-12 col-sm-9 object-field">
                <select-form-component
                    name="svlan"
                    :model-value="model.svlan"
                    :options="vlans"
                    :required="true"
                    :loading="loading"
                    option-value="vlan"
                    @change="onChange"
                />
            </div>
        </div>

        <div class="row q-my-sm">
            <label
                class="col-12 col-sm-3 text-right col-form-label"
                for="tag_transform_mode"
                >Modo transformación</label
            >
            <div class="col-12 col-sm-9 object-field">
                <select-form-component
                    name="tag_transform_mode"
                    :model-value="model.tag_transform_mode"
                    :options="tagTransformModes"
                    :required="true"
                    :filterable="false"
                    @change="onChange"
                />
            </div>
        </div>
    </template>

    <div class="row q-my-sm">
        <label class="col-12 col-sm-3 text-right col-form-label"></label>
        <div class="col-12 col-sm-9 object-field">
            <q-checkbox
                v-model="useCvlan"
                label="Usar CVLAN-ID"
                @update:model-value="
                    () => {
                        model.cvlan = null;
                        emits('change', model);
                    }
                "
            />
        </div>
    </div>
    <div class="row q-my-sm" v-if="useCvlan">
        <label class="col-12 col-sm-3 text-right col-form-label" for="cvlan"
            >CVLAN-ID</label
        >
        <div class="col-12 col-sm-9 object-field">
            <select-form-component
                name="cvlan"
                :model-value="model.cvlan"
                :options="vlans"
                :required="true"
                :loading="loading"
                option-value="vlan"
                @change="onChange"
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import SelectFormComponent from "./SelectFormComponent.vue";
defineOptions({
    name: "UseVlansComponent",
});

const props = defineProps({
    data: {
        type: Object,
        default: {
            svlan: null,
            cvlan: null,
            tag_transform_mode: null,
        },
    },
    loading: Boolean,
    vlans: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["change"]);

const model = ref({
    cvlan: null,
    svlan: null,
    tag_transform_mode: null,
});

const useSvlan = ref(false);
const useCvlan = ref(false);

const tagTransformModes = [
    {
        label: "default",
        value: "default",
    },
    {
        label: "translate",
        value: "translate",
    },
    {
        label: "translate-and-add",
        value: "translate-and-add",
    },
    {
        label: "transparent",
        value: "transparent",
    },
];

onMounted(() => {
    useSvlan.value = props.data.svlan ? true : false;
    useCvlan.value = props.data.cvlan ? true : false;
    setValues();
});

watch(
    () => props.data,
    () => {
        setValues();
    },
    { deep: true }
);

const setValues = () => {
    model.value = {
        svlan: props.data.svlan,
        cvlan: props.data.cvlan,
        tag_transform_mode: props.data.tag_transform_mode,
    };
};

const onChange = (name, val) => {
    model.value[name] = val;
    emits("change", model.value);
};
</script>
