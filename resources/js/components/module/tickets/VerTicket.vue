<template>
    <div class="row">
        <div class="col-sm-12 col-xxl-3">
            <div class="card">
                <div class="card-body bg-title">
                    <div class="row">
                        <TicketDetails
                            :id="id"
                            :action="`update/${id}`"
                            @reloadTicket="reloadTicket"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-xxl-9">
            <div class="card">
                <div class="card-title">
                    <div
                        class="form-group p-2"
                        style="background-color: #fafafa"
                    >
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-6 text-center text-md-start"
                            >
                                <h4 class="p-2">
                                    Ticket: #{{ fieldsJson.id }}
                                    {{ fieldsJson.topic }}
                                </h4>
                                <small class="text-muted pt-0 p-2 d-block">
                                    Ticket creado por el
                                    {{ fieldsJson.reporter_type }}:
                                    {{ fieldsJson.reporter }} desde el panel de
                                    administrador {{ fieldsJson.time_human
                                    }}<br />
                                    Cualquier / Main Admin (admin)
                                </small>
                            </div>
                            <div class="col-sm-12 col-md-6 gap-2">
                                <div class="mt-2 text-center text-md-end">
                                    <div class="btn-group">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary me-1waves-effect waves-light dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                        >
                                            Acciones
                                            <i class="mdi mdi-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a
                                                v-if="status != 'Cerrado'"
                                                class="dropdown-item"
                                                href="javascript:void(0)"
                                                @click="showSatisfactionModal"
                                                >Cerrar ticket</a
                                            >
                                            <a
                                                class="dropdown-item"
                                                href="javascript:void(0)"
                                                @click="reciclerTicket"
                                                >Mover a reciclaje</a
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-center text-md-end">
                                    <span
                                        class="p-2 text-white bg-danger waves-effect waves-light"
                                        >{{ fieldsJson.estado }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--     Card Ticket         -->
                <div class="card-body" id="tickets-thread">
                    <div class="row" style="height: auto">
                        <Ticket
                            :id="id"
                            @getTicketId="getTicketId"
                            @getFocus="getFocus"
                            :reloadThread="reloadThread"
                        ></Ticket>
                    </div>
                </div>
                <div class="position-relative">
                    <textarea
                        v-model="message"
                        class="form-control"
                        name="message"
                        id="message"
                        cols="30"
                        rows="3"
                        @click="clearError('message')"
                        placeholder="Escribir mensaje..."
                    ></textarea>
                    <input
                        type="file"
                        class="d-none"
                        id="file"
                        @change="uploadFile"
                    />
                    <div class="d-flex">
                        <button
                            class="btn btn-primary form-control"
                            type="button"
                            @click="onSubmit()"
                            :disabled="
                                dataForm.data.errors.any() || disabledButton
                            "
                        >
                            Enviar mensaje
                        </button>
                        <i
                            class="fa fa-upload ms-1 align-self-center cursor-pointer"
                            @click="clickUploadFile"
                            aria-hidden="true"
                        ></i>
                    </div>
                </div>

                <!--   new thread             -->
                <TicketNewThread
                    v-if="setAnswer"
                    :id="id"
                    :action="`add/${id}`"
                    @hideNewAnswer="hideNewAnswer"
                    @reloadTicket="reloadTicket"
                />
                <!--   end new thread         -->
            </div>
        </div>
    </div>

    <!-- Modal de satisfacción -->
    <TicketSatisfactionModal
        ref="satisfactionModal"
        :ticketId="id"
        @ticketClosed="onTicketClosed"
        :dataForm="dataForm"
    />

    <TicketModalEdit
        :id="vId"
        :action="`update/${vId}`"
        @cleanModal="cleanModal"
    />
</template>

<script>
import { ref, onMounted, reactive, watch } from "vue";
import Ticket from "./component/Ticket.vue";
import TicketDetails from "./component/TicketDetails";
import TicketModalEdit from "./component/TicketModalEdit";
import TicketNewThread from "./component/TicketNewThread.vue";
import TicketSatisfactionModal from "./component/TicketSatisfactionModal.vue"; // Importar el nuevo componente
import Modal from "../../../helpers/modal";
import {
    requestTicketData,
    requestTicketThreadData,
    requestChangeStatus,
} from "./helper/request";
import Form from "../../../helpers/Form";
import { dataForm } from "../../../hook/crudHook";

export default {
    name: "VerTicket",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        Ticket,
        TicketDetails,
        TicketModalEdit,
        Modal,
        TicketNewThread,
        TicketSatisfactionModal, // Registrar el nuevo componente
    },
    setup(props) {
        const fieldsJson = ref({});
        const fieldsJsonThread = ref({});
        const modal = ref();
        const vId = ref();
        const setAnswer = ref();
        const message = ref();
        const file = ref();
        const satisfactionModal = ref(); // Referencia al modal de satisfacción
        const dataForm = reactive({
            data: new Form({ file, message }),
        });
        const reloadThread = ref(false);
        const disabledButton = ref(false);
        const status = ref();

        watch(message, () => {
            dataForm.data.message = message.value;
        });

        watch(file, () => {
            dataForm.data.file = file.value;
        });

        onMounted(() => {
            initComponent();
            modal.value = new Modal("modalTicketEdit");
            setAnswer.value = false;
        });

        const initComponent = async () => {
            await getfieldsJson();
            await getfieldsJsonThread();
            hideNewAnswer();
        };

        const getfieldsJson = async () => {
            fieldsJson.value = await requestTicketData(props.id);
            status.value = fieldsJson.value.estado;
        };

        const getfieldsJsonThread = async () => {
            fieldsJsonThread.value = await requestTicketThreadData(props.id);
        };

        const reloadTicket = () => {
            initComponent();
        };

        const cleanModal = () => {
            modal.value.hide();
            reloadTicket();
        };

        const showEditModal = () => {
            modal.value.show();
        };

        const showNewAnswer = () => {
            setAnswer.value = true;
        };

        const hideNewAnswer = () => {
            setAnswer.value = false;
        };

        const getTicketId = (id) => {
            vId.value = id;
            showEditModal();
        };

        const showSatisfactionModal = () => {
            if (satisfactionModal.value) {
                satisfactionModal.value.show();
            }
        };

        const onTicketClosed = () => {
            requestChangeStatus(props.id, "Cerrado");
            reloadTicket();
            location.reload();
        };

        const reciclerTicket = () => {
            requestChangeStatus(props.id, "Reciclado");
            reloadTicket();
        };

        const getFocus = () => {
            document.getElementById("message").focus();
        };

        const uploadFile = (e) => {
            if (e.target.files.length) {
                file.value = e.target.files[0];
            }
        };

        const clickUploadFile = () => {
            $("#file").click();
        };

        const onSubmit = async () => {
            disabledButton.value = true;
            await dataForm.data
                .uploadFile(`/tickets/mensaje/add/${props.id}`, "crear")
                .then((response) => {
                    toastr.success("Mensaje Creado Satisfactoriamente");
                    reloadThread.value = !reloadThread.value;
                });
            disabledButton.value = false;
        };

        const clearError = (field) => {
            dataForm.data.errors.clear(field);
        };

        return {
            fieldsJson,
            fieldsJsonThread,
            getTicketId,
            vId,
            cleanModal,
            showNewAnswer,
            hideNewAnswer,
            setAnswer,
            reloadTicket,
            showSatisfactionModal,
            onTicketClosed,
            reciclerTicket,
            message,
            getFocus,
            onSubmit,
            dataForm,
            reloadThread,
            clickUploadFile,
            uploadFile,
            disabledButton,
            clearError,
            satisfactionModal,
            status
        };
    },
};
</script>

<style scoped></style>
