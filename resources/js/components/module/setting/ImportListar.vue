<template>
    <Datatable
        module="configuracion/tools-import"
        model="SettingToolsImport"
        list="Listado"
        @table="table"
        :editButton="{ modal: 'crudEditFieldModule' }"
        add="Importar"
    ></Datatable>

    <MessageResponse
        :message="validationMessage"
        :key="renderMessage"
    ></MessageResponse>
</template>

<script>
import Datatable from "../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../helpers/datatableHelper";
import { resetDatatable } from "../../../helpers/filters";
import ModalCentrado from "../../../shared/ModalCentrado.vue";
import Modal from "../../../helpers/modal";
import CrudFieldModule from "./components/CrudFieldModule.vue";
import MessageResponse from "../../base/utils/MessageResponse.vue";

export default {
    name: "ImportListar",
    components: {
        Datatable,
        ModalCentrado,
        MessageResponse,
    },
    props: {
        modules: String,
        module: String,
    },
    setup(props) {
        const title = ref("Crear");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/configuracion/tools-import/add");
        const reloadCrud = ref(true);
        const modal = ref();

        const validationMessage = ref("");
        const renderMessage = ref(false);

        const moduleId = ref();

        onMounted(() => {
            modal.value = new Modal("crudEditFieldModule");
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const showAddModal = async () => {
            modal.value.show();
            title.value = "Crear";
        };

        const closeModal = () => {
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear";
            action.value = "/configuracion/tools-import/add";
            datatable.table.reload();
            modal.value.hide();
        };

        const showEditModal = (idItem) => {
            modal.value.show();
            title.value = "Editar";
            action.value = `/configuracion/tools-import/update/${idItem}`;
        };

        const reload = () => {
            datatable.table.reload();
            resetDatatable.value = true;
            moduleId.value = null;
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            showAddModal,
            moduleId,
            validationMessage,
            renderMessage,
        };
    },
};
</script>

<style scoped></style>
