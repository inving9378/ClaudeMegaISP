<template>
    <!-- Lista de Tareas con el estilo de la Torre (2026-11-04): encabezado, filtros en tarjeta y tabla en tarjeta. -->
    <div class="tk-wrap">
        <div class="tk-bar">
            <div class="tk-bar-izq">
                <span class="tk-icono"><i class="fas fa-tasks"></i></span>
                <div>
                    <h1 class="tk-h1">{{ hasShowArchived() ? "Tareas archivadas" : "Tareas" }}</h1>
                    <div class="tk-meta">Qué hay que hacer, quién lo tiene y para cuándo</div>
                </div>
            </div>
            <div class="tk-bar-der">
                <a class="tk-btn" href="/scheduling/task/calendar"><i class="far fa-calendar-alt"></i> Calendario</a>
                <button v-if="!hasShowArchived()" type="button" class="tk-btn tk-btn-acento" @click="showModal">
                    <i class="fas fa-plus"></i> Nueva tarea
                </button>
            </div>
        </div>

        <section class="tk-card">
            <h2 class="tk-h2">Filtros</h2>
            <div class="tk-filtros">
                <SelectComponentWithCheckbox v-if="hasPermission.data.canView('task_filter_project')" :property="{
                    field: 'project_id',
                    label: 'Proyecto',
                    class_col: 'partial',
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
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
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
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
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
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
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
                    options: {
                        ToDo: 'Por Hacer',
                        InProgress: 'En Progreso',
                        Done: 'Terminado',
                    },
                    module_id: module_id,
                }" @change="clearError('partner_id')" :modelValue="getModelValueFilter('status')"
                    :errors="dataForm.data.errors" @update-field="setFilter" />
                <InputVuePickerMultiple v-if="hasShowArchived()" :property="{
                    field: 'archived_at',
                    label: 'Fecha archivado',
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
                    placeholder: 'Cualquier fecha',
                }" @update-field="setFilter" :modelValue="archived_at" :errors="dataForm.data.errors"
                    @change="clearError('archived_at')">
                </InputVuePickerMultiple>
                <InputVuePickerMultiple :property="{
                    field: 'finish_at',
                    label: 'Fecha terminado',
                    class_label: 'tk-flabel',
                    class_field: 'tk-ffield',
                    placeholder: 'Cualquier fecha',
                }" @update-field="setFilter" :modelValue="finish_at" :errors="dataForm.data.errors"
                    @change="clearError('finish_at')">
                </InputVuePickerMultiple>
            </div>
        </section>

        <section class="tk-card tk-tabla">
            <Datatable v-if="isReady" module="scheduling/task" model="Task" list="Tareas" :editButton="{ modal: 'crudTask' }"
                @table="table" :buttons="getButtonDatatable()"
                :persistentFilters="filterPersistent" :filters="filtersUser"></Datatable>
        </section>
    </div>

    <div class="modal fade" id="crudTask" data-backdrop="static" data-keyboard="false" v-if="showCrud">
        <div class="modal-dialog modal-dialog-slide-right">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <TaskCrud :action="action" :key="reloadCrud" :fromTicketId="fromTicketId" @close-modal="closeModal"></TaskCrud>
            </div>
        </div>
    </div>
</template>

<script>
import { currentSearch } from "../../../../hook/comunValues.js";
import Datatable from "../../../base/shared/Datatable.vue";
import { onMounted, nextTick, reactive, ref, watch, onUnmounted, getCurrentInstance } from "vue";
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
        const ns = `.leak983-taskListar-${getCurrentInstance().uid}`;
        onUnmounted(() => {
            $(document).off(ns);
        });
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
        const fromTicketId = ref(null);

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            if (props.persistent_filters) {
                filterPersistent.value = JSON.parse(props.persistent_filters);
            }
            if (props.filters) {
                filtersUser.value = JSON.parse(props.filters);
            }
            $(document).on("click" + ns, ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });

            $(document).on("click" + ns, ".btn_add_task_list", function () {
                showModal();
            });

            $(document).on("click" + ns, ".unarchive", function (e) {
                let idItem = $(e.target).parent().attr("id-item");
                unArchiveTask(idItem);
            });

            isReady.value = true;
            removeCssToButtonQuasar();

            // Abrir modal pre-llenado si viene desde un Ticket
            const fromTicketParam = new URLSearchParams(currentSearch()).get('from_ticket');
            if (fromTicketParam) {
                fromTicketId.value = parseInt(fromTicketParam);
                await nextTick();
                showCrud.value = true;
                await nextTick();
                showModal();
            }
        });

        const hasShowArchived = () => {
            return window.location.href.includes("show-archived");
        };

        const closeModal = () => {
            window.bootstrap.Modal.getInstance(document.getElementById('crudTask'))?.hide();
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Tarea";
            action.value = "/scheduling/task/add";
            datatable.table.reload();
        };

        const showModal = () => {
            title.value = "Crear Tarea";
            action.value = "/scheduling/task/add";
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('crudTask')).show();
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

        // «Nueva tarea» vive en el encabezado de la pantalla; la tabla ya no repite su propio «Agregar».
        const getButtonDatatable = () => ({});

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
            showCrud,
            fromTicketId,
            showModal,
        };
    },
};
</script>

<style scoped>
/* Estilo de la Torre de control (TorreControl.vue): mismas fichas de color, tarjetas de 14px y
   títulos de sección en versalitas. Los colores de modo oscuro se cambian abajo. */
.tk-wrap {
    --tk-surface: #fff; --tk-ink: #111827; --tk-muted: #6b7280; --tk-line: #e5e7eb;
    --tk-bg2: #f8fafc; --tk-accent: #0d9488; --tk-accent-ink: #0f766e; --tk-hover: #f8fafc;
    display: flex; flex-direction: column; gap: 14px; color: var(--tk-ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
:global(body[data-layout-mode="dark"] .tk-wrap) {
    --tk-surface: #1f2937; --tk-ink: #e5e7eb; --tk-muted: #9ca3af; --tk-line: #374151;
    --tk-bg2: #111827; --tk-accent: #2dd4bf; --tk-accent-ink: #5eead4; --tk-hover: #273244;
}
.tk-bar, .tk-card {
    background: var(--tk-surface); border: 1px solid var(--tk-line); border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}
.tk-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; flex-wrap: wrap; }
.tk-bar-izq { display: flex; align-items: center; gap: 12px; }
.tk-bar-der { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tk-icono {
    width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
    background: rgba(13, 148, 136, .1); color: var(--tk-accent); font-size: 16px;
}
.tk-h1 { font-size: 18px; font-weight: 700; margin: 0; color: var(--tk-ink); }
.tk-meta { font-size: 12.5px; color: var(--tk-muted); }
.tk-btn {
    display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600;
    color: var(--tk-ink); background: var(--tk-surface); border: 1px solid var(--tk-line);
    padding: 8px 14px; border-radius: 9px; cursor: pointer; text-decoration: none;
}
.tk-btn:hover { border-color: var(--tk-accent); color: var(--tk-accent); }
.tk-btn-acento { color: #fff; background: var(--tk-accent); border-color: var(--tk-accent); }
.tk-btn-acento:hover { color: #fff; filter: brightness(1.05); }
.tk-card { padding: 16px 18px; }
.tk-h2 { font-size: 12.5px; letter-spacing: .04em; text-transform: uppercase; color: var(--tk-muted); margin: 0 0 12px; font-weight: 700; }

/* Filtros: rejilla pareja, etiqueta arriba, controles del mismo alto. */
.tk-filtros { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px 16px; }
.tk-filtros > :deep(.row) { width: auto; max-width: none; margin: 0 !important; flex-direction: column; flex-wrap: nowrap; padding: 0; }
.tk-filtros :deep(.tk-flabel) {
    width: 100%; padding: 0; margin: 0 0 5px; text-align: left;
    font-size: 12px; font-weight: 600; color: var(--tk-muted);
}
.tk-filtros :deep(.tk-ffield) { width: 100%; padding: 0 !important; display: flex; align-items: center; gap: 8px; }
.tk-filtros :deep(.multiselect-native-select), .tk-filtros :deep(.btn-group) { flex: 1 1 auto; min-width: 0; width: 100%; }
.tk-filtros :deep(button.multiselect) {
    width: 100%; height: 38px; text-align: left !important; font-size: 13px; color: var(--tk-ink);
    background-color: var(--tk-surface); border: 1px solid var(--tk-line); border-radius: 9px;
    padding: 0 30px 0 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.tk-filtros :deep(button.multiselect:hover), .tk-filtros :deep(button.multiselect:focus) { border-color: var(--tk-accent); box-shadow: none; }
.tk-filtros :deep(.dp__main) { width: 100% !important; }
.tk-filtros :deep(.dp__input) {
    height: 38px; border-radius: 9px; border: 1px solid var(--tk-line) !important; font-size: 13px;
    background: var(--tk-surface); color: var(--tk-ink);
}
.tk-filtros :deep(.form-check-input) { flex: 0 0 auto; margin: 0; }

/* Tabla: la tarjeta la pone la pantalla; la de la tabla se aplana. */
.tk-tabla { padding: 6px 6px 2px; overflow: hidden; }
.tk-tabla :deep(.datatable_base), .tk-tabla :deep(.q-card) { background: transparent; box-shadow: none; border: 0; margin: 0; }
.tk-tabla :deep(.q-table__container) { background: transparent; box-shadow: none; color: var(--tk-ink); }
.tk-tabla :deep(.q-table__top) { flex-wrap: nowrap; justify-content: space-between; align-items: center; gap: 12px; padding: 8px 10px 10px; }
/* .q-table__top es .row: Bootstrap estira cada hijo al 100% y el buscador se salía de la tarjeta. */
.tk-tabla :deep(.q-table__top > *) { width: auto; max-width: none; }
.tk-tabla :deep(.q-table__top > div:first-child) { flex: 0 0 auto; margin: 0 !important; }
.tk-tabla :deep(.q-table__top > .row) { flex: 1 1 auto; margin: 0; }
.tk-tabla :deep(.q-table__top .dt-toolbar) { flex-wrap: nowrap; }
.tk-tabla :deep(.q-table__top .text-h6) { font-size: 12.5px; letter-spacing: .04em; text-transform: uppercase; color: var(--tk-muted); font-weight: 700; }
.tk-tabla :deep(.dt-search .q-field__control) { border-radius: 9px; }
.tk-tabla :deep(thead th) {
    font-size: 11.5px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
    color: var(--tk-muted); background: var(--tk-bg2); border-bottom: 1px solid var(--tk-line);
}
.tk-tabla :deep(tbody td) { font-size: 13px; border-bottom: 1px solid var(--tk-line); vertical-align: middle; }
.tk-tabla :deep(tbody tr:hover td) { background: var(--tk-hover); }
.tk-tabla :deep(tbody td a) { color: var(--tk-ink); }
.tk-tabla :deep(tbody td a:hover) { color: var(--tk-accent); }
.tk-tabla :deep(tbody td:nth-child(2) a) { color: var(--tk-accent-ink); font-weight: 700; }
/* Hasta dos renglones por celda; el resto se corta con «…». */
.tk-tabla :deep(.q-item__label.ellipsis) {
    white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; max-width: 260px; min-width: 60px;
}

@media (max-width: 640px) {
    .tk-bar { padding: 12px; }
    .tk-bar-der { width: 100%; }
    .tk-bar-der > * { flex: 1 1 auto; justify-content: center; }
}
</style>
