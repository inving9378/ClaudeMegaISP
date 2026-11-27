<template>
    <div class="cd cp-raiz cp" :class="{ 'cd-dark': darkMode }">
        <!-- Cabecera -->
        <header class="cp-cabecera">
            <div>
                <h1 class="cp-titulo">Campañas</h1>
                <p class="cp-sub">Llamadas automáticas de cobranza, avisos y cortes por la troncal del módulo.</p>
            </div>
            <div class="cp-cabecera-acciones">
                <a class="cp-boton" :href="dashboardUrl">
                    <i class="fa fa-headphones" aria-hidden="true"></i> Llamadas y grabaciones
                </a>
                <button v-if="puedeGestionar" type="button" class="cp-boton cp-boton--primario" @click="abrirNueva()">
                    <i class="fa fa-plus" aria-hidden="true"></i> Nueva campaña
                </button>
            </div>
        </header>

        <!-- Resumen: lo que pasa ahora y lo de hoy -->
        <section class="cp-resumen" aria-label="Resumen">
            <button type="button" class="cp-cifra cp-cifra--accion" :aria-pressed="filtros.estado === 'activa'"
                    @click="filtrarEstado(filtros.estado === 'activa' ? '' : 'activa')">
                <span class="cp-cifra-label">Campañas activas</span>
                <span v-if="kpis" class="cp-cifra-valor">{{ n(kpis.campanas_activas) }}</span>
                <span v-else class="cp-cifra-valor cp-esqueleto"></span>
                <span class="cp-cifra-nota">{{ kpis ? (kpis.campanas_pausadas ? `${n(kpis.campanas_pausadas)} en pausa` : 'ninguna en pausa') : '' }}</span>
            </button>
            <div class="cp-cifra">
                <span class="cp-cifra-label">Marcando ahora</span>
                <span v-if="kpis" class="cp-cifra-valor">{{ n(kpis.en_curso) }}</span>
                <span v-else class="cp-cifra-valor cp-esqueleto"></span>
                <span class="cp-cifra-nota">{{ kpis ? `hasta ${n(kpis.canales)} a la vez por campaña` : '' }}</span>
            </div>
            <div class="cp-cifra">
                <span class="cp-cifra-label">Por marcar</span>
                <span v-if="kpis" class="cp-cifra-valor">{{ n(kpis.por_marcar) }}</span>
                <span v-else class="cp-cifra-valor cp-esqueleto"></span>
                <span class="cp-cifra-nota">{{ kpis ? 'esperando turno en campañas activas' : '' }}</span>
            </div>
            <div class="cp-cifra">
                <span class="cp-cifra-label">Contestadas hoy</span>
                <span v-if="kpis" class="cp-cifra-valor">{{ n(kpis.llamadas_contestadas) }}</span>
                <span v-else class="cp-cifra-valor cp-esqueleto"></span>
                <span class="cp-cifra-nota">{{ notaContestadasHoy }}</span>
            </div>
            <div class="cp-cifra">
                <span class="cp-cifra-label">Ya pagaron</span>
                <span v-if="kpis" class="cp-cifra-valor">{{ n(kpis.pagadas_hoy) }}</span>
                <span v-else class="cp-cifra-valor cp-esqueleto"></span>
                <span class="cp-cifra-nota">{{ kpis ? 'hoy, antes de que se les llamara' : '' }}</span>
            </div>
        </section>

        <!-- Lista -->
        <section class="cp-panel" aria-labelledby="cp-lista-titulo">
            <div class="cp-panel-cabeza">
                <h2 id="cp-lista-titulo" class="cp-panel-titulo">Todas las campañas</h2>
                <span class="cp-tenue" aria-live="polite">{{ textoTotal }}</span>
            </div>

            <div class="cp-filtros">
                <label class="cp-buscar">
                    <span class="visually-hidden">Buscar campaña</span>
                    <i class="fa fa-search" aria-hidden="true"></i>
                    <input v-model="filtros.buscar" type="search" class="cp-campo" placeholder="Buscar por nombre o mensaje"
                           @input="buscarLuego" @keydown.enter.prevent="recargar(1)">
                </label>
                <div class="cp-segmentos" role="group" aria-label="Estado de la campaña">
                    <button v-for="e in SEGMENTOS" :key="e.id || 'todas'" type="button" class="cp-segmento"
                            :aria-pressed="filtros.estado === e.id" @click="filtrarEstado(e.id)">
                        {{ e.label }}<span class="cp-segmento-n">{{ porEstado[e.id || 'todas'] ?? '' }}</span>
                    </button>
                </div>
                <label class="cp-filtro-tipo">
                    <span class="visually-hidden">Tipo de campaña</span>
                    <select v-model="filtros.tipo" class="cp-campo" @change="recargar(1)">
                        <option value="">Todos los tipos</option>
                        <option v-for="(t, id) in TIPOS" :key="id" :value="id">{{ t.label }}</option>
                    </select>
                </label>
                <button type="button" class="cp-boton cp-exportar" :disabled="exportando || !total" @click="exportar">
                    <i class="fa" :class="exportando ? 'fa-spinner fa-spin' : 'fa-file-excel'" aria-hidden="true"></i>
                    {{ exportando ? 'Exportando…' : 'Exportar' }}
                </button>
            </div>

            <div v-if="errorCarga" class="cp-aviso cp-aviso--error cp-aviso--en-panel" role="alert">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <span>{{ errorCarga }} <button type="button" class="cp-enlace" @click="recargarTodo">Reintentar</button></span>
            </div>

            <div class="cp-tabla-caja" :class="{ 'cp-cargando': cargando && campanas.length }" :aria-busy="cargando">
                <table class="cp-tabla">
                    <caption class="visually-hidden">Campañas: estado, horario, avance y contestadas</caption>
                    <thead>
                        <tr>
                            <th scope="col" :aria-sort="ariaOrden('nombre')">
                                <button type="button" class="cp-orden" @click="ordenar('nombre')">Campaña <i class="fa" :class="iconoOrden('nombre')" aria-hidden="true"></i></button>
                            </th>
                            <th scope="col" :aria-sort="ariaOrden('estado')">
                                <button type="button" class="cp-orden" @click="ordenar('estado')">Estado <i class="fa" :class="iconoOrden('estado')" aria-hidden="true"></i></button>
                            </th>
                            <th scope="col">Cuándo llama</th>
                            <th scope="col" :aria-sort="ariaOrden('llamadas')">
                                <button type="button" class="cp-orden" @click="ordenar('llamadas')">Avance <i class="fa" :class="iconoOrden('llamadas')" aria-hidden="true"></i></button>
                            </th>
                            <th scope="col" class="cp-num" :aria-sort="ariaOrden('contestadas')">
                                <button type="button" class="cp-orden" @click="ordenar('contestadas')">Contestadas <i class="fa" :class="iconoOrden('contestadas')" aria-hidden="true"></i></button>
                            </th>
                            <th scope="col" class="cp-td-acciones"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="cargando && !campanas.length">
                            <tr v-for="i in 5" :key="'esq' + i" class="cp-fila-esqueleto" aria-hidden="true">
                                <td class="cp-td-campana"><span class="cp-esqueleto" style="width: 60%"></span><span class="cp-esqueleto cp-esqueleto--chico"></span></td>
                                <td class="cp-td-estado"><span class="cp-esqueleto" style="width: 76px"></span></td>
                                <td class="cp-td-cuando"><span class="cp-esqueleto" style="width: 88px"></span></td>
                                <td class="cp-td-avance"><span class="cp-esqueleto" style="width: 120px"></span></td>
                                <td class="cp-td-contestadas"><span class="cp-esqueleto" style="width: 40px; margin-left: auto"></span></td>
                                <td class="cp-td-acciones"></td>
                            </tr>
                        </template>
                        <tr v-else-if="!campanas.length && !errorCarga" class="cp-fila-vacia">
                            <td colspan="6" class="cp-vacio">
                                <template v-if="hayFiltros">
                                    Ninguna campaña coincide con lo que buscas.
                                    <button type="button" class="cp-enlace" @click="quitarFiltros">Quitar filtros</button>
                                </template>
                                <template v-else>
                                    <strong>Todavía no hay campañas.</strong>
                                    <span class="cp-vacio-texto">Eliges el mensaje, a quién y cuándo; revisas la lista de clientes y la activas.</span>
                                    <button v-if="puedeGestionar" type="button" class="cp-boton cp-boton--primario" @click="abrirNueva()">
                                        <i class="fa fa-plus" aria-hidden="true"></i> Crear la primera campaña
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-for="c in campanas" :key="c.id">
                            <td class="cp-td-campana">
                                <button type="button" class="cp-nombre" @click="abrirDetalle(c)">{{ c.nombre }}</button>
                                <div class="cp-meta">
                                    <span class="cp-tipo"><i class="fa" :class="tipoInfo(c.tipo).icono" aria-hidden="true"></i>{{ tipoInfo(c.tipo).label }}</span>
                                    <span v-if="c.notas" class="cp-nota" :title="c.notas">{{ c.notas }}</span>
                                </div>
                            </td>
                            <td class="cp-td-estado">
                                <span class="cp-estado" :class="`cp-estado--${c.estado}`">
                                    <i class="fa" :class="estadoInfo(c.estado).icono" aria-hidden="true"></i>{{ estadoInfo(c.estado).label }}
                                </span>
                            </td>
                            <td class="cp-td-cuando">
                                <div class="cp-num">{{ horario(c) }}</div>
                                <div class="cp-tenue">{{ periodo(c) }}</div>
                            </td>
                            <td class="cp-td-avance">
                                <div class="cp-avance">
                                    <span class="cp-barra" aria-hidden="true"><span :style="{ width: pctAvance(c) + '%' }"></span></span>
                                    <span class="cp-num">{{ n(hechas(c)) }} de {{ n(c.total_llamadas) }}</span>
                                </div>
                                <div class="cp-tenue" :class="{ 'cp-vivo': +c.marcando > 0 }">{{ notaAvance(c) }}</div>
                            </td>
                            <td class="cp-td-contestadas cp-num">
                                <span class="cp-fuerte">{{ n(c.contestadas) }}</span>
                                <span class="cp-tenue cp-pct">{{ pctContestadas(c) }}</span>
                                <span class="cp-solo-movil cp-tenue cp-pct">contestadas</span>
                                <div v-if="c.solo_deudores && +c.pagadas" class="cp-tenue">{{ n(c.pagadas) }} ya pagaron</div>
                            </td>
                            <td class="cp-td-acciones">
                                <div class="cp-acciones">
                                    <button v-if="accion(c)" type="button" class="cp-boton cp-accion" :class="`cp-accion--${accion(c).tipo}`"
                                            :disabled="procesando === c.id" @click="ejecutar(c)">
                                        <i class="fa" :class="procesando === c.id ? 'fa-spinner fa-spin' : accion(c).icono" aria-hidden="true"></i>{{ accion(c).label }}
                                    </button>
                                    <span v-else class="cp-hueco-accion" aria-hidden="true"></span>
                                    <button type="button" class="cp-icono" title="Ver llamadas" :aria-label="`Ver las llamadas de ${c.nombre}`" @click="abrirDetalle(c)">
                                        <i class="fa fa-list-ul" aria-hidden="true"></i>
                                    </button>
                                    <button v-if="puedeGestionar" type="button" class="cp-icono" title="Duplicar" :aria-label="`Duplicar ${c.nombre}`" @click="abrirNueva(c)">
                                        <i class="fa fa-copy" aria-hidden="true"></i>
                                    </button>
                                    <button v-if="puedeGestionar && c.estado === 'borrador'" type="button" class="cp-icono cp-icono--peligro"
                                            title="Eliminar" :aria-label="`Eliminar ${c.nombre}`" @click="eliminar(c)">
                                        <i class="fa fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                    <span v-else-if="puedeGestionar" class="cp-hueco-icono" aria-hidden="true"></span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer v-if="total > 0" class="cp-pie">
                <span class="cp-tenue cp-num">{{ textoRango }}</span>
                <div class="cp-paginacion">
                    <label class="cp-por-pagina">
                        <span>Por página</span>
                        <select v-model.number="filtros.por_pagina" class="cp-campo" @change="recargar(1)">
                            <option v-for="p in [10, 25, 50, 100]" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </label>
                    <button type="button" class="cp-icono" :disabled="pagina <= 1" aria-label="Página anterior" @click="recargar(pagina - 1)">
                        <i class="fa fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <span class="cp-num cp-tenue">{{ pagina }} de {{ ultimaPagina }}</span>
                    <button type="button" class="cp-icono" :disabled="pagina >= ultimaPagina" aria-label="Página siguiente" @click="recargar(pagina + 1)">
                        <i class="fa fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </footer>
        </section>

        <!-- ── Nueva campaña (80% de la pantalla, pedido de Irving) ─────────────────── -->
        <campanas-dialogo :abierto="dialogo.nueva" variante="ancho" :oscuro="darkMode"
                          :titulo="duplicandoDe ? `Duplicar «${duplicandoDe}»` : 'Nueva campaña'"
                          descripcion="El mensaje, a quién y cuándo. En el siguiente paso revisas la lista de clientes antes de llamar."
                          @cerrar="cerrarNueva">
            <form class="cp-form" novalidate @submit.prevent="puedeSiguiente && crearCampana(true)">
                <div class="cp-form-col">
                    <fieldset class="cp-seccion">
                        <legend class="cp-seccion-titulo">Mensaje</legend>
                        <div class="cp-nombre-motivo">
                            <div class="cp-campo-grupo">
                                <label for="cp-n-nombre" class="cp-etiqueta">Nombre</label>
                                <input id="cp-n-nombre" v-model="nueva.nombre" data-autofoco class="cp-campo" maxlength="255"
                                       :aria-invalid="!!erroresNueva.nombre" aria-describedby="cp-n-nombre-error"
                                       placeholder="Ej. Cobranza de noviembre, Mantenimiento en la zona 54">
                                <p v-if="erroresNueva.nombre" id="cp-n-nombre-error" class="cp-error">{{ primerError('nombre') }}</p>
                            </div>
                            <!-- El motivo es solo la etiqueta de la campaña (y «Corte» pide su permiso para llamar);
                                 a quién se llama lo deciden los filtros de la derecha (27-nov). -->
                            <div class="cp-campo-grupo">
                                <label for="cp-n-motivo" class="cp-etiqueta">Motivo</label>
                                <select id="cp-n-motivo" v-model="nueva.tipo" class="cp-campo" aria-describedby="cp-n-motivo-ayuda" @change="alCambiarMotivo">
                                    <option v-for="(t, id) in TIPOS" :key="id" :value="id">{{ t.label }}</option>
                                </select>
                                <p v-if="!puedeLanzarTipo(nueva.tipo)" id="cp-n-motivo-ayuda" class="cp-ayuda">
                                    Puedes guardarla como borrador; activarla pide el permiso de corte de servicio.
                                </p>
                            </div>
                        </div>

                        <div class="cp-campo-grupo">
                            <label for="cp-n-nota" class="cp-etiqueta">
                                Lo que escucha el cliente<span v-if="nueva.solo_deudores" class="cp-opcional"> · vacío = el mensaje de cobranza de siempre</span>
                            </label>
                            <textarea id="cp-n-nota" v-model="nueva.notas" class="cp-campo" rows="4" maxlength="2000"
                                      :aria-invalid="!!erroresNueva.notas" aria-describedby="cp-n-nota-ayuda cp-n-nota-error"
                                      :placeholder="nueva.solo_deudores ? 'Vacío = el mensaje de cobranza de siempre'
                                          : 'Ej. Le informamos que el jueves de 10:00 a 12:00 habrá mantenimiento en su zona…'"></textarea>
                            <p v-if="erroresNueva.notas" id="cp-n-nota-error" class="cp-error">{{ primerError('notas') }}</p>
                            <p v-else-if="notaConVariables" id="cp-n-nota-error" class="cp-error" role="alert">
                                Sin «Solo los que deben» el mensaje es el mismo para todos: quita {nombre}, {saldo}, {fecha} y {numero_cliente}.
                            </p>
                            <p v-if="nueva.solo_deudores" id="cp-n-nota-ayuda" class="cp-ayuda">
                                Puedes usar <code>{nombre}</code> <code>{saldo}</code> <code>{fecha}</code> <code>{numero_cliente}</code>:
                                en cada llamada se cambian por los datos del cliente.
                            </p>
                        </div>

                        <div class="cp-voz">
                            <div class="cp-voz-cabeza">
                                <span class="cp-etiqueta cp-etiqueta--sin-margen"><i class="fa fa-microphone" aria-hidden="true"></i> Voz de la llamada</span>
                                <button type="button" class="cp-boton" :class="{ 'cp-boton--peligro': reproduciendo }"
                                        :disabled="!reproduciendo && (generandoMuestra || !vozOpciones.asignada || !textoNota)"
                                        @click="reproduciendo ? $refs.muestra.pause() : escucharNota()">
                                    <i class="fa" :class="reproduciendo ? 'fa-stop' : (generandoMuestra ? 'fa-spinner fa-spin' : 'fa-play')" aria-hidden="true"></i>
                                    {{ reproduciendo ? 'Detener' : (generandoMuestra ? 'Generando…' : 'Escuchar') }}
                                </button>
                            </div>
                            <p v-if="vozCargada && !vozOpciones.asignada" class="cp-aviso cp-aviso--chico">
                                {{ vozOpciones.motivo || 'No hay voz asignada en Integraciones → Módulos IA.' }}
                            </p>
                            <p v-if="errorMuestra" class="cp-aviso cp-aviso--error cp-aviso--chico" role="alert">{{ errorMuestra }}</p>
                            <!-- La voz generada queda en un reproductor: se vuelve a oír sin generarla otra vez, y si el
                                 navegador no deja que suene sola, aquí se le da ▶. -->
                            <audio ref="muestra" class="cp-voz-audio" controls preload="auto" v-show="urlMuestra"
                                   @play="reproduciendo = !!urlMuestra" @pause="reproduciendo = false" @ended="reproduciendo = false"></audio>
                            <p v-if="avisoAutoplay" class="cp-ayuda">Tu navegador no dejó que sonara sola: dale <i class="fa fa-play" aria-hidden="true"></i> en el reproductor.</p>
                            <div class="cp-voz-campos">
                                <div class="cp-campo-grupo">
                                    <label for="cp-n-voz" class="cp-etiqueta">Voz</label>
                                    <select id="cp-n-voz" v-model="nueva.voz" class="cp-campo">
                                        <option v-for="v in vozOpciones.voces" :key="v.id" :value="v.id">{{ v.etiqueta }}</option>
                                    </select>
                                </div>
                                <div class="cp-campo-grupo">
                                    <label for="cp-n-velocidad" class="cp-etiqueta">Velocidad <strong class="cp-num">{{ velocidadEtiqueta }}</strong></label>
                                    <input id="cp-n-velocidad" v-model.number="nueva.voz_velocidad" type="range" class="cp-rango" step="0.05"
                                           :min="vozOpciones.velocidad.min" :max="vozOpciones.velocidad.max" :aria-valuetext="velocidadEtiqueta">
                                    <div class="cp-rango-extremos" aria-hidden="true"><span>Lenta</span><span>Rápida</span></div>
                                </div>
                                <div class="cp-campo-grupo">
                                    <label for="cp-n-tono" class="cp-etiqueta">Tono</label>
                                    <select id="cp-n-tono" v-model="nueva.voz_tono" class="cp-campo" :disabled="!vozOpciones.soporta_tono"
                                            aria-describedby="cp-n-tono-ayuda">
                                        <option value="">Natural</option>
                                        <option v-for="t in vozOpciones.tonos" :key="t.id" :value="t.id">{{ t.etiqueta }}</option>
                                    </select>
                                    <p v-if="vozCargada && !vozOpciones.soporta_tono" id="cp-n-tono-ayuda" class="cp-ayuda">
                                        Se activa con el modelo gpt-4o-mini-tts en Integraciones → Módulos IA.
                                    </p>
                                </div>
                            </div>
                            <p v-if="nueva.solo_deudores" class="cp-ayuda">Se escucha con un cliente de ejemplo: Juan Pérez, saldo de $350.00.</p>
                            <p v-for="campo in ['voz', 'voz_velocidad', 'voz_tono']" v-show="erroresNueva[campo]" :key="campo" class="cp-error">{{ primerError(campo) }}</p>
                        </div>
                    </fieldset>

                    <fieldset class="cp-seccion">
                        <legend class="cp-seccion-titulo">Cuándo llamar</legend>
                        <div class="cp-par">
                            <div class="cp-campo-grupo">
                                <label for="cp-n-desde" class="cp-etiqueta">Desde el día</label>
                                <input id="cp-n-desde" v-model="nueva.fecha_inicio" type="date" class="cp-campo">
                            </div>
                            <div class="cp-campo-grupo">
                                <label for="cp-n-hasta" class="cp-etiqueta">Hasta el día</label>
                                <input id="cp-n-hasta" v-model="nueva.fecha_fin" type="date" class="cp-campo" :aria-invalid="!!erroresNueva.fecha_fin">
                            </div>
                        </div>
                        <p class="cp-ayuda">Sin fechas: empieza al activarla y termina cuando se acaba la lista.</p>
                        <div class="cp-par">
                            <div class="cp-campo-grupo">
                                <label for="cp-n-hora-inicio" class="cp-etiqueta">De las</label>
                                <input id="cp-n-hora-inicio" v-model="nueva.hora_inicio" type="time" class="cp-campo">
                            </div>
                            <div class="cp-campo-grupo">
                                <label for="cp-n-hora-fin" class="cp-etiqueta">A las</label>
                                <input id="cp-n-hora-fin" v-model="nueva.hora_fin" type="time" class="cp-campo">
                            </div>
                        </div>
                    </fieldset>

                    <details class="cp-seccion cp-mas" :open="limitesAbiertos" @toggle="limitesAbiertos = $event.target.open">
                        <summary class="cp-seccion-titulo cp-mas-resumen">
                            <span>Reintentos y límites</span>
                            <span class="cp-tenue cp-mas-dato">{{ resumenLimites }}</span>
                        </summary>
                        <div class="cp-par">
                            <div class="cp-campo-grupo">
                                <label for="cp-n-intentos" class="cp-etiqueta">Intentos por cliente</label>
                                <input id="cp-n-intentos" v-model.number="nueva.max_intentos" type="number" min="1" max="10" class="cp-campo">
                            </div>
                            <div class="cp-campo-grupo">
                                <label for="cp-n-minutos" class="cp-etiqueta">Minutos entre intentos</label>
                                <input id="cp-n-minutos" v-model.number="nueva.minutos_entre_intentos" type="number" min="30" step="30" class="cp-campo">
                            </div>
                        </div>
                        <div class="cp-par">
                            <div class="cp-campo-grupo">
                                <label for="cp-n-canales" class="cp-etiqueta">Llamadas a la vez</label>
                                <input id="cp-n-canales" v-model.number="nueva.max_canales_simultaneos" type="number" min="1" max="100" class="cp-campo"
                                       :placeholder="`${canalesDefecto} (por omisión)`">
                            </div>
                        </div>
                    </details>
                </div>

                <div class="cp-form-col cp-form-col--lado">
                    <fieldset class="cp-seccion">
                        <legend class="cp-seccion-titulo">A quién se le llama</legend>
                        <campanas-audiencia id-base="cp-n"
                                            v-model:estados="nueva.audiencia.estados" v-model:zona="nueva.audiencia.zona"
                                            v-model:caja="nueva.audiencia.caja" v-model:solo-deudores="nueva.solo_deudores"
                                            v-model:dias="nueva.dias_vencimiento"
                                            :resumen="audNueva.resumen" :cargando="audNueva.cargando" />
                        <div v-if="nueva.audiencia.estados.length" class="cp-cuenta" aria-live="polite">
                            <p v-if="audNueva.error" class="cp-error cp-sin-margen">
                                {{ audNueva.error }} <button type="button" class="cp-enlace" @click="contarNueva(true)">Reintentar</button>
                            </p>
                            <template v-else-if="audNueva.resumen">
                                <p class="cp-cuenta-total">
                                    <strong class="cp-num">{{ n(audNueva.resumen.clientes_n) }}</strong>
                                    {{ audNueva.resumen.clientes_n === 1 ? 'cliente entra' : 'clientes entran' }} con estos filtros
                                </p>
                                <p v-for="(nota, i) in notasAudiencia(audNueva.resumen, nueva.audiencia)" :key="i" class="cp-cuenta-nota">{{ nota }}</p>
                            </template>
                            <p v-else class="cp-tenue cp-sin-margen">Contando clientes…</p>
                        </div>
                    </fieldset>

                    <div v-if="otrosErrores.length" class="cp-aviso cp-aviso--error" role="alert">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        <div><p v-for="(m, i) in otrosErrores" :key="i" class="cp-sin-margen">{{ m }}</p></div>
                    </div>
                </div>
            </form>
            <template #pie>
                <button type="button" class="cp-boton" @click="cerrarNueva">Cancelar</button>
                <button type="button" class="cp-boton" :disabled="creando" @click="crearCampana(false)">Guardar como borrador</button>
                <button v-if="puedeLanzarTipo(nueva.tipo)" type="button" class="cp-boton cp-boton--primario"
                        :disabled="!puedeSiguiente"
                        @click="crearCampana(true)">
                    <i class="fa" :class="creando ? 'fa-spinner fa-spin' : 'fa-arrow-right'" aria-hidden="true"></i>
                    {{ textoSiguiente }}
                </button>
            </template>
        </campanas-dialogo>

        <!-- ── Revisar y llamar: la lista de clientes, para quitar a quien no se le debe llamar (27-nov) ── -->
        <campanas-dialogo :abierto="dialogo.activar" variante="ancho" :oscuro="darkMode"
                          :titulo="activar.campana ? `Revisar a quién se le llama · «${activar.campana.nombre}»` : 'Revisar a quién se le llama'"
                          descripcion="Todos empiezan marcados. Quita a quien no quieras llamar: solo se llama a los que dejes marcados."
                          @cerrar="dialogo.activar = false">
            <div v-if="activar.campana" class="cp-revisar">
                <section class="cp-revisar-lista" aria-label="Clientes a los que se llama">
                    <div class="cp-revisar-filtros">
                        <div class="cp-revisar-quien">
                            <span class="cp-revisar-quien-etiqueta">A quién</span>
                            <span class="cp-revisar-quien-texto">{{ resumenActivar }}</span>
                            <button type="button" class="cp-boton cp-boton--chico" :aria-expanded="activar.editando ? 'true' : 'false'"
                                    aria-controls="cp-a-filtros" @click="activar.editando = !activar.editando">
                                <i class="fa" :class="activar.editando ? 'fa-check' : 'fa-sliders-h'" aria-hidden="true"></i>
                                {{ activar.editando ? 'Listo' : 'Cambiar' }}
                            </button>
                        </div>
                        <div v-if="activar.editando" id="cp-a-filtros" class="cp-revisar-editor">
                            <campanas-audiencia id-base="cp-a" compacta
                                                v-model:estados="activar.filtros.estados" v-model:zona="activar.filtros.zona"
                                                v-model:caja="activar.filtros.caja" v-model:solo-deudores="activar.soloDeudores"
                                                v-model:dias="activar.dias"
                                                :resumen="activar.resumen" :cargando="activar.cargando" />
                        </div>
                    </div>

                    <div class="cp-revisar-barra">
                        <label class="cp-buscar">
                            <i class="fa fa-search" aria-hidden="true"></i>
                            <input v-model="activar.buscar" data-autofoco type="search" class="cp-campo" autocomplete="off"
                                   placeholder="Buscar por nombre, número, teléfono o caja" aria-label="Buscar en la lista">
                        </label>
                        <div class="cp-segmentos" role="group" aria-label="Qué clientes ver">
                            <button v-for="sg in segmentosRevision" :key="sg.id" type="button" class="cp-segmento"
                                    :aria-pressed="activar.vista === sg.id ? 'true' : 'false'" @click="verRevision(sg.id)">
                                {{ sg.label }}<span v-if="activar.resumen" class="cp-segmento-n">{{ n(sg.n) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Con una lista ya a la vista que no se pudo actualizar: se dice arriba (la de abajo es la anterior). -->
                    <p v-if="activar.errorLista && activar.clientes.length" class="cp-aviso cp-aviso--error cp-aviso--chico" role="alert">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        <span>{{ activar.errorLista }} La lista de abajo es la anterior.
                            <button type="button" class="cp-enlace" @click="cargarRevision(true)">Reintentar</button></span>
                    </p>

                    <div class="cp-tabla-caja cp-tabla-caja--borde" :class="{ 'cp-cargando': activar.cargando && activar.clientes.length }" :aria-busy="activar.cargando">
                        <table class="cp-tabla cp-tabla--revisar">
                            <thead>
                                <tr>
                                    <th scope="col" class="cp-r-marca">
                                        <label class="cp-r-todos">
                                            <input type="checkbox" :checked="todosMarcados" :indeterminate.prop="algunosMarcados"
                                                   :disabled="!filtradosRevision.length" @change="marcarTodos($event.target.checked)"
                                                   :aria-label="activar.buscar.trim() ? 'Marcar o quitar a todos los de esta búsqueda' : 'Marcar o quitar a todos'">
                                            <span class="cp-r-todos-texto" aria-hidden="true">{{ activar.buscar.trim() ? 'Todos los de esta búsqueda' : 'Todos' }}</span>
                                        </label>
                                    </th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Teléfono</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Caja</th>
                                    <th v-if="activar.vistaDeudores" scope="col" class="cp-num">Saldo vencido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="activar.cargando && !activar.clientes.length">
                                    <tr v-for="i in 6" :key="`e${i}`" class="cp-fila-esqueleto">
                                        <td class="cp-r-marca"><span class="cp-esqueleto" style="width: 15px"></span></td>
                                        <td v-for="j in (activar.vistaDeudores ? 5 : 4)" :key="j"><span class="cp-esqueleto" :style="{ width: j === 1 ? '60%' : '70%' }"></span></td>
                                    </tr>
                                </template>
                                <tr v-else-if="!filtradosRevision.length" class="cp-fila-vacia">
                                    <td :colspan="activar.vistaDeudores ? 6 : 5" class="cp-vacio">
                                        <template v-if="activar.buscar.trim()">
                                            <strong>Nadie coincide con «{{ activar.buscar.trim() }}»</strong>
                                            <button type="button" class="cp-enlace" @click="activar.buscar = ''">Quitar la búsqueda</button>
                                        </template>
                                        <template v-else-if="activar.vista === 'quitados'">
                                            <strong>No has quitado a nadie</strong>
                                            <span class="cp-vacio-texto">Se le llama a toda la lista.</span>
                                        </template>
                                        <template v-else-if="!activar.filtros.estados.length">
                                            <strong>Marca al menos un estado del cliente</strong>
                                            <span class="cp-vacio-texto">Activos, Bloqueados o Inactivos: con eso se arma la lista.</span>
                                        </template>
                                        <template v-else-if="activar.errorLista">
                                            <strong role="alert">No se pudo armar la lista</strong>
                                            <span class="cp-vacio-texto">{{ activar.errorLista }}</span>
                                            <button type="button" class="cp-boton" @click="cargarRevision(true)">
                                                <i class="fa fa-redo" aria-hidden="true"></i> Reintentar
                                            </button>
                                        </template>
                                        <template v-else-if="activar.resumen">
                                            <strong>Nadie entra con estos filtros</strong>
                                            <span class="cp-vacio-texto">Marca otro estado o elige otra zona.</span>
                                            <button v-if="!activar.editando" type="button" class="cp-enlace" @click="activar.editando = true">Cambiar los filtros</button>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-for="c in paginaRevision" :key="c.id" :class="{ 'cp-fila-quitada': quitado(c.id) }">
                                    <td class="cp-r-marca">
                                        <input :id="`cp-r-${c.id}`" type="checkbox" :checked="!quitado(c.id)" @change="alternarCliente(c.id)">
                                    </td>
                                    <td class="cp-r-cliente">
                                        <div class="cp-r-cliente-linea">
                                            <label :for="`cp-r-${c.id}`" class="cp-r-nombre">{{ c.nombre }}</label>
                                            <a :href="`/cliente/editar/${c.id}`" target="_blank" rel="noopener" class="cp-r-numero cp-num"
                                               :aria-label="`Abrir la ficha del cliente ${c.id} en otra pestaña`">#{{ c.id }}</a>
                                        </div>
                                        <span v-if="quitado(c.id)" class="cp-r-quitado"><i class="fa fa-phone-slash" aria-hidden="true"></i> No se le llama</span>
                                    </td>
                                    <td class="cp-r-telefono cp-num">{{ telefono(c.telefono) }}</td>
                                    <td class="cp-r-estado">
                                        <span class="cp-estado" :class="ESTADO_CLIENTE_CLASE[c.estado]">{{ c.estado }}</span>
                                    </td>
                                    <td class="cp-r-caja cp-num" :class="{ 'cp-tenue': !c.nomenclatura }">{{ c.nomenclatura || 'Sin caja' }}</td>
                                    <td v-if="activar.vistaDeudores" class="cp-r-saldo cp-num">{{ dinero(c.monto) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <footer v-if="filtradosRevision.length > POR_PAGINA_REVISION" class="cp-pie cp-pie--detalle">
                        <span class="cp-tenue cp-num">{{ n((activar.pagina - 1) * POR_PAGINA_REVISION + 1) }}–{{ n(Math.min(activar.pagina * POR_PAGINA_REVISION, filtradosRevision.length)) }} de {{ n(filtradosRevision.length) }}</span>
                        <div class="cp-paginacion">
                            <button type="button" class="cp-icono" :disabled="activar.pagina <= 1" aria-label="Página anterior" @click="activar.pagina--">
                                <i class="fa fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <span class="cp-num cp-tenue">{{ activar.pagina }} de {{ paginasRevision }}</span>
                            <button type="button" class="cp-icono" :disabled="activar.pagina >= paginasRevision" aria-label="Página siguiente" @click="activar.pagina++">
                                <i class="fa fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </footer>
                </section>

                <aside class="cp-revisar-lado" aria-label="Resumen de la campaña">
                    <div class="cp-alcance" aria-live="polite">
                        <template v-if="activar.cargando && !activar.resumen">
                            <span class="cp-esqueleto cp-esqueleto--grande"></span>
                            <span class="cp-tenue">Armando la lista…</span>
                        </template>
                        <template v-else-if="!activar.filtros.estados.length">
                            <span class="cp-alcance-numero cp-num">0</span>
                            <span class="cp-alcance-texto">Marca al menos un estado del cliente</span>
                        </template>
                        <template v-else-if="!activar.resumen">
                            <span class="cp-alcance-numero cp-num" aria-hidden="true">—</span>
                            <span class="cp-alcance-texto">Sin lista todavía</span>
                        </template>
                        <template v-else-if="activar.resumen">
                            <span class="cp-alcance-numero cp-num">{{ n(aLlamar) }}</span>
                            <span class="cp-alcance-texto">{{ aLlamar === 1 ? 'cliente recibirá la llamada' : 'clientes recibirán la llamada' }}</span>
                            <span v-if="activar.vistaDeudores && activar.resumen.clientes_n" class="cp-tenue cp-num">Adeudo vencido de la lista: {{ dinero(montoALlamar) }}</span>
                            <span v-if="quitadosEnLista" class="cp-alcance-nota">
                                <i class="fa fa-user-minus" aria-hidden="true"></i>
                                {{ n(quitadosEnLista) }} {{ quitadosEnLista === 1 ? 'quitado' : 'quitados' }} de la lista ·
                                <button type="button" class="cp-enlace" @click="verRevision('quitados')">ver</button>
                            </span>
                            <span v-for="(nota, i) in notasAudiencia(activar.resumen, activar.filtros)" :key="i" class="cp-alcance-nota">
                                <i class="fa fa-info-circle" aria-hidden="true"></i> {{ nota }}
                            </span>
                        </template>
                    </div>

                    <p v-if="activar.error" class="cp-aviso cp-aviso--error cp-aviso--chico" role="alert">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ activar.error }}</span>
                    </p>

                    <dl class="cp-ficha">
                        <div><dt>Motivo</dt><dd>{{ tipoInfo(activar.campana.tipo).label }}</dd></div>
                        <div><dt>Mensaje</dt><dd class="cp-cita">{{ activar.campana.notas || 'El mensaje de cobranza de siempre, con el nombre y saldo de cada cliente.' }}</dd></div>
                        <div><dt>Cuándo</dt><dd>{{ horario(activar.campana) }} · {{ periodo(activar.campana) }}</dd></div>
                        <div><dt>Voz</dt><dd>{{ vozTexto(activar.campana) }}</dd></div>
                        <div><dt>Reintentos</dt><dd>Hasta {{ activar.campana.max_intentos }} por cliente, cada {{ activar.campana.minutos_entre_intentos }} min · {{ activar.campana.max_canales_simultaneos || canalesDefecto }} a la vez</dd></div>
                    </dl>
                    <p v-if="activar.campana.tipo === 'corte'" class="cp-aviso cp-aviso--chico">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Es un aviso de corte de servicio.
                    </p>
                    <p v-if="sinMensajeFijo" class="cp-aviso cp-aviso--error cp-aviso--chico" role="alert">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        <span>El mensaje de esta campaña dice el nombre o el saldo de cada cliente: solo sirve con «Solo los que deben».
                            Vuelve a marcarla, o crea la campaña con un mensaje igual para todos.</span>
                    </p>
                </aside>
            </div>
            <template #pie>
                <button type="button" class="cp-boton" @click="dialogo.activar = false">Cancelar</button>
                <button type="button" class="cp-boton cp-boton--primario" :disabled="!puedeConfirmarActivacion" @click="confirmarActivacion">
                    <i class="fa" :class="activar.enviando ? 'fa-spinner fa-spin' : 'fa-phone-alt'" aria-hidden="true"></i>
                    {{ textoBotonActivar }}
                </button>
            </template>
        </campanas-dialogo>

        <!-- ── Detalle de la campaña y sus llamadas ─────────────────────────────────── -->
        <campanas-dialogo :abierto="dialogo.detalle" variante="cajon" :oscuro="darkMode"
                          :titulo="detalle.campana ? detalle.campana.nombre : ''" @cerrar="dialogo.detalle = false">
            <template v-if="detalle.campana">
                <div class="cp-detalle-cabeza">
                    <span class="cp-estado" :class="`cp-estado--${detalle.campana.estado}`">
                        <i class="fa" :class="estadoInfo(detalle.campana.estado).icono" aria-hidden="true"></i>{{ estadoInfo(detalle.campana.estado).label }}
                    </span>
                    <span class="cp-tipo"><i class="fa" :class="tipoInfo(detalle.campana.tipo).icono" aria-hidden="true"></i>{{ tipoInfo(detalle.campana.tipo).label }}</span>
                </div>
                <dl class="cp-ficha cp-ficha--rejilla">
                    <div><dt>Cuándo</dt><dd>{{ horario(detalle.campana) }} · {{ periodo(detalle.campana) }}</dd></div>
                    <div><dt>Voz</dt><dd>{{ vozTexto(detalle.campana) }}</dd></div>
                    <div><dt>Reintentos</dt><dd>Hasta {{ detalle.campana.max_intentos }}, cada {{ detalle.campana.minutos_entre_intentos }} min</dd></div>
                    <div v-if="detalle.campana.audiencia" class="cp-ficha-ancha"><dt>A quién</dt><dd>{{ resumenAudiencia(filtrosDe(detalle.campana), detalle.campana.solo_deudores, detalle.campana.dias_vencimiento) }}<template v-if="detalle.campana.audiencia.quitados_n"> · {{ n(detalle.campana.audiencia.quitados_n) }} {{ detalle.campana.audiencia.quitados_n === 1 ? 'quitado' : 'quitados' }} de la lista</template></dd></div>
                    <div class="cp-ficha-ancha"><dt>Mensaje</dt><dd class="cp-cita">{{ detalle.campana.notas || 'El mensaje de cobranza de siempre, con el nombre y saldo de cada cliente.' }}</dd></div>
                </dl>

                <div class="cp-segmentos cp-segmentos--detalle" role="group" aria-label="Filtrar llamadas por estado">
                    <button v-for="e in segmentosLlamadas" :key="e.id || 'todas'" type="button" class="cp-segmento"
                            :aria-pressed="detalle.estado === e.id" @click="filtrarLlamadas(e.id)">
                        {{ e.label }}<span class="cp-segmento-n">{{ n(e.n) }}</span>
                    </button>
                </div>

                <p v-if="detalle.error" class="cp-aviso cp-aviso--error cp-aviso--chico" role="alert">
                    {{ detalle.error }} <button type="button" class="cp-enlace" @click="cargarLlamadas(detalle.pagina)">Reintentar</button>
                </p>
                <div class="cp-tabla-caja cp-tabla-caja--borde" :class="{ 'cp-cargando': detalle.cargando && detalle.llamadas.length }" :aria-busy="detalle.cargando">
                    <table class="cp-tabla cp-tabla--llamadas">
                        <caption class="visually-hidden">Llamadas de la campaña</caption>
                        <thead>
                            <tr>
                                <th scope="col">Cliente</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="cp-num">Intentos</th>
                                <th scope="col">Último intento</th>
                                <th v-if="detalle.campana.solo_deudores" scope="col" class="cp-num">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="detalle.cargando && !detalle.llamadas.length">
                                <tr v-for="i in 4" :key="'el' + i" class="cp-fila-esqueleto" aria-hidden="true">
                                    <td v-for="j in (detalle.campana.solo_deudores ? 6 : 5)" :key="j"><span class="cp-esqueleto" style="width: 70%"></span></td>
                                </tr>
                            </template>
                            <tr v-else-if="!detalle.llamadas.length && !detalle.error">
                                <td :colspan="detalle.campana.solo_deudores ? 6 : 5" class="cp-vacio">
                                    {{ detalle.estado ? 'No hay llamadas en este estado.' : (detalle.campana.estado === 'borrador' ? 'Todavía no tiene llamadas: se cargan al activarla.' : 'Esta campaña no tiene llamadas.') }}
                                </td>
                            </tr>
                            <tr v-for="l in detalle.llamadas" :key="l.id">
                                <td class="cp-ll-cliente">
                                    <a :href="`/cliente/editar/${l.client_id}`" class="cp-enlace-cliente">{{ l.cliente_nombre || `Cliente ${l.client_id}` }}</a>
                                </td>
                                <td class="cp-ll-telefono cp-num">{{ telefono(l.telefono) }}</td>
                                <td class="cp-ll-estado">
                                    <span class="cp-estado" :class="`cp-ll--${l.estado}`">
                                        <i class="fa" :class="estadoLlamada(l.estado).icono" aria-hidden="true"></i>{{ estadoLlamada(l.estado).label }}
                                    </span>
                                </td>
                                <td class="cp-ll-intentos cp-num">{{ l.intentos }} de {{ detalle.campana.max_intentos }}</td>
                                <td class="cp-ll-fecha cp-tenue">{{ fechaHora(l.ultimo_intento_at) }}</td>
                                <td v-if="detalle.campana.solo_deudores" class="cp-ll-saldo cp-num">{{ dinero(l.monto_vencido) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="detalle.ultima > 1" class="cp-pie cp-pie--detalle">
                    <span class="cp-tenue cp-num">{{ n(detalle.total) }} llamadas</span>
                    <div class="cp-paginacion">
                        <button type="button" class="cp-icono" :disabled="detalle.pagina <= 1" aria-label="Página anterior" @click="cargarLlamadas(detalle.pagina - 1)">
                            <i class="fa fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span class="cp-num cp-tenue">{{ detalle.pagina }} de {{ detalle.ultima }}</span>
                        <button type="button" class="cp-icono" :disabled="detalle.pagina >= detalle.ultima" aria-label="Página siguiente" @click="cargarLlamadas(detalle.pagina + 1)">
                            <i class="fa fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </template>
            <template #pie>
                <a v-if="detalle.campana" class="cp-boton" :href="urlGrabaciones(detalle.campana)">
                    <i class="fa fa-headphones" aria-hidden="true"></i> Escuchar las grabaciones
                </a>
                <button v-if="detalle.campana && accion(detalle.campana)" type="button" class="cp-boton cp-boton--primario"
                        :disabled="procesando === detalle.campana.id" @click="ejecutar(detalle.campana)">
                    <i class="fa" :class="accion(detalle.campana).icono" aria-hidden="true"></i>{{ accion(detalle.campana).label }}
                </button>
            </template>
        </campanas-dialogo>
    </div>
</template>

<script>
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { darkMode } from '../../../hook/appConfig.js';
import { message } from '../../../helpers/toastMsg.js';
import CampanasDialogo from './CampanasDialogo.vue';
import CampanasAudiencia from './CampanasAudiencia.vue';
import { ESTADOS_POR_OMISION, filtrosDe, resumenAudiencia, zonasRepetidas, tieneVariables } from './audiencia.js';

// Fecha de hoy en la hora local (toISOString da la de UTC: después de las 6 p.m. ya era «mañana»).
const hoyLocal = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// 10 ms de silencio: el play que se da dentro del clic, antes de que llegue la voz (ver escucharNota).
const SILENCIO = 'data:audio/wav;base64,UklGRnQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVAAAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgA==';

const VOZ_INICIAL = { asignada: false, motivo: '', soporta_tono: false, voces: [], tonos: [], voz: 'nova',
                      velocidad: { defecto: 0.95, min: 0.5, max: 1.5 }, plantilla: '' };

// Motivo de la campaña (27-nov: ya no decide a quién se llama; eso son los filtros de «A quién»).
const TIPOS = {
    cobranza: { label: 'Cobranza',          icono: 'fa-hand-holding-usd' },
    aviso:    { label: 'Aviso',             icono: 'fa-bullhorn' },
    anuncio:  { label: 'Anuncio',           icono: 'fa-comment-dots' },
    corte:    { label: 'Corte de servicio', icono: 'fa-cut' },
};

// El estado del cliente con los colores de siempre: activo verde, bloqueado ámbar, inactivo gris.
const ESTADO_CLIENTE_CLASE = { Activo: 'cp-estado--activa', Bloqueado: 'cp-estado--pausada', Inactivo: 'cp-estado--borrador' };

const POR_PAGINA_REVISION = 50;

const ORDEN_NOMBRES = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

// Para buscar sin importar acentos ni mayúsculas.
const plano = t => String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

function activarVacio(c = null) {
    return {
        campana: c, filtros: filtrosDe(c), soloDeudores: !!(c && c.solo_deudores), dias: Number((c && c.dias_vencimiento) ?? 1),
        // La lista que se cargó y con qué «solo los que deben»: el saldo se muestra según la lista, no según la casilla.
        resumen: null, clientes: [], vistaDeudores: false, quitados: {},
        buscar: '', vista: 'todos', pagina: 1, editando: false, cargando: false, enviando: false, errorLista: '', error: '',
    };
}

const ESTADOS = {
    activa:     { label: 'Activa',    icono: 'fa-play-circle' },
    pausada:    { label: 'En pausa',  icono: 'fa-pause-circle' },
    borrador:   { label: 'Borrador',  icono: 'fa-pencil-alt' },
    completada: { label: 'Terminada', icono: 'fa-check-circle' },
};

const SEGMENTOS = [
    { id: '', label: 'Todas' },
    { id: 'activa', label: 'Activas' },
    { id: 'pausada', label: 'En pausa' },
    { id: 'borrador', label: 'Borradores' },
    { id: 'completada', label: 'Terminadas' },
];

const ESTADOS_LLAMADA = {
    pendiente:   { label: 'Por marcar',   icono: 'fa-clock' },
    marcando:    { label: 'Marcando',     icono: 'fa-phone-alt' },
    contestada:  { label: 'Contestó',     icono: 'fa-check' },
    no_contesto: { label: 'No contestó',  icono: 'fa-phone-slash' },
    ocupado:     { label: 'Ocupado',      icono: 'fa-ban' },
    fallida:     { label: 'Falló',        icono: 'fa-exclamation-triangle' },
    pagada:      { label: 'Ya pagó',      icono: 'fa-user-check' },
    excluida:    { label: 'Sin teléfono', icono: 'fa-phone-slash' },
};

const ORDENES = ['nombre', 'estado', 'llamadas', 'contestadas', 'creada'];

// La nota es el mensaje que escucha el cliente; con «solo los que deben» arranca con el de cobranza.
function nuevaCampana(opcionesVoz, base = null) {
    const c = {
        nombre: '', tipo: 'cobranza', solo_deudores: true, max_canales_simultaneos: null,
        audiencia: { estados: [...ESTADOS_POR_OMISION], zona: null, caja: null },
        fecha_inicio: '', fecha_fin: '', hora_inicio: '09:00', hora_fin: '20:00',
        max_intentos: 3, minutos_entre_intentos: 180, dias_vencimiento: 1,
        notas: opcionesVoz.plantilla || '',
        voz: opcionesVoz.voz || 'nova', voz_velocidad: opcionesVoz.velocidad.defecto, voz_tono: '',
    };
    if (!base) return c;
    // Duplicar: mismo mensaje, voz, horario y límites; las fechas se eligen de nuevo.
    return {
        ...c,
        nombre: `Copia de ${base.nombre}`.slice(0, 255),
        tipo: base.tipo || 'cobranza',
        solo_deudores: base.solo_deudores != null ? !!base.solo_deudores : base.tipo === 'cobranza',
        audiencia: filtrosDe(base),
        max_canales_simultaneos: base.max_canales_simultaneos || null,
        hora_inicio: (base.hora_inicio || '09:00').slice(0, 5),
        hora_fin: (base.hora_fin || '20:00').slice(0, 5),
        max_intentos: base.max_intentos ?? 3,
        minutos_entre_intentos: base.minutos_entre_intentos ?? 180,
        dias_vencimiento: base.dias_vencimiento ?? 1,
        notas: base.notas ?? '',
        voz: base.voz || c.voz,
        voz_velocidad: base.voz_velocidad != null ? Number(base.voz_velocidad) : c.voz_velocidad,
        voz_tono: base.voz_tono || '',
    };
}

function leerUrl() {
    const q = new URLSearchParams(window.location.search);
    return {
        buscar: q.get('buscar') || '',
        estado: Object.keys(ESTADOS).includes(q.get('estado')) ? q.get('estado') : '',
        tipo: Object.keys(TIPOS).includes(q.get('tipo')) ? q.get('tipo') : '',
        orden: ORDENES.includes(q.get('orden')) ? q.get('orden') : 'creada',
        dir: q.get('dir') === 'asc' ? 'asc' : 'desc',
        por_pagina: [10, 25, 50, 100].includes(Number(q.get('por_pagina'))) ? Number(q.get('por_pagina')) : 25,
        pagina: Math.max(1, Number(q.get('pagina')) || 1),
    };
}

export default {
    name: 'CobranzaCampanas',
    components: { CampanasDialogo, CampanasAudiencia },
    props: {
        baseUrl:        { type: String, required: true },
        csrfToken:      { type: String, required: true },
        dashboardUrl:   { type: String, default: '/cobranza/dashboard' },
        puedeGestionar: { type: Boolean, default: false },
        puedeCortar:    { type: Boolean, default: false },
    },
    setup() {
        return { darkMode, TIPOS, SEGMENTOS, ESTADO_CLIENTE_CLASE, POR_PAGINA_REVISION, filtrosDe, resumenAudiencia };
    },
    data() {
        const url = leerUrl();
        return {
            cargando:     true,
            errorCarga:   '',
            campanas:     [],
            total:        0,
            pagina:       url.pagina,
            ultimaPagina: 1,
            desde:        0,
            hasta:        0,
            porEstado:    {},
            kpis:         null,
            filtros:      { buscar: url.buscar, estado: url.estado, tipo: url.tipo, orden: url.orden, dir: url.dir, por_pagina: url.por_pagina },
            procesando:   null,
            exportando:   false,
            temporizadorBusqueda: null,
            temporizadorVivo:     null,
            dialogo:      { nueva: false, activar: false, detalle: false },

            // Alta / duplicar
            nueva:            nuevaCampana(VOZ_INICIAL),
            duplicandoDe:     '',
            erroresNueva:     {},
            creando:          false,
            limitesAbiertos:  false,
            vozOpciones:      { ...VOZ_INICIAL },
            vozCargada:       false,
            generandoMuestra: false,
            reproduciendo:    false,
            errorMuestra:     '',
            avisoAutoplay:    false,
            urlMuestra:       null,

            // A quién se le llama: cuántos entran mientras se llena el alta, y la lista para revisar
            audNueva:  { resumen: null, cargando: false, error: '' },
            activar:   activarVacio(),
            temporizadorAudNueva: null,
            temporizadorRevision: null,
            pedidoAudiencia:      0,
            // Mientras se cambia el formulario completo (abrir, duplicar), los watchers se callan.
            reemplazando:         false,
            motivoAnterior:       'cobranza',

            // Detalle
            detalle: { campana: null, llamadas: [], estado: '', pagina: 1, ultima: 1, total: 0, porEstado: {}, cargando: false, error: '' },
        };
    },
    computed: {
        canalesDefecto() {
            return this.kpis?.canales || 10;
        },
        hayFiltros() {
            return !!(this.filtros.buscar.trim() || this.filtros.estado || this.filtros.tipo);
        },
        textoTotal() {
            if (this.cargando && !this.campanas.length) return '';
            return this.total === 1 ? '1 campaña' : `${this.n(this.total)} campañas`;
        },
        textoRango() {
            return `${this.n(this.desde)}–${this.n(this.hasta)} de ${this.n(this.total)}`;
        },
        notaContestadasHoy() {
            if (!this.kpis) return '';
            if (!this.kpis.marcadas_hoy) return 'todavía no se marca a nadie hoy';
            return `${this.kpis.contestadas_pct}% de ${this.n(this.kpis.marcadas_hoy)} marcadas`;
        },
        // Lo que se va a leer: con «solo los que deben», nota vacía = el mensaje de cobranza de siempre
        textoNota() {
            const nota = (this.nueva.notas || '').trim();
            return nota || (this.nueva.solo_deudores ? this.vozOpciones.plantilla : '');
        },
        textoSiguiente() {
            if (this.creando) return 'Guardando…';
            const r = this.audNueva.resumen;
            if (!r || this.audNueva.cargando || !this.nueva.audiencia.estados.length) return 'Siguiente: revisar la lista';
            if (!r.clientes_n) return 'Nadie entra con estos filtros';
            return `Siguiente: revisar ${this.n(r.clientes_n)} ${r.clientes_n === 1 ? 'cliente' : 'clientes'}`;
        },
        // Los campos sin aviso propio: que el 422 no quede mudo.
        otrosErrores() {
            return Object.entries(this.erroresNueva)
                .filter(([campo]) => !['nombre', 'notas', 'voz', 'voz_velocidad', 'voz_tono'].includes(campo))
                .map(([, e]) => (Array.isArray(e) ? e[0] : e));
        },
        velocidadEtiqueta() {
            return `${Number(this.nueva.voz_velocidad || 1).toFixed(2)}×`;
        },
        resumenLimites() {
            const c = this.nueva;
            return `${c.max_intentos || 3} intentos · cada ${c.minutos_entre_intentos || 180} min · ${c.max_canales_simultaneos || this.canalesDefecto} a la vez`;
        },
        resumenActivar() {
            const a = this.activar;
            return resumenAudiencia(a.filtros, a.soloDeudores, a.dias, zonasRepetidas(a.resumen && a.resumen.zonas));
        },
        // La lista a la vista: búsqueda y «todos / se les llama / quitados».
        filtradosRevision() {
            const a = this.activar;
            const q = plano(a.buscar.trim());
            const digitos = a.buscar.replace(/\D/g, '');
            return a.clientes.filter(c => {
                if (a.vista === 'llamar' && a.quitados[c.id]) return false;
                if (a.vista === 'quitados' && !a.quitados[c.id]) return false;
                if (!q) return true;
                return plano(c.nombre).includes(q) || plano(c.nomenclatura).includes(q)
                    || (digitos.length > 0 && (String(c.id) === digitos || String(c.telefono || '').includes(digitos)));
            });
        },
        paginasRevision() {
            return Math.max(1, Math.ceil(this.filtradosRevision.length / POR_PAGINA_REVISION));
        },
        paginaRevision() {
            const inicio = (this.activar.pagina - 1) * POR_PAGINA_REVISION;
            return this.filtradosRevision.slice(inicio, inicio + POR_PAGINA_REVISION);
        },
        quitadosEnLista() {
            const q = this.activar.quitados;
            return this.activar.clientes.reduce((t, c) => t + (q[c.id] ? 1 : 0), 0);
        },
        aLlamar() {
            return this.activar.clientes.length - this.quitadosEnLista;
        },
        montoALlamar() {
            const q = this.activar.quitados;
            return this.activar.clientes.reduce((t, c) => t + (q[c.id] ? 0 : Number(c.monto || 0)), 0);
        },
        segmentosRevision() {
            const lista = [
                { id: 'todos', label: 'Todos', n: this.activar.clientes.length },
                { id: 'llamar', label: 'Se les llama', n: this.aLlamar },
            ];
            if (this.quitadosEnLista || this.activar.vista === 'quitados') lista.push({ id: 'quitados', label: 'Quitados', n: this.quitadosEnLista });
            return lista;
        },
        todosMarcados() {
            const f = this.filtradosRevision;
            return f.length > 0 && f.every(c => !this.activar.quitados[c.id]);
        },
        algunosMarcados() {
            const f = this.filtradosRevision;
            const marcados = f.reduce((t, c) => t + (this.activar.quitados[c.id] ? 0 : 1), 0);
            return marcados > 0 && marcados < f.length;
        },
        // Sin «solo los que deben» se lee un texto igual para todos: la campaña tiene que traerlo y sin
        // {nombre}/{saldo}… (se leerían tal cual).
        sinMensajeFijo() {
            const c = this.activar.campana;
            if (!c || this.activar.soloDeudores) return false;
            const texto = String((c.voz ? c.notas : c.audio_mensaje) || '');
            return !texto.trim() || tieneVariables(texto);
        },
        notaConVariables() {
            return !this.nueva.solo_deudores && tieneVariables(this.nueva.notas);
        },
        puedeSiguiente() {
            const r = this.audNueva.resumen;
            return !this.creando && this.nueva.audiencia.estados.length > 0 && !this.notaConVariables
                && !(r && !this.audNueva.cargando && !r.clientes_n);
        },
        puedeConfirmarActivacion() {
            const a = this.activar;
            // La casilla y la lista tienen que ir de acuerdo: se manda «solo los que deben» de la lista.
            return !!(a.campana && a.resumen && a.filtros.estados.length && this.aLlamar > 0 && a.soloDeudores === a.vistaDeudores
                && !a.cargando && !a.enviando && !a.errorLista && !this.sinMensajeFijo);
        },
        textoBotonActivar() {
            const a = this.activar;
            if (a.enviando) return 'Activando…';
            if (a.cargando || !a.resumen) return 'Llamar';
            if (!this.aLlamar) return 'Nadie a quién llamar';
            return this.aLlamar === 1 ? 'Llamar a 1 cliente' : `Llamar a ${this.n(this.aLlamar)} clientes`;
        },
        segmentosLlamadas() {
            const pe = this.detalle.porEstado || {};
            const lista = [{ id: '', label: 'Todas', n: pe.todas || 0 }];
            for (const [id, info] of Object.entries(ESTADOS_LLAMADA)) {
                if (pe[id] || this.detalle.estado === id) lista.push({ id, label: info.label, n: pe[id] || 0 });
            }
            return lista;
        },
    },
    watch: {
        'nueva.solo_deudores'(deben) {
            if (this.reemplazando) return;
            const plantilla = this.vozOpciones.plantilla;
            if (deben && !(this.nueva.notas || '').trim()) this.nueva.notas = plantilla;
            if (!deben && plantilla && this.nueva.notas === plantilla) this.nueva.notas = '';
            this.contarNueva();
        },
        'nueva.audiencia': { deep: true, handler() { if (!this.reemplazando) this.contarNueva(); } },
        'nueva.dias_vencimiento'() { if (!this.reemplazando && this.nueva.solo_deudores) this.contarNueva(); },
        'activar.filtros': { deep: true, handler() { if (!this.reemplazando && this.dialogo.activar) this.cargarRevision(); } },
        'activar.soloDeudores'() { if (!this.reemplazando && this.dialogo.activar) this.cargarRevision(); },
        'activar.dias'() { if (!this.reemplazando && this.dialogo.activar && this.activar.soloDeudores) this.cargarRevision(); },
        'activar.buscar'() { this.activar.pagina = 1; },
        paginasRevision(total) { if (this.activar.pagina > total) this.activar.pagina = total; },
        'dialogo.nueva'(abierto) {
            if (!abierto) this.detenerMuestra();
        },
    },
    mounted() {
        this.recargarTodo();
        // Con campañas activas, el avance se actualiza solo (sin parpadeos ni perder la página).
        this.temporizadorVivo = setInterval(() => {
            if (document.visibilityState !== 'visible' || this.cargando) return;
            if (this.kpis && (this.kpis.campanas_activas > 0 || this.kpis.en_curso > 0)) {
                this.cargarKpis();
                this.recargar(this.pagina, true);
                if (this.dialogo.detalle && this.detalle.campana) this.cargarLlamadas(this.detalle.pagina, true);
            }
        }, 20000);
    },
    beforeUnmount() {
        this.detenerMuestra();
        clearInterval(this.temporizadorVivo);
        clearTimeout(this.temporizadorBusqueda);
        clearTimeout(this.temporizadorAudNueva);
        clearTimeout(this.temporizadorRevision);
    },
    methods: {
        // ── Formato ─────────────────────────────────────────────────────────────
        n(x) {
            return Number(x || 0).toLocaleString('es-MX');
        },
        dinero(x) {
            return Number(x || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
        telefono(t) {
            const d = String(t || '').replace(/\D/g, '');
            return d.length === 10 ? `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}` : (t || '—');
        },
        fechaCorta(d) {
            if (!d) return '';
            const [y, m, dia] = String(d).slice(0, 10).split('-').map(Number);
            return new Date(y, m - 1, dia).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
        },
        fechaHora(d) {
            if (!d) return '—';
            return new Date(String(d).replace(' ', 'T')).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
        horario(c) {
            return `${String(c.hora_inicio || '09:00').slice(0, 5)}–${String(c.hora_fin || '20:00').slice(0, 5)}`;
        },
        periodo(c) {
            const i = this.fechaCorta(c.fecha_inicio);
            const f = this.fechaCorta(c.fecha_fin);
            if (i && f) return `${i} – ${f}`;
            if (i) return `desde ${i}`;
            if (f) return `hasta ${f}`;
            return 'sin fechas';
        },
        vozTexto(c) {
            const voz = this.vozOpciones.voces.find(v => v.id === c.voz)?.etiqueta?.split(' (')[0] || (c.voz ? c.voz[0].toUpperCase() + c.voz.slice(1) : 'La de siempre');
            const tono = this.vozOpciones.tonos.find(t => t.id === c.voz_tono)?.etiqueta;
            const vel = c.voz_velocidad != null ? `${Number(c.voz_velocidad).toFixed(2)}×` : '';
            return [voz, vel, tono].filter(Boolean).join(' · ');
        },
        tipoInfo(t) { return TIPOS[t] || TIPOS.cobranza; },
        estadoInfo(e) { return ESTADOS[e] || { label: e, icono: 'fa-circle' }; },
        estadoLlamada(e) { return ESTADOS_LLAMADA[e] || { label: e, icono: 'fa-circle' }; },
        hechas(c) {
            return Math.max(0, (+c.total_llamadas || 0) - (+c.pendientes || 0) - (+c.marcando || 0));
        },
        pctAvance(c) {
            const t = +c.total_llamadas || 0;
            return t ? Math.round(this.hechas(c) * 100 / t) : 0;
        },
        pctContestadas(c) {
            const h = this.hechas(c);
            return h ? `${Math.round((+c.contestadas || 0) * 100 / h)}%` : '';
        },
        notaAvance(c) {
            if (+c.marcando > 0) return `${this.n(c.marcando)} marcando ahora`;
            if (+c.pendientes > 0) return `${this.n(c.pendientes)} por marcar`;
            if (+c.total_llamadas > 0) return 'Lista terminada';
            return c.estado === 'borrador' ? 'Sin activar' : 'Sin llamadas';
        },

        // ── Permisos y acciones ─────────────────────────────────────────────────
        puedeLanzarTipo(tipo) {
            return tipo === 'corte' ? this.puedeCortar : this.puedeGestionar;
        },
        accion(c) {
            if (c.estado === 'borrador' && this.puedeLanzarTipo(c.tipo)) return { tipo: 'activar', label: 'Activar…', icono: 'fa-play' };
            if (c.estado === 'activa' && this.puedeGestionar) return { tipo: 'pausar', label: 'Pausar', icono: 'fa-pause' };
            if (c.estado === 'pausada' && this.puedeLanzarTipo(c.tipo)) return { tipo: 'reanudar', label: 'Reanudar', icono: 'fa-play' };
            return null;
        },
        ejecutar(c) {
            const a = this.accion(c);
            if (!a) return;
            if (a.tipo === 'activar') this.abrirActivar(c);
            if (a.tipo === 'pausar') this.cambiarEstado(c, 'pausar');
            if (a.tipo === 'reanudar') this.cambiarEstado(c, 'reanudar');
        },
        // El texto del servidor solo si es nuestro; un 500 de Laravel diría «Server Error».
        errorDe(e, porOmision) {
            const r = e?.response;
            return r?.data?.error || (r && r.status < 500 ? r.data?.message : '') || porOmision;
        },

        // ── Lista ───────────────────────────────────────────────────────────────
        recargarTodo() {
            this.cargarKpis();
            this.recargar(this.pagina);
            if (!this.vozCargada) this.cargarVoz();
        },
        async recargar(pagina = 1, silencioso = false) {
            this.pagina = pagina;
            if (!silencioso) this.cargando = true;
            this.recordarEnUrl();
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/data`, { params: { ...this.paramsLista(), page: pagina } });
                this.campanas     = data.data;
                this.total        = data.total;
                this.ultimaPagina = data.last_page || 1;
                this.desde        = data.from || 0;
                this.hasta        = data.to || 0;
                this.porEstado    = data.por_estado || {};
                this.errorCarga   = '';
                if (pagina > this.ultimaPagina) this.recargar(this.ultimaPagina, silencioso);
            } catch (e) {
                if (!silencioso) this.errorCarga = this.errorDe(e, 'No se pudo cargar la lista de campañas.');
            } finally {
                this.cargando = false;
            }
        },
        paramsLista() {
            const f = this.filtros;
            const p = { orden: f.orden, dir: f.dir, por_pagina: f.por_pagina };
            if (f.buscar.trim()) p.buscar = f.buscar.trim();
            if (f.estado) p.estado = f.estado;
            if (f.tipo) p.tipo = f.tipo;
            return p;
        },
        async cargarKpis() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/kpis`);
                this.kpis = data;
            } catch (e) {
                this.kpis = this.kpis || null;
            }
        },
        // F5 o un enlace compartido abren la misma vista.
        recordarEnUrl() {
            const f = this.filtros;
            const p = new URLSearchParams();
            if (f.buscar.trim()) p.set('buscar', f.buscar.trim());
            if (f.estado) p.set('estado', f.estado);
            if (f.tipo) p.set('tipo', f.tipo);
            if (f.orden !== 'creada' || f.dir !== 'desc') { p.set('orden', f.orden); p.set('dir', f.dir); }
            if (f.por_pagina !== 25) p.set('por_pagina', f.por_pagina);
            if (this.pagina > 1) p.set('pagina', this.pagina);
            const q = p.toString();
            try {
                window.history.replaceState(window.history.state, '', window.location.pathname + (q ? '?' + q : ''));
            } catch { /* sin historial: solo no se recuerda */ }
        },
        buscarLuego() {
            clearTimeout(this.temporizadorBusqueda);
            this.temporizadorBusqueda = setTimeout(() => this.recargar(1), 350);
        },
        filtrarEstado(estado) {
            this.filtros.estado = estado;
            this.recargar(1);
        },
        quitarFiltros() {
            this.filtros.buscar = '';
            this.filtros.estado = '';
            this.filtros.tipo = '';
            this.recargar(1);
        },
        ordenar(campo) {
            if (this.filtros.orden === campo) {
                this.filtros.dir = this.filtros.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.filtros.orden = campo;
                this.filtros.dir = campo === 'nombre' || campo === 'estado' ? 'asc' : 'desc';
            }
            this.recargar(1);
        },
        ariaOrden(campo) {
            if (this.filtros.orden !== campo) return 'none';
            return this.filtros.dir === 'asc' ? 'ascending' : 'descending';
        },
        iconoOrden(campo) {
            if (this.filtros.orden !== campo) return 'fa-sort cp-orden-icono';
            return this.filtros.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },
        async exportar() {
            this.exportando = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/data`, { params: { ...this.paramsLista(), todo: 1 } });
                const filas = data.data.map(c => ({
                    'Campaña': c.nombre,
                    'Motivo': this.tipoInfo(c.tipo).label,
                    'A quién': c.audiencia ? resumenAudiencia(filtrosDe(c), c.solo_deudores, c.dias_vencimiento) : '',
                    'Quitados de la lista': c.audiencia ? (c.audiencia.quitados_n || 0) : '',
                    'Estado': this.estadoInfo(c.estado).label,
                    'Horario': this.horario(c),
                    'Desde': c.fecha_inicio ? String(c.fecha_inicio).slice(0, 10) : '',
                    'Hasta': c.fecha_fin ? String(c.fecha_fin).slice(0, 10) : '',
                    'Llamadas': +c.total_llamadas || 0,
                    'Hechas': this.hechas(c),
                    'Contestadas': +c.contestadas || 0,
                    '% contestadas': this.hechas(c) ? Math.round((+c.contestadas || 0) * 100 / this.hechas(c)) : null,
                    'No contestó': +c.no_contesto || 0,
                    'Ocupado': +c.ocupado || 0,
                    'Fallidas': +c.fallidas || 0,
                    'Ya pagaron': +c.pagadas || 0,
                    'Sin teléfono': +c.excluidas || 0,
                    'Por marcar': +c.pendientes || 0,
                    'Creada': String(c.created_at || '').slice(0, 10),
                    'Mensaje': c.notas || '',
                }));
                const hoja = XLSX.utils.json_to_sheet(filas);
                const libro = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(libro, hoja, 'Campañas');
                XLSX.writeFile(libro, `Campañas ${hoyLocal()}.xlsx`);
            } catch (e) {
                message(this.errorDe(e, 'No se pudo exportar la lista.'), 'error');
            } finally {
                this.exportando = false;
            }
        },
        async cambiarEstado(c, que) {
            this.procesando = c.id;
            try {
                await axios.post(`${this.baseUrl}/campanas/${c.id}/${que}`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                message(que === 'pausar'
                    ? `«${c.nombre}» quedó en pausa: no se marca a nadie más hasta que la reanudes.`
                    : `«${c.nombre}» sigue marcando.`);
                await Promise.all([this.recargar(this.pagina, true), this.cargarKpis()]);
                this.refrescarDetalle(c.id);
            } catch (e) {
                message(this.errorDe(e, que === 'pausar' ? 'No se pudo pausar la campaña.' : 'No se pudo reanudar la campaña.'), 'error');
            } finally {
                this.procesando = null;
            }
        },
        async eliminar(c) {
            const r = await Swal.fire({
                icon: 'warning',
                title: `¿Eliminar «${c.nombre}»?`,
                text: 'Es un borrador: todavía no tiene llamadas. No se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#b42318',
                reverseButtons: true,
                focusCancel: true,
            });
            if (!r.isConfirmed) return;
            try {
                await axios.delete(`${this.baseUrl}/campanas/${c.id}`, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                message(`«${c.nombre}» se eliminó.`);
                await Promise.all([this.recargar(this.pagina, true), this.cargarKpis()]);
            } catch (e) {
                message(this.errorDe(e, 'No se pudo eliminar la campaña.'), 'error');
            }
        },

        // ── Alta / duplicar ─────────────────────────────────────────────────────
        async abrirNueva(base = null) {
            if (!this.vozCargada) await this.cargarVoz();
            this.nueva           = nuevaCampana(this.vozOpciones, base);
            this.motivoAnterior  = this.nueva.tipo;
            this.callarWatchers();
            this.duplicandoDe    = base ? base.nombre : '';
            this.erroresNueva    = {};
            this.errorMuestra    = '';
            this.limitesAbiertos = false;
            if (!this.vozOpciones.voces.some(v => v.id === this.nueva.voz)) this.nueva.voz = this.vozOpciones.voz;
            if (!this.vozOpciones.soporta_tono) this.nueva.voz_tono = '';
            this.audNueva = { resumen: null, cargando: false, error: '' };
            this.dialogo.nueva = true;
            this.contarNueva(true);
        },
        cerrarNueva() {
            this.dialogo.nueva = false;
        },
        async cargarVoz() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/voz`);
                this.vozOpciones = { ...VOZ_INICIAL, ...data };
            } catch (e) {
                this.vozOpciones = { ...VOZ_INICIAL, motivo: this.puedeGestionar ? 'No se pudieron cargar las voces.' : '' };
            }
            this.vozCargada = true;
        },
        primerError(campo) {
            const e = this.erroresNueva[campo];
            return Array.isArray(e) ? e[0] : (e || '');
        },
        // Cobranza propone «solo los que deben»; otro motivo que venía de cobranza, a todos. Va en el
        // @change del select (lo que hace la persona), no en un watcher: al duplicar, un watcher
        // reaccionaba al cambio de formulario y le quitaba la casilla a la copia.
        alCambiarMotivo() {
            const t = this.nueva.tipo;
            if (t === 'cobranza') this.nueva.solo_deudores = true;
            else if (this.motivoAnterior === 'cobranza') this.nueva.solo_deudores = false;
            this.motivoAnterior = t;
        },
        async crearCampana(continuar) {
            if (this.creando) return;
            this.creando      = true;
            this.erroresNueva = {};
            try {
                const { data } = await axios.post(`${this.baseUrl}/campanas`, { ...this.nueva, voz_tono: this.nueva.voz_tono || null }, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.dialogo.nueva = false;
                this.recargar(1, true);
                this.cargarKpis();
                if (continuar && this.puedeLanzarTipo(data.tipo)) {
                    this.abrirActivar(data);
                } else {
                    message(`«${data.nombre}» quedó como borrador. Actívala cuando esté lista.`);
                }
            } catch (e) {
                if (e.response?.status === 422) {
                    this.erroresNueva = e.response.data.errors || {};
                    const conLimites = ['max_intentos', 'minutos_entre_intentos', 'max_canales_simultaneos'];
                    if (Object.keys(this.erroresNueva).some(k => conLimites.includes(k))) this.limitesAbiertos = true;
                } else {
                    // Que no se quede callado: sin esto, un error del servidor no decía nada.
                    this.erroresNueva = { general: [this.errorDe(e, 'No se pudo crear la campaña. Intenta de nuevo.')] };
                }
            } finally {
                this.creando = false;
            }
        },
        async escucharNota() {
            this.detenerMuestra();
            this.errorMuestra     = '';
            this.avisoAutoplay    = false;
            this.generandoMuestra = true;
            // Generar la voz tarda unos segundos y, para entonces, Safari, Firefox o Chrome con el
            // sonido bloqueado ya no lo cuentan como parte del clic y no dejan reproducir. Se le da
            // play al reproductor aquí, todavía dentro del clic (con un silencio), y luego se le cambia
            // el audio: el navegador ya lo dio por permitido.
            const audio = this.$refs.muestra;
            if (audio) {
                audio.src = SILENCIO;
                audio.play().catch(() => {});
            }
            try {
                const { data } = await axios.post(`${this.baseUrl}/campanas/escuchar`, {
                    notas:         this.textoNota,
                    solo_deudores: this.nueva.solo_deudores,
                    fecha_fin:     this.nueva.fecha_fin || null,
                    voz:           this.nueva.voz,
                    voz_velocidad: this.nueva.voz_velocidad,
                    voz_tono:      this.nueva.voz_tono || null,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken }, responseType: 'blob' });
                if (!(data instanceof Blob) || !data.size) throw new Error('sin audio');
                this.urlMuestra = URL.createObjectURL(data);
                if (!audio) return;
                audio.src = this.urlMuestra;
                try {
                    await audio.play();
                } catch (e) {
                    // Bloqueado aun así: queda el reproductor a la vista para darle play a mano.
                    if (e && e.name === 'NotAllowedError') this.avisoAutoplay = true;
                    else throw e;
                }
            } catch (e) {
                this.detenerMuestra();
                this.errorMuestra = await this.errorDeMuestra(e);
            } finally {
                this.generandoMuestra = false;
            }
        },
        detenerMuestra() {
            const audio = this.$refs.muestra;
            if (audio) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
            }
            if (this.urlMuestra) {
                URL.revokeObjectURL(this.urlMuestra);
                this.urlMuestra = null;
            }
            this.reproduciendo = false;
            this.avisoAutoplay = false;
        },
        // La respuesta de error llega como Blob (se pidió audio); se lee el JSON de adentro
        async errorDeMuestra(e) {
            if (e.response?.status === 429) return 'Muchas pruebas seguidas: espera un minuto y vuelve a escuchar.';
            if (!e.response) return 'El navegador no pudo reproducir la voz. Revisa que el sonido de esta página no esté bloqueado e inténtalo otra vez.';
            try {
                const json = JSON.parse(await e.response.data.text());
                const primero = json.errors ? Object.values(json.errors)[0] : null;
                return json.error || (Array.isArray(primero) ? primero[0] : '') || json.message || 'No se pudo generar la voz.';
            } catch (_) {
                return 'No se pudo reproducir la voz.';
            }
        },

        // Se llama justo DESPUÉS de cambiar el objeto: así el nextTick queda detrás de la pasada que
        // corre los watchers (antes de cambiarlo, el nextTick se adelantaría a esa pasada).
        callarWatchers() {
            this.reemplazando = true;
            this.$nextTick(() => { this.reemplazando = false; });
        },

        // ── A quién se le llama ─────────────────────────────────────────────────
        // Filtros sueltos en la URL: axios 0.x no anida objetos en los parámetros de un GET.
        paramsAudiencia(filtros, soloDeudores, dias, extra = {}) {
            const p = { 'estados[]': filtros.estados, solo_deudores: soloDeudores ? 1 : 0, ...extra };
            if (filtros.zona) p.zona = filtros.zona;
            if (filtros.caja) p.caja = filtros.caja;
            if (soloDeudores) p.dias_vencimiento = Number(dias) || 0;
            return p;
        },
        // Lo que conviene saber de la lista, además del número.
        notasAudiencia(r, filtros) {
            const notas = [];
            if (r.sin_telefono) {
                notas.push(`${this.n(r.sin_telefono)} ${r.sin_telefono === 1 ? 'no tiene' : 'no tienen'} un teléfono de 10 dígitos en su ficha: no se les llama.`);
            }
            if (filtros.zona && r.sin_caja) {
                notas.push(`${this.n(r.sin_caja)} ${r.sin_caja === 1 ? 'cliente' : 'clientes'} de estos estados no ${r.sin_caja === 1 ? 'tiene' : 'tienen'} caja en la nomenclatura nueva: al filtrar por zona no aparecen.`);
            }
            return notas;
        },
        // Cuántos entran mientras se llena el alta (sin la lista: solo números).
        contarNueva(ya = false) {
            clearTimeout(this.temporizadorAudNueva);
            if (!this.dialogo.nueva) return;
            const c = this.nueva;
            if (!c.audiencia.estados.length) {
                this.audNueva.cargando = false;
                return;
            }
            this.audNueva.cargando = true;
            const pedido = ++this.pedidoAudiencia;
            this.temporizadorAudNueva = setTimeout(async () => {
                try {
                    const { data } = await axios.get(`${this.baseUrl}/campanas/audiencia`, {
                        params: this.paramsAudiencia(c.audiencia, c.solo_deudores, c.dias_vencimiento),
                    });
                    if (pedido !== this.pedidoAudiencia) return;   // ya se pidió otra cosa
                    this.audNueva.resumen = data;
                    this.audNueva.error = '';
                } catch (e) {
                    if (pedido === this.pedidoAudiencia) this.audNueva.error = this.errorDe(e, 'No se pudo contar a cuántos clientes se les llamaría.');
                } finally {
                    if (pedido === this.pedidoAudiencia) this.audNueva.cargando = false;
                }
            }, ya ? 0 : 250);
        },

        // ── Revisar y llamar ────────────────────────────────────────────────────
        abrirActivar(c) {
            this.activar = activarVacio(c);
            this.callarWatchers();
            this.dialogo.activar = true;
            this.cargarRevision(true);
        },
        cargarRevision(ya = false) {
            clearTimeout(this.temporizadorRevision);
            const a = this.activar;
            if (!a.campana) return;
            a.errorLista = '';
            a.error = '';
            if (!a.filtros.estados.length) {
                ++this.pedidoAudiencia;   // lo que venía en camino ya no cuenta
                a.clientes = [];
                a.cargando = false;
                return;
            }
            a.cargando = true;
            const pedido = ++this.pedidoAudiencia;
            const deudores = a.soloDeudores;
            this.temporizadorRevision = setTimeout(async () => {
                try {
                    const { data } = await axios.get(`${this.baseUrl}/campanas/audiencia`, {
                        params: this.paramsAudiencia(a.filtros, deudores, a.dias, { campana: a.campana.id, lista: 1 }),
                    });
                    if (pedido !== this.pedidoAudiencia || this.activar !== a) return;
                    // Con zona, ordenados por caja y salida; sin zona, por nombre.
                    const porCaja = !!a.filtros.zona;
                    data.clientes.sort((x, y) => (porCaja ? ORDEN_NOMBRES.compare(x.nomenclatura || '~', y.nomenclatura || '~') : 0)
                        || ORDEN_NOMBRES.compare(x.nombre, y.nombre));
                    a.clientes = Object.freeze(data.clientes);
                    delete data.clientes;
                    a.resumen = data;
                    a.vistaDeudores = deudores;
                    a.pagina = 1;
                } catch (e) {
                    if (pedido === this.pedidoAudiencia) a.errorLista = this.errorDe(e, 'El servidor no respondió. Inténtalo otra vez en un momento.');
                } finally {
                    if (pedido === this.pedidoAudiencia) a.cargando = false;
                }
            }, ya ? 0 : 250);
        },
        quitado(id) {
            return !!this.activar.quitados[id];
        },
        alternarCliente(id) {
            const q = this.activar.quitados;
            if (q[id]) delete q[id];
            else q[id] = true;
        },
        // Marca o quita a todos los que se ven con la búsqueda (no solo la página).
        marcarTodos(marcar) {
            const q = this.activar.quitados;
            for (const c of this.filtradosRevision) {
                if (marcar) delete q[c.id];
                else q[c.id] = true;
            }
        },
        verRevision(vista) {
            this.activar.vista = vista;
            this.activar.pagina = 1;
        },
        async confirmarActivacion() {
            const a = this.activar;
            if (!this.puedeConfirmarActivacion) return;
            a.enviando = true;
            a.error = '';
            try {
                const { data } = await axios.post(`${this.baseUrl}/campanas/${a.campana.id}/activar`, {
                    solo_deudores:    a.vistaDeudores,
                    dias_vencimiento: Number(a.dias) || 0,
                    audiencia:        a.filtros,
                    clientes:         a.clientes.filter(c => !a.quitados[c.id]).map(c => c.id),
                    quitados:         a.clientes.filter(c => a.quitados[c.id]).map(c => c.id),
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.dialogo.activar = false;
                const ya = data.ya_no_entran
                    ? ` ${this.n(data.ya_no_entran)} de los marcados ya no ${data.ya_no_entran === 1 ? 'entraba' : 'entraban'} (pagaron o cambiaron de estado) y no se les llama.`
                    : '';
                message(`«${a.campana.nombre}» está activa: se le llamará a ${this.n(data.insertados)} ${data.insertados === 1 ? 'cliente' : 'clientes'} en su horario.${ya}`);
                await Promise.all([this.recargar(this.pagina, true), this.cargarKpis()]);
                this.refrescarDetalle(a.campana.id);
            } catch (e) {
                const errores = e.response?.status === 422 && e.response.data.errors;
                a.error = errores ? Object.values(errores)[0][0] : this.errorDe(e, 'No se pudo activar la campaña.');
            } finally {
                a.enviando = false;
            }
        },

        // ── Detalle ─────────────────────────────────────────────────────────────
        abrirDetalle(c) {
            this.detalle = { campana: c, llamadas: [], estado: '', pagina: 1, ultima: 1, total: 0, porEstado: {}, cargando: false, error: '' };
            this.dialogo.detalle = true;
            this.cargarLlamadas(1);
        },
        refrescarDetalle(id) {
            if (!this.dialogo.detalle || this.detalle.campana?.id !== id) return;
            const fresca = this.campanas.find(x => x.id === id);
            if (fresca) this.detalle.campana = fresca;
            this.cargarLlamadas(this.detalle.pagina, true);
        },
        filtrarLlamadas(estado) {
            this.detalle.estado = estado;
            this.cargarLlamadas(1);
        },
        async cargarLlamadas(pagina = 1, silencioso = false) {
            const d = this.detalle;
            if (!d.campana) return;
            if (!silencioso) d.cargando = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/campanas/${d.campana.id}/llamadas`, {
                    params: { page: pagina, estado: d.estado || undefined },
                });
                d.llamadas = data.data;
                d.pagina = data.current_page;
                d.ultima = data.last_page || 1;
                d.total = data.total;
                d.porEstado = data.por_estado || {};
                d.error = '';
            } catch (e) {
                if (!silencioso) d.error = this.errorDe(e, 'No se pudieron cargar las llamadas.');
            } finally {
                d.cargando = false;
            }
        },
        urlGrabaciones(c) {
            const p = new URLSearchParams({ tipo: 'campana', buscar: c.nombre });
            const desde = String(c.created_at || '').slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(desde)) {
                p.set('desde', desde);
                p.set('hasta', hoyLocal());
            }
            return `${this.dashboardUrl}?${p}`;
        },
    },
};
</script>

<!-- Fichas de color de Campañas/Blaster: sin scope para que también las tome el diálogo, que va en <body>. -->
<style>
.cp-raiz {
    --cp-tinta: #1f2937; --cp-tenue: #5f6774; --cp-borde: #e6e8ec; --cp-borde-campo: #8a919e; --cp-cabecera: #f8f9fb;
    --cp-superficie: #ffffff; --cp-hover: #f5f7fa; --cp-acento: #246bc0; --cp-acento-texto: #ffffff; --cp-acento-suave: #e8f1fc;
    --cp-alerta: #b42318; --cp-sombra: 0 1px 2px rgba(16, 24, 40, .05); --cp-barra: #e3e7ee;
    color: var(--cp-tinta);
}
.cp-raiz.cd-dark {
    --cp-tinta: #e5e7eb; --cp-tenue: #aab1bc; --cp-borde: rgba(255, 255, 255, .1); --cp-borde-campo: rgba(255, 255, 255, .34);
    --cp-cabecera: rgba(255, 255, 255, .04); --cp-superficie: #2a3042; --cp-hover: rgba(255, 255, 255, .05);
    --cp-acento: #6ea8f0; --cp-acento-texto: #0f1729; --cp-acento-suave: rgba(57, 135, 229, .18);
    --cp-alerta: #fda29b; --cp-sombra: none; --cp-barra: rgba(255, 255, 255, .12);
}
.cp-raiz ::selection { background: var(--cp-acento-suave); color: var(--cp-tinta); }
body.cp-sin-scroll { overflow: hidden !important; }

/* Lo que también usa CampanasAudiencia (otro componente: el scope de esta página no le llega). */
.cp-raiz :is(button, a, input, select, textarea, summary):focus-visible {
    /* El tema quita el outline con !important: el anillo va con box-shadow. */
    outline: none; box-shadow: 0 0 0 2px var(--cp-superficie), 0 0 0 4px var(--cp-acento) !important;
}
.cp-raiz .cp-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.cp-raiz .cp-tenue { color: var(--cp-tenue); font-size: 12.5px; }

/* Campos (propios: los del tema quedan casi invisibles en oscuro) */
.cp-raiz .cp-campo {
    display: block; width: 100%; min-height: 36px; padding: 7px 11px; font-size: 13.5px; line-height: 1.4;
    color: var(--cp-tinta); background: var(--cp-superficie); border: 1px solid var(--cp-borde-campo); border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
}
.cp-raiz.cd-dark .cp-campo { background-color: rgba(255, 255, 255, .04); color-scheme: dark; }
.cp-raiz .cp-campo::placeholder { color: var(--cp-tenue); opacity: 1; }
.cp-raiz .cp-campo:hover:not(:disabled) { border-color: var(--cp-tinta); }
.cp-raiz .cp-campo:disabled { opacity: .55; }
.cp-raiz .cp-campo[aria-invalid="true"] { border-color: var(--cp-alerta); }
/* Flecha propia: con la nativa el texto largo («Nova (mujer, recomendada)») se metía debajo. */
.cp-raiz select.cp-campo {
    appearance: none; padding-right: 30px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 4.5 6 8l3.5-3.5' fill='none' stroke='%235b6472' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; background-size: 12px;
}
.cp-raiz.cd-dark select.cp-campo {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 4.5 6 8l3.5-3.5' fill='none' stroke='%23aab1bc' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}
.cp-raiz textarea.cp-campo { resize: vertical; min-height: 96px; }
.cp-raiz .cp-campo-grupo { min-width: 0; margin-bottom: 14px; }
.cp-raiz .cp-campo-grupo:last-child { margin-bottom: 0; }
.cp-raiz .cp-etiqueta { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--cp-tinta); }
.cp-raiz .cp-etiqueta--sin-margen { margin: 0; }
.cp-raiz .cp-etiqueta i { color: var(--cp-acento); margin-right: 4px; }
.cp-raiz .cp-opcional { font-weight: 400; color: var(--cp-tenue); }
.cp-raiz .cp-ayuda { margin: 6px 0 0; font-size: 12.5px; color: var(--cp-tenue); line-height: 1.45; }
.cp-raiz .cp-ayuda code { font-size: 12.5px; padding: 1px 5px; border-radius: 4px; background: var(--cp-acento-suave); color: var(--cp-acento); }
.cp-raiz .cp-error { margin: 6px 0 0; font-size: 12.5px; font-weight: 500; color: var(--cp-alerta); }
.cp-raiz .cp-par { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
.cp-raiz .cp-par > .cp-campo-grupo:last-child { margin-bottom: 14px; }
@media (max-width: 767px) {
    .cp-raiz .cp-campo { min-height: 44px; }
    .cp-raiz .cp-par { grid-template-columns: 1fr; }
}
</style>

<style scoped>
/* ── Base ─────────────────────────────────────────────────────────────────── */
/* Los cortes de la lista miden el espacio de la página (sin el menú lateral), no la pantalla. */
.cp { font-size: 13.5px; container: cp / inline-size; }
.cp-fuerte { font-weight: 600; }
.cp-pct { margin-left: 5px; }
.cp-sin-margen { margin: 0; }
.cp-solo-movil { display: none; }

/* ── Cabecera ─────────────────────────────────────────────────────────────── */
.cp-cabecera { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.cp-titulo { margin: 0; font-size: 1.375rem; font-weight: 600; letter-spacing: -.01em; color: var(--cp-tinta); }
.cp-sub { margin: 4px 0 0; font-size: 13.5px; color: var(--cp-tenue); }
.cp-cabecera-acciones { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Botones ──────────────────────────────────────────────────────────────── */
.cp-boton {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 34px;
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta);
    border-radius: 8px; padding: 6px 13px; font-size: 13px; font-weight: 500; line-height: 1.2;
    cursor: pointer; text-decoration: none; white-space: nowrap; transition: background .15s, border-color .15s, color .15s;
}
.cp-boton i { font-size: 12px; }
.cp-boton:hover:not(:disabled) { border-color: var(--cp-acento); color: var(--cp-acento); }
.cp-boton:disabled { opacity: .5; cursor: default; }
.cp-boton--primario { background: var(--cp-acento); border-color: var(--cp-acento); color: var(--cp-acento-texto); font-weight: 600; }
.cp-boton--primario:hover:not(:disabled) { filter: brightness(1.08); color: var(--cp-acento-texto); }
.cp-boton--peligro { border-color: var(--cp-alerta); color: var(--cp-alerta); }
.cp-boton--chico { min-height: 30px; padding: 4px 11px; font-size: 12.5px; }
.cp-icono {
    width: 34px; height: 34px; flex: none; border-radius: 8px; border: 1px solid var(--cp-borde-campo);
    background: var(--cp-superficie); color: var(--cp-tinta); display: inline-flex; align-items: center; justify-content: center;
    font-size: 12.5px; cursor: pointer; transition: background .15s, border-color .15s, color .15s;
}
.cp-icono:hover:not(:disabled) { background: var(--cp-acento-suave); border-color: transparent; color: var(--cp-acento); }
.cp-icono:disabled { opacity: .4; cursor: default; }
.cp-icono--peligro:hover:not(:disabled) { background: rgba(180, 35, 24, .1); color: var(--cp-alerta); }
.cp-enlace { border: 0; background: none; padding: 0; color: var(--cp-acento); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }

/* ── Resumen ──────────────────────────────────────────────────────────────── */
/* Las líneas entre cifras son el fondo asomando por el gap de 1 px: salen bien en 5, 3+2 o 2+2+1. */
.cp-resumen {
    display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1px; margin-bottom: 20px;
    background: var(--cp-borde); border: 1px solid var(--cp-borde); border-radius: 12px; box-shadow: var(--cp-sombra); overflow: hidden;
}
.cp-cifra { display: block; padding: 14px 18px; min-width: 0; text-align: left; background-color: var(--cp-superficie); }
.cp-cifra > span { display: block; }
.cp-cifra-label { font-size: 12.5px; color: var(--cp-tenue); }
.cp-cifra-valor { font-size: 1.5rem; font-weight: 600; line-height: 1.25; margin-top: 2px; font-variant-numeric: tabular-nums; min-height: 1.875rem; color: var(--cp-tinta); }
.cp-cifra-nota { font-size: 12.5px; color: var(--cp-tenue); min-height: 18px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-cifra--accion { border: 0; color: inherit; font: inherit; cursor: pointer; transition: background-image .15s; }
.cp-cifra--accion:hover { background-image: linear-gradient(var(--cp-hover), var(--cp-hover)); }
.cp-cifra--accion[aria-pressed="true"] { background-image: linear-gradient(var(--cp-acento-suave), var(--cp-acento-suave)); }
.cp-cifra--accion[aria-pressed="true"] .cp-cifra-label { color: var(--cp-acento); font-weight: 600; }

/* ── Panel de la lista ────────────────────────────────────────────────────── */
.cp-panel { background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 12px; box-shadow: var(--cp-sombra); overflow: hidden; }
.cp-panel-cabeza { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 16px 20px 0; }
.cp-panel-titulo { margin: 0; font-size: 15px; font-weight: 600; color: var(--cp-tinta); }
.cp-filtros { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 20px 14px; border-bottom: 1px solid var(--cp-borde); }
.cp-buscar { position: relative; flex: 1 1 240px; max-width: 360px; margin: 0; }
.cp-buscar i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--cp-tenue); font-size: 12.5px; pointer-events: none; }
.cp-buscar .cp-campo { padding-left: 32px; }
.cp-filtro-tipo { margin: 0; flex: 0 1 190px; }
.cp-exportar { margin-left: auto; }

.cp-segmentos { display: inline-flex; padding: 3px; border-radius: 9px; background: var(--cp-cabecera); border: 1px solid var(--cp-borde); max-width: 100%; overflow-x: auto; scrollbar-width: none; }
.cp-segmentos::-webkit-scrollbar { display: none; }
.cp-segmento {
    display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; color: var(--cp-tenue);
    font-size: 13px; font-weight: 500; padding: 5px 11px; border-radius: 7px; white-space: nowrap; cursor: pointer;
}
.cp-segmento:hover { color: var(--cp-tinta); }
.cp-segmento[aria-pressed="true"] { background: var(--cp-superficie); color: var(--cp-tinta); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.cd-dark .cp-segmento[aria-pressed="true"] { background: rgba(255, 255, 255, .12); box-shadow: none; }
.cp-segmento-n { font-size: 12px; font-variant-numeric: tabular-nums; color: var(--cp-tenue); }
.cp-segmento[aria-pressed="true"] .cp-segmento-n { color: var(--cp-acento); font-weight: 600; }

/* ── Tabla ────────────────────────────────────────────────────────────────── */
.cp-tabla-caja { overflow-x: auto; transition: opacity .15s; }
.cp-cargando { opacity: .55; }
.cp-tabla { width: 100%; border-collapse: collapse; color: var(--cp-tinta); }
.cp-tabla thead th {
    background: var(--cp-cabecera); color: var(--cp-tenue); font-size: 12.5px; font-weight: 600; text-align: left;
    padding: 9px 12px; white-space: nowrap; border-bottom: 1px solid var(--cp-borde);
}
.cp-tabla tbody td { padding: 12px; border-bottom: 1px solid var(--cp-borde); vertical-align: middle; }
.cp-tabla tbody tr:last-child td { border-bottom: 0; }
.cp-tabla tbody tr:not(.cp-fila-vacia):not(.cp-fila-esqueleto):hover td { background: var(--cp-hover); }
.cp-tabla th:first-child, .cp-tabla td:first-child { padding-left: 20px; }
.cp-tabla th:last-child, .cp-tabla td:last-child { padding-right: 20px; }
.cp-tabla .cp-num { text-align: right; }
.cp-orden {
    display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; padding: 0;
    color: inherit; font: inherit; cursor: pointer; white-space: nowrap;
}
.cp-orden i { font-size: 11px; }
.cp-orden:hover { color: var(--cp-tinta); }
th[aria-sort="ascending"] .cp-orden, th[aria-sort="descending"] .cp-orden { color: var(--cp-tinta); }
.cp-orden-icono { opacity: .45; }

.cp-td-campana { min-width: 220px; }
/* El mensaje recortado no debe ensanchar la columna (si no, la tabla se sale del panel). */
.cp-td-campana .cp-meta { max-width: 0; min-width: 100%; }
.cp-nombre {
    display: block; max-width: 100%; border: 0; background: none; padding: 0; text-align: left; cursor: pointer;
    font-size: 14px; font-weight: 600; color: var(--cp-tinta); line-height: 1.35; overflow-wrap: anywhere;
}
.cp-nombre:hover { color: var(--cp-acento); text-decoration: underline; text-underline-offset: 3px; }
.cp-meta { display: flex; align-items: baseline; gap: 6px; margin-top: 3px; min-width: 0; font-size: 12.5px; color: var(--cp-tenue); }
.cp-tipo { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 12.5px; color: var(--cp-tenue); }
.cp-tipo i { font-size: 11px; }
.cp-nota { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-nota::before { content: '·'; margin-right: 6px; }
.cp-td-cuando { white-space: nowrap; }
.cp-avance { display: flex; align-items: center; gap: 10px; }
.cp-barra { position: relative; width: 96px; height: 6px; border-radius: 999px; background: var(--cp-barra); overflow: hidden; flex: none; }
.cp-barra > span { position: absolute; inset: 0 auto 0 0; border-radius: inherit; background: var(--cp-acento); min-width: 0; transition: width .4s ease-out; }
.cp-vivo { color: var(--cp-acento); font-weight: 500; }
.cp-td-acciones { width: 1%; white-space: nowrap; }
.cp-acciones { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.cp-accion { min-width: 112px; }
.cp-hueco-accion { display: inline-block; width: 112px; }
.cp-hueco-icono { display: inline-block; width: 34px; }

/* Estado: ícono + texto, nunca solo color */
.cp-estado { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
.cp-estado i { font-size: 11px; }
.cp-estado--activa, .cp-ll--contestada { background: #dcfce7; color: #166534; }
.cp-estado--pausada, .cp-ll--ocupado, .cp-ll--no_contesto { background: #fef3c7; color: #92400e; }
.cp-estado--borrador, .cp-ll--pendiente, .cp-ll--excluida { background: #eef1f5; color: #475569; }
.cp-estado--completada, .cp-ll--marcando { background: #dbeafe; color: #1e40af; }
.cp-ll--fallida { background: #fee2e2; color: #991b1b; }
.cp-ll--pagada { background: #ccfbf1; color: #115e59; }
.cd-dark .cp-estado--activa, .cd-dark .cp-ll--contestada { background: rgba(34, 197, 94, .16); color: #86efac; }
.cd-dark .cp-estado--pausada, .cd-dark .cp-ll--ocupado, .cd-dark .cp-ll--no_contesto { background: rgba(245, 158, 11, .16); color: #fcd34d; }
.cd-dark .cp-estado--borrador, .cd-dark .cp-ll--pendiente, .cd-dark .cp-ll--excluida { background: rgba(148, 163, 184, .16); color: #cbd5e1; }
.cd-dark .cp-estado--completada, .cd-dark .cp-ll--marcando { background: rgba(59, 130, 246, .18); color: #93c5fd; }
.cd-dark .cp-ll--fallida { background: rgba(239, 68, 68, .18); color: #fca5a5; }
.cd-dark .cp-ll--pagada { background: rgba(45, 212, 191, .16); color: #5eead4; }

/* Vacíos, avisos, esqueletos */
.cp-vacio { text-align: center; color: var(--cp-tenue); padding: 44px 20px !important; }
.cp-vacio strong { display: block; color: var(--cp-tinta); font-size: 14.5px; margin-bottom: 4px; }
.cp-vacio-texto { display: block; margin-bottom: 14px; }
.cp-aviso {
    display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 10px; margin: 0 0 14px;
    background: #fff7ed; color: #7c2d12; border: 1px solid #fed7aa; font-size: 13px; line-height: 1.45;
}
.cp-aviso i { margin-top: 3px; }
.cp-aviso--error { background: #fef2f2; color: #7f1d1d; border-color: #fecaca; }
.cp-aviso--chico { padding: 8px 12px; }
.cp-aviso--en-panel { margin: 14px 20px 0; }
.cd-dark .cp-aviso { background: rgba(234, 88, 12, .12); color: #fdba74; border-color: rgba(234, 88, 12, .3); }
.cd-dark .cp-aviso--error { background: rgba(239, 68, 68, .12); color: #fca5a5; border-color: rgba(239, 68, 68, .3); }
.cp-esqueleto { display: block; height: 12px; border-radius: 6px; background: var(--cp-barra); }
.cp-esqueleto--chico { width: 40%; margin-top: 8px; height: 10px; }
.cp-esqueleto--grande { width: 120px; height: 36px; margin-bottom: 6px; }
.cp-cifra-valor.cp-esqueleto { width: 56px; height: 1.6rem; margin-top: 4px; }
@media (prefers-reduced-motion: no-preference) {
    .cp-esqueleto { animation: cp-pulso 1.4s ease-in-out infinite; }
}
@keyframes cp-pulso { 50% { opacity: .45; } }

/* Pie y paginación */
.cp-pie { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 20px; border-top: 1px solid var(--cp-borde); }
.cp-paginacion { display: flex; align-items: center; gap: 8px; }
.cp-por-pagina { display: inline-flex; align-items: center; gap: 8px; margin: 0 6px 0 0; font-size: 12.5px; color: var(--cp-tenue); }
.cp-por-pagina .cp-campo { width: auto; min-height: 34px; padding: 4px 8px; }

/* ── Alta ─────────────────────────────────────────────────────────────────── */
.cp-form { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(300px, 1fr); gap: 28px; }
.cp-seccion { border: 0; margin: 0 0 20px; padding: 0; min-width: 0; }
.cp-seccion:last-child { margin-bottom: 0; }
.cp-seccion-titulo {
    display: block; width: 100%; float: none; margin: 0 0 12px; padding: 0 0 8px; border-bottom: 1px solid var(--cp-borde);
    font-size: 14px; font-weight: 600; color: var(--cp-tinta);
}
/* Un <legend> se dibuja ENCIMA del borde de su fieldset; flotado queda dentro de la caja. */
legend.cp-seccion-titulo { float: left; }
legend.cp-seccion-titulo + * { clear: both; }
.cp-ayuda + .cp-par { margin-top: 14px; }
.cp-form-col--lado .cp-seccion { background: var(--cp-cabecera); border: 1px solid var(--cp-borde); border-radius: 10px; padding: 14px 16px; }
/* Nombre ancho y el motivo a su lado */
.cp-nombre-motivo { display: grid; grid-template-columns: minmax(0, 1fr) minmax(150px, 210px); gap: 0 14px; }
.cp-nombre-motivo .cp-campo-grupo:last-child { margin-bottom: 14px; }

/* Cuántos entran con los filtros del alta */
.cp-cuenta { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--cp-borde); }
.cp-cuenta-total { margin: 0; font-size: 13.5px; color: var(--cp-tinta); }
.cp-cuenta-total strong { font-size: 1.25rem; font-weight: 700; letter-spacing: -.01em; margin-right: 2px; }
.cp-cuenta-nota { margin: 4px 0 0; font-size: 12.5px; line-height: 1.45; color: var(--cp-tenue); }

.cp-voz { border: 1px solid var(--cp-borde); border-radius: 10px; padding: 14px 16px; background: var(--cp-cabecera); }
.cp-voz-cabeza { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
.cp-voz-audio { width: 100%; height: 36px; display: block; margin-bottom: 10px; }
.cd-dark .cp-voz-audio { color-scheme: dark; }
.cp-voz-campos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 14px; }
.cp-voz-campos .cp-campo-grupo { margin-bottom: 0; }
.cp-rango { width: 100%; height: 36px; accent-color: var(--cp-acento); cursor: pointer; }
.cp-rango-extremos { display: flex; justify-content: space-between; font-size: 12px; color: var(--cp-tenue); margin-top: -6px; }

.cp-mas { margin-top: 14px; }
.cp-mas-resumen {
    display: flex; justify-content: space-between; align-items: baseline; gap: 10px; cursor: pointer; list-style: none;
    margin-bottom: 0; border-bottom: 0; padding-bottom: 0;
}
.cp-mas-resumen::-webkit-details-marker { display: none; }
.cp-mas-resumen::after { content: '\f078'; font-family: 'Font Awesome 5 Free'; font-weight: 900; font-size: 11px; color: var(--cp-tenue); transition: transform .15s; }
.cp-mas[open] .cp-mas-resumen { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--cp-borde); }
.cp-mas[open] .cp-mas-resumen::after { transform: rotate(180deg); }
.cp-mas-dato { margin-left: auto; font-weight: 400; }

/* ── Activar ──────────────────────────────────────────────────────────────── */
.cp-zona { margin-bottom: 4px; }
.cp-alcance {
    display: flex; flex-direction: column; gap: 2px; padding: 16px 18px; margin: 4px 0 16px; min-height: 96px; justify-content: center;
    border-radius: 10px; background: var(--cp-acento-suave); border: 1px solid transparent;
}
.cp-alcance-numero { font-size: 2.25rem; font-weight: 700; line-height: 1.1; letter-spacing: -.02em; color: var(--cp-tinta); }
.cp-alcance-texto { font-size: 14px; font-weight: 600; color: var(--cp-tinta); }
.cp-alcance-nota { margin-top: 8px; font-size: 12.5px; color: var(--cp-tinta); }
.cp-alcance-nota i { color: var(--cp-tenue); margin-right: 4px; }

.cp-ficha { margin: 0 0 14px; display: grid; gap: 10px; }
.cp-ficha > div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 12px; align-items: baseline; }
.cp-ficha dt { font-size: 12.5px; font-weight: 600; color: var(--cp-tenue); }
.cp-ficha dd { margin: 0; font-size: 13.5px; color: var(--cp-tinta); overflow-wrap: anywhere; }
.cp-cita { padding-left: 10px; border-left: 2px solid var(--cp-borde-campo); color: var(--cp-tinta); line-height: 1.5; white-space: pre-line; }
.cp-ficha--rejilla { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; }
.cp-ficha--rejilla > div { grid-template-columns: 1fr; gap: 2px; }
.cp-ficha-ancha { grid-column: 1 / -1; }

/* ── Revisar y llamar ─────────────────────────────────────────────────────── */
/* La lista manda; a la derecha, a cuántos se llama y la ficha de la campaña. */
.cp-revisar { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: start; }
.cp-revisar-lista { min-width: 0; }
.cp-revisar-lado { position: sticky; top: 0; min-width: 0; }
.cp-revisar-lado .cp-alcance { margin-top: 0; }
.cp-revisar-lado .cp-ficha > div { grid-template-columns: 1fr; gap: 2px; }

.cp-revisar-filtros { margin-bottom: 12px; padding: 10px 14px; border: 1px solid var(--cp-borde); border-radius: 10px; background: var(--cp-cabecera); }
.cp-revisar-quien { display: flex; align-items: center; gap: 4px 12px; flex-wrap: wrap; min-height: 30px; }
.cp-revisar-quien-etiqueta { font-size: 12.5px; font-weight: 600; color: var(--cp-tenue); }
.cp-revisar-quien-texto { flex: 1 1 260px; min-width: 0; font-size: 13.5px; font-weight: 500; color: var(--cp-tinta); }
.cp-revisar-editor { margin-top: 12px; padding-top: 14px; border-top: 1px solid var(--cp-borde); }

.cp-revisar-barra { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.cp-revisar-barra .cp-buscar { max-width: 420px; }

.cp-tabla--revisar thead th, .cp-tabla--revisar tbody td { padding: 9px 10px; }
.cp-tabla--revisar th:first-child, .cp-tabla--revisar td:first-child { padding-left: 12px; }
.cp-tabla--revisar th:last-child, .cp-tabla--revisar td:last-child { padding-right: 12px; }
.cp-tabla--revisar .cp-r-telefono, .cp-tabla--revisar .cp-r-caja { text-align: left; }
.cp-r-marca { width: 1%; }
.cp-r-todos { display: flex; align-items: center; gap: 10px; margin: 0; cursor: pointer; }
.cp-r-todos-texto { display: none; font-size: 13px; font-weight: 600; color: var(--cp-tinta); }
.cp-r-marca input { display: block; width: 15px; height: 15px; margin: 0; accent-color: var(--cp-acento); cursor: pointer; }
.cp-r-cliente { min-width: 200px; }
.cp-r-cliente-linea { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 8px; }
.cp-r-nombre { margin: 0; font-weight: 500; color: var(--cp-tinta); cursor: pointer; overflow-wrap: anywhere; }
.cp-r-numero { font-size: 12px; color: var(--cp-tenue); text-decoration: none; }
.cp-r-numero:hover { color: var(--cp-acento); text-decoration: underline; text-underline-offset: 3px; }
.cp-r-estado .cp-estado { padding: 2px 9px; font-size: 12px; }
/* Quitado: se queda en su lugar, en gris y dicho con palabras, para poder volver a marcarlo. */
.cp-tabla--revisar tbody tr.cp-fila-quitada td { background: var(--cp-cabecera); }
.cp-fila-quitada .cp-r-nombre { color: var(--cp-tenue); font-weight: 400; }
.cp-fila-quitada :is(.cp-r-telefono, .cp-r-caja, .cp-r-saldo) { color: var(--cp-tenue); }
.cp-r-quitado { display: block; margin-top: 2px; font-size: 12px; font-weight: 500; color: var(--cp-tenue); }
.cp-r-quitado i { font-size: 10.5px; margin-right: 2px; }
.cp-tabla--revisar .cp-vacio .cp-enlace { margin-top: 2px; }

/* ── Detalle ──────────────────────────────────────────────────────────────── */
.cp-detalle-cabeza { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.cp-segmentos--detalle { margin: 4px 0 12px; }
.cp-tabla--llamadas thead th, .cp-tabla--llamadas tbody td { padding: 10px; }
.cp-tabla--llamadas th:first-child, .cp-tabla--llamadas td:first-child { padding-left: 12px; }
.cp-tabla--llamadas th:last-child, .cp-tabla--llamadas td:last-child { padding-right: 12px; }
.cp-enlace-cliente { color: var(--cp-acento); font-weight: 500; text-decoration: none; }
.cp-enlace-cliente:hover { text-decoration: underline; text-underline-offset: 3px; }
.cp-pie--detalle { padding: 12px 0 0; border-top: 0; }
.cp-tabla-caja--borde { border: 1px solid var(--cp-borde); border-radius: 10px; }

/* ── Espacio mediano (laptop con menú, tablet): resumen 3 + 2, filtros en dos filas ── */
@container cp (max-width: 1019px) {
    .cp-resumen { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .cp-cifra { grid-column: span 2; }
    .cp-cifra:nth-child(n+4) { grid-column: span 3; }

    .cp-filtros {
        display: grid; grid-template-columns: minmax(0, 1fr) 190px auto; align-items: center;
        grid-template-areas: "buscar tipo exportar" "segmentos segmentos segmentos";
    }
    .cp-buscar { grid-area: buscar; max-width: none; }
    .cp-filtro-tipo { grid-area: tipo; }
    .cp-exportar { grid-area: exportar; margin-left: 0; }
    .cp-segmentos { grid-area: segmentos; justify-self: start; }
}

/* ── Cada campaña es una tarjeta cuando la tabla ya no cabe ───────────────── */
@container cp (max-width: 1019px) {
    .cp-tabla, .cp-tabla tbody { display: block; }
    .cp-tabla thead { display: none; }
    .cp-tabla tbody tr {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; padding: 14px 20px;
        grid-template-areas: "campana estado" "cuando cuando" "avance contestadas" "acciones acciones";
        border-bottom: 1px solid var(--cp-borde);
    }
    .cp-tabla tbody tr:last-child { border-bottom: 0; }
    .cp-tabla tbody td { display: block; padding: 0 !important; border: 0; }
    .cp-tabla tbody tr:hover td { background: transparent !important; }
    .cp-td-campana { grid-area: campana; min-width: 0; }
    .cp-td-estado { grid-area: estado; }
    .cp-td-cuando { grid-area: cuando; display: flex !important; gap: 8px; align-items: baseline; }
    .cp-td-cuando .cp-num { color: var(--cp-tenue); font-size: 12.5px; }
    .cp-td-avance { grid-area: avance; }
    .cp-td-contestadas { grid-area: contestadas; text-align: right; }
    .cp-td-acciones { grid-area: acciones; width: auto; }
    .cp-acciones { justify-content: flex-start; }
    .cp-hueco-accion, .cp-hueco-icono { display: none; }
    .cp-solo-movil { display: inline; }
    .cp-fila-vacia { display: block !important; }
    .cp-fila-esqueleto { grid-template-areas: "campana estado" "avance contestadas" !important; }
    .cp-fila-esqueleto .cp-td-cuando, .cp-fila-esqueleto .cp-td-acciones { display: none !important; }
}

/* ── Espacio de celular ───────────────────────────────────────────────────── */
@container cp (max-width: 599px) {
    .cp-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cp-cifra, .cp-cifra:nth-child(n+4) { grid-column: span 1; padding: 12px 14px; }
    .cp-cifra:first-child { grid-column: 1 / -1; }
    .cp-cifra-nota { white-space: normal; }

    .cp-panel-cabeza { padding-left: 14px; padding-right: 14px; }
    .cp-filtros {
        grid-template-columns: minmax(0, 1fr) auto; padding-left: 14px; padding-right: 14px;
        grid-template-areas: "buscar buscar" "segmentos segmentos" "tipo exportar";
    }
    /* Los cinco estados a la vista (antes se cortaban en «Borradore…») */
    .cp-segmentos { display: flex; flex-wrap: wrap; justify-self: stretch; }
    .cp-segmento { flex: 1 1 auto; justify-content: center; }

    .cp-tabla tbody tr { padding: 14px; }
    .cp-accion { flex: 1 1 auto; }
    .cp-barra { width: 72px; }
    .cp-pie { padding: 12px 14px; }
    .cp-pie > .cp-tenue { flex: 1 1 100%; }
    .cp-paginacion { width: 100%; justify-content: space-between; }
}

/* ── Diálogos (van en <body>, fuera de la página: miden la pantalla) ──────── */
@media (max-width: 1199px) {
    .cp-form { grid-template-columns: 1fr; gap: 20px; }
    /* Primero a cuántos se llama; la ficha en dos columnas */
    .cp-revisar { grid-template-columns: 1fr; gap: 16px; }
    .cp-revisar-lado { position: static; order: -1; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 0 20px; }
    .cp-revisar-lado .cp-alcance { margin-bottom: 0; }
}

/* Llamadas del detalle: tarjetas cuando el panel ya no cabe la tabla */
@media (max-width: 767px) {
    .cp-tabla--llamadas, .cp-tabla--llamadas tbody { display: block; }
    .cp-tabla--llamadas thead { display: none; }
    .cp-tabla--llamadas tbody tr {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 12px; padding: 12px;
        grid-template-areas: "cliente estado" "telefono intentos" "fecha saldo"; border-bottom: 1px solid var(--cp-borde);
    }
    .cp-tabla--llamadas tbody tr:last-child { border-bottom: 0; }
    .cp-tabla--llamadas tbody td { display: block; padding: 0 !important; border: 0; }
    .cp-tabla--llamadas tbody tr:hover td { background: transparent !important; }
    .cp-tabla--llamadas .cp-fila-vacia { display: block !important; }
    .cp-ll-cliente { grid-area: cliente; min-width: 0; }
    .cp-ll-estado { grid-area: estado; }
    .cp-ll-telefono { grid-area: telefono; color: var(--cp-tenue); text-align: left !important; }
    .cp-ll-intentos { grid-area: intentos; text-align: right; color: var(--cp-tenue); }
    .cp-ll-intentos::before { content: 'intento '; }
    .cp-ll-fecha { grid-area: fecha; }
    .cp-ll-saldo { grid-area: saldo; text-align: right; }

    /* Revisar: una tarjeta por cliente con su casilla a la izquierda */
    .cp-revisar-lado { grid-template-columns: 1fr; }
    .cp-revisar-lado .cp-ficha { display: none; }
    .cp-revisar-barra .cp-buscar { max-width: none; flex-basis: 100%; }
    .cp-tabla--revisar, .cp-tabla--revisar tbody, .cp-tabla--revisar thead { display: block; }
    .cp-tabla--revisar thead tr { display: block; padding: 8px 12px; border-bottom: 1px solid var(--cp-borde); }
    .cp-tabla--revisar thead th { display: none; padding: 0 !important; border: 0; background: none; }
    .cp-tabla--revisar thead th.cp-r-marca { display: block; width: auto; }
    .cp-r-todos { min-height: 32px; }
    .cp-r-todos input { width: 20px; height: 20px; }
    .cp-r-todos-texto { display: inline; }
    .cp-tabla--revisar tbody tr {
        display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: 4px 10px; padding: 12px;
        grid-template-areas: "marca cliente estado" "marca telefono saldo" "marca caja caja"; border-bottom: 1px solid var(--cp-borde);
    }
    .cp-tabla--revisar tbody tr:last-child { border-bottom: 0; }
    .cp-tabla--revisar tbody td { display: block; padding: 0 !important; border: 0; }
    .cp-tabla--revisar tbody tr:hover td { background: transparent !important; }
    .cp-tabla--revisar .cp-fila-vacia, .cp-tabla--revisar .cp-fila-esqueleto { display: block !important; padding: 12px; }
    .cp-tabla--revisar .cp-fila-esqueleto td { margin-bottom: 6px; }
    .cp-r-marca { grid-area: marca; width: auto; padding-top: 2px !important; }
    .cp-r-marca input { width: 20px; height: 20px; }
    .cp-r-cliente { grid-area: cliente; min-width: 0; }
    .cp-r-estado { grid-area: estado; }
    .cp-r-telefono { grid-area: telefono; color: var(--cp-tenue); text-align: left !important; }
    .cp-r-saldo { grid-area: saldo; text-align: right; }
    .cp-r-caja { grid-area: caja; color: var(--cp-tenue); text-align: left !important; }
}

/* ── Celular: todo a tamaño de dedo ───────────────────────────────────────── */
@media (max-width: 767px) {
    .cp-titulo { font-size: 1.25rem; }
    .cp-cabecera-acciones, .cp-cabecera-acciones .cp-boton { width: 100%; }
    .cp-cabecera-acciones .cp-boton { flex: 1 1 auto; }

    .cp-boton, .cp-icono, .cp-campo, .cp-segmento { min-height: 44px; }
    .cp-icono { width: 44px; height: 44px; }
    .cp-nombre { padding: 3px 0; }

    .cp-nombre-motivo { grid-template-columns: 1fr; }
    .cp-voz-campos { grid-template-columns: 1fr; gap: 12px; }
    .cp-par { grid-template-columns: 1fr; }
    .cp-ficha > div { grid-template-columns: 1fr; gap: 2px; }
    .cp-ficha--rejilla { grid-template-columns: 1fr; }
}
</style>
