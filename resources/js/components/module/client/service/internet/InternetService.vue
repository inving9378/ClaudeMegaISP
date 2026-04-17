<template>
    <div class="col-12" v-if="loadDatatable && allService.Internet">
        <div class="card">
            <Datatable
                idTable="internetservicetable"
                module="cliente/clientinternetservice"
                model="ClientInternetService"
                list="Listado de Servicios Internet"
                :buttons="getButtonDatatable()"
                :editButton="{ modal: 'modalinternetservice' }"
                @internetservicetable="documentTable"
                :add="null"
                :cssCard="false"
                :id="idClient"
            />
        </div>
    </div>
    <ClientCrudInternetService
        :module="'cliente/clientinternetservice'"
        :action="actionCrudInternetService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
    <ChangeInternetService
        :module="'cliente/clientinternetservice'"
        :action="actionCrudInternetService"
        :idClient="idClient"
        :render="render"
        @cleanModal="cleanModal"
    />
</template>

<script>
import Datatable from "../../../../base/shared/Datatable.vue";
import { nextTick, onBeforeMount, onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Modal from "../../../../../helpers/modal";
import ClientCrudInternetService from "./ClientCrudInternetService";
import Permission from "../../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../../helpers/Request";
import { hasService } from "../../helpers/helper";
import ChangeInternetService from "./ChangeInternetService.vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Swal from "sweetalert2";

export default {
    name: "InternetService",
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
        ClientCrudInternetService,
        ChangeInternetService,
    },
    setup(props, { emit }) {
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const modal = ref();
        const actionCrudInternetService = ref(`crear/${props.idClient}`);
        const render = ref(1);

        const loadDatatable = ref(false);
        const hasPermission = reactive({
            data: new Permission({}),
        });
        const allService = reactive({
            Internet: false,
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
                if (data.value && data.toggleModal == "modalinternetservice") {
                    showEditModal(data.key);
                }
            }
        );

        onBeforeMount(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            modal.value = new Modal("modalinternetservice");

            if (hasPermission.data.canView("client_service_internet_add")) {
                $(document).on(
                    "click",
                    "#buttonmodalinternetservice",
                    function () {
                        showAddModal();
                    }
                );
            }

            $(document).on("click", `#change_tarif_internet`, function (e) {
                showModalChangeTarif($(e.target).parent().attr("id-item"));
            });

            $(document).on("click", `#refresh_ip`, function (e) {
                $(e.target).parent().attr("id-item");
                let idItem = $(e.target).parent().attr("id-item");
                refreshIp(idItem);
            });

            allService.Internet = await hasService(
                props.idClient,
                "internet_service"
            );

            await nextTick();
            //TODO documentar Para Cargue el datable despues de inicializar las variables
            loadDatatable.value = true;
        });

        const refreshIp = async (idItem) => {
            showLoading("showTextDef");
            await axios
                .post(`/cliente/clientinternetservice/refresh-ip/${idItem}`)
                .then((response) => {
                    const { success, message } = response.data;
                    if (success == true) {
                        Swal.fire("Éxito", message, "success");
                    } else {
                        Swal.fire("Error", message, "error");
                    }
                    hideLoading();
                })
                .catch((error) => {
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                    hideLoading();
                });

            if (datatable.table) datatable.table.reload();
        };

        const cleanModal = async () => {
            allService.Internet = await hasService(
                props.idClient,
                "internet_service"
            );
            emit("resetShowAddService", "internet");
            modal.value.hide();
            $("#modalinternetserviceChange").modal("hide");
            render.value++;
            actionCrudInternetService.value = `crear/${props.idClient}`;
            if (datatable.table) datatable.table.reload();
        };

        const documentTable = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const showAddModal = () => {
            actionCrudInternetService.value = `crear/${props.idClient}`;
            modal.value.show();
        };

        const showEditModal = (idItem) => {
            actionCrudInternetService.value = `update/${idItem}`;
            modal.value.show();
        };

        const showModalChangeTarif = (idItem) => {
            actionCrudInternetService.value = `update/${idItem}`;
            $("#modalinternetserviceChange").modal("show");
        };

        const getButtonDatatable = () => {
            let buttons = {};
            if (hasPermission.data.canView("client_service_internet_add")) {
                buttons.upload = {
                    class: "btn btn-outline-info waves-effect waves-light",
                    iclass: "fa fa-plus",
                    href: "javascript:void(0)",
                    id: "buttonmodalinternetservice",
                };
            }
            return buttons;
        };

        return {
            modal,
            cleanModal,
            documentTable,
            datatable,
            actionCrudInternetService,
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
