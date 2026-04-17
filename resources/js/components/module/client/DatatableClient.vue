<template>
    <div class="q-pa-md">
        <q-card>
            <q-table
                v-table-resizable="visibleColumns"
                :id="idTable"
                :title="`${list}`"
                :rows="rows"
                :columns="columns"
                :visible-columns="visibleColumns"
                :filter="search"
                :dark="darkMode"
                no-data-label="No hay elementos para mostrar"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                v-model:pagination="pagination"
                binary-state-sort
                @request="onRequest"
                :loading="loading"
                v-model:selected="selectedRows"
                :selection="selectedTableMultiple ? 'multiple' : 'single'"
                :selected-rows-label="
                    (numberOfRows) =>
                        `${numberOfRows} ${
                            numberOfRows > 1
                                ? 'elementos seleccionados'
                                : 'elemento seleccionado'
                        }`
                "
                class="q-pa-sm"
            >
                <template v-slot:top="props">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <div class="text-h6">{{ list }}</div>
                        <div class="float-right">
                            <VueDatePicker
                                v-model="fechaCorte"
                                position="right"
                                locale="es"
                                :teleport="true"
                                placeholder="Fecha de Corte"
                                range
                                multi-calendars
                                style="width: 350px"
                                :dark="darkMode"
                            >
                            </VueDatePicker>
                        </div>

                        <select-filter
                            :options="array_all_status"
                            :id="idTable"
                            name="client_main_information.estado"
                            @setFilter="setFilter"
                        >
                        </select-filter>
                        <a
                            class="btn btn-success waves-effect waves-light"
                            v-if="add"
                            :href="`/${module}/crear`"
                        >
                            {{ add }}
                        </a>
                    </div>
                    <div class="d-flex">
                        <button
                            type="button"
                            class="btn btn-outline-info"
                            data-bs-toggle="modal"
                            data-bs-target="#modaleditcolumn"
                            style="margin-left: auto; margin-right: 10px"
                        >
                            ...
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-info"
                            @click="toogleBackgroundColor"
                            style="margin-right: 10px"
                        >
                            <i data-feather="droplet" class="icon-lg"></i>
                        </button>

                        <div v-if="lengthButtons">
                            <a
                                v-for="button in buttons"
                                :href="button.href"
                                :class="button.class"
                                :id="button.id"
                            >
                                <i :class="button.iclass"></i>
                            </a>
                        </div>
                        <div class="d-flex">
                            <q-btn
                                flat
                                round
                                dense
                                :icon="
                                    props.inFullscreen
                                        ? 'fullscreen_exit'
                                        : 'fullscreen'
                                "
                                @click="props.toggleFullscreen"
                                class="q-ml-md"
                            />
                            <q-input
                                dense
                                v-model="search"
                                placeholder="Buscar"
                                class="mb-0"
                                style="
                                    margin-left: 16px;
                                    border: 1px solid;
                                    margin-right: 10px;
                                "
                                :dark="darkMode"
                            >
                            </q-input>
                            <q-btn
                                color="primary"
                                label="Export"
                                no-caps
                                @click="
                                    exportTable(
                                        columns,
                                        rows,
                                        list,
                                        visibleColumns
                                    )
                                "
                            />
                        </div>
                    </div>
                </template>

                <template v-slot:header="props">
                    <q-tr :props="props">
                        <q-th
                            style="width: 20px !important"
                            v-if="selectedTable && selectedTableMultiple"
                        >
                            <q-checkbox
                                v-model="props.selected"
                                dense
                                :dark="darkMode"
                            />
                        </q-th>
                        <q-th
                            v-for="col in props.cols"
                            :key="col.name"
                            :props="props"
                            :style="{ width: col.name === 'id' ? '20px' : '' }"
                            class="max-w-200"
                        >
                            {{ col.label }}
                        </q-th>
                    </q-tr>
                </template>

                <template #body="props">
                    <q-tr
                        :props="props"
                        :class="{
                            Activo: checkClass(props['key'], 'Activo'),
                            Bloqueado: checkClass(props['key'], 'Bloqueado'),
                            Inactivo: checkClass(props['key'], 'Inactivo'),
                            Nuevo: checkClass(props['key'], 'Nuevo'),
                            Cancelado: checkClass(props['key'], 'Cancelado'),
                        }"
                    >
                        <q-td
                            style="width: 20px !important"
                            v-if="selectedTable"
                        >
                            <q-checkbox
                                v-model="props.selected"
                                dense
                                :dark="darkMode"
                            />
                        </q-td>
                        <template v-for="col in columns">
                            <q-td
                                :key="col.name"
                                v-if="visibleColumns.includes(col.name)"
                            >
                                <span v-html="props.row[col.name]"></span>
                            </q-td>
                        </template>
                    </q-tr>
                </template>

                <template v-slot:bottom-row>
                    <q-tr>
                        <q-td colspan="100%">
                            <div>
                                Nuevo - Cliente recien convertido sin servicios
                            </div>
                            <div>Activo - Clientes Activos</div>
                            <div>
                                Bloqueados - Clientes morosos que no han pagado
                                sus servicios
                            </div>
                            <div>
                                Inactivos - Clientes recurrentes, morosos, que
                                se les vencio el periodo de gracia
                            </div>
                            <div>
                                Cancelado - Cliente eliminado que hay esta en
                                base de datos
                            </div>
                        </q-td>
                    </q-tr>
                </template>

                <template v-slot:pagination="scope">
                    <span class="pagination-text">
                        {{ paginationText }}
                    </span>

                    <button
                        v-if="scope.pagesNumber > 2"
                        class="pagination-btn"
                        :disabled="scope.isFirstPage"
                        @click="scope.firstPage"
                    >
                        <i class="fas fa-angle-double-left"></i>
                    </button>

                    <button
                        class="pagination-btn"
                        :disabled="scope.isFirstPage"
                        @click="scope.prevPage"
                    >
                        <i class="fas fa-angle-left"></i>
                    </button>

                    <button
                        class="pagination-btn"
                        :disabled="scope.isLastPage"
                        @click="scope.nextPage"
                    >
                        <i class="fas fa-angle-right"></i>
                    </button>

                    <button
                        v-if="scope.pagesNumber > 2"
                        class="pagination-btn"
                        :disabled="scope.isLastPage"
                        @click="scope.lastPage"
                    >
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </template>
            </q-table>
        </q-card>
    </div>

    <ModalCentrado
        id="modaleditcolumn"
        :modalTitle="modalTitle"
        labelledby="labelledby"
        @submit="onSubmit"
        style="z-index: 99999"
    >
        <div class="row">
            <div class="col-sm-12">
                <p>
                    Para mostrar los campos de la tabla, seleccione la casilla
                    de verificación correspondiente.
                </p>
            </div>
            <div class="col-sm-12">
                <label for="search-column-datatable">Buscar</label>
                <div class="search-box">
                    <input
                        type="text"
                        class="form-control"
                        id="search-column-datatable"
                        v-model="searchColumns"
                    />
                </div>
            </div>
        </div>

        <form
            method="POST"
            @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)"
        >
            <template v-for="val in fieldsJson">
                <ComponentFormDefault
                    v-if="val.include"
                    :json="val"
                    :errors="dataForm.data.errors"
                    :key="val"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </template>
        </form>
    </ModalCentrado>

    <EditId :iDClient="iDClient" :module="module" @resetTable="resetTable">
    </EditId>
</template>

<script>
import { ref, watch, reactive, onMounted, computed } from "vue";
import { debounce } from "lodash";
import {
    requestColumnsDatatableByModule,
    requestAllColumnsDatatableByModule,
    requestColumnsDatatableByModuleExceptColumns,
} from "../../../helpers/Request";
import { deleteRowDatatable } from "../../../hook/datatableHook";
import ComponentFormDefault from "../../../components/ComponentFormDefault";
import Form from "../../../helpers/Form";

import ModalCentrado from "../../../shared/ModalCentrado.vue";
import SelectFilter from "./helpers/SelectFilter.vue";
import {
    exportTable,
    dataToFindExport,
} from "../../../helpers/ExportTablaExcel";
import { showLoading, hideLoading } from "../../../helpers/loading";
import EditId from "./helpers/EditId.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../hook/appConfig";

export default {
    name: "DatatableClient",
    props: {
        idTable: {
            type: String,
            default: "table",
        },
        buttons: {
            type: Object,
            default: {},
        },
        module: String,
        model: {
            type: String,
            default: "",
        },
        list: {
            type: String,
            default: "",
        },
        add: {
            type: String,
            default: "",
        },
        editButton: {
            type: Object,
            default: {},
        },
        cssCard: {
            type: Boolean,
            default: true,
        },
        buttonsInsideDatatable: {
            type: Array,
            default: [],
        },
        id: {
            type: String,
            default: null,
        },
        filters: {
            type: Object,
            default: {},
        },
        status: {
            type: String,
        },
        array_all_status: {
            type: String,
        },
        color_datatable: {
            type: String,
        },
        rowKey: {
            type: String,
            default: "id",
        },
        selectedTableMultiple: {
            type: Boolean,
            default: true,
        },
        selectedTable: {
            type: Boolean,
            default: true,
        },
        expandedTable: {
            type: Boolean,
            default: true,
        },
        all_columns_by_module: {
            type: String,
        },
        header_columns_by_module: {
            type: String,
        },
    },
    components: {
        VueDatePicker,
        ComponentFormDefault,
        ModalCentrado,
        SelectFilter,
        EditId,
    },
    emits: [
        "item-delete",
        "table",
        "clientbillingtable",
        "bundleservicetable",
        "internetservicetable",
        "vozservicetable",
        "cabletvtable",
        "bundle",
        "customservicetable",
    ],
    setup(props, { emit }) {
        const lengthButtons = _.values(props.buttons).length;
        const headers = ref(JSON.parse(props.header_columns_by_module));
        const allHeaders = ref(JSON.parse(props.all_columns_by_module));
        const data = ref(null);
        const modalTitle = ref("Mostrar Columnas / Ocultar Columnas");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const fechaCorte = ref("");
        const search = ref("");
        const loading = ref(false);

        const searchColumns = ref("");
        const ffilters = ref({});

        const columns = ref([]);
        const rows = ref([]);
        const visibleColumns = ref([]);
        const showHeaders = ref();
        const rowPerPageOptions = ref([
            5, 10, 15, 25, 50, 100, 500, 1000, 10000, 0,
        ]);
        const iDClient = ref(null);
        const isUpdatedColorDatatable = ref(false);

        const headerColumns = ref([]);
        const expandedRows = ref([]);
        const selectedRows = ref([]);
        const expandAfterColumn = ref(null);
        const menuChangeExpand = ref(null);
        const columnsToExpand = ref([]);

        onMounted(async () => {
            $(document).on(
                "click",
                `#${props.idTable} .fa-trash`,
                function (e) {
                    if (confirm("Esta seguro que desea eliminar")) {
                        let deleteItem = deleteRowDatatable(
                            props.module,
                            $(e.target).parent().attr("id-item"),
                            data.value
                        );
                        if (deleteItem) {
                            emit("item-delete");
                            getRowsByModule(
                                _.map(headers.value, (v) => v.name),
                                showHeaders.value,
                                props.filters
                            );
                        }
                    }
                }
            );

            $(document).on("click", `#${props.idTable} .edit_id`, function (e) {
                iDClient.value = $(e.target).parent().attr("id-item");
                $(`#modaleditIdClient`).modal("show");
            });

            let showInHedaer = headers.value.length;
            showHeaders.value = showInHedaer;
            visibleColumns.value = _.map(headers.value, (v) => v.name);
            let temp = _.mapKeys(allHeaders.value, (h) => h.name);
            temp = _.mapValues(temp, (h) => {
                return {
                    value: visibleColumns.value.includes(h.name),
                    field: h.name,
                    include: true,
                    label: h.label,
                    type: "input-checkbox-left-order",
                };
            });

            temp.module = {
                value: props.model,
                include: false,
            };
            tempToFilter.value = temp;
            fieldsJson.value = temp;
            dataForm.data = new Form(fieldsJson.value);

            if (headers.value) {
                await getRowsByModule(
                    _.map(headers.value, (v) => v.name),
                    showHeaders.value,
                    props.filters
                );
            }
        });
        const tempToFilter = ref({});

        const colorBlackAndWhite = ref(props.color_datatable == 1);
        const toogleBackgroundColor = () => {
            isUpdatedColorDatatable.value = true;
            colorBlackAndWhite.value = !colorBlackAndWhite.value;
            resetTable();
        };

        watch(searchColumns, () => {
            const searchTerm = searchColumns.value.toLowerCase();
            const filteredFields = Object.keys(fieldsJson.value)
                .filter((key) => {
                    const field = fieldsJson.value[key];
                    return (
                        key.toLowerCase().includes(searchTerm) ||
                        (field.label &&
                            field.label.toLowerCase().includes(searchTerm))
                    );
                })
                .reduce((filtered, key) => {
                    filtered[key] = fieldsJson.value[key];
                    return filtered;
                }, {});

            if (searchColumns.value == "") {
                fieldsJson.value = tempToFilter.value;
            } else {
                fieldsJson.value = filteredFields;
            }
        });

        const onSubmit = () => {
            dataForm.data
                .submit("post", "/update-column-by-user", "update")
                .then((response) => {
                    if (response.columns) {
                        let columns = response.columns;
                        const trueProperties = Object.entries(columns)
                            .filter(([key, value]) => value === true)
                            .map(([key]) => key);
                        visibleColumns.value = trueProperties;
                        location.reload();

                        $(`#modaleditcolumn`).modal("hide");
                    }
                });
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const hasActionColumn = (requestedColumns) => {
            return _.filter(requestedColumns, (v) => {
                return v.label == "Acciones";
            }).length;
        };

        const getActionColumn = (requestedColumns) => {
            return _.filter(requestedColumns, (v) => {
                return v.label == "Acciones";
            })[0];
        };

        const currentSortColumn = ref(null);

        const pagination = ref({
            descending: false,
            page: 1,
            rowsPerPage: 50,
            rowsNumber: 0,
        });

        const startPagination = ref(0);
        const paginationText = computed(() => {
            const startItem = startPagination.value + 1;
            const endItem = Math.min(
                pagination.value.page * pagination.value.rowsPerPage,
                pagination.value.rowsNumber
            );
            return `${startItem}-${endItem} of ${pagination.value.rowsNumber}`;
        });

        const getRowsByModule = async (
            colss,
            showInHeader,
            filters,
            order,
            dir,
            search
        ) => {
            let taked = _.take(colss, showInHeader);
            let columnS = _.map(colss, (e) => {
                return { data: e };
            });
            const url = `/${props.module}/table`;
            let allRows = null;
            let col = [];
            loading.value = true;
            try {
                const response = await axios.post(url, {
                    data: {
                        columns: columnS,
                        search: search,
                        filters: filters,
                        additionalFilter: ffilters.value,
                    },
                    order: order,
                    limits: pagination.value.rowsPerPage,
                    start: startPagination.value,
                    dir: dir,
                    color_active: colorBlackAndWhite.value,
                    is_update_color: isUpdatedColorDatatable.value,
                });

                dataToFindExport.value = {
                    data: {
                        columns: columnS,
                        search: search,
                        filters: filters,
                        additionalFilter: ffilters.value,
                    },
                    order: order,
                    limits: pagination.value.rowsPerPage,
                    start: startPagination.value,
                    dir: dir,
                    color_active: colorBlackAndWhite.value,
                    is_update_color: isUpdatedColorDatatable.value,
                    url: url,
                };
                allRows = response.data.data;
                colorBlackAndWhite.value = response.data.color_datatable;
                allHeaders.value.sort((a, b) => {
                    return parseInt(a.order) - parseInt(b.order);
                });
                allHeaders.value.forEach((header) => {
                    if (visibleColumns.value.includes(header.name)) {
                        let val = {
                            name: header.name,
                            label: header.label,
                            align: "left",
                            field: (row) => row[header.name],
                            format: (val) => `${val}`,
                            sortable: header.name != "action",
                        };
                        col.push(val);
                    }
                });

                pagination.value.rowsNumber = response.data.recordsFiltered;
            } catch (error) {
                console.error(error);
            }

            columns.value = Object.values(col);
            rows.value = Object.values(allRows);

            loading.value = false;
        };

        const pagesNumber = computed(() => {
            return Math.ceil(rows.length / pagination.value.rowsPerPage);
        });

        const onRequestDebounced = debounce((props) => {
            search.value = props.filter;
            currentSortColumn.value = props.pagination.sortBy;
            const { page, rowsPerPage, sortBy, descending } = props.pagination;
            pagination.value.page = page;
            pagination.value.rowsPerPage = rowsPerPage;
            pagination.value.sortBy = sortBy;
            pagination.value.descending = descending;

            startPagination.value = (page - 1) * rowsPerPage;

            resetTable();
        }, 1000);

        function onRequest(props) {
            if (props.filter != "") {
                onRequestDebounced(props);
            } else {
                currentSortColumn.value = props.pagination.sortBy;
                const { page, rowsPerPage, sortBy, descending } =
                    props.pagination;
                pagination.value.page = page;
                pagination.value.rowsPerPage = rowsPerPage;
                pagination.value.sortBy = sortBy;
                pagination.value.descending = descending;
                pagination.value.rowsPerPage = props.pagination.rowsPerPage;
                startPagination.value = (page - 1) * rowsPerPage;

                resetTable();
            }
        }

        const resetTable = () => {
            getRowsByModule(
                _.map(headers.value, (v) => v.name),
                showHeaders.value,
                props.filters,
                currentSortColumn.value,
                pagination.value.descending,
                search.value
            );
        };

        watch(fechaCorte, () => {
            ffilters.value = {
                ...ffilters.value,
                fecha_corte: fechaCorte.value,
            };
            resetTable();
        });

        const setFilter = (obj) => {
            ffilters.value = obj;
            resetTable();
        };

        watch(loading, () => {
            if (loading.value) {
                showLoading();
            } else {
                hideLoading();
            }
        });

        const checkClass = (html, classSelected) => {
            return $(html).hasClass(classSelected);
        };

        watch(expandAfterColumn, () => {
            setExpandFromColumn();
        });

        watch(visibleColumns, () => {
            setExpandFromColumn();
        });

        const getIndexFromColumn = (c) => {
            for (let index = 0; index < expandedRows.value.length; index++) {
                if (c === expandedRows.value[index].name) {
                    return index + 1;
                }
            }
            return -1;
        };

        const setExpandFromColumn = () => {
            let cols = columns.value;
            if (
                expandAfterColumn.value !== null &&
                visibleColumns.value.length >
                    getIndexFromColumn(expandAfterColumn.value)
            ) {
                expandedRows.value = cols.filter((c) =>
                    visibleColumns.value.includes(c.name)
                );
                headerColumns.value = expandedRows.value.splice(
                    0,
                    getIndexFromColumn(expandAfterColumn.value)
                );
                const actions = cols.find((c) => c.name === "action");
                if (actions !== undefined && actions !== null) {
                    headerColumns.value.push(actions);
                    expandedRows.value.splice(expandedRows.value.length - 1, 1);
                }
            } else {
                headerColumns.value = cols;
                expandedRows.value = [];
            }
            columnsToExpand.value = [];
            headerColumns.value.forEach((c) => {
                columnsToExpand.value.push(c);
            });
            expandedRows.value.forEach((c) => {
                columnsToExpand.value.push(c);
            });
        };

        const onChangeColumnExpand = async (c) => {
            showLoading();
            await axios
                .post("/set-column-expand-by-module", {
                    module: props.model,
                    column: c,
                })
                .then((response) => {
                    expandAfterColumn.value =
                        expandAfterColumn.value === c ? null : c;
                    menuChangeExpand.value.hide();
                    hideLoading();
                })
                .catch((err) => {
                    hideLoading();
                });
        };

        return {
            headers,
            allHeaders,
            lengthButtons,
            onSubmit,
            modalTitle,
            fieldsJson,
            dataForm,
            updateThisField,
            clearError,
            search,
            toogleBackgroundColor,
            columns,
            rows,
            visibleColumns,
            pagesNumber,
            pagination,
            rowPerPageOptions,
            onRequest,
            searchColumns,
            setFilter,
            loading,
            exportTable,
            paginationText,
            iDClient,
            resetTable,
            fechaCorte,
            checkClass,
            expandAfterColumn,
            headerColumns,
            expandedRows,
            columnsToExpand,
            onChangeColumnExpand,
            menuChangeExpand,
            selectedRows,
            darkMode,
        };
    },
};
</script>

<style scoped></style>
