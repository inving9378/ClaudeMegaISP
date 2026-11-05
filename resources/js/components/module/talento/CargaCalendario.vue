<template>
    <div class="cc" :class="{ 'cc-dark': darkMode }">
        <div class="cc-head">
            <div class="cc-nav">
                <button type="button" class="cc-flecha" aria-label="Mes anterior" @click="moverMes(-1)">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <strong class="cc-mes">{{ tituloMes }}</strong>
                <button type="button" class="cc-flecha" aria-label="Mes siguiente" @click="moverMes(1)">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <button type="button" class="cc-hoy-btn" @click="irAHoy">Hoy</button>
                <span v-if="cargando" class="spinner-border spinner-border-sm text-muted ms-1"></span>
            </div>
            <div v-if="colaboradorId" class="cc-filtro" role="group" aria-label="Ver carga de">
                <button type="button" :class="{ activo: filtro === 'persona' }" @click="filtro = 'persona'">
                    Solo {{ nombrePersona }}
                </button>
                <button type="button" :class="{ activo: filtro === 'todos' }" @click="filtro = 'todos'">
                    Todo el equipo
                </button>
            </div>
        </div>

        <div v-if="error" class="cc-error">{{ error }}</div>

        <div class="cc-grid">
            <div v-for="d in diasSemana" :key="d" class="cc-dsem">{{ d }}</div>
            <button
                v-for="c in celdas"
                :key="c.fecha"
                type="button"
                class="cc-dia"
                :class="{
                    fuera: !c.delMes,
                    pasado: c.fecha < hoy,
                    hoy: c.fecha === hoy,
                    elegido: c.fecha === modelValue,
                    viendo: c.fecha === diaVisto && c.fecha !== modelValue,
                }"
                :title="tituloCelda(c)"
                @click="tocarDia(c.fecha)"
            >
                <span class="cc-num">{{ c.dia }}</span>
                <span v-if="c.ordenes.length" class="cc-total" :class="{ alerta: c.atrasadas }">{{ c.ordenes.length }}</span>
                <span class="cc-barras">
                    <i
                        v-for="o in c.ordenes.slice(0, MAX_BARRAS)"
                        :key="o.origen + o.id"
                        :class="{ hecha: esHecha(o) }"
                        :style="{ background: o.color }"
                    ></i>
                </span>
            </button>
        </div>

        <div v-if="diaVisto" class="cc-detalle">
            <div class="cc-detalle-tit">
                {{ fechaLarga(diaVisto) }} ·
                <template v-if="ordenesDe(diaVisto).length">
                    {{ ordenesDe(diaVisto).length }} {{ ordenesDe(diaVisto).length === 1 ? "orden" : "órdenes" }}
                </template>
                <template v-else>sin órdenes{{ filtro === "persona" && colaboradorId ? " para " + nombrePersona : "" }}</template>
                <span v-if="diaVisto < hoy" class="cc-pasado-nota">(día pasado: no se puede asignar)</span>
            </div>
            <div v-for="g in gruposDe(diaVisto)" :key="g.clave" class="cc-persona">
                <div class="cc-persona-nom">
                    <span>{{ g.nombre }}</span>
                    <span class="cc-persona-n">{{ resumenPersona(g.ordenes) }}</span>
                </div>
                <div v-for="o in g.ordenes" :key="o.origen + o.id" class="cc-orden" :class="{ hecha: esHecha(o) }">
                    <span class="cc-orden-barra" :style="{ background: o.color }"></span>
                    <span class="cc-orden-hora">{{ o.hora ? hora12(o.hora) : "Sin hora" }}</span>
                    <span class="cc-orden-txt">
                        <strong>{{ o.tipo }}</strong><span v-if="o.cliente" class="cc-muted"> · {{ o.cliente }}</span>
                        <span v-if="o.atrasada" class="cc-atrasada">
                            Atrasada desde el {{ fechaCorta(o.fecha) }}
                        </span>
                        <span v-else-if="esHecha(o)" class="cc-muted"> · hecha</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="cc-leyenda">
            <span v-for="t in tiposActivos" :key="t.id"><i :style="{ background: t.color }"></i>{{ t.nombre }}</span>
            <span><i :style="{ background: colorAtrasada }"></i>Atrasada</span>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { darkMode } from "../../../hook/appConfig.js";

const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
const DIAS_LARGOS = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
const HECHAS = ["completed", "validated"];

const pad = (n) => String(n).padStart(2, "0");
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const deIso = (s) => {
    const [y, m, d] = s.split("-").map(Number);
    return new Date(y, m - 1, d);
};

/**
 * Calendario chico de carga de trabajo (item #1158, pedido de Irving 2026-11-03): al asignar una
 * orden se ve cuántas tiene cada día cada persona, una rayita por orden con el color de su tipo, y
 * las atrasadas en rojo en el día de hoy. Tocar un día de hoy en adelante lo elige (v-model).
 */
export default {
    name: "CargaCalendario",
    props: {
        // "YYYY-MM-DD" o null: el día elegido en el formulario.
        modelValue: { type: String, default: null },
        // Persona a la que se le va a asignar: con ella se puede ver solo su carga.
        colaboradorId: { type: [Number, String, Array], default: null },
    },
    emits: ["update:modelValue"],
    setup() {
        return { darkMode };
    },
    data() {
        const hoy = iso(new Date());
        return {
            // Rayitas que caben en el día; el total de la esquina dice cuántas son en realidad.
            MAX_BARRAS: 4,
            hoy,
            mes: deIso(hoy.slice(0, 8) + "01"),
            datos: { ordenes: [], tipos: [], personas: [], color_atrasada: "#D50000" },
            cargando: false,
            error: "",
            filtro: "todos",
            diaVisto: null,
            diasSemana: ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"],
        };
    },
    computed: {
        idPersona() {
            const v = Array.isArray(this.colaboradorId) ? this.colaboradorId[0] : this.colaboradorId;
            return v === null || v === undefined || v === "" ? null : Number(v);
        },
        nombrePersona() {
            const p = this.datos.personas.find((x) => x.colaborador_id === this.idPersona);
            return p ? p.nombre.split(" ")[0] : "esta persona";
        },
        tituloMes() {
            const t = `${MESES[this.mes.getMonth()]} ${this.mes.getFullYear()}`;
            return t.charAt(0).toUpperCase() + t.slice(1);
        },
        rango() {
            // De lunes de la primera semana a domingo de la última.
            const primero = new Date(this.mes.getFullYear(), this.mes.getMonth(), 1);
            const ultimo = new Date(this.mes.getFullYear(), this.mes.getMonth() + 1, 0);
            const desde = new Date(primero);
            desde.setDate(primero.getDate() - ((primero.getDay() + 6) % 7));
            const hasta = new Date(ultimo);
            hasta.setDate(ultimo.getDate() + ((7 - ultimo.getDay()) % 7));
            return { desde, hasta };
        },
        ordenesFiltradas() {
            if (this.filtro !== "persona" || !this.idPersona) return this.datos.ordenes;
            return this.datos.ordenes.filter((o) => o.colaborador_id === this.idPersona);
        },
        porDia() {
            const m = {};
            for (const o of this.ordenesFiltradas) {
                if (!m[o.dia]) m[o.dia] = [];
                m[o.dia].push(o);
            }
            return m;
        },
        celdas() {
            const out = [];
            const d = new Date(this.rango.desde);
            while (d <= this.rango.hasta) {
                const f = iso(d);
                const ordenes = this.porDia[f] || [];
                out.push({
                    fecha: f,
                    dia: d.getDate(),
                    delMes: d.getMonth() === this.mes.getMonth(),
                    ordenes,
                    atrasadas: ordenes.filter((o) => o.atrasada).length,
                });
                d.setDate(d.getDate() + 1);
            }
            return out;
        },
        tiposActivos() {
            return this.datos.tipos.filter((t) => t.activo);
        },
        colorAtrasada() {
            return this.datos.color_atrasada || "#D50000";
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(v) {
                if (!v) return;
                this.diaVisto = v;
                const d = deIso(v);
                if (d.getMonth() !== this.mes.getMonth() || d.getFullYear() !== this.mes.getFullYear()) {
                    this.mes = new Date(d.getFullYear(), d.getMonth(), 1);
                }
            },
        },
        idPersona: {
            immediate: true,
            handler(v) {
                this.filtro = v ? "persona" : "todos";
            },
        },
        mes() {
            this.cargar();
        },
    },
    mounted() {
        if (!this.diaVisto) this.diaVisto = this.hoy;
        this.cargar();
    },
    methods: {
        async cargar() {
            const { desde, hasta } = this.rango;
            const pedido = `${iso(desde)}|${iso(hasta)}`;
            this._pedido = pedido;
            this.cargando = true;
            this.error = "";
            try {
                const { data } = await axios.get("/talento/api/ordenes/carga", {
                    params: { desde: iso(desde), hasta: iso(hasta) },
                });
                if (this._pedido !== pedido) return; // ya se pidió otro mes
                this.datos = {
                    ordenes: data?.ordenes ?? [],
                    tipos: data?.tipos ?? [],
                    personas: data?.personas ?? [],
                    color_atrasada: data?.color_atrasada ?? "#D50000",
                };
                if (data?.hoy) this.hoy = data.hoy;
            } catch (e) {
                if (this._pedido === pedido) this.error = "No se pudo cargar el trabajo de esos días.";
            } finally {
                if (this._pedido === pedido) this.cargando = false;
            }
        },
        moverMes(n) {
            this.mes = new Date(this.mes.getFullYear(), this.mes.getMonth() + n, 1);
        },
        irAHoy() {
            this.mes = new Date(deIso(this.hoy).getFullYear(), deIso(this.hoy).getMonth(), 1);
            this.diaVisto = this.hoy;
        },
        tocarDia(f) {
            this.diaVisto = f;
            // Lo que ya pasó se consulta, pero no se asigna: ninguna orden nace atrasada.
            if (f >= this.hoy) this.$emit("update:modelValue", f);
        },
        ordenesDe(f) {
            return this.porDia[f] || [];
        },
        gruposDe(f) {
            const g = {};
            for (const o of this.ordenesDe(f)) {
                const k = o.colaborador_id ?? "sin";
                if (!g[k]) g[k] = { clave: k, nombre: o.persona, ordenes: [] };
                g[k].ordenes.push(o);
            }
            return Object.values(g).sort((a, b) =>
                (b.clave === this.idPersona) - (a.clave === this.idPersona) || a.nombre.localeCompare(b.nombre)
            );
        },
        resumenPersona(ordenes) {
            // «2 Instalación nueva · 1 Soporte/reparación · 1 atrasada»
            const c = {};
            for (const o of ordenes) {
                const k = o.atrasada ? "atrasada" : o.tipo.replace(/\s*\(.*\)\s*/, "").trim();
                c[k] = (c[k] || 0) + 1;
            }
            return Object.entries(c)
                .map(([k, n]) => (k === "atrasada" ? `${n} atrasada${n > 1 ? "s" : ""}` : `${n} ${k}`))
                .join(" · ");
        },
        esHecha(o) {
            return HECHAS.includes(o.estado);
        },
        tituloCelda(c) {
            if (!c.ordenes.length) return `${this.fechaLarga(c.fecha)}: sin órdenes`;
            return `${this.fechaLarga(c.fecha)}: ${c.ordenes.length} órdenes${c.atrasadas ? ` (${c.atrasadas} atrasadas)` : ""}`;
        },
        fechaLarga(f) {
            const d = deIso(f);
            const t = `${DIAS_LARGOS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]}`;
            return t.charAt(0).toUpperCase() + t.slice(1);
        },
        fechaCorta(f) {
            if (!f) return "";
            const d = deIso(f);
            return `${d.getDate()} de ${MESES[d.getMonth()]}`;
        },
        hora12(h) {
            const [hh, mm] = h.split(":").map(Number);
            return `${hh % 12 === 0 ? 12 : hh % 12}:${pad(mm)} ${hh < 12 ? "a.m." : "p.m."}`;
        },
        // Para que el padre recargue tras crear o mover una orden.
        recargar() {
            this.cargar();
        },
    },
};
</script>

<style scoped>
.cc {
    --cc-borde: #e0e3e7;
    --cc-texto: #3c4043;
    --cc-suave: #70757a;
    --cc-fondo: #fff;
    --cc-fondo-alt: #f8f9fa;
    --cc-acento: #1a73e8;
    --cc-acento-suave: #e8f0fe;
    border: 1px solid var(--cc-borde);
    border-radius: 10px;
    background: var(--cc-fondo);
    color: var(--cc-texto);
    padding: 10px;
    font-size: 13px;
}
.cc-dark,
:global(body[data-layout-mode="dark"] .cc) {
    --cc-borde: #3c4043;
    --cc-texto: #e8eaed;
    --cc-suave: #9aa0a6;
    --cc-fondo: #202124;
    --cc-fondo-alt: #2a2b2e;
    --cc-acento: #8ab4f8;
    --cc-acento-suave: rgba(138, 180, 248, 0.16);
}
.cc-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 6px;
}
.cc-nav {
    display: flex;
    align-items: center;
    gap: 4px;
}
.cc-mes {
    min-width: 128px;
    text-align: center;
    font-size: 14px;
}
.cc-flecha,
.cc-hoy-btn {
    border: 0;
    background: transparent;
    color: var(--cc-suave);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cc-flecha:hover,
.cc-hoy-btn:hover {
    background: var(--cc-fondo-alt);
}
.cc-hoy-btn {
    width: auto;
    border-radius: 14px;
    padding: 0 10px;
    border: 1px solid var(--cc-borde);
    color: var(--cc-texto);
    margin-left: 4px;
}
.cc-filtro {
    display: inline-flex;
    border: 1px solid var(--cc-borde);
    border-radius: 14px;
    overflow: hidden;
}
.cc-filtro button {
    border: 0;
    background: transparent;
    color: var(--cc-suave);
    padding: 3px 10px;
    font-size: 12px;
}
.cc-filtro button.activo {
    background: var(--cc-acento-suave);
    color: var(--cc-acento);
    font-weight: 600;
}
.cc-error {
    color: #d93025;
    font-size: 12px;
    margin-bottom: 4px;
}
.cc-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 3px;
}
.cc-dsem {
    text-align: center;
    font-size: 11px;
    color: var(--cc-suave);
    text-transform: uppercase;
    padding-bottom: 2px;
}
.cc-dia {
    position: relative;
    height: 52px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: var(--cc-fondo-alt);
    color: var(--cc-texto);
    padding: 3px 4px;
    text-align: left;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    cursor: pointer;
}
.cc-dia:hover {
    border-color: var(--cc-borde);
}
.cc-dia.fuera {
    opacity: 0.45;
}
.cc-dia.pasado {
    background: transparent;
    cursor: default;
}
.cc-dia.pasado .cc-num {
    color: var(--cc-suave);
}
.cc-dia.hoy .cc-num {
    background: var(--cc-acento);
    color: #fff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cc-dark .cc-dia.hoy .cc-num,
:global(body[data-layout-mode="dark"] .cc .cc-dia.hoy .cc-num) {
    color: #202124;
}
.cc-dia.elegido {
    border-color: var(--cc-acento);
    box-shadow: 0 0 0 1px var(--cc-acento) inset;
    background: var(--cc-acento-suave);
}
.cc-dia.viendo {
    border-color: var(--cc-suave);
}
.cc-num {
    font-size: 12px;
    font-weight: 600;
    line-height: 20px;
}
.cc-total {
    position: absolute;
    top: 4px;
    right: 5px;
    font-size: 11px;
    font-weight: 700;
    color: var(--cc-suave);
}
.cc-total.alerta {
    color: #d50000;
}
.cc-barras {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.cc-barras i {
    display: block;
    height: 3px;
    border-radius: 2px;
}
.cc-barras i.hecha {
    opacity: 0.35;
}
.cc-detalle {
    margin-top: 8px;
    border-top: 1px solid var(--cc-borde);
    padding-top: 8px;
}
.cc-detalle-tit {
    font-weight: 600;
    margin-bottom: 6px;
}
.cc-pasado-nota {
    font-weight: 400;
    color: var(--cc-suave);
    margin-left: 4px;
}
.cc-persona + .cc-persona {
    margin-top: 6px;
}
.cc-persona-nom {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-weight: 600;
}
.cc-persona-n {
    font-weight: 400;
    color: var(--cc-suave);
    text-align: right;
}
.cc-orden {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 2px 0 2px 2px;
}
.cc-orden.hecha {
    opacity: 0.55;
}
.cc-orden-barra {
    flex: 0 0 4px;
    align-self: stretch;
    border-radius: 2px;
}
.cc-orden-hora {
    flex: 0 0 64px;
    color: var(--cc-suave);
    font-size: 12px;
}
.cc-orden-txt {
    min-width: 0;
}
.cc-muted {
    color: var(--cc-suave);
}
.cc-atrasada {
    display: inline-block;
    margin-left: 6px;
    padding: 0 6px;
    border-radius: 8px;
    background: rgba(213, 0, 0, 0.12);
    color: #d50000;
    font-size: 11px;
    font-weight: 600;
}
.cc-dark .cc-atrasada,
:global(body[data-layout-mode="dark"] .cc .cc-atrasada) {
    color: #ff8a80;
}
.cc-leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 8px;
    font-size: 11px;
    color: var(--cc-suave);
}
.cc-leyenda i {
    display: inline-block;
    width: 14px;
    height: 4px;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
}
@media (max-width: 480px) {
    .cc-dia {
        height: 46px;
    }
    .cc-orden-hora {
        flex-basis: 54px;
    }
}
</style>
