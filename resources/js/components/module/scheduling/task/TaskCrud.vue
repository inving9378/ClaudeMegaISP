<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
            <div v-if="id">
                <a :href="`/scheduling/task/editar/${id}`"
                    ><p>Tarea #{{ id }}</p></a
                >
            </div>
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
            <div v-if="id">
                <button
                    class="btn btn-primary float-right"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#addNote"
                >
                    Agregar Nota
                </button>
            </div>
            <div
                class="p-2 m-2 h-fix-content shadow-low"
                v-if="observations.length > 0"
            >
                <div
                    class="mb-3 shadow-low"
                    style="border-top: 1px solid"
                    v-for="observation in observations"
                >
                    <div class="d-flex justify-between">
                        <span>{{ observation.name_created_by }}</span>
                        <span>{{ observation.date_created }}</span>
                    </div>
                    <div
                        style="max-height: 200px; overflow-y: auto"
                        v-html="observation.observation"
                    ></div>
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

            <a
                v-if="id"
                class="btn btn-secondary mr-3"
                href="javascript:void(0)"
                @click="archiveTask"
                :key="id"
            >
                Archivar
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

    <div
        v-if="id"
        class="modal fade"
        id="addNote"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Nota</h6>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <InputEditor
                            title=""
                            :property="{
                                field: 'observation',
                                class_col: 'full',
                                class_field: 'col-sm-12 col-md-12',
                                class_label: '',
                            }"
                            :errors="dataForm.data.errors"
                            @update-field="updateObservation"
                        >
                        </InputEditor>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="closeModalNote"
                    >
                        Cerrar
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="addNote"
                    >
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, nextTick } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";
import { position, address } from "../../../../helpers/googleMapsVariables";
import QColorPicker from "../../../../shared/QColorPicker.vue";
import InputEditor from "../../../../shared/InputEditor.vue";
import {
    clientMainInformationId,
    getListTemplate,
} from "../../client/info/comun_variable";
import InputGroupNumberTime from "./components/InputGroupNumberTime.vue";
import { updateColorField } from "./helper";

export default {
    name: "TaskCrud",
    props: {
        action: String,
        customerId: String,
    },
    components: {
        ComponentFormDefault,
        QColorPicker,
        InputGroupNumberTime,
        InputEditor,
    },
    emits: ["close-modal"],
    setup(props, { emit }) {
        const id = ref(null);
        const observation = ref(null);
        const disabledObservation = ref(false);
        const observations = ref([]);
        const dataTask = ref(null);
        const addedNote = ref(false);

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
            let idItem = await getIdByAction(action);
            if (action == "/scheduling/task/add") {
                id.value = null;
                await getfieldsJson("Task");
            } else {
                id.value = idItem;
                unreadTaskNotification(idItem);
                clientMainInformationId.value = props.customerId;
                await getfieldsEdited("Task", idItem);
                position.value = dataForm.data.geo_data ?? ",";
                getNotesByTask(id.value);
                getDataTask(id.value);

                await nextTick(() => {
                    let field = "status";
                    let value = ref(dataForm.data[field]);
                    updateColorField(value, field);

                    field = "priority";
                    value = ref(dataForm.data[field]);
                    updateColorField(value, field);
                });
            }
        };

        const getIdByAction = async (action) => {
            return _.trimStart(action, "/scheduling/task/update/");
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const unreadTaskNotification = (id) => {
            axios
                .post(`/scheduling/task/unread-notification/${id}`)
                .then((response) => {});
        };

        const updateThisField = ({ field, value }) => {
            if (field == "template_id") {
                getDataTemplate(value);
            }
            if (field == "client_main_information_id") {
                getDataClient(value);
            }

            if (field == "status" || field == "priority") {
                nextTick(() => {
                    updateColorField(value, field);
                });
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

        const onSubmit = () => {
            if (
                dataTask.value != null &&
                dataForm.data["status"] != dataTask.value.status &&
                addedNote.value == false &&
                props.action != "/scheduling/task/add"
            ) {
                alert(
                    "No puede cambiar el estado de la tarea sin agregar una nota"
                );
                return;
            } else {
                dataForm.data
                    .submit("post", `${props.action}`, props.action)
                    .then((response) => {
                        emit("close-modal");
                        window.location.href = "/scheduling/task/calendar";
                    });
            }
        };

        const updateObservation = ({ field, value }) => {
            observation.value = value.value;
        };

        const addNote = () => {
            disabledObservation.value = true;
            axios["post"](`/scheduling/task/add_note/${id.value}`, {
                observation: observation.value,
            }).then((response) => {
                toastr.success("Nota Agregada Correctamente", "Tareas");
                addedNote.value = true;
                closeModalNote();
                getNotesByTask(id.value);
            });
        };

        const closeModalNote = () => {
            observation.value = null;
            $("#addNote").modal("hide");
        };

        const archiveTask = () => {
            if (confirm("¿Seguro que desea archivar esta tarea?")) {
                axios["post"](`/scheduling/task/archive/${id.value}`).then(
                    (response) => {
                        toastr.success(
                            "Tarea archivada Correctamente",
                            "Tareas"
                        );
                        location.reload();
                    }
                );
            }
        };

        const getNotesByTask = (id) => {
            axios["post"](`/scheduling/task/get-notes-by-task/${id}`).then(
                (response) => {
                    observations.value = response.data.observations;
                }
            );
        };
        const getDataTask = (id) => {
            axios["post"](`/scheduling/task/get-data-task/${id}`).then(
                (response) => {
                    dataTask.value = response.data;
                }
            );
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            closeModal,
            id,
            archiveTask,
            addNote,
            updateObservation,
            observations,
        };
    },
};
</script>

<style scoped></style>
