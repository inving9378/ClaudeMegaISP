<template>
    <div class="d-flex flex-wrap gap-2 mb-2 justify-content-end">
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
            data-bs-target="#crudTemplateTask"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="configuracion/template-task"
        model="TemplateTask"
        list="Listado Plantillas de Tareas"
        @table="table"
        :editButton="{ modal: 'crudTemplateTask' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudTemplateTask"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <TemplateTaskCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></TemplateTaskCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import TemplateTaskCrud from "./TemplateTaskCrud.vue";

export default {
    name: "TemplateTaskListar",
    components: { Datatable, TemplateTaskCrud },
    props: {
    },
    setup(props) {
        const title = ref("Crear Plantilla de Tarea");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/configuracion/template-task/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudTemplateTask").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Plantilla de Tarea";
            action.value = "/configuracion/template-task/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudTemplateTask").modal("show");
            title.value = "Editar Plantilla de Tarea";
            action.value = `/configuracion/template-task/update/${idItem}`;
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
