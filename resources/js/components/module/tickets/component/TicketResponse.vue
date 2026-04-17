<template>
    <div class="ticket-messages">
        <div class="message-block admin">
            <div class="comment-heading clearfix">
                <div class="pull-right comment-heading-actions">
                    <a
                        v-if="val.file"
                        :href="val.file.path"
                        class="edit-message float-end me-1"
                        data-toggle="tooltip"
                        data-placement="top"
                        title="Descargar Documento"
                        download
                        ><i class="fas fa-download"></i
                    ></a>

                    <a
                        href="javascript:void(0)"
                        class="edit-message float-end me-1"
                        title="Responder mensaje"
                        @click="getFocus"
                    >
                        <span class="fa fa-reply"></span>
                    </a>
                    <a
                        href="javascript:void(0)"
                        class="edit-message float-end me-1"
                        title="Edit message"
                        @click="getTicketId(val.id)"
                    >
                        <span class="fa fa-pen"></span>
                    </a>
                </div>
                <div
                    title="comentario"
                    class="comment-icon default-avatar default_color--7"
                >
                    <span class="default_avatar_letter_custom">i</span>
                </div>
                <div
                    class="comment-title-wrapper"
                    style="width: calc(100% - 110px)"
                >
                    <h5 class="comment-title">{{ val.edited_name }}</h5>
                    <small class="comment-author">
                        <span class="icons"></span>
                        <span class="text-muted">
                            creado
                            <time class="timeago">{{ val.time_human }} </time
                            >({{ val.created_at }})
                        </span>
                    </small>
                    <div>
                        <small> </small>
                    </div>
                </div>
            </div>
            <div class="ticket-message-body message-with-blockquote">
                <!-- Usar CSS para mantener los saltos de línea -->
                <pre class="message-pre">{{ val.message }}</pre>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "TicketResponse",
    props: {
        val: Object,
    },
    emits: ["getTicketId", "getFocus"],
    setup(props, { emit }) {
        const getTicketId = (id) => {
            emit("getTicketId", id);
        };

        const getFocus = () => {
            emit("getFocus");
        };

        return {
            getTicketId,
            getFocus,
        };
    },
};
</script>

<style scoped>
/* Estilos para el pre que mantiene los saltos de línea pero con mejor apariencia */
.message-pre {
    white-space: pre-wrap; /* Mantiene saltos de línea y espacios */
    word-wrap: break-word; /* Rompe palabras largas */
    font-family: inherit; /* Hereda la fuente del contenedor */
    font-size: inherit; /* Hereda el tamaño de fuente */
    line-height: 1.6;
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    color: inherit;
}
</style>
