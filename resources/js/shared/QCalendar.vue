<template>
    <!-- Calendario de tareas de Scheduling con aspecto de Google Calendar (2026-11-04). -->
    <div class="gc">
        <div class="gc-barra">
            <div class="gc-nav">
                <button type="button" class="gc-btn gc-btn-borde" @click="api('today')">Hoy</button>
                <button type="button" class="gc-btn gc-redondo" aria-label="Anterior" @click="api('prev')">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button type="button" class="gc-btn gc-redondo" aria-label="Siguiente" @click="api('next')">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <h5 class="gc-titulo">{{ titulo }}</h5>
                <span v-if="cargando" class="spinner-border spinner-border-sm gc-cargando"></span>
            </div>
            <div class="gc-derecha">
                <div class="gc-vistas" role="group" aria-label="Vista">
                    <button v-for="v in vistas" :key="v.id" type="button" :class="{ activo: vista === v.id }" @click="cambiarVista(v.id)">
                        {{ v.nombre }}
                    </button>
                </div>
                <slot name="acciones"></slot>
            </div>
        </div>

        <slot name="debajo"></slot>

        <!-- Arriba de la cuadrícula y fija al bajar: debajo del calendario quedaba fuera de la pantalla. -->
        <div class="gc-leyenda">
            <span class="gc-leyenda-tit">Prioridad</span>
            <span v-for="p in prioridades" :key="p.id" class="gc-ley"><i :style="{ background: p.color }"></i>{{ p.nombre }}</span>
            <span class="gc-sep"></span>
            <span class="gc-leyenda-tit">Estado</span>
            <span class="gc-ley"><span class="gc-est gc-est-ToDo"></span>Por hacer</span>
            <span class="gc-ley"><span class="gc-est gc-est-InProgress"></span>En progreso</span>
            <span class="gc-ley"><span class="gc-est gc-est-Done"><i class="fa fa-check"></i></span>Terminada</span>
            <span class="gc-ley"><span class="gc-marca-atrasada">!</span>Atrasada</span>
        </div>

        <div v-if="error" class="gc-error">{{ error }}</div>
        <div ref="cal" class="gc-cal"></div>
    </div>
</template>

<script>
import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import esLocale from "@fullcalendar/core/locales/es";
import { filters } from "../helpers/filters";
import { getTaskEvents } from "../components/module/client/billing/helpers/request";
import { setDefaultValueSelect } from "../hook/comunValues";

const PRIORIDADES = [
    { id: "Alta", nombre: "Alta", color: "#d93025" },
    { id: "Media", nombre: "Media", color: "#f29900" },
    { id: "Baja", nombre: "Baja", color: "#1a73e8" },
];
const SIN_PRIORIDAD = "#5f6368";
const ABIERTAS = ["ToDo", "InProgress"];

/** "#d93025" → "rgba(217,48,37,a)" para el fondo suave del evento. */
function suave(hex, a) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
}

function iniciales(nombre) {
    const partes = String(nombre || "").trim().split(/\s+/).filter(Boolean);
    if (!partes.length) return "";
    return (partes[0][0] + (partes[1] ? partes[1][0] : "")).toUpperCase();
}

/**
 * Calendario de Scheduling › Calendario. La instancia de FullCalendar se guarda en `this.cal` fuera de
 * data(): el proxy reactivo de Vue la rompe. Cada evento lleva la clase `event_edit_task` con
 * `data-id-item`, que es lo que CalendarIndex escucha para abrir la tarea.
 */
export default {
    name: "QCalendar",
    props: {
        module_id: String,
    },
    data() {
        return {
            titulo: "",
            vista: "timeGridWeek",
            cargando: false,
            error: "",
            prioridades: PRIORIDADES,
            vistas: [
                { id: "dayGridMonth", nombre: "Mes" },
                { id: "timeGridWeek", nombre: "Semana" },
                { id: "timeGridDay", nombre: "Día" },
                { id: "listWeek", nombre: "Lista" },
            ],
        };
    },
    async mounted() {
        let inicial = "timeGridWeek";
        this.cargando = true;
        try {
            // La vista que dejó guardada la persona llega en el primer evento del mes.
            const primeros = await getTaskEvents({ ...(filters.value || {}), ...this.mesEnCurso() });
            if (primeros?.[0]?.initialView) inicial = primeros[0].initialView;
        } catch (e) {
            window.console.error("Calendario: no se pudo leer la vista guardada", e);
        }
        if (!this.vistas.some((v) => v.id === inicial)) inicial = "timeGridWeek";
        this.vista = inicial;
        this.listo = false;

        this.cal = new Calendar(this.$refs.cal, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
            locale: esLocale,
            firstDay: 0,
            headerToolbar: false,
            initialView: inicial,
            // Semana y día: 720 px con su propia barra, abriendo en las 7:00 (como Google).
            contentHeight: 720,
            dayMaxEvents: 4,
            nowIndicator: true,
            scrollTime: "07:00:00",
            slotDuration: "00:30:00",
            eventTimeFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
            slotLabelFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
            editable: true,
            droppable: true,
            events: (info, ok, falla) => this.traer(info, ok, falla),
            loading: (activo) => (this.cargando = activo),
            datesSet: (info) => {
                this.titulo = info.view.title;
                this.vista = info.view.type;
                if (this.listo) setDefaultValueSelect(info.view.type, "initialView", this.module_id);
                this.listo = true;
            },
            eventContent: (arg) => this.contenido(arg),
            eventDrop: (info) => this.mover(info),
            eventResize: (info) => this.mover(info),
        });
        this.cal.render();
        this.dejarDeVigilar = this.$watch(() => filters.value, () => this.cal?.refetchEvents());
    },
    beforeUnmount() {
        this.dejarDeVigilar?.();
        this.cal?.destroy();
        this.cal = null;
    },
    methods: {
        api(accion) {
            this.cal?.[accion]();
        },
        cambiarVista(id) {
            this.cal?.changeView(id);
        },
        mesEnCurso() {
            const hoy = new Date();
            const fmt = (d) => d.toISOString().slice(0, 10);
            return {
                rango_inicio: fmt(new Date(hoy.getFullYear(), hoy.getMonth(), 1)),
                rango_fin: fmt(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)),
            };
        },
        async traer(info, ok, falla) {
            this.error = "";
            try {
                const data = await getTaskEvents({
                    ...(filters.value || {}),
                    rango_inicio: info.startStr,
                    rango_fin: info.endStr,
                });
                ok((Array.isArray(data) ? data : []).map((e) => this.evento(e)));
            } catch (e) {
                window.console.error("Calendario: no se pudieron cargar las tareas", e);
                this.error = "No se pudieron cargar las tareas. Vuelve a intentar en un momento.";
                falla(e);
            }
        },
        evento(e) {
            const prioridad = PRIORIDADES.find((p) => p.id === e.priority);
            // Una tarea de 24 h o más va en la franja «Todo el día» (como Google): en la vista de semana
            // ocuparía columnas enteras y taparía las demás. Así no se arrastra (perdería sus horas):
            // se cambia desde la tarea.
            const ini = new Date(e.start);
            const fin = e.end ? new Date(e.end) : null;
            const variosDias = !!fin && fin - ini >= 864e5;
            const dia = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
            const finExclusivo = variosDias
                ? dia(new Date(fin.getFullYear(), fin.getMonth(), fin.getDate() + (fin.getHours() || fin.getMinutes() ? 1 : 0)))
                : null;
            return {
                id: String(e.id),
                title: e.title || "(sin título)",
                start: variosDias ? dia(ini) : e.start,
                end: variosDias ? finExclusivo : e.end,
                allDay: variosDias,
                // Una orden de Talento se mueve con su motivo desde Talento › Órdenes o «Mi día».
                editable: !e.es_orden_talento && !variosDias,
                extendedProps: {
                    color: prioridad ? prioridad.color : SIN_PRIORIDAD,
                    estado: e.status,
                    abierta: ABIERTAS.includes(e.status),
                    hora: e.timeStart,
                    persona: e.user_or_team_name,
                    colorPersona: e.color_assigned,
                    clienteInfo: e.client_main_information_id,
                    finReal: fin ? fin.getTime() : ini.getTime(),
                },
            };
        },
        contenido(arg) {
            const ev = arg.event;
            const p = ev.extendedProps;
            const atrasada = p.abierta && p.finReal < Date.now();
            const lista = arg.view.type.startsWith("list");
            const horas = arg.view.type.startsWith("timeGrid");

            const caja = document.createElement("div");
            caja.className = "gc-ev event_edit_task" + (p.estado === "Done" ? " gc-ev-hecha" : "") + (lista ? " gc-ev-lista" : "");
            caja.dataset.idItem = ev.id;
            if (p.clienteInfo) caja.dataset.clientInformation = p.clienteInfo;
            caja.style.setProperty("--gc-c", p.color);
            caja.style.setProperty("--gc-c-suave", suave(p.color, 0.14));
            caja.title = [ev.title, p.hora, p.persona].filter(Boolean).join(" · ");

            const fila = document.createElement("div");
            fila.className = "gc-ev-fila";

            const est = document.createElement("span");
            est.className = `gc-est gc-est-${p.estado || "ToDo"}`;
            if (p.estado === "Done") {
                const i = document.createElement("i");
                i.className = "fa fa-check";
                est.appendChild(i);
            }
            fila.appendChild(est);

            if (atrasada) {
                const m = document.createElement("span");
                m.className = "gc-marca-atrasada";
                m.textContent = "!";
                m.title = "Atrasada: ya pasó su hora y sigue abierta";
                fila.appendChild(m);
            }

            const t = document.createElement("span");
            t.className = "gc-ev-titulo";
            t.textContent = (!horas && !lista && p.hora ? p.hora + " " : "") + ev.title;
            fila.appendChild(t);

            if (p.persona) {
                const a = document.createElement("span");
                a.className = "gc-quien";
                a.textContent = iniciales(p.persona);
                a.title = p.persona;
                a.style.background = p.colorPersona === "arcoiris"
                    ? "linear-gradient(135deg,#ea4335,#fbbc04,#34a853,#4285f4)"
                    : p.colorPersona || "#5f6368";
                fila.appendChild(a);
            }
            caja.appendChild(fila);

            if (horas && arg.timeText) {
                const h = document.createElement("div");
                h.className = "gc-ev-hora";
                h.textContent = arg.timeText;
                caja.appendChild(h);
            }

            return { domNodes: [caja] };
        },
        async mover(info) {
            try {
                await axios.post("/scheduling/task/update-task-to-calendar", {
                    data: { id: info.event.id, start: info.event.startStr, end: info.event.endStr },
                });
                window.toastr?.success("Tarea actualizada", "Tareas");
            } catch (e) {
                window.console.error("Calendario: no se pudo mover la tarea", e);
                window.toastr?.error("No se pudo mover la tarea", "Tareas");
                info.revert();
            }
        },
    },
};
</script>

<style scoped>
.gc {
    --gc-borde: #e0e3e7;
    --gc-texto: #3c4043;
    --gc-suave: #70757a;
    --gc-fondo: #fff;
    --gc-hover: #f1f3f4;
    --gc-acento: #1a73e8;
    --gc-acento-suave: #e8f0fe;
    color: var(--gc-texto);
    font-family: "Google Sans", Roboto, -apple-system, "Segoe UI", Arial, sans-serif;
}
:global(body[data-layout-mode="dark"] .gc) {
    --gc-borde: #3c4043;
    --gc-texto: #e8eaed;
    --gc-suave: #9aa0a6;
    --gc-fondo: #202124;
    --gc-hover: #2d2e31;
    --gc-acento: #8ab4f8;
    --gc-acento-suave: rgba(138, 180, 248, 0.16);
}
.gc-barra {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}
.gc-nav,
.gc-derecha {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.gc-titulo {
    margin: 0 0 0 8px;
    font-size: 22px;
    font-weight: 400;
    color: var(--gc-texto);
}
.gc-titulo::first-letter {
    text-transform: uppercase;
}
.gc-cargando {
    color: var(--gc-suave);
    margin-left: 6px;
}
.gc-btn {
    border: 0;
    background: transparent;
    color: var(--gc-texto);
}
.gc-btn:hover {
    background: var(--gc-hover);
}
.gc-btn-borde {
    border: 1px solid var(--gc-borde);
    border-radius: 18px;
    padding: 6px 16px;
    font-weight: 500;
    font-size: 14px;
}
.gc-redondo {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: var(--gc-suave);
}
.gc-vistas {
    display: inline-flex;
    border: 1px solid var(--gc-borde);
    border-radius: 18px;
    overflow: hidden;
}
.gc-vistas button {
    border: 0;
    background: transparent;
    color: var(--gc-texto);
    padding: 6px 15px;
    font-size: 13px;
    font-weight: 500;
}
.gc-vistas button + button {
    border-left: 1px solid var(--gc-borde);
}
.gc-vistas button.activo {
    background: var(--gc-acento-suave);
    color: var(--gc-acento);
    font-weight: 600;
}
.gc-error {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    background: #fce8e6;
    color: #c5221f;
}

/* FullCalendar con aspecto de Google Calendar */
.gc-cal {
    --fc-border-color: var(--gc-borde);
    --fc-page-bg-color: var(--gc-fondo);
    --fc-neutral-bg-color: var(--gc-hover);
    --fc-today-bg-color: transparent;
    --fc-list-event-hover-bg-color: var(--gc-hover);
    --fc-now-indicator-color: #ea4335;
    background: var(--gc-fondo);
    font-size: 12px;
}
.gc-cal :deep(td),
.gc-cal :deep(th),
.gc-cal :deep(.fc-scrollgrid),
.gc-cal :deep(.fc-list) {
    border-color: var(--gc-borde) !important;
}
.gc-cal :deep(.fc-col-header-cell-cushion) {
    color: var(--gc-suave) !important;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    text-decoration: none;
    padding: 8px 0;
}
.gc-cal :deep(.fc-day-today .fc-col-header-cell-cushion) {
    color: var(--gc-acento) !important;
    font-weight: 700;
}
.gc-cal :deep(.fc-daygrid-day-top) {
    justify-content: center;
}
.gc-cal :deep(.fc-daygrid-day-number) {
    color: var(--gc-texto) !important;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    width: 24px;
    height: 24px;
    margin-top: 4px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.gc-cal :deep(.fc-day-today .fc-daygrid-day-number) {
    background: var(--gc-acento);
    color: var(--gc-fondo) !important;
}
.gc-cal :deep(.fc-day-other .fc-daygrid-day-number) {
    color: var(--gc-suave) !important;
}
.gc-cal :deep(.fc-daygrid-more-link) {
    color: var(--gc-texto);
    font-weight: 600;
    font-size: 11px;
    padding-left: 4px;
}
.gc-cal :deep(.fc-event) {
    border: 0;
    background: transparent;
    box-shadow: none;
    margin: 1px 2px;
}
.gc-cal :deep(.fc-event-main) {
    padding: 0;
    color: inherit;
}
.gc-cal :deep(.fc-timegrid-slot) {
    height: 2.3em;
}
.gc-cal :deep(.fc-timegrid-slot-minor) {
    border-top-style: none;
}
.gc-cal :deep(.fc-timegrid-slot-label-cushion),
.gc-cal :deep(.fc-timegrid-axis-cushion) {
    color: var(--gc-suave);
    font-size: 10px;
}
.gc-cal :deep(.fc-list-day-cushion) {
    background: var(--gc-hover);
}
.gc-cal :deep(.fc-list-day-text),
.gc-cal :deep(.fc-list-day-side-text) {
    color: var(--gc-texto) !important;
    text-decoration: none;
}
.gc-cal :deep(.fc-list-event-dot) {
    display: none;
}
.gc-cal :deep(.fc-list-event-time) {
    color: var(--gc-suave);
}
.gc-cal :deep(.fc-popover) {
    background: var(--gc-fondo);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
}
.gc-cal :deep(.fc-popover-header) {
    background: transparent;
}
/* `display: block` y el padding pisan el `.event_edit_task { display: flex }` global del calendario viejo. */
.gc-cal :deep(.gc-ev) {
    display: block;
    text-align: left;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-left: 4px solid var(--gc-c);
    background: var(--gc-c-suave);
    color: var(--gc-texto);
    border-radius: 4px;
    padding: 2px 5px;
    line-height: 1.25;
    cursor: pointer;
}
.gc-cal :deep(.gc-ev:hover) {
    filter: brightness(0.97);
}
.gc-cal :deep(.gc-ev-lista) {
    border-left: 0;
    background: transparent;
    padding: 0;
}
.gc-cal :deep(.gc-ev-fila) {
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
}
.gc-cal :deep(.gc-ev-titulo) {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gc-cal :deep(.gc-ev-hora) {
    font-size: 11px;
    opacity: 0.8;
    white-space: nowrap;
}
.gc-cal :deep(.gc-ev-hecha .gc-ev-titulo) {
    text-decoration: line-through;
    font-weight: 500;
}
.gc-cal :deep(.gc-ev-hecha) {
    opacity: 0.6;
}
.gc-cal :deep(.gc-quien) {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    font-weight: 700;
    color: #fff;
}

/* Estado (también en la leyenda) */
.gc :deep(.gc-est) {
    flex: 0 0 auto;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}
.gc :deep(.gc-est-ToDo),
.gc :deep(.gc-est-PostponedByClient),
.gc :deep(.gc-est-Archivado) {
    border: 2px solid var(--gc-suave);
}
.gc :deep(.gc-est-InProgress) {
    border: 2px solid #f29900;
    background: linear-gradient(90deg, #f29900 50%, transparent 50%);
}
.gc :deep(.gc-est-Done) {
    background: #188038;
    color: #fff;
    font-size: 7px;
}
.gc :deep(.gc-marca-atrasada) {
    flex: 0 0 auto;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #d93025;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* `.main-content` trae overflow: hidden y así `sticky` no pega; `clip` recorta igual sin ser contenedor de scroll. */
:global(.main-content:has(.gc-leyenda)) {
    overflow: clip;
}
/* `top` es el alto de la barra superior del sistema, que también es fija. */
.gc-leyenda {
    position: sticky;
    top: 70px;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    padding: 8px 0;
    font-size: 12px;
    color: var(--gc-suave);
    background: var(--gc-fondo);
}
.gc-leyenda-tit {
    font-weight: 600;
    color: var(--gc-texto);
}
.gc-ley {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.gc-ley > i {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
}
.gc-sep {
    width: 1px;
    height: 14px;
    background: var(--gc-borde);
}
@media (max-width: 576px) {
    .gc-titulo {
        font-size: 17px;
    }
    .gc-vistas button {
        padding: 5px 10px;
    }
}
</style>
