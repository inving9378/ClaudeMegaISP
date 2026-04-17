<template>
    <div class="task_grid_table d-none">
        <div class="section-table-content">
            <div class="board_task row">
                <div class="column_task col-md-4">
                    <div class="column-header">
                        <div>
                            <h2>Por Hacer</h2>
                        </div>
                        <div>
                            <button
                                class="add-button"
                                data-bs-toggle="modal"
                                :data-bs-target="`#crudTask`"
                            >
                                +
                            </button>
                        </div>

                        <div class="dropdown_task">
                            <button class="dropbtn_task dropdown">
                                &#9660;
                            </button>
                        </div>
                    </div>

                    <div
                        v-mutation="handler1"
                        @dragenter="onDragEnter"
                        @dragleave="onDragLeave"
                        @dragover="onDragOver"
                        @drop="onDrop"
                        class="drop-target full-width"
                        id="ToDo"
                    >
                        <div
                            class="task"
                            v-for="val in taskToDo"
                            :id="`ToDo_${val.id}`"
                            :key="val.id"
                            draggable="true"
                            @dragstart="onDragStart"
                        >
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Prioridad:</div>
                                <div v-html="val.priority" class="ml-2"></div>
                            </div>
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Asignado:</div>
                                <div
                                    v-html="val.assigned_to"
                                    class="ml-2"
                                ></div>
                            </div>
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                #
                                <p v-html="val.id"></p>
                            </div>
                            <span>
                                <button
                                    type="button"
                                    class="btn btn-aqua-custom"
                                >
                                    Editar
                                </button></span
                            >
                            <p v-html="val.title"></p>
                        </div>
                    </div>
                    <div class="no-data" v-if="taskToDo.length == 0">
                        No hay datos para mostrar
                    </div>
                </div>
                <div class="column_task col-md-4">
                    <div class="column-header">
                        <div>
                            <h2>En Progreso</h2>
                        </div>
                        <div>
                            <button
                                class="add-button"
                                data-bs-toggle="modal"
                                :data-bs-target="`#crudTask`"
                            >
                                +
                            </button>
                        </div>

                        <div class="dropdown_task">
                            <button class="dropbtn_task dropdown">
                                &#9660;
                            </button>
                        </div>
                    </div>
                    <div
                        v-mutation="handler2"
                        @dragenter="onDragEnter"
                        @dragleave="onDragLeave"
                        @dragover="onDragOver"
                        @drop="onDrop"
                        class="drop-target full-width"
                        id="InProgress"
                    >
                        <div
                            class="task"
                            v-for="val in taskInProgress"
                            :id="`InProgress_${val.id}`"
                            :key="val.id"
                            draggable="true"
                            @dragstart="onDragStart"
                        >
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Prioridad:</div>
                                <div v-html="val.priority" class="ml-2"></div>
                            </div>
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Asignado:</div>
                                <div
                                    v-html="val.assigned_to"
                                    class="ml-2"
                                ></div>
                            </div>
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                #
                                <p v-html="val.id"></p>
                            </div>
                            <span>
                                <button
                                    type="button"
                                    class="btn btn-aqua-custom"
                                >
                                    Editar
                                </button></span
                            >
                            <p v-html="val.title"></p>
                        </div>
                    </div>

                    <div class="no-data" v-if="taskInProgress.length == 0">
                        No hay datos para mostrar
                    </div>
                </div>
                <div class="column_task col-md-4">
                    <div class="column-header">
                        <div>
                            <h2>Terminado</h2>
                        </div>
                        <div>
                            <button
                                class="add-button"
                                data-bs-toggle="modal"
                                :data-bs-target="`#crudTask`"
                            >
                                +
                            </button>
                        </div>

                        <div class="dropdown_task">
                            <button class="dropbtn_task dropdown">
                                &#9660;
                            </button>
                        </div>
                    </div>
                    <div
                        v-mutation="handler3"
                        @dragenter="onDragEnter"
                        @dragleave="onDragLeave"
                        @dragover="onDragOver"
                        @drop="onDrop"
                        class="drop-target full-width"
                        id="Done"
                    >
                        <div
                            class="task"
                            v-for="val in taskDone"
                            :key="val.id"
                            :id="`Done_${val.id}`"
                            draggable="true"
                            @dragstart="onDragStart"
                        >
                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Prioridad:</div>
                                <div v-html="val.priority" class="ml-2"></div>
                            </div>

                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                <div>Asignado:</div>
                                <div
                                    v-html="val.assigned_to"
                                    class="ml-2"
                                ></div>
                            </div>

                            <div
                                class="priority mb-3 d-flex align-items-center"
                            >
                                #
                                <p v-html="val.id"></p>
                            </div>

                            <span>
                                <button
                                    type="button"
                                    class="btn btn-aqua-custom"
                                >
                                    Editar
                                </button></span
                            >
                            <p v-html="val.title"></p>
                        </div>
                    </div>

                    <div class="no-data" v-if="taskDone.length == 0">
                        No hay datos para mostrar
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import {
    filters,
    comunRows,
    resetDatatable,
} from "../../../../helpers/filters";
import Form from "../../../../helpers/Form";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import { tableShow } from "./helper";

export default {
    name: "TaskTableGrid",
    components: {},
    props: {
        buttons: {
            type: Object,
            default: {},
        },
        multipleFilters: {
            type: Object,
            default: {},
        },
    },
    setup(props) {
        const title = ref("Crear Tarea");

        const action = ref("/scheduling/task/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });

        const taskInProgress = ref([]);
        const taskDone = ref([]);
        const taskToDo = ref([]);

        const status1 = ref([]);
        const status2 = ref([]);
        const status3 = ref([]);

        const handler1 = (mutationRecords) => {
            status1.value = [];
            for (const index in mutationRecords) {
                const record = mutationRecords[index];
                const info = `type: ${record.type}, nodes added: ${
                    record.addedNodes.length > 0 ? "true" : "false"
                }, nodes removed: ${
                    record.removedNodes.length > 0 ? "true" : "false"
                }, newValue: ${record.oldValue}`;
                status1.value.push(info);
            }
        };

        const handler2 = (mutationRecords) => {
            status2.value = [];
            for (const index in mutationRecords) {
                const record = mutationRecords[index];
                const info = `type: ${record.type}, nodes added: ${
                    record.addedNodes.length > 0 ? "true" : "false"
                }, nodes removed: ${
                    record.removedNodes.length > 0 ? "true" : "false"
                }, newValue: ${record.oldValue}`;
                status2.value.push(info);
            }
        };

        const handler3 = (mutationRecords) => {
            status3.value = [];
            for (const index in mutationRecords) {
                const record = mutationRecords[index];
                const info = `type: ${record.type}, nodes added: ${
                    record.addedNodes.length > 0 ? "true" : "false"
                }, nodes removed: ${
                    record.removedNodes.length > 0 ? "true" : "false"
                }, newValue: ${record.oldValue}`;
                status3.value.push(info);
            }
        };

        const onDragStart = (e) => {
            e.dataTransfer.setData("text", e.target.id);
            e.dataTransfer.dropEffect = "move";
        };

        const onDragEnter = (e) => {
            // don't drop on other draggables
            if (e.target.draggable !== true) {
                e.target.classList.add("drag-enter");
            }
        };

        const onDragLeave = (e) => {
            e.target.classList.remove("drag-enter");
        };

        const onDragOver = (e) => {
            e.preventDefault();
        };

        const onDrop = (e) => {
            e.preventDefault();

            // don't drop on other draggables
            if (e.target.draggable === true) {
                return;
            }

            const draggedId = e.dataTransfer.getData("text");
            const draggedEl = document.getElementById(draggedId);

            const boxOld = draggedEl.id;
            const statusNew = e.target.id;
            const arrayBoxOld = boxOld.split("_");
            const statusOld = arrayBoxOld[0];
            const idTask = arrayBoxOld[1];

            if (statusOld !== statusNew) {
                updateStatusTask(idTask, statusNew);
                draggedEl.id = `${statusNew}_${idTask}`;
            }

            if (draggedEl.parentNode === e.target) {
                e.target.classList.remove("drag-enter");
                return;
            }

            // Quitar la clase de arrastre
            e.target.classList.remove("drag-enter");
        };

        watch(comunRows, () => {
            // Filtra las tareas que tienen el estado "InProgress"
            taskInProgress.value = comunRows.value.filter((task) =>
                task.status.includes("En Progreso")
            );
            taskDone.value = comunRows.value.filter((task) =>
                task.status.includes("Terminado")
            );

            taskToDo.value = comunRows.value.filter((task) =>
                task.status.includes("Por Hacer")
            );
        });
        const closeModal = () => {
            $("#crudTask").modal("hide");
            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Tarea";
            action.value = "/scheduling/task/add";
            datatable.table.reload();
        };

        const setFilter = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        const getButtonDatatable = () => {
            let buttons = {};
            buttons.add = {
                class: "btn btn-outline-info waves-effect waves-light ms-3 me-3",
                href: "javascript:void(0)",
                id: "",
                dataBsTarget: "#crudTask",
                dataBsToogle: "modal",
                text: "Agregar",
            };
            return buttons;
        };

        const updateStatusTask = async (idTask, statusNew) => {
            showLoading("showTextDef");
            await axios
                .post(`/scheduling/task/update/${idTask}`, {
                    status: statusNew,
                })
                .then((response) => {
                    resetDatatable.value = true;
                })
                .catch((response) => {
                    hideLoading();
                });
            hideLoading();
        };

        return {
            title,
            action,
            closeModal,
            setFilter,
            dataForm,
            getButtonDatatable,
            tableShow,
            reloadCrud,
            taskDone,
            taskInProgress,
            taskToDo,

            handler1,
            handler2,
            handler3,
            onDragEnter,
            onDragLeave,
            onDragOver,
            onDrop,
            onDragStart,
            status1,
            status2,
            status3,
        };
    },
};
</script>

<style lang="sass"></style>
