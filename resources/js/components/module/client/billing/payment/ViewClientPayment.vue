<template>
    <Datatable
        :id="id"
        idTable="clientbillingtable"
        module="cliente/billing/payment"
        model="ClientPayment"
        list="Listado de Pagos"
        :buttons="{
            upload: {
                class: 'btn btn-outline-info waves-effect waves-light',
                iclass: 'fa fa-plus',
                href: 'javascript:void(0)',
                id: 'buttonmodalpayment',
            },
        }"
        :editButton="{ modal: 'modalpayment' }"
        @clientbillingtable="table"
        :add="null"
        :cssCard="false"
        @item-delete="itemDelete"
    />

   <ViewTotalPayment :idClient="id" :payments="payment" />

    <ClientCrudPayment
        v-if="showCrud"
        :module="'cliente/billing/payment'"
        :action="action"
        :idClient="id"
        @cleanModal="cleanModal"
    />
</template>

<script>
import DatatableHelper from "../../../../../helpers/datatableHelper";
import { onMounted, reactive, ref, watch,nextTick } from "vue";
import Datatable from "../../../../base/shared/Datatable";
import Modal from "../../../../../helpers/modal";
import ClientCrudPayment from "./ClientCrudPayment";
import ViewTotalPayment from "./ViewTotalPayment";
import {payments} from "../helpers/request";

export default {
    name: "ViewClientPayment",
    props: {
        id: {
            type: String,
            default: null,
        },
        editModal: Object,
    },
    components: { Datatable, ClientCrudPayment, ViewTotalPayment },
    setup(props) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = ref();
        const action = ref(`crear/${props.id}`);
        const payment = ref([]);
        const showCrud = ref(false);

        watch(
            () => props.editModal,
            (data, dataBefore) => {
                if (data.value && data.toggleModal == "modalpayment") {
                    showEditModal(data.key);
                }
            }
        );

        onMounted(async () => {
            modal.value = new Modal("modalpayment");
            payment.value = await payments(props.id);

            $(document).on("click", "#buttonmodalpayment", function () {
                showAddModal();
            });
        });

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const cleanModal = async () => {
            modal.value.hide();
            showCrud.value = false;
            action.value = `crear/${props.id}`;
            datatable.table.reload();
            payment.value = await payments(props.id);
        };

        const itemDelete = async () => {
            payment.value = await payments(props.id);
        }

        const showAddModal = () => {
            action.value = `crear/${props.id}`;
            showCrud.value = true;

            nextTick(() => {
                modal.value.show();
            });
        };


        const showEditModal = (idItem) => {
            action.value = `update/${idItem}`;
            showCrud.value = true;

            nextTick(() => {
                modal.value.show();
            });
        };

        return {
            modal,
            cleanModal,
            table,
            datatable,
            action,
            payment,
            itemDelete,
            showCrud
        };
    },
};
</script>

<style scoped></style>
