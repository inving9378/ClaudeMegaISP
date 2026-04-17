<template>
    <q-list bordered class="rounded-borders">
        <q-item>
            <q-item-section top class="col-2 gt-sm">
                <q-item-label class="q-mt-sm">{{
                    item.name_item
                }}</q-item-label>
            </q-item-section>

            <q-item-section>
                <q-item-label lines="1">
                    <span class="text-weight-medium">{{ item.quantity }}</span>
                </q-item-label>
            </q-item-section>

            <q-item-section top side>
                <div class="text-grey-8 q-gutter-xs">
                    <q-btn
                        class="gt-xs"
                        size="12px"
                        flat
                        dense
                        round
                        icon="delete"
                        @click="rejectItemPending(item.id)"
                    />
                    <q-btn
                        class="gt-xs"
                        size="12px"
                        flat
                        dense
                        round
                        icon="done"
                        @click="addPendingItem(item.id)"
                    />
                </div>
            </q-item-section>
        </q-item>
    </q-list>
</template>

<script>
import Swal from "sweetalert2";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
export default {
    name: "SectionItemPending",
    components: {},
    props: {
        item: Object,
    },
    emits: ["updatePendingItems"],
    setup(props, { emit }) {
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
                                emit("updatePendingItems");
                                hideLoading();
                                Swal.fire("Éxito", message, "success");
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
                                emit("updatePendingItems");
                                Swal.fire("Éxito", message, "success");
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
            addPendingItem,
            rejectItemPending,
        };
    },
};
</script>

<style scoped></style>
