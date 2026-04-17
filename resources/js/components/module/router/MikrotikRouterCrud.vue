<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <hr class="mb-5"/>

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
            <!-- end card -->
        </div> <!-- end col -->
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6>Estado de Mikrotik</h6>
                    <table class="table" v-if="!loading">
                        <tbody>
                        <tr>
                            <td>
                                Estado
                            </td>
                            <td>
                                <label :class="`badge bg-${statusRouter['board-name'] ? 'success' : 'danger'}`">
                                    {{ statusRouter['board-name'] ? 'API OK' : 'API ERROR' }}
                                </label>
                            </td>

                        </tr>
                        <tr>
                            <td>
                                Plataforma
                            </td>
                            <td>
                                {{ statusRouter.platform }}
                            </td>
                        </tr>

                        <tr>
                            <td>
                                Board name
                            </td>
                            <td>
                                {{ statusRouter['board-name'] }}
                            </td>
                        </tr>

                        <tr>
                            <td>
                                ROS Versión
                            </td>
                            <td>
                                {{ statusRouter.version }}
                            </td>
                        </tr>


                        <tr>
                            <td>
                                Carga del CPU
                            </td>
                            <td>
                                {{ statusRouter['cpu-load'] }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                IPv6
                            </td>
                            <td>
                                <label class="label label-warning">-</label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Última estado
                            </td>
                            <td>
                                {{ statusRouter.uptime }}
                            </td>
                        </tr>
                        <tr>
                           <td>
                               Ping
                            </td>
                            <td>
                                {{ statusRouter.ping }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div v-if="loading"> Loading...</div>
                    <div class="row float-right">
                        <div class="d-flex flex-wrap flex-row-reverse">
                            <div class="m-1">
                                <button @click="removeMikrotikRules" type="button" class="btn btn-outline-danger">
                                    Eliminar las reglas del Router
                                </button>
                            </div>
                            <div class="m-1">
                                <button @click="createMikrotikRules" type="button" class="btn btn btn-success">Crear las
                                    reglas iniciales del Router
                                </button>
                            </div>
                            <div class="m-1">
                                <button @click="cloneClient" type="button" class="btn btn btn-success">Clonar Clientes desde base de datos
                                </button>
                            </div>
                            <div class="m-1">
                                <button @click="statusMikrotik" type="button" class="btn btn-outline-primary">Estado del
                                    Router
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {reactive, ref, onMounted} from "vue";
import ComponentFormDefault from "../../ComponentFormDefault";
import Form from "../../../helpers/Form";
import {
    requestFieldsByModuleIdRelation,
    requestMikrotikStatus,
    requestRemoveMikrotikRules,
    requestCreateMikrotikRules,
    requestCloneClientToMikrotik
} from "../../../helpers/Request";

export default {
    name: "MikrotikRouterCrud",
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

        const statusRouter = ref({});
        const loading = ref(false);
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        const updateThisField = ({field, value}) => {
            dataForm.data[field] = value;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field)
        }

        const statusMikrotik = async () => {
            loading.value = true;
            statusRouter.value = await requestMikrotikStatus(props.id);
            loading.value = false;
        }

        const createMikrotikRules = async () => {
            statusRouter.value = await requestCreateMikrotikRules(props.id);
        }

        const removeMikrotikRules = async () => {
            statusRouter.value = await requestRemoveMikrotikRules(props.id);
        }
        const cloneClient = async  () => {
            await requestCloneClientToMikrotik(props.id);
        }

        let submitButtonAction = props.id
            ? 'Salvar Router Mikrotik'
            : 'Crear Router Mikrotik';

        onMounted(async () => {
            await getfieldsJsonRelation(
                "Mikrotik",
                "Router",
                props.id,
                "mikrotik"
            );
            await statusMikrotik();
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
                    `/red/router/mikrotik/${props.action}`,
                    props.action
                )
                .then((response) =>
                    toastr.success(
                        `Mikrotik ${
                            props.id ? "actualizado" : "creado"
                        } satisfactoriamente`,
                        "Mikrotik"
                    )
                );
        };

        return {
            fieldsJson,
            dataForm,
            statusRouter,
            loading,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            statusMikrotik,
            removeMikrotikRules,
            createMikrotikRules,
            cloneClient
        };
    },
}
</script>

<style scoped>

</style>
