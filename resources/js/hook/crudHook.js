import { reactive, ref } from "vue";
import Form from "../helpers/Form";
import {
    requestFieldsByModule,
    requestEditedFieldsById,
    requestGeneralEditedFields,
    requestFieldsByModuleIdRelation,
} from "../helpers/Request";
import { arrayOfObjectToArrayOfArray } from "../helpers/Transform";

export const fieldsJson = ref({});
export const fields = ref([]);
export const allFields = ref([]);
export const dataForm = reactive({
    data: new Form({}),
});

// Resetea el estado compartido del formulario CRUD. Se llama al desmontar una ficha/CRUD
// (p.ej. ClientCrud.onUnmounted) para que este singleton module-level no arrastre los datos
// del registro anterior entre navegaciones SPA (el bundle JS no se recarga → estos exports
// persisten). Cada CRUD re-puebla dataForm en su propio onMounted, así que dejarlo vacío es seguro.
export const resetCrudForm = () => {
    dataForm.data = new Form({});
    fields.value = [];
    fieldsJson.value = {};
    allFields.value = {};
};

export const getfieldsJson = async (model) => {
    fieldsJson.value = await requestFieldsByModule(model);
    dataForm.data = new Form(fieldsJson.value);
};

export const getfieldsJsonRelation = async (
    module,
    parent_module,
    id,
    relation
) => {
    fieldsJson.value = await requestFieldsByModuleIdRelation(
        module,
        parent_module,
        id,
        relation
    );
    dataForm.data = new Form(fieldsJson.value);
};

export const getfieldsEdited = async (model, id) => {
    fieldsJson.value = await requestEditedFieldsById(model, id);
    dataForm.data = new Form(fieldsJson.value);
};

export const getfieldsGeneralEdited = async (model) => {
    fieldsJson.value = await requestGeneralEditedFields(model);
    dataForm.data = new Form(fieldsJson.value);
};

export const getfieldsEditedWithMultipleModel = async (
    model,
    id,
    idModels = {}
) => {
    // Pre-compute the id for each model respecting cascading idModels overrides
    let currentId = id;
    const requests = model.map((variable) => {
        const key = Object.keys(variable)[0];
        if (idModels[key]) {
            currentId = idModels[key];
        }
        return { key, modelName: variable[key], id: currentId };
    });

    // Fetch all models in parallel instead of sequentially
    const results = await Promise.all(
        requests.map(({ modelName, id }) => requestEditedFieldsById(modelName, id))
    );

    const arrayFieldsValues = [];
    results.forEach((result, index) => {
        const { key } = requests[index];
        fields.value[index] = result;
        arrayFieldsValues[key] = result;
    });

    fieldsJson.value = arrayFieldsValues;

    // assign field to key in array
    allFields.value = _.mapKeys(
        _.flattenDeep(arrayOfObjectToArrayOfArray(fields.value)),
        (v) => v.field
    );
    dataForm.data = new Form(allFields.value);
};

export const getfieldsWithMultipleModel = async (model) => {
    const requests = model.map((variable) => {
        const key = Object.keys(variable)[0];
        return { key, modelName: variable[key] };
    });

    // Fetch all models in parallel
    const results = await Promise.all(
        requests.map(({ modelName }) => requestFieldsByModule(modelName))
    );

    results.forEach((result, index) => {
        const { key } = requests[index];
        fields.value[index] = result;
        fieldsJson.value[key] = result;
    });

    // assign field to key in array
    allFields.value = _.mapKeys(
        _.flattenDeep(arrayOfObjectToArrayOfArray(fields.value)),
        (v) => v.field
    );
    dataForm.data = new Form(allFields.value);
};

export const updateThisField = ({ field, value }) => {
    dataForm.data[field] = value;
};

export const clearError = ({ field }) => {
    dataForm.data.errors.clear(field);
};
