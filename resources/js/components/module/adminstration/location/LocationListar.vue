<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudlocation">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/ubicacion"
        model="Location"
        list="Listado de Ubicaciones"
        @table="table"
        :editButton="{ modal: 'crudlocation' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudlocation"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <LocationCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></LocationCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import LocationCrud from "./LocationCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "LocationListar",
    components: {Datatable, LocationCrud},
    setup(props) {
        const title = ref('Crear Ubicacion');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/ubicacion/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudlocation').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Ubicacion';
            action.value = '/administracion/ubicacion/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudlocation').modal('show');
            title.value = 'Editar Ubicacion';
            action.value = `/administracion/ubicacion/update/${idItem}`;
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
