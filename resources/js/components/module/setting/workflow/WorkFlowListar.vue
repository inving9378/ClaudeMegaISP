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
            data-bs-target="#crudworkflow"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="configuracion/work-flow"
        model="WorkFlow"
        list="Listado Flujo de Trabajo"
        @table="table"
        :editButton="{ modal: 'crudworkflow' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudworkflow"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <WorkFlowCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></WorkFlowCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import WorkFlowCrud from "./WorkFlowCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "ColonyListar",
    components: { Datatable, WorkFlowCrud },
    props: {
    },
    setup(props) {
        const title = ref("Crear Flujo de Trabajo");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/configuracion/work-flow/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudworkflow").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Flujo de Trabajo";
            action.value = "/configuracion/work-flow/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudworkflow").modal("show");
            title.value = "Editar Flujo de Trabajo";
            action.value = `/configuracion/work-flow/update/${idItem}`;
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
