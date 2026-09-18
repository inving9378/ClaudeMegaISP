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
/**
 * `isEdit` decide si un select-component muestra el checkbox "usar como valor
 * por defecto" (v-if="!isEdit" en toda la familia de shared/Select*Component.vue).
 *
 * Antes era una constante calculada UNA sola vez al cargar el bundle
 * (`window.location.href.includes('editar')`), sin reaccionar a la navegación
 * SPA (spa-nav.js desmonta y remonta el árbol de Vue en cada navegación, pero
 * el módulo JS —y su valor ya calculado— sobreviven durante toda la sesión de
 * la pestaña). Resultado: si la PRIMERA página cargada era "crear" y luego se
 * navegaba a "editar" (o viceversa) sin recargar, isEdit se quedaba con el
 * valor de la primera carga — el checkbox aparecía o desaparecía al revés de
 * lo que correspondía a la pantalla real.
 *
 * Ahora es un ref reactivo: los ~12 componentes que lo importan (`import {
 * isEdit } from ".../comunValues"`) y lo devuelven tal cual desde su
 * `setup()` siguen funcionando igual en el template (Vue desenvuelve refs de
 * nivel superior devueltos por setup()), pero ahora sí se actualiza.
 * `refreshIsEdit(url)` la llama spa-nav.js justo antes de remontar el árbol
 * en cada navegación, para que los componentes que se monten ya vean el
 * valor correcto desde su primer render.
 */
export const isEdit = ref(window.location.href.includes('editar'));

export const refreshIsEdit = (url) => {
    const target = url ?? window.location.href;
    isEdit.value = target.includes('editar');
};

