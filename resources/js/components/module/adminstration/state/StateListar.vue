<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudstate">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/estado"
        model="State"
        list="Listado de Estados"
        @table="table"
        :editButton="{ modal: 'crudstate' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudstate"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <StateCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></StateCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import StateCrud from "./StateCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "StateListar",
    components: {Datatable,StateCrud},
    setup(props) {
        const title = ref('Crear Estado');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/estado/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudstate').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Estado';
            action.value = '/administracion/estado/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudstate').modal('show');
            title.value = 'Editar State';
            action.value = `/administracion/estado/update/${idItem}`;
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
