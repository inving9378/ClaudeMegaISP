<template>
    <div class="q-pa-md">
        <div class="q-gutter-y-md" style="max-width: 600px">
            <q-tabs v-model="activeTab" dense align="left" :breakpoint="0">
                <q-tab
                    name="accepted"
                    label="Artículos"
                    v-if="tabs.includes('accepted')"
                />
            </q-tabs>
        </div>
    </div>
    <q-tab-panels v-model="activeTab" animated :dark="darkMode">
        <q-tab-panel name="accepted" v-if="tabs.includes('accepted')">
            <div class="q-pa-md">
                <q-card>
                    <q-card-section
                        class="d-flex"
                        style="justify-content: space-between"
                    >
                        <div class="text-h6">Materiales</div>
                    </q-card-section>
                    <q-table
                        v-table-resizable
                        :rows="materialsAccepted"
                        :columns="columnsAccepted"
                        :filter="filter"
                        :dark="darkMode"
                        :rows-per-page-label="'Elementos por página'"
                        :rows-per-page-options="rowPerPageOptions"
                        v-model:pagination="pagination"
                        binary-state-sort
                        :loading="loading"
                        no-data-label="No hay elementos para mostrar"
                    >
                        <template v-slot:top="props">
                            <div class="d-flex justify-content-end">
                                <q-btn
                                    flat
                                    round
                                    dense
                                    :icon="
                                        props.inFullscreen
                                            ? 'fullscreen_exit'
                                            : 'fullscreen'
                                    "
                                    @click="props.toggleFullscreen"
                                    class="q-ml-md"
                                />
                                <q-input
                                    borderless
                                    dense
                                    v-model="filter"
                                    placeholder="Buscar"
                                    class="mb-0"
                                    style="margin-left: 16px; border: 1px solid"
                                    :dark="darkMode"
                                />
                            </div>
                        </template>
                        <template v-slot:body-cell-No="props">
                            <q-td :props="props">
                                {{ props.pageIndex + 1 }}
                            </q-td>
                        </template>
                        <!-- <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="output"
                                    @click="moveItem(props.row.inventory_item_id)"
                                />
                            </q-td>
                        </template> -->
                    </q-table>
                </q-card>
            </div>

            <div class="q-pa-md">
                <q-card>
                    <q-card-section
                        class="d-flex"
                        style="justify-content: space-between"
                    >
                        <div class="text-h6">Herramientas</div>
                    </q-card-section>
                    <q-table
                        v-table-resizable
                        :rows="toolsAccepted"
                        :columns="columnsAccepted"
                        :filter="filter"
                        :dark="darkMode"
                        :rows-per-page-label="'Elementos por página'"
                        :rows-per-page-options="rowPerPageOptions"
                        v-model:pagination="pagination"
                        binary-state-sort
                        :loading="loading"
                        no-data-label="No hay elementos para mostrar"
                    >
                        <template v-slot:top="props">
                            <div class="d-flex justify-content-end">
                                <q-btn
                                    flat
                                    round
                                    dense
                                    :icon="
                                        props.inFullscreen
                                            ? 'fullscreen_exit'
                                            : 'fullscreen'
                                    "
                                    @click="props.toggleFullscreen"
                                    class="q-ml-md"
                                />
                                <q-input
                                    borderless
                                    dense
                                    v-model="filter"
                                    placeholder="Buscar"
                                    class="mb-0"
                                    style="margin-left: 16px; border: 1px solid"
                                    :dark="darkMode"
                                />
                            </div>
                        </template>
                        <template v-slot:body-cell-No="props">
                            <q-td :props="props">
                                {{ props.pageIndex + 1 }}
                            </q-td>
                        </template>
                        <!-- <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="output"
                                    @click="moveItem(props.row.inventory_item_id)"
                                />
                            </q-td>
                        </template> -->
                    </q-table>
                </q-card>
            </div>
        </q-tab-panel>
    </q-tab-panels>

    <div
        class="modal fade"
        id="modalchange_item_seller"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Mover Artículo</h6>
                </div>
                <InventoryMovementAll
                    :user_id="client_id"
                    @close-modal="closeModal"
                    :key="reloadCrud"
                >
                </InventoryMovementAll>
            </div>
        </div>
    </div>
</template>

<script>
import { nextTick, onMounted, ref, watch } from "vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Swal from "sweetalert2";
import InventoryMovementAll from "./components/InventoryMovementAll.vue";
import { idItem } from "./components/comun_variables";
import { darkMode } from "../../../../../hook/appConfig";
export default {
    name: "InventoryItemClient",
    components: { InventoryMovementAll },
    props: {
        client_id: String,
    },
    setup(props) {
        const tabs = ref(["accepted"]);
        const percentage = tabs.value ? `${100 / tabs.value.length}%` : null;
        const activeTab = ref("accepted");

        const setActiveTab = (tab) => {
            activeTab.value = tab;
        };

        const itemsAccepted = ref([]);
        const itemsPending = ref([]);

        const loadedItems = ref(false);
        const dataPendings = ref([]);
        const dataAccepted = ref([]);
        const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
        const loading = ref(false);
        const filter = ref("");

        const toolsPending = ref([]); // Lista para herramientas
        const materialsPending = ref([]); // Lista para materiales
        const materialsAccepted = ref([]); // Lista para materiales
        const toolsAccepted = ref([]); // Lista para herramientas

        const pagination = ref({
            descending: false,
            page: 1,
            rowsPerPage: 50,
            rowsNumber: 10,
        });

        onMounted(async () => {
            await getItemsPending();
        });

        const getItemsPending = async () => {
            showLoading();
            await nextTick();
            await getItemsByStore(props.client_id);
            hideLoading();
        };

        const getItemsByStore = async (client_id) => {
            showLoading("showTextDef");
            await axios
                .post(
                    `/inventory/inventory_item_stock/get_items_by_client/${client_id}`
                )
                .then((response) => {
                    const { success, message, pending, accepted } =
                        response.data;
                    if (success) {
                        dataPendings.value = pending;
                        loadedItems.value = true;

                        toolsAccepted.value = accepted.filter(
                            (item) => item.type_item === "tool"
                        );
                        materialsAccepted.value = accepted.filter(
                            (item) => item.type_item === "material"
                        );
                    }
                })
                .catch((error) => {
                    hideLoading();
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                });
            hideLoading();
        };

        const columns = ref([
            {
                name: "id",
                align: "start",
                label: "Id",
                field: "id",
                sortable: false,
                visible: true,
            },
            {
                name: "inventory_item_id",
                align: "start",
                label: "Id del articulo",
                field: "inventory_item_id",
                sortable: true,
                visible: true,
            },
            {
                name: "name_item",
                align: "start",
                label: "Nombre",
                field: "name_item",
                sortable: true,
                visible: true,
            },
            {
                name: "quantity",
                align: "start",
                label: "Cantidad",
                field: "quantity",
                sortable: true,
                visible: true,
            },
            {
                name: "type_item",
                align: "start",
                label: "Tipo de articulo",
                field: "type_item",
                sortable: true,
                visible: true,
            },
            {
                name: "actions",
                align: "start",
                label: "Acciones",
                field: "actions",
                sortable: false,
                visible: true,
            },
        ]);

        const columnsAccepted = ref([
            {
                name: "id",
                align: "start",
                label: "Id",
                field: "id",
                sortable: false,
                visible: true,
            },
            {
                name: "inventory_item_id",
                align: "start",
                label: "Id del articulo",
                field: "inventory_item_id",
                sortable: true,
                visible: true,
            },
            {
                name: "name_item",
                align: "start",
                label: "Nombre",
                field: "name_item",
                sortable: true,
                visible: true,
            },
            {
                name: "current_stock",
                align: "start",
                label: "Cantidad",
                field: "current_stock",
                sortable: true,
                visible: true,
            },
            {
                name: "type_item",
                align: "start",
                label: "Tipo de articulo",
                field: "type_item",
                sortable: true,
                visible: true,
            },
            {
                name: "actions",
                align: "start",
                label: "Acciones",
                field: "actions",
                sortable: false,
                visible: true,
            },
        ]);

        const reloadCrud = ref(true);
        const moveItem = async (id) => {
            idItem.value = id;
            $(`#modalchange_item_seller`).modal("show");
        };

        const closeModal = () => {
            idItem.value = null;
            $(`#modalchange_item_seller`).modal("hide");
            reloadCrud.value = !reloadCrud.value;
        };

        return {
            itemsPending,
            itemsAccepted,
            loadedItems,
            dataPendings,
            columns,
            filter,
            pagination,
            rowPerPageOptions,
            loading,
            dataAccepted,
            toolsPending,
            materialsPending,
            activeTab,
            setActiveTab,
            tabs,
            materialsAccepted,
            toolsAccepted,
            moveItem,
            columnsAccepted,
            closeModal,
            reloadCrud,
            darkMode,
        };
    },
};
</script>

<style scoped>
.q-tab {
    flex: 0 1 auto; /* Esto hace que las pestañas se ajusten al contenido */
    white-space: nowrap; /* Evita que el texto se divida en varias líneas */
}
.btn-eliminar {
    background-color: #ff4444; /* Fondo rojo */
    color: white; /* Ícono blanco */
    transition: background-color 0.3s, color 0.3s; /* Transición suave */
}

.btn-eliminar:hover {
    background-color: white; /* Fondo blanco al pasar el mouse */
    color: #ff4444; /* Ícono rojo al pasar el mouse */
}

/* Clase para el botón de aceptar */
.btn-aceptar {
    background-color: #4caf50; /* Fondo verde */
    color: white; /* Ícono blanco */
    transition: background-color 0.3s, color 0.3s; /* Transición suave */
}

.btn-aceptar:hover {
    background-color: white; /* Fondo blanco al pasar el mouse */
    color: #4caf50; /* Ícono verde al pasar el mouse */
}
</style>
