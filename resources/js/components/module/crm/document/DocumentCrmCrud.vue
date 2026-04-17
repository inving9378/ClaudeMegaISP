<template>
    <div v-if="hasPermission.data.canView('crm_document_view_crm')">
        <Datatable
            idTable="documenttable"
            :id="id"
            module="crm/document"
            model="DocumentCrm"
            :buttons="getButtonDatatable()"
            :editButton="{ modal: 'modalDocument' }"
            @documenttable="documentTable"
            :add="null"
        />

        <UploadDocumentCrm
            :module="'crm/document'"
            :action="actionUploadDocument"
            :idCrm="id"
            @cleanModal="cleanModal"
        />
        <CrmTemplate
            :module="'crm/document'"
            :idCrm="id"
            @cleanModal="cleanModal"
        />
    </div>
</template>

<script>
import Modal from "../../../../helpers/modal";
import Permission from "../../../../helpers/Permission";
import DatatableHelper from "../../../../helpers/datatableHelper";
import Datatable from "../../../base/shared/Datatable";
import UploadDocumentCrm from "./UploadDocumentCrm";
import {reactive, ref, onMounted, watch, onBeforeMount} from "vue";
import {allViewHasPermission} from '../../../../helpers/Request';
import CrmTemplate from "./CrmTemplate.vue";

export default {
    name: "DocumentCrmCrud",
    props: {
        id: {
            type: String,
            default: null,
        },
        editModal: Object,
    },
    components: {
        Datatable,
        UploadDocumentCrm,
        CrmTemplate
    },
    setup(props) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = reactive({
            data: {}
        })
        const actionUploadDocument = ref(`add/${props.id}`);
        const hasPermission = reactive({
            data: new Permission({})
        })

        watch(
            () => props.editModal,
            (data, dataBefore) => {
                if (data.value && data.toggleModal == 'modalDocument'){
                    showEditModal(data.key);
                }
            }
        );

        onBeforeMount(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            modal.data = new Modal("modalDocument");
            if (hasPermission.data.canView('crm_document_add')){
                $(document).on("click", "#buttonmodaluploaddocument", function () {
                    showAddModal();
                });
            }
        });

        const cleanModal = () => {
            modal.data.hide();
            actionUploadDocument.value = `add/${props.id}`;
            datatable.table.reload();
        };

        const documentTable = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const showAddModal = () => {
            actionUploadDocument.value = `add/${props.id}`;
            modal.data.show();
        }

        const showEditModal = (id) => {
            actionUploadDocument.value = `update/${id}`;
            modal.data.show();
        };

        const getButtonDatatable = () => {
            let buttons = {};
            if (hasPermission.data.canView('crm_document_add')){
                buttons.upload = {
                    class: 'btn btn-outline-info waves-effect waves-light me-2',
                    iclass: 'fa fa-plus',
                    href: 'javascript:void(0)',
                    id: 'buttonmodaluploaddocument'
                };
            }

            buttons.contract = {
                class: "btn btn-outline-info waves-effect waves-light me-2",
                iclass: "fa fa-user",
                href: `javascript:void(0)`,
                id: "generateContract",
            };
            return buttons;
        }

        return {
            modal,
            cleanModal,
            documentTable,
            datatable,
            actionUploadDocument,
            getButtonDatatable,
            hasPermission
        };
    },
};
</script>

<style scoped></style>
