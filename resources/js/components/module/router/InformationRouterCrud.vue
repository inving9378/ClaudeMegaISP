<template>
    <div class="row">
        <div class="col-12">
            <form
                method="POST"
                @submit.prevent="onSubmit"
                @change="dataForm.data.errors.clear($event.target.name)"
                @keydown="dataForm.data.errors.clear($event.target.name)"
            >
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Router</h4>
                        <template v-for="val in fieldsJson['init']">
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
                </div>
                <hr class="mb-2" />
                <br />
                <div class="card">
                    <div class="card-body">
                        <template v-for="val in fieldsJson['other']">
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
                </div>

                <div class="form-group text-center">
                    <a class="btn btn-secondary me-2" href="/red/router/listar">
                        Atras
                    </a>
                    <button
                        class="btn btn-primary"
                        type="submit"
                        :disabled="dataForm.data.errors.any()"
                    >
                        {{ submitButtonAction }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import ComponentFormDefault from "../../ComponentFormDefault";
import Form from "../../../helpers/Form";
import {
    requestEditedFieldsById,
    requestFieldsByModule,
} from "../../../helpers/Request";

export default {
    name: "InformationRouterCrud",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
    },
    setup(props) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        let submitButtonAction = props.id ? `Salvar` : `Crear`;

        onMounted(() => {
            initComponent(props.action);
        });

        const initComponent = async (action) => {
            action == `crear/${props.id}`
                ? await getfieldsJson("Router")
                : await getfieldsEdited("Router", props.action);
        };

        const getfieldsEdited = async (model, action) => {
            let id = action.substr(7);
            fieldsJson.value = await requestEditedFieldsById(model, id);
            dataForm.data = new Form(fieldsJson.value);
            temAuthorizationAccounting.value =
                dataForm.data["authorization_accounting"];

            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["other"] = _.mapKeys(val["other"], (v) => v.field);
            fieldsJson.value = val;
        };

        const temAuthorizationAccounting = ref("");

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["other"] = _.mapKeys(val["other"], (v) => v.field);
            fieldsJson.value = val;
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const onSubmit = () => {
            if (
                dataForm.data["authorization_accounting"] !=
                temAuthorizationAccounting.value
            ) {
                if (
                    confirm(
                        "¿Estas seguro de cambiar de " +
                            temAuthorizationAccounting.value +
                            " a " +
                            dataForm.data["authorization_accounting"] +
                            "?. Si lo hace, los usuarios que estan en " +
                            temAuthorizationAccounting.value +
                            " pasaran a ser parte de " +
                            dataForm.data["authorization_accounting"] +
                            "."
                    )
                ) {
                    dataForm.data["authorization_accounting"] =
                        dataForm.data["authorization_accounting"];
                } else {
                    dataForm.data["authorization_accounting"] =
                        temAuthorizationAccounting.value;
                }
            }
            dataForm.data
                .submit("post", `/red/router/${props.action}`, props.action)
                .then((response) => {
                    toastr.success(
                        `El Router ${
                            props.id ? "actualizado" : "creado"
                        } satisfactoriamente`,
                        "Router"
                    );
                    location.reload();
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
        };
    },
};
</script>

<style scoped></style>
