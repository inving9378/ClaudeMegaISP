<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <SelectComponentWithCheckbox
            v-if="hasPermission.data.canView('calendar_filter_project')"
            :property="{
                field: 'project_id',
                label: 'Proyecto',
                class_col: '',
                search: {
                    model: 'App\\Models\\Project',
                    id: `id`,
                    text: 'title',
                },
                module_id: module_id,
            }"
            @change="clearError('project_id')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />
        <SelectComponentTeam
            v-if="hasPermission.data.canView('calendar_filter_assigned_to')"
            :property="{
                field: 'assignuser_filter',
                label: 'Asignado a',
                class_col: '',
                search: {
                    model: 'App\\Models\\User',
                    id: `id`,
                    text: 'name',
                    scope: 'notClientRole',
                },
                module_id: module_id,
                class_label:
                    'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                class_field: 'col-sm-12 col-md-9',
            }"
            @change="clearError('assignuser_filter')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />

        <SelectComponentWithCheckbox
            v-if="hasPermission.data.canView('calendar_filter_partner')"
            :property="{
                field: 'partner_id',
                label: 'Socios',
                class_col: '',
                search: {
                    model: 'App\\Models\\Partner',
                    id: `id`,
                    text: 'name',
                },
                module_id: module_id,
            }"
            @change="clearError('partner_id')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />
        <SelectComponentWithCheckbox
            v-if="hasPermission.data.canView('calendar_filter_status')"
            :property="{
                field: 'status',
                label: 'Estado',
                class_col: '',
                options: {
                    ToDo: 'Por Hacer',
                    InProgress: 'En Progreso',
                    Done: 'Terminado',
                },
                module_id: module_id,
            }"
            @change="clearError('partner_id')"
            :modelValue="[]"
            :errors="dataForm.data.errors"
            @update-field="setFilter"
        />
        <div class="multiple-filters-datatable">
            <button
                v-if="hasPermission.data.canView('calendar_filter_filter')"
                type="button"
                class="btn btn-outline-primary waves-effect waves-light ms-3"
                data-bs-toggle="modal"
                :data-bs-target="`#multipleFilers_calendar`"
            >
                Filtros
            </button>
        </div>
        <div>
            <button
                type="button"
                class="btn btn-outline-info waves-effect waves-light ms-3 me-3"
                data-bs-target="#crudTask"
                data-bs-toggle="modal"
                @click="reloadCrudd"
            >
                <span>Agregar Tarea</span>
            </button>
        </div>
    </div>
    <div class="legend-task-calendar p-3">
        <h5 class="mb-3">Leyenda de Tareas</h5>
        <div class="d-flex">
            <!-- Prioridad de la tarea -->
            <div class="mb-2">
                <strong>Prioridad:</strong>
                <div class="d-flex align-items-center">
                    <span class="badge priority-high me-2">Alta</span>
                    <span class="badge priority-medium text-dark me-2"
                        >Media</span
                    >
                    <span class="badge priority-low">Baja</span>
                </div>
            </div>

            <!-- Estado de la tarea -->
            <div class="ms-4 mb-2">
                <strong>Estado:</strong>
                <div class="d-flex align-items-center">
                    <span class="badge status-task-new me-2">Por Hacer</span>
                    <span class="badge status-task-in-progress text-dark me-2"
                        >En Progreso</span
                    >
                    <span class="badge status-task-done">Terminada</span>
                </div>
            </div>

            <div class="ms-4">
                <span style="display: block; width: 250px">
                    <img
                        :src="`${imgbase}images/leyenda_tareas.png`"
                        class="img_legend_task"
                    />
                </span>
            </div>
        </div>
    </div>
    <FilterDataTable
        :filters="getFilters()"
        :id="`multipleFilers_calendar`"
        title="Filters"
        :key="filtersM"
        @resetFilters="resetFilters"
    >
    </FilterDataTable>

    <QCalendar :module_id="module_id"> </QCalendar>
    <div
        class="modal fade"
        id="crudTask"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <TaskCrud
                    :action="action"
                    :key="reloadCrud"
                    :customerId="clientMainInformationId"
                    @close-modal="closeModal"
                ></TaskCrud>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import TaskCrud from "../task/TaskCrud.vue";
import QCalendar from "../../../../shared/QCalendar.vue";
import { filters } from "../../../../helpers/filters";
import Form from "../../../../helpers/Form";

import SelectComponentWithCheckbox from "../../../../shared/SelectComponentWithCheckbox.vue";
import FilterDataTable from "../../../base/shared/FilterDataTable.vue";
import { clientMainInformationId } from "../../client/info/comun_variable";
import SelectComponentTeam from "../../../../shared/SelectComponentTeam.vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "CalendarIndex",
    components: {
        TaskCrud,
        SelectComponentWithCheckbox,
        FilterDataTable,
        SelectComponentTeam,
        QCalendar,
    },
    props: {
        module_id: {
            type: String,
        },
        imgbase: String,
    },
    setup(props) {
        const title = ref("Editar Tarea");
        const action = ref("/scheduling/task/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });
        const filtersM = ref(true);
        const showButtonFiltersArchived = ref(true);

        const hasPermission = reactive({
            data: new Permission({}),
        });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            $(document).on("click", ".event_edit_task", function () {
                let idItem = $(this).attr("data-id-item");
                clientMainInformationId.value = $(this).attr(
                    "data-client-information"
                );
                showEditModal(idItem);
            });
            removeCssToButtonQuasar();
        });

        const reloadCrudd = () => {
            action.value = "/scheduling/task/add";
            title.value = "Agregar Tarea";
            reloadCrud.value = !reloadCrud.value;
        };

        const closeModal = () => {
            $("#crudTask").modal("hide");
            reloadCrud.value = !reloadCrud.value;
        };

        const showEditModal = (idItem) => {
            reloadCrud.value = true;
            $("#crudTask").modal("show");
            title.value = "Editar Tarea";
            action.value = `/scheduling/task/update/${idItem}`;
        };

        const setFilter = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        const getButtonDatatable = () => {
            let buttons = {};
            buttons.add = {
                class: "btn btn-outline-info waves-effect waves-light ms-3 me-3",
                href: "javascript:void(0)",
                id: "",
                dataBsTarget: "#crudTask",
                dataBsToogle: "modal",
                text: "Agregar",
            };
            return buttons;
        };

        const getFilters = () => {
            let options = {};
            options.filter_2 = {
                type: "input_vue_datepicker_multiple",
                field: "periodo",
                include: 1,
                label: "Periodo",
                placeholder: "",
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };
            options.filter_4 = {
                type: "select_component_with_search",
                field: "client_main_information_id_filter",
                include: 1,
                label: "Cliente",
                placeholder: "Seleccione Cliente",
                search: {
                    model: "App\\Models\\ClientMainInformation",
                    id: `id`,
                    text: "name",
                },
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };
            options.filter_5 = {
                type: "input-string",
                field: "id",
                include: 1,
                label: "Id de la Tarea",
                placeholder: "Id de la Tarea",
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };

            options.filter_6 = {
                type: "select-2-component",
                field: "priority",
                include: 1,
                label: "Priority",
                placeholder: "Select Condition",
                options: {
                    Alta: "Alta",
                    Media: "Media",
                    Baja: "Baja",
                },
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };

            options.filter_8 = {
                type: "select-2-component",
                field: "location_id",
                include: 1,
                label: "Ubicación",
                placeholder: "Seleccione Ubicación",
                search: {
                    model: "App\\Models\\Location",
                    id: `id`,
                    text: "name",
                },
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };

            options.filter_9 = {
                type: "input-string",
                field: "text",
                include: 1,
                label: "Busqueda Texto",
                placeholder: "Busqueda Texto",
                class_col: "full",
                class_label: "col-12",
                class_field: "col-12",
            };
            return options;
        };

        const removeCssToButtonQuasar = () => {
            $(".button_table_type_select").removeClass("row");
            $(".button_table_type_select").removeClass("q-btn-group");
            $(".button_table_type_select").removeClass("d-none");
        };

        const resetFilters = () => {
            filtersM.value = !filtersM.value;
            filters.value = {};
            showButtonFiltersArchived.value = true;
        };

        const addFilterStatusArchived = () => {
            const value = ref(true);
            showButtonFiltersArchived.value = false;

            setFilter({
                field: "archived",
                value: value,
            });
        };

        return {
            title,
            action,
            reloadCrud,
            setFilter,
            getButtonDatatable,
            getFilters,
            dataForm,
            closeModal,
            reloadCrudd,
            resetFilters,
            filtersM,
            clientMainInformationId,
            addFilterStatusArchived,
            showButtonFiltersArchived,
            hasPermission,
        };
    },
};
</script>

<style scoped></style>
