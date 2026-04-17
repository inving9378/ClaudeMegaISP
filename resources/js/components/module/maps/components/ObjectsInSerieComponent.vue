<template>
    <q-dialog
        v-model="dialog"
        persistent
        seamless
        position="right"
        @before-show="onShowDialog"
        @hide="emits('hide')"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>Adicionar elementos en serie</h6></q-item-section
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
                            <label for="object-type">Tipo de objeto</label>
                            <q-select
                                v-model="currentObject.type"
                                for="object-type"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                option-value="dialog"
                                option-label="text"
                                :options="
                                    options.filter((m) =>
                                        permissons.data.canView(
                                            `maps_${m.dialog}_add`
                                        )
                                    )
                                "
                                :disable="start"
                            />

                            <label for="object-name" class="q-mt-sm"
                                >Nombre</label
                            >
                            <q-input
                                v-model="currentObject.data.name"
                                for="object-name"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                :disable="start"
                            />

                            <label for="object-start" class="q-mt-sm"
                                >Empezar en</label
                            >
                            <q-input
                                type="number"
                                v-model="currentObject.start"
                                for="object-start"
                                :rules="[
                                    (val) => rules.required(val),
                                    (val) =>
                                        val >= 0 ||
                                        'Debe ser mayor o igual a 0',
                                ]"
                                outlined
                                dense
                                :disable="start"
                            />
                        </div>
                        <div
                            class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12"
                        >
                            <awesome-marker-icon
                                :color="currentObject.color"
                                :icon-color="currentObject.icon_color"
                                :disable="start"
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
                    label="Iniciar"
                    @click="drawObjects"
                    v-if="!start"
                />
                <q-btn
                    no-caps
                    color="primary"
                    label="Terminar"
                    @click="
                        () => {
                            emits('end');
                            start = false;
                        }
                    "
                    v-if="start"
                />
                <q-btn
                    no-caps
                    color="primary"
                    label="Finalizar"
                    @click="dialog = false"
                    v-if="start"
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
import { onMounted, ref, watch } from "vue";
import { errorValidation } from "../../../../helpers/toastMsg";
import AwesomeMarkerIcon from "./AwesomeMarkerIcon.vue";
import { rules } from "../../../../helpers/validations";
import { menuOptions } from "../helper/mapUtils";

defineOptions({
    name: "ObjectsInSerieComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    project: Object,
    currentName: String,
    permissons: Object,
});

const emits = defineEmits(["start", "end", "hide"]);

const dialog = ref(false);
const loading = ref(false);
const currentObject = ref(null);
const form = ref(null);
const start = ref(false);

const options = ref([]);
const excludes = [
    "region",
    "route",
    "folder",
    "elements_in_serie",
    "note",
    "client",
];

onMounted(() => {
    options.value = menuOptions.filter((m) => !excludes.includes(m.dialog));
});

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

watch(
    () => props.currentName,
    (n) => {
        if (n) {
            currentObject.value.data.name = n;
        }
    }
);

const setDefaultData = () => {
    start.value = false;
    currentObject.value = {
        project_id: props.project.id,
        color: "#5bc0de",
        icon_color: "#FFFFFF",
        coords: null,
        type: null,
        start: null,
        data: {
            name: null,
        },
    };
};

const drawObjects = () => {
    form.value.validate().then((success) => {
        if (success) {
            start.value = true;
            emits("start", currentObject.value);
        } else {
            errorValidation();
        }
    });
};

const onShowDialog = () => {
    setDefaultData();
    loading.value = false;
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
