<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">Refrescar</button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudmethodofpayment">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/metotdo-de-pago"
        model="MethodOfPayment"
        list="Listado de Metodos de Pago"
        @table="table"
        :editButton="{ modal: 'crudmethodofpayment' }"
    ></Datatable>

    <div
        class="modal fade"
        id="crudmethodofpayment"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <MethodOfPaymentCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></MethodOfPaymentCrud>
            </div>
        </div>
    </div>
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import MethodOfPaymentCrud from "./MethodOfPaymentCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";

export default {
    name: "MethodOfPaymentListar",
    components: {Datatable,MethodOfPaymentCrud},
    setup(props) {
        const title = ref('Crear Método de Pago');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/metotdo-de-pago/add');
        const reloadCrud = ref(true);

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        })

        const closeModal = () => {
            $('#crudmethodofpayment').modal('hide');
            reloadCrud.value = !reloadCrud.value;
            title.value = 'Crear Método de Pago';
            action.value = '/administracion/metotdo-de-pago/add';
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            $('#crudmethodofpayment').modal('show');
            title.value = 'Editar Método de Pago';
            action.value = `/administracion/metotdo-de-pago/update/${idItem}`;
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
