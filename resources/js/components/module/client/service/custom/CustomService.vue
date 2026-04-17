<template>
    <div class="col-12" v-if="loadDatatable && allService.Custom">
        <div class="card">
            <Datatable
                idTable="customservicetable"
                module="cliente/clientcustomservice"
                model="ClientCustomService"
                list="Listado de Servicios Custom"
                :buttons="getButtonDatatable()"
                :editButton="{ modal: 'modalcustomservice' }"
                @customservicetable="documentTable"
                :add="null"
                :cssCard="false"
                :id="idClient"
            />
        </div>
    </div>
    <ClientCrudCustomService
        :module="'cliente/clientcustomservice'"
        :action="actionCrudCustomService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
    <ChangeCustomService
        :module="'cliente/clientcustomservice'"
        :action="actionCrudCustomService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
</template>

<script>
import Datatable from "../../../../base/shared/Datatable";
import { onBeforeMount, onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Modal from "../../../../../helpers/modal";
import ClientCrudCustomService from "./ClientCrudCustomService";
import Permission from "../../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../../helpers/Request";
import { hasService } from "../../helpers/helper";
import ChangeCustomService from "./ChangeCustomService.vue";

export default {
    name: "CustomService",
    props: {
        idClient: {
            type: String,
            default: null,
        },
        editModal: Object,
        showAddService: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["resetShowAddService"],
    components: {
        Datatable,
        ClientCrudCustomService,
        ChangeCustomService,
    },
    setup(props, { emit }) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = ref();
        const actionCrudCustomService = ref(`crear/${props.idClient}`);
        const render = ref(1);

        const loadDatatable = ref(false);
        const hasPermission = reactive({
            data: new Permission({}),
        });
        const allService = reactive({
            Custom: false,
        });

        watch(
            () => props.showAddService,
            (data, dataBefore) => {
                if (data) {
                    showAddModal();
                }
            }
        );

        watch(
            () => props.editModal,
            (data, dataBefore) => {
                if (data.value && data.toggleModal == "modalcustomservice") {
                    showEditModal(data.key);
                }
            }
        );

        onBeforeMount(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            modal.value = new Modal("modalcustomservice");

            $(document).on("click", "#buttonmodalcustomservice", function () {
                showAddModal();
            });

            $(document).on("click", `#change_tarif_custom`, function (e) {
                showModalChangeTarif($(e.target).parent().attr("id-item"));
            });

            allService.Custom = await hasService(
                props.idClient,
                "custom_service"
            );
            //TODO documentar Para Cargue el datable despues de inicializar las variables
            loadDatatable.value = true;
        });

        const cleanModal = async () => {
            allService.Custom = await hasService(
                props.idClient,
                "custom_service"
            );
            emit("resetShowAddService", "custom");
            modal.value.hide();
            $("#modalcustomserviceChange").modal("hide");
            render.value++;
            actionCrudCustomService.value = `crear/${props.idClient}`;
            if (datatable.table) datatable.table.reload();
        };

        const documentTable = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const showAddModal = () => {
            actionCrudCustomService.value = `crear/${props.idClient}`;
            modal.value.show();
        };

        const showEditModal = (idItem) => {
            actionCrudCustomService.value = `update/${idItem}`;
            modal.value.show();
        };

        const showModalChangeTarif = (idItem) => {
            actionCrudCustomService.value = `update/${idItem}`;
            $("#modalcustomserviceChange").modal("show");
        };

        const getButtonDatatable = () => {
            let buttons = {};
            if (hasPermission.data.canView("client_service_custom_add")) {
                buttons.upload = {
                    class: "btn btn-outline-info waves-effect waves-light",
                    iclass: "fa fa-plus",
                    href: "javascript:void(0)",
                    id: "buttonmodalcustomservice",
                };
            }
            return buttons;
        };

        return {
            modal,
            cleanModal,
            documentTable,
            datatable,
            actionCrudCustomService,
            render,
            getButtonDatatable,
            hasPermission,
            loadDatatable,
            allService,
        };
    },
};
</script>

<style scoped></style>
