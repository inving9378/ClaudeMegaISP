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
            data-bs-target="#crudcolony"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="administracion/colonia"
        model="Colony"
        list="Listado de Colonia"
        @table="table"
        :editButton="{ modal: 'crudcolony' }"
        :select_filter="filters"
    ></Datatable>

    <div
        class="modal fade"
        id="crudcolony"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <ColonyCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></ColonyCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import ColonyCrud from "./ColonyCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "ColonyListar",
    components: { Datatable, ColonyCrud },
    props: {
        filters: String,
    },
    setup(props) {
        const title = ref("Crear Colonia");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/administracion/colonia/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudcolony").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Colonia";
            action.value = "/administracion/colonia/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudcolony").modal("show");
            title.value = "Editar Colonia";
            action.value = `/administracion/colonia/update/${idItem}`;
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
