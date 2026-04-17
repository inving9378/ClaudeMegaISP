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
            data-bs-target="#cruddocumentationmenu"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="administracion/documentation/documentation_menu"
        model="DocumentationMenu"
        list="Listado de Menús de Documentación"
        @table="table"
        :editButton="{ modal: 'cruddocumentationmenu' }"
    ></Datatable>

    <div
        class="modal fade"
        id="cruddocumentationmenu"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <DocumentationMenuCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></DocumentationMenuCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../../base/shared/Datatable";
import DocumentationMenuCrud from "./DocumentationMenuCrud.vue";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";

export default {
    name: "DocumentationMenuListar",
    components: { Datatable, DocumentationMenuCrud },
    props: {
    },
    setup(props) {
        const title = ref("Crear menú para documentación");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/administracion/documentation/documentation_menu/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#cruddocumentationmenu").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear menú para documentación";
            action.value = "/administracion/documentation/documentation_menu/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#cruddocumentationmenu").modal("show");
            title.value = "Editar menú para documentación";
            action.value = `/administracion/documentation/documentation_menu/update/${idItem}`;
        };

        const reload = () => {
            datatable.table.reload();
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
        };
    },
};
</script>

<style scoped></style>
