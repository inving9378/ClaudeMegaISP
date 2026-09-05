<template>
    <svg class="map-connections-overlay" width="100%" height="100%">
        <path
            v-for="conn in connections"
            :key="'connection-' + conn.id"
            :d="conn.path"
            class="map-connection animate-fade-in"
            :class="{
                'map-connection-destroy': hasEdit && conn.id !== null,
                green: conn.id === null,
            }"
            @click="
                {
                    currentConnection = conn;
                    showConfirm = conn.id !== null;
                }
            "
            @contextmenu.prevent="openForm(conn)"
            v-show="conn.visible"
        />
    </svg>

    <form-junction
        :object="currentConnection?.data ?? null"
        :show="showJunction"
        @hide="showJunction = false"
        @save="onSave"
    />

    <form-zone
        :object="currentConnection ?? null"
        :show="showZone"
        @hide="showZone = false"
        @save="onSave"
    />
    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar esta conexión"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import {
    destroyConnection,
    saveConnection,
} from "../../helper/connections-request";
import { message } from "../../../../../helpers/toastMsg";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import FormJunction from "../devices/FormJunction.vue";
import FormZone from "../devices/FormZone.vue";
import { ref } from "vue";
import { savePortDevice } from "../../helper/devices-request";
import DialogConfirm from "./DialogConfirm.vue";
import { connections } from "../../../../../composables/useMapConnections";

defineOptions({
    name: "AvaiablesRoutesComponent",
});

const props = defineProps({
    hasEdit: Boolean,
});

const emits = defineEmits(["removed"]);

const showJunction = ref(false);
const showZone = ref(false);
const currentConnection = ref(null);
const showConfirm = ref(false);

const openForm = (con) => {
    if (props.hasEdit) {
        if (con.connection_type === "fiber-to-fiber") {
            currentConnection.value = con;
            showJunction.value = true;
        } else if (
            con.from?.device_type === "olt" ||
            con.to?.device_type === "olt"
        ) {
            currentConnection.value =
                con.from?.device_type === "olt" ? con.from : con.to;
            showZone.value = true;
        }
    }
};

const onSave = async (data) => {
    showLoading();
    let result = null;
    if (showJunction.value) {
        result = await saveConnection({
            id: currentConnection.value.id,
            data: data,
        });
    } else {
        result = await savePortDevice(data);
    }
    hideLoading();
    if (result) {
        Object.assign(currentConnection.value, result);
        showJunction.value = false;
        showZone.value = false;
        message("Configuración guardada correctamente", "success");
    } else {
        message("No se ha podido guardar esta configuracion", "error");
    }
};

const destroy = async () => {
    if (props.hasEdit) {
        showLoading();
        let result = await destroyConnection(currentConnection.value.id);
        hideLoading();
        if (result) {
            emits("removed", result);
            message("Conexión eliminada correctamente", "success");
        } else {
            message("No se ha podido eliminar esta conexión", "error");
        }
        showConfirm.value = false;
    }
};
</script>
