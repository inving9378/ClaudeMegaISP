<template>
    <!-- Scheduling › Calendario con aspecto de Google Calendar (2026-11-04): barra única, filtros plegables. -->
    <div class="gcp">
        <QCalendar :module_id="module_id">
            <template #acciones>
                <button type="button" class="gcp-btn" :class="{ activo: verFiltros }" @click="verFiltros = !verFiltros">
                    <i class="fas fa-filter"></i> Filtros
                    <span v-if="nFiltros" class="gcp-n">{{ nFiltros }}</span>
                </button>
                <a class="gcp-btn" href="/scheduling/task" title="Ver la lista de tareas"><i class="fas fa-tasks"></i> Tareas</a>
                <button type="button" class="gcp-crear" data-bs-target="#crudTask" data-bs-toggle="modal" @click="reloadCrudd">
                    <i class="fas fa-plus"></i> Crear
                </button>
            </template>
            <template #debajo>
                <div v-show="verFiltros" class="gcp-filtros">
                    <SelectComponentWithCheckbox
                        v-if="hasPermission.data.canView('calendar_filter_project')"
                        :property="{
                            field: 'project_id',
                            label: 'Proyecto',
                            class_col: '',
                            class_label: 'gcp-flabel',
                            class_field: 'gcp-ffield',
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
                            class_label: 'gcp-flabel',
                            class_field: 'gcp-ffield',
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
                            class_label: 'gcp-flabel',
                            class_field: 'gcp-ffield',
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
                            class_label: 'gcp-flabel',
                            class_field: 'gcp-ffield',
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
                    <div v-if="hasPermission.data.canView('calendar_filter_filter')" class="gcp-mas">
                        <button type="button" class="gcp-btn" data-bs-toggle="modal" :data-bs-target="`#multipleFilers_calendar`">
                            <i class="fas fa-sliders-h"></i> Más filtros
                        </button>
                    </div>
                </div>
            </template>
        </QCalendar>
    </div>

    <FilterDataTable
        :filters="getFilters()"
        :id="`multipleFilers_calendar`"
        title="Más filtros"
        :key="filtersM"
        @resetFilters="resetFilters"
    >
    </FilterDataTable>

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
import { computed, onMounted, reactive, ref, watch, onUnmounted, getCurrentInstance } from "vue";
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
        const ns = `.leak983-calendarIndex-${getCurrentInstance().uid}`;
        onUnmounted(() => {
            $(document).off(ns);
        });
        const title = ref("Editar Tarea");
        const action = ref("/scheduling/task/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });
        const filtersM = ref(true);
        const verFiltros = ref(false);
        // Cuántos filtros están aplicando (para el número junto a «Filtros»).
        const nFiltros = computed(() =>
            Object.values(filters.value || {}).filter((v) =>
                Array.isArray(v) ? v.length > 0 : v !== null && v !== undefined && v !== "" && v !== false
            ).length
        );
        const showButtonFiltersArchived = ref(true);

        const hasPermission = reactive({
            data: new Permission({}),
        });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            $(document).on("click" + ns, ".event_edit_task", function () {
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
            window.bootstrap.Modal.getInstance(document.getElementById('crudTask'))?.hide();
            reloadCrud.value = !reloadCrud.value;
        };

        const showEditModal = (idItem) => {
            reloadCrud.value = true;
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('crudTask')).show();
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
            verFiltros,
            nFiltros,
        };
    },
};
</script>

<style scoped>
.gcp {
    --gcp-borde: #e0e3e7;
    --gcp-texto: #3c4043;
    --gcp-suave: #70757a;
    --gcp-fondo: #fff;
    --gcp-hover: #f1f3f4;
    --gcp-acento: #1a73e8;
    --gcp-acento-suave: #e8f0fe;
    background: var(--gcp-fondo);
    border: 1px solid var(--gcp-borde);
    border-radius: 12px;
    padding: 16px 18px;
}
:global(body[data-layout-mode="dark"] .gcp) {
    --gcp-borde: #3c4043;
    --gcp-texto: #e8eaed;
    --gcp-suave: #9aa0a6;
    --gcp-fondo: #202124;
    --gcp-hover: #2d2e31;
    --gcp-acento: #8ab4f8;
    --gcp-acento-suave: rgba(138, 180, 248, 0.16);
}
.gcp-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 1px solid var(--gcp-borde);
    border-radius: 18px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gcp-texto);
    background: transparent;
    text-decoration: none;
}
.gcp-btn:hover {
    background: var(--gcp-hover);
    color: var(--gcp-texto);
}
.gcp-btn.activo {
    background: var(--gcp-acento-suave);
    color: var(--gcp-acento);
    border-color: transparent;
}
.gcp-n {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    background: var(--gcp-acento);
    color: var(--gcp-fondo);
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
/* Botón «Crear» como el de Google Calendar: píldora con sombra. */
.gcp-crear {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 0;
    border-radius: 18px;
    padding: 7px 18px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gcp-texto);
    background: var(--gcp-fondo);
    box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
}
.gcp-crear i {
    color: var(--gcp-acento);
}
.gcp-crear:hover {
    background: var(--gcp-hover);
}

.gcp-filtros {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px 16px;
    align-items: end;
    padding: 12px 14px;
    margin-bottom: 12px;
    border-radius: 10px;
    background: var(--gcp-hover);
}
.gcp-filtros > :deep(.row) {
    width: auto;
    max-width: none;
    margin: 0 !important;
    padding: 0;
    flex-direction: column;
    flex-wrap: nowrap;
}
.gcp-filtros :deep(.gcp-flabel) {
    width: 100%;
    padding: 0;
    margin: 0 0 5px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gcp-suave);
    text-align: left;
}
.gcp-filtros :deep(.gcp-ffield) {
    width: 100%;
    padding: 0 !important;
    display: flex;
    align-items: center;
    gap: 8px;
}
.gcp-filtros :deep(.multiselect-native-select),
.gcp-filtros :deep(.btn-group) {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
}
.gcp-filtros :deep(button.multiselect) {
    width: 100%;
    height: 36px;
    text-align: left !important;
    font-size: 13px;
    color: var(--gcp-texto);
    background-color: var(--gcp-fondo);
    border: 1px solid var(--gcp-borde);
    border-radius: 8px;
    padding: 0 30px 0 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.gcp-filtros :deep(.form-check-input) {
    flex: 0 0 auto;
    margin: 0;
}
.gcp-mas {
    display: flex;
    align-items: flex-end;
    padding-bottom: 8px;
}
</style>
