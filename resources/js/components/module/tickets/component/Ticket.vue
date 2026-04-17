<template>
    <div class="ticket-messages">
        <div class="message-block admin">
            <div class="comment-heading clearfix">
                <div class="pull-right comment-heading-actions">
                    <a
                        v-if="fieldsJson.ticket && fieldsJson.ticket.files"
                        :href="
                            fieldsJson.ticket && fieldsJson.ticket.files
                                ? fieldsJson.ticket.files.path
                                : ''
                        "
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
                        @click="getTicketId(fieldsJson.id)"
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
                    <h5 class="comment-title">{{ fieldsJson.edited_name }}</h5>
                    <small class="comment-author">
                        <span class="icons"></span>
                        <span class="text-muted">
                            creado
                            <time class="timeago"
                                >{{ fieldsJson.time_human }} </time
                            >({{ fieldsJson.created_at }})
                        </span>
                    </small>
                    <div>
                        <small> </small>
                    </div>
                </div>
            </div>
            <div class="ticket-message-body message-with-blockquote">
                <p>{{ fieldsJson.message }}</p>
            </div>
        </div>
    </div>

    <div class="form-group col-sm-12" v-for="val in responseTicket">
        <TicketResponse
            :val="val"
            @getTicketId="getTicketId"
            @getFocus="getFocus"
        ></TicketResponse>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import TicketResponse from "./TicketResponse.vue";
import {
    requestParentTicketData,
    requestChildTicketData,
} from "../helper/request";

export default {
    name: "Ticket",
    props: {
        id: String,
        reloadThread: Boolean,
    },
    emits: ["getTicketId", "getFocus"],
    components: { TicketResponse },
    setup(props, { emit }) {
        const fieldsJson = ref({});
        const responseTicket = ref({});

        onMounted(() => {
            getfieldsJson();
            gettTicketThreadDataJson();
        });

        watch(
            () => props.reloadThread,
            (reloadThread, reloadThreadBefore) => {
                getfieldsJson();
                gettTicketThreadDataJson();
            }
        );

        const getfieldsJson = async () => {
            fieldsJson.value = await requestParentTicketData(props.id);
        };

        const gettTicketThreadDataJson = async () => {
            responseTicket.value = await requestChildTicketData(props.id);
        };

        const getTicketId = (id) => {
            emit("getTicketId", id);
        };

        const getFocus = () => {
            emit("getFocus");
        };

        return { fieldsJson, responseTicket, getTicketId, getFocus };
    },
};
</script>

<style scoped></style>
