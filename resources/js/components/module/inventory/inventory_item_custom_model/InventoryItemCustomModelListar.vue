<template>
    <div class="d-flex flex-wrap justify-between gap-2 mb-2">
        <div v-if="
            hasPermission.data.canView(
                `inventory_item_custom_model_add_inventory_item_custom_model`
            )
        ">
            <button type="button" class="btn btn-outline-primary waves-effect waves-light" data-bs-toggle="modal"
                data-bs-target="#crudInventoryItemCustomModel">
                Agregar
            </button>
        </div>
    </div>
    <Datatable module="inventory/inventory_item_custom_model" model="InventoryItemCustomModel"
        list="Listado de Articulos" @table="table"></Datatable>

    <div class="modal fade" id="crudInventoryItemCustomModel" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <InventoryItemCustomModelCrud :action="action" :key="reloadCrud" @close-modal="closeModal">
                </InventoryItemCustomModelCrud>
            </div>
        </div>
    </div>

</template>

<script>
import Datatable from "../../../base/shared/Datatable.vue";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import InventoryItemCustomModelCrud from "./InventoryItemCustomModelCrud.vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "InventoryItemCustomModelListar",
    components: {
        Datatable,
        InventoryItemCustomModelCrud
    },
    props: {
        module_id: Number,
        url_base: String
    },
    setup(props) {
        const title = ref("Crear Tipo de Articulo");
        const action = ref("/inventory/inventory_item_custom_model/add");
        const reloadCrud = ref(true);
        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const hasPermission = reactive({
            data: new Permission({}),
        });


        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                showEditModal(idItem);
            });
        });

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const closeModal = () => {
            $("#crudInventoryItemCustomModel").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Tipo de Articulo";
            action.value = "/inventory/inventory_item_custom_model/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudInventoryItemCustomModel").modal("show");
            title.value = "Editar Modelo de Articulo";
            action.value = `/inventory/inventory_item_custom_model/update/${idItem}`;
        };


        return {
            table,
            reload,
            closeModal,
            showEditModal,
            reloadCrud,
            action,
            title,
            hasPermission
        };
    },
};
</script>

<style scoped></style>
