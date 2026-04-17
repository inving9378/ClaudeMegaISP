<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        >{{
            defaultValue(object.service_ports?.map((s) => s.vlan).join(","))
        }}</q-item-label
    >

    <q-dialog v-model="showDialog" persistent @before-show="beforeShow">
        <q-card style="width: 450px; max-width: 55vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Actualizar VLANs</div>
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
                            for="add_vlans"
                            >VLANs</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="add_vlans"
                                :model-value="addVlans"
                                :options="vlans"
                                :required="true"
                                :multiple="true"
                                option-value="vlan"
                                @change="(name, val) => (addVlans = val)"
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
import { computed, ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { getNomenclatures } from "../../helper/request";
import { useUtils } from "../../../../../composables/useUtils";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormAttachmentsVlans",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);

const form = ref("form");

const saving = ref(false);

const addVlans = ref([]);
const vlans = ref([]);
const loading = ref(false);

const { defaultValue } = useUtils();

const removeVlans = computed(() => {
    return props.object.service_ports
        ?.map((s) => s.vlan)
        .filter((v) => !addVlans.value.includes(v));
});

const beforeShow = async () => {
    if (vlans.value.length === 0) {
        loading.value = true;
        let result = await getNomenclatures(["vlans"]);
        loading.value = false;
        if (result) {
            vlans.value = result.vlans.filter(
                (v) => v.olt_id === props.object.olt_id
            );
        }
    }
    addVlans.value = props.object.service_ports?.map((s) => s.vlan);
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(`/olts/onus/update-attached-vlans/${props.object.id}`, {
                    add_vlans: addVlans.value,
                    remove_vlans: removeVlans.value,
                })
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
