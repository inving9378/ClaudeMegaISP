<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <a href="/administracion/user/crear?role=vendedor" class="btn btn-success waves-effect waves-light ms-auto">
                    Agregar Vendedor
                </a>
    </div>
    <Datatable
        module="sellers/seller"
        model="Seller"
        list="Listado de Vendedores"
        @table="table"
    ></Datatable>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "SellerListar",
    components: { Datatable },
    props: {
    },
    setup(props) {
        const title = ref("Crear Vendedor");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/sellers/seller/add");
        const reloadCrud = ref(true);

        onMounted(() => {
           /*  $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            }); */
        });

        const closeModal = () => {
            $("#crudinventoryitemtype").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Vendedor";
            action.value = "/sellers/seller/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#crudinventoryitemtype").modal("show");
            title.value = "Editar Vendedor";
            action.value = `/sellers/seller/update/${idItem}`;
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
