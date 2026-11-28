<template>
    <!-- Configuración de la troncal de Campañas/Blaster (28-nov: rehecha con el diseño de Campañas, pedido de
         Irving). El grupo de timbrado se elige con búsqueda y se puede crear aquí mismo. -->
    <div class="cd cp-raiz cvc" :class="{ 'cd-dark': darkMode }">
        <header class="cvc-cabecera">
            <div>
                <h1 class="cvc-titulo">Troncal de Campañas</h1>
                <p class="cvc-sub">Por aquí salen todas las campañas y entra quien regresa la llamada.</p>
            </div>
            <span class="cvc-estado" :class="`cvc-estado--${estadoTroncal.tono}`">
                <span class="cvc-punto" aria-hidden="true"></span>{{ estadoTroncal.texto }}
            </span>
        </header>

        <div class="cvc-cuerpo">
            <form class="cvc-panel cvc-form" novalidate @submit.prevent="guardar">
                <p v-if="errorCarga" class="cvc-aviso cvc-aviso--error" role="alert">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    <span>{{ errorCarga }} <button type="button" class="cvc-enlace" @click="cargar">Reintentar</button></span>
                </p>

                <fieldset class="cvc-seccion">
                    <legend class="cvc-seccion-titulo"><i class="fa fa-server" aria-hidden="true"></i>Conexión con Servnet</legend>
                    <div class="cvc-fila cvc-fila--host">
                        <div class="cp-campo-grupo">
                            <label for="cvc-host" class="cp-etiqueta">Servidor SIP</label>
                            <input id="cvc-host" v-model.trim="form.sip_host" class="cp-campo" placeholder="sip.servnet.com.mx"
                                   autocomplete="off" spellcheck="false" :aria-invalid="!!errors.sip_host">
                            <p v-if="errors.sip_host" class="cp-error">{{ primero('sip_host') }}</p>
                        </div>
                        <div class="cp-campo-grupo">
                            <label for="cvc-puerto" class="cp-etiqueta">Puerto</label>
                            <input id="cvc-puerto" v-model.number="form.sip_port" class="cp-campo cp-num" type="number" min="1" max="65535"
                                   placeholder="5060" :aria-invalid="!!errors.sip_port">
                            <p v-if="errors.sip_port" class="cp-error">{{ primero('sip_port') }}</p>
                        </div>
                    </div>
                    <div class="cvc-fila">
                        <div class="cp-campo-grupo">
                            <label for="cvc-usuario" class="cp-etiqueta">Usuario</label>
                            <input id="cvc-usuario" v-model.trim="form.sip_username" class="cp-campo" autocomplete="off" spellcheck="false"
                                   :aria-invalid="!!errors.sip_username">
                            <p v-if="errors.sip_username" class="cp-error">{{ primero('sip_username') }}</p>
                        </div>
                        <div class="cp-campo-grupo">
                            <label for="cvc-secret" class="cp-etiqueta">Contraseña</label>
                            <div class="cvc-secreto">
                                <input id="cvc-secret" v-model="form.sip_secret" class="cp-campo" :type="mostrarSecret ? 'text' : 'password'"
                                       autocomplete="new-password" :placeholder="tieneSecret ? 'Sin cambios' : ''"
                                       :aria-invalid="!!errors.sip_secret" aria-describedby="cvc-secret-ayuda">
                                <button type="button" class="cvc-ojo" :aria-label="mostrarSecret ? 'Ocultar la contraseña' : 'Ver la contraseña'"
                                        :aria-pressed="mostrarSecret ? 'true' : 'false'" @click="mostrarSecret = !mostrarSecret">
                                    <i class="fa" :class="mostrarSecret ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p v-if="errors.sip_secret" class="cp-error">{{ primero('sip_secret') }}</p>
                            <p v-else-if="tieneSecret" id="cvc-secret-ayuda" class="cp-ayuda">Déjala vacía para conservar la que ya está guardada.</p>
                        </div>
                    </div>
                    <div class="cvc-fila">
                        <div class="cp-campo-grupo">
                            <label for="cvc-fromuser" class="cp-etiqueta">From User <span class="cp-opcional">· opcional</span></label>
                            <input id="cvc-fromuser" v-model.trim="form.sip_fromuser" class="cp-campo" placeholder="El DID que te asignó Servnet"
                                   autocomplete="off" spellcheck="false">
                        </div>
                        <div class="cp-campo-grupo">
                            <label for="cvc-fromdomain" class="cp-etiqueta">From Domain <span class="cp-opcional">· opcional</span></label>
                            <input id="cvc-fromdomain" v-model.trim="form.sip_fromdomain" class="cp-campo" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="cvc-seccion">
                    <legend class="cvc-seccion-titulo"><i class="fa fa-user-tag" aria-hidden="true"></i>Cómo aparece al llamar</legend>
                    <div class="cvc-fila cvc-fila--callerid">
                        <div class="cp-campo-grupo">
                            <label for="cvc-cid-nombre" class="cp-etiqueta">Nombre</label>
                            <input id="cvc-cid-nombre" v-model="form.callerid_nombre" class="cp-campo" maxlength="100" placeholder="Meganet Telecomunicaciones">
                        </div>
                        <div class="cp-campo-grupo">
                            <label for="cvc-cid-numero" class="cp-etiqueta">Número</label>
                            <input id="cvc-cid-numero" v-model.trim="form.callerid_numero" class="cp-campo cp-num" maxlength="30" inputmode="tel"
                                   placeholder="10 dígitos" :aria-invalid="!!errors.callerid_numero">
                            <p v-if="errors.callerid_numero" class="cp-error">{{ primero('callerid_numero') }}</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="cvc-seccion">
                    <legend class="cvc-seccion-titulo"><i class="fa fa-phone-volume" aria-hidden="true"></i>Llamadas que entran</legend>
                    <div class="cp-campo-grupo cvc-grupo">
                        <label id="cvc-grupo-etiqueta" for="cvc-grupo" class="cp-etiqueta">Suenan en el grupo de timbrado</label>
                        <campanas-selector id="cvc-grupo" id-etiqueta="cvc-grupo-etiqueta"
                                           :model-value="form.grupo_entrante_id != null ? String(form.grupo_entrante_id) : null"
                                           :grupos="gruposSelector" :texto-todas="textoPorOmision" buscar-etiqueta="Buscar grupo de timbrado"
                                           vacio="No hay grupos de timbrado activos." :accion-texto="puedeCrearGrupo ? 'Crear grupo de timbrado' : ''"
                                           @update:model-value="v => (form.grupo_entrante_id = v ? Number(v) : null)"
                                           @accion="abrirNuevoGrupo" />
                        <p v-if="errors.grupo_entrante_id" class="cp-error">{{ primero('grupo_entrante_id') }}</p>
                    </div>
                    <!-- Lo que hace el grupo elegido, en palabras. -->
                    <div class="cvc-resumen-grupo" :class="{ 'cvc-resumen-grupo--recien': grupoRecien }" aria-live="polite">
                        <template v-if="grupoElegido">
                            <span class="cvc-resumen-icono" aria-hidden="true"><i class="fa fa-users"></i></span>
                            <div>
                                <p class="cvc-resumen-titulo">{{ grupoElegido.nombre }}<span v-if="grupoRecien" class="cvc-nuevo">Nuevo</span></p>
                                <p class="cvc-resumen-texto">{{ descripcionGrupo(grupoElegido) }}</p>
                            </div>
                        </template>
                        <template v-else-if="grupos.length">
                            <span class="cvc-resumen-icono" aria-hidden="true"><i class="fa fa-users"></i></span>
                            <div>
                                <p class="cvc-resumen-titulo">Sin elegir: el primer grupo activo</p>
                                <p class="cvc-resumen-texto">Hoy es «{{ grupos[0].nombre }}» · {{ descripcionGrupo(grupos[0]) }}</p>
                            </div>
                        </template>
                        <template v-else>
                            <span class="cvc-resumen-icono cvc-resumen-icono--aviso" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span>
                            <div>
                                <p class="cvc-resumen-titulo">No hay grupos de timbrado</p>
                                <p class="cvc-resumen-texto">
                                    Quien regrese la llamada no tendrá dónde sonar.
                                    <button v-if="puedeCrearGrupo" type="button" class="cvc-enlace" @click="abrirNuevoGrupo('')">Crear uno</button>
                                </p>
                            </div>
                        </template>
                    </div>
                </fieldset>

                <footer class="cvc-barra">
                    <span class="cvc-cambios" :class="{ 'cvc-cambios--si': hayCambios }">
                        <span class="cvc-punto" aria-hidden="true"></span>{{ hayCambios ? 'Cambios sin guardar' : 'Todo guardado' }}
                    </span>
                    <button type="button" class="cvc-boton" :disabled="probando" @click="probarConexion">
                        <i class="fa" :class="probando ? 'fa-spinner fa-spin' : 'fa-plug'" aria-hidden="true"></i>
                        {{ probando ? 'Probando…' : 'Probar conexión' }}
                    </button>
                    <button type="submit" class="cvc-boton cvc-boton--primario" :disabled="guardando || cargando">
                        <i class="fa" :class="guardando ? 'fa-spinner fa-spin' : 'fa-check'" aria-hidden="true"></i>
                        {{ guardando ? 'Aplicando…' : 'Guardar y aplicar' }}
                    </button>
                </footer>
            </form>

            <aside class="cvc-lado" aria-label="Estado del registro con Servnet">
                <section class="cvc-panel cvc-registro" aria-live="polite">
                    <h2 class="cvc-panel-titulo">Registro con Servnet</h2>
                    <div class="cvc-registro-estado" :class="`cvc-registro-estado--${registro.tono}`">
                        <span class="cvc-registro-icono" aria-hidden="true"><i class="fa" :class="registro.icono"></i></span>
                        <div>
                            <p class="cvc-registro-titulo">{{ registro.titulo }}</p>
                            <p class="cvc-registro-texto">{{ registro.texto }}</p>
                        </div>
                    </div>
                    <dl v-if="testResult" class="cvc-datos">
                        <div><dt>En Asterisk</dt><dd>{{ testResult.provisionado === false ? 'No está dada de alta' : 'Dada de alta' }}</dd></div>
                        <div v-if="testResult.estado"><dt>Estado</dt><dd class="cp-num">{{ testResult.estado }}</dd></div>
                        <div v-if="testResult.ips"><dt>IPs de entrada</dt><dd class="cp-num">{{ testResult.ips }}</dd></div>
                    </dl>
                    <details v-if="testResult && testResult.output" class="cvc-detalle">
                        <summary>Ver el detalle técnico</summary>
                        <pre>{{ testResult.output }}</pre>
                    </details>
                    <button type="button" class="cvc-boton cvc-boton--ancho" :disabled="probando" @click="probarConexion">
                        <i class="fa" :class="probando ? 'fa-spinner fa-spin' : 'fa-plug'" aria-hidden="true"></i>
                        {{ probando ? 'Probando…' : (testResult ? 'Probar otra vez' : 'Probar conexión') }}
                    </button>
                </section>

                <p v-if="mensajeGuardado" class="cvc-aviso cvc-aviso--bien" role="status">
                    <i class="fa fa-check-circle" aria-hidden="true"></i><span>{{ mensajeGuardado }}</span>
                </p>
                <p v-for="(w, i) in avisos" :key="i" class="cvc-aviso" role="status">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ w }}</span>
                </p>
                <p v-if="errorGuardado" class="cvc-aviso cvc-aviso--error" role="alert">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ errorGuardado }}</span>
                </p>
            </aside>
        </div>

        <campanas-grupo-nuevo :abierto="dialogoGrupo" :voip-url="voipUrl" :csrf-token="csrfToken" :oscuro="darkMode"
                              :nombre-inicial="nombreGrupoInicial" @cerrar="dialogoGrupo = false" @creado="alCrearGrupo" />
    </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';
import CampanasSelector from './CampanasSelector.vue';
import CampanasGrupoNuevo from './CampanasGrupoNuevo.vue';

const FORM_VACIO = {
    sip_host: '', sip_port: 5060, sip_username: '', sip_secret: '', sip_fromuser: '', sip_fromdomain: '',
    callerid_nombre: 'Meganet Telecomunicaciones', callerid_numero: '', grupo_entrante_id: null,
};
const MODO = { ringall: 'Todas a la vez', hunt: 'Una por una, en orden', memoryhunt: 'Una por una, rotando' };
const CAMPOS = Object.keys(FORM_VACIO);
const huella = f => JSON.stringify(CAMPOS.map(k => (k === 'sip_secret' ? !!f[k] : (f[k] ?? ''))));

export default {
    name: 'CobranzaVoipConfig',
    components: { CampanasSelector, CampanasGrupoNuevo },
    props: {
        baseUrl:         { type: String, required: true },
        csrfToken:       { type: String, required: true },
        voipUrl:         { type: String, default: '/voip' },
        puedeCrearGrupo: { type: Boolean, default: false },
    },
    setup() {
        return { darkMode };
    },
    data() {
        return {
            config:          {},
            form:            { ...FORM_VACIO },
            guardado:        huella(FORM_VACIO),
            grupos:          [],
            errors:          {},
            cargando:        true,
            errorCarga:      '',
            guardando:       false,
            probando:        false,
            mostrarSecret:   false,
            testResult:      null,
            mensajeGuardado: '',
            avisos:          [],
            errorGuardado:   '',
            dialogoGrupo:    false,
            nombreGrupoInicial: '',
            grupoRecien:     null,
        };
    },
    computed: {
        tieneSecret() {
            return this.config.sip_secret === '••••••••';
        },
        hayCambios() {
            return !this.cargando && huella(this.form) !== this.guardado;
        },
        estadoTroncal() {
            const e = this.config.estado;
            if (e === 'activa') return { tono: 'bien', texto: 'Activa' };
            if (e === 'error') return { tono: 'mal', texto: 'Con error' };
            return { tono: 'neutro', texto: this.config.id ? 'Sin aplicar' : 'Sin configurar' };
        },
        registro() {
            const t = this.testResult;
            if (this.probando) return { tono: 'neutro', icono: 'fa-spinner fa-spin', titulo: 'Probando…', texto: 'Preguntando a Asterisk por el registro.' };
            if (!t) return { tono: 'neutro', icono: 'fa-plug', titulo: 'Sin probar', texto: 'Guarda y prueba la conexión para ver si Servnet aceptó el registro.' };
            if (t.registrado) return { tono: 'bien', icono: 'fa-check-circle', titulo: 'Registrada con Servnet', texto: 'Las campañas pueden llamar y las llamadas pueden entrar.' };
            return { tono: 'mal', icono: 'fa-times-circle', titulo: 'Sin registro', texto: 'Servnet no aceptó la conexión: revisa el servidor, el usuario y la contraseña.' };
        },
        gruposSelector() {
            return [{
                titulo: null,
                opciones: this.grupos.map(g => ({
                    valor: String(g.id), etiqueta: g.nombre, n: null, detalle: this.descripcionGrupo(g), buscar: MODO[g.estrategia] || '',
                })),
            }];
        },
        textoPorOmision() {
            return this.grupos.length ? `El primer grupo activo (${this.grupos[0].nombre})` : 'Sin grupo de timbrado';
        },
        grupoElegido() {
            return this.grupos.find(g => g.id === this.form.grupo_entrante_id) || null;
        },
    },
    mounted() {
        this.cargar();
        window.addEventListener('beforeunload', this.avisarSalida);
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.avisarSalida);
    },
    methods: {
        primero(campo) {
            const e = this.errors[campo];
            return Array.isArray(e) ? e[0] : (e || '');
        },
        descripcionGrupo(g) {
            const ext = `${g.miembros} ${g.miembros === 1 ? 'extensión' : 'extensiones'}`;
            return [ext, MODO[g.estrategia] || g.estrategia, `${g.ring_time} s`, g.es_cola ? 'con espera' : null].filter(Boolean).join(' · ');
        },
        avisarSalida(ev) {
            if (this.hayCambios) { ev.preventDefault(); ev.returnValue = ''; }
        },
        async cargar() {
            this.cargando = true;
            this.errorCarga = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/voip/configuracion`);
                const { grupos, ...config } = data || {};
                this.grupos = grupos || [];
                if (config.id) {
                    this.config = config;
                    this.form = { ...FORM_VACIO, ...Object.fromEntries(CAMPOS.map(k => [k, config[k] ?? FORM_VACIO[k]])), sip_secret: '' };
                }
                this.guardado = huella(this.form);
            } catch (e) {
                this.errorCarga = e.response?.status === 403 ? 'No tienes permiso para configurar la troncal.' : 'No se pudo cargar la configuración.';
            } finally {
                this.cargando = false;
            }
        },
        // Solo la lista de grupos (tras crear uno): lo que se lleva capturado no se pierde.
        async recargarGrupos() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/voip/configuracion`);
                this.grupos = (data && data.grupos) || [];
            } catch (e) { /* se queda la lista que había */ }
        },
        async guardar() {
            if (this.guardando) return;
            this.guardando = true;
            this.errors = {};
            this.mensajeGuardado = '';
            this.avisos = [];
            this.errorGuardado = '';

            // Sin contraseña nueva se conserva la guardada (el servidor no la pide).
            const payload = { ...this.form };
            if (!payload.sip_secret) delete payload.sip_secret;

            try {
                const { data } = await axios.post(`${this.baseUrl}/voip/configuracion`, payload, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (data && data.ok === false) {
                    this.errorGuardado = data.msg || 'No se pudo aplicar la configuración en Asterisk.';
                } else {
                    this.mensajeGuardado = data?.msg || 'Configuración guardada y aplicada en Asterisk.';
                    this.avisos = (data && data.warnings) || [];
                    this.grupoRecien = null;
                    await this.cargar();
                }
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                    this.errorGuardado = 'Revisa los campos marcados.';
                } else {
                    this.errorGuardado = e.response?.data?.message || e.response?.data?.error || 'No se pudo guardar. Intenta de nuevo.';
                }
            } finally {
                this.guardando = false;
            }
        },
        async probarConexion() {
            this.probando = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/voip/test`);
                this.testResult = data;
            } catch (e) {
                this.testResult = { registrado: false, output: e.message };
            } finally {
                this.probando = false;
            }
        },
        abrirNuevoGrupo(nombre) {
            this.nombreGrupoInicial = typeof nombre === 'string' ? nombre : '';
            this.dialogoGrupo = true;
        },
        async alCrearGrupo({ id, nombre }) {
            this.dialogoGrupo = false;
            await this.recargarGrupos();
            if (!this.grupos.some(g => g.id === id)) {
                this.grupos = [...this.grupos, { id, nombre, estrategia: 'ringall', es_cola: false, ring_time: 20, miembros: 0 }];
            }
            this.form.grupo_entrante_id = id;
            this.grupoRecien = id;
            this.mensajeGuardado = `Grupo «${nombre}» creado y elegido. Guarda y aplica para que las llamadas suenen ahí.`;
            this.avisos = [];
            this.errorGuardado = '';
        },
    },
};
</script>

<style scoped>
.cvc { font-size: 13.5px; padding-bottom: 8px; }
.cvc-cabecera { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
/* Interlineado propio: el tema de la app les da a h1 y h2 hasta 96 px. */
.cvc-titulo { margin: 0; font-size: 1.375rem; line-height: 1.25; font-weight: 600; letter-spacing: -.01em; color: var(--cp-tinta); }
.cvc-sub { margin: 4px 0 0; font-size: 13.5px; color: var(--cp-tenue); }

.cvc-punto { width: 8px; height: 8px; flex: none; border-radius: 50%; background: currentColor; }
.cvc-estado { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
.cvc-estado--bien { background: #dcfce7; color: #166534; }
.cvc-estado--mal { background: #fee2e2; color: #991b1b; }
.cvc-estado--neutro { background: #eef1f5; color: #475569; }
.cd-dark .cvc-estado--bien { background: rgba(34, 197, 94, .16); color: #86efac; }
.cd-dark .cvc-estado--mal { background: rgba(239, 68, 68, .18); color: #fca5a5; }
.cd-dark .cvc-estado--neutro { background: rgba(148, 163, 184, .16); color: #cbd5e1; }

.cvc-cuerpo { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr); gap: 20px; align-items: start; }
.cvc-panel { background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 12px; box-shadow: var(--cp-sombra); }
.cvc-form { padding: 20px 22px 0; }

.cvc-seccion { border: 0; margin: 0 0 22px; padding: 0; min-width: 0; }
.cvc-seccion-titulo {
    display: flex; align-items: center; gap: 9px; float: left; width: 100%;
    margin: 0 0 14px; padding: 0 0 9px; border-bottom: 1px solid var(--cp-borde);
    font-size: 14px; font-weight: 600; color: var(--cp-tinta);
}
.cvc-seccion-titulo + * { clear: both; }
.cvc-seccion-titulo .fa { width: 16px; font-size: 13px; color: var(--cp-tenue); text-align: center; }

.cvc-fila { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0 16px; }
.cvc-fila--host { grid-template-columns: minmax(0, 1fr) 140px; }
.cvc-fila--callerid { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }

.cvc-secreto { position: relative; }
.cvc-secreto .cp-campo { padding-right: 42px; }
.cvc-ojo {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%); width: 32px; height: 30px; border: 0; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center; background: transparent; color: var(--cp-tenue); cursor: pointer;
}
.cvc-ojo:hover, .cvc-ojo[aria-pressed="true"] { color: var(--cp-acento); background: var(--cp-acento-suave); }

/* El grupo elegido, dicho en palabras. Recién creado: se ilumina un momento. */
.cvc-grupo { margin-bottom: 10px; }
.cvc-resumen-grupo {
    display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 10px;
    background: var(--cp-cabecera); border: 1px solid var(--cp-borde);
    transition: border-color .4s, background-color .4s, box-shadow .4s;
}
.cvc-resumen-grupo--recien { border-color: var(--cp-acento); background: var(--cp-acento-suave); }
@media (prefers-reduced-motion: no-preference) {
    .cvc-resumen-grupo--recien { animation: cvc-recien 1.6s cubic-bezier(.16, 1, .3, 1); }
}
@keyframes cvc-recien {
    0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--cp-acento) 45%, transparent); transform: translateY(4px); }
    30% { transform: none; }
    100% { box-shadow: 0 0 0 12px transparent; }
}
.cvc-resumen-icono {
    flex: none; width: 34px; height: 34px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center;
    background: var(--cp-superficie); border: 1px solid var(--cp-borde); color: var(--cp-acento); font-size: 13px;
}
.cvc-resumen-icono--aviso { color: #b45309; }
.cd-dark .cvc-resumen-icono--aviso { color: #fcd34d; }
.cvc-resumen-titulo { display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 600; color: var(--cp-tinta); }
.cvc-resumen-texto { margin: 2px 0 0; font-size: 12.5px; line-height: 1.45; color: var(--cp-tenue); }
.cvc-nuevo { padding: 1px 8px; border-radius: 999px; background: var(--cp-acento); color: var(--cp-acento-texto); font-size: 11px; font-weight: 700; }

/* Barra de guardar: se queda abajo mientras se llena el formulario. */
.cvc-barra {
    position: sticky; bottom: 0; z-index: 3; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin: 0 -22px; padding: 12px 22px; border-top: 1px solid var(--cp-borde);
    background: var(--cp-superficie); border-radius: 0 0 12px 12px;
}
.cvc-cambios { display: inline-flex; align-items: center; gap: 8px; margin-right: auto; font-size: 12.5px; color: var(--cp-tenue); }
.cvc-cambios .cvc-punto { width: 7px; height: 7px; background: var(--cp-borde-campo); }
.cvc-cambios--si { color: var(--cp-tinta); font-weight: 600; }
.cvc-cambios--si .cvc-punto { background: #d97706; }

.cvc-boton {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 36px;
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta);
    border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 500; line-height: 1.2; cursor: pointer; white-space: nowrap;
    transition: background-color .15s, border-color .15s, color .15s, filter .15s;
}
.cvc-boton i { font-size: 12px; }
.cvc-boton:hover:not(:disabled) { border-color: var(--cp-acento); color: var(--cp-acento); }
.cvc-boton:disabled { opacity: .5; cursor: default; }
.cvc-boton--primario { background: var(--cp-acento); border-color: var(--cp-acento); color: var(--cp-acento-texto); font-weight: 600; }
.cvc-boton--primario:hover:not(:disabled) { filter: brightness(1.08); color: var(--cp-acento-texto); }
.cvc-boton--ancho { width: 100%; margin-top: 14px; }
.cvc-enlace { border: 0; background: none; padding: 0; color: var(--cp-acento); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }

/* Lado: el registro con Servnet */
.cvc-lado { position: sticky; top: 16px; display: grid; gap: 12px; }
.cvc-registro { padding: 18px 20px; }
.cvc-panel-titulo { margin: 0 0 14px; font-size: 14px; line-height: 1.3; font-weight: 600; color: var(--cp-tinta); }
.cvc-registro-estado { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 10px; }
.cvc-registro-estado--neutro { background: var(--cp-cabecera); }
.cvc-registro-estado--bien { background: #f0fdf4; }
.cvc-registro-estado--mal { background: #fef2f2; }
.cd-dark .cvc-registro-estado--bien { background: rgba(34, 197, 94, .1); }
.cd-dark .cvc-registro-estado--mal { background: rgba(239, 68, 68, .1); }
.cvc-registro-icono { flex: none; font-size: 22px; line-height: 1; margin-top: 1px; color: var(--cp-tenue); }
.cvc-registro-estado--bien .cvc-registro-icono { color: #15803d; }
.cvc-registro-estado--mal .cvc-registro-icono { color: #b91c1c; }
.cd-dark .cvc-registro-estado--bien .cvc-registro-icono { color: #86efac; }
.cd-dark .cvc-registro-estado--mal .cvc-registro-icono { color: #fca5a5; }
.cvc-registro-titulo { margin: 0; font-size: 15px; font-weight: 600; color: var(--cp-tinta); }
.cvc-registro-texto { margin: 3px 0 0; font-size: 12.5px; line-height: 1.45; color: var(--cp-tenue); }

.cvc-datos { margin: 14px 0 0; display: grid; gap: 8px; }
.cvc-datos > div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 10px; }
.cvc-datos dt { font-size: 12.5px; font-weight: 600; color: var(--cp-tenue); }
.cvc-datos dd { margin: 0; font-size: 13px; color: var(--cp-tinta); overflow-wrap: anywhere; }
.cvc-detalle { margin-top: 12px; }
.cvc-detalle summary { font-size: 12.5px; font-weight: 600; color: var(--cp-acento); cursor: pointer; }
.cvc-detalle pre {
    margin: 8px 0 0; max-height: 220px; overflow: auto; padding: 10px 12px; border-radius: 8px;
    background: #0f172a; color: #cbd5e1; font-size: 12px; line-height: 1.5; white-space: pre-wrap;
}

.cvc-aviso {
    display: flex; gap: 10px; align-items: flex-start; margin: 0; padding: 11px 14px; border-radius: 10px;
    background: #fff7ed; color: #7c2d12; border: 1px solid #fed7aa; font-size: 13px; line-height: 1.45;
}
.cvc-aviso i { margin-top: 3px; }
.cvc-aviso--bien { background: #f0fdf4; color: #14532d; border-color: #bbf7d0; }
.cvc-aviso--error { background: #fef2f2; color: #7f1d1d; border-color: #fecaca; }
.cvc-form > .cvc-aviso { margin-bottom: 16px; }
.cd-dark .cvc-aviso { background: rgba(234, 88, 12, .12); color: #fdba74; border-color: rgba(234, 88, 12, .3); }
.cd-dark .cvc-aviso--bien { background: rgba(34, 197, 94, .1); color: #86efac; border-color: rgba(34, 197, 94, .28); }
.cd-dark .cvc-aviso--error { background: rgba(239, 68, 68, .12); color: #fca5a5; border-color: rgba(239, 68, 68, .3); }

@media (max-width: 1099px) {
    .cvc-cuerpo { grid-template-columns: minmax(0, 1fr); }
    .cvc-lado { position: static; order: -1; }
}
@media (max-width: 767px) {
    .cvc-form { padding: 16px 16px 0; }
    .cvc-barra { margin: 0 -16px; padding: 12px 16px; }
    .cvc-fila, .cvc-fila--host, .cvc-fila--callerid { grid-template-columns: minmax(0, 1fr); }
    .cvc-boton { min-height: 44px; flex: 1 1 auto; }
    .cvc-cambios { flex-basis: 100%; }
}
</style>
