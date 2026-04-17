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
            data-bs-target="#crudinventorystore"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="inventory/inventory_store"
        model="InventoryStore"
        list="Listado de Almacenes"
        @table="table"
        :editButton="{ modal: 'crudinventorystore' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudinventorystore"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <InventoryStoreCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></InventoryStoreCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import InventoryStoreCrud from "./InventoryStoreCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "InventoryStoreListar",
    components: { Datatable, InventoryStoreCrud },
    props: {
    },
    setup(props) {
        const title = ref("Crear Almacen");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/inventory/inventory_store/add");
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#crudinventorystore").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Almacen";
            action.value = "/inventory/inventory_store/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudinventorystore").modal("show");
            title.value = "Editar Almacen";
            action.value = `/inventory/inventory_store/update/${idItem}`;
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
