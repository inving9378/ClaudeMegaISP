<template>
    <div class="accordion-item">
        <div
            id="panelsStayOpen-collapseThree"
            class="accordion-collapse collapse show"
            :aria-labelledby="`panelsStayOpen-${id}`"
        >
            <div class="accordion-body">
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                >
                    <div class="row">
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
                    </div>
                    <div class="text-end">
                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import ComponentFormDefault from "../../../../../ComponentFormDefault.vue";
import Swal from "sweetalert2";
import Form from "../../../../../../helpers/Form";
import { requestEditedFieldsById } from "../../../../../../helpers/Request";

export default {
    name: "ContentConfig",
    props: {
        id: String | Number,
        module: String,
        title: String,
    },
    components: {
        ComponentFormDefault,
    },
    emits: ["close-modal"],
    setup(props, { emit }) {
        const dataForm = reactive({
            data: new Form({}),
        });
        const fieldsJson = ref({});
        const id = ref(null);
        let submitButtonAction = "Guardar";

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsEdited(props.module, props.id);
        };

        const getfieldsEdited = async (model, id) => {
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            dataForm.data
                .uploadFile(
                    `/configuracion/finance-notification/update/${props.id}`
                )
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        initComponent();
                    } else {
                        Swal.fire("Error", message, "error");
                    }
                })
                .catch((error) => {
                    Swal.fire(
                        "Error",
                        error.response?.data?.message ||
                            "Ocurrió un error inesperado.",
                        "error"
                    );
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            id,
        };
    },
};
</script>

<style scoped></style>
