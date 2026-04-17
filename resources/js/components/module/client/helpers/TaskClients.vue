<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
            <template v-for="val in fieldsJson">
                <ComponentFormDefault
                    v-if="val.include"
                    :json="val"
                    :errors="dataForm.data.errors"
                    :key="dataForm.data[val]"
                    v-model="dataForm.data[val.field]"
                    @update-field="updateThisField"
                    @clear-error="clearError"
                />
            </template>
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
import { onMounted, ref, watch, reactive } from "vue";
import { clearError } from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";
import { position, address } from "../../../../helpers/googleMapsVariables";
import QColorPicker from "../../../../shared/QColorPicker.vue";
import Form from "../../../../helpers/Form";
import { requestFieldsByModule } from "../../../../helpers/Request";
import {
    clientMainInformationId,
    getListTemplate,
} from "../info/comun_variable";

export default {
    name: "TaskClients",
    props: {
        action: String,
        customerId: Number,
    },
    components: {
        ComponentFormDefault,
        QColorPicker,
    },
    setup(props, { emit }) {
        const id = ref(null);
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            clientMainInformationId.value = props.customerId;
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent();
            }
        );

        const initComponent = async () => {
            await getfieldsJson("Task");
        };

        const closeModal = () => {
            $("#crudTask").modal("hide");
        };

        const updateThisField = ({ field, value }) => {
            if (field == "template_id") {
                getDataTemplate(value);
            }
            if (field == "client_main_information_id") {
                getDataClient(value);
            }
            dataForm.data[field] = value;
        };

        const getDataClient = async (value) => {
            if (value.value != null) {
                const response = await axios.post(
                    `/cliente/get-data-client-to-select-component/${value.value}`
                );

                updateThisField({
                    field: "address",
                    value: response.data.address,
                });

                updateThisField({
                    field: "location_id",
                    value: response.data.location_id,
                });

                position.value = response.data.geo_data;
            } else {
                position.value = ",";
                updateThisField({
                    field: "address",
                    value: null,
                });
            }
        };

        const getDataTemplate = async (value) => {
            const response = await axios.post(
                `/configuracion/template-task/get-data-template/${value.value}`
            );
            updateThisField({
                field: "description",
                value: response.data.description,
            });
            updateThisField({
                field: "title",
                value: response.data.title,
            });

            updateThisField({
                field: "project_id",
                value: response.data.project_id,
            });

            updateThisField({
                field: "assigned_to",
                value: response.data.assigned_to,
            });

            updateThisField({
                field: "template_verification",
                value: response.data.template_verification,
            });
            getListTemplate.value = response.data.template_verification;
            updateThisField({
                field: "priority",
                value: response.data.priority,
            });
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
        };

        const onSubmit = () => {
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    closeModal();
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            closeModal,
            id,
        };
    },
};
</script>

<style scoped></style>
