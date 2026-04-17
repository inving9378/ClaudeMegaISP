

import { ref } from "vue";
import Swal from "sweetalert2";

export const idItem = ref(null);

export const quantityItemUser = ref('1');
export const quantityItemStore = ref('1');

export const user_id = ref(null);
export const store_id_to = ref(null);
export const store_id_from = ref(null);

const closeModal = (modal) => {
    $(`#${modal}`).modal("hide");
}


export const updateUserId = ({ field, value }) => {
    user_id.value = value.value;
};

export const updateStoreId = ({ field, value }) => {
    if (field == 'store_id_to') {
        store_id_to.value = value.value;
    }
    if (field == 'store_id_from') {
        store_id_from.value = value.value;
    }
};

export const updateQuantityItemUser = ({ field, value }) => {
    if (value.value < 1) {
        quantityItemUser.value = 1;
        return;
    }
    quantityItemUser.value = value.value;
}

export const updateQuantityItemStore = ({ field, value }) => {
    if (value.value < 1) {
        quantityItemStore.value = 1;
        return;
    }
    quantityItemStore.value = value.value;
}


export const assignUser = async () => {
    await axios
        .post(
            `/inventory/inventory_item/assign_to_user/${idItem.value}`,
            {
                id_user: user_id.value,
                quantity: quantityItemUser.value,
                store_from: store_id_from.value
            }
        )
        .then((response) => {
            const { success, message } = response.data;
            if (success == true) {
                Swal.fire("Éxito", message, "success");
                closeModal('modalassign_item_to_user');
            } else {
                Swal.fire("Error", message, "error");
            }
        })
        .catch((error) => {
            Swal.fire(
                "Error",
                error.response?.data?.message ||
                "Ocurrió un error inesperado.",
                "error"
            );
        });
};

export const changeStore = async () => {
    try {
        const response = await axios.post(
            `/inventory/inventory_item/change_store/${idItem.value}`,
            {
                store_to: store_id_to.value,
                quantity: quantityItemStore.value,
                store_from: store_id_from.value
            }
        );

        const { success, message } = response.data;
        if (success == true) {
            Swal.fire("Éxito", message, "success");
            closeModal('modalchange_item_store');
        } else {
            Swal.fire("Error", message, "error");
        }
    } catch (error) {
        Swal.fire(
            "Error",
            error.response?.data?.message || "Ocurrió un error inesperado.",
            "error"
        );
    }
};

