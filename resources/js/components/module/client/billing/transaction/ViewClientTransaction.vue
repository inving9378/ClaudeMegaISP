<template>
    <Datatable
        :id="id"
        idTable="clienttransactiontable"
        module="cliente/billing/transaction"
        model="ClientTransaction"
        list="Listado de Transactions"
        :buttons="{
            reload_transaction_table: {
                class: 'btn btn-outline-info waves-effect waves-light',
                iclass: 'fas fa-history',
                href: 'javascript:void(0)',
                id: 'button-reload-table-transaction',
            },
            upload: {
                class: 'btn btn-outline-info waves-effect waves-light ms-1',
                iclass: 'fa fa-plus',
                href: 'javascript:void(0)',
                id: 'buttonmodaltransaction',
            }
        }"
        :editButton="{ modal: 'modaltransaction' }"
        :add="null"
        :cssCard="false"
        @item-delete="itemDelete"
    />

    <ViewTotalTransaction :idClient="id" :transactions="transaction" />

    <ClientCrudTransaction
        :module="'cliente/billing/transaction'"
        :action="action"
        :idClient="id"
        @cleanModal="cleanModal"
    />
</template>

<script>
import DatatableHelper from "../../../../../helpers/datatableHelper";
import { onMounted, reactive, ref, watch } from "vue";
import Datatable from "../../../../base/shared/Datatable";
import Modal from "../../../../../helpers/modal";
import ClientCrudTransaction from "./ClientCrudTransaction";
import ViewTotalTransaction from "./ViewTotalTransaction";
import {transactions} from "../helpers/request";

export default {
    name: "ViewClientPayment",
    props: {
        id: {
            type: String,
            default: null,
        },
        editModal: Object,
    },
    components: { Datatable, ClientCrudTransaction, ViewTotalTransaction },
    setup(props) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = ref();
        const action = ref(`crear/${props.id}`);
        const transaction = ref([]);

        watch(
            () => props.editModal,
            (data, dataBefore) => {
                if (data.value && data.toggleModal == "modaltransaction") {
                    showEditModal(data.key);
                }
            }
        );

        onMounted(async () => {
            modal.value = new Modal("modaltransaction");
            transaction.value = await transactions(props.id);

            $(document).on("click", "#buttonmodaltransaction", function () {
                showAddModal();
            });

            $(document).on("click", "#button-reload-table-transaction", function () {
                datatable.table.reload();
            });
        });

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const cleanModal = async () => {
            modal.value.hide();
            action.value = `crear/${props.id}`;
            datatable.table.reload();
            transaction.value = await transactions(props.id);
        };

        const itemDelete = async () => {
            transaction.value = await transactions(props.id);
        };

        const showAddModal = () => {
            action.value = `crear/${props.id}`;
            modal.value.show();
        };

        const showEditModal = (idItem) => {
            action.value = `update/${idItem}`;
            modal.value.show();
        };

        return {
            modal,
            cleanModal,
            table,
            datatable,
            action,
            transaction,
            itemDelete
        };
    },
};
</script>

<style scoped></style>
