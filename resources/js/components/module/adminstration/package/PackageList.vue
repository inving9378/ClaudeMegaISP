<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudpackage">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/paquete"
        model="Package"
        list="Listado de Paquetes"
        @table="table"
        :editButton="{ modal: 'crudpackage' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudpackage"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <PackageCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></PackageCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import PackageCrud from "./PackageCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "PackageList",
    components: {Datatable, PackageCrud },
    setup(props) {
        const title = ref('Crear Paquete');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/package/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudpackage').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Paquete';
            action.value = '/administracion/package/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudpackage').modal('show');
            title.value = 'Editar Paquete';
            action.value = `/administracion/package/update/${idItem}`;
        };

        const reload = () => {
            datatable.table.reload();
        }

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        }

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud
        };
    }
}
</script>

<style scoped>

</style>
