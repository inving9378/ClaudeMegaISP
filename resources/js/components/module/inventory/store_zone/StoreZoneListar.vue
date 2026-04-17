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
            data-bs-target="#modalcrudstore_zone"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="inventory/store_zone"
        model="StoreZone"
        list="Listado de Zonas"
        @table="table"
        :editButton="{ modal: 'modalcrudstore_zone' }"
        :persistentFilters="filterPersistent"
    ></Datatable>

    <div
        class="modal fade"
        id="modalcrudstore_zone"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <StoreZoneCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></StoreZoneCrud>
            </div>
        </div>
    </div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable.vue";
import StoreZoneCrud from "./StoreZoneCrud";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "InventoryStoreListar",
    components: { Datatable, StoreZoneCrud },
    props: {
        persistent_filters: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const title = ref("Crear Zona");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/inventory/store_zone/add");
        const reloadCrud = ref(true);
        const filterPersistent = ref({});

        onMounted(() => {
            if (props.persistent_filters) {
                filterPersistent.value = JSON.parse(props.persistent_filters);
            }
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            $("#modalcrudstore_zone").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Zona";
            action.value = "/inventory/store_zone/add";
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $("#modalcrudstore_zone").modal("show");
            title.value = "Editar Zona";
            action.value = `/inventory/store_zone/update/${idItem}`;
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
            filterPersistent,
        };
    },
};
</script>

<style scoped></style>
