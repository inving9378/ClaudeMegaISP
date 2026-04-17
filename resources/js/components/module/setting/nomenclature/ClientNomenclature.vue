<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row" style="min-height: 400px !important">
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
import { requestFieldsByModule } from "../../../../helpers/Request";
import Form from "../../../../helpers/Form";

export default {
    name: "ClientNomenclature",
    props: {
        action: String,
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const id = ref(null);
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

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
            await getfieldsJson("ClientNomenclature");
        };

        const closeModal = () => {
            $("#addClientNomenclature").modal("hide");
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
        };

        const onSubmit = () => {
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    $("#addClientNomenclature").modal("hide");
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
