<template>
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
</template>

<script>
import { nextTick, onMounted, ref, watch } from "vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Swal from "sweetalert2";
import SectionItemPending from "./SectionItemPending.vue";
import SectionItemAccepted from "./SectionItemAccepted.vue";
import { darkMode } from "../../../../../hook/appConfig";

export default {
    name: "ItemsPending",
    components: {},
    props: {
        store_id: String | Number,
    },
    setup(props) {
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
            await getItemsByStore(props.store_id);
            hideLoading();
        };

        const getItemsByStore = async (store_id) => {
            showLoading("showTextDef");
            await axios
                .post(
                    `/inventory/inventory_item_stock/get_items_by_store/${store_id}`
                )
                .then((response) => {
                    const { success, message, pending, accepted } =
                        response.data;
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
                                getItemsPending();
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
                                Swal.fire("Éxito", message, "success");
                                getItemsPending();
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
                }
            });
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
            darkMode,
        };
    },
};
</script>

<style scoped>
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
