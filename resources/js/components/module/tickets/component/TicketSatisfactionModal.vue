<template>
    <div
        class="modal fade"
        id="satisfactionModal"
        tabindex="-1"
        aria-labelledby="satisfactionModalLabel"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="satisfactionModalLabel">
                        Encuesta de Satisfacción
                    </h5>
                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body">
                    <form id="satisfactionForm">
                        <div class="mb-3">
                            <label class="form-label"
                                >1. ¿Se resolvió completamente el problema por
                                el que nos contactó?</label
                            >
                            <div>
                                <div class="form-check form-check-inline">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.problemSolved"
                                        id="problemSolvedYes"
                                        value="Sí"
                                        required
                                    />
                                    <label
                                        class="form-check-label"
                                        for="problemSolvedYes"
                                        >Sí</label
                                    >
                                </div>
                                <div class="form-check form-check-inline">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.problemSolved"
                                        id="problemSolvedNo"
                                        value="No"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="problemSolvedNo"
                                        >No</label
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                >2. ¿Quedó alguna duda sobre la solución
                                proporcionada?</label
                            >
                            <div>
                                <div class="form-check form-check-inline">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.remainingDoubt"
                                        id="remainingDoubtYes"
                                        value="Sí"
                                        required
                                    />
                                    <label
                                        class="form-check-label"
                                        for="remainingDoubtYes"
                                        >Sí</label
                                    >
                                </div>
                                <div class="form-check form-check-inline">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.remainingDoubt"
                                        id="remainingDoubtNo"
                                        value="No"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="remainingDoubtNo"
                                        >No</label
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                >3. ¿Cómo calificaría la atención recibida
                                durante este proceso?</label
                            >
                            <div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.serviceRating"
                                        id="serviceRatingExcellent"
                                        value="Excelente"
                                        required
                                    />
                                    <label
                                        class="form-check-label"
                                        for="serviceRatingExcellent"
                                        >Excelente</label
                                    >
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.serviceRating"
                                        id="serviceRatingGood"
                                        value="Buena"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="serviceRatingGood"
                                        >Buena</label
                                    >
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.serviceRating"
                                        id="serviceRatingRegular"
                                        value="Regular"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="serviceRatingRegular"
                                        >Regular</label
                                    >
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.serviceRating"
                                        id="serviceRatingPoor"
                                        value="Mala"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="serviceRatingPoor"
                                        >Mala</label
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                >4. ¿Recomendaría nuestro servicio a otras
                                personas?</label
                            >
                            <div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.wouldRecommend"
                                        id="wouldRecommendYes"
                                        value="Sí"
                                        required
                                    />
                                    <label
                                        class="form-check-label"
                                        for="wouldRecommendYes"
                                        >Sí</label
                                    >
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.wouldRecommend"
                                        id="wouldRecommendProbably"
                                        value="Probablemente"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="wouldRecommendProbably"
                                        >Probablemente</label
                                    >
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        v-model="formData.wouldRecommend"
                                        id="wouldRecommendNo"
                                        value="No"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="wouldRecommendNo"
                                        >No</label
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="additionalComments" class="form-label"
                                >5. Comentarios adicionales (opcional)</label
                            >
                            <textarea
                                class="form-control"
                                id="additionalComments"
                                v-model="formData.additionalComments"
                                rows="2"
                                placeholder="Si tiene algún comentario adicional, puede escribirlo aquí"
                            ></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="submitForm"
                    >
                        Cerrar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import Modal from "../../../../helpers/modal";
import Swal from "sweetalert2";

export default {
    name: "TicketSatisfactionModal",
    props: {
        ticketId: {
            type: String,
            required: true,
        },
        dataForm: {
            type: Object,
            required: true,
        },
    },
    emits: ["ticketClosed"],
    setup(props, { emit }) {
        const modal = ref(null);
        const formData = ref({
            problemSolved: "",
            remainingDoubt: "",
            serviceRating: "",
            wouldRecommend: "",
            additionalComments: "",
        });

        onMounted(() => {
            modal.value = new Modal("satisfactionModal");
        });

        const show = () => {
            if (modal.value) {
                modal.value.show();
            }
        };

        const hide = () => {
            if (modal.value) {
                modal.value.hide();
            }
        };

        const resetForm = () => {
            formData.value = {
                problemSolved: "",
                remainingDoubt: "",
                serviceRating: "",
                wouldRecommend: "",
                additionalComments: "",
            };
        };

        const generateNoteContent = () => {
            let noteContent =
                "📋 ENCUESTA DE SATISFACCIÓN AL CERRAR TICKET\n\n";
            noteContent += `1. ¿Se resolvió completamente el problema? → ${formData.value.problemSolved}\n`;
            noteContent += `2. ¿Quedó alguna duda sobre la solución? → ${formData.value.remainingDoubt}\n`;
            noteContent += `3. Calificación de la atención recibida → ${formData.value.serviceRating}\n`;
            noteContent += `4. ¿Recomendaría nuestro servicio? → ${formData.value.wouldRecommend}\n`;

            if (formData.value.additionalComments) {
                noteContent += `5. Comentarios adicionales → ${formData.value.additionalComments}\n`;
            }

            noteContent += `\n📅 Encuesta completada el ${new Date().toLocaleString(
                "es-ES"
            )}`;

            return noteContent;
        };

        const submitForm = async () => {
            // Validar que todos los campos obligatorios estén completos
            if (
                !formData.value.problemSolved ||
                !formData.value.remainingDoubt ||
                !formData.value.serviceRating ||
                !formData.value.wouldRecommend
            ) {
                Swal.fire({
                    icon: "warning",
                    title: "Campos incompletos",
                    text: "Por favor, responda todas las preguntas obligatorias.",
                });
                return;
            }

            try {
                // Generar el contenido de la nota
                const noteContent = generateNoteContent();
                props.dataForm.data.message = noteContent;

                await props.dataForm.data
                    .uploadFile(`/tickets/mensaje/add/${props.ticketId}`, "crear")
                    .then((response) => {
                        console.log(response);
                    });

                hide();
                resetForm();

                Swal.fire({
                    icon: "success",
                    title: "Ticket cerrado",
                    text: "La encuesta de satisfacción ha sido registrada como nota en el ticket.",
                });

                // Emitir evento para notificar que el ticket fue cerrado
                emit("ticketClosed");
            } catch (error) {
                console.error("Error al enviar la encuesta:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Ha ocurrido un error al procesar la encuesta. Por favor, intente nuevamente.",
                });
            }
        };

        return {
            formData,
            show,
            hide,
            submitForm,
        };
    },
};
</script>

<style scoped>
.modal-content {
    border-radius: 10px;
    border: none;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
}

.modal-header {
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.form-label {
    font-weight: 500;
    margin-bottom: 8px;
}

.form-check {
    margin-bottom: 5px;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>
