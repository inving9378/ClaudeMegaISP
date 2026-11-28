<template>
    <!-- La lista de «Revisar a quién se le llama» (28-nov, pedido de Irving): lo del listado de clientes
         (filtro de estado, búsqueda, orden por columna, columnas, exportar, paginación) con el vocabulario
         de Campañas. La lista llega completa del servidor: aquí se filtra, ordena y pagina. -->
    <section class="crt" :class="{ 'crt--cargando': cargando && clientes.length }" aria-label="Lista de clientes" :aria-busy="cargando">
        <header class="crt-barra">
            <label class="crt-buscar">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input :value="buscar" data-autofoco type="search" class="cp-campo" autocomplete="off" spellcheck="false"
                       placeholder="Buscar por nombre, número, teléfono o caja" aria-label="Buscar en la lista"
                       @input="$emit('update:buscar', $event.target.value)">
                <button v-if="buscar" type="button" class="crt-limpiar" aria-label="Borrar la búsqueda" @click="$emit('update:buscar', '')">
                    <i class="fa fa-times-circle" aria-hidden="true"></i>
                </button>
            </label>

            <div class="crt-segmentos" role="group" aria-label="Qué clientes ver">
                <button v-for="sg in segmentos" :key="sg.id" type="button" class="crt-segmento"
                        :aria-pressed="vista === sg.id ? 'true' : 'false'" @click="$emit('update:vista', sg.id)">
                    {{ sg.label }}<span class="crt-segmento-n">{{ n(sg.n) }}</span>
                </button>
            </div>

            <div class="crt-acciones">
                <div class="crt-columnas" @focusout="alSalirColumnas" @keydown.esc.stop.prevent="cerrarColumnas(true)">
                    <button ref="botonColumnas" type="button" class="crt-boton" :aria-expanded="columnasAbiertas ? 'true' : 'false'"
                            aria-controls="crt-columnas-panel" @click="columnasAbiertas ? cerrarColumnas(true) : abrirColumnas()">
                        <i class="fa fa-columns" aria-hidden="true"></i> Columnas
                        <span v-if="ocultas.length" class="crt-boton-n">{{ columnasVisibles.length }}/{{ columnas.length }}</span>
                    </button>
                    <transition name="crt-pop">
                        <div v-if="columnasAbiertas" id="crt-columnas-panel" ref="panelColumnas" class="crt-pop" role="group" aria-label="Columnas que se ven">
                            <label v-for="col in columnas" :key="col.id" class="crt-pop-op" :class="{ 'crt-pop-op--fija': col.fija }">
                                <input type="checkbox" :checked="col.fija || !ocultas.includes(col.id)" :disabled="col.fija" @change="alternarColumna(col.id)">
                                <span>{{ col.label }}</span>
                                <span v-if="col.fija" class="crt-pop-nota">siempre</span>
                            </label>
                            <button v-if="ocultas.length" type="button" class="crt-pop-todas" @click="verTodasLasColumnas">Ver todas</button>
                        </div>
                    </transition>
                </div>
                <button type="button" class="crt-boton" :disabled="!filtrados.length" @click="exportar">
                    <i class="fa fa-file-excel" aria-hidden="true"></i> Exportar
                </button>
            </div>
        </header>

        <div class="crt-filtros">
            <div class="crt-chips" role="group" aria-label="Filtrar por estado del cliente">
                <span class="crt-chips-etiqueta" aria-hidden="true">Estado</span>
                <button v-for="e in opcionesEstado" :key="e.id" type="button" class="crt-chip" :class="`crt-chip--${e.id.toLowerCase()}`"
                        :aria-pressed="estados.includes(e.id) ? 'true' : 'false'" @click="alternarEstado(e.id)">
                    <span class="crt-punto" aria-hidden="true"></span>{{ e.label }}<span class="crt-chip-n">{{ n(e.n) }}</span>
                </button>
                <button v-if="estados.length" type="button" class="crt-enlace" @click="estados = []">Todos los estados</button>
            </div>
            <label class="crt-orden-movil">
                <span>Ordenar</span>
                <select class="cp-campo" :value="orden.campo ? `${orden.campo}:${orden.dir}` : ''" @change="ordenDesde($event.target.value)">
                    <option value="">Como llegó</option>
                    <template v-for="col in columnas" :key="col.id">
                        <option :value="`${col.id}:asc`">{{ col.label }} {{ col.id === 'monto' ? '(menor primero)' : '(A–Z)' }}</option>
                        <option :value="`${col.id}:desc`">{{ col.label }} {{ col.id === 'monto' ? '(mayor primero)' : '(Z–A)' }}</option>
                    </template>
                </select>
            </label>
            <p class="crt-total" aria-live="polite">
                <template v-if="clientes.length && filtrados.length !== clientes.length">
                    Mostrando <strong class="crt-num">{{ n(filtrados.length) }}</strong> de {{ n(clientes.length) }}
                </template>
                <template v-else-if="clientes.length">{{ n(clientes.length) }} {{ clientes.length === 1 ? 'cliente' : 'clientes' }}</template>
            </p>
        </div>

        <div class="crt-caja">
            <table class="crt-tabla">
                <caption class="visually-hidden">Clientes de la lista; la casilla dice si se le llama.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="crt-marca">
                            <label class="crt-todos">
                                <input type="checkbox" :checked="todos === true" :indeterminate.prop="todos === null" :disabled="!filtrados.length"
                                       aria-label="Llamar o quitar a todos los que se ven" @change="marcarVisibles($event.target.checked)">
                                <span class="crt-todos-texto">Todos los que se ven</span>
                            </label>
                        </th>
                        <th v-for="col in columnasVisibles" :key="col.id" scope="col" :class="[`crt-c-${col.id}`, { 'crt-num': col.num }]"
                            :aria-sort="orden.campo === col.id ? (orden.dir === 'asc' ? 'ascending' : 'descending') : 'none'">
                            <button type="button" class="crt-orden" @click="ordenar(col.id)">
                                {{ col.label }}<i class="fa" :class="iconoOrden(col.id)" aria-hidden="true"></i>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="cargando && !clientes.length">
                        <tr v-for="i in 6" :key="`e${i}`" class="crt-fila-esqueleto" aria-hidden="true">
                            <td class="crt-marca"><span class="crt-esqueleto" style="width: 16px"></span></td>
                            <td v-for="(col, j) in columnasVisibles" :key="col.id" :class="`crt-c-${col.id}`">
                                <span class="crt-esqueleto" :style="{ width: `${[55, 80, 65, 50, 70, 75, 45][j % 7]}%` }"></span>
                            </td>
                        </tr>
                    </template>
                    <tr v-else-if="!filtrados.length" class="crt-fila-vacia">
                        <td :colspan="columnasVisibles.length + 1" class="crt-vacio">
                            <template v-if="buscar.trim() && clientes.length">
                                <strong>Nadie coincide con «{{ buscar.trim() }}»</strong>
                                <span>Busca por nombre, número de cliente, teléfono o caja (D1Z54C7).</span>
                                <button type="button" class="crt-boton" @click="$emit('update:buscar', '')">Quitar la búsqueda</button>
                            </template>
                            <template v-else-if="estados.length && clientes.length">
                                <strong>Nadie con ese estado en la lista</strong>
                                <button type="button" class="crt-boton" @click="estados = []">Ver todos los estados</button>
                            </template>
                            <template v-else-if="vista === 'quitados' && clientes.length">
                                <strong>No has quitado a nadie</strong>
                                <span>Se le llama a toda la lista. Desmarca la casilla de quien no quieras llamar.</span>
                            </template>
                            <slot v-else name="vacio"><strong>No hay clientes en la lista</strong></slot>
                        </td>
                    </tr>
                    <tr v-for="c in pagina" v-else :key="c.id" :class="{ 'crt-fila--quitada': !!quitados[c.id] }">
                        <td class="crt-marca">
                            <input :id="`crt-${c.id}`" type="checkbox" :checked="!quitados[c.id]" @change="$emit('alternar', c.id)">
                        </td>
                        <td v-for="col in columnasVisibles" :key="col.id" :class="[`crt-c-${col.id}`, { 'crt-num': col.num }]">
                            <a v-if="col.id === 'id'" :href="`/cliente/editar/${c.id}`" target="_blank" rel="noopener" class="crt-id"
                               :aria-label="`Abrir la ficha del cliente ${c.id} en otra pestaña`">{{ c.id }}</a>
                            <template v-else-if="col.id === 'nombre'">
                                <label :for="`crt-${c.id}`" class="crt-nombre">{{ c.nombre }}</label>
                                <span class="crt-id-movil">#{{ c.id }}</span>
                                <span v-if="quitados[c.id]" class="crt-no-llama"><i class="fa fa-phone-slash" aria-hidden="true"></i>No se le llama</span>
                            </template>
                            <span v-else-if="col.id === 'estado'" class="crt-estado" :class="`crt-estado--${String(c.estado).toLowerCase()}`">
                                <span class="crt-punto" aria-hidden="true"></span>{{ c.estado }}
                            </span>
                            <span v-else-if="(col.id === 'caja' || col.id === 'zona') && !c.nomenclatura" class="crt-falta">{{ col.id === 'caja' ? 'Sin caja' : 'Sin zona' }}</span>
                            <template v-else>{{ texto(col.id, c) }}</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer v-if="filtrados.length" class="crt-pie">
            <span class="crt-num crt-rango">{{ n(desde) }}–{{ n(hasta) }} de {{ n(filtrados.length) }}</span>
            <div class="crt-paginacion">
                <label class="crt-por-pagina">
                    <span>Por página</span>
                    <select v-model.number="porPagina" class="cp-campo">
                        <option v-for="p in [25, 50, 100, 250]" :key="p" :value="p">{{ p }}</option>
                    </select>
                </label>
                <button type="button" class="crt-icono" :disabled="paginaActual <= 1" aria-label="Página anterior" @click="paginaActual--">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i>
                </button>
                <span class="crt-num crt-de">{{ paginaActual }} de {{ paginas }}</span>
                <button type="button" class="crt-icono" :disabled="paginaActual >= paginas" aria-label="Página siguiente" @click="paginaActual++">
                    <i class="fa fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </footer>
    </section>
</template>

<script>
import * as XLSX from 'xlsx';
import { ESTADOS_CLIENTE, nombreZona } from './audiencia.js';

const plano = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const natural = (a, b) => String(a ?? '').localeCompare(String(b ?? ''), 'es', { numeric: true, sensitivity: 'base' });
// «D1Z54C7:3» → «D1Z54»
const zonaDe = nom => { const m = /^(D\d+[ZW]\d+)/i.exec(String(nom || '')); return m ? m[1].toUpperCase() : ''; };
const COLUMNAS_KEY = 'campanas.revision.columnas';
const POR_PAGINA_KEY = 'campanas.revision.por_pagina';

const leer = (clave, defecto) => {
    try { const v = JSON.parse(localStorage.getItem(clave) || 'null'); return v ?? defecto; } catch (e) { return defecto; }
};
const guardar = (clave, valor) => {
    try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (e) { /* sin almacenamiento: vale para esta vez */ }
};

export default {
    name: 'CampanasRevisionTabla',
    props: {
        clientes: { type: Array, default: () => [] },
        // { id: true } de los que se quitaron (no se les llama).
        quitados: { type: Object, default: () => ({}) },
        deudores: { type: Boolean, default: false },
        cargando: { type: Boolean, default: false },
        buscar:   { type: String, default: '' },
        vista:    { type: String, default: 'todos' },
        nombre:   { type: String, default: 'campaña' },
    },
    emits: ['alternar', 'marcar', 'update:buscar', 'update:vista'],
    data() {
        const ocultas = leer(COLUMNAS_KEY, []);
        const porPagina = Number(leer(POR_PAGINA_KEY, 50));
        return {
            estados: [],
            ocultas: Array.isArray(ocultas) ? ocultas : [],
            orden: { campo: null, dir: 'asc' },
            porPagina: [25, 50, 100, 250].includes(porPagina) ? porPagina : 50,
            paginaActual: 1,
            columnasAbiertas: false,
        };
    },
    computed: {
        columnas() {
            const cols = [
                { id: 'id', label: 'Número', valor: c => c.id, cmp: (a, b) => a - b },
                { id: 'nombre', label: 'Cliente', valor: c => c.nombre, cmp: natural, fija: true },
                { id: 'telefono', label: 'Teléfono', valor: c => String(c.telefono || ''), cmp: natural },
                { id: 'estado', label: 'Estado', valor: c => c.estado, cmp: natural },
                { id: 'zona', label: 'Zona', valor: c => zonaDe(c.nomenclatura), cmp: natural },
                { id: 'caja', label: 'Caja', valor: c => c.nomenclatura || '', cmp: natural },
            ];
            if (this.deudores) cols.push({ id: 'monto', label: 'Saldo vencido', valor: c => Number(c.monto || 0), cmp: (a, b) => a - b, num: true });
            return cols;
        },
        columnasVisibles() {
            return this.columnas.filter(c => c.fija || !this.ocultas.includes(c.id));
        },
        // El texto para buscar de cada cliente, una sola vez por lista.
        indice() {
            return this.clientes.map(c => ({
                c, texto: plano(`${c.nombre} ${c.nomenclatura || ''}`), digitos: `${c.id} ${String(c.telefono || '').replace(/\D/g, '')}`,
            }));
        },
        filtrados() {
            const q = plano(this.buscar.trim());
            const digitos = this.buscar.replace(/\D/g, '');
            const estados = this.estados;
            const lista = this.indice.filter(({ c, texto, digitos: d }) => {
                if (this.vista === 'llamar' && this.quitados[c.id]) return false;
                if (this.vista === 'quitados' && !this.quitados[c.id]) return false;
                if (estados.length && !estados.includes(c.estado)) return false;
                if (!q) return true;
                return texto.includes(q) || (digitos.length > 0 && d.includes(digitos));
            }).map(x => x.c);
            const col = this.orden.campo && this.columnas.find(x => x.id === this.orden.campo);
            if (!col) return lista;
            const signo = this.orden.dir === 'desc' ? -1 : 1;
            return lista.sort((a, b) => signo * col.cmp(col.valor(a), col.valor(b)) || a.id - b.id);
        },
        paginas() {
            return Math.max(1, Math.ceil(this.filtrados.length / this.porPagina));
        },
        desde() {
            return this.filtrados.length ? (this.paginaActual - 1) * this.porPagina + 1 : 0;
        },
        hasta() {
            return Math.min(this.paginaActual * this.porPagina, this.filtrados.length);
        },
        pagina() {
            return this.filtrados.slice(Math.max(0, this.desde - 1), this.hasta);
        },
        // true: a todos los que se ven se les llama · false: a ninguno · null: a algunos.
        todos() {
            const f = this.filtrados;
            if (!f.length) return false;
            const marcados = f.reduce((t, c) => t + (this.quitados[c.id] ? 0 : 1), 0);
            return marcados === f.length ? true : (marcados === 0 ? false : null);
        },
        quitadosN() {
            return this.clientes.reduce((t, c) => t + (this.quitados[c.id] ? 1 : 0), 0);
        },
        segmentos() {
            const total = this.clientes.length;
            const lista = [
                { id: 'todos', label: 'Todos', n: total },
                { id: 'llamar', label: 'Se les llama', n: total - this.quitadosN },
            ];
            if (this.quitadosN || this.vista === 'quitados') lista.push({ id: 'quitados', label: 'Quitados', n: this.quitadosN });
            return lista;
        },
        opcionesEstado() {
            return Object.keys(ESTADOS_CLIENTE)
                .map(e => ({ id: e, label: ESTADOS_CLIENTE[e], n: this.clientes.filter(c => c.estado === e).length }))
                .filter(e => e.n > 0 || this.estados.includes(e.id));
        },
    },
    watch: {
        buscar() { this.paginaActual = 1; },
        vista() { this.paginaActual = 1; },
        estados() { this.paginaActual = 1; },
        clientes() { this.paginaActual = 1; },
        orden: { deep: true, handler() { this.paginaActual = 1; } },
        porPagina(v) { this.paginaActual = 1; guardar(POR_PAGINA_KEY, v); },
        paginas(total) { if (this.paginaActual > total) this.paginaActual = total; },
    },
    methods: {
        n(x) { return Number(x || 0).toLocaleString('es-MX'); },
        texto(col, c) {
            if (col === 'telefono') {
                const d = String(c.telefono || '').replace(/\D/g, '');
                return d.length === 10 ? `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}` : (c.telefono || '—');
            }
            if (col === 'zona') return c.nomenclatura ? nombreZona(zonaDe(c.nomenclatura)) : 'Sin zona';
            if (col === 'caja') return c.nomenclatura || 'Sin caja';
            if (col === 'monto') return Number(c.monto || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
            return c[col] ?? '';
        },
        // Primera vez de menor a mayor (el saldo, de mayor a menor); otra vez, al revés.
        ordenar(campo) {
            if (this.orden.campo === campo) this.orden.dir = this.orden.dir === 'asc' ? 'desc' : 'asc';
            else this.orden = { campo, dir: campo === 'monto' ? 'desc' : 'asc' };
        },
        ordenDesde(valor) {
            const [campo, dir] = String(valor).split(':');
            this.orden = campo ? { campo, dir: dir === 'desc' ? 'desc' : 'asc' } : { campo: null, dir: 'asc' };
        },
        iconoOrden(campo) {
            if (this.orden.campo !== campo) return 'fa-sort crt-orden-icono';
            return this.orden.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },
        alternarEstado(e) {
            this.estados = this.estados.includes(e) ? this.estados.filter(x => x !== e) : [...this.estados, e];
        },
        // Todos los que se ven con el filtro y la búsqueda (no solo la página).
        marcarVisibles(marcado) {
            this.$emit('marcar', { ids: this.filtrados.map(c => c.id), marcado });
        },
        alternarColumna(id) {
            this.ocultas = this.ocultas.includes(id) ? this.ocultas.filter(x => x !== id) : [...this.ocultas, id];
            guardar(COLUMNAS_KEY, this.ocultas);
        },
        verTodasLasColumnas() {
            this.ocultas = [];
            guardar(COLUMNAS_KEY, []);
        },
        abrirColumnas() {
            this.columnasAbiertas = true;
            this.$nextTick(() => {
                const primero = this.$refs.panelColumnas && this.$refs.panelColumnas.querySelector('input:not(:disabled)');
                if (primero) primero.focus();
            });
        },
        cerrarColumnas(devolverFoco) {
            if (!this.columnasAbiertas) return;
            this.columnasAbiertas = false;
            if (devolverFoco) this.$nextTick(() => this.$refs.botonColumnas && this.$refs.botonColumnas.focus());
        },
        alSalirColumnas(e) {
            if (this.columnasAbiertas && !e.currentTarget.contains(e.relatedTarget)) this.cerrarColumnas(false);
        },
        exportar() {
            const cols = this.columnasVisibles;
            const filas = this.filtrados.map(c => {
                const fila = {};
                for (const col of cols) {
                    fila[col.label] = col.id === 'monto' ? Number(c.monto || 0) : (col.id === 'id' ? c.id : this.texto(col.id, c));
                }
                fila['Se le llama'] = this.quitados[c.id] ? 'No' : 'Sí';
                return fila;
            });
            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filas), 'Clientes');
            XLSX.writeFile(libro, `Lista - ${this.nombre}`.replace(/[\\/:*?"<>|]/g, '').slice(0, 120) + '.xlsx');
        },
    },
};
</script>

<style scoped>
/* Mide su propio ancho: en el diálogo ancho es una tabla; angosta, una tarjeta por cliente. */
.crt {
    container: crt / inline-size;
    border: 1px solid var(--cp-borde); border-radius: 10px; background: var(--cp-superficie);
    /* clip y no hidden: con hidden, el encabezado de la tabla dejaría de quedarse arriba al bajar. */
    overflow: clip;
}
.crt-caja { transition: opacity .15s; }
.crt--cargando .crt-caja { opacity: .55; }

/* ── Barra: buscar, qué ver, columnas y exportar ─────────────────────────── */
.crt-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 16px 10px; }
.crt-buscar { position: relative; flex: 1 1 260px; max-width: 380px; margin: 0; }
.crt-buscar > .fa-search { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--cp-tenue); font-size: 12.5px; pointer-events: none; }
.crt-buscar .cp-campo { padding-left: 32px; padding-right: 32px; }
.crt-buscar input::-webkit-search-cancel-button { display: none; }
.crt-limpiar {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border: 0; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center; background: transparent; color: var(--cp-tenue); cursor: pointer;
}
.crt-limpiar:hover { color: var(--cp-tinta); background: var(--cp-hover); }

.crt-segmentos { display: inline-flex; padding: 3px; border-radius: 9px; background: var(--cp-cabecera); border: 1px solid var(--cp-borde); max-width: 100%; overflow-x: auto; scrollbar-width: none; }
.crt-segmentos::-webkit-scrollbar { display: none; }
.crt-segmento {
    display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; color: var(--cp-tenue);
    font-size: 13px; font-weight: 500; padding: 5px 11px; border-radius: 7px; white-space: nowrap; cursor: pointer;
    transition: background-color .15s, color .15s;
}
.crt-segmento:hover { color: var(--cp-tinta); }
.crt-segmento[aria-pressed="true"] { background: var(--cp-superficie); color: var(--cp-tinta); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.cd-dark .crt-segmento[aria-pressed="true"] { background: rgba(255, 255, 255, .12); box-shadow: none; }
.crt-segmento-n { font-size: 12px; font-variant-numeric: tabular-nums; color: var(--cp-tenue); }
.crt-segmento[aria-pressed="true"] .crt-segmento-n { color: var(--cp-acento); font-weight: 600; }

.crt-acciones { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.crt-boton {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 34px;
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta);
    border-radius: 8px; padding: 6px 13px; font-size: 13px; font-weight: 500; line-height: 1.2;
    cursor: pointer; white-space: nowrap; transition: background-color .15s, border-color .15s, color .15s;
}
.crt-boton i { font-size: 12px; }
.crt-boton:hover:not(:disabled), .crt-boton[aria-expanded="true"] { border-color: var(--cp-acento); color: var(--cp-acento); }
.crt-boton:disabled { opacity: .5; cursor: default; }
.crt-boton-n { padding: 0 6px; border-radius: 999px; background: var(--cp-acento-suave); color: var(--cp-acento); font-size: 11.5px; font-weight: 600; font-variant-numeric: tabular-nums; }

/* Columnas: un panel que baja del botón (el mismo gesto que el selector de zona). */
.crt-columnas { position: relative; }
.crt-pop {
    position: absolute; z-index: 40; top: calc(100% + 6px); right: 0; min-width: 220px; padding: 6px;
    background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 10px;
    box-shadow: 0 16px 32px -8px rgba(15, 23, 42, .22), 0 4px 10px -4px rgba(15, 23, 42, .12);
    transform-origin: top right;
}
.cd-dark .crt-pop { box-shadow: 0 16px 32px -8px rgba(0, 0, 0, .6), 0 4px 10px -4px rgba(0, 0, 0, .4); }
.crt-pop-op { display: flex; align-items: center; gap: 10px; min-height: 34px; margin: 0; padding: 6px 10px; border-radius: 7px; font-size: 13.5px; font-weight: 500; color: var(--cp-tinta); cursor: pointer; }
.crt-pop-op:hover { background: var(--cp-hover); }
.crt-pop-op input { width: 15px; height: 15px; margin: 0; accent-color: var(--cp-acento); cursor: pointer; }
.crt-pop-op--fija { cursor: default; color: var(--cp-tenue); }
.crt-pop-op--fija:hover { background: none; }
.crt-pop-nota { margin-left: auto; font-size: 11.5px; color: var(--cp-tenue); }
.crt-pop-todas { display: block; width: 100%; margin-top: 4px; padding: 8px 10px 4px; border: 0; border-top: 1px solid var(--cp-borde); background: none; color: var(--cp-acento); font-size: 13px; font-weight: 600; text-align: left; cursor: pointer; }
@media (prefers-reduced-motion: no-preference) {
    .crt-pop-enter-active { transition: opacity .16s cubic-bezier(.16, 1, .3, 1), transform .24s cubic-bezier(.16, 1, .3, 1), clip-path .24s cubic-bezier(.16, 1, .3, 1); }
    .crt-pop-leave-active { transition: opacity .12s ease-in, transform .12s ease-in; }
    .crt-pop-enter-from { opacity: 0; transform: translateY(-6px) scale(.97); clip-path: inset(0 0 70% 0 round 10px); }
    .crt-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(.985); }
}

/* ── Filtro de estado y cuántos se ven ───────────────────────────────────── */
.crt-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; padding: 0 16px 12px; border-bottom: 1px solid var(--cp-borde); }
.crt-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.crt-chips-etiqueta { margin-right: 2px; font-size: 12.5px; font-weight: 600; color: var(--cp-tenue); }
.crt-chip {
    display: inline-flex; align-items: center; gap: 7px; min-height: 30px; padding: 4px 11px; border-radius: 999px;
    border: 1px solid var(--cp-borde); background: var(--cp-superficie); color: var(--cp-tinta);
    font-size: 13px; font-weight: 500; cursor: pointer; transition: background-color .15s, border-color .15s;
}
.crt-chip:hover { border-color: var(--cp-borde-campo); }
.crt-chip[aria-pressed="true"] { border-color: var(--cp-acento); background: var(--cp-acento-suave); }
.crt-chip-n { font-size: 12px; font-variant-numeric: tabular-nums; color: var(--cp-tenue); }
.crt-chip[aria-pressed="true"] .crt-chip-n { color: var(--cp-acento); font-weight: 600; }
.crt-enlace { border: 0; background: none; padding: 0 4px; color: var(--cp-acento); font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
.crt-total { margin: 0 0 0 auto; font-size: 12.5px; color: var(--cp-tenue); }
.crt-total strong { color: var(--cp-tinta); }
.crt-orden-movil { display: none; align-items: center; gap: 8px; margin: 0; font-size: 12.5px; color: var(--cp-tenue); }
.crt-orden-movil .cp-campo { width: auto; min-height: 34px; padding: 4px 8px; }

/* Los colores de estado del cliente son los de todo el sistema: verde, rojo y ámbar. */
.crt-punto { width: 7px; height: 7px; flex: none; border-radius: 50%; background: currentColor; }
.crt-chip--activo .crt-punto, .crt-estado--activo { color: #15803d; }
.crt-chip--bloqueado .crt-punto, .crt-estado--bloqueado { color: #b91c1c; }
.crt-chip--inactivo .crt-punto, .crt-estado--inactivo { color: #b45309; }
.cd-dark .crt-chip--activo .crt-punto, .cd-dark .crt-estado--activo { color: #86efac; }
.cd-dark .crt-chip--bloqueado .crt-punto, .cd-dark .crt-estado--bloqueado { color: #fca5a5; }
.cd-dark .crt-chip--inactivo .crt-punto, .cd-dark .crt-estado--inactivo { color: #fcd34d; }

/* ── Tabla ───────────────────────────────────────────────────────────────── */
.crt-tabla { width: 100%; border-collapse: separate; border-spacing: 0; color: var(--cp-tinta); font-size: 13.5px; }
/* El encabezado se queda arriba al bajar por la lista (fondo opaco). Pegajoso el <thead> entero y no
   cada <th>: celda por celda, cada una se pinta aparte y sus líneas no coincidían por un píxel. */
.crt-tabla thead { position: sticky; top: calc(-1 * var(--cpd-pad-top, 0px)); z-index: 2; }
.crt-tabla thead th {
    background: #f8f9fb; color: var(--cp-tenue); font-size: 12.5px; font-weight: 600; text-align: left;
    padding: 9px 12px; white-space: nowrap; border-bottom: 1px solid var(--cp-borde);
}
.cd-dark .crt-tabla thead th { background: #30364a; }
.crt-tabla tbody td { padding: 11px 12px; border-bottom: 1px solid var(--cp-borde); vertical-align: middle; transition: background-color .12s, color .12s; }
.crt-tabla tbody tr:last-child td { border-bottom: 0; }
.crt-tabla tbody tr:not(.crt-fila-vacia):not(.crt-fila-esqueleto):hover td { background: var(--cp-hover); }
.crt-tabla th:first-child, .crt-tabla td:first-child { padding-left: 16px; }
.crt-tabla th:last-child, .crt-tabla td:last-child { padding-right: 16px; }
.crt-tabla .crt-num { text-align: right; }
.crt-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.crt-tabla th.crt-num .crt-orden { flex-direction: row-reverse; }

.crt-orden { display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; padding: 0; color: inherit; font: inherit; cursor: pointer; white-space: nowrap; }
.crt-orden i { font-size: 11px; }
.crt-orden:hover { color: var(--cp-tinta); }
th[aria-sort="ascending"] .crt-orden, th[aria-sort="descending"] .crt-orden { color: var(--cp-tinta); }
th[aria-sort="ascending"] .crt-orden i, th[aria-sort="descending"] .crt-orden i { color: var(--cp-acento); }
.crt-orden-icono { opacity: .45; }

/* La casilla sin ancho fijo: con «1%» o en píxeles, la línea del encabezado pegajoso de esta celda quedaba
   un píxel desfasada (medido en claro y oscuro); con su ancho natural no. */
.crt-marca input, .crt-todos input { display: block; width: 16px; height: 16px; margin: 0; accent-color: var(--cp-acento); cursor: pointer; }
.crt-todos { display: flex; align-items: center; gap: 10px; margin: 0; cursor: pointer; }
.crt-todos-texto { display: none; font-size: 13px; font-weight: 600; color: var(--cp-tinta); }

.crt-id { color: var(--cp-tenue); font-variant-numeric: tabular-nums; text-decoration: none; }
.crt-id:hover { color: var(--cp-acento); text-decoration: underline; text-underline-offset: 3px; }
.crt-c-nombre { min-width: 200px; }
.crt-nombre { margin: 0; font-weight: 500; color: var(--cp-tinta); cursor: pointer; overflow-wrap: anywhere; }
.crt-id-movil { display: none; margin-left: 6px; font-size: 12px; color: var(--cp-tenue); font-variant-numeric: tabular-nums; }
.crt-c-telefono, .crt-c-caja { font-variant-numeric: tabular-nums; white-space: nowrap; }
.crt-c-zona, .crt-c-caja { color: var(--cp-tenue); white-space: nowrap; }
td.crt-c-monto { font-weight: 600; }
.crt-falta { color: var(--cp-tenue); font-style: italic; }

.crt-estado {
    display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px 2px 8px; border-radius: 999px;
    background: color-mix(in srgb, currentColor 11%, transparent); font-size: 12.5px; font-weight: 600; white-space: nowrap;
}

/* Al que se quita se le deja en la lista (para volver a marcarlo), apagado y con su aviso. */
.crt-fila--quitada td:not(.crt-marca) { color: var(--cp-tenue); }
.crt-fila--quitada .crt-nombre { color: var(--cp-tenue); font-weight: 400; }
.crt-fila--quitada .crt-estado { opacity: .6; }
.crt-no-llama {
    display: inline-flex; align-items: center; gap: 5px; margin-left: 8px; padding: 1px 8px; border-radius: 999px;
    background: color-mix(in srgb, var(--cp-alerta) 10%, transparent); color: var(--cp-alerta);
    font-size: 11.5px; font-weight: 600; white-space: nowrap; vertical-align: 1px;
}
.crt-no-llama .fa { font-size: 10px; }

.crt-fila-esqueleto td { padding-top: 15px; padding-bottom: 15px; }
.crt-esqueleto { display: block; height: 11px; border-radius: 6px; background: var(--cp-barra); }
@media (prefers-reduced-motion: no-preference) {
    .crt-esqueleto { animation: crt-pulso 1.4s ease-in-out infinite; }
}
@keyframes crt-pulso { 50% { opacity: .45; } }

.crt-vacio { padding: 44px 20px !important; text-align: center; color: var(--cp-tenue); }
.crt-vacio :deep(strong), .crt-vacio > strong { display: block; margin-bottom: 4px; color: var(--cp-tinta); font-size: 14.5px; }
.crt-vacio > span { display: block; margin-bottom: 14px; }

/* ── Pie: rango y páginas (como el listado de campañas) ──────────────────── */
.crt-pie { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 16px; border-top: 1px solid var(--cp-borde); background: var(--cp-cabecera); }
.crt-rango, .crt-de { font-size: 12.5px; color: var(--cp-tenue); }
.crt-paginacion { display: flex; align-items: center; gap: 8px; }
.crt-por-pagina { display: inline-flex; align-items: center; gap: 8px; margin: 0 6px 0 0; font-size: 12.5px; color: var(--cp-tenue); }
.crt-por-pagina .cp-campo { width: auto; min-height: 34px; padding: 4px 8px; }
.crt-icono {
    width: 34px; height: 34px; flex: none; border-radius: 8px; border: 1px solid var(--cp-borde-campo);
    background: var(--cp-superficie); color: var(--cp-tinta); display: inline-flex; align-items: center; justify-content: center;
    font-size: 12.5px; cursor: pointer; transition: background-color .15s, border-color .15s, color .15s;
}
.crt-icono:hover:not(:disabled) { background: var(--cp-acento-suave); border-color: transparent; color: var(--cp-acento); }
.crt-icono:disabled { opacity: .4; cursor: default; }

/* ── Angosta: una tarjeta por cliente ────────────────────────────────────── */
@container crt (max-width: 720px) {
    .crt-acciones { margin-left: 0; }
    .crt-orden-movil { display: inline-flex; }
    .crt-total { flex-basis: 100%; margin-left: 0; }
    .crt-tabla thead tr { display: block; }
    .crt-tabla thead { position: static; }
    .crt-tabla thead th { display: none; }
    .crt-tabla thead th.crt-marca { display: block; width: auto; padding: 10px 16px; }
    .crt-todos-texto { display: inline; }
    .crt-tabla tbody { display: block; }
    .crt-tabla tbody tr {
        display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: 4px 10px; padding: 12px 16px;
        grid-template-areas: "marca nombre estado" "marca telefono monto" "marca zona caja"; border-bottom: 1px solid var(--cp-borde);
    }
    .crt-tabla tbody tr:last-child { border-bottom: 0; }
    .crt-tabla tbody td { display: block; padding: 0 !important; border: 0; background: none !important; }
    .crt-tabla .crt-fila-vacia, .crt-tabla .crt-fila-esqueleto { display: block !important; }
    .crt-tabla .crt-fila-vacia td { padding: 32px 0 !important; }
    .crt-tabla .crt-fila-esqueleto td { margin-bottom: 8px; }
    .crt-marca { grid-area: marca; padding-top: 2px !important; }
    .crt-marca input, .crt-todos input { width: 20px; height: 20px; }
    .crt-c-id { display: none !important; }
    .crt-id-movil { display: inline; }
    .crt-c-nombre { grid-area: nombre; min-width: 0; }
    .crt-c-estado { grid-area: estado; }
    .crt-c-telefono { grid-area: telefono; color: var(--cp-tenue); }
    .crt-c-monto { grid-area: monto; text-align: right; }
    .crt-c-zona { grid-area: zona; }
    .crt-c-caja { grid-area: caja; text-align: right; }
    .crt-pie { padding: 10px 12px; }
    .crt-rango { flex-basis: 100%; }
    .crt-paginacion { width: 100%; justify-content: space-between; }
}
@media (max-width: 767px) {
    .crt-boton, .crt-icono, .crt-segmento, .crt-chip, .crt-limpiar { min-height: 44px; }
    .crt-icono { width: 44px; height: 44px; }
    .crt-buscar { max-width: none; flex-basis: 100%; }
}
</style>
