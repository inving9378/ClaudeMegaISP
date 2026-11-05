<template>
    <div class="ag" :class="{ 'ag-dark': darkMode }">
        <div class="ag-barra">
            <div class="ag-nav">
                <button type="button" class="ag-btn ag-btn-borde" @click="api('today')">Hoy</button>
                <button type="button" class="ag-btn ag-redondo" aria-label="Anterior" @click="api('prev')">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button type="button" class="ag-btn ag-redondo" aria-label="Siguiente" @click="api('next')">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <h5 class="ag-titulo">{{ titulo }}</h5>
                <span v-if="cargando" class="spinner-border spinner-border-sm text-muted"></span>
            </div>
            <div class="ag-derecha">
                <select v-if="!colaboradorId" v-model="persona" class="form-select form-select-sm ag-select" aria-label="Persona">
                    <option :value="null">Todo el equipo</option>
                    <option v-for="p in personas" :key="p.colaborador_id" :value="p.colaborador_id">{{ p.nombre }}</option>
                </select>
                <label class="ag-solo-atrasadas">
                    <input v-model="soloAtrasadas" type="checkbox" class="form-check-input me-1" />Solo atrasadas
                    <span v-if="numAtrasadas" class="ag-num-atrasadas">{{ numAtrasadas }}</span>
                </label>
                <div class="ag-vistas" role="group" aria-label="Vista">
                    <button v-for="v in vistas" :key="v.id" type="button" :class="{ activo: vista === v.id }" @click="cambiarVista(v.id)">
                        {{ v.texto }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

        <div ref="calendario" class="ag-cal"></div>

        <div class="ag-leyenda">
            <span class="ag-leyenda-tit">Tipos:</span>
            <span v-for="t in tiposActivos" :key="t.id" class="ag-tipo" :class="{ apagado: ocultos.includes(t.id) }">
                <!-- Quien gestiona órdenes toca el cuadrito para cambiar el color del tipo; el nombre muestra u oculta. -->
                <input v-if="puedeEditarColores" type="color" class="ag-color" :value="t.color"
                       :title="`Cambiar el color de ${t.nombre}`" @change="cambiarColor(t, $event.target.value)" />
                <button type="button" class="ag-tipo-btn" :title="ocultos.includes(t.id) ? 'Mostrar' : 'Ocultar'" @click="alternarTipo(t.id)">
                    <i v-if="!puedeEditarColores" :style="{ background: t.color }"></i>{{ t.nombre }}
                </button>
            </span>
            <span class="ag-tipo"><span class="ag-tipo-btn"><i :style="{ background: colorAtrasada }"></i>Atrasada</span></span>
            <span v-if="puedeGestionar" class="ag-ayuda">
                Arrastra una orden abierta a otro día para reprogramarla (te pide el motivo).<template v-if="puedeEditarColores"> Toca un cuadrito de color para cambiarlo; toca el nombre para ocultar ese tipo.</template>
            </span>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import esLocale from "@fullcalendar/core/locales/es";
import { darkMode } from "../../../hook/appConfig.js";

const ABIERTAS = ["pending", "in_progress"];
const HECHAS = ["completed", "validated"];
const pad = (n) => String(n).padStart(2, "0");
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

function alfa(hex, a) {
    const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || "");
    if (!m) return `rgba(97,97,97,${a})`;
    return `rgba(${parseInt(m[1], 16)},${parseInt(m[2], 16)},${parseInt(m[3], 16)},${a})`;
}

/**
 * Agenda de órdenes de Talento estilo Google (item #1158): mes, semana, día y lista; una orden =
 * un evento con el color de su tipo; lo atrasado se pinta en rojo en HOY con la fecha que tenía.
 * Mismos datos que el calendario chico de «Asignar trabajo» (GET /talento/api/ordenes/carga).
 */
export default {
    name: "TalentoAgenda",
    props: {
        // Dentro de la ficha de una persona: solo su agenda.
        colaboradorId: { type: [Number, String], default: null },
        puedeGestionar: { type: Boolean, default: false },
        puedeEditarColores: { type: Boolean, default: false },
    },
    emits: ["ver", "mover"],
    setup() {
        return { darkMode };
    },
    data() {
        return {
            titulo: "",
            vista: "dayGridMonth",
            vistas: [
                { id: "dayGridMonth", texto: "Mes" },
                { id: "timeGridWeek", texto: "Semana" },
                { id: "timeGridDay", texto: "Día" },
                { id: "listWeek", texto: "Lista" },
            ],
            ordenes: [],
            tipos: [],
            personasVistas: {},
            colorAtrasada: "#D50000",
            persona: null,
            soloAtrasadas: false,
            ocultos: [],
            cargando: false,
            error: "",
        };
    },
    computed: {
        tiposActivos() {
            return this.tipos.filter((t) => t.activo);
        },
        personas() {
            return Object.values(this.personasVistas).sort((a, b) => a.nombre.localeCompare(b.nombre));
        },
        numAtrasadas() {
            return this.filtradas(false).filter((o) => o.atrasada).length;
        },
    },
    watch: {
        persona() {
            this.pintar();
        },
        soloAtrasadas() {
            this.pintar();
        },
        ocultos() {
            this.pintar();
        },
    },
    mounted() {
        // Fuera de data(): un proxy reactivo de Vue alrededor del calendario lo rompe por dentro.
        this.cal = new Calendar(this.$refs.calendario, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: esLocale,
            firstDay: 1,
            headerToolbar: false,
            initialView: this.vista,
            height: "auto",
            dayMaxEvents: 4,
            nowIndicator: true,
            slotMinTime: "07:00:00",
            slotMaxTime: "21:00:00",
            allDayText: "Sin hora",
            noEventsText: "No hay órdenes en estos días.",
            eventOrder: "clave",
            displayEventTime: false,
            editable: this.puedeGestionar,
            eventDurationEditable: false,
            datesSet: (info) => {
                this.titulo = info.view.title.charAt(0).toUpperCase() + info.view.title.slice(1);
                this.vista = info.view.type;
                this.cargar(info.start, info.end);
            },
            eventContent: (arg) => this.contenido(arg),
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                this.$emit("ver", info.event.extendedProps.orden);
            },
            eventDrop: (info) => {
                const o = info.event.extendedProps.orden;
                const dia = iso(info.event.start);
                info.revert();
                if (dia < iso(new Date())) {
                    this.error = "Una orden no se puede mover a un día que ya pasó.";
                    return;
                }
                this.error = "";
                const hora = info.event.allDay ? "" : ` ${pad(info.event.start.getHours())}:${pad(info.event.start.getMinutes())}`;
                this.$emit("mover", { orden: o, fecha: dia + hora });
            },
        });
        this.cal.render();
    },
    beforeUnmount() {
        this.cal?.destroy();
    },
    methods: {
        api(accion) {
            this.cal?.[accion]();
        },
        cambiarVista(v) {
            this.cal?.changeView(v);
        },
        recargar() {
            const v = this.cal?.view;
            if (v) this.cargar(v.activeStart, v.activeEnd);
        },
        async cargar(inicio, finExclusivo) {
            const fin = new Date(finExclusivo);
            fin.setDate(fin.getDate() - 1);
            const pedido = `${iso(inicio)}|${iso(fin)}`;
            this._pedido = pedido;
            this.cargando = true;
            this.error = "";
            try {
                const { data } = await axios.get("/talento/api/ordenes/carga", {
                    params: { desde: iso(inicio), hasta: iso(fin) },
                });
                if (this._pedido !== pedido) return;
                this.ordenes = data?.ordenes ?? [];
                this.tipos = data?.tipos ?? [];
                this.colorAtrasada = data?.color_atrasada ?? "#D50000";
                for (const p of data?.personas ?? []) this.personasVistas[p.colaborador_id] = p;
                this.pintar();
            } catch (e) {
                window.console.error("Agenda de órdenes:", e);
                if (this._pedido === pedido) this.error = "No se pudo cargar la agenda.";
            } finally {
                if (this._pedido === pedido) this.cargando = false;
            }
        },
        filtradas(conAtrasadas = true) {
            const fija = this.colaboradorId ? Number(this.colaboradorId) : this.persona;
            return this.ordenes.filter(
                (o) =>
                    (!fija || o.colaborador_id === fija) &&
                    !this.ocultos.includes(o.tipo_id) &&
                    (!conAtrasadas || !this.soloAtrasadas || o.atrasada)
            );
        },
        pintar() {
            if (!this.cal) return;
            const eventos = this.filtradas().map((o) => {
                // Lo atrasado va arriba de hoy, sin hora: no es una cita de hoy, es algo pendiente.
                const conHora = o.hora && !o.atrasada;
                const inicio = conHora ? `${o.dia}T${o.hora}:00` : o.dia;
                let fin;
                if (conHora) {
                    const [h, m] = o.hora.split(":").map(Number);
                    fin = `${o.dia}T${pad(Math.min(h + 1, 23))}:${pad(m)}:00`;
                }
                return {
                    id: `${o.origen}-${o.id}`,
                    start: inicio,
                    end: fin,
                    allDay: !conHora,
                    startEditable: this.puedeGestionar && o.origen === "task" && ABIERTAS.includes(o.estado),
                    backgroundColor: "transparent",
                    borderColor: "transparent",
                    // Orden dentro del día: atrasadas primero, luego por hora.
                    clave: (o.atrasada ? "0" : "1") + (o.hora || "99:99"),
                    extendedProps: { orden: o },
                };
            });
            this.cal.removeAllEvents();
            this.cal.addEventSource(eventos);
        },
        // Evento estilo Google: rayita del color del tipo, fondo tenue, hora · tipo · cliente/persona.
        // Se arma con nodos (no HTML) porque los nombres vienen de la base.
        contenido(arg) {
            const o = arg.event.extendedProps.orden;
            const caja = document.createElement("div");
            caja.className = "ag-ev" + (o.atrasada ? " ag-ev-atrasada" : "") + (HECHAS.includes(o.estado) ? " ag-ev-hecha" : "");
            caja.style.setProperty("--ag-c", o.color);
            caja.style.setProperty("--ag-c-suave", alfa(o.color, this.darkMode ? 0.28 : 0.14));
            const linea1 = document.createElement("div");
            linea1.className = "ag-ev-l1";
            const partes = [];
            if (o.atrasada) partes.push(`Atrasada (${this.fechaCorta(o.fecha)})`);
            else if (o.hora) partes.push(this.hora12(o.hora));
            partes.push(o.tipo.replace(/\s*\(.*\)\s*/, ""));
            if (HECHAS.includes(o.estado)) partes.push("✓");
            linea1.textContent = partes.join(" · ");
            const linea2 = document.createElement("div");
            linea2.className = "ag-ev-l2";
            linea2.textContent = [this.colaboradorId ? null : o.persona, o.cliente].filter(Boolean).join(" · ");
            caja.title = [linea1.textContent, linea2.textContent].filter(Boolean).join("\n");
            caja.append(linea1, linea2);
            return { domNodes: [caja] };
        },
        alternarTipo(id) {
            this.ocultos = this.ocultos.includes(id) ? this.ocultos.filter((x) => x !== id) : [...this.ocultos, id];
        },
        async cambiarColor(t, color) {
            try {
                await axios.put(`/talento/api/order-types/${t.id}`, { color });
                this.recargar();
            } catch (e) {
                this.error = "No se pudo guardar el color.";
            }
        },
        fechaCorta(f) {
            const [y, m, d] = f.split("-").map(Number);
            return new Date(y, m - 1, d).toLocaleDateString("es-MX", { day: "numeric", month: "short" });
        },
        hora12(h) {
            const [hh, mm] = h.split(":").map(Number);
            return `${hh % 12 === 0 ? 12 : hh % 12}:${pad(mm)}${hh < 12 ? " a.m." : " p.m."}`;
        },
    },
};
</script>

<style scoped>
.ag {
    --ag-borde: #e0e3e7;
    --ag-texto: #3c4043;
    --ag-suave: #70757a;
    --ag-fondo: #fff;
    --ag-hover: #f1f3f4;
    --ag-acento: #1a73e8;
    --ag-acento-suave: #e8f0fe;
    color: var(--ag-texto);
}
.ag-dark,
:global(body[data-layout-mode="dark"] .ag) {
    --ag-borde: #3c4043;
    --ag-texto: #e8eaed;
    --ag-suave: #9aa0a6;
    --ag-fondo: #202124;
    --ag-hover: #2d2e31;
    --ag-acento: #8ab4f8;
    --ag-acento-suave: rgba(138, 180, 248, 0.16);
}
.ag-barra {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}
.ag-nav,
.ag-derecha {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.ag-titulo {
    margin: 0 0 0 6px;
    font-size: 20px;
    font-weight: 400;
}
.ag-btn {
    border: 0;
    background: transparent;
    color: var(--ag-texto);
}
.ag-btn:hover {
    background: var(--ag-hover);
}
.ag-btn-borde {
    border: 1px solid var(--ag-borde);
    border-radius: 18px;
    padding: 5px 16px;
    font-weight: 500;
}
.ag-redondo {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: var(--ag-suave);
}
.ag-select {
    width: auto;
    min-width: 170px;
    border-radius: 18px;
}
.ag-solo-atrasadas {
    display: inline-flex;
    align-items: center;
    font-size: 13px;
    margin: 0 4px;
    cursor: pointer;
}
.ag-num-atrasadas {
    margin-left: 6px;
    background: #d50000;
    color: #fff;
    border-radius: 10px;
    padding: 0 7px;
    font-size: 11px;
    font-weight: 700;
}
.ag-vistas {
    display: inline-flex;
    border: 1px solid var(--ag-borde);
    border-radius: 18px;
    overflow: hidden;
}
.ag-vistas button {
    border: 0;
    background: transparent;
    color: var(--ag-texto);
    padding: 5px 14px;
    font-size: 13px;
}
.ag-vistas button + button {
    border-left: 1px solid var(--ag-borde);
}
.ag-vistas button.activo {
    background: var(--ag-acento-suave);
    color: var(--ag-acento);
    font-weight: 600;
}

/* FullCalendar con aspecto de Google Calendar */
.ag-cal {
    --fc-border-color: var(--ag-borde);
    --fc-page-bg-color: var(--ag-fondo);
    --fc-neutral-bg-color: var(--ag-hover);
    --fc-today-bg-color: transparent;
    --fc-list-event-hover-bg-color: var(--ag-hover);
    --fc-now-indicator-color: #ea4335;
    background: var(--ag-fondo);
    font-size: 12px;
}
/* El tema oscuro global repinta los bordes de tablas: la cuadrícula usa siempre el color del calendario. */
.ag-cal :deep(td),
.ag-cal :deep(th),
.ag-cal :deep(.fc-scrollgrid),
.ag-cal :deep(.fc-list) {
    border-color: var(--ag-borde) !important;
}
.ag-cal :deep(.fc-col-header-cell-cushion) {
    color: var(--ag-suave) !important;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    text-decoration: none;
    padding: 6px 0;
}
.ag-cal :deep(.fc-daygrid-day-top) {
    justify-content: center;
}
.ag-cal :deep(.fc-daygrid-day-number) {
    /* el tema oscuro del sistema pinta los enlaces de azul con !important */
    color: var(--ag-texto) !important;
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
.ag-cal :deep(.fc-day-today .fc-daygrid-day-number) {
    background: var(--ag-acento);
    color: var(--ag-fondo) !important;
}
.ag-cal :deep(.fc-day-other .fc-daygrid-day-number) {
    color: var(--ag-suave) !important;
}
.ag-cal :deep(.fc-day-past:not(.fc-day-today)) {
    background: transparent;
}
.ag-cal :deep(.fc-daygrid-more-link) {
    color: var(--ag-texto);
    font-weight: 600;
    font-size: 11px;
    padding-left: 4px;
}
.ag-cal :deep(.fc-event) {
    border: 0;
    background: transparent;
    box-shadow: none;
    margin: 1px 2px;
}
.ag-cal :deep(.fc-timegrid-event .fc-event-main),
.ag-cal :deep(.fc-daygrid-event .fc-event-main) {
    padding: 0;
}
.ag-cal :deep(.fc-timegrid-slot) {
    height: 2.2em;
}
.ag-cal :deep(.fc-timegrid-slot-label-cushion),
.ag-cal :deep(.fc-timegrid-axis-cushion) {
    color: var(--ag-suave);
    font-size: 10px;
}
.ag-cal :deep(.fc-list-day-cushion) {
    background: var(--ag-hover);
}
.ag-cal :deep(.fc-list-day-text),
.ag-cal :deep(.fc-list-day-side-text) {
    color: var(--ag-texto);
    text-decoration: none;
}
.ag-cal :deep(.fc-list-event-dot),
.ag-cal :deep(.fc-list-event-time) {
    display: none;
}
.ag-cal :deep(.fc-popover) {
    background: var(--ag-fondo);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
}
.ag-cal :deep(.ag-ev) {
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-left: 4px solid var(--ag-c);
    background: var(--ag-c-suave);
    color: var(--ag-texto);
    border-radius: 4px;
    padding: 1px 5px;
    line-height: 1.25;
    cursor: pointer;
}
.ag-cal :deep(.ag-ev-l1) {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ag-cal :deep(.ag-ev-l2) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.85;
}
.ag-cal :deep(.ag-ev-atrasada) {
    background: var(--ag-c);
    color: #fff;
}
.ag-cal :deep(.ag-ev-hecha) {
    opacity: 0.55;
}

.ag-leyenda {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    margin-top: 10px;
    font-size: 12px;
    color: var(--ag-suave);
}
.ag-leyenda-tit {
    font-weight: 600;
}
.ag-tipo {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}
.ag-tipo.apagado {
    opacity: 0.4;
    text-decoration: line-through;
}
.ag-tipo-btn {
    border: 0;
    background: transparent;
    color: inherit;
    padding: 0;
    display: inline-flex;
    align-items: center;
}
.ag-tipo-btn i {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 3px;
    margin-right: 5px;
}
.ag-color {
    width: 14px;
    height: 14px;
    padding: 0;
    margin-right: 5px;
    border: 0;
    border-radius: 3px;
    background: transparent;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
}
.ag-color::-webkit-color-swatch-wrapper {
    padding: 0;
}
.ag-color::-webkit-color-swatch {
    border: 0;
    border-radius: 3px;
}
.ag-color::-moz-color-swatch {
    border: 0;
    border-radius: 3px;
}
.ag-ayuda {
    flex-basis: 100%;
    font-style: italic;
}
@media (max-width: 576px) {
    .ag-titulo {
        font-size: 16px;
    }
    .ag-vistas button {
        padding: 4px 9px;
    }
}
</style>
