<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors?.has(property.field) && 'has-danger'}`"
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
                v-if="errors?.has(property.field)"
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
    name: "SelectComponentClient",
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
        errors: Object,
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
            if (props.modelValue && currentOptions.value.length > 0) {
                let opt = currentOptions.value.find(
                    (o) => o.value.toString() === props.modelValue.toString()
                );
                model.value = opt ? opt : null;
            } else {
                model.value = null;
            }
        };

        const setDataFromServer = async () => {
            loading.value = true;
            try {
                const response = await axios.post("/get-options-client", {
                    ...attributes.value,
                    pageSize: pageSize.value,
                    page: 1, // Siempre cargar primera página al filtrar
                    currentSelected: props.modelValue || null,
                });

                // Limpiar opciones antes de cargar nuevas
                currentOptions.value = response.data.options;
                allOptions.value = response.data.options;

                lastPage.value = Math.ceil(
                    response.data.totalCount / pageSize.value
                );

                // Resetear paginación
                nextPage.value = 2;

                // Actualizar modelo si hay valor
                if (props.modelValue) {
                    const selected =
                        response.data.options.find(
                            (o) =>
                                o.value.toString() ===
                                props.modelValue.toString()
                        ) || response.data.currentSelected;
                    model.value = selected;
                }
            } catch (error) {
                currentOptions.value = [];
                allOptions.value = [];
            } finally {
                loading.value = false;
            }
        };

        const filterFn = (val, update) => {
            update(async () => {
                if (val === "" || val === null || val.length > 0) {
                    attributes.value.filter = val === "" ? null : val;
                    await setDataFromServer();
                }
            });
        };

        const updateModel = (val) => {
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        };

        const onScroll = async ({ to, ref }) => {
            const lastIndex = currentOptions.value.length - 1;
            if (
                loading.value ||
                nextPage.value >= lastPage.value ||
                to !== lastIndex
            ) {
                return;
            }

            loading.value = true;
            try {
                const response = await axios.post("/get-options-client", {
                    ...attributes.value,
                    pageSize: pageSize.value,
                    page: nextPage.value,
                });

                currentOptions.value.push(...response.data.options);
                allOptions.value.push(...response.data.options);
                nextPage.value++;
            } finally {
                loading.value = false;
                nextTick(() => ref.refresh());
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
