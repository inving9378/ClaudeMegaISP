<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="m-0 fw-bold text-secondary">Catálogo de Precios</h6>
            <button class="btn btn-primary btn-sm" @click="showAddModal">
                <i class="uil uil-plus me-1"></i> Agregar Producto
            </button>
        </div>

        <Datatable
            :module="`inventory/supplier/${supplierId}/product-prices`"
            model="SupplierProductPrice"
            list="Catálogo de Precios del Proveedor"
            :filters="{ supplier_id: [supplierId] }"
            @table="table"
            :editButton="{ modal: 'crudsupplier_productprice' }"
        />
    </div>

    <!-- Modal CRUD -->
    <div
        class="modal fade"
        id="crudsupplier_productprice"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        tabindex="-1"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <SupplierProductPricesCrud
                    :action="action"
                    :id="editId"
                    :supplierId="supplierId"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                    @supplier-product-price-saved="onSaved"
                />
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../../base/shared/Datatable.vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import SupplierProductPricesCrud from "./SupplierProductPricesCrud.vue";
import Swal from "sweetalert2";
import { onMounted, reactive, ref } from "vue";

export default {
    name: "SupplierProducts",
    components: { Datatable, SupplierProductPricesCrud },
    props: {
        supplierId: {
            type: [Number, String],
            required: true,
        },
        prices: {
            type: Array,
            default: () => [],
        },
    },
    setup(props) {
        const title = ref("Agregar Producto al Catálogo");
        const action = ref(
            `/inventory/supplier/${props.supplierId}/product-prices/add`
        );
        const editId = ref(null);
        const reloadCrud = ref(true);

        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        const showAddModal = () => {
            editId.value = null;
            reloadCrud.value = !reloadCrud.value;
            title.value = "Agregar Producto al Catálogo";
            action.value = `/inventory/supplier/${props.supplierId}/product-prices/add`;
            $("#crudsupplier_productprice").modal("show");
        };

        const showEditModal = (idItem) => {
            editId.value = String(idItem);
            reloadCrud.value = !reloadCrud.value;
            title.value = "Editar Precio";
            action.value = `/inventory/supplier/${props.supplierId}/product-prices/update/${idItem}`;
            $("#crudsupplier_productprice").modal("show");
        };

        const onSaved = () => {
            closeModal();
            datatable.table.reload();
        };

        const closeModal = () => {
            $("#crudsupplier_productprice").modal("hide");
            editId.value = null;
            reloadCrud.value = !reloadCrud.value;
            title.value = "Agregar Producto al Catálogo";
            action.value = `/inventory/supplier/${props.supplierId}/product-prices/add`;
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const deletePrice = (idItem) => {
            Swal.fire({
                title: "¿Está seguro que desea eliminar?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        await axios.post(
                            `/inventory/supplier/${props.supplierId}/product-prices/destroy/${idItem}`
                        );
                        Swal.fire(
                            "Eliminado",
                            "Precio eliminado correctamente.",
                            "success"
                        );
                        datatable.table.reload();
                    } catch (error) {
                        Swal.fire(
                            "Error",
                            "No se pudo eliminar el precio.",
                            "error"
                        );
                    }
                }
            });
        };

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function (e) {
                e.stopPropagation();
                const idItem = $(this).attr("id-item");
                showEditModal(idItem);
            });

            $(document).on("click", ".btn-delete-price", function (e) {
                e.stopPropagation();
                e.preventDefault();
                const idItem = $(this).attr("id-item");
                if (idItem) deletePrice(idItem);
            });

            $(document).on("click", "#table-datatable tbody tr", function (e) {
                if (
                    $(e.target).closest(
                        "a, button, input[type='checkbox'], .fa-trash, .uil-pen-modal"
                    ).length
                )
                    return;
            });
        });

        return {
            title,
            action,
            editId,
            reloadCrud,
            datatable,
            showAddModal,
            showEditModal,
            onSaved,
            closeModal,
            table,
            deletePrice,
            supplierId: props.supplierId,
        };
    },
};
</script>
