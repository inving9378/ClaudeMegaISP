<template>
    <q-btn
        color="warning"
        flat
        size="sm"
        round
        icon="mdi-sync"
        :loading="loading"
        @click="reboot"
        v-if="flat"
    >
        <q-tooltip> Reiniciar </q-tooltip>
    </q-btn>

    <q-btn color="warning" :loading="loading" no-caps @click="reboot" v-else>
        <q-icon name="mdi-sync" style="padding: 0px !important" />
        Reiniciar
    </q-btn>
</template>

<script setup>
import axios from "axios";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";

defineOptions({
    name: "RebootOnu",
});

const props = defineProps({
    onu: Object,
    flat: Boolean,
});

const loading = ref(false);

const reboot = async () => {
    Swal.fire({
        title: "¿Seguro que deseas reiniciar esta onu?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            loading.value = true;
            await axios
                .post(`/olts/onus/reboot/${props.onu.id}`)
                .then((res) => {
                    let response = res.data;
                    if (response.success) {
                        message("ONU reiniciada correctamente", "success");
                    } else {
                        message(response.message ?? response.error, "error");
                    }
                })
                .finally(() => {
                    loading.value = false;
                });
        }
    });
};
</script>
