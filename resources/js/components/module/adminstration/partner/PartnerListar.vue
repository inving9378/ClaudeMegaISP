<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudsocio">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/socios"
        model="Partner"
        list="Listado de Socios"
        @table="table"
        :editButton="{ modal: 'crudsocio' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudsocio"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <PartnerCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></PartnerCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import PartnerCrud from "./PartnerCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "SocioListar",
    components: {Datatable, PartnerCrud},
    setup(props) {
        const title = ref('Crear Socio');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/socios/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudsocio').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Socio';
            action.value = '/administracion/socios/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudsocio').modal('show');
            title.value = 'Editar Socio';
            action.value = `/administracion/socios/update/${idItem}`;
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
