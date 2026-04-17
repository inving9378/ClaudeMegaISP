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
            data-bs-target="#crudmunicipality"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="administracion/municipio"
        model="Municipality"
        list="Listado de Municipio"
        @table="table"
        :editButton="{ modal: 'crudmunicipality' }"
        :select_filter="filters"
    ></Datatable>

    <div
        class="modal fade"
        id="crudmunicipality"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <MunicipalityCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></MunicipalityCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import MunicipalityCrud from "./MunicipalityCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "MunicipalityListar",
    components: { Datatable, MunicipalityCrud },
    props: {
        filters: String,
    },
    setup(props) {
        const title = ref("Crear Municipio");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/administracion/municipio/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudmunicipality").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Municipio";
            action.value = "/administracion/municipio/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudmunicipality").modal("show");
            title.value = "Editar Municipio";
            action.value = `/administracion/municipio/update/${idItem}`;
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
