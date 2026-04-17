import { onMounted, reactive, ref, watch } from "vue";

export const setDefaultValue = (event) => {
    let check = event.target.checked;
    let data = event.target.getAttribute('data');
    let [field, module_id] = data.split(', ');
    if (check) {
        let val = event.target.value;
        if (field == 'colony_id' || field == 'state_id' || field == 'municipality_id') {
            setDefaultValueColony(event)
        } else {
            saveOrDeleteDefaultValue(val, field, module_id);
        }


    } else {
        if (field == 'colony_id' || field == 'state_id' || field == 'municipality_id') {
            setDefaultValueColony(event)
        } else {
            saveOrDeleteDefaultValue(null, field, module_id);
        }

    }
};

export const setDefaultValueSelect = (val, field, module_id) => {
    if (Array.isArray(val)) {
        val = val.join(","); // Convierte el array en un string separado por comas
    }
    saveOrDeleteDefaultValue(val, field, module_id);
};

export const saveOrDeleteDefaultValue = (val, field, module_id) => {
    let dataDefault = {
        value: val,
        field: field,
        module_id: module_id,
    };
    axios
        .post(`/save-or-delete-default-value`, dataDefault)
        .then((response) => {
        })
        .catch((error) => {
        });
};


const valToDef = ref({});

const setDefaultValueColony = (event) => {
    let check = event.target.checked;
    let data = event.target.getAttribute("data");
    let val = event.target.value;
    let [field, module_id] = data.split(", ");
    if (check) {
        valToDef.value[field] = val;
        saveOrDeleteDefaultValue(
            valToDef.value,
            "colony_id",
            module_id
        );
    } else {
        delete valToDef.value[field];
        saveOrDeleteDefaultValue(
            valToDef.value,
            "colony_id",
            module_id
        );
    }
};

export const uncheck = (field, module_id) => {
    const $checkbox = $(
        'input[type="checkbox"][data="' +
        field +
        ", " +
        module_id +
        '"]'
    );
    $checkbox.prop("checked", false);
};
export const isEdit = window.location.href.includes('editar');

