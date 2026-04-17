<template>
    <q-item-label class="cursor-pointer text-primary" @click="showDialog = true"
        >{{ defaultValue(object[field]) }}
        <span v-if="object.custom_template_name"
            >({{ object.custom_template_name }})</span
        ></q-item-label
    >

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 500px; max-width: 60vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Cambiar tipo ONU</div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-form ref="form" greedy>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="onu_type"
                            >Tipo ONU</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="onu_type"
                                :model-value="
                                    formData?.type?.attr_to_server?.onu_type
                                "
                                :options="typeOnus"
                                :required="true"
                                option-label="name"
                                option-value="name"
                                :loading="loading"
                                @change="
                                    (name, val) => {
                                        formData['type']['attr_to_server'][
                                            name
                                        ][name] = val;
                                    }
                                "
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="onu_type"
                            >Perfil personalizado</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="custom_template_name"
                                :model-value="
                                    formData?.profile?.attr_to_server
                                        ?.custom_template_name
                                "
                                :options="profiles"
                                :clearable="false"
                                @change="
                                    (name, val) => {
                                        formData['profile']['attr_to_server'][
                                            name
                                        ] = val;
                                    }
                                "
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="save"
                />
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
            <q-inner-loading
                :showing="saving"
                label="Procesando, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </q-card>
    </q-dialog>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";
import { useUtils } from "../../../../../composables/useUtils";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormOnuType",
});

const props = defineProps({
    object: Object,
    field: {
        type: String,
        default: "olt_name",
    },
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");

const formData = ref({
    type: null,
    profile: null,
});

const saving = ref(false);

const typeOnus = ref([]);
const loading = ref(false);

const profiles = ref([
    {
        label: "Ninguno",
        value: null,
    },
]);

onMounted(() => {
    for (let i = 1; i < 8; i++) {
        profiles.value.push({
            label: `Genérico ${i}`,
            value: `Generic_${i}`,
        });
    }
});

const { defaultValue } = useUtils();

const beforeShow = async () => {
    if (typeOnus.value.length === 0) {
        loading.value = true;
        let result = await getNomenclatures(["type_onus"]);
        loading.value = false;
        if (result) {
            typeOnus.value = result.type_onus;
        }
    }
    formData.value = {
        type: {
            attr_to_server: {
                onu_type: props.object.onu_type_name,
            },
        },
        profile: {
            attr_to_server: {
                custom_template_name: props.object.custom_template_name,
            },
        },
    };
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(
                    `/olts/onus/change-onu-type/${props.object.id}`,
                    formData.value
                )
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("ONU actualizada correctamente", "success");
                        showDialog.value = false;
                    }
                })
                .catch((err) => {
                    message(
                        "Ha ocurrido un error al procesar la solicitud",
                        "error"
                    );
                })
                .finally(() => {
                    saving.value = false;
                });
        } else {
            errorValidation();
        }
    });
};
</script>
