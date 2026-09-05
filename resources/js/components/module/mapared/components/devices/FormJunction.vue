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
                    <q-item-section><h6>Configurar empalme</h6></q-item-section>
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
                            <label for="object-type">Tipo</label>
                            <q-select
                                v-model="currentObject.type"
                                for="object-type"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                map-options
                                emit-value
                                :options="options"
                            />

                            <label for="object-loss_insertion_db"
                                >Pérdida por inserción</label
                            ><q-input
                                v-model.number="currentObject.loss_insertion_db"
                                type="number"
                                for="object-loss_insertion_db"
                                outlined
                                dense
                            />

                            <label
                                for="object-loss_reflexion_db"
                                class="q-mt-md"
                                >Pérdida por reflexión</label
                            ><q-input
                                v-model.number="currentObject.loss_reflexion_db"
                                type="number"
                                for="object-loss_reflexion_db"
                                outlined
                                dense
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
import { ref, watch } from "vue";
import { errorValidation } from "../../../../../helpers/toastMsg";
import { rules } from "../../../../../helpers/validations";

defineOptions({
    name: "FormJunction",
});

const props = defineProps({
    object: Object,
    show: Boolean,
});

const emits = defineEmits(["save", "hide"]);

const dialog = ref(false);
const currentObject = ref(null);
const form = ref(null);
const options = ref([
    {
        label: "Fusión",
        value: "fusion",
    },
    {
        label: "Conectorizado",
        value: "conectorizado",
    },
    {
        label: "Mecánico",
        value: "mecanico",
    },
    {
        label: "Línea",
        value: "linea",
    },
]);

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
              type: null,
              loss_insertion_db: null,
              loss_reflexion_db: null,
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
