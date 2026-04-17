<template>
    <div
        class="modal fade"
        :id="id"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <div class="modal-body m-0 row">
                    <template v-for="val in fieldsJsonFilters">
                        <ComponentFormDefault
                            v-if="val.include"
                            :id="val.field"
                            :json="val"
                            :errors="dataFilters.filters.errors"
                            :key="val"
                            v-model="dataFilters.filters[val.field]"
                            @update-field="updateThisField"
                            @clear-error="clearError"
                            modelValue=""
                        />
                    </template>
                </div>
                <div class="modal-footer">
                    <a
                        class="btn btn-secondary mr-3"
                        href="javascript:void(0)"
                        @click="resetFilters"
                    >
                        Eliminar Filtros
                    </a>

                    <button
                        class="btn btn-primary"
                        type="submit"
                        :disabled="dataFilters.filters.errors.any()"
                        @click="setFilters"
                    >
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { getOptions } from "../../../helpers/Transform";
import ComponentFormDefault from "../../../components/ComponentFormDefault";
import Form from "../../../helpers/Form";
import { filters } from "../../../helpers/filters";
import { isArray } from "lodash";
export default {
    name: "FilterDataTable",
    props: {
        title: String,
        filters: Object,
        id: String,
    },
    components: { ComponentFormDefault },
    emits: ["setFilter", "resetFilters"],
    setup(props, { emit }) {
        const fieldsJsonFilters = ref(props.filters);
        const dataFilters = reactive({
            filters: new Form({}),
        });
        onMounted(() => {});

        const updateThisField = ({ field, value }) => {
            dataFilters.filters[field] = value;
        };

        const clearError = ({ field }) => {
            dataFilters.filters.errors.clear(field);
        };

        const setFilters = () => {
            // Verifica que dataFilters.filters es un objeto
            if (
                typeof dataFilters.filters === "object" &&
                dataFilters.filters !== null
            ) {
                // Recorre cada clave en fieldsJsonFilters.value
                Object.keys(fieldsJsonFilters.value).forEach((key) => {
                    const field = fieldsJsonFilters.value[key].field;

                    // Verifica si el campo existe en dataFilters.filters
                    if (
                        field in dataFilters.filters &&
                        dataFilters.filters[field] != null
                    ) {
                        if (!isArray(dataFilters.filters[field])) {
                            filters.value = {
                                ...filters.value,
                                [field]: [dataFilters.filters[field]],
                            };
                        } else {
                            filters.value = {
                                ...filters.value,
                                [field]: dataFilters.filters[field],
                            };
                        }
                        // Actualiza filters.value con el nuevo campo
                    }
                });
            } else {
                console.error("dataFilters.filters no es un objeto válido");
            }
        };

        const resetFilters = () => {
            filters.value = {};
            dataFilters.filters = {};
            emit("resetFilters");
            $(`#${props.id}`).modal("hide");
        };

        return {
            fieldsJsonFilters,
            dataFilters,
            updateThisField,
            clearError,
            setFilters,
            resetFilters,
        };
    },
};
</script>

<style scoped></style>
