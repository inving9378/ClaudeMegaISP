<template>
    <template v-if="flat"
        ><q-btn
            :color="
                onu.administrative_status === 'Enabled' ? 'danger' : 'primary'
            "
            flat
            size="sm"
            round
            :icon="
                onu.administrative_status === 'Enabled'
                    ? 'mdi-cancel'
                    : 'mdi-check-circle-outline'
            "
            :loading="loading"
            @click="enable"
            v-if="flat"
        >
            <q-tooltip>
                {{
                    onu.administrative_status === "Enabled"
                        ? "Deshabilitar"
                        : "Habilitar"
                }}
            </q-tooltip>
        </q-btn></template
    >

    <q-btn
        :color="onu.administrative_status === 'Enabled' ? 'warning' : 'primary'"
        :loading="loading"
        no-caps
        @click="enable"
        v-else
    >
        <q-icon
            :name="
                onu.administrative_status === 'Enabled'
                    ? 'mdi-cancel'
                    : 'mdi-check-circle-outline'
            "
            style="padding: 0px !important"
        />
        {{
            onu.administrative_status === "Enabled"
                ? "Deshabilitar"
                : "Habilitar"
        }}
    </q-btn>
</template>

<script setup>
import axios from "axios";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import { ref } from "vue";

defineOptions({
    name: "EnableOnu",
});

const props = defineProps({
    onu: Object,
    flat: Boolean,
});

const emits = defineEmits(["enabled"]);

const loading = ref(false);

const save = async () => {
    loading.value = true;
    const { id, administrative_status } = props.onu;
    const enable = administrative_status !== "Enabled";
    await axios
        .post(`/olts/onus/enable-disable/${id}`, {
            enable,
        })
        .then((res) => {
            let response = res.data;
            if (response.success) {
                message(
                    `ONU ${
                        enable ? "habilitada" : "deshabilitada"
                    } correctamente`,
                    "success"
                );
                emits("enabled", enable);
            } else {
                message(response.message, "error");
            }
        })
        .finally(() => {
            loading.value = false;
        });
};

const enable = async () => {
    if (props.onu.administrative_status === "Enabled") {
        Swal.fire({
            title: "¿Seguro que deseas deshabilitar esta onu?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, continuar",
            cancelButtonText: "Cancelar",
        }).then(async (result) => {
            if (result.isConfirmed) {
                save();
            }
        });
    } else {
        save();
    }
};
</script>
