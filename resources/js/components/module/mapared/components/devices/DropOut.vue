<template>
    <q-card-actions
        align="center"
        class="no-gutter-x bg-grey-3"
        style="padding: 0; height: 160px"
        v-if="showDropOut"
    >
        <q-item style="width: auto; padding: 0">
            <q-item-section
                v-for="port in device.ports"
                :key="`row-${port.id}`"
                style="max-width: 30px"
            >
                <q-bar
                    class="q-my-xs rotate-270"
                    :class="getBackground(port?.client)"
                    style="min-width: 146px; margin-left: -58px"
                >
                    <div class="col text-center">
                        {{ port.client.client_id }}
                    </div>
                    <q-btn
                        round
                        size="8px"
                        color="secondary"
                        icon="mdi-information-variant"
                        class="q-mr-xs"
                        ><q-tooltip>Información</q-tooltip>
                        <q-menu style="max-width: 400px !important">
                            <q-item>
                                <q-item-section>
                                    <q-item-label>
                                        Dirección drop
                                    </q-item-label>
                                    <q-item-label caption>{{
                                        port.data?.reason ===
                                        "Cambio de domicilio"
                                            ? port.data.old_address
                                            : port?.client.address
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item v-if="port.data?.reason">
                                <q-item-section>
                                    <q-item-label>
                                        <b>Motivo:</b>
                                        {{ port.data.reason }}
                                    </q-item-label>
                                </q-item-section>
                            </q-item>
                        </q-menu>
                    </q-btn>
                    <q-btn
                        round
                        size="8px"
                        color="danger"
                        icon="delete"
                        @click="
                            {
                                currentPort = port;
                                showConfirm = true;
                            }
                        "
                        ><q-tooltip class="bg-danger"
                            >Eliminar</q-tooltip
                        ></q-btn
                    >
                </q-bar>
            </q-item-section>
        </q-item>
    </q-card-actions>
    <q-btn
        round
        :icon="showDropOut ? 'mdi-arrow-bottom-right' : 'mdi-arrow-top-left'"
        color="primary"
        size="10px"
        style="position: absolute; bottom: 10px; right: 10px"
        @click="showDropOut = !showDropOut"
    >
        <q-tooltip
            >{{ showDropOut ? "Ocultar" : "Mostrar" }} salida drop</q-tooltip
        >
    </q-btn>
    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar este cliente de la salida drop"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { onMounted, ref } from "vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { removeClientFromDrop } from "../../helper/connections-request";
import { devices } from "../../../../../composables/useMapConnections";

defineOptions({
    name: "DropOut",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
});

const emits = defineEmits(["update"]);

const expanded = ref(true);
const showConfirm = ref(false);
const currentPort = ref(null);
const showDropOut = ref(true);

onMounted(() => {
    expanded.value = true;
});

const destroy = async () => {
    showLoading();
    const result = await removeClientFromDrop(currentPort.value.id);
    if (result) {
        showConfirm.value = false;
        emits("update", result);
    }
    hideLoading();
};

const getBackground = (client) => {
    if (client) {
        const clients = devices.value.filter((d) => d.type === "client");
        for (let i = 0; i < clients.length; i++) {
            for (let j = 0; j < clients[i].ports.length; j++) {
                if (client.client_id === clients[i].ports[j].client.client_id) {
                    return client.css_state;
                }
            }
        }
        return "bg-orange";
    }
    return null;
};
</script>
