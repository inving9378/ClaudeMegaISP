<template>
    <div>
        <tabs @changeTab="changeTab" :tabss="tabs">
            <tab title="VER RED" tab="ver" :active="true">
                <Datatable
                    :id="id"
                    module="red/ipv4/ip"
                    model="NetworkIp"
                    list="Listado de IP"
                    order="ip"
                    dir="asc"
                ></Datatable>
            </tab>
            <tab title="VISIÓN GENERAL DE LA RED" tab="Vision">
                <NetworkOverview
                    :id="id"
                ></NetworkOverview>
            </tab>
        </tabs>
    </div>

    <div
        class="modal fade"
        id="modalEditIp"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <NetworkIpCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></NetworkIpCrud>
            </div>
        </div>
    </div>


</template>

<script>
import Tabs from "../../base/shared/tabs/Tabs";
import Tab from "../../base/shared/tabs/Tab";
import Datatable from "../../base/shared/Datatable";
import NetworkOverview from "./NetworkOverview";
import NetworkIpCrud from "./ip/NetworkIpCrud.vue";

import { onMounted, reactive, ref, onUnmounted, getCurrentInstance } from "vue";
import StoreZoneCrud from "../inventory/store_zone/StoreZoneCrud.vue";
import DatatableHelper from "../../../helpers/datatableHelper.js";

export default {
    name: "NetworkVer",
    components: {StoreZoneCrud, Datatable, Tabs, Tab, NetworkOverview, NetworkIpCrud},
    props: {
        id: String
    },
    setup(props) {

        const ns = `.leak983-networkVer-${getCurrentInstance().uid}`;
        onUnmounted(() => {
            $(document).off(ns);
        });
        const title = ref("Editar Red");
        const action = ref("/red/ipv4/ip/add");
        const reloadCrud = ref(true);

        const datatable = reactive({
            table: new DatatableHelper({}),
        });

        onMounted(() => {
            $(document).on("click" + ns, ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
        });

        const closeModal = () => {
            window.bootstrap.Modal.getInstance(document.getElementById('modalEditIp'))?.hide();
            reloadCrud.value = !reloadCrud.value;
            datatable.table.reload();
        };

        const showEditModal = (idItem) => {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditIp')).show();
            action.value = `/red/ipv4/ip/update/${idItem}`;
        };

        const tabs = reactive({
            ver: true,
            Vision: false
        });

        const changeTab = ({tab}) => {
            tabs[tab] = true;
        };

        return {
            changeTab,
            tabs,
            action,
            reloadCrud,
            closeModal,
            showEditModal,
            title,
        };
    },
};
</script>

<style scoped>

</style>
