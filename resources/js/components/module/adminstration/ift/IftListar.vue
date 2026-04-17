<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudift">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/ift"
        model="Ift"
        list="Listado de Ift"
        @table="table"
        :editButton="{ modal: 'crudift' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudift"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <IftCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></IftCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import IftCrud from "./IftCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "IftListar",
    components: {Datatable,IftCrud},
    setup(props) {
        const title = ref('Crear Ift');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/ift/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudift').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Ift';
            action.value = '/administracion/ift/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudift').modal('show');
            title.value = 'Editar Ift';
            action.value = `/administracion/ift/update/${idItem}`;
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
