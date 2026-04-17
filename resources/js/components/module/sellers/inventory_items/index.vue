<template>
    <div class="q-pa-md">
        <div class="q-gutter-y-md" style="max-width: 600px">
            <q-tabs v-model="activeTab" dense align="left" :breakpoint="0">
                <q-tab
                    name="accepted"
                    label="Actuales"
                    v-if="tabs.includes('accepted')"
                />
                <q-tab
                    name="pending"
                    label="Pedidos pendientes"
                    v-if="tabs.includes('pending')"
                />
                <q-tab
                    name="last_actions"
                    label="Ultimas acciones"
                    v-if="tabs.includes('last_actions')"
                />
            </q-tabs>
        </div>
    </div>
    <q-tab-panels v-model="activeTab" animated :dark="darkMode">
        <q-tab-panel
            name="accepted"
            v-if="tabs.includes('accepted') && activeTab == 'accepted'"
        >
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
                        <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="output"
                                    @click="moveItem(props.row.id)"
                                />
                            </q-td>
                        </template>
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
                        <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="output"
                                    @click="moveItem(props.row.id)"
                                />
                            </q-td>
                        </template>
                    </q-table>
                </q-card>
            </div>
        </q-tab-panel>

        <!-- Pendientes -->
        <q-tab-panel
            name="pending"
            v-if="tabs.includes('pending') && activeTab == 'pending'"
        >
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
                        :rows="materialsPending"
                        :columns="columns"
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

                        <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="delete"
                                    @click="rejectItemPending(props.row.id)"
                                />
                                <q-btn
                                    class="gt-xs btn-aceptar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="done"
                                    @click="addPendingItem(props.row.id)"
                                />
                            </q-td>
                        </template>
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
                        :rows="toolsPending"
                        :columns="columns"
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
                        <template v-slot:body-cell-actions="props">
                            <q-td :props="props">
                                <q-btn
                                    class="gt-xs btn-eliminar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="delete"
                                    @click="rejectItemPending(props.row.id)"
                                />
                                <q-btn
                                    class="gt-xs btn-aceptar me-3"
                                    size="12px"
                                    flat
                                    dense
                                    round
                                    icon="done"
                                    @click="addPendingItem(props.row.id)"
                                />
                            </q-td>
                        </template>
                    </q-table>
                </q-card>
            </div>
        </q-tab-panel>

        <q-tab-panel
            name="last_actions"
            v-if="tabs.includes('last_actions') && activeTab == 'last_actions'"
        >
            <div class="q-pa-md">
                <q-card>
                    <q-card-section
                        class="d-flex"
                        style="justify-content: space-between"
                    >
                        <div class="text-h6">Ultimas Acciones</div>
                    </q-card-section>
                    <q-table
                        v-table-resizable
                        :rows="lastActions"
                        :columns="columnsLastActions"
                        :filter="filter"
                        :dark="darkMode"
                        :rows-per-page-label="'Elementos por página'"
                        :rows-per-page-options="rowPerPageOptions"
                        v-model:pagination="pagination"
                        binary-state-sort
                        :loading="loading"
                        no-data-label="No hay elementos para mostrar"
                    >
                        <template v-slot:body-cell-No="props">
                            <q-td :props="props">
                                {{ props.pageIndex + 1 }}
                            </q-td>
                        </template>
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
                    :user_id="user_id"
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
import { userId } from "../comun_variables";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import Swal from "sweetalert2";
import InventoryMovementAll from "./components/InventoryMovementAll.vue";
import { idItem } from "./components/comun_variables";
import { darkMode } from "../../../../hook/appConfig";
export default {
    name: "InventoryItemSeller",
    components: { InventoryMovementAll },
    props: {
        user_id: Number | String,
    },
    setup(props) {
        const tabs = ref(["accepted", "pending", "last_actions"]);
        const activeTab = ref("accepted");

        const setActiveTab = async (tab) => {
            showLoading("showTextDef");
            await getItemsByUser(userId.value, tab);
            hideLoading();
        };

        watch(activeTab, () => {
            setActiveTab(activeTab.value);
        });

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

        const lastActions = ref([]);

        const pagination = ref({
            descending: false,
            page: 1,
            rowsPerPage: 50,
            rowsNumber: 10,
        });

        onMounted(async () => {
            if (props.user_id) {
                userId.value = props.user_id;
            }
            await getItemsByUser(userId.value);
        });

        const getItemsByUser = async (userId) => {
            showLoading("showTextDef");
            await axios
                .post(
                    `/inventory/inventory_item_stock/get_items_by_user/${userId}`,
                    {
                        tab: activeTab.value,
                    }
                )
                .then((response) => {
                    const {
                        success,
                        message,
                        pending,
                        accepted,
                        last_actions,
                    } = response.data;
                    if (success) {
                        dataPendings.value = pending;
                        loadedItems.value = true;

                        // Filtrar los datos por type_item
                        toolsPending.value = pending.filter(
                            (item) => item.type_item === "tool"
                        );
                        materialsPending.value = pending.filter(
                            (item) => item.type_item === "material"
                        );

                        toolsAccepted.value = accepted.filter(
                            (item) => item.type_item === "tool"
                        );
                        materialsAccepted.value = accepted.filter(
                            (item) => item.type_item === "material"
                        );

                        console.log(last_actions);

                        lastActions.value = last_actions;
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

        const columnsLastActions = ref([
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
                name: "type",
                align: "start",
                label: "Tipo de Movimiento",
                field: "type",
                sortable: true,
                visible: true,
            },
            {
                name: "status_name",
                align: "start",
                label: "Estado",
                field: "status_name",
                sortable: true,
                visible: true,
            },
            {
                name: "desde",
                align: "start",
                label: "Desde",
                field: "desde",
                sortable: true,
                visible: true,
            },
            {
                name: "hacia",
                align: "start",
                label: "Hacia",
                field: "hacia",
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

        const addPendingItem = async (id) => {
            Swal.fire({
                title: "¿Estás seguro de aceptar este Artículo?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    showLoading("showTextDef");
                    await axios
                        .post(
                            `/inventory/inventory_item_stock/accept_item_by_movement/${id}`
                        )
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success == true) {
                                hideLoading();
                                Swal.fire("Éxito", message, "success");
                                getItemsByUser(userId.value);
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
                }
            });
        };

        const rejectItemPending = async (id) => {
            Swal.fire({
                title: "¿Estás seguro de rechazar este Artículo?",
                text: "No podrás deshacer esta acción.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, continuar",
                cancelButtonText: "Cancelar",
                html: '<textarea id="swal-textarea" class="swal2-textarea" placeholder="Explica el motivo del rechazo" rows="5"></textarea>',
                didOpen: () => {
                    const textarea = document.getElementById("swal-textarea");
                    const confirmButton = Swal.getConfirmButton();

                    // Deshabilitar el botón de confirmación inicialmente
                    confirmButton.disabled = true;

                    // Escuchar cambios en el textarea
                    textarea.addEventListener("input", () => {
                        if (textarea.value.trim() === "") {
                            confirmButton.disabled = true; // Deshabilitar si está vacío
                        } else {
                            confirmButton.disabled = false; // Habilitar si hay texto
                        }
                    });
                },
                preConfirm: () => {
                    const reason = document
                        .getElementById("swal-textarea")
                        .value.trim();
                    if (!reason) {
                        Swal.showValidationMessage(
                            "Por favor, explica el motivo del rechazo"
                        );
                        return false; // Evita que se cierre el SweetAlert2
                    }
                    return reason; // Retorna el motivo
                },
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const reason = result.value; // Obtiene el motivo
                    await axios
                        .post(
                            `/inventory/inventory_item_stock/reject_item_by_movement/${id}`,
                            {
                                reason: reason, // Envía el motivo en el cuerpo de la solicitud
                            }
                        )
                        .then((response) => {
                            const { success, message } = response.data;
                            if (success) {
                                hideLoading();
                                Swal.fire("Éxito", message, "success");
                                getItemsByUser(userId.value);
                            }
                        })
                        .catch((error) => {
                            Swal.fire(
                                "Error",
                                error.response?.data?.message ||
                                    "Ocurrido un error inesperado.",
                                "error"
                            );
                        });
                    hideLoading();
                }
            });
        };

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
            rejectItemPending,
            addPendingItem,
            userId,
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
            lastActions,
            columnsLastActions,
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
