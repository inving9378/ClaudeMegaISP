<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
        id="form-list-template"
    >
        <div class="modal-body m-0 row">
            <template v-for="val in fieldsJson">
                <ComponentFormDefault
                    v-if="val.include"
                    :id="id"
                    :json="val"
                    :errors="dataForm.data.errors"
                    :key="val"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </template>
            <div>
                <div style="position: relative; float: right">
                    <q-btn label="Agregar" color="primary" @click="addRow" />
                </div>
            </div>
            <div class="col-12">
                <div class="col-12">
                    <q-list
                        v-if="listItems.length > 0"
                        bordered
                        separator
                        class="rounded-borders"
                    >
                        <q-item
                            v-for="(item, index) in listItems"
                            :key="item"
                            :id="`item-${index}`"
                        >
                            <div :class="`row mb-2`">
                                <div class="input-group">
                                    <label
                                        :for="`check_${index}`"
                                        :class="`d-md-block d-none col-md-3 col-form-label text-md-end pe-2 `"
                                    >
                                        Paso {{ index }}
                                    </label>

                                    <input
                                        type="text"
                                        :id="`check_${index}`"
                                        :name="`check_${index}`"
                                        :class="'form-control col-sm-12 col-md-9 ms-1'"
                                        autocomplete="off"
                                    />
                                    <div class="input-group-text">
                                        <span
                                            class="cursor-pointer"
                                            @click="removeRow(index)"
                                            >Eliminar
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </q-item>
                    </q-list>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a
                class="btn btn-secondary mr-3"
                href="javascript:void(0)"
                @click="closeModal"
            >
                Cerrar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
                :disabled="dataForm.data.errors.any()"
            >
                Guardar
            </button>
        </div>
    </form>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";

export default {
    name: "ListTemplateVerificationCrud",
    props: {
        action: String,
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const id = ref(null);
        let submitButtonAction =
            props.action == "/configuracion/list-template-verification/add"
                ? "Crear Lista de Plantillas de Verificación"
                : "Salvar Lista de Plantillas de Verificación";

        const listItems = ref([]);

        onMounted(() => {
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        const initComponent = async (action) => {
            console.log(action)
            let partnerId = getIdByAction(action);
            if (action == "/configuracion/list-template-verification/add") {
                id.value = null;
                await getfieldsJson("ListTemplateVerification");
            } else {
                id.value = partnerId;
                await getfieldsEdited("ListTemplateVerification", partnerId);
                addIndexToListItems(dataForm.data["checks"]);
            }
        };

        const addIndexToListItems = async (checks) => {
            const checkItems = JSON.parse(checks);
            for (const key in checkItems) {
                if (checkItems.hasOwnProperty(key)) {
                    const value = checkItems[key];
                    await addRow();
                    const input = $(`#check_${key}`);

                    // Verificar si el input existe antes de asignar el valor
                    if (input.length) {
                        input.val(value); // Asignar el valor al input
                        input.text(value); // Asegurarse de que el texto del input refleje el valor (si aplica)
                    } else {
                        console.log(
                            `Input con ID #check_${key} no encontrado.`
                        );
                    }
                }
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(
                action,
                "/configuracion/list-template-verification/update/"
            );
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = () => {
            const arrayData = getDataToList();
            dataForm.data["checks"] = arrayData;
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    emit("close-modal");
                });
        };

        const getDataToList = () => {
            const arrayData = [];
            const form = document.getElementById("form-list-template");

            // Selecciona todos los inputs cuyo ID comienza con "check_"
            const inputs = form.querySelectorAll('input[id^="check_"]');

            // Recorrer los inputs encontrados y hacer algo con ellos
            inputs.forEach((input) => {
                arrayData.push(input.value);
            });
            return arrayData;
        };

        const addRow = async () => {
            listItems.value.push(`${listItems.value.length + 1}`);
        };

        const removeRow = (index) => {
            if (index >= 0 && index < listItems.value.length) {
                listItems.value.splice(index, 1);
            } else {
                console.log("Índice inválido.");
            }
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal,
            id,

            listItems,
            addRow,
            removeRow,
        };
    },
};
</script>

<style scoped></style>
