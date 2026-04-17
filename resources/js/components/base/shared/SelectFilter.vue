<template>
    <label
        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center ms-auto`"
    >
        {{ label }}
    </label>
    <div class="">
        <div class="gap-2" style="width: 200px">
            <select
                :class="{ 'form-control': true }"
                v-model="val"
                style="width: 100%"
            >
                <option
                    v-for="option in opts"
                    :value="option.value"
                    :text="option.text"
                    :key="option.value"
                ></option>
            </select>
        </div>
    </div>
    <button type="button" class="btn" @click="refreshFilter">
        <i data-feather="rotate-cw"></i>
    </button>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { getOptions, selectTransform } from "../../../helpers/Transform";

export default {
    name: "SelectFilter",
    props: {
        options: String,
    },
    components: {},
    emits: ["setFilter"],
    setup(props, { emit }) {
        const val = ref([]);
        const options = ref([]);
        const opts = reactive(options);

        const idSelect = ref();
        const label = ref();
        const field = ref();

        onMounted(async () => {
            let opt = JSON.parse(props.options);
            if (opt.hasOwnProperty("search")) {
                options.value = await getOptions(opt.search);
                label.value = opt.search.label;
                field.value = opt.search.field;
            }
            if (opt.hasOwnProperty("options")) {
                options.value = selectTransform(opt.options.options);
                label.value = opt.options.label;
                field.value = opt.options.field;
            }
        });

        watch(val, () => {
            emit("setFilter", { [field.value]: val.value });
        });
        const refreshFilter = () => {
            val.value = "null";
        };

        return {
            val,
            options,
            opts,
            refreshFilter,
            idSelect,
            label,
        };
    },
};
</script>

<style scoped></style>
