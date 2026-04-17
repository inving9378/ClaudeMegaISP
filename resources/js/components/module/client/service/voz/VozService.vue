<template>
    <div class="col-12" v-if="loadDatatable && allService.Voz">
        <div class="card">
            <Datatable
                idTable="vozservicetable"
                module="cliente/clientvozservice"
                model="ClientVozService"
                list="Listado de Servicios de Voz"
                :buttons="getButtonDatatable()"
                :editButton="{ modal: 'modalvozservice' }"
                @vozservicetable="documentTable"
                :add="null"
                :cssCard="false"
                :id="idClient"
            />
        </div>
    </div>
    <ClientCrudVozService
        :module="'cliente/clientvozservice'"
        :action="actionCrudVozService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
    <ChangeVozService
        :module="'cliente/clientvozservice'"
        :action="actionCrudVozService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
</template>

<script>
import Datatable from "../../../../base/shared/Datatable";
import {onBeforeMount, onMounted, reactive, ref, watch} from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Modal from "../../../../../helpers/modal";
import ClientCrudVozService from "./ClientCrudVozService";
import Permission from "../../../../../helpers/Permission";
import {allViewHasPermission} from "../../../../../helpers/Request";
import {hasService} from "../../helpers/helper";
import ChangeVozService from "./ChangeVozService.vue";

export default {
    name: "VozService",
    props: {
        idClient: {
            type: String,
            default: null,
        },
        editModal: Object,
        showAddService: {
            type: Boolean,
            default: false
        }
    },
    emits: ["resetShowAddService"],
    components: {
        Datatable,
        ClientCrudVozService,
        ChangeVozService
    },
    setup(props, {emit}) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = ref();
        const actionCrudVozService = ref(`crear/${props.idClient}`);
        const render = ref(1);

        const loadDatatable = ref(false);
        const hasPermission = reactive({
            data: new Permission({})
        })
        const allService = reactive({
            Voz: false
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
                if (data.value && data.toggleModal == 'modalvozservice') {
                    showEditModal(data.key);
                }
            }
        );

        onBeforeMount(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            modal.value = new Modal("modalvozservice");

            if (hasPermission.data.canView('client_service_voz_add')) {
                $(document).on("click", "#buttonmodalvozservice", function () {
                    showAddModal();
                });
            }

            $(document).on("click", `#change_tarif_voz`, function (e) {
                showModalChangeTarif(
                    $(e.target).parent().attr("id-item")
                );
            });

            allService.Voz = await hasService(props.idClient, 'voz_service')
            //TODO documentar Para Cargue el datable despues de inicializar las variables
            loadDatatable.value = true;
        });

        const cleanModal = async () => {
            allService.Voz = await hasService(props.idClient, 'voz_service')
            emit('resetShowAddService', 'voz')
            modal.value.hide();
            $("#modalvozserviceChange").modal("hide");
            render.value++;
            actionCrudVozService.value = `crear/${props.idClient}`;
            if (datatable.table) datatable.table.reload();
        };

        const documentTable = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const showAddModal = () => {
            actionCrudVozService.value = `crear/${props.idClient}`;
            modal.value.show();
        };

        const showEditModal = (idItem) => {
            actionCrudVozService.value = `update/${idItem}`;
            modal.value.show();
        };

        const showModalChangeTarif = (idItem) => {
            actionCrudVozService.value = `update/${idItem}`;
            $("#modalvozserviceChange").modal("show");
        };

        const getButtonDatatable = () => {
            let buttons = {};
            if (hasPermission.data.canView('client_service_voz_add')) {
                buttons.upload = {
                    class: 'btn btn-outline-info waves-effect waves-light',
                    iclass: 'fa fa-plus',
                    href: 'javascript:void(0)',
                    id: 'buttonmodalvozservice',
                };
            }
            return buttons;
        }

        return {
            modal,
            cleanModal,
            documentTable,
            datatable,
            actionCrudVozService,
            render,
            getButtonDatatable,
            hasPermission,
            loadDatatable,
            allService
        };
    },
};
</script>

<style scoped></style>
