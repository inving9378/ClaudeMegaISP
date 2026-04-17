<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                    id="form-list-template"
                >
                    <div class="mt-3">
                        <div class="card">
                            <div class="card-header">
                                <div
                                    class="row customer-billing-sticky-sidebar spl-sticky-sidebar"
                                >
                                    <div
                                        class="col-lg-12 customer-billing-sticky-sidebar-inner"
                                    >
                                        <div class="panel panel-default">
                                            <div class="panel-body">
                                                <div class="row">
                                                    <div class="">
                                                        <div
                                                            class="position-relative float-left customer-buttons-wrapper"
                                                        >
                                                            <button
                                                                class="btn btn-primary"
                                                                type="button"
                                                                :disabled="
                                                                    dataForm.data.errors.any()
                                                                "
                                                                @click="goBack"
                                                            >
                                                                volver
                                                            </button>
                                                        </div>

                                                        <div
                                                            class="position-relative float-right customer-buttons-wrapper"
                                                        >
                                                            <button
                                                                class="btn btn-primary"
                                                                type="submit"
                                                                :disabled="
                                                                    dataForm.data.errors.any()
                                                                "
                                                            >
                                                                {{
                                                                    submitButtonAction
                                                                }}
                                                            </button>
                                                        </div>
                                                        <div
                                                            class="position-relative float-right customer-buttons-wrapper me-5"
                                                            v-if="
                                                                hasPermission.data.canView(
                                                                    'task_archive_task'
                                                                )
                                                            "
                                                        >
                                                            <button
                                                                v-if="
                                                                    archived ==
                                                                    0
                                                                "
                                                                class="btn btn-primary"
                                                                type="button"
                                                                @click="
                                                                    archiveTask
                                                                "
                                                            >
                                                                Archivar
                                                            </button>
                                                            <span
                                                                v-else
                                                                class="position-relative float-right customer-buttons-wrapper me-5"
                                                            >
                                                                Tarea Archivada
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-xl-8">
                                    <div class="card">
                                        <div
                                            class="card-header d-flex justify-between"
                                        >
                                            <h6>Detalles</h6>
                                            <span class="float-right"
                                                >Tarea # {{ id }}</span
                                            >
                                        </div>
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low d-flex flex-wrap"
                                        >
                                            <template
                                                v-for="val in fieldsJson[
                                                    'task_left'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="val.include"
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>
                                        </div>

                                        <div class="card">
                                            <div class="card-header">
                                                <span class="float-left"
                                                    ><h6>
                                                        Archivos Adjuntos
                                                    </h6></span
                                                >
                                            </div>

                                            <div
                                                class="p-2 m-2 h-fix-content shadow-low"
                                            >
                                                <!-- Mostrar lista de archivos -->
                                                <div
                                                    v-for="(
                                                        file, index
                                                    ) in files"
                                                    :key="file.id"
                                                >
                                                    <div
                                                        class="row align-items-center"
                                                    >
                                                        <div class="col">
                                                            <a
                                                                href="javascript:void(0)"
                                                                class="text-primary"
                                                                >{{
                                                                    file.name
                                                                }}</a
                                                            >
                                                        </div>

                                                        <div class="col-auto">
                                                            <button
                                                                class="btn"
                                                                type="button"
                                                                @click="
                                                                    removeFile(
                                                                        file
                                                                    )
                                                                "
                                                            >
                                                                <span
                                                                    class="fa fa-trash"
                                                                ></span>
                                                            </button>
                                                        </div>

                                                        <div class="col-auto">
                                                            <button
                                                                class="btn"
                                                                type="button"
                                                                @click="
                                                                    downloadFile(
                                                                        file
                                                                    )
                                                                "
                                                            >
                                                                <span
                                                                    class="fa fa-download"
                                                                ></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <hr />
                                                </div>

                                                <!-- Input de subida -->
                                                <div class="mt-3">
                                                    <input
                                                        type="file"
                                                        ref="fileInput"
                                                        class="form-control"
                                                        multiple
                                                        @change="uploadFiles"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="card"
                                            style="
                                                max-height: 400px;
                                                overflow-y: auto;
                                            "
                                        >
                                            <div class="card-header">
                                                <span class="float-left"
                                                    ><h6>Notas</h6></span
                                                >

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
                                            >
                                                <div
                                                    v-if="observations.length"
                                                    class="mb-3 shadow-low"
                                                    style="
                                                        border-top: 1px solid;
                                                    "
                                                    v-for="observation in observations"
                                                >
                                                    <div
                                                        class="d-flex justify-between"
                                                    >
                                                        <span>{{
                                                            observation.name_created_by
                                                        }}</span>
                                                        <span>{{
                                                            observation.date_created
                                                        }}</span>
                                                    </div>
                                                    <div
                                                        style="
                                                            max-height: 200px;
                                                            overflow-y: auto;
                                                        "
                                                        v-html="
                                                            observation.observation
                                                        "
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="card">
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low"
                                        >
                                            <h5>Gente</h5>
                                            <template
                                                v-for="val in fieldsJson[
                                                    'task_right'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="
                                                        val.include &&
                                                        val.position == 1
                                                    "
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>

                                            <div class="row col-12">
                                                <div class="col-4">
                                                    Creado por:
                                                </div>
                                                <div class="col-8">
                                                    {{ dataTask.created_by }}
                                                </div>
                                            </div>

                                            <div class="row col-12">
                                                <div class="col-4">
                                                    Watchers:
                                                </div>
                                                <div class="col-8">
                                                    1 watche on This task
                                                    <span>+</span>
                                                </div>
                                            </div>
                                        </div>
                                        <hr />
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low"
                                        >
                                            <h5>Momento</h5>

                                            <div class="row col-12">
                                                <div class="col-4">
                                                    Creado :
                                                </div>
                                                <div class="col-8">
                                                    {{ dataTask.created_at }}
                                                </div>
                                            </div>

                                            <div class="row col-12">
                                                <div class="col-4">
                                                    Actualizado:
                                                </div>
                                                <div class="col-8">
                                                    {{ dataTask.updated_at }}
                                                </div>
                                            </div>

                                            <div class="row col-12">
                                                <div class="col-4">
                                                    Resuelto:
                                                </div>
                                                <div class="col-8">
                                                    {{ dataTask.finish_at }}
                                                </div>
                                            </div>

                                            <template
                                                v-for="val in fieldsJson[
                                                    'task_right'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="
                                                        val.include &&
                                                        val.position == 2
                                                    "
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>

                                            <template
                                                v-for="val in fieldsJson[
                                                    'task_right'
                                                ]"
                                            >
                                                <ComponentFormDefault
                                                    v-if="
                                                        val.include &&
                                                        val.position == 3
                                                    "
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>
                                        </div>

                                        <hr />
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low"
                                        >
                                            <h5>Tiempo</h5>

                                            <InputGroupNumberTime
                                                :model-value="
                                                    dataForm.data[
                                                        'time_to_task_location'
                                                    ]
                                                "
                                                @update-field="updateThisField"
                                                :property="{
                                                    field: 'time_to_task_location',
                                                    label: 'Tiempo Hasta la Tarea',
                                                    hours: 0,
                                                }"
                                                :key="
                                                    dataForm.data[
                                                        'time_to_task_location'
                                                    ]
                                                "
                                            >
                                            </InputGroupNumberTime>

                                            <InputGroupNumberTime
                                                :model-value="
                                                    dataForm.data[
                                                        'time_from_task_location'
                                                    ]
                                                "
                                                @update-field="updateThisField"
                                                :property="{
                                                    field: 'time_from_task_location',
                                                    label: 'Tiempo Desde la Tarea',
                                                    hours: 0,
                                                }"
                                                :key="
                                                    dataForm.data[
                                                        'time_from_task_location'
                                                    ]
                                                "
                                            >
                                            </InputGroupNumberTime>
                                        </div>

                                        <hr />
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low"
                                        >
                                            <h5>Registro de Trabajo</h5>
                                            <div class="row col-12">
                                                <div class="col-4">
                                                    <button
                                                        type="button"
                                                        class="btn btn-success"
                                                    >
                                                        Iniciar
                                                    </button>
                                                </div>
                                                <div class="col-4">
                                                    <button
                                                        type="button"
                                                        class="btn btn-warning"
                                                    >
                                                        Pausar
                                                    </button>
                                                </div>
                                                <div class="col-4">
                                                    <button
                                                        type="button"
                                                        class="btn btn-danger"
                                                    >
                                                        Parar
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row col-12 mt-2">
                                                <div class="col-12 text-center">
                                                    <button
                                                        type="button"
                                                        class="btn btn-primary"
                                                    >
                                                        Añadir el Registro de
                                                        Trabajo
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="row col-12 mt-2">
                                                <div class="col-4">
                                                    Registrado:
                                                </div>
                                                <div class="col-8"></div>
                                            </div>
                                            <div class="row col-12 mt-2">
                                                <div class="col-4">
                                                    Estimado:
                                                </div>
                                                <div class="col-8"></div>
                                            </div>
                                            <div class="row col-12 mt-2">
                                                <div class="col-4">
                                                    Pendiente:
                                                </div>
                                                <div class="col-8"></div>
                                            </div>
                                        </div>
                                        <hr />
                                        <div
                                            class="m-2 h-fix-content shadow-low"
                                        >
                                            <h5>Lista de Verificacion</h5>
                                            <div class="row col-12">
                                                <div class="col-6">
                                                    <button
                                                        type="button"
                                                        class="btn btn-warning"
                                                        @click="
                                                            getListVerificationByTask
                                                        "
                                                    >
                                                        Cargar lista de
                                                        verificación
                                                    </button>
                                                </div>

                                                <div class="col-6 text-center">
                                                    <button
                                                        type="button"
                                                        class="btn btn-primary"
                                                    >
                                                        Limpiar las listas de
                                                        verificación
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-3"
                                                v-if="checks.length > 0"
                                            >
                                                <h5>
                                                    {{ nameListVerification }}
                                                </h5>
                                                <div
                                                    v-for="(
                                                        item, index
                                                    ) in checks"
                                                    :key="item"
                                                    :id="`checks-input-list-verification`"
                                                >
                                                    <div
                                                        :class="`row mb-2 item align-items-center col-12`"
                                                    >
                                                        <div
                                                            class="col-sm-12 col-md-9"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                :id="`check_${index}`"
                                                                switch="none"
                                                                @change="
                                                                    getDataToList(
                                                                        index,
                                                                        item
                                                                    )
                                                                "
                                                                :data-name="
                                                                    item
                                                                "
                                                            />
                                                            <label
                                                                class="m-0"
                                                                :for="`check_${index}`"
                                                            ></label>
                                                        </div>
                                                        <label
                                                            :for="`check_${index}`"
                                                            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
                                                        >
                                                            {{ item }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <TaskRecentActivity :data="logs"> </TaskRecentActivity>
        </div>
    </div>

    <div
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
                        <InputEditorOld
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
                        </InputEditorOld>
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
import { onMounted, ref, nextTick, reactive } from "vue";
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsEditedWithMultipleModel,
    getfieldsWithMultipleModel,
    updateThisField,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";
import InputGroupNumberTime from "./components/InputGroupNumberTime.vue";
import { updateColorField } from "./helper";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import axios from "axios";
import TaskRecentActivity from "./components/TaskRecentActivity.vue";
import Swal from "sweetalert2";
import InputEditorOld from "../../../../shared/InputEditorOld.vue";
import { remove } from "lodash";

export default {
    name: "TaskEdit",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
        observations: {
            type: String,
        },
        archived: {
            type: String,
        },
    },
    components: {
        ComponentFormDefault,
        InputEditorOld,
        InputGroupNumberTime,
        TaskRecentActivity,
    },
    setup(props) {
        let submitButtonAction = props.id ? "Salvar" : "Crear Tarea";
        const checks = ref([]);
        const nameListVerification = ref("");
        const checksChecked = ref([]);
        const observations = ref({});
        const files = ref({});
        const dataTask = ref({});

        const addedNote = ref(false);
        const hasPermission = reactive({
            data: new Permission({}),
        });

        const logs = ref({});

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            props.id
                ? await getfieldsEditedWithMultipleModel(
                      [{ task_left: "TaskLeft" }, { task_right: "TaskRight" }],
                      props.id
                  )
                : await getfieldsWithMultipleModel([
                      { task_left: "TaskLeft" },
                      { task_right: "TaskRight" },
                  ]);

            await nextTick();
            //esperar a que se cargue el DOM
            let fieldStatus = "status";
            const valueStatus = ref(dataForm.data[fieldStatus]);

            updateColorField(valueStatus, fieldStatus);

            let fieldPriority = "priority";
            const valuePriority = ref(dataForm.data[fieldPriority]);
            fieldsJson.value.task_left.file.include = false;
            updateColorField(valuePriority, fieldPriority);
            getNotesByTask();
            getDataTask();
        });

        const updateThisField = ({ field, value }) => {
            if (field == "status" || field == "priority") {
                nextTick(() => {
                    updateColorField(value, field);
                });
            }
            dataForm.data[field] = value;
        };

        const getListVerificationByTask = async () => {
            await axios["post"](
                `/scheduling/task/get-list-template-verification-by-task/${props.id}`
            ).then((response) => {
                checks.value = JSON.parse(response.data.checks);
                nameListVerification.value =
                    response.data.list_template_verification_name;

                checksChecked.value = JSON.parse(response.data.checksChecked);

                setCheckToInputs(checksChecked.value, checks.value);
            });
        };

        const setCheckToInputs = async (checkeds, checks) => {
            // Convierte proxies a arrays simples
            const checkedItems = [...checkeds];
            const checkItems = [...checks];
            await nextTick();

            checkItems.forEach((check) => {
                const checkbox = document.querySelector(
                    `input[data-name="${check}"]`
                );
                if (checkbox) {
                    checkbox.checked = checkedItems.includes(check);
                } else {
                    console.warn(
                        `Checkbox with data-name="${check}" not found.`
                    );
                }
            });
        };

        const getDataToList = (index, text) => {
            const arrayChecks = [];
            const input = document.getElementById(`check_${index}`);

            if (input.checked) {
                if (!arrayChecks.includes(text)) {
                    arrayChecks.push(text);
                }
            } else {
                const itemIndex = arrayChecks.indexOf(text);
                if (itemIndex !== -1) {
                    arrayChecks.splice(itemIndex, 1);
                }
            }
            updateThisField({ field: "checks", value: arrayChecks });
        };

        const getChecks = () => {
            const arrayData = [];
            const form = document.getElementById("form-list-template");

            // Selecciona todos los inputs cuyo ID comienza con "check_"
            const inputs = form.querySelectorAll('input[id^="check_"]');

            // Recorrer los inputs encontrados y hacer algo con ellos
            inputs.forEach((input) => {
                if (input.checked) {
                    arrayData.push(input.dataset.name);
                }
            });
            return arrayData;
        };

        const transformToArray = () => {
            const arrayData = [];

            // Si dataForm.data.assigned_to es una cadena, lo transformamos en un array
            if (typeof dataForm.data.assigned_to === "string") {
                arrayData.push(
                    ...dataForm.data.assigned_to
                        .split(",")
                        .map((item) => parseInt(item, 10))
                );
            } else {
                // Si ya es un array o un número, simplemente lo manejamos adecuadamente
                if (Array.isArray(dataForm.data.assigned_to)) {
                    arrayData.push(...dataForm.data.assigned_to);
                } else {
                    arrayData.push(dataForm.data.assigned_to);
                }
            }

            return arrayData;
        };

        const onSubmit = () => {
            dataForm.data["checks"] = getChecks();
            dataForm.data["assigned_to"] = transformToArray();

            if (
                dataForm.data["status"] != dataTask.value.status &&
                addedNote.value == false
            ) {
                alert(
                    "No puede cambiar el estado de la tarea sin agregar una nota"
                );
                return;
            } else {
                dataForm.data
                    .uploadFile(`/scheduling/task/${props.action}`)
                    .then((response) => {
                        toastr.success(
                            "Tarea actualizada Correctamente",
                            "Tareas"
                        );
                        location.reload();
                    });
            }
        };

        const archiveTask = () => {
            if (
                (dataForm.data["status"] != dataTask.value.status &&
                    addedNote.value == false) ||
                dataForm.data["status"] != "Done"
            ) {
                Swal.fire(
                    "¡Error!",
                    "No puede cambiar el estado de la tarea sin agregar una nota o sin finalizar la tarea",
                    "error"
                );
                return;
            }

            if (confirm("¿Seguro que desea archivar esta tarea?")) {
                axios["post"](`/scheduling/task/archive/${props.id}`).then(
                    (response) => {
                        toastr.success(
                            "Tarea archivada Correctamente",
                            "Tareas"
                        );
                    }
                );
            }
        };

        const observation = ref(null);
        const disabledObservation = ref(false);

        const showNotes = ref(false);

        const seeNotes = () => {
            showNotes.value = !showNotes.value;
        };

        const updateObservation = ({ field, value }) => {
            observation.value = value.value;
        };

        const addNote = () => {
            disabledObservation.value = true;
            axios["post"](`/scheduling/task/add_note/${props.id}`, {
                observation: observation.value,
            }).then((response) => {
                toastr.success("Tarea archivada Correctamente", "Tareas");
                disabledObservation.value = false;
                observation.value = null;
                getNotesByTask();
                addedNote.value = true;
                closeModalNote();
            });
        };

        const getNotesByTask = () => {
            axios["post"](
                `/scheduling/task/get-notes-by-task/${props.id}`
            ).then((response) => {
                observations.value = response.data.observations;
            });
        };

        const getDataTask = () => {
            axios["post"](`/scheduling/task/get-data-task/${props.id}`).then(
                (response) => {
                    dataTask.value = response.data;
                    logs.value = response.data?.logs;
                    files.value = response.data?.files;
                }
            );
        };

        const closeModalNote = () => {
            observation.value = null;
            $("#addNote").modal("hide");
        };

        const goBack = () => {
            const previousUrl = document.referrer;
            const actualUrl = window.location.href;

            if (previousUrl) {
                if (previousUrl == actualUrl) {
                    location.href = "/scheduling/task";
                } else {
                    location.href = previousUrl;
                }
                // Recargar la página previa
            } else {
                // Si no hay historial, redirige a una ruta predeterminada (por ejemplo, el calendario o tareas)
                location.href = "/scheduling/task";
            }
        };

        const downloadFile = async (file) => {
            try {
                // Construir la URL de descarga
                const downloadUrl = `/scheduling/task/download-file/${file.fileable_id}`;

                // Datos para enviar en la solicitud POST
                let dataFile = {
                    id_file: file.id,
                };

                // Hacer una solicitud al servidor para descargar el archivo
                const response = await axios.post(downloadUrl, dataFile, {
                    responseType: "blob", // Indicar que la respuesta es un Blob
                });

                // Verificar si la respuesta es exitosa (código 200)
                if (response.status !== 200) {
                    throw new Error("No se pudo obtener el archivo");
                }

                // Crear un enlace temporal para descargar el archivo
                const url = window.URL.createObjectURL(
                    new Blob([response.data])
                );
                const a = document.createElement("a");
                a.href = url;
                a.download = file.name; // Nombre del archivo
                document.body.appendChild(a);
                a.click();

                // Limpiar y remover el enlace temporal
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                Swal.fire(
                    "¡Error!",
                    "Hubo un error al descargar el archivo",
                    "error"
                );
                console.error("Error al descargar el archivo:", error);
            }
        };

        const uploadFiles = async (event) => {
            const filesSelected = event.target.files;
            if (!filesSelected.length) return;

            const formData = new FormData();
            for (let i = 0; i < filesSelected.length; i++) {
                formData.append("files[]", filesSelected[i]);
            }

            try {
                const response = await axios.post(
                    `/scheduling/task/upload-file/${props.id}`,
                    formData,
                    { headers: { "Content-Type": "multipart/form-data" } }
                );

                toastr.success("Archivo(s) subido(s) correctamente", "Tareas");
                getDataTask(); // refresca la lista de archivos
            } catch (error) {
                console.error("Error subiendo archivo:", error);
                Swal.fire("Error", "No se pudo subir el archivo", "error");
            }
        };

        const removeFile = (file) => {
            axios
                .post(`/scheduling/task/remove-file/${file.fileable_id}`, {
                    id_file: file.id,
                })
                .then((response) => {
                    getDataTask(); // refresca la lista de archivos
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            getListVerificationByTask,
            checks,
            checksChecked,
            nameListVerification,
            getDataToList,
            observations,
            archiveTask,
            updateObservation,
            addNote,
            closeModalNote,
            seeNotes,
            showNotes,
            goBack,
            dataTask,
            hasPermission,
            logs,
            files,
            downloadFile,
            uploadFiles,
            removeFile,
        };
    },
};
</script>

<style scoped></style>
