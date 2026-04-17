<template>
    <div class="d-flex mt-3 mb-2">
        <div class="dropdown m-auto me-2">
            <button
                type="button"
                class="btn btn-outline-secondary waves-effect waves-light"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <span class="ms-1">Añadir Servicio</span>
                <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <a
                    class="dropdown-item"
                    href="javascript:void(0)"
                    @click="addService('bundle')"
                    v-if="canAddServiceBundle"
                    >Agregar Paquete</a
                >
                <a
                    class="dropdown-item"
                    href="javascript:void(0)"
                    @click="addService('internet')"
                    v-if="canAddServiceInternet"
                    >Agregar Servicio Internet</a
                >
                <a
                    class="dropdown-item"
                    href="javascript:void(0)"
                    @click="addService('voz')"
                    v-if="canAddServiceVoz"
                    >Agregar Servicio de Voz</a
                >
                <a
                    class="dropdown-item"
                    href="javascript:void(0)"
                    @click="addService('custom')"
                    v-if="canAddServiceCustom"
                    >Agregar Servicio Custom</a
                >
            </div>
        </div>
    </div>

    <BundleService
        v-if="hasPermission.data.canView('client_service_bundle_view_client')"
        :idClient="id"
        :editModal="editModal"
        :showAddService="showAddService.bundle"
        @resetShowAddService="resetShowAddService"
        :key="reload"
    />
    <InternetService
        v-if="hasPermission.data.canView('client_service_internet_view_client')"
        :idClient="id"
        :editModal="editModal"
        :showAddService="showAddService.internet"
        @resetShowAddService="resetShowAddService"
        :key="reload"
    />
    <VozService
        v-if="hasPermission.data.canView('client_service_voz_view_client')"
        :idClient="id"
        :editModal="editModal"
        :showAddService="showAddService.voz"
        @resetShowAddService="resetShowAddService"
        :key="reload"
    />
    <CustomService
        :idClient="id"
        :editModal="editModal"
        :showAddService="showAddService.custom"
        @resetShowAddService="resetShowAddService"
        :key="reload"
    />
    <div style="height: 300px"></div>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import InternetService from "./internet/InternetService.vue";
import VozService from "./voz/VozService";
import BundleService from "./bundle/BundleService";
import { onBeforeMount, onMounted, reactive, ref } from "vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import CustomService from "./custom/CustomService";
import { canAddService } from "../helpers/helper";

export default {
    name: "ClientService",
    props: {
        id: {
            type: String,
            default: null,
        },
        editModal: Object,
    },
    components: {
        CustomService,
        Datatable,
        InternetService,
        VozService,
        BundleService,
    },
    setup(props) {
        const hasPermission = reactive({
            data: new Permission({}),
        });
        const showAddService = reactive({
            internet: false,
            voz: false,
            custom: false,
        });
        const reload = ref(1);
        const canAddServiceBundle = ref(false);
        const canAddServiceInternet = ref(false);
        const canAddServiceVoz = ref(false);
        const canAddServiceCustom = ref(false);

        onBeforeMount(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
        });

        onMounted(async () => {
            canAddServiceBundle.value = await canAddService(props.id, "bundle");
            canAddServiceInternet.value = await canAddService(
                props.id,
                "internet"
            );
            canAddServiceVoz.value = await canAddService(props.id, "voz");
            canAddServiceCustom.value = await canAddService(props.id, "custom");
        });

        const addService = (service) => {
            showAddService[service] = true;
        };

        const resetShowAddService = (service) => {
            if (service == 'bundle') reload.value++;
            showAddService[service] = false;
        }

        return {
            hasPermission,
            addService,
            showAddService,
            resetShowAddService,
            reload,
            canAddService,
            canAddServiceInternet,
            canAddServiceBundle,
            canAddServiceVoz,
            canAddServiceCustom,
        };
    },
};
</script>

<style scoped></style>
