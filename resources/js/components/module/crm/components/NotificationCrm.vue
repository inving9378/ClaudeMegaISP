<template>
    <div class="form-group row">
        <label
            class="col-xl-12 col-xxl-3 col-form-label text-xl-center text-xxl-right"
            >Notificaciones</label
        >
        <div class="col-sm-12 col-md-6">
            <button
                type="button"
                class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal"
                data-bs-target="#notification_crm"
            >
                Notificar
            </button>
        </div>
    </div>

    <div
        class="modal fade"
        id="notification_crm"
        tabindex="-1"
        role="dialog"
        aria-labelledby="composemodalTitle"
        aria-hidden="true"
    >
        <div
            class="modal-dialog modal-dialog-centered modal-lg"
            role="document"
        >
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-size-16" id="composemodalTitle">
                        Nuevo Mensaje
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input
                            attr_name="email-notificate-crm"
                            id="email-notificate-crm"
                            type="email"
                            class="form-control"
                            placeholder="To"
                            v-model="crmEmail"
                        />
                        <div
                            v-if="errors.has('email-notificate-crm')"
                            class="pristine-error text-help"
                        >
                            El campo correo es requerido y debe ser un correo
                            valido
                        </div>
                    </div>

                    <div class="mb-3">
                        <input
                            attr_name="subject-notificate-crm"
                            id="subject-notificate-crm"
                            type="text"
                            class="form-control"
                            placeholder="Subject"
                            v-model="crmSubject"
                        />
                        <div
                            v-if="errors.has('subject-notificate-crm')"
                            class="pristine-error text-help"
                        >
                            El campo titulo es requerido
                        </div>
                    </div>

                    <div class="mb-3 email-editor">
                        <InputEditor
                            :property="{
                                field: 'message-notificate-crm',
                                title: 'Mensaje:',
                            }"
                            @update-field="updateThisField"
                        ></InputEditor>
                        <div
                            v-if="errors.has('message-notificate-crm')"
                            class="pristine-error text-help"
                        >
                            El campo mensaje es requerido
                        </div>
                    </div>

                    <div
                        v-if="disabled && !dataForm.data.errors.any()"
                        class="alert alert-info alert-dismissible fade show mb-0"
                        role="alert"
                    >
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        Espere un momento mientras se envia el mensaje
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="disabled"
                        @click="sendNotification"
                    >
                        Send <i class="fab fa-telegram-plane ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import InputEditor from "../../../../shared/InputEditor";
import { reactive, ref, watch } from "vue";
import Form from "../../../../helpers/Form";

export default {
    name: "NotificationCrm",
    components: {
        InputEditor,
    },
    props: {
        id: String,
        email: String,
    },
    emits: ["reset"],
    setup(props, { emit }) {
        const fieldsJson = ref({
            "message-notificate-crm": {
                field: "message-notificate-crm",
                value: null,
            },
            "email-notificate-crm": {
                field: "email-notificate-crm",
                value: null,
            },
            "subject-notificate-crm": {
                field: "subject-notificate-crm",
                value: null,
            },
        });
        const dataForm = reactive({
            data: new Form(fieldsJson.value),
        });
        const errors = ref(dataForm.data.errors);
        const disabled = ref(false);
        const crmEmail = ref(props.email);
        const crmSubject = ref("");

        watch(
            () => props.email,
            (email, actionBefore) => {
                crmEmail.value = email;
            }
        );

        watch(crmEmail, () => {
            updateThisField({ field: "email-notificate-crm", value: crmEmail });
        });
        watch(crmSubject, () => {
            updateThisField({
                field: "subject-notificate-crm",
                value: crmSubject,
            });
        });

        const sendNotification = async () => {
            disabled.value = true;
            dataForm.data
                .submit("post", `/crm/send-notification/${props.id}`)
                .then((response) => {
                    disabled.value = false;
                    $("#notification_crm").modal("hide");
                    emit("reset");
                })
                .catch((e) => {
                    disabled.value = false;
                });
        };

        const updateThisField = ({ field, value }) => {
            clearError(field);
            dataForm.data[field] = value;
        };

        const updateThisFieldInput = (field) => {
            clearError(field);
            dataForm.data[field] = $(`#${field}`).val();
        };

        const clearError = (field) => {
            dataForm.data.errors.clear(field);
        };

        return {
            sendNotification,
            updateThisField,
            updateThisFieldInput,
            dataForm,
            errors,
            disabled,
            crmEmail,
            crmSubject,
        };
    },
};
</script>

<style scoped></style>
