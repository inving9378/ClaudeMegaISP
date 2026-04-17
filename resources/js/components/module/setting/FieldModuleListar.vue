<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <Select-Module :modules="modules" @module-id="getModuleId">
        </Select-Module>
        <button type="button" class="btn" @click="reload">
            <i data-feather="rotate-cw" @click="reload"></i>
        </button>
        <button
            type="button"
            class="btn btn-outline-primary waves-effect waves-light"
            @click="showAddModal"
        >
            Agregar
        </button>
    </div>
    <Datatable
        module="configuracion/additional-fields"
        model="FieldModule"
        list="Listado"
        @table="table"
        :editButton="{ modal: 'crudEditFieldModule' }"
    ></Datatable>

    <Crud-Field-Module
        @close-modal="closeModal"
        :action="action"
        :title="title"
        :module="module"
        :key="moduleId"
        :moduleId="moduleId"
    >
    </Crud-Field-Module>

    <MessageResponse
        :message="validationMessage"
        :key="renderMessage"
    ></MessageResponse>
</template>

<script>
import Datatable from "../../base/shared/Datatable";
import { onMounted, reactive, ref } from "vue";
import DatatableHelper from "../../../helpers/datatableHelper";
import SelectModule from "./components/SelectModule.vue";
import { resetDatatable } from "../../../helpers/filters";
import ModalCentrado from "../../../shared/ModalCentrado.vue";
import Modal from "../../../helpers/modal";
import CrudFieldModule from "./components/CrudFieldModule.vue";
import MessageResponse from "../../base/utils/MessageResponse.vue";

export default {
    name: "FieldModuleListar",
    components: {
        Datatable,
        SelectModule,
        ModalCentrado,
        CrudFieldModule,
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
        const action = ref("/configuracion/additional-fields/add");
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
            if (moduleId.value) {
                modal.value.show();
            } else {
                const nuevoMensaje = obtenerMensaje();
                validationMessage.value = nuevoMensaje;
            }
        };

        const obtenerMensaje = () => {
            renderMessage.value = !renderMessage.value;
            return "Debe seleccionar algun modulo";
        };

        const closeModal = () => {
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear";
            action.value = "/configuracion/additional-fields/add";
            datatable.table.reload();
            modal.value.hide();
        };

        const showEditModal = (idItem) => {
            modal.value.show();
            title.value = "Editar";
            action.value = `/configuracion/additional-fields/update/${idItem}`;
        };

        const reload = () => {
            datatable.table.reload();
            resetDatatable.value = true;
            moduleId.value = null
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const getModuleId = (obj) => {
            moduleId.value = obj;
        };

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            showAddModal,
            getModuleId,
            moduleId,
            validationMessage,
            renderMessage
        };
    },
};
</script>

<style scoped></style>
