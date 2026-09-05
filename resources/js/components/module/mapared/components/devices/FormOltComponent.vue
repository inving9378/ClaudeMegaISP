<template>
    <template v-if="object"
        ><q-btn
            color="primary"
            icon="edit"
            round
            size="sm"
            @click="dialog = true"
            ><q-tooltip>Editar</q-tooltip></q-btn
        ></template
    >
    <template v-else>
        <q-btn
            color="primary"
            icon="mdi-server-network"
            round
            style="font-size: 11px"
            @click="dialog = true"
            v-if="btn"
            ><q-tooltip>Agregar OLT</q-tooltip></q-btn
        >
        <q-item clickable style="padding: 5px" @click="dialog = true" v-else>
            <q-item-section avatar>
                <q-icon name="mdi-server-network" />
            </q-item-section>
            <q-item-section style="margin-left: 5px"> OLT </q-item-section>
        </q-item>
    </template>
    <q-dialog v-model="dialog" persistent @before-show="onShowDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Adicionar" }}
                            olt
                        </h6></q-item-section
                    >
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
                            <label for="object-name">Nombre</label>
                            <q-input
                                v-model="currentObject.name"
                                for="object-name"
                                :rules="[(val) => !!val || 'Requerido']"
                                outlined
                                dense
                            />
                            <template v-if="!object">
                                <label for="object-wan-ports">Puertos wan</label
                                ><q-input
                                    v-model.number="
                                        currentObject.data.wan_ports
                                    "
                                    type="number"
                                    for="object-wan-ports"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    outlined
                                    dense
                                />
                                <label for="object-console-ports"
                                    >Puertos de consola</label
                                ><q-input
                                    v-model.number="
                                        currentObject.data.console_ports
                                    "
                                    type="number"
                                    for="object-console-ports"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    outlined
                                    dense
                                />
                                <template
                                    v-for="(card, index) in currentObject.data
                                        .service_cards"
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
                                                    currentObject.data.service_cards.splice(
                                                        index,
                                                        1
                                                    )
                                                "
                                            />
                                        </template>
                                    </q-input>
                                </template>
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
                        currentObject.data.service_cards.push({
                            ports: null,
                            order: 'asc',
                        })
                    "
                />
                <q-btn no-caps color="primary" label="Guardar" @click="save" />
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
</template>

<script setup>
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import { saveDevice } from "../../helper/devices-request";

defineOptions({
    name: "FormOltComponent",
});

const props = defineProps({
    object: Object,
    layer_id: {
        type: Number,
        default: null,
    },
    parent_id: {
        type: Number,
        default: null,
    },
    btn: {
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(["update"]);

const dialog = ref(false);
const currentObject = ref(null);
const form = ref(null);

const setDefaultData = () => {
    currentObject.value = props.object
        ? { ...props.object }
        : {
              name: "Olt",
              layer_id: props.layer_id,
              parent_id: props.parent_id,
              position_x: 20,
              position_y: 20,
              type: "olt",
              data: {
                  wan_ports: null,
                  console_ports: null,
                  service_cards: [
                      {
                          ports: null,
                          order: "asc",
                      },
                  ],
              },
          };
};

const onShowDialog = () => {
    setDefaultData();
};

const save = () => {
    form.value.validate().then((success) => {
        if (success) {
            storeOrSave();
        } else {
            errorValidation();
        }
    });
};

const storeOrSave = async () => {
    showLoading();
    const result = await saveDevice(currentObject.value);
    hideLoading();
    if (result) {
        if (currentObject.value.id) {
            message("Dispositivo modificado correctamente");
        } else {
            message("Dispositivo agregado correctamente");
        }
        emits("update", result);
        dialog.value = false;
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};
</script>

<style scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
</style>
