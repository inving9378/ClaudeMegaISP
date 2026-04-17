<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`" style="padding-right: 0">
            <q-select
                v-model="model"
                ref="modelRef"
                outlined
                :dense="dense"
                :options-dense="dense"
                :name="property.name"
                :multiple="multiple"
                :clearable="clearable"
                :options="currentOptions"
                :use-input="filterable"
                :use-chips="multiple && chips"
                :loading="loading"
                input-debounce="0"
                hide-bottom-space
                emit-value
                map-options
                class="full-width"
                style="padding-right: 0px"
                @virtual-scroll="onScroll"
                @filter="filterFn"
                @update:model-value="updateModel"
            >
                <template #loading>
                    <span class="loader"></span>
                </template>
            </q-select>
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
            >
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script>
import { ref, watch, onMounted, nextTick } from "vue";
import axios from "axios";

export default {
    name: "SelectLongOptions",
    props: {
        modelValue: Number | String,
        dense: {
            type: Boolean,
            default: true,
        },
        clearable: {
            type: Boolean,
            default: true,
        },
        filterable: {
            type: Boolean,
            default: true,
        },
        hidde_bottom_space: {
            type: Boolean,
            default: true,
        },
        multiple: {
            type: Boolean,
            default: false,
        },
        chips: {
            type: Boolean,
            default: true,
        },
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        idModel: {
            type: String,
            default: null,
        },
    },
    setup(props, { emit }) {
        const model = ref(null);
        const modelRef = ref(null);
        const currentOptions = ref([]);
        const allOptions = ref([]);
        const loading = ref(false);
        const nextPage = ref(2);
        const pageSize = ref(50);
        const lastPage = ref(1);
        const attributes = ref(null);

        onMounted(async () => {
            attributes.value = props.property.search;
            await setDataFromServer();
        });

        watch(
            () => props.modelValue,
            (n, o) => {
                setModelValue();
            }
        );

        const setModelValue = () => {
            if (
                props.modelValue !== null &&
                props.modelValue !== undefined &&
                props.modelValue !== ""
            ) {
                if (currentOptions.value.length > 0) {
                    let opt = currentOptions.value.find(
                        (o) =>
                            o.value.toString() === props.modelValue.toString()
                    );
                    model.value = opt ? opt : null;
                }
            } else {
                model.value = null;
            }
        };

        const setDataFromServer = async () => {
            let currentSelectedValue = null;

            if (
                model.value !== null &&
                model.value !== undefined &&
                model.value !== ""
            ) {
                currentSelectedValue =
                    typeof model.value === "object"
                        ? model.value.value
                        : model.value;
            } else if (
                props.modelValue !== null &&
                props.modelValue !== undefined &&
                props.modelValue !== ""
            ) {
                currentSelectedValue = props.modelValue;
            }

            await axios
                .post("/get-long-options-select", {
                    ...attributes.value,
                    pageSize: pageSize.value,
                    page: nextPage.value - 1,
                    currentSelected: currentSelectedValue,
                })
                .then((response) => {
                    let data = response.data;
                    let options = data.options;
                    currentOptions.value.push(...options);
                    allOptions.value.push(...options);
                    let current = data.currentSelected;
                    if (current !== null) {
                        let opt = currentOptions.value.find(
                            (o) => o.value === current.value
                        );
                        if (!opt) {
                            currentOptions.value.push(current);
                            allOptions.value.push(current);
                        }
                    }
                    lastPage.value = Math.ceil(
                        response.data.totalCount / pageSize.value
                    );
                })
                .catch((error) => {
                    currentOptions.value = [];
                    allOptions.value = [];
                    lastPage.value = 1;
                });
            setModelValue();
        };

        const filterFn = (val, update, abort) => {
            update(() => {
                if (val === "" || val === null || val.length > 2) {
                    currentOptions.value = [];
                    allOptions.value = [];
                    nextPage.value = 2;
                    attributes.value.filter = val === "" ? null : val;
                    setDataFromServer();
                }
            });
        };

        const updateModel = (val) => {
            const finalValue = val === null ? "" : val;
            emit("update-field", {
                value: finalValue,
                field: props.property.field,
            });
        };

        const onScroll = async ({ to, ref }) => {
            const lastIndex = currentOptions.value.length - 1;
            if (
                loading.value !== true &&
                nextPage.value < lastPage.value &&
                to === lastIndex
            ) {
                loading.value = true;
                nextPage.value++;
                await setDataFromServer();
                nextTick(() => {
                    ref.refresh();
                    loading.value = false;
                });
            }
        };
        return {
            model,
            modelRef,
            currentOptions,
            allOptions,
            loading,
            nextPage,
            pageSize,
            lastPage,
            attributes,
            filterFn,
            updateModel,
            onScroll,
        };
    },
};
</script>
<style scope>
.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip {
    width: 0px;
    position: absolute;
    right: 40px;
}
.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip
    button {
    right: 30px;
}
.q-field__control.relative-position.row.no-wrap {
    padding-left: 5px !important;
}
.q-field__inner.relative-position.col.self-stretch {
    padding-right: 0;
}
.q-icon.notranslate.material-icons.q-select__dropdown-icon.rotate-180 {
    right: -25px;
}
.loader {
    width: 24px;
    height: 24px;
    border: 3px solid #263038;
    border-bottom-color: transparent;
    border-radius: 50%;
    display: inline-block;
    box-sizing: border-box;
    animation: rotation 1s linear infinite;
}

@keyframes rotation {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>
