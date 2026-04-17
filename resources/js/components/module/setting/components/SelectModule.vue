<template>
    <label
        :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center ms-auto`"
    >
        seleccionar modulo
    </label>
    <div class="col-3">
        <div class="d-flex flex-wrap gap-2">
            <select class="form-control" v-model="val">
                <option value="null" text=""></option>
                <optgroup
                    v-for="(group, groupName) in sortedOpts"
                    :label="groupName"
                    :key="groupName"
                >
                    <option
                        v-for="(text, value) in group"
                        :value="value"
                        :text="text"
                        :key="value"
                    ></option>
                </optgroup>
            </select>
        </div>
    </div>
</template>
<script>
import { onMounted, ref, watch, reactive, computed } from "vue";
import { selectTransform } from "../../../../helpers/Transform";
import { filters } from "../../../../helpers/filters";

export default {
    name: "SelectModule",
    props: {
        modules: String,
        datatable: String | Object,
    },
    components: {},
    emits: ["module-id"],
    setup(props, { emit }) {
        const val = ref();
        const options = ref([]);
        const opts = reactive(options);
        const sortedOpts = computed(() => {
            const sortedGroups = Object.keys(options.value).sort();
            const sortedOpts = {};

            sortedGroups.forEach((groupName) => {
                sortedOpts[groupName] = options.value[groupName];
            });

            return sortedOpts;
        });

        onMounted(async () => {
            options.value = JSON.parse(props.modules);
        });

        watch(val, (newValue, oldValue) => {
            if (newValue != null) {
                let data = {
                    module_id: newValue,
                };
                filters.value = data;
                emit("module-id", newValue);
            }
        });

        watch(filters, (newValue, oldValue) => {
            if (newValue == null) {
                val.value = null;
            }
        });

        return {
            val,
            opts,
            sortedOpts
        };
    },
};
</script>

<style scoped></style>
