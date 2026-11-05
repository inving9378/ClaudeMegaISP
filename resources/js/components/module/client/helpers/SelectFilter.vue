<template>
    <label
        :for="idSelect"
        :class="`col-form-label text-md-end ms-auto`"
    >
        {{ label }}
    </label>
    <div class="gap-2" style="width: 200px">
        <select
            :class="{ 'form-control': true }"
            :name="idSelect"
            :id="idSelect"
            v-model="val"
            style="width: 100%"
            multiple
        >
            <option
                v-for="option in opts"
                :value="option.value"
                :text="option.text"
                :key="option.value"
            ></option>
        </select>
    </div>
    <button type="button" class="btn" @click="refreshFilter">
        <i data-feather="rotate-cw"></i>
    </button>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import {
    selectTransform,
    convertToBoostrapSelect,
} from "../../../../helpers/Transform";

export default {
    name: "SelectFilter",
    props: {
        options: String,
        id: String,
        name: String,
        label: { type: String, default: "Estado" },
        // Valores ya elegidos al montar. No emite setFilter: el padre ya los tiene.
        initial: {
            type: Array,
            default: () => [],
        },
    },
    components: {},
    emits: ["setFilter"],
    setup(props, { emit }) {
        const val = ref([...props.initial]);
        const options = ref([]);
        const opts = reactive(options);

        const idSelect = ref(`${props.id}_select`);

        // El multiselect entrega un arreglo nuevo cada vez que se cierra, aunque no cambie nada.
        watch(val, (actual, anterior) => {
            if (_.isEqual(actual, anterior)) return;
            emit("setFilter", { [props.name]: val.value });
        });

        onMounted(async () => {
            options.value = selectTransform(JSON.parse(props.options));
            $(document).ready(function () {
                convertToBoostrapSelect(idSelect.value, val, options.value);
            });
        });
        const refreshFilter = () => {
            val.value = [];
        };

        return {
            val,
            options,
            opts,
            refreshFilter,
            idSelect,
        };
    },
};
</script>

<style scoped></style>
