<template>
    <q-btn
        color="warning"
        flat
        size="sm"
        round
        icon="sync_alt"
        :loading="loading"
        class="rotate-90"
        @click="reboot"
        v-if="flat"
    >
        <q-tooltip> Resincronizar configuración </q-tooltip>
    </q-btn>

    <q-btn color="warning" :loading="loading" no-caps @click="reboot" v-else>
        <q-icon
            name="sync_alt"
            style="padding: 0px !important"
            class="rotate-90"
        />
        Resincronizar configuración
    </q-btn>
</template>

<script setup>
import axios from "axios";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";

defineOptions({
    name: "ResyncOnu",
});

const props = defineProps({
    onu: Object,
    flat: Boolean,
});

const loading = ref(false);

const reboot = async () => {
    Swal.fire({
        title: "¿Seguro que deseas resincronizar esta onu?",
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
                .post(`/olts/onus/resync/${props.onu.id}`)
                .then((res) => {
                    let response = res.data;
                    if (response.success) {
                        message("ONU resincronizada correctamente", "success");
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
