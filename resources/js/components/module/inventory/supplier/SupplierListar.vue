<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light ms-auto"
            @click="reload"
        >
            Refrescar
        </button>
        <a
            href="/inventory/supplier/create"
            class="btn btn-outline-primary waves-effect waves-light"
        >
            Agregar Proveedor
        </a>
    </div>

    <Datatable
        module="inventory/supplier"
        model="Supplier"
        list="Listado de Proveedores"
        @table="table"
        :editButton="{ modal: 'crudsupplier' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudsupplier"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <SupplierCrud
                    :action="action"
                    :id="editId"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></SupplierCrud>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import Datatable from "../../../base/shared/Datatable.vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import SupplierCrud from "./SupplierCrud.vue";

export default {
    name: "SupplierListar",
    components: { Datatable, SupplierCrud },
    setup() {
        const title = ref("Crear Proveedor");
        const action = ref("/inventory/supplier/add");
        const editId = ref(null);
        const reloadCrud = ref(true);
        const datatable = reactive({ table: new DatatableHelper({}) });

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                const idItem = $(this).attr("id-item");
                showEditModal(idItem);
            });

            $(document).on("click", "#table-datatable tbody tr", function (e) {
                if (
                    $(e.target).closest("a, button, input[type='checkbox']")
                        .length
                )
                    return;
                const id = $(this).find("td:first").text().trim();
                if (id) window.location.href = `/inventory/supplier/show/${id}`;
            });

            $(document).on(
                "mouseenter",
                "#table-datatable tbody tr",
                function () {
                    $(this).css("cursor", "pointer");
                }
            );
        });

        const showEditModal = (idItem) => {
            editId.value = String(idItem);
            reloadCrud.value = !reloadCrud.value;
            title.value = "Editar Proveedor";
            action.value = `/inventory/supplier/update/${idItem}`;
            $("#crudsupplier").modal("show");
        };

        const closeModal = () => {
            $("#crudsupplier").modal("hide");
            editId.value = null;
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Proveedor";
            action.value = "/inventory/supplier/add";
            datatable.table.reload();
        };

        const reload = () => datatable.table.reload();
        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        return { title, action, editId, reloadCrud, closeModal, reload, table };
    },
};
</script>
