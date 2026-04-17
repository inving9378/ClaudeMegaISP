<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Configuración de Comandos</h4>

                    <hr class="mb-5" />

                    <div class="d-flex">
                        <div>
                            <button
                                @click="checkProcess"
                                class="btn btn-primary"
                                type="button"
                            >
                                Verificar Sheduling Process
                            </button>
                            <p class="text-danger">Estado: {{ status }}</p>
                        </div>
                        <div class="" style="margin-left: 10px">
                            <button
                                v-if="showActiveCommand"
                                @click="activeCommand"
                                class="btn btn-primary"
                                type="button"
                            >
                                Activar Comandos
                            </button>
                        </div>
                    </div>

                    <div class="panel" v-for="command in commands">
                        <!-- TODO Quitar cuando todos los comando esten funcionales -->
                        <div class="seccion" v-if="command.status == true">
                            <h3>{{ command.command }}</h3>
                            <div class="item">
                                <p>{{ command.command_description }}</p>
                            </div>
                            <div class="">
                                <button
                                    class="btn btn-primary btnEditCommandConfig"
                                    style="float: right"
                                    type="button"
                                    :data-id="command.id"
                                >
                                    {{ submitButtonAction }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end card -->
        </div>
        <!-- end col -->
    </div>
    <edit-command-config
        :frequency_has_time="frequency_has_time"
    ></edit-command-config>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import EditCommandConfig from "./components/EditCommandConfig";
import { showLoading,hideLoading } from "../../../helpers/loading";

export default {
    name: "CommandConfig",
    props: {
        action: String,
        commands: String,
        frequency_has_time: String,
        url: String,
    },
    components: {
        EditCommandConfig,
    },
    setup(props, { emit }) {
        let submitButtonAction = ref("Editar");

        const commands = ref(JSON.parse(props.commands));
        const status = ref("");
        const showActiveCommand = ref(false);
        const resultStartProcess = ref("");

        onMounted(async () => {});

        const checkProcess = async () => {
            showLoading("showTextDef");
            let url = `${props.url}/administracion/check-schedule-process`;
            try {
                const response = await axios.get(url);
                status.value = response.data.status;
                if (status.value == "Detenido") {
                    showActiveCommand.value = true;
                }
                hideLoading()
            } catch (error) {
                console.error(error);
                hideLoading();
            }
        };

        const activeCommand = async () => {
            let url = `${props.url}/administracion/active-schedule-process`;
            try {
                showLoading("showTextDef");
                const response = await axios.post(url);
                resultStartProcess.value = response.data.status;
                if (resultStartProcess.value == "ok") {
                    showActiveCommand.value = false;
                    status.value = "En Ejecución"
                }
                if (resultStartProcess.value == "fail") {
                    showActiveCommand.value = false;
                    status.value = "Fallo en la Ejecución";
                }
                hideLoading();
            } catch (error) {
                console.error(error);
                hideLoading();
            }
        };

        return {
            submitButtonAction,
            commands,
            checkProcess,
            status,
            showActiveCommand,
            activeCommand,
        };
    },
};
</script>

<style scoped></style>
