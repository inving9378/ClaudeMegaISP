<template>
    <div class="mt-2" id='calendar' v-show="showCalendar"></div>
    <div class="mt-2" v-show="showCalendar">
        <div class="external-event fc-event text-info bg-soft-info" data-class="bg-success">
            <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i> Dia de facturación
        </div>
        <div class="external-event fc-event text-warning bg-soft-warning" data-class="bg-info">
            <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i> Dia de Expiracion del servicio
        </div>
        <div class="external-event fc-event text-danger bg-soft-danger" data-class="bg-warning">
            <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i> Fin del Periodo de Gracia
        </div>
    </div>
</template>

<script>
import {onMounted, ref, watch} from 'vue';
import {getEventByDate} from "../helpers/request";
import {Calendar} from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'

export default {
    name: "Minifullcalend",
    props: {
        showMiniCalendar: {
            type: Boolean,
            default: false
        },
        data: Object
    },
    setup(props) {
        const showCalendar = ref(props.showMiniCalendar);
        const calendar = ref();
        const pending = ref(0);
        const renderingCalendar = ref(false);

        watch(
            () => props.showMiniCalendar,
            (showMiniCalendar, showMiniCalendarBefore) => {
                showCalendar.value = showMiniCalendar;
            }
        );

        watch(
            () => props.data.billing_date,
            () => {
                if (calendar.value) {
                    pending.value++;
                }
            }
        );

        watch(
            () => props.data.billing_expiration,
            () => {
                if (calendar.value) {
                    pending.value++;
                }
            }
        );

        watch(
            () => props.data.grace_period,
            () => {
                if (calendar.value) {
                    pending.value++;
                }
            }
        );

        watch(pending, async () => {
            if (pending.value > 0 && !renderingCalendar.value) {
                await renderCalendar();
                pending.value--;
            }
        })

        const renderCalendar = async () => {
            renderingCalendar.value = true;
            await calendar.value.removeAllEvents();
            let event = await getEventByDate(props.data);
            calendar.value.addEventSource(event);
            renderingCalendar.value = false;
        }

        onMounted(() => {
            $(document).ready(function () {
                let calendarEl = document.getElementById('calendar')
                calendar.value = new Calendar(calendarEl, {
                    plugins: [dayGridPlugin],
                })

                setTimeout(async () => {
                    await calendar.value.render();
                }, 500)
            })
        })

        return {showCalendar}
    }
}
</script>

<style>
#calendar {
    height: 500px;
}
</style>
