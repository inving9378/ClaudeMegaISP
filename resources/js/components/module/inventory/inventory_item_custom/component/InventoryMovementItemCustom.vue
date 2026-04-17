<template>
    <div class="modal-body m-0" style="overflow-y: unset">
        <form id="form-inventory-movement-all" class="row" method="POST" @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)">
            <template v-for="val in fieldsJson">
                <ComponentFormDefault v-if="val.include" :json="val" :errors="dataForm.data.errors" :key="val"
                    v-model="dataForm.data[val.field]" @update-field="updateThisField" @clear-error="clearError" />
            </template>
            <template v-for="val in fieldsJsonSelectFrom">
                <ComponentFormDefault v-if="val.include" :json="val" :errors="dataForm.data.errors" :key="val"
                    v-model="dataForm.data[val.field]" @update-field="updateThisField" @clear-error="clearError" />
            </template>
            <template v-for="val in fieldsJsonSelectTo">
                <ComponentFormDefault v-if="val.include" :json="val" :errors="dataForm.data.errors" :key="val"
                    v-model="dataForm.data[val.field]" @update-field="updateThisField" @clear-error="clearError" />
            </template>
        </form>
    </div>
    <div class="modal-footer">
        <a class="btn btn-secondary mr-3" href="javascript:void(0)" @click="closeModal">
            Cerrar
        </a>

        <button class="btn btn-primary" form="form-inventory-movement-all" type="submit"
            :disabled="dataForm.data.errors.any()">
            Guardar
        </button>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import Swal from "sweetalert2";
import Form from "../../../../../helpers/Form";

export default {
    name: "InventoryMovementItemCustom",
    props: {
        filter_store: {
            type: Object,
            default: {},
        },
        custom_model_id: Number
    },
    emits: ["close-modal"],
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const dataForm = reactive({
            data: new Form({}),
        });
        const fieldsJson = ref({});

        const updateThisField = ({ field, value }) => {
            if (field == "select_from") {
                buildFieldJsonSelectFrom(value.value);
            }
            if (field == "select_to") {
                buildFieldJsonSelectTo(value.value);
            }
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsJson("InventoryMovementAll");
        };

        const getfieldsJson = async () => {
            let fieldJson = await getFieldJsonCustomItems();
            await updateFieldJsonFilterIfGetProp(fieldJson);
        };


        const getFieldJsonCustomItems = async () => {
            const entities = ['user', 'store', 'client'];
            // 1. Campos Estáticos base
            let fields = {
                id_item: {
                    field: `id_item`,
                    type: "select-component-client",
                    label: "Seleccione Articulo",
                    include: 1,
                    placeholder: "Seleccione",
                    position: 1,
                    class_col: "full",
                    search: {
                        "model": "App\\Models\\InventoryItem",
                        "id": "id",
                        "text": "name",
                        "scope": "hasStock",
                        "filter_where": {
                            0: {
                                field: 'inventory_item_custom_model_id',
                                value: `${props.custom_model_id}`
                            }
                        }
                    },
                },
                select_from: {
                    field: `select_from`,
                    type: "select-component",
                    label: "Selccione Origen",
                    include: 1,
                    placeholder: "Seleccione",
                    position: 2,
                    class_col: "partial",
                    options: { "store": "Almacén", "user": "Usuario", "client": "Cliente" },
                    class_label: "col-form-label pr-2",
                    class_field: "col-12",
                },
                select_to: {
                    field: `select_to`,
                    type: "select-component",
                    label: "Selccione Destino",
                    include: 1,
                    placeholder: "Seleccione",
                    position: 3,
                    class_col: "partial",
                    options: { "store": "Almacén", "user": "Usuario", "client": "Cliente" },
                    class_label: "col-form-label pr-2",
                    class_field: "col-12",
                },
                inventory_item_custom_model_id: {
                    field: `inventory_item_custom_model_id`,
                    type: "depend-field",
                    label: "inventory_item_custom_model_id",
                    include: 1,
                    placeholder: "inventory_item_custom_model_id",
                    position: 3,
                    value: `${props.custom_model_id}`
                }
            };

            // 2. Generar dinámicamente las combinaciones "from_to_enable"
            entities.forEach(from => {
                entities.forEach(to => {
                    const key = `${from}_to_${to}_enable`;
                    fields[key] = {
                        field: key,
                        type: "depend-field",
                        label: key,
                        include: 1,
                        placeholder: "",
                        position: 2, // Según tu ejemplo todos llevan 2
                    };
                });
            });

            // 3. Generar dinámicamente los campos "from" y "to" individuales
            entities.forEach(entity => {
                [`${entity}_from`, `${entity}_to`].forEach(key => {
                    fields[key] = {
                        field: key,
                        type: "depend-field",
                        label: key,
                        include: 1,
                        placeholder: "",
                        position: 2,
                    };
                });
            });
            return fields;
        }


        const updateFieldJsonFilterIfGetProp = async (fieldJson) => {
            if (
                props.filter_store.length > 0 &&
                fieldJson.id_item &&
                fieldJson.id_item.search
            ) {
                fieldJson.id_item.search.filter = buildFilterStoreRelation();
            }
            fieldsJson.value = fieldJson;
            dataForm.data = new Form(fieldsJson.value);
        };

        const buildFilterStoreRelation = () => {
            return {
                0: {
                    field_relation: "inventory_stores",
                    field: "modelable_id",
                    value: props.filter_store.inventory_store_id[0],
                },
                1: {
                    field_relation: "inventory_stores",
                    field: "modelable_type",
                    value: "App\\Models\\InventoryStore",
                },
            };
        };
        const buildFilterStoreFrom = (suffix) => {
            return props.filter_store.length > 0 && suffix === "from"
                ? {
                    model: "App\\Models\\InventoryStore",
                    id: "id",
                    text: "name",
                    filter: {
                        0: {
                            field: "id",
                            value: props.filter_store.inventory_store_id[0],
                        },
                    },
                }
                : {
                    model: "App\\Models\\InventoryStore",
                    id: "id",
                    text: "name",
                };
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const fieldsJsonSelectFrom = ref({});
        const fieldsJsonSelectTo = ref({});

        const fieldsSelect = ["user", "store", "client"];

        const buildValJson = (value, suffix) => {
            return {
                store: {
                    field: `${value}_${suffix}`,
                    type: "select-component",
                    label:
                        suffix === "from"
                            ? "Almacen Origen"
                            : "Almacen Destino",
                    include: 1,
                    placeholder: "Seleccione un Almacen",
                    position: 2,
                    class_label: "col-form-label pr-2",
                    class_field: "col-12",
                    class_col: "partial",
                    search: buildFilterStoreFrom(suffix),
                },
                user: {
                    field: `${value}_${suffix}`,
                    type: "select-component",
                    label:
                        suffix === "from"
                            ? "Usuario De Origen"
                            : "Usuario Destino",
                    include: 1,
                    placeholder: "Seleccione",
                    position: 2,
                    class_label: "col-form-label pr-2",
                    class_field: "col-12",
                    class_col: "partial",
                    search: {
                        model: "App\\Models\\User",
                        id: "id",
                        text: "name",
                        scope: "notClientRole",
                    },
                },
                client: {
                    field: `${value}_${suffix}`,
                    type: "select-component-client",
                    label:
                        suffix === "from"
                            ? "Cliente De Origen"
                            : "Cliente Destino",
                    include: 1,
                    placeholder: "Seleccione",
                    position: 2,
                    class_label: "col-form-label pr-2",
                    class_field: "col-12",
                    class_col: "partial",
                    search: {
                        model: "App\\Models\\ClientMainInformation",
                        id: "client_id",
                        text: "name",
                    },
                },
            };
        };

        const buildFieldJsonSelectFrom = (value) => {
            fieldsSelect.forEach((field) => {
                if (field != value) {
                    updateThisField({ field: `${field}_from`, value: null });
                }
            });
            fieldsJsonSelectFrom.value = {
                from: {
                    ...buildValJson(value, "from")[value],
                },
            };
        };

        const buildFieldJsonSelectTo = (value) => {
            fieldsSelect.forEach((field) => {
                if (field != value) {
                    updateThisField({ field: `${field}_to`, value: null });
                }
            });
            fieldsJsonSelectTo.value = {
                to: {
                    ...buildValJson(value, "to")[value],
                },
            };
        };

        const updateFieldsOlderCheckboxes = (from, to) => {
            let strField = `${from}_to_${to}_enable`;
            dataForm.data[strField] = true;
            dataForm.data.errors.clear(strField);
            updateThisField({ field: strField, value: true });
            updateOtherCheckboxesFalse(strField);
        };

        const arrayFieldChecks = [
            "user_to_user_enable",
            "user_to_client_enable",
            "user_to_store_enable",
            "store_to_store_enable",
            "store_to_user_enable",
            "store_to_client_enable",
            "client_to_client_enable",
            "client_to_user_enable",
            "client_to_store_enable",
        ];

        const updateOtherCheckboxesFalse = (selectedField) => {
            arrayFieldChecks.forEach((field) => {
                if (field != selectedField) {
                    dataForm.data[field] = false;
                    dataForm.data.errors.clear(field);
                    updateThisField({ field, value: false });
                }
            });
        };

        const onSubmit = () => {
            updateFieldsOlderCheckboxes(
                dataForm.data["select_from"],
                dataForm.data["select_to"]
            );
            dataForm.data
                .submit(
                    "post",
                    `/inventory/inventory_item/add_movement`,
                    "reset"
                )
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        closeModal();
                    }
                })
                .catch((error) => {
                    let fieldsErrors = error.response?.data?.errors;

                    if (fieldsErrors) {
                        // Construir un mensaje que incluya todos los errores
                        let errorMessage = "";
                        for (const field in fieldsErrors) {
                            if (fieldsErrors.hasOwnProperty(field)) {
                                errorMessage += `${fieldsErrors[field].join(
                                    ", "
                                )}\n`;
                            }
                        }

                        // Mostrar el mensaje de error con todos los errores
                        Swal.fire("Error", errorMessage, "error");
                    } else {
                        // Si no hay errores específicos, mostrar el mensaje general
                        Swal.fire(
                            "Error",
                            error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                            "error"
                        );
                    }
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            closeModal,
            fieldsJsonSelectFrom,
            fieldsJsonSelectTo,
        };
    },
};
</script>

<style scoped></style>
