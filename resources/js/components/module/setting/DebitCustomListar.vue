<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#cruddebitcustom">Agregar
        </button>
    </div>
    <Datatable
        module="configuracion/debitcustom"
        model="SettingDebtPaymentClientCustom"
        list="Listado"
        @table="table"
        :editButton="{ modal: 'cruddebitcustom' }"
    ></Datatable>

    <div
        class="modal fade"
        id="cruddebitcustom"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <DebitCustomCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></DebitCustomCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../base/shared/Datatable";
import DebitCustomCrud from "./DebitCustomCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../helpers/datatableHelper";

export default {
    name: "DebitCustomListar",
    components: {Datatable,DebitCustomCrud},
    setup(props) {
        const title = ref('Crear');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/configuracion/debitcustom/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#cruddebitcustom').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear';
            action.value = '/configuracion/debitcustom/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#cruddebitcustom').modal('show');
            title.value = 'Editar';
            action.value = `/configuracion/debitcustom/update/${idItem}`;
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
