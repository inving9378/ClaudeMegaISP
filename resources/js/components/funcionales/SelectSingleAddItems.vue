<template>
    <div class="card">
        <div class="row card-body">
            <div class="p-2 m-2 col-sm-12 d-flex flex-column h-fix-content">
                <div class="row">
                    <label
                        :for="property.field"
                        class="col-4 col-form-label text-left"
                        >{{ property.label }}</label
                    >
                    <div class="col-8">
                        <select
                            class="form-control"
                            :id="property.field"
                            v-model="val"
                        >
                            <option
                                value="null"
                                v-text="property.placeholder"
                            ></option>
                            <option
                                v-for="option in options.val"
                                :value="option.value"
                                :text="option.text"
                            ></option>
                        </select>
                    </div>
                </div>
                <div class="mt-4" :v-if="items.lenght > 0">
                    <div  v-for="(item, index) in items"
                          class="alert alert-info alert-top-border alert-dismissible fade show mb-0 mt-1" role="alert">
                        <i class="mdi mdi-alert-circle-outline text-info align-middle me-3"></i><strong>Plan</strong> - {{ item.text }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" @click="removeItem(item)"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { getOptions, selectTransform } from "../../helpers/Transform";

export default {
    name: "SelectSingleAddItems",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: {
            type: Array,
            default: [],
        },
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const items = ref([]);
        const options = reactive({
            val: [],
        });

        onMounted(async () => {
            options.val = props.property.options
                ? await selectTransform(props.property.options)
                : await getOptions(props.property.search);

            _.forEach(val.value, (value) => {
                _.forEach(value, (v, k) => {
                    let filt = _.filter(options.val, (opt) => {
                        return opt.value == k;
                    })[0];
                    for (let i = 0; i < v.cant; i++) {
                        items.value.push(filt);
                    }
                });
            });

            emit("update-field", { value: items, field: props.property.field });
            val.value = null;
        });

        watch(val, () => {
            if (!_.isEmpty(val.value)) {
                let filt = _.filter(options.val, (v) => {
                    return v.value == val.value;
                })[0];

                items.value.push(filt);

                emit("update-field", {
                    value: items,
                    field: props.property.field,
                });
                val.value = null;
            }
        });

        const removeItem = (item) => {
            items.value = _.filter(items.value, function(all) { return all.value != item.value });
        }

        return {
            val,
            options,
            items,
            removeItem
        };
    },
};
</script>

<style scoped></style>
