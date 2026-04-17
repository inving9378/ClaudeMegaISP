<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        v-if="
            hasPermission?.data.canView('onu_edit') &&
            ['Enabled', 'Disabled'].includes(onu.catv)
        "
    >
        <div class="object-field">
            <q-checkbox
                v-model="catv"
                label="Habilitado"
                color="primary"
                dense
                @update:model-value="save"
            /><q-inner-loading
                :showing="saving"
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </div>
    </q-item-label>
    <q-item-label v-else>{{ onu.catv }}</q-item-label>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { message } from "../../../../../helpers/toastMsg";
import axios from "axios";

defineOptions({
    name: "FormCatv",
});

const props = defineProps({
    onu: Object,
    hasPermission: Object,
});

const emits = defineEmits(["update"]);

const showDialog = ref(false);
const saving = ref(false);
const catv = ref(null);

onMounted(() => {
    catv.value = props.onu.catv === "Enabled";
});

const save = async (val) => {
    saving.value = true;
    await axios
        .post(`/olts/onus/set-catv/${props.onu.id}`, {
            status: val ? "enable" : "disable",
        })
        .then((res) => {
            let response = res.data;
            if (!response.success) {
                message(response.error ?? response.message, "error");
            } else {
                emits("update", response.onu);
                message(
                    `CATV ${
                        val ? "habilitado" : "deshabilitado"
                    } correctamente`,
                    "success"
                );
                showDialog.value = false;
            }
        })
        .catch((err) => {
            message("Ha ocurrido un error al procesar la solicitud", "error");
        })
        .finally(() => {
            saving.value = false;
        });
};
</script>
