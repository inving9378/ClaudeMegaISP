<template>
    <div v-if="tieneTelefono || sinHttps" class="mv-wrap">
        <!-- Sin HTTPS: bubble visible pero deshabilitada, para que la ausencia
             del teléfono no se confunda con "no tengo extensión asignada". -->
        <div v-if="sinHttps" class="mv-panel-aviso" v-show="abierto">
            Este mini-teléfono necesita HTTPS.<br>
            Entra por <code>https://dev.meganett.com.mx</code>, no por la IP directa.
        </div>

        <!-- Panel expandido (solo para marcar — mientras hay llamada, el modal de abajo manda) -->
        <div v-else-if="abierto && !estadoLlamada" class="mv-panel">
            <div class="mv-head">
                <span class="mv-dot" :class="'mv-dot--' + estado"></span>
                <span class="mv-titulo">{{ nombre }} <small>({{ numero }})</small></span>
                <button class="mv-close" @click="abierto = false" title="Cerrar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="mv-estado-txt">{{ etiquetaEstado }}</div>

            <div class="mv-marcar">
                <input class="mv-input" v-model="destino" placeholder="Número a marcar…"
                       @input="consultarDisponibilidad" @keyup.enter="llamar"
                       :disabled="estado !== 'registrado'">
                <button class="mv-btn mv-btn--ok" @click="llamar"
                        :disabled="estado !== 'registrado' || !destino" title="Llamar">
                    <i class="fa fa-phone"></i>
                </button>
            </div>
            <div v-if="destino" class="mv-disponibilidad" :class="'mv-disponibilidad--' + claseDisponibilidad">
                <span class="mv-disp-dot"></span>{{ textoDisponibilidad }}
            </div>
        </div>

        <!-- Burbuja -->
        <button class="mv-bubble" :class="sinHttps ? 'mv-bubble--sinhttps' : 'mv-bubble--' + estado"
                @click="abierto = !abierto"
                :title="sinHttps ? 'Requiere HTTPS' : etiquetaEstado">
            <i class="fa fa-phone"></i>
            <span v-if="estadoLlamada" class="mv-badge"></span>
        </button>

        <!-- Modal de llamada — SIEMPRE visible mientras se marca, suena o está conectada, sin
             importar si la burbuja está abierta o cerrada. Antes el botón de colgar solo
             existía dentro del panel colapsable y se perdía de vista con facilidad. -->
        <div v-if="estadoLlamada" class="mv-call-backdrop">
            <div class="mv-call-modal">
                <!-- Terminó (sin respuesta, colgaron, etc.) — se muestra el motivo
                     un momento antes de cerrar, en vez de que el modal desaparezca
                     de golpe sin explicación. -->
                <div v-if="finalizando" class="mv-call-fin">
                    <i class="fa fa-phone-slash mv-call-icon mv-call-icon--fin"></i>
                    <div class="mv-call-titulo">{{ mensajeFinal }}</div>
                    <div class="mv-call-cerrando">Cerrando…</div>
                </div>

                <!-- Marcando (saliente, todavía sin contestar) -->
                <div v-else-if="estadoLlamada === 'saliente'">
                    <i class="fa fa-phone mv-call-icon mv-call-icon--saliente"></i>
                    <div class="mv-call-titulo">Llamando a…</div>
                    <div class="mv-call-remoto">{{ remotoId }}</div>
                    <div class="mv-call-acciones">
                        <button class="mv-btn mv-btn--bad mv-btn--grande mv-btn--solo" @click="colgar" title="Colgar">
                            <i class="fa fa-phone-slash"></i>
                        </button>
                    </div>
                </div>

                <!-- Entrante -->
                <div v-else-if="estadoLlamada === 'entrante'">
                    <i class="fa fa-phone-volume mv-ring mv-call-icon"></i>
                    <div class="mv-call-titulo">Llamada entrante</div>
                    <div class="mv-call-remoto">{{ remotoId }}</div>
                    <div class="mv-call-acciones">
                        <button class="mv-btn mv-btn--ok mv-btn--grande" @click="contestar" title="Contestar">
                            <i class="fa fa-phone"></i>
                        </button>
                        <button class="mv-btn mv-btn--bad mv-btn--grande" @click="colgar" title="Rechazar">
                            <i class="fa fa-phone-slash"></i>
                        </button>
                    </div>
                </div>

                <!-- Conectada -->
                <div v-else>
                    <i class="fa fa-phone mv-call-icon mv-call-icon--activa"></i>
                    <div class="mv-call-titulo">Llamada en curso</div>
                    <div class="mv-call-remoto">{{ remotoId }}</div>
                    <div class="mv-cronometro">{{ duracionTexto }}</div>
                    <div class="mv-call-acciones">
                        <button class="mv-btn" :class="silenciado ? 'mv-btn--warn' : ''" @click="toggleMute" :title="silenciado ? 'Activar mic' : 'Silenciar'">
                            <i :class="silenciado ? 'fa fa-microphone-slash' : 'fa fa-microphone'"></i>
                        </button>
                        <button class="mv-btn mv-btn--bad mv-btn--grande" @click="colgar" title="Colgar">
                            <i class="fa fa-phone-slash"></i>
                        </button>
                    </div>
                </div>

                <!-- MegaVoz Fase 4 — ficha del cliente que llama, si se identificó -->
                <div v-if="cargandoFicha" class="mv-ficha mv-ficha--cargando">Buscando cliente…</div>
                <div v-else-if="fichaCliente" class="mv-ficha">
                    <div class="mv-ficha-nombre">{{ fichaCliente.name }}</div>
                    <div class="mv-ficha-fila">
                        <span>Saldo</span>
                        <b :class="fichaCliente.balance < 0 ? 'mv-saldo--debe' : 'mv-saldo--ok'">
                            {{ formatoSaldo(fichaCliente.balance) }}
                        </b>
                    </div>
                    <div class="mv-ficha-fila" v-if="fichaCliente.active_services.length">
                        <span>Servicio</span>
                        <b>{{ fichaCliente.active_services[0].description }}</b>
                    </div>
                    <div class="mv-ficha-fila">
                        <span>Tickets abiertos</span>
                        <b :class="fichaCliente.tickets_open > 0 ? 'mv-saldo--debe' : ''">{{ fichaCliente.tickets_open }}</b>
                    </div>
                    <div class="mv-ficha-acciones">
                        <a :href="`/cliente/editar/${fichaCliente.id}`" target="_blank" class="mv-ficha-link">Ver ficha</a>
                        <a :href="`/tickets/crear/${fichaCliente.id}`" target="_blank" class="mv-ficha-link mv-ficha-link--ticket">+ Crear ticket</a>
                    </div>
                </div>
                <div v-else-if="fichaCliente === false" class="mv-ficha mv-ficha--sinidentificar">
                    Número no identificado.
                    <a href="/tickets/crear" target="_blank" class="mv-ficha-link mv-ficha-link--ticket">+ Crear ticket</a>
                </div>
            </div>
        </div>

        <audio ref="audioRemoto" autoplay></audio>
    </div>
</template>

<script>
import JsSIP from 'jssip';
import { markRaw } from 'vue';

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
        claseDisponibilidad() {
            if (this.destinoDisponible === true) return 'ok';
            if (this.destinoDisponible === false) return 'no';
            return 'consultando';
        },
        textoDisponibilidad() {
            if (this.destinoDisponible === true) return 'Disponible';
            if (this.destinoDisponible === false) return 'Sin conexión — puede que no conteste';
            return 'Consultando…';
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
            this.iniciarUA(body);
        } catch (e) {
            // Sin teléfono asignado o error de red — el widget simplemente no aparece.
        }
    },

    beforeUnmount() {
        if (this.ua) this.ua.stop();
        clearInterval(this._timerId);
        clearTimeout(this._dispDebounce);
        this.detenerTonoLlamando();
    },

    methods: {
        iniciarUA(cred) {
            const socket = new JsSIP.WebSocketInterface(cred.wss_url);
            socket.via_transport = 'wss';

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
                this.estadoLlamada = data.originator === 'local' ? 'saliente' : 'entrante';
                this.abierto = true;
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
            this.sesion.on('ended', () => this.terminarConMensaje('Llamada finalizada'));
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
            if (! this.destino || ! this.ua) return;
            // Todo lo demás (asignar this.sesion, estadoLlamada='saliente',
            // remotoId, engancharSesion, la ficha) lo hace el handler de
            // 'newRTCSession' de arriba — dispara síncrono dentro de
            // ua.call(), así que duplicarlo aquí es justo lo que causaba el
            // bug del estado pisado.
            this.ua.call(`sip:${this.destino}@${location.hostname}`, {
                mediaConstraints: { audio: true, video: false },
            });
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
            if (! numero) {
                this.destinoDisponible = null;
                return;
            }
            this.destinoDisponible = null; // "Consultando…" mientras llega la respuesta
            this._dispDebounce = setTimeout(async () => {
                // El texto pudo cambiar mientras esperaba el debounce — no pisar
                // el resultado de un número que ya no es el que se ve en pantalla.
                if (numero !== this.destino) return;
                try {
                    const r = await fetch(`/voip/mi-telefono/disponibilidad/${encodeURIComponent(numero)}`, {
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
            // Corta el timbre de aviso AQUÍ, en el clic mismo — no esperar a
            // los eventos 'accepted'/'confirmed' de la sesión (que ya lo
            // hacían vía marcarActiva(), pero con margen para que quede
            // sonando de más mientras la llamada termina de conectar).
            // Encontrado en vivo 24-sep-2026: David probando el timbre
            // entrante nuevo reportó que al contestar se escuchaba algo
            // "estilo música de espera" — coincide con el propio timbre
            // (dos ráfagas repetidas cada 3s) quedándose sonando de más.
            this.detenerTonoLlamando();
            this.sesion.answer({ mediaConstraints: { audio: true, video: false } });
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
        terminarConMensaje(mensaje) {
            clearInterval(this._timerId);
            this._timerId = null;
            this.detenerTonoLlamando();
            this.mensajeFinal = mensaje;
            this.finalizando = true;
            setTimeout(() => this.limpiarSesion(), 2000);
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

.mv-bubble {
    width: 52px; height: 52px; border-radius: 50%;
    border: none; cursor: pointer; position: relative;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff;
    background: #64748b;
    box-shadow: 0 2px 10px rgba(0,0,0,.25);
    transition: background .2s;
}
.mv-bubble--registrando { background: #d97706; }
.mv-bubble--registrado  { background: #16a34a; }
.mv-bubble--error       { background: #dc2626; }

.mv-badge {
    position: absolute; top: -2px; right: -2px; width: 14px; height: 14px;
    border-radius: 50%; background: #ef4444; border: 2px solid #fff;
    animation: mv-pulse 1s infinite;
}
@keyframes mv-pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.3); }
}

.mv-panel {
    position: absolute; bottom: 62px; right: 0; width: 260px;
    background: #fff; border-radius: 12px; padding: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
    font-size: 13.5px; color: #1f2937;
}
.mv-bubble--sinhttps { background: #94a3b8; }
.mv-panel-aviso {
    position: absolute; bottom: 62px; right: 0; width: 220px;
    background: #1f2937; color: #fff; border-radius: 10px; padding: 12px;
    font-size: 12.5px; line-height: 1.5; box-shadow: 0 10px 30px rgba(0,0,0,.2);
}
.mv-panel-aviso code { color: #93c5fd; }
.mv-head { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.mv-titulo { font-weight: 700; flex: 1; }
.mv-titulo small { font-weight: 400; color: #6b7280; }
.mv-close { border: none; background: transparent; color: #9ca3af; cursor: pointer; }

.mv-dot { width: 9px; height: 9px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.mv-dot--registrando { background: #d97706; }
.mv-dot--registrado  { background: #16a34a; }
.mv-dot--error        { background: #dc2626; }

.mv-estado-txt { color: #6b7280; font-size: 12px; margin-bottom: 10px; }

.mv-marcar { display: flex; gap: 6px; }
.mv-disponibilidad {
    display: flex; align-items: center; gap: 6px;
    font-size: 11.5px; margin-top: 6px; color: #6b7280;
}
.mv-disp-dot { width: 7px; height: 7px; border-radius: 50%; background: #9ca3af; flex-shrink: 0; }
.mv-disponibilidad--ok .mv-disp-dot { background: #16a34a; }
.mv-disponibilidad--ok { color: #16a34a; }
.mv-disponibilidad--no .mv-disp-dot { background: #d97706; }
.mv-disponibilidad--no { color: #b45309; }
.mv-input {
    flex: 1; border: 1px solid #d1d5db; border-radius: 8px;
    padding: 6px 10px; font-size: 13.5px;
}

.mv-llamando-de { margin-bottom: 10px; }
.mv-ring { color: #16a34a; margin-right: 4px; }

.mv-en-llamada { text-align: center; }
.mv-remoto { font-weight: 600; margin-bottom: 2px; }
.mv-cronometro { color: #6b7280; font-variant-numeric: tabular-nums; margin-bottom: 10px; }

.mv-acciones { display: flex; gap: 8px; justify-content: center; }
.mv-btn {
    width: 38px; height: 38px; border-radius: 50%; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; background: #e5e7eb; color: #374151;
}
.mv-btn--ok   { background: #16a34a; color: #fff; }
.mv-btn--bad  { background: #dc2626; color: #fff; }
.mv-btn--warn { background: #d97706; color: #fff; }
.mv-btn:disabled { opacity: .4; cursor: not-allowed; }
/* El ícono (FontAwesome, <i>) dentro de un botón puede no repasar el clic a
   su padre de forma confiable en algunos navegadores — el reporte real de
   David: "solo funciona el clic en la esquina" de la burbuja, exactamente
   este patrón. pointer-events:none saca al ícono de la jugada del todo: el
   clic SIEMPRE cae directo en el botón, sin importar dónde del ícono se
   haga clic. */
.mv-bubble i, .mv-btn i, .mv-close i { pointer-events: none; }
.mv-btn--grande { width: 52px; height: 52px; font-size: 20px; }

/* Modal de llamada activa — visible siempre, no depende de la burbuja */
.mv-call-backdrop {
    position: fixed; inset: 0; z-index: 10052;
    background: rgba(15, 23, 42, .35);
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: 90px;
}
.mv-call-modal {
    background: #fff; border-radius: 16px; padding: 24px 28px;
    width: 260px; text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,.35);
}
.mv-call-icon { font-size: 30px; color: #16a34a; margin-bottom: 8px; display: block; }
.mv-call-icon--activa { color: #0d9488; }
.mv-call-icon--saliente { color: #2563eb; animation: mv-pulse 1.2s infinite; }
.mv-call-icon--fin { color: #6b7280; }
.mv-call-fin { text-align: center; }
.mv-call-cerrando { font-size: 11px; color: #9ca3af; margin-top: 6px; }
.mv-btn--solo { width: 60px; height: 60px; font-size: 24px; }
.mv-call-titulo { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 700; }
.mv-call-remoto { font-size: 18px; font-weight: 700; color: #1f2937; margin: 4px 0 10px; }
.mv-call-acciones { display: flex; gap: 14px; justify-content: center; margin-top: 14px; }

/* MegaVoz Fase 4 — ficha del cliente que llama */
.mv-ficha {
    margin-top: 16px; padding-top: 14px; border-top: 1px solid #e5e7eb;
    text-align: left; font-size: 12.5px;
}
.mv-ficha--cargando { text-align: center; color: #9ca3af; font-style: italic; }
.mv-ficha--sinidentificar { text-align: center; color: #9ca3af; }
.mv-ficha-nombre { font-weight: 700; font-size: 14px; color: #1f2937; margin-bottom: 6px; }
.mv-ficha-fila {
    display: flex; justify-content: space-between; gap: 8px;
    padding: 2px 0; color: #6b7280;
}
.mv-saldo--debe { color: #dc2626; }
.mv-saldo--ok   { color: #16a34a; }
.mv-ficha-acciones { display: flex; gap: 10px; margin-top: 10px; justify-content: center; }
.mv-ficha-link {
    font-size: 12px; font-weight: 600; text-decoration: none;
    color: #2563eb; border: 1px solid #bfdbfe; border-radius: 6px;
    padding: 4px 10px;
}
.mv-ficha-link--ticket { color: #b45309; border-color: #fde68a; }
</style>
