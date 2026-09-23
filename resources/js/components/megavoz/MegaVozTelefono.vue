<template>
    <div v-if="tieneTelefono || sinHttps" class="mv-wrap">
        <!-- Sin HTTPS: bubble visible pero deshabilitada, para que la ausencia
             del teléfono no se confunda con "no tengo extensión asignada". -->
        <div v-if="sinHttps" class="mv-panel-aviso" v-show="abierto">
            Este mini-teléfono necesita HTTPS.<br>
            Entra por <code>https://dev.meganett.com.mx</code>, no por la IP directa.
        </div>

        <!-- Panel expandido -->
        <div v-else-if="abierto" class="mv-panel">
            <div class="mv-head">
                <span class="mv-dot" :class="'mv-dot--' + estado"></span>
                <span class="mv-titulo">{{ nombre }} <small>({{ numero }})</small></span>
                <button class="mv-close" @click="abierto = false" title="Cerrar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="mv-estado-txt">{{ etiquetaEstado }}</div>

            <!-- Llamada entrante -->
            <div v-if="llamadaEntrante" class="mv-llamando">
                <div class="mv-llamando-de">
                    <i class="fa fa-phone-volume mv-ring"></i>
                    Llamada de <strong>{{ remotoId }}</strong>
                </div>
                <div class="mv-acciones">
                    <button class="mv-btn mv-btn--ok" @click="contestar" title="Contestar">
                        <i class="fa fa-phone"></i>
                    </button>
                    <button class="mv-btn mv-btn--bad" @click="colgar" title="Rechazar">
                        <i class="fa fa-phone-slash"></i>
                    </button>
                </div>
            </div>

            <!-- En llamada -->
            <div v-else-if="enLlamada" class="mv-en-llamada">
                <div class="mv-remoto"><i class="fa fa-user me-1"></i>{{ remotoId }}</div>
                <div class="mv-cronometro">{{ duracionTexto }}</div>
                <div class="mv-acciones">
                    <button class="mv-btn" :class="silenciado ? 'mv-btn--warn' : ''" @click="toggleMute" :title="silenciado ? 'Activar mic' : 'Silenciar'">
                        <i :class="silenciado ? 'fa fa-microphone-slash' : 'fa fa-microphone'"></i>
                    </button>
                    <button class="mv-btn mv-btn--bad" @click="colgar" title="Colgar">
                        <i class="fa fa-phone-slash"></i>
                    </button>
                </div>
            </div>

            <!-- Marcar -->
            <div v-else class="mv-marcar">
                <input class="mv-input" v-model="destino" placeholder="Número a marcar…"
                       @keyup.enter="llamar" :disabled="estado !== 'registrado'">
                <button class="mv-btn mv-btn--ok" @click="llamar"
                        :disabled="estado !== 'registrado' || !destino" title="Llamar">
                    <i class="fa fa-phone"></i>
                </button>
            </div>
        </div>

        <!-- Burbuja -->
        <button class="mv-bubble" :class="sinHttps ? 'mv-bubble--sinhttps' : 'mv-bubble--' + estado"
                @click="abierto = !abierto"
                :title="sinHttps ? 'Requiere HTTPS' : etiquetaEstado">
            <i class="fa fa-phone"></i>
            <span v-if="llamadaEntrante || enLlamada" class="mv-badge"></span>
        </button>

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
        };
    },

    computed: {
        llamadaEntrante() {
            return this.sesion && this.sesion.direction === 'incoming' && !this.sesion.isEstablished();
        },
        enLlamada() {
            return this.sesion && this.sesion.isEstablished();
        },
        etiquetaEstado() {
            const mapa = {
                inactivo:   'Sin conexión',
                registrando:'Conectando…',
                registrado: 'En línea — listo para llamar',
                error:      'No se pudo conectar',
            };
            return mapa[this.estado] || this.estado;
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
            }));

            this.estado = 'registrando';

            this.ua.on('registered', () => { this.estado = 'registrado'; });
            this.ua.on('unregistered', () => { this.estado = 'inactivo'; });
            this.ua.on('registrationFailed', () => { this.estado = 'error'; });

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
                this.abierto = true;
                this.engancharSesion();
            });

            this.ua.start();
        },

        engancharSesion() {
            this.sesion.on('accepted', () => {
                this.inicioLlamada = Date.now();
                this._timerId = setInterval(this.tick, 1000);
                this.engancharAudio();
            });
            this.sesion.on('confirmed', () => this.engancharAudio());
            this.sesion.on('ended', () => this.limpiarSesion());
            this.sesion.on('failed', () => this.limpiarSesion());
        },

        engancharAudio() {
            const remoteStream = new MediaStream();
            this.sesion.connection.getReceivers().forEach((receiver) => {
                if (receiver.track) remoteStream.addTrack(receiver.track);
            });
            if (this.$refs.audioRemoto) {
                this.$refs.audioRemoto.srcObject = remoteStream;
            }
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
            this.sesion = markRaw(this.ua.call(`sip:${this.destino}@${location.hostname}`, {
                mediaConstraints: { audio: true, video: false },
            }));
            this.remotoId = this.destino;
            this.engancharSesion();
        },

        contestar() {
            if (! this.sesion) return;
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

        limpiarSesion() {
            clearInterval(this._timerId);
            this._timerId = null;
            this.inicioLlamada = null;
            this.duracionTexto = '00:00';
            this.silenciado = false;
            this.sesion = null;
            this.destino = '';
            if (this.$refs.audioRemoto) this.$refs.audioRemoto.srcObject = null;
        },
    },
};
</script>

<style scoped>
.mv-wrap { position: fixed; right: 160px; bottom: 24px; z-index: 1039; }

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
</style>
