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
            data-bs-target="#crudTypeTemplate"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="administracion/document_type_template"
        model="DocumentTypeTemplate"
        list="Listado de Tipos de Plantilla"
        @table="table"
        :editButton="{ modal: 'crudTypeTemplate' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudTypeTemplate"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <TypeTemplateCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></TypeTemplateCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import TypeTemplateCrud from "./TypeTemplateCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "TypeTemplateListar",
    components: { Datatable, TypeTemplateCrud },
    setup() {
        const title = ref("Crear Tipo de Plantilla");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/administracion/document_type_template/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudTypeTemplate").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Tipo de Plantilla";
            action.value = "/administracion/document_type_template/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudTypeTemplate").modal("show");
            title.value = "Editar Tipo de Plantilla";
            action.value = `/administracion/document_type_template/update/${idItem}`;
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
