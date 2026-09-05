<template>
    <q-list
        class="q-pa-xs q-my-xs shadow-3 bg-white"
        :class="device.parent_id ? '' : 'map-device'"
        dense
        :id="`${device.type}-${device.id}`"
        :style="{
            left: device.position_x + 'px',
            top: device.position_y + 'px',
        }"
    >
        <q-expansion-item
            class="bg-grey-5 map-device-header"
            :id="`${device.type}-header-${device.id}`"
            v-model="expanded"
            @after-show="emits('redraw')"
            @after-hide="emits('redraw')"
        >
            <template v-slot:header>
                <q-item-section
                    @click.stop="(ev) => ev.preventDefault()"
                    class="draggable"
                >
                    <q-item-label lines="1" class="draggable">
                        {{ device.name }}
                    </q-item-label>
                </q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <q-btn
                        icon="add"
                        color="primary"
                        round
                        size="sm"
                        @click="dialog = true"
                    >
                        <q-tooltip>Agregar puertos</q-tooltip>
                    </q-btn>
                </q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <form-olt-component
                        :object="device"
                        @update="(data) => emits('update', data)"
                    />
                </q-item-section>
                <q-item-section avatar>
                    <change-connections-state :ports="device.ports"
                /></q-item-section>
                <q-item-section
                    avatar
                    @click.stop="(ev) => ev.preventDefault()"
                    v-if="hasEdit"
                >
                    <q-btn
                        round
                        size="sm"
                        color="danger"
                        icon="delete"
                        @click.stop="showConfirm = true"
                        ><q-tooltip class="bg-danger"
                            >Eliminar</q-tooltip
                        ></q-btn
                    >
                </q-item-section>
            </template>
            <q-card v-if="expanded">
                <q-card-section class="q-pa-none">
                    <q-list dense>
                        <q-item>
                            <q-item-section>
                                <q-slider
                                    v-model="slider"
                                    :min="0"
                                    :max="2"
                                    snap
                                    markers
                                    @update:model-value="
                                        (val) => {
                                            currentSize = avaiablesSizes.find(
                                                (s) => s.value === val
                                            ).name;
                                            emits('redraw');
                                        }
                                    "
                                />
                            </q-item-section>
                        </q-item>
                        <q-item>
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos wan
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in wanPorts"
                                :key="`olt-port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    class="shadow-3"
                                    :id="port.element_id"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    :disable="
                                        getConnectionByPort(port) !== null
                                    "
                                    @click.stop="emits('toggle', port)"
                                    >{{ port.style }}</q-btn
                                >
                            </q-item-section>
                        </q-item>

                        <q-item>
                            <q-item-section>
                                <q-item-label
                                    :style="{
                                        'font-size': `${10 + slider * 2}px`,
                                    }"
                                >
                                    Puertos de consola
                                </q-item-label>
                            </q-item-section>
                            <q-item-section
                                avatar
                                v-for="port in device.ports.filter(
                                    (p) => p.type === 'console'
                                )"
                                :key="`olt-port-${port.id}`"
                                style="padding: 2px !important"
                            >
                                <q-btn
                                    :size="currentSize"
                                    dense
                                    padding="6px"
                                    disable
                                    class="shadow-3"
                                    :id="port.element_id"
                                    :label="port.name"
                                    :color="port.color"
                                    :text-color="port.textColor"
                                    :style="port.border"
                                    :class="{
                                        'map-port-active': port.selected,
                                    }"
                                    @click.stop="emits('toggle', port)"
                                />
                            </q-item-section>
                        </q-item>

                        <template
                            v-for="group in groupedPorts"
                            :key="`olt-${device.id}-card-${group}`"
                        >
                            <q-item
                                style="
                                    min-height: 10px !important;
                                    margin-top: 15px;
                                "
                            >
                                <q-item-section>
                                    <q-item-label
                                        :style="{
                                            'font-size': `${10 + slider * 2}px`,
                                        }"
                                    >
                                        Tarjeta de servicio {{ group.card }}
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section avatar>
                                    <q-btn
                                        color="primary"
                                        icon="compare_arrows"
                                        size="xs"
                                        padding="5px"
                                        @click="
                                            changeDirection(
                                                device.id,
                                                group.card - 1,
                                                group.order === 'asc'
                                                    ? 'desc'
                                                    : 'asc'
                                            )
                                        "
                                        v-if="hasEdit"
                                    >
                                        <q-tooltip>
                                            Invertir puertos
                                        </q-tooltip>
                                    </q-btn>
                                </q-item-section>
                            </q-item>
                            <q-item
                                v-for="(row, indexRow) in group.ports"
                                :key="`olt-${device.id}-card-${group}-row-${indexRow}`"
                            >
                                <q-item-section
                                    avatar
                                    v-for="(port, portIndex) in row"
                                    :key="`port-${portIndex}`"
                                    style="padding: 2px !important"
                                >
                                    <q-btn
                                        :size="currentSize"
                                        dense
                                        padding="6px"
                                        class="shadow-3"
                                        :id="port.element_id"
                                        :label="port.name"
                                        :color="port.color"
                                        :text-color="port.textColor"
                                        :style="port.border"
                                        :class="{
                                            'map-port-active': port.selected,
                                        }"
                                        :disable="
                                            getConnectionByPort(port) !== null
                                        "
                                        @click.stop="emits('toggle', port)"
                                    />
                                </q-item-section>
                            </q-item>
                        </template>
                    </q-list>
                </q-card-section>
            </q-card>
        </q-expansion-item>
    </q-list>

    <q-dialog v-model="dialog" persistent @hide="onHideDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section><h6>Adicionar puertos</h6></q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="dialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />
            <q-card-section>
                <q-form ref="form" greedy>
                    <div class="row">
                        <div class="col">
                            <div>
                                <input
                                    type="checkbox"
                                    v-model="newPorts.wan_ports_add"
                                    id="wan-ports-add"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        margin-right: 10px;
                                    "
                                />
                                <label
                                    for="wan-ports-add"
                                    style="position: absolute"
                                    >Puertos wan</label
                                >
                            </div>
                            <q-input
                                v-model.number="newPorts.wan_ports"
                                type="number"
                                for="object-wan-ports"
                                :rules="[(val) => !!val || 'Requerido']"
                                outlined
                                dense
                                v-if="newPorts.wan_ports_add"
                            />

                            <div>
                                <input
                                    type="checkbox"
                                    v-model="newPorts.console_ports_add"
                                    id="console-ports-add"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        margin-right: 10px;
                                    "
                                />
                                <label
                                    for="console-ports-add"
                                    style="position: absolute"
                                    >Puertos de consola</label
                                >
                            </div>
                            <q-input
                                v-model.number="newPorts.console_ports"
                                type="number"
                                for="object-console-ports"
                                :rules="[(val) => !!val || 'Requerido']"
                                outlined
                                dense
                                v-if="newPorts.console_ports_add"
                            />

                            <div>
                                <input
                                    type="checkbox"
                                    v-model="newPorts.service_cards_add"
                                    id="service-cards-add"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        margin-right: 10px;
                                    "
                                />
                                <label
                                    for="service-cards-add"
                                    style="position: absolute"
                                    >Tarjetas de servicio</label
                                >
                            </div>
                            <template
                                v-for="(card, index) in newPorts.service_cards"
                                :key="`card-${index}`"
                            >
                                <label :for="`object-ports_x_card-${index}`"
                                    >Puertos de la tarjeta de servicio
                                    {{ index + 1 }}</label
                                ><q-input
                                    v-model.number="card.ports"
                                    type="number"
                                    :for="`object-ports_x_card-${index}`"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    outlined
                                    dense
                                >
                                    <template v-slot:append>
                                        <q-select
                                            v-model="card.order"
                                            filled
                                            borderless
                                            dense
                                            options-dense
                                            map-options
                                            emit-value
                                            :options="[
                                                {
                                                    label: '0...n',
                                                    value: 'asc',
                                                },
                                                {
                                                    label: 'n...0',
                                                    value: 'desc',
                                                },
                                            ]"
                                            style="width: 100px"
                                    /></template>
                                    <template v-slot:after v-if="index > 0">
                                        <q-btn
                                            round
                                            dense
                                            flat
                                            icon="delete"
                                            color="negative"
                                            style="margin: 15px"
                                            @click="
                                                newPorts.service_cards.splice(
                                                    index,
                                                    1
                                                )
                                            "
                                        />
                                    </template>
                                </q-input>
                            </template>
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important"
                ><q-btn
                    label="Agregar tarjeta"
                    color="secondary"
                    no-caps
                    @click="
                        newPorts.service_cards.push({
                            ports: null,
                            order: 'asc',
                        })
                    "
                    v-if="newPorts.service_cards_add"
                />
                <q-btn
                    :disable="
                        !newPorts.console_ports_add &&
                        !newPorts.service_cards_add &&
                        !newPorts.wan_ports_add
                    "
                    no-caps
                    color="primary"
                    label="Guardar"
                    @click="save"
                />
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    label="Cancelar"
                    @click="dialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>

    <dialog-confirm
        :show="showConfirm"
        message="Seguro que deseas eliminar este dispositivo"
        @yes="destroy"
        @no="showConfirm = false"
    />
</template>

<script setup>
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { message } from "../../../../../helpers/toastMsg";
import {
    addPorts,
    changeCardOLTDirection,
    destroyDevice,
} from "../../helper/devices-request";
import FormOltComponent from "./FormOltComponent.vue";
import ChangeConnectionsState from "../others/ChangeConnectionsState.vue";
import { computed, onMounted, ref, watch } from "vue";
import DialogConfirm from "../others/DialogConfirm.vue";
import {
    getColorFromPort,
    avaiablesSizes,
    getConnectionByPort,
} from "../../../../../composables/useMapConnections";

defineOptions({
    name: "OltComponent",
});

const props = defineProps({
    device: Object,
    hasEdit: Boolean,
});

const emits = defineEmits(["removed", "toggle", "update", "redraw"]);
const showConfirm = ref(false);

const dialog = ref(false);

const newPorts = ref({
    wan_ports_add: false,
    wan_ports: null,
    console_ports_add: false,
    console_ports: null,
    service_cards_add: false,
    service_cards: null,
});

const form = ref(null);

const slider = ref(0);
const currentSize = ref("xs");
const expanded = ref(true);

onMounted(() => {
    slider.value = 0;
    currentSize.value = "xs";
    expanded.value = true;
});

watch(
    () => newPorts.value.wan_ports_add,
    () => {
        newPorts.value.wan_ports = null;
    }
);

watch(
    () => newPorts.value.console_ports_add,
    () => {
        newPorts.value.console_ports = null;
    }
);

watch(
    () => newPorts.value.service_cards_add,
    (n) => {
        if (!n) {
            newPorts.value.service_cards = null;
        } else {
            newPorts.value.service_cards = [
                {
                    ports: null,
                    order: "asc",
                },
            ];
        }
    }
);

const wanPorts = computed(() => {
    const ports = props.device.ports.filter((p) => p.type === "in");
    ports.forEach((p) => {
        const { border, color, textColor } = getColorFromPort(p);
        p["color"] = color;
        p["border"] = border;
        p["textColor"] = textColor;
    });
    return ports;
});

const groupedPorts = computed(() => {
    const result = [];
    for (let i = 0; i < props.device.data.service_cards.length; i++) {
        result.push({
            card: i + 1,
            order: props.device.data.service_cards[i].order,
            ports: [],
        });
    }
    const pointsPerRow = 12;
    result.forEach((r) => {
        let ports = props.device.ports.filter((p) => p.card === r.card);
        if (r.order === "desc") {
            ports.reverse();
        }
        ports.forEach((p) => {
            const { border, color, textColor } = getColorFromPort(p);
            p["color"] = color;
            p["border"] = border;
            p["textColor"] = textColor;
        });
        for (let i = 0; i < ports.length; i += pointsPerRow) {
            r.ports.push(ports.slice(i, i + pointsPerRow));
        }
    });
    return result;
});

const destroy = async () => {
    showLoading();
    let result = await destroyDevice(props.device.id);
    hideLoading();
    if (result) {
        emits("removed", result);
        message("Dispositivo eliminado correctamente", "success");
    } else {
        message("No se ha podido eliminar este dispositivo", "error");
    }
    showConfirm.value = false;
};

const onHideDialog = () => {
    newPorts.value = {
        wan_ports: null,
        console_ports: null,
        service_cards: [
            {
                ports: null,
                order: "asc",
            },
        ],
    };
};

const save = () => {
    form.value.validate().then(async (success) => {
        if (success) {
            showLoading();
            const result = await addPorts(props.device.id, newPorts.value);
            hideLoading();
            if (result) {
                message("Puertos agregados correctamente");
                emits("update", result);
                dialog.value = false;
            } else {
                message(
                    "Ha ocurrido un error interno. Consulte al administrador",
                    "error"
                );
            }
        } else {
            errorValidation();
        }
    });
};

const changeDirection = async (device, card, order) => {
    showLoading();
    const result = await changeCardOLTDirection(device, card, order);
    hideLoading();
    if (result) {
        message("Tarjeta invertida correctamente");
        emits("update", result);
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};
</script>
