<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="card-text-body">
                        <p class="card-text">
                            En el Router deberá existir:
                            <br />
                            * Nombre Interface para Servidor Ppoe (Ejemplo):
                            vlan200 <br />
                            * Direccion remota servidor Ppp (Nombre del Pool
                            Ejemplo): estatica <br />
                            * Nombre de la Interfaz puente (Ejemplo):
                            RED_LOCAL_LAN
                        </p>
                    </div>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <hr class="mb-5" />

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

                        <div class="form-group text-center">
                            <a
                                class="btn btn-secondary me-2"
                                href="/red/router/listar"
                            >
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
            <!-- end card -->
        </div>
        <!-- end col -->
    </div>
</template>

<script>
import { reactive, ref, onMounted } from "vue";
import ComponentFormDefault from "../../ComponentFormDefault";
import Form from "../../../helpers/Form";
import { requestFieldsByModuleIdRelation } from "../../../helpers/Request";

export default {
    name: "MikrotikConfigCrud",
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

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };


        let submitButtonAction = props.id
            ? "Salvar Router Mikrotik"
            : "Crear Router Mikrotik";

        onMounted(async () => {
            await getfieldsJsonRelation(
                "MikrotikConfig",
                "Router",
                props.id,
                "mikrotikconfig"
            );
        });

        const getfieldsJsonRelation = async (
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

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/red/router/mikrotik/config/${props.action}`,
                    props.action
                )
                .then((response) =>
                    toastr.success(
                        `Configuraión Adicional Mikrotik ${
                            props.id ? "actualizado" : "creado"
                        } satisfactoriamente`,
                        "Configuraión Adicional Mikrotik"
                    )
                );
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
