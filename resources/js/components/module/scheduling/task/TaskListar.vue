<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <SelectComponentWithCheckbox v-if="hasPermission.data.canView('task_filter_project')" :property="{
            field: 'project_id',
            label: 'Proyecto',
            class_col: 'partial',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            class_field: 'col-sm-12 col-md-4',
            search: {
                model: 'App\\Models\\Project',
                id: `id`,
                text: 'title',
            },
            module_id: module_id,
        }" @change="clearError('project_id')" :modelValue="getModelValueFilter('project_id')"
            :errors="dataForm.data.errors" @update-field="setFilter" />
        <SelectComponentTeam v-if="hasPermission.data.canView('task_filter_assigned_to')" :property="{
            field: 'assignuser_filter',
            label: 'Asignado a',
            class_col: 'partial',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            class_field: 'col-sm-12 col-md-4',
            search: {
                model: 'App\\Models\\User',
                id: `id`,
                text: 'name',
                scope: 'notClientRole',
            },
            module_id: module_id
        }" @change="clearError('assignuser_filter')" :modelValue="getModelValueFilter('assignuser_filter')"
            :errors="dataForm.data.errors" @update-field="setFilter" />

        <SelectComponentWithCheckbox v-if="hasPermission.data.canView('task_filter_partner')" :property="{
            field: 'partner_id',
            label: 'Socios',
            class_col: 'partial',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            class_field: 'col-sm-12 col-md-4',
            search: {
                model: 'App\\Models\\Partner',
                id: `id`,
                text: 'name',
            },
            module_id: module_id,
        }" @change="clearError('partner_id')" :modelValue="getModelValueFilter('partner_id')"
            :errors="dataForm.data.errors" @update-field="setFilter" />
        <SelectComponentWithCheckbox v-if="hasPermission.data.canView('task_filter_status')" :property="{
            field: 'status',
            label: 'Estado',
            class_col: 'partial',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            class_field: 'col-sm-12 col-md-4',
            options: {
                ToDo: 'Por Hacer',
                InProgress: 'En Progreso',
                Done: 'Terminado',
            },
            module_id: module_id,
        }" @change="clearError('partner_id')" :modelValue="getModelValueFilter('status')"
            :errors="dataForm.data.errors" @update-field="setFilter" />
    </div>

    <div class="d-flex flex-wrap">
        <InputVuePickerMultiple v-if="hasShowArchived()" :property="{
            field: 'archived_at',
            label: 'Fecha Archivado',
            class_field: 'col-sm-12 col-md-9',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            placeholder: 'Fecha',
        }" @update-field="setFilter" :modelValue="archived_at" :errors="dataForm.data.errors"
            @change="clearError('archived_at')">
        </InputVuePickerMultiple>

        <InputVuePickerMultiple :property="{
            field: 'finish_at',
            label: 'Fecha Terminado',
            class_field: 'col-sm-12 col-md-9',
            class_label:
                'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            placeholder: 'Fecha Terminado',
        }" @update-field="setFilter" :modelValue="finish_at" :errors="dataForm.data.errors"
            @change="clearError('finish_at')">
        </InputVuePickerMultiple>
    </div>

    <Datatable v-if="isReady" module="scheduling/task" model="Task" list="Tareas" :editButton="{ modal: 'crudTask' }"
        @table="table" :buttons="getButtonDatatable()"
        :persistentFilters="filterPersistent" :filters="filtersUser"></Datatable>


    <div class="modal fade" id="crudTask" data-backdrop="static" data-keyboard="false" v-if="showCrud">
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <TaskCrud :action="action" :key="reloadCrud" @close-modal="closeModal"></TaskCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable.vue";
import { onMounted, reactive, ref,watch } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import TaskCrud from "./TaskCrud.vue";
import SelectComponentWithCheckbox from "../../../../shared/SelectComponentWithCheckbox.vue";
import { filters } from "../../../../helpers/filters";
import Form from "../../../../helpers/Form";
import { clearError } from "../../../../hook/crudHook";
import SelectComponentTeam from "../../../../shared/SelectComponentTeam.vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import InputVuePickerMultiple from "../../../../shared/InputVuePickerMultiple.vue";

export default {
    name: "TaskListar",
    components: {
        Datatable,
        TaskCrud,
        SelectComponentWithCheckbox,
        SelectComponentTeam,
        InputVuePickerMultiple,
    },
    props: {
        persistent_filters: {
            type: String,
            default: null,
        },
        module_id: {
            type: String,
            default: null,
        },
        filters: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const title = ref("Crear Tarea");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/scheduling/task/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });

        const filterPersistent = ref({});
        const filtersUser = ref({});

        const showButtonFiltersArchived = ref(true);
        const hasPermission = reactive({
            data: new Permission({}),
        });

        const archived_at = ref("");
        const finish_at = ref("");
        const isReady = ref(false);
        const showCrud = ref(false);

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            if (props.persistent_filters) {
                filterPersistent.value = JSON.parse(props.persistent_filters);
            }
            if (props.filters) {
                filtersUser.value = JSON.parse(props.filters);
            }
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });

            $(document).on("click", ".btn_add_task_list", function () {
                showModal();
            });

            $(document).on("click", ".unarchive", function (e) {
                let idItem = $(e.target).parent().attr("id-item");
                unArchiveTask(idItem);
            });

            isReady.value = true;
            removeCssToButtonQuasar();
        });

        const hasShowArchived = () => {
            return window.location.href.includes("show-archived");
        };

        const closeModal = () => {
            $("#crudTask").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Tarea";
            action.value = "/scheduling/task/add";
            datatable.table.reload();
        };

        const showModal = () => {
            title.value = "Crear Tarea";
            action.value = "/scheduling/task/add";
            $("#crudTask").modal("show");
        }

        const showEditModal = (idItem) => {
            window.open(`/scheduling/task/editar/${idItem}`);
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
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
                class: "btn btn-outline-info waves-effect waves-light ms-3 me-3 btn_add_task_list",
                href: "javascript:void(0)",
                id: "",
                text: "Agregar",
            };
            return buttons;
        };

        const removeCssToButtonQuasar = () => {
            $(".button_table_type_select").removeClass("row");
            $(".button_table_type_select").removeClass("q-btn-group");
            $(".button_table_type_select").removeClass("d-none");
        };

        const unArchiveTask = (id) => {
            if (confirm("¿Seguro que desea desarchivar esta tarea?")) {
                axios
                    .post(`/scheduling/task/unarchive/${id}`)
                    .then((response) => {
                        reloadCrud.value = !reloadCrud.value;
                    })
                    .catch((error) => {
                        console.log(error);
                    });
            }
        };

        const getModelValueFilter = (field) => {
            if (filtersUser.value[field]) {
                return filtersUser.value[field];
            }
            return [];
        };

        watch(isReady, () => {
            if(isReady.value){
                showCrud.value = true;
            }
        });

        return {
            title,
            action,
            closeModal,
            reloadCrud,
            setFilter,
            dataForm,
            getButtonDatatable,
            clearError,
            showButtonFiltersArchived,
            filterPersistent,
            hasPermission,
            filtersUser,
            archived_at,
            hasShowArchived,
            finish_at,
            getModelValueFilter,
            isReady,
            showCrud
        };
    },
};
</script>

<style scoped></style>
