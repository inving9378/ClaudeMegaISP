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
            data-bs-target="#crudlocation"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="administracion/sucursal"
        model="Sucursal"
        list="Listado de sucursales"
        @table="table"
        :editButton="{ modal: 'crudlocation' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudlocation"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <sucursal-crud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import SucursalCrud from "./SucursalCrud.vue";
const title = ref("Crear sucursal");
const datatable = reactive({
    table: new DatatableHelper({}),
});
const action = ref("/administracion/sucursal/add");
const reloadCrud = ref(true);

onMounted(() => {
    $(document).on("click", ".uil-pen-modal", function () {
        let idItem = $(this).parent().attr("id-item");
        let modal = $(this).parent().attr("toggle-modal");
        showEditModal(idItem, modal);
    });
});

const closeModal = (reload) => {
    $("#crudlocation").modal("hide");
    reloadCrud.value = !reloadCrud.value;
    title.value = "Crear sucursal";
    action.value = "/administracion/sucursal/add";
    if (reload) {
        datatable.table.reload();
    }
};

const showEditModal = (idItem) => {
    $("#crudlocation").modal("show");
    title.value = "Editar sucursal";
    action.value = `/administracion/sucursal/update/${idItem}`;
};

const reload = () => {
    datatable.table.reload();
};

const table = (refTable) => {
    datatable.table = new DatatableHelper(refTable);
};
</script>

<style scoped></style>
