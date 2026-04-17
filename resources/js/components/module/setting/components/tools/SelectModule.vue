<template>
    <div
        class="col-12 row m-auto mb-2"
        :class="`${errors.has('module_id') && 'has-danger'}`"
        id="example-element"
        @click="clearError"
    >
        <label
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Modulo
        </label>
        <div class="col-sm-12 col-lg-8" @click="clearError">
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

            <div
                v-if="errors.has('module_id')"
                class="pristine-error text-help"
            >
                {{ errors.get("module_id") }}
            </div>
        </div>
    </div>
</template>
<script>
import { onMounted, ref, watch, reactive, computed } from "vue";

export default {
    name: "SelectModule",
    props: {
        modules: String,
        datatable: String | Object,
        errors: {
            type: Object,
            default: {},
        },
    },
    components: {},
    emits: ["module-id","update-field","clear-error"],
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
                emit("module-id", newValue);
            }
        });
        const clearError = () => {
            emit("clear-error", "module_id");
        };

        return {
            val,
            opts,
            sortedOpts,
            clearError,
        };
    },
};
</script>

<style scoped></style>
