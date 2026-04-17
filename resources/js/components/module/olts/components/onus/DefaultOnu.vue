<template>
    <q-btn
        color="warning"
        flat
        size="sm"
        round
        icon="mdi-restart"
        :loading="loading"
        @click="reset"
        v-if="flat"
    >
        <q-tooltip> Establecer valores por defecto </q-tooltip>
    </q-btn>

    <q-btn color="warning" :loading="loading" no-caps @click="reset" v-else>
        <q-icon name="mdi-restart" style="padding: 0px !important" />
        Establecer valores por defecto
    </q-btn>
</template>

<script setup>
import axios from "axios";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";

defineOptions({
    name: "DefaultOnu",
});

const props = defineProps({
    onu: Object,
    flat: Boolean,
});

const loading = ref(false);

const reset = async () => {
    Swal.fire({
        title: "¿Seguro que deseas restablecer los valores por defecto de esta onu?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            // loading.value = true;
            // await axios
            //     .post(`/api/olts/onu/resync/${props.onu.id}`)
            //     .then((res) => {
            //         let response = res.data;
            //         if (response.success) {
            //             message("Datos restablecidos correctamente", "success");
            //         } else {
            //             message(response.message ?? response.error, "error");
            //         }
            //     })
            //     .finally(() => {
            //         loading.value = false;
            //     });
        }
    });
};
</script>
