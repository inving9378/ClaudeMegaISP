<template>
    <q-btn
        color="danger"
        flat
        size="sm"
        round
        icon="delete"
        :loading="removing"
        @click="removeOnu"
        v-if="flat"
        ><q-tooltip> Eliminar </q-tooltip></q-btn
    >
    <q-btn color="danger" :loading="removing" no-caps @click="removeOnu" v-else>
        <q-icon name="delete" style="padding: 0px !important" />
        Eliminar
    </q-btn>
</template>

<script setup>
import axios from "axios";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";

defineOptions({
    name: "RemoveOnu",
});

const props = defineProps({
    id: Number,
    flat: Boolean,
});

const emits = defineEmits(["removed"]);

const removing = ref(false);

const removeOnu = async () => {
    Swal.fire({
        title: "¿Seguro que deseas eliminar esta onu?",
        text: "No podrás deshacer esta acción.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            removing.value = true;
            const result = await axios.delete(`/olts/onus/remove/${props.id}`);
            removing.value = false;
            if (result) {
                if (result.data.success) {
                    message("Onu eliminada correctamente", "success");
                    emits("removed");
                } else {
                    message(result.data.message, "error");
                }
            }
        }
    });
};
</script>
