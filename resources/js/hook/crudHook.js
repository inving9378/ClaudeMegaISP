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
    let counter = 0;
    let arrayFieldsValues = [];
    for (let variable of model) {
        let key = Object.keys(variable);
        if (idModels[key]) {
            id = idModels[key];
        }
        let result = await requestEditedFieldsById(variable[key], id);
        fields.value[counter++] = result;
        arrayFieldsValues[key] = result;
    }
    fieldsJson.value = arrayFieldsValues;

    // assign field to key in array
    allFields.value = _.mapKeys(
        _.flattenDeep(arrayOfObjectToArrayOfArray(fields.value)),
        (v) => v.field
    );
    dataForm.data = new Form(allFields.value);
};

export const getfieldsWithMultipleModel = async (model) => {
    let counter = 0;
    for (let variable of model) {
        let key = Object.keys(variable);
        let result = await requestFieldsByModule(variable[key]);
        fields.value[counter++] = result;
        fieldsJson.value[key] = result;
    }

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
