<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has('state_id') && 'has-danger'}`"
    >
        <label
            for="state_id"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Estado
        </label>
        <div class="col-sm-12 col-md-9">
            <select
                :class="{ 'form-control': true }"
                name="state_id"
                id="state_id"
                v-model="valState"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`state_id, ${property.module_id}`"
                :val="valState ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div v-if="errors.has('state_id')" class="pristine-error text-help">
                {{ errors.get("state_id") }}
            </div>
        </div>
    </div>

    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has('municipality_id') && 'has-danger'}`"
    >
        <label
            for="municipality_id"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Municipio
        </label>
        <div class="col-sm-12 col-md-9">
            <select
                :class="{ 'form-control': true }"
                name="municipality_id"
                id="municipality_id"
                v-model="valMunicipio"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`municipality_id, ${property.module_id}`"
                :val="valMunicipio ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div
                v-if="errors.has('municipality_id')"
                class="pristine-error text-help"
            >
                {{ errors.get("municipality_id") }}
            </div>
        </div>
    </div>

    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has('colony_id') && 'has-danger'}`"
    >
        <label
            for="colony_id"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            Colonia
        </label>
        <div
            class="col-sm-12 col-md-9"
            :style="{ border: errors.has('colony_id') ? '1px solid red' : '' }"
        >
            <select
                :class="{ 'form-control': true }"
                name="colony_id"
                id="colony_id"
                v-model="valColony"
            ></select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`colony_id, ${property.module_id}`"
                :val="valColony ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
            <div
                v-if="errors.has('colony_id')"
                class="pristine-error text-help"
            >
                {{ errors.get("colony_id") }}
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import {
    convertToSelect2,
    getOptions,
    selectTransform,
} from "../helpers/Transform";
import { setDefaultValue, uncheck, isEdit } from "../hook/comunValues";
import { getValueDB } from "../helpers/Request";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

export default {
    name: "Select2EstadoMunicipioColoniaComponent",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Number,
    },
    components: {
        InputCheckboxDefaultVal,
    },
    emits: ["update-field", "change", "clear-error"],
    setup(props, { emit }) {
        const select2 = ref({
            state: {},
            municipio: {},
            colony: {},
        });

        const valuesDb = ref({
            state_id: "",
            municipality_id: "",
            colony_id: "",
        });

        const defaultValue =
            typeof props.property.default_value === "string"
                ? JSON.parse(props.property.default_value)
                : props.property.default_value;

        const valState = ref();
        const valMunicipio = ref();
        const valColony = ref();

        const optionsState = ref([]);
        const optsState = reactive(optionsState);

        const optionsMunicipio = ref([]);
        const optsMunicipio = reactive(optionsMunicipio);

        const optionsColony = ref([]);
        const optsColony = reactive(optionsColony);
        const isFirstLoad = ref(true);

        watch(valState, () => {
            emit("update-field", { value: valState, field: "state_id" });
            uncheck("state_id", props.property.module_id);
            uncheck("municipality_id", props.property.module_id);
            uncheck("colony_id", props.property.module_id);
            changeMunicipio(valState.value);
            changeColony("changeState");
        });
        watch(valMunicipio, () => {
            emit("update-field", {
                value: valMunicipio,
                field: "municipality_id",
            });
            uncheck("municipality_id", props.property.module_id);
            uncheck("colony_id", props.property.module_id);
            changeColony(valMunicipio.value);
        });
        watch(valColony, () => {
            emit("clear-error", "colony_id");
            uncheck("colony_id", props.property.module_id);
            emit("update-field", { value: valColony, field: "colony_id" });
        });

        onMounted(async () => {
            optionsState.value = await getOptions({
                model: "App\\Models\\State",
                id: "id",
                text: "name",

            });
            let defaultStateValue = "";
            let defaultMunicipioValue = "";
            let defaultColonyValue = "";

            if (props.modelValue) {
                let response = await getValueDB(props.property.label, [
                    "state_id",
                    "municipality_id",
                    "colony_id",
                ]);
                valuesDb.value.state_id = response.state_id;
                valuesDb.value.municipality_id = _.toInteger(
                    response.municipality_id
                );
                valuesDb.value.colony_id = _.toInteger(response.colony_id);

                if (valuesDb.value.state_id) {
                    await changeMunicipio(valuesDb.value.state_id);
                    defaultStateValue = valuesDb.value.state_id;
                }
                if (valuesDb.value.municipality_id) {
                    await changeColony(valuesDb.value.municipality_id);
                    defaultMunicipioValue = valuesDb.value.municipality_id;
                }
                if (valuesDb.value.colony_id) {
                    defaultColonyValue = valuesDb.value.colony_id;
                }
            } else {
                if (typeof defaultValue === "object" && defaultValue) {
                    if (defaultValue.state_id) {
                        const defStateValue = ref(defaultValue.state_id);
                        emit("update-field", {
                            value: defStateValue,
                            field: "state_id",
                        });
                        await changeMunicipio(defaultValue.state_id);
                        defaultStateValue = defaultValue.state_id;
                    }
                    if (defaultValue.municipality_id) {
                        const defMunicipioValue = ref(
                            defaultValue.municipality_id
                        );
                        emit("update-field", {
                            value: defMunicipioValue,
                            field: "municipality_id",
                        });
                        await changeColony(defaultValue.municipality_id);
                        defaultMunicipioValue = defaultValue.municipality_id;
                    }
                    if (defaultValue.colony_id) {
                        const defColonyValue = ref(defaultValue.colony_id);
                        emit("update-field", {
                            value: defColonyValue,
                            field: "colony_id",
                        });
                        defaultColonyValue = defaultValue.colony_id;
                    }
                }
            }

            $(document).ready(async () => {
                select2.value.state = await convertToSelect2(
                    "state_id",
                    optionsState,
                    defaultStateValue,
                    "Seleccionar Estado",
                    true
                );
                select2.value.municipio = await convertToSelect2(
                    "municipality_id",
                    optionsMunicipio,
                    defaultMunicipioValue,
                    "Seleccionar Municipio",
                    true
                );
                select2.value.colony = await convertToSelect2(
                    "colony_id",
                    optionsColony,
                    defaultColonyValue,
                    "Seleccionar Colonia",
                    true
                );
            });
        });

        const changeMunicipio = async (state_id) => {
            optionsMunicipio.value = state_id
                ? await getOptions({
                      model: "App\\Models\\Municipality",
                      id: "id",
                      text: "name",
                      filter: [
                          {
                              field_relation: "state",
                              field: "id",
                              value: state_id || null,
                          },
                      ],
                  })
                : await getOptions({
                      model: "App\\Models\\Municipality",
                      id: "id",
                      text: "name",
                      alfabetic:true
                  });

            if (_.size(select2.value.municipio)) {
                select2.value.municipio.destroy();
                select2.value.municipio = await convertToSelect2(
                    "municipality_id",
                    optionsMunicipio,
                    valuesDb.value.municipality_id,
                    "Seleccionar Municipio",
                    true
                );
                valuesDb.value.municipality_id = "";
            }
        };

        const changeColony = async (municipality_id) => {
            if (municipality_id == "changeState") {
                optionsColony.value = null;
            } else {
                optionsColony.value = municipality_id
                    ? await getOptions({
                          model: "App\\Models\\Colony",
                          id: "id",
                          text: "name",
                          filter: [
                              {
                                  field_relation: "municipio",
                                  field: "id",
                                  value: municipality_id || null,
                              },
                          ],
                      })
                    : await getOptions({
                          model: "App\\Models\\Colony",
                          id: "id",
                          text: "name",
                      });
            }

            if (_.size(select2.value.colony)) {
                select2.value.colony.destroy();
                select2.value.colony = await convertToSelect2(
                    "colony_id",
                    optionsColony,
                    valuesDb.value.colony_id,
                    "Seleccionar Colonia",
                    true
                );
                valuesDb.value.colony_id = "";
            }
        };

        return {
            valState,
            valMunicipio,
            valColony,
            optsState,
            optsMunicipio,
            optsColony,
            setDefaultValue,
            isEdit
        };
    },
};
</script>

<style scoped></style>
