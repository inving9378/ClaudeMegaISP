<template>
    <q-dialog
        v-model="dialog"
        persistent
        @before-show="onShowDialog"
        @hide="emits('hide', currentObject)"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Configurar" }}
                            cliente
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
                        <div
                            class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12"
                        >
                            <label for="object-client_id">Cliente</label>
                            <q-select
                                v-model="currentObject.data.client_id"
                                :rules="[(val) => !!val || 'Requerido']"
                                :loading="loadingClient"
                                :options="currentOptions"
                                for="object-client_id"
                                option-value="id"
                                option-label="client_name_with_fathers_names"
                                outlined
                                dense
                                options-dense
                                use-input
                                input-debounce="0"
                                hide-bottom-space
                                emit-value
                                map-options
                                @virtual-scroll="onScroll"
                                @filter="filterFn"
                                @update:model-value="onUpdateClient"
                            />

                            <label for="object-description" class="q-mt-md"
                                >Descripción</label
                            ><q-input
                                v-model="currentObject.data.description"
                                type="textarea"
                                for="object-description"
                                :rows="3"
                                outlined
                                dense
                            />
                        </div>
                        <div
                            class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12"
                        >
                            <awesome-marker-icon
                                :color="currentObject.color"
                                :icon-color="currentObject.icon_color"
                                @change-color="
                                    (val) => (currentObject.color = val)
                                "
                                @change-icon-color="
                                    (val) => (currentObject.icon_color = val)
                                "
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    no-caps
                    color="primary"
                    label="Guardar"
                    :loading="loading"
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
</template>

<script setup>
import { ref, watch, nextTick } from "vue";
import { saveObject } from "../helper/request";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import AwesomeMarkerIcon from "./AwesomeMarkerIcon.vue";
import Swal from "sweetalert2";

defineOptions({
    name: "ClientComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    project: Object,
    layer: Object,
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);

const dialog = ref(false);
const loading = ref(false);
const loadingClient = ref(false);
const currentObject = ref(null);
const nextPage = ref(2);
const pageSize = ref(50);
const lastPage = ref(1);
const form = ref(null);
const currentOptions = ref([]);
const allOptions = ref([]);
const filterClient = ref(null);

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const setDefaultData = () => {
    currentObject.value = props.object
        ? { ...props.object }
        : {
              ...props.layer,
              data: {
                  client_id: null,
                  description: null,
              },
          };
};

const onShowDialog = () => {
    setDefaultData();
    loading.value = false;
    setDataFromServer();
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
    loading.value = true;
    const object = await saveObject(currentObject.value);
    if (currentObject.value.id) {
        emits("updated", object);
        message("Cliente modificado correctamente");
    } else {
        emits("created", object);
        message("Cliente agregado correctamente");
    }
    loading.value = false;
    currentObject.value = object;
    dialog.value = false;
};

const setDataFromServer = async () => {
    let client_id = currentObject.value.data.client_id;
    loadingClient.value = true;
    await axios
        .post("/maps/get-clients", {
            pageSize: pageSize.value,
            page: nextPage.value - 1,
            filter: filterClient.value,
            current: client_id,
        })
        .then((response) => {
            let data = response.data;
            let options = data.options;
            currentOptions.value.push(...options);
            allOptions.value.push(...options);
            let current = data.current;
            // if (current) {
            //     let opt = currentOptions.value.find((o) => o.id === current.id);
            //     if (!opt) {
            //         currentOptions.value.push(current);
            //         allOptions.value.push(current);
            //     }
            // }
            lastPage.value = Math.ceil(
                data.pagination.total / data.pagination.per_page
            );
        })
        .catch((error) => {
            currentOptions.value = [];
            allOptions.value = [];
            lastPage.value = 1;
        })
        .finally(() => {
            loadingClient.value = false;
        });
};

const filterFn = (val, update, abort) => {
    update(() => {
        if (val === "" || val === null || val.length > 2) {
            currentOptions.value = [];
            allOptions.value = [];
            nextPage.value = 2;
            filterClient.value = val === "" ? null : val;
            setDataFromServer();
        }
    });
};

const onScroll = async ({ to, ref }) => {
    const lastIndex = currentOptions.value.length - 1;
    if (
        loadingClient.value !== true &&
        nextPage.value < lastPage.value &&
        to === lastIndex
    ) {
        loadingClient.value = true;
        nextPage.value++;
        await setDataFromServer();
        nextTick(() => {
            ref.refresh();
            loadingClient.value = false;
        });
    }
};

const onUpdateClient = (val) => {
    if (val) {
        const client = currentOptions.value.find((c) => c.id === val);
        if (client.geodata !== null) {
            const geodata = client.geodata.split(",");
            const coords = props.layer.coords;
            if (
                geodata[0].trim() !== coords.lat &&
                geodata[1].trim() !== coords.lng
            ) {
                Swal.fire({
                    title: "¡Info!",
                    text: "El cliente seleccionado tiene una ubicación configurada y no coincide con la proporcionada actualmente, desea cambiar la ubicación proporcionada por la que tiene actualmente el cliente?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Si",
                    cancelButtonText: "No",
                }).then((result) => {
                    if (result.isConfirmed) {
                        currentObject.value.coords = {
                            lat: geodata[0].trim(),
                            lng: geodata[1].trim(),
                        };
                    }
                });
            }
        }
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
