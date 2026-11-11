<template>
    <q-item
        class="map-client rounded-borders q-gutter-x-xs"
        :class="port?.client?.css_state"
        dense
        :style="{
            'max-with': `${clientWidth}px !important`,
            border: '1px solid #000',
            padding: '2px',
            left: device.position_x + 'px',
            top: device.position_y + 'px',
            position: 'absolute',
        }"
        :id="`${device.type}-${device.id}`"
    >
        <template v-if="device.orientation === 'left'">
            <q-item-section avatar style="margin-left: -15px">
                <q-btn
                    round
                    size="sm"
                    padding="5px"
                    :id="port.element_id"
                    :color="port.color"
                    :text-color="port.textColor"
                    :style="port.border"
                    icon="person_outline"
                    :class="port.selected ? 'map-port-active' : null"
                    :disable="getConnectionByPort(port) !== null"
                    @click.stop="emits('toggle', port)"
                >
                    <q-tooltip v-if="port.dbs">
                        {{ port.dbs }}
                    </q-tooltip>
                </q-btn>
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <q-btn
                    size="sm"
                    padding="5px"
                    color="grey"
                    icon="compare_arrows"
                    @click.stop="changePortDirection"
                >
                    <q-tooltip class="bg-grey">Intercambiar puerto</q-tooltip>
                </q-btn>
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <q-btn
                    size="sm"
                    padding="5px"
                    color="amber-8"
                    icon="lightbulb"
                    @click.stop="encenderLampara"
                >
                    <q-tooltip class="bg-grey">Iluminar recorrido hasta la OLT</q-tooltip>
                </q-btn>
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <port-note-component
                    :object="port"
                    :has-edit="hasEdit"
                    @save="(note) => (port.note = note)"
                />
            </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <form-disconnect-client
                    :object="port"
                    @removed="(data) => emits('update', data)"
                />
            </q-item-section>
            <q-item-section class="draggable">
                <q-item-label class="q-ml-sm draggable" lines="1">
                    {{ port.client?.client_id }} - {{ port.name }}
                </q-item-label>
            </q-item-section>
        <q-item-section side class="potencia-cliente" style="font-size: 11px; line-height: 1.2">
            <template v-if="potencia && potencia.actual_dbm != null">
                <span :class="colorActual">
                    {{ potencia.actual_dbm.toFixed(1) }} dBm
                    <small v-if="!potencia.en_vivo && potencia.leida_at">(última: {{ potencia.leida_at.slice(5, 16) }})</small>
                </span>
                <span v-if="potencia.inicial_dbm != null" class="text-grey-7">
                    inicial: {{ potencia.inicial_dbm.toFixed(1) }}
                    <span v-if="potencia.diferencia_db != null">(Δ {{ potencia.diferencia_db > 0 ? "+" : "" }}{{ potencia.diferencia_db.toFixed(1) }})</span>
                </span>
            </template>
            <span v-else-if="potencia && !potencia.tiene_onu" class="text-grey-7">sin ONU ligada</span>
            <span v-else-if="potencia" class="text-grey-7">sin lectura</span>
        </q-item-section>
        </template>
        <template v-else>
            <q-item-section class="draggable">
                <q-item-label class="q-ml-sm draggable" lines="1">
                    {{ port.client?.client_id }} - {{ port.name }}
                </q-item-label>
            </q-item-section>
        <q-item-section side class="potencia-cliente" style="font-size: 11px; line-height: 1.2">
            <template v-if="potencia && potencia.actual_dbm != null">
                <span :class="colorActual">
                    {{ potencia.actual_dbm.toFixed(1) }} dBm
                    <small v-if="!potencia.en_vivo && potencia.leida_at">(última: {{ potencia.leida_at.slice(5, 16) }})</small>
                </span>
                <span v-if="potencia.inicial_dbm != null" class="text-grey-7">
                    inicial: {{ potencia.inicial_dbm.toFixed(1) }}
                    <span v-if="potencia.diferencia_db != null">(Δ {{ potencia.diferencia_db > 0 ? "+" : "" }}{{ potencia.diferencia_db.toFixed(1) }})</span>
                </span>
            </template>
            <span v-else-if="potencia && !potencia.tiene_onu" class="text-grey-7">sin ONU ligada</span>
            <span v-else-if="potencia" class="text-grey-7">sin lectura</span>
        </q-item-section>
            <q-item-section avatar v-if="hasEdit">
                <form-disconnect-client
                    :object="port"
                    @removed="(data) => emits('update', data)"
                />
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <q-btn
                    size="sm"
                    padding="5px"
                    color="amber-8"
                    icon="lightbulb"
                    @click.stop="encenderLampara"
                >
                    <q-tooltip class="bg-grey">Iluminar recorrido hasta la OLT</q-tooltip>
                </q-btn>
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <port-note-component
                    :object="port"
                    :has-edit="hasEdit"
                    @save="(note) => (port.note = note)"
                />
            </q-item-section>
            <q-item-section avatar style="padding: 0">
                <q-btn
                    size="sm"
                    padding="5px"
                    color="grey"
                    icon="compare_arrows"
                    @click.stop="changePortDirection"
                >
                    <q-tooltip class="bg-grey">Intercambiar puerto</q-tooltip>
                </q-btn>
            </q-item-section>
            <q-item-section avatar style="margin-right: -15px; padding: 0">
                <q-btn
                    round
                    size="sm"
                    padding="5px"
                    class="shadow-3"
                    :id="port.element_id"
                    :color="port.color"
                    :text-color="port.textColor"
                    :style="port.border"
                    icon="person_outline"
                    :class="port.selected ? 'map-port-active' : null"
                    :disable="getConnectionByPort(port) !== null"
                    @click.stop="emits('toggle', port)"
                >
                    <q-tooltip v-if="port.dbs">
                        {{ port.dbs }}
                    </q-tooltip>
                </q-btn>
            </q-item-section></template
        >
    </q-item>
</template>

<script setup>
import { computed, onBeforeMount, ref, watch } from "vue";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { saveDevice } from "../../helper/devices-request";
import PortNoteComponent from "../PortNoteComponent.vue";
import FormDisconnectClient from "../others/FormDisconnectClient.vue";
import {
    getColorFromPort,
    getConnectionByPort,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "ClientComponent",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
    potencia: { type: Object, default: null },
});

const emits = defineEmits(["toggle", "change-port-direction", "update"]);

const port = ref(null);

const clientWidth = 400;

const colorActual = computed(() => {
    const v = props.potencia?.actual_dbm;
    if (v == null) return "";
    return v <= -27 ? "text-negative" : v <= -24 ? "text-warning" : "text-positive";
});

onBeforeMount(() => {
    port.value = props.device.ports[0];
    const { border, color, textColor } = getColorFromPort(port.value);
    port.value["color"] = color;
    port.value["border"] = border;
    port.value["textColor"] = textColor;
});

watch(
    () => props.device,
    (n) => {
        port.value = n.ports[0];
    }
);

// Item #1241: avisa al mapa (LeafletMapRed) que ilumine el recorrido cliente→OLT.
const encenderLampara = () => {
    const clientId = port.value?.client?.id ?? port.value?.client_id;
    const c = port.value?.client;
    const etiqueta = c?.name ?? c?.full_name ?? (clientId ? `Cliente ${clientId}` : undefined);
    window.dispatchEvent(new CustomEvent("mapared:lampara", { detail: { clientId, etiqueta } }));
};

const changePortDirection = async () => {
    showLoading();
    const result = await saveDevice({
        id: props.device.id,
        orientation: props.device.orientation === "left" ? "right" : "left",
    });
    hideLoading();
    if (result) {
        emits("update", result);
    }
};
</script>
<style scoped>
.organizer-ports {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 5px;
    margin: 20px;
}
</style>
