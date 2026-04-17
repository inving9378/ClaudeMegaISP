<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light ms-auto"
            @click="reload"
        >
            Refrescar
        </button>
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light"
            data-bs-toggle="modal"
            data-bs-target="#cruddocumentationsubmenu"
        >
            Agregar
        </button>
    </div>
    <!-- Indicador de filtro activo -->
    <div v-if="filterMenuId" class="alert alert-info mb-2 d-flex justify-content-between align-items-center">
        <span>
            <i class="fas fa-filter me-2"></i>
            Mostrando submenús del menú: <strong>{{ filterMenuTitle || 'Cargando...' }}</strong>
        </span>
        <!-- CAMBIO: Botón en lugar de enlace para evitar recarga de página -->
        <button 
            @click="clearFilter" 
            class="btn btn-sm btn-outline-secondary"
        >
            <i class="fas fa-times me-1"></i>Quitar filtro
        </button>
    </div>
    <Datatable
        :key="tableKey"
        ref="datatableRef"
        module="administracion/documentation/documentation_submenu"
        model="DocumentationSubmenu"
        list="Listado de Submenús de Documentación"
        @table="table"
        :editButton="{ modal: 'cruddocumentationsubmenu' }"
        :filters="filters"
    >

    </Datatable>

    <div
        class="modal fade"
        id="cruddocumentationsubmenu"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <DocumentationSubmenuCrud
                    :action="action"
                    :key="reloadCrud"
                    :filter-menu-id="filterMenuId"
                    @close-modal="closeModal"
                ></DocumentationSubmenuCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../../base/shared/Datatable.vue";
import DocumentationSubmenuCrud from "./DocumentationSubmenuCrud.vue";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";

export default {
    name: "DocumentationSubmenuListar",
    components: { Datatable, DocumentationSubmenuCrud },
    props: {},
    setup(props) {
        const title = ref("Crear submenú para documentación");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const datatableRef = ref(null);
        const action = ref("/administracion/documentation/documentation_submenu/add");
        const reloadCrud = ref(true);
        const tableKey = ref(0);
        
        // Filtros reactivos para el Datatable
        const filters = ref({});
        const filterMenuId = ref(null);
        const filterMenuTitle = ref("");

        onMounted(() => {
            // Detectar filtro de URL (query param)
            detectFilterFromURL();

            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const detectFilterFromURL = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const menuId = urlParams.get('filter_menu_id');
            
            if (menuId) {
                filterMenuId.value = menuId;
                filters.value = {
                    documentation_menu_id: menuId
                };
                fetchMenuTitle(menuId);
            } else {
                filterMenuId.value = null;
                filters.value = {};
            }
        };

        // Método para quitar filtro sin recargar página
        const clearFilter = () => {
            // Limpiar variables reactivas
            filterMenuId.value = null;
            filterMenuTitle.value = "";
            filters.value = {};
            
            // Actualizar URL sin recargar 
            const url = new URL(window.location);
            url.searchParams.delete('filter_menu_id');
            window.history.pushState({}, '', url);
            
            // Actualizar action para creación sin filtro
            action.value = "/administracion/documentation/documentation_submenu/add";
            
            // Recargar tabla sin filtro (rápido, sin remontaje completo)
            tableKey.value++;
        };

        const fetchMenuTitle = async (menuId) => {
            try {
                const response = await axios.get(`/administracion/documentation/documentation_menu/get-title/${menuId}`);
                if (response.data && response.data.title) {
                    filterMenuTitle.value = response.data.title;
                } else {
                    filterMenuTitle.value = `Menú #${menuId}`;
                }
            } catch (error) {
                console.warn("No se pudo obtener el título del menú:", error);
                filterMenuTitle.value = `Menú #${menuId}`;
            }
        };

        // NUEVO: Función para ver contenidos
        const viewContents = (submenuId) => {
            window.location.href = `/administracion/documentation/documentation_submenu/${submenuId}`;
        };

        const closeModal = () => {
            $("#cruddocumentationsubmenu").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear submenú para documentación";
            
            if (filterMenuId.value) {
                action.value = `/administracion/documentation/documentation_submenu/add?filter_menu_id=${filterMenuId.value}`;
            } else {
                action.value = "/administracion/documentation/documentation_submenu/add";
            }
            
            reloadTableWithFilter();
        };

        const reloadTableWithFilter = () => {
            if (filterMenuId.value) {
                filters.value = {
                    documentation_menu_id: filterMenuId.value
                };
            }
            tableKey.value++;
        };

        const showEditModal = (idItem) => {
            $("#cruddocumentationsubmenu").modal("show");
            title.value = "Editar submenú para documentación";
            action.value = `/administracion/documentation/documentation_submenu/update/${idItem}`;
        };

        const reload = () => {
            reloadTableWithFilter();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            filters,
            filterMenuId,
            filterMenuTitle,
            datatableRef,
            tableKey,
            clearFilter,
            viewContents,
        };
    },
};
</script>

<style scoped></style>