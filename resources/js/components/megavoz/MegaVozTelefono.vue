<template>
    <div v-if="tieneTelefono || sinHttps" class="mv-wrap">
        <!-- Sin HTTPS: bubble visible pero deshabilitada, para que la ausencia
             del teléfono no se confunda con "no tengo extensión asignada". -->
        <div v-if="sinHttps" class="mv-panel-aviso" v-show="abierto">
            Este mini-teléfono necesita HTTPS.<br>
            Entra por <code>https://dev.meganett.com.mx</code>, no por la IP directa.
        </div>

        <!-- Panel para marcar (mientras hay llamada o tarjeta de después, manda la ventana de llamada) -->
        <transition name="mv-pop">
            <div v-if="! sinHttps && abierto && ! estadoLlamada && ! postLlamada" class="mv-panel">
                <div class="mv-head">
                    <span class="mv-yo">
                        {{ iniciales(nombre) || '?' }}
                        <span class="mv-dot" :class="'mv-dot--' + estado"></span>
                    </span>
                    <div class="mv-head-txt">
                        <div class="mv-titulo">{{ nombre }}</div>
                        <div class="mv-estado-txt">Ext. {{ String(numero).replace(/^web/, '') }} · {{ etiquetaEstado }}</div>
                    </div>
                    <button class="mv-close" @click="abierto = false" title="Cerrar">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <div class="mv-marcar">
                    <input class="mv-input" v-model="destino" placeholder="Número a marcar…"
                           @input="consultarDisponibilidad" @keyup.enter="llamar"
                           :disabled="estado !== 'registrado'">
                    <button class="mv-btn mv-btn--ok" @click="llamar"
                            :disabled="estado !== 'registrado' || ! puedeMarcar" title="Llamar">
                        <i class="fa fa-phone"></i>
                    </button>
                </div>
                <div v-if="destino" class="mv-disponibilidad" :class="'mv-disponibilidad--' + claseDisponibilidad">
                    <span class="mv-disp-dot"></span>{{ textoDisponibilidad }}
                </div>
            </div>
        </transition>

        <!-- Burbuja -->
        <button class="mv-bubble" :class="sinHttps ? 'mv-bubble--sinhttps' : 'mv-bubble--' + estado"
                @click="clicBurbuja"
                :title="sinHttps ? 'Requiere HTTPS' : (estadoLlamada ? 'Ver la llamada' : etiquetaEstado)">
            <i class="fa fa-phone"></i>
            <span v-if="estadoLlamada" class="mv-badge"></span>
        </button>

        <!-- Ventana de llamada, estilo teléfono: flota sin tapar la pantalla, se arrastra a
             cualquier lado y se puede minimizar para seguir trabajando. Aparece SIEMPRE que se
             marca, suena o hay llamada, y al colgar muestra ahí mismo lo de Tickets. -->
        <transition name="mv-ventana">
            <div v-if="faseVentana" ref="ventana" class="mv-call"
                 :class="['mv-call--' + faseVentana, { 'mv-call--mini': mini, 'mv-call--arrastrando': arrastre }]"
                 :style="estiloVentana" @pointerdown="empezarArrastre">
                <transition name="mv-fade" mode="out-in">
                    <!-- Minimizada: una barra con lo esencial -->
                    <div v-if="mini" key="mini" class="mv-mini">
                        <span class="mv-avatar mv-avatar--chico" :class="{ 'mv-avatar--sonando': sonando }">
                            <template v-if="inicialesRemoto">{{ inicialesRemoto }}</template>
                            <i v-else class="fa fa-user"></i>
                        </span>
                        <div class="mv-mini-txt">
                            <div class="mv-mini-nombre">{{ nombreRemoto }}</div>
                            <div class="mv-mini-estado">{{ textoEstado }}</div>
                        </div>
                        <template v-if="faseVentana === 'entrante'">
                            <button class="mv-redondo mv-redondo--chico mv-redondo--colgar" @click="colgar" title="Rechazar">
                                <i class="fa fa-phone-slash"></i>
                            </button>
                            <button class="mv-redondo mv-redondo--chico mv-redondo--contestar" @click="contestar" title="Contestar">
                                <i class="fa fa-phone"></i>
                            </button>
                        </template>
                        <template v-else-if="faseVentana !== 'fin'">
                            <button v-if="faseVentana === 'activa'" class="mv-redondo mv-redondo--chico"
                                    :class="{ 'mv-redondo--on': silenciado }" @click="toggleMute"
                                    :title="silenciado ? 'Activar micrófono' : 'Silenciar'">
                                <i :class="silenciado ? 'fa fa-microphone-slash' : 'fa fa-microphone'"></i>
                            </button>
                            <button class="mv-redondo mv-redondo--chico mv-redondo--colgar" @click="colgar" title="Colgar">
                                <i class="fa fa-phone-slash"></i>
                            </button>
                        </template>
                        <button class="mv-ctl" @click="minimizada = false" title="Ampliar">
                            <i class="fa fa-expand-alt"></i>
                        </button>
                    </div>

                    <!-- Ampliada -->
                    <div v-else :key="'grande-' + faseVentana" class="mv-grande">
                        <span class="mv-asa" aria-hidden="true"></span>
                        <div class="mv-barra">
                            <span class="mv-barra-txt"><i class="fa fa-phone-alt"></i> Llamada de voz</span>
                            <button v-if="faseVentana === 'post'" class="mv-ctl" @click="cerrarPostLlamada" title="Cerrar">
                                <i class="fa fa-times"></i>
                            </button>
                            <button v-else class="mv-ctl" @click="minimizada = true" title="Minimizar y seguir trabajando">
                                <i class="fa fa-minus"></i>
                            </button>
                        </div>

                        <div class="mv-centro">
                            <div class="mv-avatar" :class="{ 'mv-avatar--sonando': sonando, 'mv-avatar--fin': faseVentana === 'fin' || faseVentana === 'post' }">
                                <template v-if="sonando">
                                    <span class="mv-onda"></span>
                                    <span class="mv-onda mv-onda--2"></span>
                                </template>
                                <template v-if="inicialesRemoto">{{ inicialesRemoto }}</template>
                                <i v-else class="fa fa-user"></i>
                            </div>
                            <div class="mv-nombre">{{ nombreRemoto }}</div>
                            <div v-if="subRemoto" class="mv-sub">{{ subRemoto }}</div>
                            <div class="mv-estado" :class="{ 'mv-estado--reloj': faseVentana === 'activa' }">{{ textoEstado }}</div>
                        </div>

                        <!-- MegaVoz Fase 4 — ficha del cliente que llama, si se identificó -->
                        <div v-if="['entrante', 'saliente', 'activa'].includes(faseVentana) && (cargandoFicha || fichaCliente !== null)" class="mv-info">
                            <div v-if="cargandoFicha" class="mv-info-tenue">Buscando cliente…</div>
                            <template v-else-if="fichaCliente">
                                <div class="mv-info-fila">
                                    <span>Saldo</span>
                                    <b :class="fichaCliente.balance < 0 ? 'mv-saldo--debe' : 'mv-saldo--ok'">
                                        {{ formatoSaldo(fichaCliente.balance) }}
                                    </b>
                                </div>
                                <div class="mv-info-fila" v-if="fichaCliente.active_services.length">
                                    <span>Servicio</span>
                                    <b>{{ fichaCliente.active_services[0].description }}</b>
                                </div>
                                <div class="mv-info-fila">
                                    <span>Tickets abiertos</span>
                                    <b :class="fichaCliente.tickets_open > 0 ? 'mv-saldo--debe' : ''">{{ fichaCliente.tickets_open }}</b>
                                </div>
                                <div class="mv-info-acciones">
                                    <a :href="`/cliente/editar/${fichaCliente.id}`" target="_blank" class="mv-chip">
                                        <i class="fa fa-id-card"></i> Ver ficha
                                    </a>
                                    <a :href="`/tickets/crear/${fichaCliente.id}`" target="_blank" class="mv-chip mv-chip--ticket">
                                        <i class="fa fa-plus"></i> Crear ticket
                                    </a>
                                </div>
                            </template>
                            <div v-else class="mv-info-tenue">
                                Número no identificado ·
                                <a href="/tickets/crear" target="_blank" class="mv-link">Crear ticket</a>
                            </div>
                        </div>

                        <!-- Después de colgar con un cliente (Mostrador y quien atiende): lleva a
                             Tickets para registrar la llamada; si el número no es de ningún cliente,
                             ofrece darlo de alta y, al guardarlo, crear su ticket. -->
                        <div v-if="faseVentana === 'post'" class="mv-info mv-info--post">
                            <div v-if="postLlamada.buscando" class="mv-info-tenue">Buscando cliente…</div>
                            <template v-else-if="postLlamada.noEsCliente">
                                <div class="mv-info-tenue">Este número no es de ningún cliente.</div>
                                <button class="mv-boton mv-boton--alta" @click="darDeAltaCliente">
                                    <i class="fa fa-user-plus"></i> Dar de alta cliente
                                </button>
                                <div class="mv-info-nota">Al guardarlo te lleva a crear su ticket.</div>
                            </template>
                            <div v-if="postLlamada.cuenta !== null" class="mv-cuenta">
                                <div class="mv-cuenta-txt">Te llevo a Tickets en <b>{{ postLlamada.cuenta }}</b> s</div>
                                <div class="mv-cuenta-barra"><span :style="{ width: (postLlamada.cuenta / 5 * 100) + '%' }"></span></div>
                                <div class="mv-cuenta-acciones">
                                    <button class="mv-boton" @click="irATickets"><i class="fa fa-ticket-alt"></i> Ir ahora</button>
                                    <button class="mv-boton mv-boton--suave" @click="quedarme">Quedarme</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="faseVentana === 'entrante'" class="mv-controles">
                            <div class="mv-control">
                                <button class="mv-redondo mv-redondo--colgar" @click="colgar" title="Rechazar">
                                    <i class="fa fa-phone-slash"></i>
                                </button>
                                <span>Rechazar</span>
                            </div>
                            <div class="mv-control">
                                <button class="mv-redondo mv-redondo--contestar mv-redondo--brinca" @click="contestar" title="Contestar">
                                    <i class="fa fa-phone"></i>
                                </button>
                                <span>Contestar</span>
                            </div>
                        </div>
                        <div v-else-if="faseVentana === 'saliente'" class="mv-controles">
                            <div class="mv-control">
                                <button class="mv-redondo mv-redondo--colgar" @click="colgar" title="Colgar">
                                    <i class="fa fa-phone-slash"></i>
                                </button>
                                <span>Colgar</span>
                            </div>
                        </div>
                        <div v-else-if="faseVentana === 'activa'" class="mv-controles">
                            <div class="mv-control">
                                <button class="mv-redondo" :class="{ 'mv-redondo--on': silenciado }" @click="toggleMute"
                                        :title="silenciado ? 'Activar micrófono' : 'Silenciar'">
                                    <i :class="silenciado ? 'fa fa-microphone-slash' : 'fa fa-microphone'"></i>
                                </button>
                                <span>{{ silenciado ? 'Silenciado' : 'Silenciar' }}</span>
                            </div>
                            <div class="mv-control">
                                <button class="mv-redondo mv-redondo--colgar" @click="colgar" title="Colgar">
                                    <i class="fa fa-phone-slash"></i>
                                </button>
                                <span>Colgar</span>
                            </div>
                            <div class="mv-control">
                                <button class="mv-redondo" @click="minimizada = true" title="Minimizar y seguir trabajando">
                                    <i class="fa fa-compress-alt"></i>
                                </button>
                                <span>Minimizar</span>
                            </div>
                        </div>
                        <div v-else-if="faseVentana === 'fin'" class="mv-controles mv-controles--fin">
                            <span>Cerrando…</span>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>

        <audio ref="audioRemoto" autoplay></audio>
    </div>
</template>

<script>
import JsSIP from 'jssip';
import { markRaw, nextTick } from 'vue';
import { revisarNumero } from './numeroMarcado.js';

export default {
    name: 'MegaVozTelefono',

    data() {
        return {
            tieneTelefono: false,
            sinHttps: false,
            abierto: false,
            estado: 'inactivo', // inactivo | registrando | registrado | error
            numero: '',
            nombre: '',
            destino: '',
            ua: null,
            pcConfig: null,
            sesion: null,
            remotoId: '',
            silenciado: false,
            inicioLlamada: null,
            duracionTexto: '00:00',
            _timerId: null,
            // Estado de la llamada como dato reactivo PROPIO — null | 'saliente' |
            // 'entrante' | 'activa'. No se puede derivar leyendo this.sesion (ver
            // el porqué en iniciarUA: markRaw lo saca de la reactividad de Vue a
            // propósito, así que sesion.isEstablished() nunca dispara un
            // re-render cuando cambia solo por dentro).
            estadoLlamada: null,
            // Disponibilidad del NÚMERO QUE SE ESTÁ MARCANDO (no la propia) — null
            // = todavía sin consultar (o cambió el texto y no ha llegado la
            // respuesta), true/false = resultado real contra Asterisk.
            destinoDisponible: null,
            _dispDebounce: null,
            // MegaVoz Fase 4 — ficha del que llama/al que se llama. null = sin
            // buscar todavía (o remoto no parece número externo), false =
            // buscado y NO identificado, objeto = cliente encontrado.
            fichaCliente: null,
            cargandoFicha: false,
            // Modal en "cerrando…" — true mientras se muestra el motivo por
            // el que terminó la llamada (sin respuesta, colgaron, etc.)
            // antes de limpiar la sesión de verdad.
            finalizando: false,
            mensajeFinal: '',
            // Tono de "está timbrando" para llamadas salientes — ver
            // iniciarTonoLlamando(). Por SIP el que llama NUNCA recibe audio
            // real mientras el otro lado suena (eso solo empieza cuando
            // contestan) — el "tuut… tuut…" que se oye en un teléfono normal
            // lo genera el propio aparato/app que llama, no la central.
            _audioCtx: null,
            _ringInterval: null,
            // Blindaje contra doble clic en "Contestar" — ver contestar().
            _contestando: false,
            // Al colgar una llamada contestada con un número externo, quien
            // atiende clientes (MiTelefonoController::atiendeClientes) va a Tickets.
            ticketAlColgar: false,
            // { numero, cliente: {id, name}|null, noEsCliente, buscando, cuenta: s|null }
            postLlamada: null,
            _postIntervalo: null,
            // Ventana de llamada: minimizada (barra) o ampliada, dónde la dejó el usuario
            // ({x, y} en px, se recuerda en localStorage) y el arrastre en curso.
            minimizada: false,
            posVentana: null,
            arrastre: null,
            anchoPantalla: typeof window !== 'undefined' ? window.innerWidth : 1280,
        };
    },

    computed: {
        etiquetaEstado() {
            const mapa = {
                inactivo:   'Sin conexión',
                registrando:'Conectando…',
                registrado: 'En línea — listo para llamar',
                error:      'No se pudo conectar',
            };
            return mapa[this.estado] || this.estado;
        },
        // Lo escrito en «Número a marcar», ya limpio: sin espacios ni guiones y con +52/044 de
        // México reducidos a 10 dígitos. Ver numeroMarcado.js.
        marcado() {
            return revisarNumero(this.destino);
        },
        puedeMarcar() {
            return this.marcado.tipo === 'interno' || this.marcado.tipo === 'nacional';
        },
        claseDisponibilidad() {
            if (this.marcado.tipo === 'invalido') return 'no';
            if (this.marcado.tipo === 'nacional') return 'fuera';
            if (this.destinoDisponible === true) return 'ok';
            if (this.destinoDisponible === false) return 'no';
            return 'consultando';
        },
        // null | 'entrante' | 'saliente' | 'activa' | 'fin' | 'post'
        faseVentana() {
            if (this.estadoLlamada) return this.finalizando ? 'fin' : this.estadoLlamada;
            return this.postLlamada ? 'post' : null;
        },
        mini() {
            return this.minimizada && this.faseVentana !== 'post';
        },
        sonando() {
            return ! this.finalizando && (this.estadoLlamada === 'entrante' || this.estadoLlamada === 'saliente');
        },
        esMovil() {
            return this.anchoPantalla < 576;
        },
        numeroRemoto() {
            return this.faseVentana === 'post' ? (this.postLlamada?.numero || '') : this.remotoId;
        },
        clienteRemoto() {
            if (this.faseVentana === 'post') return this.postLlamada?.cliente || null;
            return this.fichaCliente && this.fichaCliente.id ? this.fichaCliente : null;
        },
        nombreRemoto() {
            return this.clienteRemoto?.name || this.etiquetaNumero(this.numeroRemoto) || 'Desconocido';
        },
        subRemoto() {
            if (this.clienteRemoto?.name) return this.etiquetaNumero(this.numeroRemoto);
            const noEsCliente = this.faseVentana === 'post' ? this.postLlamada?.noEsCliente : this.fichaCliente === false;
            return noEsCliente ? 'No es cliente' : '';
        },
        inicialesRemoto() {
            return this.clienteRemoto?.name ? this.iniciales(this.clienteRemoto.name) : '';
        },
        textoEstado() {
            switch (this.faseVentana) {
                case 'entrante': return 'Llamada entrante…';
                case 'saliente': return 'Llamando…';
                case 'activa':   return this.duracionTexto;
                case 'fin':      return this.mensajeFinal;
                case 'post':     return 'Llamada terminada' + (this.postLlamada?.duracion ? ` · ${this.postLlamada.duracion}` : '');
                default:         return '';
            }
        },
        estiloVentana() {
            if (! this.posVentana || this.esMovil) return {};
            return { left: `${this.posVentana.x}px`, top: `${this.posVentana.y}px`, right: 'auto', bottom: 'auto' };
        },
        textoDisponibilidad() {
            if (this.marcado.tipo === 'invalido') return this.marcado.motivo;
            if (this.marcado.tipo === 'nacional') return `Llamada a la calle · se marcará ${this.marcado.numero}`;
            if (this.destinoDisponible === true) return 'Disponible';
            if (this.destinoDisponible === false) return 'Sin conexión — puede que no conteste';
            return 'Consultando…';
        },
    },

    watch: {
        // Al aparecer o cambiar de tamaño, que no quede fuera de la pantalla.
        faseVentana(nueva, antes) {
            if (nueva && ! antes) nextTick(() => this.acotarVentana());
        },
        minimizada() {
            nextTick(() => this.acotarVentana());
        },
    },

    async mounted() {
        // WebRTC exige contexto seguro — sin HTTPS ni lo intenta. Se avisa en vez
        // de desaparecer en silencio (antes no se distinguía de "sin extensión").
        if (location.protocol !== 'https:') {
            this.sinHttps = true;
            return;
        }
        try {
            const r = await fetch('/voip/mi-telefono/credenciales', {
                headers: { 'Accept': 'application/json' },
            });
            const body = await r.json();
            if (! body.tiene_telefono) {
                return;
            }
            this.tieneTelefono = true;
            this.numero = body.numero;
            this.nombre = body.nombre;
            this.ticketAlColgar = !! body.ticket_al_colgar;
            try {
                const guardada = JSON.parse(localStorage.getItem('megavoz.ventana') || 'null');
                if (guardada && Number.isFinite(guardada.x) && Number.isFinite(guardada.y)) this.posVentana = guardada;
            } catch (e) { /* sin almacenamiento: posición por defecto */ }
            window.addEventListener('resize', this.alRedimensionar);
            this.iniciarUA(body);
        } catch (e) {
            // Sin teléfono asignado o error de red — el widget simplemente no aparece.
        }
    },

    beforeUnmount() {
        if (this.ua) this.ua.stop();
        window.removeEventListener('resize', this.alRedimensionar);
        this.soltarArrastre();
        clearInterval(this._timerId);
        clearTimeout(this._dispDebounce);
        clearInterval(this._postIntervalo);
        this.detenerTonoLlamando();
    },

    methods: {
        iniciarUA(cred) {
            const socket = new JsSIP.WebSocketInterface(cred.wss_url);
            socket.via_transport = 'wss';

            // ICE: SOLO el TURN propio (ver config/voip.php 'turn' y
            // docs/bitacora/2026-09-25-megavoz-turn-coturn.md) — YA NO se
            // agrega el STUN público de Google aparte. Un servidor TURN
            // también responde peticiones STUN normales (es un superset),
            // así que agregar los dos por separado solo suma un segundo
            // servidor externo al que esperar durante la reunión de
            // candidatos ICE — con uno propio, local, rápido (confirmado
            // con turnutils_uclient: <1s de conexión), ya no hace falta
            // esperar también a Google. Sospecha 25-sep-2026: las demoras
            // de 30-60s en conectar/contestar coinciden con tener DOS
            // servidores distintos que consultar en vez de uno solo.
            // Contraseña del TURN viene del backend (MiTelefonoController::
            // credenciales()), nunca hardcodeada aquí — mismo criterio que
            // el secret SIP. Sin TURN configurado (turn_username vacío),
            // el navegador se queda solo con candidatos locales — mismo
            // comportamiento de antes de conectar el TURN.
            const iceServers = [];
            if (cred.turn_url && cred.turn_username) {
                iceServers.push({
                    urls: cred.turn_url,
                    username: cred.turn_username,
                    credential: cred.turn_credential,
                });
            }
            // markRaw: mismo motivo que ua/sesion más abajo — pasar un
            // objeto reactivo de Vue a una API nativa del navegador
            // (RTCPeerConnection, vía JsSIP) puede reventar en silencio.
            // Sospecha real 25-sep-2026: la primera llamada probada tras
            // conectar el TURN nunca llegó a generar ni un INVITE en
            // Asterisk (el tono que se oía era el ringback LOCAL, que no
            // depende de que la llamada saliera de verdad) — encaja con
            // que ua.call() esté lanzando una excepción no capturada al
            // construir el RTCPeerConnection con este objeto todavía
            // reactivo.
            this.pcConfig = markRaw({ iceServers });

            // markRaw: JsSIP.UA trae propiedades internas no configurables — si Vue
            // lo vuelve reactivo (los objetos en data() lo son por defecto), leer
            // esas propiedades a través del Proxy revienta con "proxy invariant
            // violation" en cuanto arma el INVITE (JsSIP/vue-3 no combinan).
            this.ua = markRaw(new JsSIP.UA({
                sockets: [socket],
                uri: `sip:${cred.numero}@${cred.realm}`,
                password: cred.secret,
                display_name: cred.nombre,
                register: true,
                session_timers: false,
                // Sin esto, JsSIP nunca manda tráfico de mantenimiento sobre
                // el websocket — encontrado en vivo 24-sep-2026 (Irving↔Diana
                // probando llamadas reales): "Web socket closed abruptly" se
                // repite cada 1-2 minutos en el log de Asterisk durante TODA
                // la sesión, no solo durante llamadas. Si la desconexión cae
                // justo mientras una llamada está timbrando/conectando, la
                // llamada se muere con la conexión — coincide exacto con "se
                // cae a los 2 tonos". 30s mantiene el socket con actividad
                // regular, muy por debajo de cualquier timeout de inactividad
                // razonable (el de nginx para /ws es de 3600s).
                keepalive_interval: 30,
            }));

            this.estado = 'registrando';

            this.ua.on('registered', () => { this.estado = 'registrado'; });
            this.ua.on('unregistered', () => { this.estado = 'inactivo'; });
            this.ua.on('registrationFailed', () => { this.estado = 'error'; });

            // JsSIP dispara este MISMO evento para las dos direcciones —
            // entrante (originator 'remote') Y saliente (originator 'local',
            // disparado DENTRO de ua.call(), de forma síncrona, antes de que
            // esa función siquiera regrese). Antes `llamar()` fijaba
            // estadoLlamada='saliente' y ESTE handler lo pisaba con
            // 'entrante' sin mirar el originator — el bug real que reportó
            // David (marcar a 1001 se veía como "llamada entrante"). Un solo
            // dueño del estado para las dos direcciones, diferenciado por
            // `data.originator`, en vez de que `llamar()` y este handler se
            // pisen entre sí.
            this.ua.on('newRTCSession', (data) => {
                // Ya hay una sesión activa — no se apilan llamadas en este mini-teléfono.
                if (this.sesion) {
                    data.session.terminate();
                    return;
                }
                this.sesion = markRaw(data.session);
                this.remotoId = data.session.remote_identity?.uri?.user
                    || data.session.remote_identity?.display_name
                    || '—';
                this.cerrarPostLlamada();
                this.estadoLlamada = data.originator === 'local' ? 'saliente' : 'entrante';
                this.abierto = true;
                this.minimizada = false;
                this.engancharSesion();
                this.cargarFicha(this.remotoId);
                // Saliente: tono de "está timbrando" (ver iniciarTonoLlamando).
                // Entrante: hasta hoy NO sonaba NADA — el modal se abría en
                // silencio total. Si quien recibe la llamada no está viendo
                // la pantalla en ese instante exacto, nunca se entera — esto
                // es lo que David describió como "Irving llama y a Diana no
                // le suena nada, solo se le abre el modal" (24-sep-2026). Un
                // teléfono real SIEMPRE suena para avisar una llamada
                // entrante; aquí faltaba esa pieza por completo.
                if (data.originator === 'local') {
                    this.iniciarTonoLlamando();
                } else {
                    this.iniciarTimbreEntrante();
                }
            });

            this.ua.start();
        },

        engancharSesion() {
            // El track remoto puede llegar en cualquier momento del ciclo de la
            // llamada (a veces antes de "accepted") — escuchar el evento 'track'
            // de la conexión es lo único confiable; leer getReceivers() UNA vez
            // en accepted/confirmed se perdía el audio si el track llegaba
            // después de ese instante (el bug real: la llamada conectaba, pero
            // nunca sonaba).
            if (this.sesion.connection) {
                this.sesion.connection.addEventListener('track', this.onRemoteTrack);
            } else {
                this.sesion.on('peerconnection', (data) => {
                    data.peerconnection.addEventListener('track', this.onRemoteTrack);
                });
            }

            // Acota la reunión de rutas de red (ICE) antes de mandar la
            // respuesta SIP — JsSIP, por diseño, espera a que el navegador la
            // dé por TERMINADA (node_modules/jssip/lib/RTCSession.js,
            // _createLocalDescription()) y NO trae ningún límite de tiempo
            // propio. Si esa reunión se traba (red lenta llegando al TURN, o
            // el navegador nunca la declara "completa"), el que llama o
            // contesta se queda sin mandar NADA — el síntoma real probado en
            // vivo 25-sep: "le di contestar al toque y de todos modos se cae
            // a los 30/60s", con el log de Asterisk en silencio total de ese
            // lado durante toda la espera. `event.ready()` es el escape que
            // la propia librería expone para esto: se apura en cuanto
            // aparece una ruta ÚTIL (srflx/relay, la que sirve para conectar
            // fuera de la LAN), y como respaldo, se apura de todos modos a
            // los 3s con lo que haya — mejor una llamada con lo que se tenga
            // que una que nunca llega a mandarse.
            let iceResuelto = false;
            let iceTimer = null;
            this.sesion.on('icecandidate', (event) => {
                if (iceResuelto) return;
                const cand = event.candidate;
                const util = cand && cand.candidate && /\btyp (srflx|relay)\b/.test(cand.candidate);
                if (util) {
                    iceResuelto = true;
                    if (iceTimer) clearTimeout(iceTimer);
                    event.ready();
                    return;
                }
                if (! iceTimer) {
                    iceTimer = setTimeout(() => {
                        if (iceResuelto) return;
                        iceResuelto = true;
                        event.ready();
                    }, 3000);
                }
            });

            // 'accepted'/'confirmed': el momento exacto en que cada uno dispara
            // difiere según quién llamó y quién contestó — se escuchan los dos
            // y se marca "activa" una sola vez (idempotente) con lo que llegue
            // primero, en vez de apostarle a uno solo de los dos eventos.
            const marcarActiva = () => {
                this.detenerTonoLlamando();
                if (this.estadoLlamada === 'activa') return;
                this.estadoLlamada = 'activa';
                this.inicioLlamada = Date.now();
                this._timerId = setInterval(this.tick, 1000);
            };
            this.sesion.on('accepted', marcarActiva);
            this.sesion.on('confirmed', marcarActiva);
            // Contestar pide el micrófono en ese momento (contestar() ->
            // session.answer({mediaConstraints:{audio:true}})) — si el
            // navegador no tiene permiso (nunca se concedió, se negó, o el
            // usuario tardó y el navegador lo descartó), JsSIP dispara ESTE
            // evento y la llamada muere ahí mismo, silenciosa: sin esto el
            // que contesta solo ve "se cortó" sin ninguna pista de por qué
            // (investigando el reporte de Irving/David 24-sep: "se corta
            // cuando contesta" — no se pudo reproducir en vivo esta sesión
            // por falta de navegador, este es el hueco más probable
            // encontrado revisando el código: el mismo síntoma exacto que
            // produciría un permiso de micrófono denegado/ignorado).
            this.sesion.on('getusermediafailed', () => this.terminarConMensaje('No se pudo acceder al micrófono — revisa el permiso en el navegador'));
            this.sesion.on('ended', () => {
                // Se lee ANTES de limpiar la sesión (limpiarSesion borra número y ficha).
                const post = this.datosPostLlamada();
                this.terminarConMensaje('Llamada finalizada', post ? () => this.iniciarPostLlamada(post) : null);
            });
            this.sesion.on('failed', (data) => this.terminarConMensaje(this.mensajeDeFallo(data)));
        },

        onRemoteTrack(event) {
            if (! this.$refs.audioRemoto) return;
            const stream = event.streams?.[0]
                || new MediaStream([event.track]);
            this.$refs.audioRemoto.srcObject = stream;
            // autoplay puede quedar pausado si el navegador aún no vio una
            // interacción del usuario en ESTE documento — marcar (el clic para
            // llamar ya cuenta como interacción, pero por si acaso).
            this.$refs.audioRemoto.play().catch(() => {});
        },

        tick() {
            if (! this.inicioLlamada) return;
            const s = Math.floor((Date.now() - this.inicioLlamada) / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2, '0');
            const ss = String(s % 60).padStart(2, '0');
            this.duracionTexto = `${mm}:${ss}`;
        },

        llamar() {
            if (! this.ua || ! this.puedeMarcar) return;
            const numero = this.marcado.numero;
            // Todo lo demás (asignar this.sesion, estadoLlamada='saliente',
            // remotoId, engancharSesion, la ficha) lo hace el handler de
            // 'newRTCSession' de arriba — dispara síncrono dentro de
            // ua.call(), así que duplicarlo aquí es justo lo que causaba el
            // bug del estado pisado.
            try {
                this.ua.call(`sip:${numero}@${location.hostname}`, {
                    mediaConstraints: { audio: true, video: false },
                    pcConfig: this.pcConfig,
                });
            } catch (err) {
                // Antes esto podía fallar en silencio (ej. RTCPeerConnection
                // rechazando un pcConfig mal formado) — el tono local de
                // "está timbrando" ni se entera, así que parecía que la
                // llamada salió cuando en realidad nunca llegó al servidor.
                console.error('MegaVoz: llamar() falló', err);
                alert('No se pudo iniciar la llamada: ' + err.message);
            }
        },

        // MegaVoz Fase 4 — solo se busca si el remoto PARECE un número externo
        // real (>=7 dígitos): una extensión interna de 3-4 dígitos nunca va a
        // hacer match en client_main_information, y mostrar "no identificado"
        // en cada llamada entre compañeros sería puro ruido.
        async cargarFicha(numero) {
            this.fichaCliente = null;
            const soloDigitos = (numero || '').replace(/\D/g, '');
            if (soloDigitos.length < 7) return;

            this.cargandoFicha = true;
            try {
                const r = await fetch(`/voip/mi-telefono/ficha/${encodeURIComponent(numero)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const body = await r.json();
                this.fichaCliente = body.found ? body : false;
            } catch {
                this.fichaCliente = false;
            } finally {
                this.cargandoFicha = false;
            }
        },

        formatoSaldo(balance) {
            const n = Number(balance) || 0;
            return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },

        consultarDisponibilidad() {
            clearTimeout(this._dispDebounce);
            const numero = this.destino;
            const marcado = revisarNumero(numero);
            // Solo las extensiones tienen «conectado / sin conexión»: un número de fuera no
            // es un teléfono de la central y preguntarle a ARI siempre diría «sin conexión».
            if (marcado.tipo !== 'interno') {
                this.destinoDisponible = null;
                return;
            }
            this.destinoDisponible = null; // "Consultando…" mientras llega la respuesta
            this._dispDebounce = setTimeout(async () => {
                // El texto pudo cambiar mientras esperaba el debounce — no pisar
                // el resultado de un número que ya no es el que se ve en pantalla.
                if (numero !== this.destino) return;
                try {
                    const r = await fetch(`/voip/mi-telefono/disponibilidad/${encodeURIComponent(marcado.numero)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const body = await r.json();
                    if (numero === this.destino) this.destinoDisponible = body.disponible;
                } catch {
                    if (numero === this.destino) this.destinoDisponible = null;
                }
            }, 450);
        },

        contestar() {
            if (! this.sesion) return;
            // Blindaje contra doble clic — encontrado en vivo 25-sep-2026:
            // "Invalid status: 5" de JsSIP (RTCSession ya estaba en
            // STATUS_ANSWERED cuando se llamó answer() otra vez) — un
            // segundo clic mientras la primera llamada a answer() seguía en
            // curso (pidiendo el micrófono, armando el SDP) reintentaba
            // sobre una sesión que ya iba a medias. Sin el guard, ese
            // segundo intento tronaba y colgaba la llamada real.
            if (this._contestando) return;
            this._contestando = true;

            // Corta el timbre de aviso AQUÍ, en el clic mismo — no esperar a
            // los eventos 'accepted'/'confirmed' de la sesión (que ya lo
            // hacían vía marcarActiva(), pero con margen para que quede
            // sonando de más mientras la llamada termina de conectar).
            // Encontrado en vivo 24-sep-2026: David probando el timbre
            // entrante nuevo reportó que al contestar se escuchaba algo
            // "estilo música de espera" — coincide con el propio timbre
            // (dos ráfagas repetidas cada 3s) quedándose sonando de más.
            this.detenerTonoLlamando();
            try {
                this.sesion.answer({ mediaConstraints: { audio: true, video: false }, pcConfig: this.pcConfig });
            } catch (err) {
                console.error('MegaVoz: contestar() falló', err);
                alert('No se pudo contestar la llamada: ' + err.message);
                this._contestando = false;
            }
        },

        colgar() {
            if (this.sesion) this.sesion.terminate();
        },

        toggleMute() {
            if (! this.sesion) return;
            if (this.silenciado) {
                this.sesion.unmute({ audio: true });
            } else {
                this.sesion.mute({ audio: true });
            }
            this.silenciado = ! this.silenciado;
        },

        // Muestra el motivo por el que terminó la llamada (sin respuesta,
        // colgaron, cancelada…) un par de segundos antes de cerrar el modal
        // de verdad — antes desaparecía sin avisar nada, y con una llamada
        // saliente sin respuesta eso se veía como que el mini-teléfono se
        // había quedado colgado.
        terminarConMensaje(mensaje, despues = null) {
            clearInterval(this._timerId);
            this._timerId = null;
            this.detenerTonoLlamando();
            this.mensajeFinal = mensaje;
            this.finalizando = true;
            setTimeout(() => {
                this.limpiarSesion();
                if (despues) despues();
            }, 2000);
        },

        // Cadencia clásica de ringback (440Hz+480Hz, 2s tono / 4s silencio) —
        // sintetizado con Web Audio, sin depender de ningún archivo de audio.
        // Arranca dentro del mismo clic de "Llamar" (o del disparo síncrono
        // de newRTCSession que provoca), así que no choca con las políticas
        // de autoplay del navegador (exigen que el audio arranque a partir
        // de una interacción real del usuario, y esto lo es).
        iniciarTonoLlamando() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const ctx = new Ctx();
                // Defensivo: algunos navegadores crean el AudioContext en
                // estado "suspended" incluso dentro del mismo gesto de clic
                // (depende del navegador/versión) — sin resume() el tono
                // queda armado pero MUDO, sin ningún error visible (el
                // try/catch de aquí abajo no lo atrapa porque no lanza
                // excepción, simplemente no suena). resume() sobre un
                // contexto que ya está "running" no hace nada, así que es
                // seguro llamarlo siempre.
                ctx.resume().catch(() => {});

                const gain = ctx.createGain();
                gain.gain.value = 0;
                gain.connect(ctx.destination);

                [440, 480].forEach((freq) => {
                    const osc = ctx.createOscillator();
                    osc.frequency.value = freq;
                    osc.connect(gain);
                    osc.start();
                });

                const ciclo = () => {
                    gain.gain.setValueAtTime(0.12, ctx.currentTime);
                    gain.gain.setValueAtTime(0, ctx.currentTime + 2);
                };
                ciclo();

                this._audioCtx = ctx;
                this._ringGain = gain;
                this._ringInterval = setInterval(ciclo, 6000);
            } catch {
                // Sin Web Audio (navegador viejo o autoplay bloqueado) — la
                // llamada sigue igual, solo sin el tono.
            }
        },

        // Llamada ENTRANTE — timbre de aviso para quien recibe. Antes
        // (hasta 24-sep-2026) no existía nada de esto: solo se abría el
        // modal, sin ningún sonido — si la persona no estaba viendo la
        // pantalla justo en ese momento, nunca se enteraba de la llamada.
        // Mismo mecanismo de síntesis que iniciarTonoLlamando() (Web
        // Audio, sin archivo), pero con cadencia de "ring-ring" (dos
        // ráfagas cortas) en vez del tono largo de "está timbrando" —
        // para que suenen distinguibles entre sí.
        iniciarTimbreEntrante() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const ctx = new Ctx();
                ctx.resume().catch(() => {});

                const gain = ctx.createGain();
                gain.gain.value = 0;
                gain.connect(ctx.destination);

                [440, 480].forEach((freq) => {
                    const osc = ctx.createOscillator();
                    osc.frequency.value = freq;
                    osc.connect(gain);
                    osc.start();
                });

                const ciclo = () => {
                    const t = ctx.currentTime;
                    // Dos ráfagas de 0.4s separadas por 0.2s de silencio.
                    gain.gain.setValueAtTime(0.15, t);
                    gain.gain.setValueAtTime(0, t + 0.4);
                    gain.gain.setValueAtTime(0.15, t + 0.6);
                    gain.gain.setValueAtTime(0, t + 1.0);
                };
                ciclo();

                this._audioCtx = ctx;
                this._ringGain = gain;
                this._ringInterval = setInterval(ciclo, 3000);
            } catch {
                // Sin Web Audio — la llamada sigue igual, solo sin timbre.
            }
        },

        detenerTonoLlamando() {
            clearInterval(this._ringInterval);
            this._ringInterval = null;
            if (this._audioCtx) {
                this._audioCtx.close().catch(() => {});
                this._audioCtx = null;
            }
        },

        mensajeDeFallo(data) {
            const mapa = {
                'No Answer':        'No contestaron',
                'Request Timeout':  'No contestaron',
                'Busy':             'Ocupado',
                'Rejected':         'Llamada rechazada',
                'Canceled':         'Llamada cancelada',
                'Unavailable':      'No disponible',
                // JsSIP también puede reportar el fallo de micrófono aquí
                // (aparte del evento dedicado 'getusermediafailed' de
                // arriba) — mismo mensaje en los dos casos.
                'USER_DENIED_MEDIA_ACCESS': 'No se pudo acceder al micrófono — revisa el permiso en el navegador',
                'RTP_TIMEOUT':      'Se perdió la conexión de audio (red inestable)',
            };
            return mapa[data?.cause] || 'No se pudo completar la llamada';
        },

        // Solo llamadas que de verdad se platicaron (estaban 'activa') con un número
        // externo: las internas entre compañeros y las que no se contestaron no cuentan.
        datosPostLlamada() {
            if (! this.ticketAlColgar || this.estadoLlamada !== 'activa') return null;
            const numero = (this.remotoId || '').replace(/\D/g, '');
            if (numero.length < 7) return null;
            const f = this.fichaCliente;
            return {
                numero,
                cliente: f && f.id ? { id: f.id, name: f.name } : null,
                noEsCliente: f === false,
                duracion: this.duracionTexto,
            };
        },

        async iniciarPostLlamada(post) {
            this.cerrarPostLlamada();
            this.minimizada = false;
            this.postLlamada = { ...post, buscando: false, cuenta: 5 };
            this._postIntervalo = setInterval(() => {
                if (! this.postLlamada || this.postLlamada.cuenta === null) return;
                this.postLlamada.cuenta -= 1;
                if (this.postLlamada.cuenta <= 0) this.irATickets();
            }, 1000);

            // Si la ficha no alcanzó a llegar durante la llamada, se busca ahora.
            if (! post.cliente && ! post.noEsCliente) {
                this.postLlamada.buscando = true;
                try {
                    const r = await fetch(`/voip/mi-telefono/ficha/${encodeURIComponent(post.numero)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const body = await r.json();
                    if (! this.postLlamada) return;
                    if (body.found) this.postLlamada.cliente = { id: body.id, name: body.name };
                    else this.postLlamada.noEsCliente = true;
                } catch (e) {
                    // Sin datos del cliente se sigue igual: igual llega a Tickets.
                } finally {
                    if (this.postLlamada) this.postLlamada.buscando = false;
                }
            }
        },

        detenerCuenta() {
            clearInterval(this._postIntervalo);
            this._postIntervalo = null;
            if (this.postLlamada) this.postLlamada.cuenta = null;
        },

        irATickets() {
            this.detenerCuenta();
            // La tarjeta sigue a la vista si falta dar de alta al cliente.
            if (! this.postLlamada?.noEsCliente) this.postLlamada = null;
            this.abierto = false;
            this.navegar('/tickets/abiertos');
        },

        quedarme() {
            this.detenerCuenta();
            if (! this.postLlamada?.noEsCliente) this.postLlamada = null;
        },

        darDeAltaCliente() {
            const numero = this.postLlamada?.numero || '';
            this.cerrarPostLlamada();
            this.abierto = false;
            this.navegar(`/cliente/crear?telefono=${encodeURIComponent(numero)}&despues=ticket`);
        },

        cerrarPostLlamada() {
            clearInterval(this._postIntervalo);
            this._postIntervalo = null;
            this.postLlamada = null;
        },

        // Navegación SPA (el teléfono vive fuera de #init-vue y no se recarga);
        // si spa-nav no está, carga normal: la llamada ya terminó.
        navegar(ruta) {
            const url = new URL(ruta, window.location.origin).href;
            if (typeof window.__spaNavigate === 'function') {
                window.__spaNavigate(url, true);
            } else {
                window.location.href = url;
            }
        },

        numeroLegible(n) {
            const d = String(n || '').replace(/\D/g, '');
            return d.length === 10 ? `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}` : n;
        },

        clicBurbuja() {
            if (this.estadoLlamada) {
                this.minimizada = false;
            } else if (this.postLlamada) {
                this.cerrarPostLlamada();
                this.abierto = true;
            } else {
                this.abierto = ! this.abierto;
            }
        },

        iniciales(nombre) {
            return String(nombre || '').trim().split(/\s+/).filter(Boolean).slice(0, 2)
                .map((p) => p[0]).join('').toUpperCase();
        },

        // Número externo legible ("55 1234 5678") o extensión interna ("Ext. 1001").
        etiquetaNumero(n) {
            const d = String(n || '').replace(/\D/g, '');
            if (! n) return '';
            if (d.length >= 7) return this.numeroLegible(d);
            return `Ext. ${String(n).replace(/^web/, '')}`;
        },

        // Arrastrar la ventana desde cualquier parte que no sea un botón o liga.
        empezarArrastre(e) {
            if (this.esMovil || (e.pointerType === 'mouse' && e.button !== 0)) return;
            if (e.target.closest('button, a, input, audio')) return;
            const caja = this.$refs.ventana?.getBoundingClientRect();
            if (! caja) return;
            this.arrastre = { dx: e.clientX - caja.left, dy: e.clientY - caja.top, seleccion: document.body.style.userSelect };
            // Que al pasar sobre la página no se vaya seleccionando su texto.
            document.body.style.userSelect = 'none';
            window.addEventListener('pointermove', this.moverArrastre);
            window.addEventListener('pointerup', this.soltarArrastre);
            window.addEventListener('pointercancel', this.soltarArrastre);
            e.preventDefault();
        },

        moverArrastre(e) {
            if (! this.arrastre) return;
            this.posVentana = this.acotar(e.clientX - this.arrastre.dx, e.clientY - this.arrastre.dy);
        },

        soltarArrastre() {
            window.removeEventListener('pointermove', this.moverArrastre);
            window.removeEventListener('pointerup', this.soltarArrastre);
            window.removeEventListener('pointercancel', this.soltarArrastre);
            if (! this.arrastre) return;
            document.body.style.userSelect = this.arrastre.seleccion || '';
            this.arrastre = null;
            this.guardarPosicion();
        },

        acotar(x, y) {
            const el = this.$refs.ventana;
            const ancho = el?.offsetWidth || 340;
            const alto = el?.offsetHeight || 480;
            const margen = 8;
            return {
                x: Math.round(Math.min(Math.max(margen, x), Math.max(margen, window.innerWidth - ancho - margen))),
                y: Math.round(Math.min(Math.max(margen, y), Math.max(margen, window.innerHeight - alto - margen))),
            };
        },

        acotarVentana() {
            if (this.posVentana) this.posVentana = this.acotar(this.posVentana.x, this.posVentana.y);
        },

        guardarPosicion() {
            try {
                if (this.posVentana) localStorage.setItem('megavoz.ventana', JSON.stringify(this.posVentana));
            } catch (e) { /* sin almacenamiento: solo dura esta página */ }
        },

        alRedimensionar() {
            this.anchoPantalla = window.innerWidth;
            this.acotarVentana();
        },

        limpiarSesion() {
            clearInterval(this._timerId);
            this._timerId = null;
            this.detenerTonoLlamando();
            this.inicioLlamada = null;
            this.duracionTexto = '00:00';
            this.silenciado = false;
            this.sesion = null;
            this.estadoLlamada = null;
            this.remotoId = '';
            this.destino = '';
            this.destinoDisponible = null;
            this.fichaCliente = null;
            this.cargandoFicha = false;
            this.finalizando = false;
            this._contestando = false;
            this.mensajeFinal = '';
            if (this.$refs.audioRemoto) this.$refs.audioRemoto.srcObject = null;
        },
    },
};
</script>

<style scoped>
/* z-index alto a propósito: help-float-btn (ayuda) usa 9997 — más que este
   widget antes (1039). Sin confirmar overlap real, pero es la sospecha más
   probable del botón "solo clickeable en una esquina" que reportó David, y
   subirlo no tiene downside (este widget SIEMPRE debe ganar sobre paneles
   informativos de fondo, nunca al revés). */
.mv-wrap { position: fixed; right: 160px; bottom: 24px; z-index: 10050; }

/* ── Burbuja ─────────────────────────────────────────────────────────── */
.mv-bubble {
    width: 54px; height: 54px; border-radius: 50%;
    border: none; cursor: pointer; position: relative;
    display: flex; align-items: center; justify-content: center;
    font-size: 21px; color: #fff;
    background: #64748b;
    box-shadow: 0 4px 14px rgba(0,0,0,.28);
    transition: background .2s, transform .15s ease, box-shadow .2s;
}
.mv-bubble:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.3); }
.mv-bubble:active { transform: scale(.94); }
.mv-bubble--registrando { background: #d97706; }
.mv-bubble--registrado  { background: #16a34a; }
.mv-bubble--error       { background: #dc2626; }
.mv-bubble--sinhttps    { background: #94a3b8; }

.mv-badge {
    position: absolute; top: -2px; right: -2px; width: 14px; height: 14px;
    border-radius: 50%; background: #ef4444; border: 2px solid #fff;
    animation: mv-pulse 1s infinite;
}
@keyframes mv-pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.3); }
}

/* El ícono (FontAwesome, <i>) dentro de un botón puede no repasar el clic a
   su padre de forma confiable en algunos navegadores — el reporte real de
   David: "solo funciona el clic en la esquina" de la burbuja, exactamente
   este patrón. pointer-events:none saca al ícono de la jugada del todo: el
   clic SIEMPRE cae directo en el botón, sin importar dónde del ícono se
   haga clic. */
.mv-bubble i, .mv-btn i, .mv-close i, .mv-ctl i, .mv-redondo i, .mv-boton i { pointer-events: none; }

/* ── Panel para marcar ───────────────────────────────────────────────── */
.mv-panel {
    position: absolute; bottom: 66px; right: 0; width: 300px;
    background: #fff; border-radius: 18px; padding: 16px;
    box-shadow: 0 16px 40px rgba(0,0,0,.22);
    font-size: 13.5px; color: #1f2937;
    transform-origin: bottom right;
}
.mv-panel-aviso {
    position: absolute; bottom: 66px; right: 0; width: 220px;
    background: #1f2937; color: #fff; border-radius: 12px; padding: 12px;
    font-size: 12.5px; line-height: 1.5; box-shadow: 0 10px 30px rgba(0,0,0,.2);
}
.mv-panel-aviso code { color: #93c5fd; }
.mv-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.mv-yo {
    position: relative; width: 40px; height: 40px; border-radius: 50%; flex: none;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(145deg, #2a8f7f, #1b5e55); color: #fff;
    font-weight: 700; font-size: 14px;
}
.mv-yo .mv-dot { position: absolute; right: -1px; bottom: -1px; border: 2px solid #fff; width: 12px; height: 12px; }
.mv-head-txt { flex: 1; min-width: 0; }
.mv-titulo { font-weight: 700; font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mv-estado-txt { color: #6b7280; font-size: 12px; }
.mv-close {
    width: 30px; height: 30px; border-radius: 50%; border: none; flex: none;
    background: transparent; color: #9ca3af; cursor: pointer; transition: background .15s, color .15s;
}
.mv-close:hover { background: #f3f4f6; color: #4b5563; }

.mv-dot { width: 9px; height: 9px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.mv-dot--registrando { background: #d97706; }
.mv-dot--registrado  { background: #16a34a; }
.mv-dot--error       { background: #dc2626; }

.mv-marcar { display: flex; gap: 8px; }
.mv-input {
    flex: 1; min-width: 0; border: 1px solid #d1d5db; border-radius: 999px;
    padding: 9px 16px; font-size: 14px; outline: none; transition: border-color .15s, box-shadow .15s;
}
.mv-input:focus { border-color: #25d366; box-shadow: 0 0 0 3px rgba(37,211,102,.18); }
.mv-btn {
    width: 42px; height: 42px; border-radius: 50%; border: none; flex: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; background: #e5e7eb; color: #374151;
    transition: transform .15s ease, filter .15s;
}
.mv-btn:active:not(:disabled) { transform: scale(.92); }
.mv-btn--ok { background: #25d366; color: #fff; }
.mv-btn--ok:hover:not(:disabled) { filter: brightness(1.05); }
.mv-btn:disabled { opacity: .4; cursor: not-allowed; }
.mv-disponibilidad {
    display: flex; align-items: center; gap: 6px;
    font-size: 11.5px; margin: 8px 0 0 6px; color: #6b7280;
}
.mv-disp-dot { width: 7px; height: 7px; border-radius: 50%; background: #9ca3af; flex-shrink: 0; }
.mv-disponibilidad--ok .mv-disp-dot { background: #16a34a; }
.mv-disponibilidad--ok { color: #16a34a; }
.mv-disponibilidad--no .mv-disp-dot { background: #d97706; }
.mv-disponibilidad--no { color: #b45309; }
.mv-disponibilidad--fuera .mv-disp-dot { background: #2563eb; }
.mv-disponibilidad--fuera { color: #2563eb; }

/* ── Ventana de llamada (estilo teléfono) ────────────────────────────── */
.mv-call {
    position: fixed; right: 24px; bottom: 96px; z-index: 10052;
    width: 340px; border-radius: 26px; overflow: hidden;
    color: #e9edef;
    background: radial-gradient(130% 75% at 50% 0%, #1f5a50 0%, #133a35 42%, #0b141a 100%);
    box-shadow: 0 24px 60px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06);
    touch-action: none; user-select: none; cursor: grab;
}
.mv-call--arrastrando { cursor: grabbing; box-shadow: 0 30px 70px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.1); }
.mv-call--mini { width: 340px; border-radius: 999px; }
.mv-call--fin, .mv-call--post { background: radial-gradient(130% 75% at 50% 0%, #2b3a40 0%, #1a2529 45%, #0b141a 100%); }

.mv-grande { position: relative; }
.mv-asa {
    position: absolute; top: 8px; left: 50%; transform: translateX(-50%);
    width: 38px; height: 4px; border-radius: 2px; background: rgba(255,255,255,.22);
}
.mv-barra { display: flex; align-items: center; gap: 8px; padding: 18px 14px 0 18px; }
.mv-barra-txt { flex: 1; display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: rgba(233,237,239,.7); }
.mv-ctl {
    width: 32px; height: 32px; border-radius: 50%; border: none; flex: none;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.08); color: #e9edef; cursor: pointer; font-size: 13px;
    transition: background .15s, transform .15s;
}
.mv-ctl:hover { background: rgba(255,255,255,.18); }
.mv-ctl:active { transform: scale(.9); }

.mv-centro { text-align: center; padding: 22px 22px 4px; }
.mv-avatar {
    position: relative; width: 108px; height: 108px; border-radius: 50%; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 38px; font-weight: 600; color: #fff;
    background: linear-gradient(145deg, #2a8f7f, #1b5e55);
    box-shadow: 0 10px 28px rgba(0,0,0,.35);
}
.mv-avatar--fin { filter: grayscale(.7); opacity: .85; }
.mv-onda {
    position: absolute; inset: 0; border-radius: 50%;
    border: 2px solid rgba(37,211,102,.6);
    animation: mv-onda 2s ease-out infinite;
}
.mv-onda--2 { animation-delay: 1s; }
@keyframes mv-onda {
    from { transform: scale(1); opacity: .9; }
    to   { transform: scale(1.8); opacity: 0; }
}
.mv-nombre { font-size: 22px; font-weight: 600; color: #fff; line-height: 1.25; overflow-wrap: anywhere; }
.mv-sub { font-size: 14px; color: rgba(233,237,239,.7); margin-top: 3px; }
.mv-estado { margin-top: 10px; font-size: 14px; color: rgba(233,237,239,.8); }
.mv-estado--reloj { font-variant-numeric: tabular-nums; font-size: 17px; color: #25d366; letter-spacing: .03em; }

.mv-info {
    margin: 14px 16px 0; padding: 12px 14px; border-radius: 16px;
    background: rgba(255,255,255,.07); font-size: 13px;
}
.mv-info-fila { display: flex; justify-content: space-between; gap: 10px; padding: 3px 0; color: rgba(233,237,239,.7); }
.mv-info-fila b { color: #fff; font-weight: 600; text-align: right; }
.mv-info-fila b.mv-saldo--debe { color: #ff8a80; }
.mv-info-fila b.mv-saldo--ok   { color: #6ee7a8; }
.mv-info-acciones { display: flex; gap: 8px; margin-top: 10px; }
.mv-chip {
    flex: 1; text-align: center; font-size: 12.5px; font-weight: 600; text-decoration: none;
    padding: 8px 10px; border-radius: 999px; color: #e9edef; background: rgba(255,255,255,.1);
    transition: background .15s;
}
.mv-chip:hover { background: rgba(255,255,255,.18); color: #fff; }
.mv-chip--ticket { background: rgba(37,211,102,.2); color: #8af0b6; }
.mv-chip--ticket:hover { background: rgba(37,211,102,.3); color: #b5f7d0; }
.mv-info-tenue { text-align: center; color: rgba(233,237,239,.68); line-height: 1.5; }
.mv-link { color: #8af0b6; font-weight: 600; }
.mv-link:hover { color: #b5f7d0; }
.mv-info-nota { font-size: 11.5px; text-align: center; color: rgba(233,237,239,.6); margin-top: 6px; }
.mv-info--post { margin-bottom: 22px; padding: 14px; }

.mv-cuenta { margin-top: 12px; }
.mv-info-tenue + .mv-cuenta, .mv-info-nota + .mv-cuenta { padding-top: 12px; border-top: 1px solid rgba(255,255,255,.08); }
.mv-cuenta-txt { text-align: center; font-size: 13px; color: rgba(233,237,239,.85); }
.mv-cuenta-barra { height: 4px; border-radius: 2px; background: rgba(255,255,255,.12); margin: 9px 0 12px; overflow: hidden; }
.mv-cuenta-barra span { display: block; height: 100%; background: #25d366; transition: width 1s linear; }
.mv-cuenta-acciones { display: flex; gap: 8px; }
.mv-boton {
    flex: 1; border: none; border-radius: 999px; padding: 10px 12px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    background: #25d366; color: #052e1a;
    transition: transform .15s ease, filter .15s;
}
.mv-boton:hover { filter: brightness(1.07); }
.mv-boton:active { transform: scale(.96); }
.mv-boton--suave { background: rgba(255,255,255,.12); color: #e9edef; }
.mv-boton--alta { display: block; width: 100%; margin-top: 10px; background: #fff; color: #0b141a; }

.mv-controles { display: flex; justify-content: center; gap: 30px; padding: 26px 16px 26px; }
.mv-controles--fin { font-size: 12px; color: rgba(233,237,239,.5); padding-top: 18px; }
.mv-control { display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 12px; color: rgba(233,237,239,.75); }
.mv-redondo {
    width: 62px; height: 62px; border-radius: 50%; border: none; flex: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 23px; color: #fff; background: rgba(255,255,255,.13); cursor: pointer;
    transition: transform .15s ease, background .15s ease, box-shadow .15s;
}
.mv-redondo:hover { background: rgba(255,255,255,.22); }
.mv-redondo:active { transform: scale(.9); }
.mv-redondo--on, .mv-redondo--on:hover { background: #fff; color: #0b141a; }
.mv-redondo--colgar { background: #ea4335; box-shadow: 0 8px 20px rgba(234,67,53,.4); }
.mv-redondo--colgar:hover { background: #d93025; }
.mv-redondo--contestar { background: #25d366; box-shadow: 0 8px 20px rgba(37,211,102,.4); }
.mv-redondo--contestar:hover { background: #1ebe5a; }
.mv-redondo--brinca { animation: mv-brinca 1.6s ease-in-out infinite; }
.mv-redondo--brinca:hover { animation-play-state: paused; }
@keyframes mv-brinca {
    0%, 55%, 100% { transform: translateY(0) rotate(0); }
    15% { transform: translateY(-7px) rotate(-14deg); }
    30% { transform: translateY(0) rotate(10deg); }
    42% { transform: translateY(-3px) rotate(-6deg); }
}
.mv-redondo--chico { width: 40px; height: 40px; font-size: 15px; box-shadow: none; }

/* Minimizada */
.mv-mini { display: flex; align-items: center; gap: 10px; padding: 9px 10px 9px 9px; }
.mv-avatar--chico { width: 46px; height: 46px; margin: 0; font-size: 16px; flex: none; box-shadow: none; }
.mv-avatar--chico.mv-avatar--sonando { animation: mv-latido 1.4s ease-out infinite; }
@keyframes mv-latido {
    0%   { box-shadow: 0 0 0 0 rgba(37,211,102,.6); }
    100% { box-shadow: 0 0 0 12px rgba(37,211,102,0); }
}
.mv-mini-txt { flex: 1; min-width: 0; }
.mv-mini-nombre { font-weight: 600; color: #fff; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mv-mini-estado { font-size: 12.5px; color: #25d366; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Transiciones ────────────────────────────────────────────────────── */
.mv-ventana-enter-active, .mv-ventana-leave-active,
.mv-pop-enter-active, .mv-pop-leave-active { transition: opacity .22s ease, transform .24s cubic-bezier(.2,.8,.2,1); }
.mv-ventana-enter-from, .mv-ventana-leave-to { opacity: 0; transform: translateY(18px) scale(.95); }
.mv-pop-enter-from, .mv-pop-leave-to { opacity: 0; transform: translateY(10px) scale(.96); }
.mv-fade-enter-active, .mv-fade-leave-active { transition: opacity .15s ease; }
.mv-fade-enter-from, .mv-fade-leave-to { opacity: 0; }

/* ── Celular: la llamada ocupa la parte de abajo, como en el teléfono ─── */
@media (max-width: 575.98px) {
    .mv-wrap { right: 16px; bottom: 16px; }
    .mv-panel { width: calc(100vw - 32px); }
    .mv-call {
        left: 0; right: 0; bottom: 0; top: auto;
        width: auto; border-radius: 26px 26px 0 0; cursor: default;
    }
    .mv-call--mini { top: 0; bottom: auto; border-radius: 0 0 22px 22px; }
    .mv-grande { min-height: 74vh; display: flex; flex-direction: column; }
    .mv-grande .mv-controles { margin-top: auto; padding-bottom: 34px; }
}

@media (prefers-reduced-motion: reduce) {
    .mv-onda, .mv-redondo--brinca, .mv-avatar--sonando, .mv-badge { animation: none !important; }
}
</style>
