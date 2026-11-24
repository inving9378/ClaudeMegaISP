<template>
    <div class="cd" ref="raiz" :class="{ 'cd-dark': darkMode }" :style="actual ? { paddingBottom: espacioReproductor + 'px' } : null">
        <!-- Encabezado: qué se ve y de cuándo -->
        <div class="cd-cabecera">
            <div>
                <h1 class="cd-titulo">Llamadas de la central</h1>
                <p class="cd-sub">Entrantes, salientes, campañas e internas, con su grabación.</p>
            </div>
            <div class="cd-rango">
                <div class="cd-segmentos" role="group" aria-label="Rango de fechas">
                    <button v-for="rg in rangosRapidos" :key="rg.dias" type="button" class="cd-segmento"
                            :aria-pressed="rangoActivo === rg.dias" @click="rangoRapido(rg.dias)">{{ rg.label }}</button>
                </div>
                <div class="cd-fechas">
                    <label class="cd-fecha">
                        <span class="visually-hidden">Desde</span>
                        <input type="date" class="form-control form-control-sm" v-model="desde" :max="hasta" @change="fechasAMano">
                    </label>
                    <span class="cd-tenue" aria-hidden="true">a</span>
                    <label class="cd-fecha">
                        <span class="visually-hidden">Hasta</span>
                        <input type="date" class="form-control form-control-sm" v-model="hasta" :min="desde" @change="fechasAMano">
                    </label>
                </div>
                <button type="button" class="cd-boton" @click="recargarTodo" :disabled="cargando">
                    <i class="fa fa-sync-alt" :class="{ 'fa-spin': cargando }" aria-hidden="true"></i> Actualizar
                </button>
            </div>
        </div>

        <div v-if="datos && datos.central === false" class="cd-aviso" role="status">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
            <div>
                <strong>La central de llamadas no responde en este servidor.</strong>
                Cuando esté instalada (Configuración → Add-ons → VoIP → Dependencias del servidor) aquí aparecerán todas las llamadas.
            </div>
        </div>

        <div v-else-if="errorCarga && ! datos" class="cd-aviso cd-aviso--error" role="alert">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
            <div>
                <strong>No se pudo cargar el tablero.</strong> Revisa tu conexión e inténtalo otra vez.
                <button type="button" class="cd-boton cd-boton--suave ms-2" @click="recargarTodo">Reintentar</button>
            </div>
        </div>

        <template v-else>
            <div v-if="errorCarga" class="cd-aviso cd-aviso--error cd-aviso--chico" role="alert">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <div>No se pudo actualizar; lo que ves es de la última carga. <button type="button" class="cd-enlace" @click="recargarTodo">Reintentar</button></div>
            </div>

            <!-- Tipo de llamada: también es el desglose por tipo -->
            <div class="cd-tipos" role="group" aria-label="Tipo de llamada">
                <button v-for="t in tipos" :key="t.id" type="button" class="cd-tipo" :aria-pressed="tipo === t.id"
                        @click="elegirTipo(t.id)">
                    <i v-if="t.icono" class="fa" :class="t.icono" aria-hidden="true"></i>
                    <span>{{ t.label }}</span>
                    <span class="cd-tipo-n">{{ datos ? (datos.por_tipo?.[t.id || 'todas'] ?? 0) : '·' }}</span>
                </button>
            </div>

            <!-- Resumen del tipo elegido -->
            <section class="cd-resumen" aria-label="Resumen">
                <div class="cd-cifra">
                    <div class="cd-cifra-label">Llamadas</div>
                    <div class="cd-cifra-valor" :class="{ 'cd-esqueleto': ! datos }">{{ datos ? r.total : '' }}</div>
                    <div class="cd-cifra-nota">{{ datos ? (tipo ? tipoActual.label.toLowerCase() : 'de todos los tipos') : '' }}</div>
                </div>
                <div class="cd-cifra">
                    <div class="cd-cifra-label">Contestadas</div>
                    <div class="cd-cifra-valor" :class="{ 'cd-esqueleto': ! datos }">{{ datos ? r.contestadas : '' }}</div>
                    <div class="cd-cifra-nota">{{ datos ? (r.contestadas_pct === null ? '—' : `${r.contestadas_pct}% del total`) : '' }}</div>
                </div>
                <button type="button" class="cd-cifra cd-cifra--accion" :class="{ 'cd-cifra--alerta': r.perdidas > 0 }"
                        :disabled="! datos || ! r.perdidas" @click="verPerdidas">
                    <span class="cd-cifra-label">Perdidas</span>
                    <span class="cd-cifra-valor" :class="{ 'cd-esqueleto': ! datos }">
                        <i v-if="r.perdidas > 0" class="fa fa-phone-slash cd-cifra-icono" aria-hidden="true"></i>{{ datos ? r.perdidas : '' }}
                    </span>
                    <span class="cd-cifra-nota">{{ datos ? (r.perdidas ? 'entraron y nadie contestó · ver' : 'entraron y nadie contestó') : '' }}</span>
                </button>
                <div class="cd-cifra">
                    <div class="cd-cifra-label">Tiempo hablado</div>
                    <div class="cd-cifra-valor" :class="{ 'cd-esqueleto': ! datos }">{{ datos ? duracion(r.segundos_total || 0) : '' }}</div>
                    <div class="cd-cifra-nota">{{ datos ? (r.duracion_prom ? `${duracion(r.duracion_prom)} en promedio` : '—') : '' }}</div>
                </div>
                <div class="cd-cifra">
                    <div class="cd-cifra-label">Grabadas</div>
                    <div class="cd-cifra-valor" :class="{ 'cd-esqueleto': ! datos }">{{ datos ? r.con_grabacion : '' }}</div>
                    <div class="cd-cifra-nota">{{ datos ? `de ${r.contestadas} contestadas` : '' }}</div>
                </div>
            </section>

            <!-- Por día -->
            <section class="cd-panel">
                <div class="cd-panel-cabeza">
                    <h2 class="cd-panel-titulo" id="cd-titulo-dia">Por día</h2>
                    <span v-if="datos && r.total" class="cd-tenue">Haz clic en una barra para ver las llamadas de ese día</span>
                </div>
                <div class="cd-grafica" :aria-label="resumenGrafica" role="img" aria-describedby="cd-titulo-dia">
                    <div v-if="! datos" class="cd-grafica-esqueleto"></div>
                    <div v-else-if="! r.total" class="cd-vacio cd-vacio--grafica">
                        Sin llamadas {{ tipoActual.vacio }} {{ textoRango }}.
                    </div>
                    <apexchart v-else type="bar" height="220" :options="opcionesGrafica" :series="seriesGrafica"></apexchart>
                </div>
            </section>

            <!-- Historial -->
            <section class="cd-panel" ref="historial">
                <div class="cd-panel-cabeza">
                    <h2 class="cd-panel-titulo">Historial</h2>
                    <span class="cd-tenue" aria-live="polite">{{ totalLlamadas }} llamada{{ totalLlamadas === 1 ? '' : 's' }}</span>
                </div>
                <div class="cd-filtros">
                    <label class="cd-buscar">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <span class="visually-hidden">Buscar</span>
                        <input type="search" class="form-control form-control-sm" placeholder="Número, cliente, trabajador o campaña"
                               v-model="filtros.buscar" @input="buscarConPausa">
                    </label>
                    <div class="cd-segmentos" role="group" aria-label="Estado">
                        <button v-for="e in estados" :key="e.id" type="button" class="cd-segmento"
                                :aria-pressed="filtros.estado === e.id" @click="elegirEstado(e.id)" :title="e.ayuda">{{ e.label }}</button>
                    </div>
                    <label class="cd-interruptor">
                        <input type="checkbox" role="switch" v-model="filtros.con_grabacion" @change="cargarLlamadas(1)">
                        <span>Solo con grabación</span>
                    </label>
                    <button v-if="filtros.dia" type="button" class="cd-chip" @click="quitarDia" :aria-label="`Quitar el filtro del ${fechaLarga(filtros.dia)}`">
                        Solo el {{ fechaLarga(filtros.dia) }} <i class="fa fa-times" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="cd-tabla" :aria-busy="cargandoLlamadas ? 'true' : 'false'">
                        <caption class="visually-hidden">Historial de llamadas{{ filtros.dia ? ' del ' + fechaLarga(filtros.dia) : '' }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="cd-col-play"><span class="visually-hidden">Grabación</span><i class="fa fa-headphones" aria-hidden="true" title="Grabación"></i></th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Llamada</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Trabajador o campaña</th>
                                <th scope="col" class="cd-num">Duración</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody v-if="cargandoLlamadas && ! llamadas.length">
                            <tr v-for="n in 6" :key="'e' + n" class="cd-fila-esqueleto" aria-hidden="true">
                                <td v-for="m in 7" :key="m"><span class="cd-esqueleto"></span></td>
                            </tr>
                        </tbody>
                        <tbody v-else-if="errorLlamadas">
                            <tr>
                                <td colspan="7" class="cd-vacio" role="alert">
                                    <div>No se pudo cargar el historial.</div>
                                    <button type="button" class="cd-boton cd-boton--suave mt-2" @click="cargarLlamadas(pagina)">Reintentar</button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else-if="! llamadas.length">
                            <tr>
                                <td colspan="7" class="cd-vacio">
                                    <template v-if="hayFiltros">
                                        <div>Ninguna llamada coincide con la búsqueda y los filtros.</div>
                                        <button type="button" class="cd-boton cd-boton--suave mt-2" @click="quitarFiltros">Quitar filtros</button>
                                    </template>
                                    <template v-else-if="r.total">
                                        <div>Sin llamadas en esta página.</div>
                                    </template>
                                    <template v-else>
                                        <div>Prueba con un rango más amplio.</div>
                                        <button v-if="rangoActivo !== 30" type="button" class="cd-boton cd-boton--suave mt-2" @click="rangoRapido(30)">Ver los últimos 30 días</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else :class="{ 'cd-cargando': cargandoLlamadas }">
                            <tr v-for="l in llamadas" :key="l.id" :class="{ 'cd-fila-actual': esActual(l) }">
                                <td class="cd-col-play">
                                    <button v-if="l.grabacion && puedeEscuchar" type="button" class="cd-play" :data-llamada="l.id"
                                            :class="{ 'cd-play--actual': esActual(l) }" @click="alternar(l)"
                                            :aria-label="(esActual(l) && sonando ? 'Pausar' : 'Escuchar') + ' la llamada del ' + soloFecha(l.inicio) + ' a las ' + soloHora(l.inicio)">
                                        <i class="fa" :class="esActual(l) && sonando ? 'fa-pause' : 'fa-play'" aria-hidden="true"></i>
                                    </button>
                                    <span v-else-if="l.tiene_grabacion" class="cd-sin-play" title="Grabada; no tienes permiso para escuchar grabaciones">
                                        <i class="fa fa-lock" aria-hidden="true"></i><span class="visually-hidden">Grabada, sin permiso para escucharla</span>
                                    </span>
                                    <span v-else-if="seContesto(l)" class="cd-sin-play cd-sin-play--aviso" title="Se contestó y no se grabó (antes del 24-nov solo se grababan las de la cola)">
                                        <i class="fa fa-microphone-slash" aria-hidden="true"></i><span class="visually-hidden">Se contestó y no se grabó</span>
                                    </span>
                                </td>
                                <td class="cd-nowrap">
                                    <div class="cd-fuerte">{{ soloFecha(l.inicio) }}</div>
                                    <div class="cd-tenue">{{ soloHora(l.inicio) }}</div>
                                </td>
                                <td class="cd-nowrap">
                                    <div class="cd-llamada">
                                        <span class="cd-dir" :class="'cd-dir--' + l.direccion" aria-hidden="true">
                                            <i class="fa" :class="iconoTipo(l.direccion)"></i>
                                        </span>
                                        <div>
                                            <div class="cd-numero">{{ textoNumero(l) }}</div>
                                            <div class="cd-tenue">{{ nombreTipo(l.direccion) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="cd-cliente">
                                    <a v-if="l.cliente_id" :href="`/cliente/editar/${l.cliente_id}`" :title="l.cliente">{{ l.cliente }}</a>
                                    <span v-else-if="l.direccion !== 'interna' && l.numero" class="cd-tenue">No es cliente</span>
                                    <span v-else class="cd-tenue">—</span>
                                </td>
                                <td>
                                    <div v-if="l.direccion === 'campana'" class="cd-persona">
                                        <span class="cd-avatar cd-avatar--campana" aria-hidden="true"><i class="fa fa-bullhorn"></i></span>
                                        <div>
                                            <div class="cd-persona-nombre" :title="l.campana">{{ l.campana || 'Campaña sin registro' }}</div>
                                            <div v-if="l.campana_tipo" class="cd-tenue">{{ nombreCampana(l.campana_tipo) }}</div>
                                        </div>
                                    </div>
                                    <div v-else-if="l.trabajador_id" class="cd-persona">
                                        <span class="cd-avatar" aria-hidden="true">{{ iniciales(nombreTrabajador(l)) }}</span>
                                        <div>
                                            <div class="cd-persona-nombre" :title="nombreTrabajador(l)">{{ nombreTrabajador(l) }}</div>
                                            <div class="cd-tenue">Ext. {{ extensionTrabajador(l) }}</div>
                                        </div>
                                    </div>
                                    <div v-else-if="l.extension" class="cd-persona">
                                        <span class="cd-avatar cd-avatar--ext" aria-hidden="true"><i class="fa fa-phone"></i></span>
                                        <div class="cd-persona-nombre">Ext. {{ extensionTrabajador(l) }}</div>
                                    </div>
                                    <div v-else-if="l.estado === 'asistente'" class="cd-persona">
                                        <span class="cd-avatar cd-avatar--bot" aria-hidden="true"><i class="fa fa-robot"></i></span>
                                        <div class="cd-persona-nombre">Asistente de voz</div>
                                    </div>
                                    <span v-else class="cd-tenue">—</span>
                                </td>
                                <td class="cd-num cd-nowrap">
                                    <div class="cd-fuerte">{{ l.segundos ? duracion(l.segundos) : '—' }}</div>
                                    <div v-if="l.espera > 0" class="cd-tenue">espera {{ duracion(l.espera) }}</div>
                                </td>
                                <td>
                                    <span class="cd-estado" :class="'cd-estado--' + l.estado">
                                        <i class="fa" :class="iconoEstado(l.estado)" aria-hidden="true"></i>{{ nombreEstado(l.estado) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cd-pie" v-if="totalLlamadas > porPagina">
                    <span class="cd-tenue">Página {{ pagina }} de {{ paginas }}</span>
                    <div class="cd-paginas">
                        <button type="button" class="cd-boton" :disabled="pagina <= 1 || cargandoLlamadas" @click="cargarLlamadas(pagina - 1)">‹ Anterior</button>
                        <button type="button" class="cd-boton" :disabled="pagina >= paginas || cargandoLlamadas" @click="cargarLlamadas(pagina + 1)">Siguiente ›</button>
                    </div>
                </div>
            </section>
        </template>

        <!-- Avisos para lector de pantalla -->
        <div class="visually-hidden" aria-live="polite">{{ anuncio }}</div>

        <!-- Reproductor fijo al fondo de la ventana: se sigue escuchando mientras se recorre el historial.
             Va fuera del layout (el contenedor del layout recorta lo que es «sticky»). -->
        <Teleport to="body">
            <aside v-if="actual" ref="reproductor" class="cd-reproductor" :class="{ 'cd-dark': darkMode }" :style="posicionReproductor"
                   aria-label="Reproductor de la grabación" @keydown.esc="cerrarReproductor">
                <div class="cd-repro-info">
                    <span class="cd-dir" :class="'cd-dir--' + actual.direccion" aria-hidden="true"><i class="fa" :class="iconoTipo(actual.direccion)"></i></span>
                    <div class="cd-repro-texto" :title="textoNumero(actual) + (actual.cliente ? ' · ' + actual.cliente : '')">
                        <div class="cd-fuerte cd-recorte">{{ textoNumero(actual) }}<template v-if="actual.cliente"> · {{ actual.cliente }}</template></div>
                        <div class="cd-tenue cd-recorte">{{ nombreTipo(actual.direccion) }} · {{ soloFecha(actual.inicio) }} {{ soloHora(actual.inicio) }}<template v-if="actual.trabajador"> · {{ nombreTrabajador(actual) }}</template></div>
                    </div>
                </div>
                <div class="cd-repro-audio">
                    <button type="button" class="cd-icono" :disabled="! hayAnterior" @click="saltar(-1)" title="Grabación anterior" aria-label="Grabación anterior">
                        <i class="fa fa-step-backward" aria-hidden="true"></i>
                    </button>
                    <audio ref="audio" controls preload="auto" :src="urlGrabacion(actual, false)" aria-label="Grabación de la llamada"
                           @play="sonando = true" @pause="sonando = false" @ended="sonando = false" @error="errorAudio = true"
                           @loadedmetadata="aplicarVelocidad"></audio>
                    <button type="button" class="cd-icono" :disabled="! haySiguiente" @click="saltar(1)" title="Siguiente grabación" aria-label="Siguiente grabación">
                        <i class="fa fa-step-forward" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="cd-repro-acciones">
                    <div class="cd-segmentos cd-segmentos--chico" role="group" aria-label="Velocidad">
                        <button v-for="v in velocidades" :key="v" type="button" class="cd-segmento" :aria-pressed="velocidad === v"
                                @click="cambiarVelocidad(v)">{{ String(v).replace('.', ',') }}×</button>
                    </div>
                    <a class="cd-icono" :href="urlGrabacion(actual, true)" title="Descargar" aria-label="Descargar la grabación"><i class="fa fa-download" aria-hidden="true"></i></a>
                    <button type="button" class="cd-icono" @click="cerrarReproductor" title="Cerrar (Esc)" aria-label="Cerrar el reproductor"><i class="fa fa-times" aria-hidden="true"></i></button>
                </div>
                <div v-if="errorAudio" class="cd-repro-error" role="alert">
                    No se pudo abrir esta grabación: ya no está en el servidor o el archivo está dañado.
                </div>
                <div v-else class="cd-repro-atajos cd-tenue">Espacio: pausa · ← →: 5 segundos · Esc: cerrar</div>
            </aside>
        </Teleport>
    </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';

// Paleta validada (dataviz) contra la superficie clara y la oscura; azul y naranja son los
// de VoIP → Dashboard (contestadas / no atendidas). El gris de «sin respuesta» es neutro a
// propósito: no es una categoría que compita, es lo que no dio resultado.
const COLORES = {
    claro:  ['#2a78d6', '#eb6834', '#878d99'],
    oscuro: ['#3987e5', '#d95926', '#8a92a6'],
};
const TIPOS = [
    { id: '',         label: 'Todas',     icono: null,              vacio: '' },
    { id: 'entrante', label: 'Entrantes', icono: 'fa-arrow-down',   vacio: 'entrantes' },
    { id: 'saliente', label: 'Salientes', icono: 'fa-arrow-up',     vacio: 'salientes' },
    { id: 'campana',  label: 'Campañas',  icono: 'fa-bullhorn',     vacio: 'de campañas' },
    { id: 'interna',  label: 'Internas',  icono: 'fa-exchange-alt', vacio: 'internas' },
];
const ESTADOS = ['contestada', 'perdida', 'sin_respuesta'];
const RANGOS = [1, 7, 30];
const LLAVE_VELOCIDAD = 'cobranza-dashboard-velocidad';
const FECHA = /^\d{4}-\d{2}-\d{2}$/;

function isoLocal(fecha) {
    const d = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return d.toISOString().slice(0, 10);
}

function haceDias(n) {
    const d = new Date(); d.setDate(d.getDate() - n);
    return isoLocal(d);
}

function leerVelocidad() {
    try {
        const v = parseFloat(window.localStorage.getItem(LLAVE_VELOCIDAD));
        return [1, 1.5, 2].includes(v) ? v : 1;
    } catch {
        return 1;
    }
}

export default {
    name: 'CobranzaDashboard',

    props: {
        baseUrl:        { type: String, required: true },
        grabacionesUrl: { type: String, required: true },
        puedeEscuchar:  { type: Boolean, default: false },
    },

    setup() {
        return { darkMode };
    },

    data() {
        const q = new URLSearchParams(window.location.search);
        const f = v => (FECHA.test(v || '') ? v : null);
        return {
            tipos: TIPOS,
            estados: [
                { id: '', label: 'Todas', ayuda: null },
                { id: 'contestada', label: 'Contestadas', ayuda: 'Las atendió una persona o el asistente de voz' },
                { id: 'perdida', label: 'Perdidas', ayuda: 'Entraron y nadie las contestó' },
                { id: 'sin_respuesta', label: 'Sin respuesta', ayuda: 'Las hicimos (o una campaña) y no contestaron' },
            ],
            rangosRapidos: [{ dias: 1, label: 'Hoy' }, { dias: 7, label: '7 días' }, { dias: 30, label: '30 días' }],
            velocidades: [1, 1.5, 2],
            desde: f(q.get('desde')) || haceDias(6),
            hasta: f(q.get('hasta')) || haceDias(0),
            tipo: TIPOS.some(t => t.id === q.get('tipo')) ? q.get('tipo') : '',
            filtros: {
                buscar: q.get('buscar') || '',
                estado: ESTADOS.includes(q.get('estado')) ? q.get('estado') : '',
                con_grabacion: q.get('grabacion') === '1',
                dia: f(q.get('dia')),
            },
            cargando: false,
            errorCarga: false,
            datos: null,
            cargandoLlamadas: false,
            errorLlamadas: false,
            llamadas: [],
            totalLlamadas: 0,
            pagina: 1,
            porPagina: 25,
            actual: null,
            sonando: false,
            errorAudio: false,
            velocidad: leerVelocidad(),
            posicionReproductor: {},
            espacioReproductor: 0,
            anuncio: '',
            _pausa: null,
            _pedido: 0,
            _pedidoDatos: 0,
        };
    },

    computed: {
        r() {
            return this.datos?.resumen || {};
        },
        // El rango rápido se marca por las fechas, venga de un botón, de la dirección o a mano.
        rangoActivo() {
            if (this.hasta !== haceDias(0)) return null;
            return RANGOS.find(n => this.desde === haceDias(n - 1)) ?? null;
        },
        tipoActual() {
            return TIPOS.find(t => t.id === this.tipo) || TIPOS[0];
        },
        textoRango() {
            const d = this.datos;
            if (! d) return '';
            return d.desde === d.hasta ? `el ${this.fechaLarga(d.desde)}` : `entre el ${this.fechaLarga(d.desde)} y el ${this.fechaLarga(d.hasta)}`;
        },
        resumenGrafica() {
            const r = this.r;
            return `Llamadas por día ${this.textoRango}: ${r.contestadas ?? 0} contestadas, ${r.perdidas ?? 0} perdidas y ${r.sin_respuesta ?? 0} sin respuesta. El detalle está en el historial.`;
        },
        seriesGrafica() {
            const d = this.datos?.por_dia || [];
            return [
                { name: 'Contestadas', data: d.map(x => x.contestadas) },
                { name: 'Perdidas', data: d.map(x => x.perdidas) },
                { name: 'Sin respuesta', data: d.map(x => x.sin_respuesta) },
            ];
        },
        opcionesGrafica() {
            const oscuro = this.darkMode;
            const tinta = oscuro ? '#c3c2b7' : '#52514e';
            const superficie = oscuro ? '#2a3042' : '#ffffff';
            const dias = this.datos?.por_dia || [];
            // Una fecha cada tantos días: con 30 juntas no se lee ninguna (en celular caben 5).
            const paso = Math.max(1, Math.ceil(dias.length / (window.innerWidth < 768 ? 5 : 10)));
            return {
                chart: {
                    stacked: true, toolbar: { show: false }, fontFamily: 'inherit', background: 'transparent',
                    animations: { enabled: false }, zoom: { enabled: false },
                    events: { dataPointSelection: (e, ctx, cfg) => this.verDia(dias[cfg.dataPointIndex]?.fecha) },
                },
                theme: { mode: oscuro ? 'dark' : 'light' },
                colors: oscuro ? COLORES.oscuro : COLORES.claro,
                plotOptions: { bar: { columnWidth: dias.length > 20 ? '70%' : '50%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                stroke: { show: true, width: 2, colors: [superficie] },
                dataLabels: { enabled: false },
                legend: { position: 'top', horizontalAlign: 'left', labels: { colors: tinta }, markers: { size: 6 }, itemMargin: { horizontal: 10 } },
                grid: { borderColor: oscuro ? '#3a4055' : '#ebebe8', yaxis: { lines: { show: true } }, padding: { left: 4, right: 4 } },
                xaxis: {
                    categories: dias.map((x, i) => (i % paso === 0 || i === dias.length - 1 ? this.fechaCorta(x.fecha) : '')),
                    labels: { style: { colors: tinta }, rotate: 0, hideOverlappingLabels: false, trim: false },
                    axisBorder: { show: false }, axisTicks: { show: false },
                    tooltip: { enabled: false },
                },
                yaxis: { labels: { style: { colors: tinta }, formatter: v => Math.round(v) }, forceNiceScale: true, min: 0 },
                tooltip: { shared: true, intersect: false, theme: oscuro ? 'dark' : 'light', x: { formatter: (v, o) => this.fechaLarga(dias[o?.dataPointIndex]?.fecha) } },
                states: { hover: { filter: { type: 'darken', value: 0.9 } }, active: { filter: { type: 'none' } } },
            };
        },
        paginas() {
            return Math.max(1, Math.ceil(this.totalLlamadas / this.porPagina));
        },
        hayFiltros() {
            return !! (this.filtros.buscar.trim() || this.filtros.estado || this.filtros.con_grabacion || this.filtros.dia);
        },
        conGrabacion() {
            return this.puedeEscuchar ? this.llamadas.filter(l => l.grabacion) : [];
        },
        indiceActual() {
            return this.conGrabacion.findIndex(l => l.id === this.actual?.id);
        },
        // Anterior/siguiente siguen en la otra página cuando esta se acaba.
        hayAnterior() {
            return this.indiceActual > 0 || (this.indiceActual === 0 && this.pagina > 1);
        },
        haySiguiente() {
            const i = this.indiceActual;
            return (i >= 0 && i < this.conGrabacion.length - 1) || (i === this.conGrabacion.length - 1 && this.pagina < this.paginas);
        },
    },

    watch: {
        errorAudio() {
            this.$nextTick(() => this.colocarReproductor());
        },
    },

    mounted() {
        this.recargarTodo();
        window.addEventListener('resize', this.colocarReproductor);
        document.addEventListener('keydown', this.atajos);
        // Abrir o cerrar el menú lateral cambia el ancho del tablero sin cambiar la ventana.
        if (window.ResizeObserver) {
            this._observador = new ResizeObserver(() => this.colocarReproductor());
            this._observador.observe(this.$refs.raiz);
        }
    },

    beforeUnmount() {
        clearTimeout(this._pausa);
        this.$refs.audio?.pause();
        window.removeEventListener('resize', this.colocarReproductor);
        document.removeEventListener('keydown', this.atajos);
        this._observador?.disconnect();
    },

    methods: {
        params(extra = {}) {
            const p = { desde: this.desde, hasta: this.hasta, ...extra };
            if (this.tipo) p.tipo = this.tipo;
            return new URLSearchParams(p);
        },

        async pedir(ruta, params) {
            const r = await fetch(`${this.baseUrl}/${ruta}?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (! r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        },

        // La dirección de la página guarda lo que se está viendo: F5 o un enlace compartido abren lo mismo.
        recordarEnUrl() {
            const p = new URLSearchParams();
            if (this.rangoActivo !== 7) { p.set('desde', this.desde); p.set('hasta', this.hasta); }
            if (this.tipo) p.set('tipo', this.tipo);
            if (this.filtros.estado) p.set('estado', this.filtros.estado);
            if (this.filtros.buscar.trim()) p.set('buscar', this.filtros.buscar.trim());
            if (this.filtros.con_grabacion) p.set('grabacion', '1');
            if (this.filtros.dia) p.set('dia', this.filtros.dia);
            const q = p.toString();
            try {
                window.history.replaceState(window.history.state, '', window.location.pathname + (q ? '?' + q : ''));
            } catch { /* sin historial: solo no se recuerda */ }
        },

        recargarTodo() {
            if (this.filtros.dia && (this.filtros.dia < this.desde || this.filtros.dia > this.hasta)) this.filtros.dia = null;
            this.cargar();
            this.cargarLlamadas(1);
        },

        async cargar() {
            const pedido = ++this._pedidoDatos;
            this.cargando = true;
            try {
                const datos = await this.pedir('data', this.params());
                if (pedido !== this._pedidoDatos) return;
                this.datos = datos;
                this.errorCarga = false;
            } catch {
                if (pedido === this._pedidoDatos) this.errorCarga = true;
            } finally {
                if (pedido === this._pedidoDatos) this.cargando = false;
            }
        },

        async cargarLlamadas(pagina) {
            const pedido = ++this._pedido;   // solo cuenta la respuesta del último pedido
            this.cargandoLlamadas = true;
            this.recordarEnUrl();
            const f = this.filtros;
            const extra = { pagina, por_pagina: this.porPagina };
            if (f.buscar.trim()) extra.buscar = f.buscar.trim();
            if (f.estado) extra.estado = f.estado;
            if (f.con_grabacion) extra.con_grabacion = 1;
            if (f.dia) extra.dia = f.dia;
            try {
                const r = await this.pedir('llamadas', this.params(extra));
                if (pedido !== this._pedido) return false;
                this.llamadas = r.llamadas || [];
                this.totalLlamadas = r.total || 0;
                this.pagina = r.pagina || 1;
                this.errorLlamadas = false;
                return true;
            } catch {
                if (pedido !== this._pedido) return false;
                this.errorLlamadas = true;
                return false;
            } finally {
                if (pedido === this._pedido) this.cargandoLlamadas = false;
            }
        },

        buscarConPausa() {
            clearTimeout(this._pausa);
            this._pausa = setTimeout(() => this.cargarLlamadas(1), 350);
        },

        rangoRapido(dias) {
            this.desde = haceDias(dias - 1);
            this.hasta = haceDias(0);
            this.recargarTodo();
        },

        fechasAMano() {
            if (! this.desde || ! this.hasta) return;
            this.recargarTodo();
        },

        // Tocar una barra deja el rango como está y solo filtra el historial a ese día.
        verDia(fecha) {
            if (! fecha) return;
            this.filtros.dia = this.filtros.dia === fecha ? null : fecha;
            this.cargarLlamadas(1);
            if (this.filtros.dia) this.$refs.historial?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        quitarDia() {
            this.filtros.dia = null;
            this.cargarLlamadas(1);
        },

        verPerdidas() {
            this.filtros.estado = 'perdida';
            this.cargarLlamadas(1);
            this.$refs.historial?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        elegirTipo(id) {
            if (this.tipo === id) return;
            this.tipo = id;
            this.recargarTodo();
        },

        elegirEstado(id) {
            this.filtros.estado = id;
            this.cargarLlamadas(1);
        },

        quitarFiltros() {
            this.filtros = { buscar: '', estado: '', con_grabacion: false, dia: null };
            this.cargarLlamadas(1);
        },

        // ── Reproductor ──
        esActual(l) {
            return !! this.actual && this.actual.id === l.id;
        },

        alternar(l) {
            if (this.esActual(l)) {
                const a = this.$refs.audio;
                if (a) a.paused ? a.play().catch(() => {}) : a.pause();
                return;
            }
            this.reproducir(l);
        },

        reproducir(l) {
            this.errorAudio = false;
            this.actual = l;
            this.anuncio = `Reproduciendo la llamada del ${this.soloFecha(l.inicio)} a las ${this.soloHora(l.inicio)}, ${this.textoNumero(l)}.`;
            this.$nextTick(() => {
                this.colocarReproductor();
                const a = this.$refs.audio;
                if (! a) return;
                a.load();
                a.play().catch(() => {});
            });
        },

        async saltar(paso) {
            const i = this.indiceActual;
            const lista = this.conGrabacion;
            if (lista[i + paso]) return this.reproducir(lista[i + paso]);
            const otra = this.pagina + paso;
            if (otra < 1 || otra > this.paginas) return;
            if (await this.cargarLlamadas(otra)) {
                const nueva = this.conGrabacion;
                const l = paso > 0 ? nueva[0] : nueva[nueva.length - 1];
                if (l) this.reproducir(l);
            }
        },

        cerrarReproductor() {
            if (! this.actual) return;
            const id = this.actual.id;
            this.$refs.audio?.pause();
            this.actual = null;
            this.sonando = false;
            this.anuncio = 'Reproductor cerrado.';
            this.$nextTick(() => {
                const boton = [...(this.$refs.raiz?.querySelectorAll('.cd-play[data-llamada]') || [])].find(b => b.dataset.llamada === String(id));
                boton?.focus();
            });
        },

        // Fijo al fondo de la ventana, alineado con el tablero. En escritorio deja libre la esquina
        // de JARVIS y la ayuda; en celular va arriba de la barra de módulos de abajo.
        colocarReproductor() {
            const raiz = this.$refs.raiz;
            if (! raiz || ! this.actual) return;
            const caja = raiz.getBoundingClientRect();
            const barra = document.getElementById('mnav-rail');
            const altoBarra = barra && barra.offsetParent !== null ? barra.getBoundingClientRect().height : 0;
            const abajo = Math.round(altoBarra) + 12;
            const libre = window.innerWidth > 992 ? 150 : 0;
            const ancho = Math.max(280, Math.min(caja.width, window.innerWidth - caja.left - libre - 12));
            this.posicionReproductor = window.innerWidth < 768
                ? { left: '8px', right: '8px', width: 'auto', bottom: `${abajo}px` }
                : { left: `${Math.round(caja.left)}px`, width: `${Math.round(ancho)}px`, bottom: `${abajo}px` };
            this.$nextTick(() => {
                const alto = this.$refs.reproductor?.getBoundingClientRect().height || 0;
                this.espacioReproductor = Math.ceil(alto + abajo + 16);
            });
        },

        atajos(e) {
            if (! this.actual || e.ctrlKey || e.metaKey || e.altKey) return;
            const dentro = e.target.closest?.('input, textarea, select, [contenteditable="true"]');
            if (e.key === 'Escape') { this.cerrarReproductor(); return; }
            if (dentro) return;
            const a = this.$refs.audio;
            if (! a) return;
            if (e.key === ' ' && ! e.target.closest?.('button, a, audio')) {
                e.preventDefault();
                a.paused ? a.play().catch(() => {}) : a.pause();
            } else if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && ! e.target.closest?.('audio')) {
                e.preventDefault();
                a.currentTime = Math.max(0, a.currentTime + (e.key === 'ArrowLeft' ? -5 : 5));
            }
        },

        aplicarVelocidad() {
            if (this.$refs.audio) this.$refs.audio.playbackRate = this.velocidad;
        },

        cambiarVelocidad(v) {
            this.velocidad = v;
            this.aplicarVelocidad();
            try { window.localStorage.setItem(LLAVE_VELOCIDAD, String(v)); } catch { /* solo no se recuerda */ }
        },

        urlGrabacion(l, descargar) {
            return `${this.grabacionesUrl}/${encodeURIComponent(l.grabacion)}` + (descargar ? '?descargar=1' : '');
        },

        // ── Formato ──
        seContesto(l) {
            return (l.estado === 'contestada' || l.estado === 'asistente') && l.segundos > 0;
        },

        textoNumero(l) {
            if (l.direccion === 'interna') {
                const de = String(l.origen || '').replace(/^web/, '');
                return de ? `${de} → ${l.numero || '?'}` : `A ${l.numero || '?'}`;
            }
            if (l.numero) return this.formatoNumero(l.numero);
            return l.direccion === 'campana' ? (l.campana || 'Campaña') : 'Número no disponible';
        },

        formatoNumero(n) {
            const d = String(n || '').replace(/\D/g, '');
            if (d.length === 10) return `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}`;
            return n || '—';
        },

        duracion(seg) {
            if (seg === null || seg === undefined) return '—';
            const s = Math.round(seg);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const ss = String(s % 60).padStart(2, '0');
            return h ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
        },

        fechaCorta(iso) {
            if (! iso) return '';
            const [, m, d] = iso.slice(0, 10).split('-');
            return `${d}/${m}`;
        },

        fechaLarga(iso) {
            if (! iso) return '';
            const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            const [a, m, d] = iso.slice(0, 10).split('-');
            return `${parseInt(d, 10)} ${meses[parseInt(m, 10) - 1]} ${a}`;
        },

        soloFecha(iso) {
            if (! iso) return '';
            const [a, m, d] = iso.slice(0, 10).split('-');
            return `${d}/${m}/${a}`;
        },

        soloHora(iso) {
            return iso ? iso.slice(11, 16) : '';
        },

        iconoTipo(d) {
            return { entrante: 'fa-arrow-down', saliente: 'fa-arrow-up', interna: 'fa-exchange-alt', campana: 'fa-bullhorn' }[d] || 'fa-phone';
        },

        nombreTipo(d) {
            return { entrante: 'Entrante', saliente: 'Saliente', interna: 'Interna', campana: 'Campaña' }[d] || d;
        },

        nombreCampana(t) {
            return { cobranza: 'Cobranza', aviso: 'Aviso', anuncio: 'Anuncio', corte: 'Corte' }[t] || 'Campaña';
        },

        iconoEstado(e) {
            return { contestada: 'fa-check', asistente: 'fa-robot', no_atendida: 'fa-phone-slash', sin_respuesta: 'fa-minus' }[e] || 'fa-minus';
        },

        nombreEstado(e) {
            return { contestada: 'Contestada', asistente: 'Asistente', no_atendida: 'Perdida', sin_respuesta: 'Sin respuesta' }[e] || e;
        },

        // El backend manda «Nombre Apellido (2007)»: el nombre sin el número.
        nombreTrabajador(l) {
            return String(l.trabajador || '').replace(/\s*\([^)]*\)\s*$/, '');
        },

        // El número de hoy (el del paréntesis), no el que tenía cuando se hizo la llamada.
        extensionTrabajador(l) {
            const m = String(l.trabajador || '').match(/\((\d+)\)\s*$/);
            return m ? m[1] : String(l.extension || '').replace(/^web/, '');
        },

        iniciales(nombre) {
            const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
            return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?';
        },
    },
};
</script>

<style scoped>
.cd, .cd-reproductor {
    --cd-tinta: #1f2937; --cd-tenue: #5f6774; --cd-borde: #e6e8ec; --cd-cabecera: #f8f9fb;
    --cd-superficie: #ffffff; --cd-hover: #f5f7fa; --cd-acento: #246bc0; --cd-acento-suave: #e8f1fc;
    --cd-alerta: #b42318; --cd-sombra: 0 1px 2px rgba(16, 24, 40, .05);
    color: var(--cd-tinta);
}
.cd.cd-dark, .cd-reproductor.cd-dark {
    --cd-tinta: #e5e7eb; --cd-tenue: #aab1bc; --cd-borde: rgba(255,255,255,.1); --cd-cabecera: rgba(255,255,255,.04);
    --cd-superficie: #2a3042; --cd-hover: rgba(255,255,255,.05); --cd-acento: #6ea8f0; --cd-acento-suave: rgba(57,135,229,.18);
    --cd-alerta: #fda29b; --cd-sombra: none;
}
.cd ::selection { background: var(--cd-acento-suave); color: var(--cd-tinta); }

/* Encabezado */
.cd-cabecera { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.cd-titulo { margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--cd-tinta); }
.cd-sub { margin: 4px 0 0; font-size: 13px; color: var(--cd-tenue); }
.cd-rango { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cd-fechas { display: inline-flex; align-items: center; gap: 6px; }
.cd-fecha { margin: 0; }
.cd-fecha input { width: auto; font-variant-numeric: tabular-nums; }

/* Controles segmentados (rango, estado, velocidad) */
.cd-segmentos { display: inline-flex; padding: 2px; border-radius: 8px; background: var(--cd-cabecera); border: 1px solid var(--cd-borde); }
.cd-segmento {
    border: 0; background: transparent; color: var(--cd-tenue); font-size: 12.5px; font-weight: 500;
    padding: 5px 12px; border-radius: 6px; cursor: pointer; white-space: nowrap; transition: background .15s, color .15s;
}
.cd-segmento:hover { color: var(--cd-tinta); }
.cd-segmento[aria-pressed="true"] { background: var(--cd-superficie); color: var(--cd-tinta); box-shadow: 0 1px 2px rgba(16,24,40,.12); }
.cd-dark .cd-segmento[aria-pressed="true"] { background: rgba(255,255,255,.12); box-shadow: none; }
.cd-segmentos--chico .cd-segmento { padding: 4px 8px; font-size: 12px; font-variant-numeric: tabular-nums; }

/* Tipo de llamada */
.cd-tipos {
    display: flex; gap: 4px; border-bottom: 1px solid var(--cd-borde); margin-bottom: 16px; overflow-x: auto; scrollbar-width: none;
}
.cd-tipos::-webkit-scrollbar { display: none; }
@media (max-width: 767px) {
    .cd-tipos { -webkit-mask-image: linear-gradient(90deg, #000 85%, transparent); mask-image: linear-gradient(90deg, #000 85%, transparent); }
}
.cd-tipo {
    display: inline-flex; align-items: center; gap: 8px; border: 0; background: transparent; color: var(--cd-tenue);
    font-size: 14px; font-weight: 500; padding: 10px 14px; margin-bottom: -1px; border-bottom: 2px solid transparent;
    cursor: pointer; white-space: nowrap; transition: color .15s, border-color .15s;
}
.cd-tipo i { font-size: 11px; }
.cd-tipo:hover { color: var(--cd-tinta); }
.cd-tipo[aria-pressed="true"] { color: var(--cd-acento); border-bottom-color: var(--cd-acento); }
.cd-tipo-n {
    font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums; padding: 1px 8px; border-radius: 999px;
    background: var(--cd-cabecera); color: var(--cd-tenue); border: 1px solid var(--cd-borde);
}
.cd-tipo[aria-pressed="true"] .cd-tipo-n { background: var(--cd-acento-suave); color: var(--cd-acento); border-color: transparent; }

/* Resumen: una sola banda, no cinco tarjetas */
.cd-resumen {
    display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 16px; overflow: hidden;
    background: var(--cd-superficie); border: 1px solid var(--cd-borde); border-radius: 10px; box-shadow: var(--cd-sombra);
}
.cd-cifra { display: block; padding: 14px 18px; min-width: 0; text-align: left; }
.cd-cifra + .cd-cifra { border-left: 1px solid var(--cd-borde); }
.cd-cifra > span { display: block; }
.cd-cifra-label { font-size: 12.5px; color: var(--cd-tenue); }
.cd-cifra-valor { font-size: 1.5rem; font-weight: 600; line-height: 1.25; margin-top: 2px; font-variant-numeric: tabular-nums; min-height: 1.875rem; }
.cd-cifra-nota { font-size: 12px; color: var(--cd-tenue); min-height: 18px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cd-cifra-icono { font-size: .875rem; margin-right: 8px; vertical-align: 2px; }
.cd-cifra--accion { border: 0; background: transparent; color: inherit; font: inherit; cursor: default; }
.cd-cifra--accion:not(:disabled) { cursor: pointer; transition: background .15s; }
.cd-cifra--accion:not(:disabled):hover { background: var(--cd-hover); }
.cd-cifra--alerta .cd-cifra-valor, .cd-cifra--alerta .cd-cifra-nota { color: var(--cd-alerta); }
.cd-resumen .cd-cifra--accion { border-left: 1px solid var(--cd-borde); }
@media (max-width: 991px) {
    .cd-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cd-resumen .cd-cifra { border-left: 0; border-top: 1px solid var(--cd-borde); }
    .cd-resumen .cd-cifra:nth-child(-n+2) { border-top: 0; }
    .cd-resumen .cd-cifra:nth-child(even) { border-left: 1px solid var(--cd-borde); }
}

/* Paneles */
.cd-panel { background: var(--cd-superficie); border: 1px solid var(--cd-borde); border-radius: 10px; box-shadow: var(--cd-sombra); margin-bottom: 16px; overflow: hidden; scroll-margin-top: 90px; }
.cd-panel-cabeza { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; padding: 14px 20px 0; }
.cd-panel-titulo { margin: 0; font-size: 15px; font-weight: 600; color: var(--cd-tinta); }
.cd-grafica { padding: 4px 12px 8px; min-height: 236px; }
.cd-grafica :deep(.apexcharts-bar-area) { cursor: pointer; }
/* El tema pinta ejes y leyenda de las gráficas en gris claro (2:1); aquí se leen. */
.cd-grafica :deep(.apexcharts-xaxis text), .cd-grafica :deep(.apexcharts-yaxis text) { fill: var(--cd-tenue) !important; }
.cd-grafica :deep(.apexcharts-legend-text) { color: var(--cd-tinta) !important; }
.cd-grafica-esqueleto { height: 220px; margin: 8px; border-radius: 8px; background: var(--cd-cabecera); }

/* Filtros */
.cd-filtros { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 20px 14px; border-bottom: 1px solid var(--cd-borde); }
.cd-buscar { position: relative; flex: 1 1 240px; max-width: 380px; margin: 0; }
.cd-buscar i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--cd-tenue); font-size: 12px; }
.cd-buscar input { padding-left: 30px; }
.cd-buscar input::placeholder { color: var(--cd-tenue) !important; opacity: 1; }
.cd-interruptor { display: inline-flex; align-items: center; gap: 8px; margin: 0; font-size: 13px; cursor: pointer; white-space: nowrap; }
.cd-interruptor input { width: 32px; height: 18px; appearance: none; border-radius: 999px; background: #c5cad3; position: relative; cursor: pointer; transition: background .15s; flex: none; margin: 0; }
.cd-interruptor input { background: #8d94a0; }
.cd-dark .cd-interruptor input { background: #7a8296; }
.cd-interruptor input::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.25); transition: transform .15s; }
.cd-interruptor input:checked { background: var(--cd-acento); }
.cd-interruptor input:checked::after { transform: translateX(14px); }
.cd-filtros .cd-segmentos { max-width: 100%; overflow-x: auto; scrollbar-width: none; }
.cd-filtros .cd-segmentos::-webkit-scrollbar { display: none; }
.cd-chip {
    display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 999px; padding: 4px 10px 4px 12px;
    background: var(--cd-acento-suave); color: var(--cd-acento); font-size: 12.5px; font-weight: 600; cursor: pointer;
}
.cd-chip i { font-size: 10px; }

/* Tabla */
.cd-tabla { width: 100%; border-collapse: collapse; font-size: 13.5px; color: var(--cd-tinta); min-width: 820px; }
.cd-tabla thead th {
    background: var(--cd-cabecera); color: var(--cd-tenue); font-size: 12px; font-weight: 600;
    padding: 9px 10px; white-space: nowrap; border-bottom: 1px solid var(--cd-borde); text-align: left;
}
.cd-tabla tbody td { padding: 10px; border-bottom: 1px solid var(--cd-borde); vertical-align: middle; height: 58px; }
.cd-tabla tbody tr:last-child td { border-bottom: none; }
.cd-tabla tbody tr:hover td { background: var(--cd-hover); }
.cd-tabla th:first-child, .cd-tabla td:first-child { padding-left: 16px; }
.cd-tabla th:last-child, .cd-tabla td:last-child { padding-right: 20px; }
.cd-tabla .cd-num { text-align: right; }
.cd-col-play { width: 52px; text-align: center; }
.cd-tabla thead .cd-col-play i { font-size: 12px; }
.cd-cargando { opacity: .55; transition: opacity .15s; }
.cd-fila-actual td { background: var(--cd-acento-suave) !important; }
.cd-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.cd-fuerte { font-weight: 600; }
.cd-tenue { color: var(--cd-tenue); font-size: 12px; }
.cd-nowrap { white-space: nowrap; }
.cd-recorte { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.cd-play {
    width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--cd-borde); background: var(--cd-superficie);
    color: var(--cd-acento); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.cd-play .fa-play { margin-left: 2px; }
.cd-play:hover { background: var(--cd-acento-suave); border-color: transparent; }
.cd-play--actual, .cd-play--actual:hover { background: var(--cd-acento); border-color: var(--cd-acento); color: #fff; }
.cd-dark .cd-play--actual { color: #0f1729; }
.cd-sin-play { display: inline-flex; width: 34px; height: 34px; align-items: center; justify-content: center; color: var(--cd-tenue); font-size: 12px; cursor: help; }
.cd-sin-play--aviso { color: #b45309; }
.cd-dark .cd-sin-play--aviso { color: #f6b05a; }

.cd-llamada { display: flex; align-items: center; gap: 10px; }
.cd-numero { font-variant-numeric: tabular-nums; font-weight: 500; }
.cd-dir { width: 28px; height: 28px; border-radius: 50%; flex: none; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
.cd-dir--entrante { background: var(--cd-acento-suave); color: var(--cd-acento); }
.cd-dir--saliente { background: rgba(98,80,214,.12); color: #5b48c9; }
.cd-dir--interna  { background: rgba(107,114,128,.12); color: var(--cd-tenue); }
.cd-dir--campana  { background: rgba(217,119,6,.13); color: #b45309; }
.cd-dark .cd-dir--saliente { color: #b4a9f7; }
.cd-dark .cd-dir--campana  { color: #f6b05a; }

.cd-cliente { min-width: 160px; }
.cd-cliente a {
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    max-width: 200px; overflow-wrap: anywhere; line-height: 1.35; font-weight: 500; color: var(--cd-acento);
    text-decoration: none; text-underline-offset: 2px;
}
.cd-cliente a:hover { text-decoration: underline; }

.cd-persona { display: flex; align-items: center; gap: 10px; min-width: 0; }
.cd-persona > div { min-width: 0; }
.cd-persona-nombre { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px; }
.cd-avatar {
    width: 30px; height: 30px; border-radius: 50%; flex: none; display: inline-flex; align-items: center; justify-content: center;
    background: var(--cd-acento-suave); color: var(--cd-acento); font-size: 11.5px; font-weight: 700;
}
.cd-avatar--ext { background: rgba(107,114,128,.12); color: var(--cd-tenue); font-size: 11px; }
.cd-avatar--bot { background: rgba(27,175,122,.14); color: #15845f; }
.cd-avatar--campana { background: rgba(217,119,6,.13); color: #b45309; }
.cd-dark .cd-avatar--bot { color: #6ee7b7; }
.cd-dark .cd-avatar--campana { color: #f6b05a; }

/* Estado: icono + texto, nunca solo color */
.cd-estado { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.cd-estado i { font-size: 10px; }
.cd-estado--contestada    { background: #dcfce7; color: #166534; }
.cd-estado--asistente     { background: #dbeafe; color: #1e40af; }
.cd-estado--no_atendida   { background: #fee2e2; color: #991b1b; }
.cd-estado--sin_respuesta { background: #f1f5f9; color: #475569; }
.cd-dark .cd-estado--contestada    { background: rgba(34,197,94,.16);  color: #86efac; }
.cd-dark .cd-estado--asistente     { background: rgba(59,130,246,.18); color: #93c5fd; }
.cd-dark .cd-estado--no_atendida   { background: rgba(239,68,68,.18);  color: #fca5a5; }
.cd-dark .cd-estado--sin_respuesta { background: rgba(148,163,184,.16); color: #cbd5e1; }

/* Botones */
.cd-icono {
    width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--cd-borde); background: var(--cd-superficie); flex: none;
    color: var(--cd-tinta); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer;
    text-decoration: none; transition: background .15s, border-color .15s, color .15s;
}
.cd-icono:hover:not(:disabled) { background: var(--cd-acento-suave); border-color: transparent; color: var(--cd-acento); }
.cd-icono:disabled { opacity: .4; cursor: default; }
.cd-boton {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid var(--cd-borde); background: var(--cd-superficie); color: var(--cd-tinta);
    border-radius: 6px; padding: 5px 12px; font-size: 12.5px; font-weight: 500; cursor: pointer;
}
.cd-boton i { font-size: 11px; }
.cd-boton:hover:not(:disabled) { border-color: var(--cd-acento); color: var(--cd-acento); }
.cd-boton:disabled { opacity: .45; cursor: default; }
.cd-boton--suave { background: var(--cd-acento-suave); border-color: transparent; color: var(--cd-acento); }
.cd-enlace { border: 0; background: none; padding: 0; color: inherit; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
/* El tema quita el outline de botones y enlaces con !important: el anillo va con box-shadow. */
.cd :is(button, a, input):focus-visible,
.cd-reproductor :is(button, a, audio):focus-visible {
    outline: none; box-shadow: 0 0 0 2px var(--cd-superficie), 0 0 0 4px var(--cd-acento) !important;
}

/* Vacíos, avisos, esqueletos */
.cd-vacio { text-align: center; color: var(--cd-tenue); padding: 40px 16px !important; height: auto !important; font-size: 13.5px; }
.cd-vacio--grafica { display: flex; align-items: center; justify-content: center; min-height: 220px; padding: 16px !important; }
.cd-aviso {
    display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 10px; margin-bottom: 16px;
    background: #fff7ed; color: #7c2d12; border: 1px solid #fed7aa; font-size: 13.5px;
}
.cd-aviso i { margin-top: 3px; }
.cd-aviso--error { background: #fef2f2; color: #7f1d1d; border-color: #fecaca; }
.cd-aviso--chico { padding: 8px 14px; font-size: 13px; }
.cd-dark .cd-aviso { background: rgba(234,88,12,.12); color: #fdba74; border-color: rgba(234,88,12,.3); }
.cd-dark .cd-aviso--error { background: rgba(239,68,68,.12); color: #fca5a5; border-color: rgba(239,68,68,.3); }
.cd-esqueleto { display: block; border-radius: 6px; background: var(--cd-cabecera); }
.cd-cifra-valor.cd-esqueleto { width: 64px; }
.cd-fila-esqueleto .cd-esqueleto { height: 12px; width: 70%; }
.cd-fila-esqueleto td:first-child .cd-esqueleto { width: 34px; height: 34px; border-radius: 50%; }
@media (prefers-reduced-motion: no-preference) {
    .cd-esqueleto, .cd-grafica-esqueleto { animation: cd-pulso 1.4s ease-in-out infinite; }
}
@keyframes cd-pulso { 50% { opacity: .5; } }

/* Celular: controles a tamaño de dedo */
@media (max-width: 767px) {
    .cd-segmento { padding: 10px 12px; }
    .cd-boton { padding: 10px 14px; }
    .cd-fecha input, .cd-buscar input { min-height: 44px; }
    .cd-play, .cd-icono, .cd-sin-play { width: 44px; height: 44px; }
    .cd-interruptor { min-height: 44px; }
    .cd-tipo { padding: 12px 14px; }
}

/* Pie */
.cd-pie { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 20px; border-top: 1px solid var(--cd-borde); }
.cd-paginas { display: flex; gap: 6px; }

/* Reproductor fijo (va en <body>; left/width los pone colocarReproductor) */
.cd-reproductor {
    position: fixed; z-index: 1040;
    display: grid; grid-template-columns: minmax(170px, 1fr) minmax(260px, 1.5fr) auto; align-items: center; column-gap: 16px; row-gap: 2px;
    padding: 10px 14px 8px; border-radius: 12px; background: var(--cd-superficie); border: 1px solid var(--cd-borde);
    box-shadow: 0 10px 28px rgba(16, 24, 40, .16), 0 2px 6px rgba(16, 24, 40, .08); font-size: 13.5px;
}
.cd-reproductor.cd-dark { background: #323a50; box-shadow: 0 10px 28px rgba(0,0,0,.45); }
@media (prefers-reduced-motion: no-preference) {
    .cd-reproductor { animation: cd-sube .2s cubic-bezier(.16, 1, .3, 1); }
}
@keyframes cd-sube { from { transform: translateY(12px); opacity: 0; } }
.cd-repro-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.cd-repro-texto { min-width: 0; }
.cd-repro-audio { display: flex; align-items: center; gap: 8px; min-width: 0; }
.cd-repro-audio audio { flex: 1; min-width: 0; height: 36px; }
.cd-reproductor.cd-dark audio { color-scheme: dark; }
.cd-repro-acciones { display: flex; align-items: center; gap: 6px; }
.cd-repro-error { grid-column: 1 / -1; font-size: 12.5px; color: #b91c1c; }
.cd-reproductor.cd-dark .cd-repro-error { color: #fca5a5; }
.cd-repro-atajos { grid-column: 2 / 3; font-size: 11.5px; text-align: center; }
@media (max-width: 991px) {
    .cd-reproductor { grid-template-columns: 1fr auto; }
    .cd-repro-audio { grid-column: 1 / -1; grid-row: 2; }
    .cd-repro-atajos { display: none; }
}
</style>
