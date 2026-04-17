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
            data-bs-target="#crudProject"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="scheduling/project"
        model="Project"
        list="Proyectos"
        @table="table"
        :editButton="{ modal: 'crudProject' }"
        rowKey="title"
    ></Datatable>

    <div
        class="modal fade"
        id="crudProject"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <ProjectCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></ProjectCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import ProjectCrud from "./ProjectCrud.vue";

export default {
    name: "TypeTemplateListar",
    components: { Datatable, ProjectCrud },
    props: {},
    setup(props) {
        const title = ref("Crear Proyecto");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/scheduling/project/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudProject").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Proyecto";
            action.value = "/scheduling/project/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudProject").modal("show");
            title.value = "Editar Proyecto";
            action.value = `/scheduling/project/update/${idItem}`;
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
