<template>
    <q-dialog
        v-model="dialog"
        persistent
        @before-show="onShowDialog"
        @hide="emits('hide')"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section><h6>Zona</h6></q-item-section>
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
                            <q-select
                                v-model="currentObject.zone"
                                for="object-zone"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                map-options
                                emit-value
                                option-value="zone"
                                option-label="zone"
                                :options="options"
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn no-caps color="primary" label="Aceptar" @click="save" />
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
import { onMounted, ref, watch } from "vue";
import { errorValidation } from "../../../../../helpers/toastMsg";
import { rules } from "../../../../../helpers/validations";
import { zones } from "../../helper/request";

defineOptions({
    name: "FormZone",
});

const props = defineProps({
    object: Object,
    show: Boolean,
});

const emits = defineEmits(["save", "hide"]);

const dialog = ref(false);
const currentObject = ref(null);
const form = ref(null);
const options = ref([]);

onMounted(async () => {
    const result = await zones();
    if (result) {
        options.value = result;
    }
});

watch(
    () => props.show,
    (n) => {
        dialog.value = n;
    }
);

const setDefaultData = () => {
    currentObject.value = props.object
        ? { ...props.object }
        : {
              zone: null,
          };
};

const onShowDialog = () => {
    setDefaultData();
};

const save = () => {
    form.value.validate().then((success) => {
        if (success) {
            emits("save", currentObject.value);
        } else {
            errorValidation();
        }
    });
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
