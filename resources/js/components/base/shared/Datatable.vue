<template>
    <div class="d-flex flex-wrap gap-2 mb-2 justify-content-end multiple-filters-datatable"
        v-if="lengthMultipleFilters">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-3" data-bs-toggle="modal"
            :data-bs-target="`#multipleFilers_${idTable}`">
            Filtros
        </button>
    </div>
    <FilterDataTable :filters="multipleFilters" :id="`multipleFilers_${idTable}`" title="Filters" :key="filtersM"
        @resetFilters="resetTable">
    </FilterDataTable>
    <div class="datatable_base">
        <q-card>
            <q-table v-table-resizable="visibleColumns" :id="idTable" :rows="rows" :columns="headerColumns"
                :visible-columns="visibleColumns" :filter="search" :row-key="rowKey" :dark="darkMode"
                no-data-label="No hay elementos para mostrar" rows-per-page-label="Elementos por página"
                loading-label="Obteniendo datos" :selected-rows-label="(numberOfRows) =>
                    `${numberOfRows} ${numberOfRows > 1
                        ? 'elementos seleccionados'
                        : 'elemento seleccionado'
                    }`
                    " v-model:selected="selectedRows" v-model:pagination="pagination" binary-state-sort
                @request="onRequest" :rows-per-page-options="rowPerPageOptions" :loading="loading"
                :selection="selectedTableMultiple ? 'multiple' : 'single'" class="q-pa-sm">
                <template v-slot:top="props">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <div class="text-h6">{{ list }}</div>
                        <template v-for="{ option, indexOpt } in optionsFilters" :key="`opt-filter-${indexOpt}`">
                            <select-filter :options="option" @setFilter="setFilter">
                            </select-filter>
                        </template>
                        <a class="btn btn-success waves-effect waves-light ms-auto" v-if="add"
                            :href="`/${module}/crear`">
                            {{ add }}
                        </a>
                    </div>
                    <div class="row">
                        <div class="col d-flex justify-content-end">
                            <template v-if="lengthButtons">
                                <q-btn v-for="(button, key) in buttons" :key="key" no-caps :class="button.class || 'btn btn-outline-info'
                                    " :id="button.id" :icon="button.iclass" :label="button.text"
                                    style="margin-right: 15px" :tag="isValidHref(button.href)
                                        ? 'a'
                                        : 'button'
                                        " :href="isValidHref(button.href)
                                            ? button.href
                                            : undefined
                                            " :target="isValidHref(button.href)
                                            ? button.target || '_self'
                                            : undefined
                                            " :data-bs-target="!isValidHref(button.href)
                                            ? button.dataBsTarget
                                            : undefined
                                            " :data-bs-toggle="!isValidHref(button.href)
                                            ? button.dataBsToogle
                                            : undefined
                                            " />
                            </template>

                            <!--  <q-btn
                                class="btn btn-outline-info"
                                icon="mdi-arrow-expand-vertical"
                                no-caps
                                style="margin-right: 15px"
                                v-if="headerColumns.length > 0"
                            >
                                <q-menu
                                    transition-show="jump-down"
                                    transition-hide="jump-up"
                                    class="q-pa-sm"
                                    ref="menuChangeExpand"
                                >
                                    Expandir tabla a partir de
                                    <q-scroll-area
                                        :visible="visible"
                                        style="height: 200px; width: 300px"
                                    >
                                        <q-list dense>
                                            <q-item
                                                clickable
                                                v-for="(
                                                    c, index
                                                ) in columnsToExpand"
                                                :key="`column-expand-${index}`"
                                                @click="
                                                    onChangeColumnExpand(c.name)
                                                "
                                                :class="
                                                    c.name === 'id' ||
                                                    c.name === 'action'
                                                        ? 'hidden'
                                                        : ''
                                                "
                                                v-close-popup
                                            >
                                                <q-item-section
                                                    side
                                                    style="
                                                        width: 30px !important;
                                                    "
                                                >
                                                    <q-icon
                                                        name="mdi-check"
                                                        size="sm"
                                                        v-if="
                                                            expandAfterColumn ===
                                                            c.name
                                                        "
                                                    />
                                                </q-item-section>
                                                <q-item-section>
                                                    <q-item-label lines="1">{{
                                                        c.label
                                                    }}</q-item-label>
                                                </q-item-section>
                                            </q-item>
                                        </q-list>
                                    </q-scroll-area>
                                </q-menu>
                            </q-btn> -->
                            <q-btn icon="mdi-dots-horizontal" data-bs-toggle="modal"
                                :data-bs-target="`#modaleditcolumn_${idTable}`" class="btn btn-outline-info"
                                style="margin-right: 15px" />
                            <q-btn icon="mdi-history" class="btn btn-outline-info" style="margin-right: 25px"
                                @click="reloadDataTable" />
                            <q-input dense v-model="search" placeholder="Buscar..." outlined :dark="darkMode">
                            </q-input>
                            <q-btn flat round dense :icon="props.inFullscreen
                                ? 'fullscreen_exit'
                                : 'fullscreen'
                                " @click="props.toggleFullscreen" />
                            <q-btn color="primary" label="Exportar" no-caps @click="
                                exportTable(
                                    columns,
                                    rows,
                                    list,
                                    visibleColumns
                                )
                                " />
                        </div>
                    </div>
                </template>
                <template v-slot:header="props">
                    <q-tr :props="props">
                        <q-th style="width: 20px !important" v-if="
                            selectedTable &&
                            selectedTableMultiple &&
                            headerColumns.length > 0
                        ">
                            <q-checkbox v-model="props.selected" dense :dark="darkMode" />
                        </q-th>
                        <q-th style="width: 20px !important" v-if="expandedTable && expandedRows.length > 0" />
                        <q-th v-for="col in props.cols" :key="col.name" :props="props"
                            :style="{ width: col.name === 'id' ? '20px' : '' }" class="max-w-200">
                            {{ col.label }}
                        </q-th>
                    </q-tr>
                </template>
                <template v-slot:body="props">
                    <q-tr :props="props">
                        <q-td style="width: 20px !important" v-if="selectedTable && headerColumns.length > 0">
                            <q-checkbox v-model="props.selected" dense :dark="darkMode" />
                        </q-td>
                        <q-td style="width: 20px !important" v-if="expandedTable && expandedRows.length > 0">
                            <q-btn size="sm" color="primary" round dense @click="props.expand = !props.expand"
                                :icon="props.expand ? 'remove' : 'add'" />
                        </q-td>
                        <q-td v-for="col in props.cols" :key="col.name" :props="props"
                            :class="getTdClass(props.row, col)" :style="{
                                width: col.name === 'id' ? '20px' : '',
                                'max-width': '250px',
                            }">
                            <q-item-label lines="1"><span v-html="props.row[col.name]"></span></q-item-label>
                        </q-td>
                    </q-tr>
                    <q-tr v-show="props.expand" :props="props" v-if="expandedTable && expandedRows.length > 0">
                        <q-td colspan="100%">
                            <q-list dense>
                                <q-item v-for="(exp, indexExp) in expandedRows" :key="`expanded-${indexExp}`">
                                    <q-item-section avatar>
                                        <b>{{ exp.label }}: </b>
                                    </q-item-section>
                                    <q-item-section>
                                        <span v-html="props.row[exp.name]"></span>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </q-td>
                    </q-tr>
                </template>

                <template v-slot:pagination="scope">
                    <span class="pagination-text">
                        {{ paginationText }}
                    </span>

                    <button v-if="scope.pagesNumber > 2" class="pagination-btn" :disabled="scope.isFirstPage"
                        @click="scope.firstPage">
                        <i class="fas fa-angle-double-left"></i>
                    </button>

                    <button class="pagination-btn" :disabled="scope.isFirstPage" @click="scope.prevPage">
                        <i class="fas fa-angle-left"></i>
                    </button>
                    <button class="pagination-btn" :disabled="scope.isLastPage" @click="scope.nextPage">
                        <i class="fas fa-angle-right"></i>
                    </button>

                    <button v-if="scope.pagesNumber > 2" class="pagination-btn" :disabled="scope.isLastPage"
                        @click="scope.lastPage">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </template>

                <template v-slot:bottom-row="scope">
                    <slot name="bottom-row" v-bind="scope"></slot>
                </template>
            </q-table>
        </q-card>
    </div>

    <ModalCentrado :id="`modaleditcolumn_${idTable}`" :modalTitle="modalTitle" labelledby="labelledby"
        @submit="onSubmit" style="z-index: 99999">
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
                    <input type="text" class="form-control" id="search-column-datatable" v-model="searchColumns" />
                </div>
            </div>
        </div>

        <form method="POST" @submit.prevent="onSubmit" @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)">
            <template v-for="val in fieldsJson" :key="`visible-${val.field}`">
                <ComponentFormDefault v-if="
                    val.include &&
                    !excludeDefaultColumns.includes(val.field)
                " :json="val" :errors="dataForm.data.errors" :key="val" v-model="dataForm.data[val.field]"
                    @update-field="updateThisField" @clear-error="clearError" />
            </template>
        </form>
    </ModalCentrado>
</template>

<script setup>
import { ref, watch, reactive, onMounted, computed } from "vue";
import { debounce } from "lodash";
import { initHelperFormField } from "../../../helpers/module/setting_additional_field/helper";
import {
    requestColumnsDatatableByModule,
    requestColumnExpandDtByModule,
    requestAllColumnsDatatableByModule,
} from "../../../helpers/Request";
import { deleteRowDatatable } from "../../../hook/datatableHook";
import ComponentFormDefault from "../../../components/ComponentFormDefault";
import Form from "../../../helpers/Form";
import ModalCentrado from "../../../shared/ModalCentrado.vue";
import { filters, resetDatatable, comunRows } from "../../../helpers/filters";
import SelectFilter from "./SelectFilter.vue";
import {
    exportTable,
    dataToFindExport,
} from "../../../helpers/ExportTablaExcel";
import { showLoading, hideLoading } from "../../../helpers/loading";
import FilterDataTable from "./FilterDataTable.vue";
import Swal from "sweetalert2";
import { darkMode } from "../../../hook/appConfig";

defineOptions({
    name: "Datatable",
});

const props = defineProps({
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
    persistentFilters: {
        type: Object,
        default: {},
    },
    status: {
        type: String,
    },
    select_filter: {
        type: String,
    },
    multipleFilters: {
        type: Object,
        default: {},
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
    emitsRows: {
        type: Boolean,
        default: false,
    },
    excludeDefaultColumns: {
        type: Array,
        default: [],
    },
    order: {
        type: String,
        default: "NULL",
    },
    dir: {
        type: String,
        default: "desc",
    },
});

const emits = defineEmits([
    "item-delete",
    "table",
    "clientbillingtable",
    "bundleservicetable",
    "internetservicetable",
    "vozservicetable",
    "cabletvtable",
    "bundle",
    "customservicetable",
    "emitsRows",
]);
const lengthButtons = _.values(props.buttons).length;
const lengthMultipleFilters = _.values(props.multipleFilters).length;
const headers = ref(null);
const allHeaders = ref({});
const data = ref(null);
const modalTitle = ref("Mostrar Columnas / Ocultar Columnas");
const fieldsJson = ref({});
const dataForm = reactive({
    data: new Form({}),
});
const search = ref("");
const searchColumns = ref("");
const loading = ref(false);

const tempToFilter = ref({});
const requestedColumnsF = ref();

const showInHeaderF = ref();
const columns = ref([]);
const rows = ref([]);
const visibleColumns = ref([]);
const showHeaders = ref();

const selectFilters = ref();
const ffilters = ref();

const filtersM = ref(true);

const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 500, 1000, 10000, 0]);

const headerColumns = ref([]);
const expandedRows = ref([]);
const selectedRows = ref([]);
const expandAfterColumn = ref(null);
const menuChangeExpand = ref(null);
const columnsToExpand = ref([]);

const isInitial = ref(true);

watch(resetDatatable, () => {
    if (resetDatatable.value) {
        resetDatatable.value = false;
        resetFieldJson();
        search.value = "";
        getRowsByModule(
            _.map(headers.value, (v) => v.name),
            showHeaders.value,
            filters.value
        );
    }
});

watch(searchColumns, () => {
    const searchTerm = searchColumns.value.toLowerCase();
    const filteredFields = Object.keys(fieldsJson.value)
        .filter((key) => {
            const field = fieldsJson.value[key];
            return (
                key.toLowerCase().includes(searchTerm) ||
                (field.label && field.label.toLowerCase().includes(searchTerm))
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

watch(filters, () => {
    const isEmpty = Object.values(filters.value).every((value) => {
        if (Array.isArray(value)) return value.length === 0;
        if (typeof value === "string") return value.trim() === "";
        return value === null || value === undefined || value === "";
    });

    if (!isEmpty || isInitial.value == false) {
        getRowsByModule(
            _.map(headers.value, (v) => v.name),
            showHeaders.value,
            filters.value
        );
        getTemp();
    }
});

const resetFieldJson = () => {
    fieldsJson.value = tempToFilter.value;
};

onMounted(async () => {
    $(document).on("click", `#${props.idTable} .fa-trash`, function (e) {
        Swal.fire({
            title: "Esta seguro que desea eliminar?",
            text: "No podrás deshacer esta acción.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, continuar",
            cancelButtonText: "Cancelar",
        }).then(async (result) => {
            if (result.isConfirmed) {
                showLoading("showTextDef");
                await axios
                    .post(
                        `/${props.module}/destroy/${$(e.target)
                            .parent()
                            .attr("id-item")}`,
                        {
                            module: props.module,
                        }
                    )
                    .then((response) => {
                        hideLoading();
                        emits("item-delete");
                        getRowsByModule(
                            _.map(headers.value, (v) => v.name),
                            showHeaders.value,
                            filters.value
                        );
                        getTemp();

                        Swal.fire(
                            "Éxito",
                            "Elemento eliminado correctamente",
                            "success"
                        );
                    })
                    .catch((error) => {
                        hideLoading();

                        const backendMessage =
                            error?.response?.data?.message ??
                            error?.response?.data?.error ??
                            "";

                        // Detectar error SQLSTATE 23000 (clave foránea)
                        const isForeignKeyError =
                            typeof backendMessage === "string" &&
                            backendMessage.includes("SQLSTATE[23000]");

                        if (isForeignKeyError) {
                            Swal.fire(
                                "Error",
                                "No se puede eliminar este elemento porque tiene registros asociados. Elimine primero los elementos relacionados.",
                                "error"
                            );
                        } else {
                            Swal.fire(
                                "Error",
                                backendMessage ||
                                "Ocurrió un error inesperado.",
                                "error"
                            );
                        }
                    });
                hideLoading();
            }
        });
    });
    if (props.select_filter) {
        selectFilters.value = JSON.parse(props.select_filter);
    }

    if (props.order !== 'NULL'){
        currentSortColumn.value = props.order;
    }

    if (props.dir){
        currentDirColumn.value = props.dir;
    }

    let requestedColumns = await requestColumnsDatatableByModule(props.model);

    let showInHedaer = requestedColumns.length;
    showHeaders.value = showInHedaer;
    let headerColumns = requestedColumns;

    if (hasActionColumn(requestedColumns)) {
        headerColumns.push(getActionColumn(requestedColumns));
    }

    headers.value = headerColumns;
    allHeaders.value = await requestAllColumnsDatatableByModule(props.model);

    requestedColumnsF.value = requestedColumns;
    showInHeaderF.value = showInHedaer;

    if (headers.value) {
        await getRowsByModule(
            _.map(headers.value, (v) => v.name),
            showInHedaer,
            props.filters
        );
    }
    let temp = getTemp();
    tempToFilter.value = temp;

    if (isModuleFieldModule(props.model)) {
        initHelperFormField(props, rows);
    }
});

const isModuleFieldModule = (model) => {
    return model == "FieldModule";
};

const getTemp = () => {
    let temp = _.mapKeys(allHeaders.value, (h) => h.name);
    let activeColumns = _.map(headers.value, (v) => v.name);
    temp = _.mapValues(temp, (h) => ({
        value: activeColumns.includes(h.name),
        field: h.name + "/datatable" + props.idTable,
        include: true,
        label: h.label,
        type: "input-checkbox-left-order",
    }));

    temp = Object.keys(temp).reduce((acc, key) => {
        acc[key + "/datatable" + props.idTable] = temp[key];
        return acc;
    }, {});

    temp.module = {
        value: props.model,
        include: false,
    };

    fieldsJson.value = temp;
    dataForm.data = new Form(fieldsJson.value);
    return temp;
};

const setVisibleColumns = (columns) => {
    let temp = [];
    columns.forEach((c) => {
        if (
            !props.excludeDefaultColumns.includes(
                `${c}/datatable${props.idTable}`
            )
        ) {
            temp.push(c);
        }
    });
    visibleColumns.value = temp;
};

const onSubmit = () => {
    dataForm.data
        .submit("post", "/update-column-by-user", "update")
        .then((response) => {
            if (response.columns) {
                // 1. Filtrar las columnas activas (value === true)
                const activeColumns = Object.entries(response.columns)
                    .filter(([key, value]) => value === true)
                    .map(([key]) =>
                        key.replace(`/datatable${props.idTable}`, "")
                    );

                setVisibleColumns(activeColumns);

                const updatedHeaders = allHeaders.value.filter((header) =>
                    activeColumns.includes(header.name)
                );

                const actionColumn = allHeaders.value.find(
                    (header) => header.name === "action"
                );
                if (
                    actionColumn &&
                    !updatedHeaders.some((h) => h.name === "action")
                ) {
                    updatedHeaders.push(actionColumn);
                }

                headers.value = updatedHeaders;
                $(`#modaleditcolumn_${props.idTable}`).modal("hide");
            }
        })
        .catch((error) => {
            console.error("Error al actualizar columnas:", error);
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

const currentDirColumn = ref('desc');

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 1,
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

const reloadDataTable = () => {
    resetDatatable.value = true;
};

let controller = null

const getRowsByModule = async (
    colss,
    showInHeader,
    filters,
    order,
    dir,
    search
) => {
    if (_.isEmpty(colss)) {
        return;
    }
    if (controller) {
        controller.abort();
    }
    controller = new AbortController();

    loading.value = true;
    let taked = _.take(colss, showInHeader);
    let columnS = _.map(taked, (e) => {
        return { data: e };
    });
    const url = `/${props.module}/table`;
    let allRows = null;
    let visibles = [];
    let col = [];
    let modal = _.forEach(props.editButton, (v, k) => {
        if (v) data[k] = v;
    });

    if (order === undefined){
        order = currentSortColumn.value;
    }

    if (dir === undefined){
        dir = currentDirColumn.value;
    }

    try {
        const response = await axios.post(url, {
            data: {
                columns: columnS,
                filters: filters,
                additionalFilter: ffilters.value,
                idModule: props.id,
                search: search,
            },
            buttons: JSON.stringify(props.buttonsInsideDatatable.value),
            modal,
            order: order,
            limits: pagination.value.rowsPerPage,
            start: startPagination.value,
            dir: dir,
            persistentFilter: props.persistentFilters,
            module: props.model,
        }, {
            signal: controller.signal
        });

        dataToFindExport.value = {
            data: {
                columns: columnS,
                filters: filters,
                additionalFilter: ffilters.value,
                idModule: props.id,
                search: search,
            },
            buttons: JSON.stringify(props.buttonsInsideDatatable.value),
            modal,
            order: order,
            limits: pagination.value.rowsPerPage,
            start: startPagination.value,
            dir: dir,
            url: url,
        };
        allRows = response.data.data;
        if (Array.isArray(allHeaders.value)) {
            allHeaders.value.sort((a, b) => {
                return parseInt(a.order) - parseInt(b.order);
            });
            allHeaders.value.forEach((header) => {
                let val = {
                    name: header.name,
                    label: header.label,
                    align: header.name !== "action" ? "left" : "right",
                    field: (row) => row[header.name],
                    format: (val) => `${val}`,
                    sortable: header.name !== "action",
                };
                col.push(val);
            });
        }

        headers.value.forEach((header) => {
            visibles.push(header.name);
        });
        pagination.value.rowsNumber = response.data.recordsFiltered;
    } catch (error) {
         loading.value = false;
        if (axios.isCancel(error)) {
            console.log('Petición previa abortada');
            return;
        }
        console.error(error);
    }

    columns.value = Object.values(col);
    rows.value = Object.values(allRows);
    comunRows.value = rows.value;
    if (isModuleFieldModule(props.model)) {
        rows.value = rows.value.map((element) => {
            const rowId = getIdItem(element.action);
            return { ...element, rowId: rowId };
        });
    }
    setVisibleColumns(Object.values(visibles));
    if (props.emitsRows) {
        emits("emitsRows", rows.value);
    }
    isInitial.value = false;
    loading.value = false;
};

const getIdItem = (action) => {
    const match = action.match(/id-item="(\d+)"/);
    return match ? match[1] : "";
};

const resetTable = () => {
    filtersM.value = !filtersM.value;
    getRowsByModule(
        _.map(headers.value, (v) => v.name),
        showHeaders.value,
        filters.value,
        currentSortColumn.value,
        pagination.value.descending,
        search.value
    );
};

const onRequestDebounced = debounce((props) => {
    search.value = props.filter;
    currentSortColumn.value = props.pagination.sortBy;
    const { page, rowsPerPage, sortBy, descending } = props.pagination;
    pagination.value.page = page;
    pagination.value.rowsPerPage = rowsPerPage;
    pagination.value.sortBy = sortBy;
    pagination.value.descending = descending;
    pagination.value.rowsPerPage = props.pagination.rowsPerPage;
    startPagination.value = (page - 1) * rowsPerPage;

    resetTable();
}, 3000);

function onRequest(props) {
    if (props.filter != "") {
        onRequestDebounced(props);
    } else {
        currentSortColumn.value = props.pagination.sortBy;
        const { page, rowsPerPage, sortBy, descending } = props.pagination;
        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.sortBy = sortBy;
        pagination.value.descending = descending;
        pagination.value.rowsPerPage = props.pagination.rowsPerPage;
        startPagination.value = (page - 1) * rowsPerPage;

        resetTable();
    }
}

const optionsFilters = ref([]);
watch(selectFilters, () => {
    selectFilters.value.forEach((element) => {
        optionsFilters.value.push(element);
    });
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
            expandAfterColumn.value = expandAfterColumn.value === c ? null : c;
            menuChangeExpand.value.hide();
            hideLoading();
        })
        .catch((err) => {
            hideLoading();
        });
};
const isValidHref = (href) => {
    if (!href) return false; // Si es null, undefined o ""
    if (href === "#") return false;
    if (href === "javascript:void(0)") return false;
    return true; // En cualquier otro caso, es válido
};

const getTdClass = (row, col) => {
    // Si quieres aplicar la clase a todas las columnas de esa fila:
    if (row.class_table_row && row.class_table_row.td) {
        return row.class_table_row.td;
    }

    // O si solo quieres aplicarla a una columna específica (por ejemplo, "current_stock"):
    // if (col.name === 'current_stock' && row.class_table_row?.td) {
    //   return row.class_table_row.td;
    // }

    return ""; // Sin clase por defecto
};
</script>

<style scoped></style>
