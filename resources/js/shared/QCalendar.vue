<template>
    <div id="calendartask"></div>
</template>

<script>
import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listWeek from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import esLocale from "@fullcalendar/core/locales/es";
import { filters } from "../helpers/filters";
import { onMounted, ref, watch } from "vue";
import {
    getTaskEvents,
    reloadEvents,
} from "../components/module/client/billing/helpers/request";
import { setDefaultValue, setDefaultValueSelect } from "../hook/comunValues";

export default {
    name: "QCalendar",
    components: {
        Calendar,
    },
    props: {
        module_id: String,
    },
    setup(props) {
        const calendar = ref();
        const isInitialized = ref(false);

        onMounted(async () => {
            await initializeCalendar();
            isInitialized.value = true;
        });

        watch(filters, async () => {
            await reloadCalendarEvents();
        });

        async function initializeCalendar() {
            try {
                let events = await getTaskEvents();
                let formattedEvents = formatEvents(events);
                applyDynamicStyles(events);

                let calendarEl = document.getElementById("calendartask");
                calendar.value = new Calendar(calendarEl, {
                    plugins: [
                        dayGridPlugin,
                        timeGridPlugin,
                        interactionPlugin,
                        listWeek,
                    ],
                    locale: esLocale,
                    firstDay: 0,
                    headerToolbar: {
                        left: "prev,next today",
                        center: "title",
                        right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
                    },
                    initialView: events[0].initialView ?? "timeGridWeek",
                    events: formattedEvents,
                    editable: true,
                    droppable: true,
                    slotDuration: "00:30:00",
                    contentHeight: "auto",
                    eventClick: async function (info) {},
                    eventDrop: async function (info) {
                        let updatedEvent = adjustEventTime(info);
                        try {
                            await updateTaskEvent(updatedEvent);
                        } catch (error) {
                            console.error(
                                "Error al actualizar el evento:",
                                error
                            );
                            alert("Error al actualizar el evento");
                            info.revert();
                        }
                    },
                    eventResize: async function (info) {
                        // Ajustar la duración del evento después de cambiar el tamaño
                        let updatedEvent = adjustEventTime(info);
                        try {
                            await updateTaskEvent(updatedEvent);
                        } catch (error) {
                            console.error(
                                "Error al actualizar la duración del evento:",
                                error
                            );
                            alert("Error al actualizar la duración del evento");
                            info.revert();
                        }
                    },
                    datesSet: function (info) {
                        if (isInitialized.value) {
                            setDefaultValueSelect(
                                info.view.type,
                                "initialView",
                                props.module_id
                            );
                        }
                    },
                    eventContent: function (arg) {
                        return renderEventContent(arg);
                    },
                });

                calendar.value.render();
            } catch (error) {
                console.error("Error al cargar los eventos:", error);
            }
        }

        const isAllDay = (time) => {
            let hour = time ? parseInt(time.split(":")[0]) : 0;
            return !isNaN(hour) && hour >= 8;
        };

        function formatEvents(events) {
            return events.map((event) => ({
                id: `${event.id}`,
                title: `${event.title}`,
                start: event.start,
                end: event.end,
                textColor: "white",
                allDay: isAllDay(event.estimated_time),
                classNames: event.classNames,
                extendedProps: {
                    textTime: event.time,
                    address: event.address,
                    colorUser: event.color_assigned,
                    colorPriority: event.color_priority,
                    colorStatus: event.color_status,
                    userOrTeamName: event.user_or_team_name,
                },
            }));
        }

        function applyDynamicStyles(events) {
            events.forEach((event) => {
                const style = document.createElement("style");
                document.head.appendChild(style);
            });
        }

        function renderEventContent(arg) {
            let colorUser = arg.event.extendedProps.colorUser;
            if (colorUser == "arcoiris") {
                colorUser =
                    "linear-gradient(to right, red, orange, yellow, green, blue, indigo, violet)";
            }
            let eventContainer = document.createElement("div");
            eventContainer.id = `${arg.event.id}`;
            eventContainer.classList.add("event_edit_task");
            eventContainer.dataset.idItem = arg.event.id;
            eventContainer.style.overflow = "hidden";
            eventContainer.style.height = "-webkit-fill-available";
            eventContainer.dataset.clientInformation =
                arg.event.extendedProps.clientInformation;

            eventContainer.classList.add("event-content-task");

            eventContainer.innerHTML = `
    <div class="priority-indicator"style="background: ${
        arg.event.extendedProps.colorPriority || ""
    };" ></div>
    <div class="task-content">
        <div class="task-title">
            <span>${arg.event.title}</span>
        </div>
    </div>
    <div class="status-user">
        <span class="status-circle" style="background: ${
            arg.event.extendedProps.colorStatus || ""
        };"></span>
        <span class="user-circle" style="background: ${colorUser || ""};"><i>${
                arg.event.extendedProps.userOrTeamName
            }</i></span>
    </div>
`;

            return { domNodes: [eventContainer] };
        }

        const reloadCalendarEvents = async () => {
            try {
                let events = await getTaskEvents(filters.value);
                let formattedEvents = formatEvents(events);
                applyDynamicStyles(events);

                calendar.value.removeAllEvents();
                calendar.value.addEventSource(formattedEvents);
            } catch (error) {
                console.error("Error al recargar los eventos:", error);
            }
        };

        function adjustEventTime(info) {
            let start = info.event.startStr;
            let end = info.event.endStr;
            return {
                id: info.event.id,
                start: start,
                end: end,
            };
        }

        const updateTaskEvent = async (data) => {
            await axios
                .post("/scheduling/task/update-task-to-calendar", {
                    data,
                })
                .then((response) => {
                    toastr.success("Tarea actualizada Correctamente", "Tareas");
                });
        };

        return {};
    },
};
</script>

<style scoped></style>
