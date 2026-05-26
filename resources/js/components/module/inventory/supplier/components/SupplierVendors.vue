<template>
    <div>
        <div class="d-flex justify-content-end mb-3">
            <a
                :href="`/inventory/supplier/${supplierId}/vendors/create`"
                class="btn btn-outline-primary waves-effect waves-light"
            >
                <i class="fas fa-plus me-1"></i> Agregar Vendedor
            </a>
        </div>

        <Datatable
            :module="`inventory/supplier/${supplierId}`"
            model="SupplierVendor"
            list="Listado de Vendedores"
            :filters="{ supplier_id: [supplierId] }"
            @table="table"
            :editButton="{ modal: 'crudsupplier_vendor' }"
        />
    </div>
    <div
        class="modal fade"
        id="crudsupplier_vendor"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <SupplierVendorsCrud
                    :action="action"
                    :id="editId"
                    :supplierId="supplierId"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                    @supplier-vendor-saved="onSaved"
                />
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../../base/shared/Datatable.vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import { onMounted, reactive, ref } from "vue";
import SupplierVendorsCrud from "./SupplierVendorsCrud.vue";
import Swal from "sweetalert2";
import axios from "axios";

export default {
    name: "SupplierVendors",
    components: { Datatable, SupplierVendorsCrud },
    props: {
        supplierId: {
            type: [Number, String],
            required: true,
        },
    },
    setup(props) {
        const title = ref("Crear Vendedor");
        const action = ref(`/inventory/supplier/${props.supplierId}/vendors/add`);
        const editId = ref(null);
        const reloadCrud = ref(true);

        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function (e) {
                e.stopPropagation();
                const idItem = $(this).attr("id-item");
                showEditModal(idItem);
            });

            $(document).on("click", ".btn-delete-item", async function (e) {
                e.stopPropagation();
                e.preventDefault();
                
                const idItem = $(this).attr("id-item");
                
                if (!idItem) {
                    console.error("No se encontró id-item");
                    return;
                }

                await deleteVendor(idItem);
            });

            $(document).on("click", "#table-datatable tbody tr", function (e) {
                if (
                    $(e.target).closest(
                        "a, button, input[type='checkbox'], .uil-pen-modal, .btn-delete-item"
                    ).length
                ) return;
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
            title.value = "Editar Vendedor";
            action.value = `/inventory/supplier/${props.supplierId}/vendors/update/${idItem}`;
            $("#crudsupplier_vendor").modal("show");
        };

        const deleteVendor = async (idItem) => {
            const result = await Swal.fire({
                title: '¿Eliminar vendedor?',
                text: `ID: ${idItem} - Esta acción no se puede deshacer`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            });

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Eliminando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const url = `/inventory/supplier/${props.supplierId}/vendors/destroy/${idItem}`;

                const response = await axios.post(url, {}, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                });

                Swal.close();

                await Swal.fire({
                    title: '¡Eliminado!',
                    text: response.data.message || 'Vendedor eliminado correctamente',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: true
                });

                datatable.table.reload();

            } catch (error) {
                console.error("Error en deleteVendor:", error);

                Swal.fire({
                    title: 'Error',
                    text: error.response?.data?.message || 'No se pudo eliminar el vendedor',
                    icon: 'error'
                });
            }
        };

        const closeModal = () => {
            $("#crudsupplier_vendor").modal("hide");
            editId.value = null;
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Vendedor";
            action.value = `/inventory/supplier/${props.supplierId}/vendors/add`;
        };

        const onSaved = () => {
            closeModal();
            datatable.table.reload();
        };

        const reload = () => datatable.table.reload();
        
        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        return {
            title,
            action,
            editId,
            reloadCrud,
            closeModal,
            onSaved,
            reload,
            table,
            supplierId: props.supplierId,
        };
    },
};
</script>