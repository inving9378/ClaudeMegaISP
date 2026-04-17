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
import { onMounted, ref, watch,reactive } from "vue";
import ComponentFormDefault from "../../../ComponentFormDefault";
import Swal from "sweetalert2";
import Form from "../../../../helpers/Form";
import { requestFieldsByModule, requestEditedFieldsById } from "../../../../helpers/Request";

export default {
    name: "StoreZoneCrud",
    props: {
        action: String,
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
        let submitButtonAction =
            props.action === "/red/ipv4/ip/add"
                ? "Crear Ipv4"
                : "Salvar Ipv4";

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
            let partnerId = getIdByAction(action);
            if (action == "/red/ipv4/ip/add") {
                id.value = null;
                await getfieldsJson("NetworkIp");
            } else {
                id.value = partnerId;
                await getfieldsEdited("NetworkIp", partnerId);
            }
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
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

        const getIdByAction = (action) => {
            return _.trimStart(action, "/red/ipv4/ip/update/");
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = () => {
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    const { success, message } = response;
                    if (success) {
                        Swal.fire("Éxito", message, "success");
                        closeModal();
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
            closeModal,
            id,
        };
    },
};
</script>

<style scoped></style>
