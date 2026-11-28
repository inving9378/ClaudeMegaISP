<template>
    <div class="cd cp-raiz ia" :class="{ 'cd-dark': darkMode }">
        <!-- Cabecera -->
        <header class="ia-cabecera">
            <div>
                <h1 class="ia-titulo">Asistente de voz</h1>
                <p class="ia-sub">Contesta las llamadas de la cola, resuelve lo que puede y pasa a una persona lo demás.</p>
            </div>
            <button v-if="puedeConfigurar" type="button" role="switch" class="ia-interruptor"
                    :aria-checked="guardado.enabled ? 'true' : 'false'" :disabled="!cargado || cambiandoInterruptor"
                    @click="alternarEncendido">
                <span class="ia-interruptor-pista" aria-hidden="true"><span class="ia-interruptor-bola"></span></span>
                <span>{{ guardado.enabled ? 'Contesta llamadas' : 'Apagada' }}</span>
            </button>
        </header>

        <!-- Estado: ¿está contestando? -->
        <section class="ia-estado" aria-labelledby="ia-estado-titulo">
            <div class="ia-estado-cabeza" :class="`ia-tono--${estadoGeneral.tono}`">
                <span class="ia-estado-icono" aria-hidden="true"><i :class="estadoGeneral.icono"></i></span>
                <div class="ia-estado-texto" aria-live="polite">
                    <h2 id="ia-estado-titulo" class="ia-estado-titulo">{{ estadoGeneral.titulo }}</h2>
                    <p class="ia-estado-detalle">{{ estadoGeneral.detalle }}</p>
                </div>
                <button type="button" class="ia-icono" :disabled="cargandoEstado" aria-label="Revisar de nuevo" title="Revisar de nuevo"
                        @click="cargarEstado">
                    <i class="fa fa-sync-alt" :class="{ 'fa-spin': cargandoEstado }" aria-hidden="true"></i>
                </button>
            </div>

            <ul class="ia-requisitos" aria-label="Lo que necesita para contestar">
                <template v-if="estado">
                    <li v-for="r in requisitos" :key="r.clave" class="ia-req" :class="{ 'ia-req--mal': !r.ok }">
                        <i :class="r.ok ? 'fa fa-check-circle' : 'fa fa-exclamation-circle'" aria-hidden="true"></i>
                        <span class="ia-req-nombre">{{ r.nombre }}</span>
                        <span class="ia-req-valor">{{ r.valor }}</span>
                        <span class="ia-sr">{{ r.ok ? 'listo' : 'falta' }}</span>
                    </li>
                </template>
                <template v-else>
                    <li v-for="i in 5" :key="i" class="ia-req ia-req--esqueleto" aria-hidden="true"><span class="ia-esqueleto"></span></li>
                </template>
            </ul>

            <ul v-if="problemas.length" class="ia-problemas">
                <li v-for="p in problemas" :key="p.clave">
                    <strong>{{ p.nombre }}:</strong> {{ p.texto }}
                    <a v-if="p.enlace" :href="p.enlace" class="ia-enlace">{{ p.enlaceTexto }}</a>
                </li>
            </ul>

            <dl v-if="estado" class="ia-hoy">
                <div><dt>Atendidas hoy</dt><dd class="cp-num">{{ n(estado.hoy.atendidas) }}</dd></div>
                <div><dt>Pasaron a una persona</dt><dd class="cp-num">{{ n(estado.hoy.transferidas) }}</dd></div>
                <div><dt>Eran clientes</dt><dd class="cp-num">{{ n(estado.hoy.clientes) }}</dd></div>
                <div><dt>Costo de IA hoy</dt><dd class="cp-num">{{ dinero(estado.hoy.costo_usd) }}</dd></div>
            </dl>
        </section>

        <div v-if="errorCarga" class="ia-aviso ia-aviso--error" role="alert">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
            <span>{{ errorCarga }} <button type="button" class="ia-enlace" @click="cargarConfig">Reintentar</button></span>
        </div>

        <!-- Pestañas -->
        <div class="ia-pestanas" role="tablist" aria-label="Secciones de la asistente" @keydown="teclaPestana">
            <button v-for="p in pestanas" :key="p.id" :id="`ia-tab-${p.id}`" ref="pestanas" type="button" role="tab"
                    class="ia-pestana" :aria-selected="pestana === p.id ? 'true' : 'false'" :aria-controls="`ia-panel-${p.id}`"
                    :tabindex="pestana === p.id ? 0 : -1" @click="irA(p.id)">
                {{ p.titulo }}
                <span v-if="p.cuenta" class="ia-pestana-n cp-num">{{ p.cuenta }}</span>
            </button>
        </div>

        <!-- ═══ Configuración ═══ -->
        <div v-if="puedeConfigurar" v-show="pestana === 'config'" id="ia-panel-config" role="tabpanel" aria-labelledby="ia-tab-config" tabindex="0">
            <form v-if="cargado" class="ia-config" novalidate @submit.prevent="guardar">
                <div class="ia-columnas">
                    <div class="ia-col">
                        <section class="ia-panel" aria-labelledby="ia-s-instrucciones">
                            <header class="ia-panel-cabeza">
                                <h2 id="ia-s-instrucciones" class="ia-panel-titulo">Qué sabe y cómo atiende</h2>
                                <p class="ia-panel-desc">Quién es, qué servicios y precios ofrece y cuándo pasar la llamada a una persona.</p>
                            </header>
                            <div class="ia-panel-cuerpo">
                                <div class="cp-campo-grupo">
                                    <label for="ia-prompt" class="cp-etiqueta">Instrucciones</label>
                                    <textarea id="ia-prompt" v-model="form.system_prompt" class="cp-campo ia-prompt" rows="14"
                                              :placeholder="catalogos.prompt_base" :aria-invalid="errores.system_prompt ? 'true' : null"
                                              aria-describedby="ia-prompt-ayuda"></textarea>
                                    <p v-if="errores.system_prompt" class="cp-error">{{ errores.system_prompt }}</p>
                                    <p id="ia-prompt-ayuda" class="cp-ayuda">
                                        Para pasar la llamada, la IA termina su respuesta con <code>[TRANSFERIR]</code>.
                                        Vacío, usa unas instrucciones básicas.
                                        <span class="cp-num ia-contador">{{ n(form.system_prompt.length) }} caracteres</span>
                                    </p>
                                </div>
                                <details class="ia-detalles">
                                    <summary>Lo que se agrega solo en cada llamada</summary>
                                    <ul>
                                        <li><strong>Reglas de teléfono:</strong> respuestas de una o dos oraciones, una pregunta a la vez, sin listas ni enlaces, sin inventar datos.</li>
                                        <li><strong>Quién llama:</strong> si el número es de un cliente, su nombre, estado del servicio, plan, saldo, fechas de pago y corte y su referencia de pago. Si no, que puede ser alguien interesado en contratar.</li>
                                        <li><strong>La fecha y la hora</strong> de la llamada.</li>
                                        <li><strong>La base de conocimiento</strong> completa, con los pasos de cada problema.</li>
                                    </ul>
                                </details>
                            </div>
                        </section>

                        <section class="ia-panel" aria-labelledby="ia-s-saludo">
                            <header class="ia-panel-cabeza">
                                <h2 id="ia-s-saludo" class="ia-panel-titulo">Saludo</h2>
                                <p class="ia-panel-desc">Lo primero que oye quien llama, justo después del tono.</p>
                            </header>
                            <div class="ia-panel-cuerpo">
                                <div class="cp-campo-grupo">
                                    <div class="ia-etiqueta-fila">
                                        <label for="ia-saludo-cliente" class="cp-etiqueta cp-etiqueta--sin-margen">Cuando llama un cliente</label>
                                        <button type="button" class="ia-boton ia-boton--chico" :disabled="escucha.cargando !== null"
                                                @click="escucharSaludo('cliente')">
                                            <i :class="iconoEscucha('cliente')" aria-hidden="true"></i> {{ textoEscucha('cliente') }}
                                        </button>
                                    </div>
                                    <textarea id="ia-saludo-cliente" v-model="form.greeting_customer" class="cp-campo ia-saludo" rows="2"
                                              maxlength="500" :aria-invalid="errores.greeting_customer ? 'true' : null"></textarea>
                                    <p v-if="errores.greeting_customer" class="cp-error">{{ errores.greeting_customer }}</p>
                                    <p class="cp-ayuda"><code>[nombre]</code> se cambia por el primer nombre del cliente. Vacío, usa el de abajo.</p>
                                </div>
                                <div class="cp-campo-grupo">
                                    <div class="ia-etiqueta-fila">
                                        <label for="ia-saludo-nuevo" class="cp-etiqueta cp-etiqueta--sin-margen">Cuando llama alguien que no es cliente</label>
                                        <button type="button" class="ia-boton ia-boton--chico" :disabled="escucha.cargando !== null"
                                                @click="escucharSaludo('nuevo')">
                                            <i :class="iconoEscucha('nuevo')" aria-hidden="true"></i> {{ textoEscucha('nuevo') }}
                                        </button>
                                    </div>
                                    <textarea id="ia-saludo-nuevo" v-model="form.greeting_lead" class="cp-campo ia-saludo" rows="2"
                                              maxlength="500" :aria-invalid="errores.greeting_lead ? 'true' : null"></textarea>
                                    <p v-if="errores.greeting_lead" class="cp-error">{{ errores.greeting_lead }}</p>
                                </div>
                                <p v-if="escucha.error" class="ia-aviso ia-aviso--error ia-aviso--chico" role="alert">{{ escucha.error }}</p>
                                <p class="cp-tenue ia-nota">Escuchar usa la voz elegida, aunque no hayas guardado; cada muestra se cobra en Módulos IA.</p>
                            </div>
                        </section>
                    </div>

                    <div class="ia-col ia-col--lateral">
                        <section class="ia-panel" aria-labelledby="ia-s-voz">
                            <header class="ia-panel-cabeza">
                                <h2 id="ia-s-voz" class="ia-panel-titulo">Voz</h2>
                            </header>
                            <div class="ia-panel-cuerpo">
                                <div class="cp-campo-grupo">
                                    <label for="ia-voz" class="cp-etiqueta">Voz</label>
                                    <select id="ia-voz" v-model="form.voice" class="cp-campo">
                                        <option v-for="v in catalogos.voces" :key="v.id" :value="v.id">{{ v.etiqueta }}</option>
                                    </select>
                                </div>
                                <div class="cp-campo-grupo">
                                    <label for="ia-tono" class="cp-etiqueta">Tono</label>
                                    <select id="ia-tono" v-model="form.tono" class="cp-campo" :disabled="estado && !estado.voz_tono"
                                            aria-describedby="ia-tono-ayuda">
                                        <option v-for="t in catalogos.tonos" :key="t.id" :value="t.id">{{ t.etiqueta }}</option>
                                    </select>
                                    <p id="ia-tono-ayuda" class="cp-ayuda">
                                        <template v-if="estado && !estado.voz_tono">El tono y el acento de México necesitan el modelo <code>gpt-4o-mini-tts</code> en Módulos IA.</template>
                                        <template v-else>Siempre en español de México.</template>
                                    </p>
                                </div>
                                <div class="cp-campo-grupo">
                                    <div class="ia-etiqueta-fila">
                                        <label for="ia-velocidad" class="cp-etiqueta cp-etiqueta--sin-margen">Velocidad</label>
                                        <output for="ia-velocidad" class="ia-valor cp-num">{{ textoVelocidad }}</output>
                                    </div>
                                    <input id="ia-velocidad" v-model.number="form.velocidad" type="range" class="ia-rango"
                                           :min="catalogos.velocidad.min" :max="catalogos.velocidad.max" step="0.05">
                                    <div class="ia-rango-extremos" aria-hidden="true"><span>Más lenta</span><span>Más rápida</span></div>
                                </div>
                            </div>
                        </section>

                        <section class="ia-panel" aria-labelledby="ia-s-cuando">
                            <header class="ia-panel-cabeza">
                                <h2 id="ia-s-cuando" class="ia-panel-titulo">Cuándo contesta</h2>
                            </header>
                            <div class="ia-panel-cuerpo">
                                <div class="cp-campo-grupo">
                                    <div class="ia-etiqueta-fila">
                                        <label for="ia-piloto" class="cp-etiqueta cp-etiqueta--sin-margen">Llamadas que atiende</label>
                                        <output for="ia-piloto" class="ia-valor cp-num">{{ form.piloto_porcentaje }} %</output>
                                    </div>
                                    <input id="ia-piloto" v-model.number="form.piloto_porcentaje" type="range" class="ia-rango"
                                           min="0" max="100" step="5" aria-describedby="ia-piloto-ayuda">
                                    <p id="ia-piloto-ayuda" class="cp-ayuda">{{ ayudaPiloto }}</p>
                                </div>
                                <div class="cp-par">
                                    <div class="cp-campo-grupo">
                                        <label for="ia-desde" class="cp-etiqueta">Desde</label>
                                        <input id="ia-desde" v-model="form.horario_inicio" type="time" class="cp-campo"
                                               :aria-invalid="errores.horario_inicio ? 'true' : null">
                                    </div>
                                    <div class="cp-campo-grupo">
                                        <label for="ia-hasta" class="cp-etiqueta">Hasta</label>
                                        <input id="ia-hasta" v-model="form.horario_fin" type="time" class="cp-campo"
                                               :aria-invalid="errores.horario_fin ? 'true' : null">
                                    </div>
                                </div>
                                <p v-if="errores.horario_fin || errores.horario_inicio" class="cp-error ia-error-par">{{ errores.horario_fin || errores.horario_inicio }}</p>
                                <div class="cp-campo-grupo">
                                    <label for="ia-lineas" class="cp-etiqueta">Llamadas a la vez</label>
                                    <input id="ia-lineas" v-model.number="form.lineas" type="number" class="cp-campo ia-numero" min="1"
                                           :max="catalogos.lineas_max" inputmode="numeric" aria-describedby="ia-lineas-ayuda"
                                           :aria-invalid="errores.lineas ? 'true' : null">
                                    <p v-if="errores.lineas" class="cp-error">{{ errores.lineas }}</p>
                                    <p id="ia-lineas-ayuda" class="cp-ayuda">Si ya está en tantas llamadas, la siguiente pasa directo a una persona.</p>
                                </div>
                            </div>
                        </section>

                        <section class="ia-panel" aria-labelledby="ia-s-limites">
                            <header class="ia-panel-cabeza">
                                <h2 id="ia-s-limites" class="ia-panel-titulo">Cada llamada</h2>
                            </header>
                            <div class="ia-panel-cuerpo">
                                <div class="cp-par">
                                    <div class="cp-campo-grupo">
                                        <label for="ia-turnos" class="cp-etiqueta">Respuestas máximas</label>
                                        <input id="ia-turnos" v-model.number="form.max_turns" type="number" class="cp-campo" min="3" max="30" inputmode="numeric"
                                               :aria-invalid="errores.max_turns ? 'true' : null">
                                    </div>
                                    <div class="cp-campo-grupo">
                                        <label for="ia-duracion" class="cp-etiqueta">Duración máxima</label>
                                        <select id="ia-duracion" v-model.number="form.max_duration_seconds" class="cp-campo">
                                            <option v-for="m in opcionesDuracion" :key="m" :value="m * 60">{{ m }} min</option>
                                        </select>
                                    </div>
                                </div>
                                <p v-if="errores.max_turns" class="cp-error ia-error-par">{{ errores.max_turns }}</p>
                                <div class="cp-campo-grupo">
                                    <label for="ia-silencio" class="cp-etiqueta">Si no dice nada, pregunta «¿sigues ahí?» a los</label>
                                    <select id="ia-silencio" v-model.number="form.timeout_seconds" class="cp-campo" aria-describedby="ia-silencio-ayuda">
                                        <option v-for="s in opcionesSilencio" :key="s" :value="s">{{ s }} segundos</option>
                                    </select>
                                    <p id="ia-silencio-ayuda" class="cp-ayuda">Al segundo silencio, o al llegar al máximo, avisa y pasa la llamada a una persona.</p>
                                </div>
                                <div class="cp-campo-grupo">
                                    <div class="ia-etiqueta-fila">
                                        <label for="ia-creatividad" class="cp-etiqueta cp-etiqueta--sin-margen">Creatividad</label>
                                        <output for="ia-creatividad" class="ia-valor cp-num">{{ Number(form.temperature).toFixed(1) }}</output>
                                    </div>
                                    <input id="ia-creatividad" v-model.number="form.temperature" type="range" class="ia-rango" min="0" max="1" step="0.1"
                                           aria-describedby="ia-creatividad-ayuda">
                                    <div class="ia-rango-extremos" aria-hidden="true"><span>Precisa</span><span>Creativa</span></div>
                                    <p id="ia-creatividad-ayuda" class="cp-ayuda">Para atención a clientes conviene baja. Algunos modelos (gpt-5 en adelante) no la usan.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="ia-barra" :class="{ 'ia-barra--visible': sucio || guardando || errorGuardar }" role="region" aria-label="Guardar cambios">
                    <p class="ia-barra-texto" aria-live="polite">
                        <template v-if="errorGuardar"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> {{ errorGuardar }}</template>
                        <template v-else-if="sucio">Hay cambios sin guardar.</template>
                    </p>
                    <button type="button" class="ia-boton" :disabled="!sucio || guardando" @click="descartar">Descartar</button>
                    <button type="submit" class="ia-boton ia-boton--primario" :disabled="!sucio || guardando">
                        <i v-if="guardando" class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                        {{ guardando ? 'Guardando…' : 'Guardar cambios' }}
                    </button>
                </div>
            </form>
            <div v-else-if="!errorCarga" class="ia-columnas" aria-hidden="true">
                <div class="ia-col"><div class="ia-panel ia-panel--esqueleto"></div></div>
                <div class="ia-col ia-col--lateral"><div class="ia-panel ia-panel--esqueleto"></div></div>
            </div>
        </div>

        <!-- ═══ Probar ═══ -->
        <div v-if="puedeConfigurar" v-show="pestana === 'probar'" id="ia-panel-probar" class="ia-probar" role="tabpanel" aria-labelledby="ia-tab-probar" tabindex="0">
            <section class="ia-panel ia-chat" aria-labelledby="ia-s-probar">
                <header class="ia-panel-cabeza ia-chat-cabeza">
                    <div>
                        <h2 id="ia-s-probar" class="ia-panel-titulo">Probar sin llamar</h2>
                        <p class="ia-panel-desc">Escribe lo que diría quien llama. Usa la configuración de la pestaña anterior, aunque no esté guardada.</p>
                    </div>
                    <button type="button" class="ia-boton ia-boton--chico" :disabled="!prueba.turnos.length || prueba.pensando" @click="reiniciarPrueba">
                        <i class="fa fa-redo" aria-hidden="true"></i> Empezar de nuevo
                    </button>
                </header>

                <div class="ia-chat-opciones">
                    <div class="cp-campo-grupo ia-chat-numero">
                        <label for="ia-prueba-numero" class="cp-etiqueta">Llama desde <span class="cp-opcional">(opcional)</span></label>
                        <input id="ia-prueba-numero" v-model="prueba.numero" type="tel" class="cp-campo" inputmode="tel" maxlength="20"
                               placeholder="Teléfono de un cliente" :disabled="prueba.turnos.length > 0" aria-describedby="ia-prueba-numero-ayuda">
                        <p id="ia-prueba-numero-ayuda" class="cp-ayuda">
                            <template v-if="prueba.cliente && prueba.cliente.encontrado">
                                Contesta como a <strong>{{ prueba.cliente.nombre }}</strong> · {{ prueba.cliente.estado || 'sin estado' }} · {{ textoSaldo(prueba.cliente.saldo) }}
                            </template>
                            <template v-else-if="prueba.cliente">Ese número no es de ningún cliente: contesta como a alguien nuevo.</template>
                            <template v-else>Con el teléfono de un cliente, contesta con sus datos (estado, plan, saldo).</template>
                        </p>
                    </div>
                    <label class="ia-check">
                        <input v-model="prueba.conVoz" type="checkbox">
                        <span>Oír las respuestas</span>
                    </label>
                </div>

                <ol ref="chat" class="ia-chat-turnos" aria-live="polite" aria-label="Conversación de prueba">
                    <li v-if="!prueba.turnos.length && !prueba.pensando" class="ia-chat-vacio">
                        La asistente saluda al empezar la llamada. Escribe la primera frase de quien llama, por ejemplo «No tengo internet desde ayer» o «¿Cuánto debo?».
                    </li>
                    <li v-for="(t, i) in prueba.turnos" :key="i" class="ia-turno" :class="`ia-turno--${t.quien}`">
                        <span class="ia-turno-quien">{{ t.quien === 'asistente' ? 'Asistente' : 'Quien llama' }}</span>
                        <p class="ia-turno-texto">{{ t.texto }}</p>
                        <span v-if="t.transferir" class="ia-insignia ia-insignia--info"><i class="fa fa-user" aria-hidden="true"></i> Pasa la llamada a una persona</span>
                        <audio v-if="t.audio" :src="t.audio" controls preload="auto" class="ia-turno-audio"></audio>
                        <span v-if="t.meta" class="ia-turno-meta cp-num">{{ t.meta }}</span>
                    </li>
                    <li v-if="prueba.pensando" class="ia-turno ia-turno--asistente ia-turno--pensando">
                        <span class="ia-turno-quien">Asistente</span>
                        <span class="ia-puntos" aria-label="Pensando"><span></span><span></span><span></span></span>
                    </li>
                </ol>

                <p v-if="prueba.error" class="ia-aviso ia-aviso--error ia-aviso--chico ia-chat-error" role="alert">{{ prueba.error }}</p>

                <form class="ia-chat-entrada" @submit.prevent="enviarPrueba">
                    <label for="ia-prueba-texto" class="ia-sr">Lo que dice quien llama</label>
                    <input id="ia-prueba-texto" ref="pruebaTexto" v-model="prueba.texto" class="cp-campo" maxlength="500" autocomplete="off"
                           :placeholder="prueba.terminada ? 'La llamada pasó a una persona. Empieza de nuevo para otra prueba.' : 'Lo que dice quien llama…'"
                           :disabled="prueba.pensando || prueba.terminada">
                    <button type="submit" class="ia-boton ia-boton--primario" :disabled="!prueba.texto.trim() || prueba.pensando || prueba.terminada">
                        <i class="fa fa-paper-plane" aria-hidden="true"></i> Enviar
                    </button>
                </form>
            </section>

            <aside class="ia-panel ia-ideas" aria-labelledby="ia-s-ideas">
                <header class="ia-panel-cabeza">
                    <h2 id="ia-s-ideas" class="ia-panel-titulo">Para probar</h2>
                    <p class="ia-panel-desc">Lo que más se pregunta por teléfono. Toca una frase para escribirla.</p>
                </header>
                <ul class="ia-ideas-lista">
                    <li v-for="idea in IDEAS" :key="idea">
                        <button type="button" class="ia-idea" :disabled="prueba.pensando || prueba.terminada" @click="usarIdea(idea)">{{ idea }}</button>
                    </li>
                </ul>
                <dl class="ia-ideas-usa">
                    <div><dt>Voz</dt><dd>{{ nombreVoz }}</dd></div>
                    <div><dt>Conversación</dt><dd>{{ modeloDe('voip.bot_voz') }}</dd></div>
                </dl>
                <p class="cp-tenue ia-ideas-nota">Cada respuesta usa la IA y se cobra en Módulos IA, igual que en una llamada. Estas pruebas no se guardan en Conversaciones.</p>
            </aside>
        </div>

        <!-- ═══ Conversaciones ═══ -->
        <div v-show="pestana === 'conversaciones'" id="ia-panel-conversaciones" role="tabpanel" aria-labelledby="ia-tab-conversaciones" tabindex="0">
            <section class="ia-panel">
                <div class="ia-filtros">
                    <div class="ia-buscar">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <label for="ia-conv-buscar" class="ia-sr">Buscar por teléfono o cliente</label>
                        <input id="ia-conv-buscar" v-model="conv.buscar" type="search" class="cp-campo" placeholder="Teléfono o cliente"
                               @input="buscarConversacionesLuego">
                    </div>
                    <label class="ia-sr" for="ia-conv-tipo">Quién llamó</label>
                    <select id="ia-conv-tipo" v-model="conv.tipo" class="cp-campo ia-filtro" @change="cargarConversaciones(1)">
                        <option value="">Todos</option>
                        <option value="customer_support">Clientes</option>
                        <option value="sales_lead">No clientes</option>
                    </select>
                    <label class="ia-sr" for="ia-conv-estado">Resultado</label>
                    <select id="ia-conv-estado" v-model="conv.estado" class="cp-campo ia-filtro" @change="cargarConversaciones(1)">
                        <option value="">Cualquier resultado</option>
                        <option value="transferred">Pasó a una persona</option>
                        <option value="completed">Terminó con la asistente</option>
                    </select>
                    <label class="ia-sr" for="ia-conv-motivo">Motivo</label>
                    <select id="ia-conv-motivo" v-model="conv.categoria" class="cp-campo ia-filtro" @change="cargarConversaciones(1)">
                        <option value="">Cualquier motivo</option>
                        <option v-for="(etiqueta, id) in catalogos.categorias" :key="id" :value="id">{{ etiqueta }}</option>
                    </select>
                    <span class="cp-tenue ia-total" aria-live="polite">{{ conv.cargada ? `${n(conv.total)} ${conv.total === 1 ? 'llamada' : 'llamadas'}` : '' }}</span>
                </div>

                <div v-if="conv.error" class="ia-aviso ia-aviso--error ia-aviso--en-panel" role="alert">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ conv.error }}
                </div>

                <div class="ia-tabla-caja" :class="{ 'ia-cargando': conv.cargando && conv.cargada }">
                    <table class="ia-tabla">
                        <caption class="ia-sr">Llamadas que atendió la asistente</caption>
                        <thead>
                            <tr>
                                <th scope="col">Cuándo</th>
                                <th scope="col">Quién llamó</th>
                                <th scope="col">De qué se trató</th>
                                <th scope="col">Resultado</th>
                                <th scope="col" class="ia-col-num ia-ocultable">Duración</th>
                                <th scope="col" class="ia-col-num ia-ocultable">Costo</th>
                                <th scope="col"><span class="ia-sr">Ver</span></th>
                            </tr>
                        </thead>
                        <tbody v-if="!conv.cargada">
                            <tr v-for="i in 4" :key="i" aria-hidden="true"><td colspan="7"><span class="ia-esqueleto"></span></td></tr>
                        </tbody>
                        <tbody v-else>
                            <tr v-for="c in conv.filas" :key="c.id">
                                <td class="ia-nowrap cp-num">{{ fecha(c.inicio) }}</td>
                                <td>
                                    <span class="ia-fuerte">{{ c.cliente || (c.telefono ? c.telefono : 'Número oculto') }}</span>
                                    <span v-if="c.cliente && c.telefono" class="ia-secundario cp-num">{{ c.telefono }}</span>
                                    <span v-else-if="!c.cliente_id" class="ia-secundario">No es cliente</span>
                                </td>
                                <td class="ia-col-resumen">
                                    <span class="ia-fuerte">{{ catalogos.categorias[c.categoria] || 'Sin analizar' }}</span>
                                    <span v-if="c.resumen" class="ia-secundario ia-dos-lineas">{{ c.resumen }}</span>
                                </td>
                                <td><span class="ia-insignia" :class="resultado(c).clase">{{ resultado(c).texto }}</span></td>
                                <td class="ia-col-num ia-ocultable cp-num">{{ duracion(c.duracion) }}</td>
                                <td class="ia-col-num ia-ocultable cp-num">{{ dinero(c.costo_usd) }}</td>
                                <td class="ia-col-accion">
                                    <button type="button" class="ia-boton ia-boton--chico" @click="verConversacion(c)">
                                        Ver<span class="ia-sr"> la llamada de {{ c.cliente || c.telefono || 'número oculto' }}</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!conv.filas.length && !conv.error">
                                <td colspan="7" class="ia-vacio">
                                    <i class="fa fa-headset" aria-hidden="true"></i>
                                    <p v-if="hayFiltrosConv">Ninguna llamada con esos filtros. <button type="button" class="ia-enlace" @click="limpiarFiltrosConv">Quitar filtros</button></p>
                                    <p v-else>Todavía no ha atendido llamadas. Cuando conteste, aquí verás qué pidió cada persona y en qué quedó.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="conv.ultima > 1" class="ia-paginas" aria-label="Páginas">
                    <button type="button" class="ia-icono" :disabled="conv.pagina <= 1 || conv.cargando" aria-label="Página anterior" @click="cargarConversaciones(conv.pagina - 1)">
                        <i class="fa fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <span class="cp-num">Página {{ conv.pagina }} de {{ conv.ultima }}</span>
                    <button type="button" class="ia-icono" :disabled="conv.pagina >= conv.ultima || conv.cargando" aria-label="Página siguiente" @click="cargarConversaciones(conv.pagina + 1)">
                        <i class="fa fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </nav>
            </section>
        </div>

        <!-- ═══ Leads ═══ -->
        <div v-if="puedeLeads" v-show="pestana === 'leads'" id="ia-panel-leads" role="tabpanel" aria-labelledby="ia-tab-leads" tabindex="0">
            <section class="ia-panel">
                <div class="ia-filtros">
                    <div class="ia-segmentos" role="group" aria-label="Estado del lead">
                        <button v-for="s in segmentosLead" :key="s.id" type="button" class="ia-segmento"
                                :aria-pressed="leads.estado === s.id ? 'true' : 'false'" @click="filtrarLeads(s.id)">{{ s.texto }}</button>
                    </div>
                    <span class="cp-tenue ia-total" aria-live="polite">{{ leads.cargada ? `${n(leads.total)} ${leads.total === 1 ? 'lead' : 'leads'}` : '' }}</span>
                </div>
                <p class="ia-panel-desc ia-leads-desc">Personas que no son clientes y quisieron contratar o cotizar. Se anotan solas al colgar, con lo que dijeron en la llamada.</p>

                <div v-if="leads.error" class="ia-aviso ia-aviso--error ia-aviso--en-panel" role="alert">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ leads.error }}
                </div>

                <div class="ia-tabla-caja" :class="{ 'ia-cargando': leads.cargando && leads.cargada }">
                    <table class="ia-tabla">
                        <caption class="ia-sr">Leads de la asistente</caption>
                        <thead>
                            <tr>
                                <th scope="col">Cuándo</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Le interesa</th>
                                <th scope="col">Estado</th>
                                <th scope="col"><span class="ia-sr">Ver</span></th>
                            </tr>
                        </thead>
                        <tbody v-if="!leads.cargada">
                            <tr v-for="i in 3" :key="i" aria-hidden="true"><td colspan="6"><span class="ia-esqueleto"></span></td></tr>
                        </tbody>
                        <tbody v-else>
                            <tr v-for="l in leads.filas" :key="l.id">
                                <td class="ia-nowrap cp-num">{{ fecha(l.created_at) }}</td>
                                <td class="ia-fuerte">{{ l.name || 'Sin nombre' }}</td>
                                <td class="cp-num ia-nowrap">{{ l.phone || '—' }}</td>
                                <td>{{ interes(l) || '—' }}</td>
                                <td>
                                    <label :for="`ia-lead-${l.id}`" class="ia-sr">Estado de {{ l.name || l.phone }}</label>
                                    <select :id="`ia-lead-${l.id}`" v-model="l.status" class="cp-campo ia-select-chico" @change="guardarEstadoLead(l)">
                                        <option v-for="(t, id) in ESTADOS_LEAD" :key="id" :value="id">{{ t }}</option>
                                    </select>
                                </td>
                                <td class="ia-col-accion">
                                    <button type="button" class="ia-boton ia-boton--chico" @click="abrirLead(l)">
                                        Ver<span class="ia-sr"> a {{ l.name || l.phone }}</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!leads.filas.length && !leads.error">
                                <td colspan="6" class="ia-vacio">
                                    <i class="fa fa-user-plus" aria-hidden="true"></i>
                                    <p v-if="leads.estado">Ningún lead en ese estado.</p>
                                    <p v-else>Aún no hay leads. Cuando alguien que no es cliente pregunte por contratar, aparecerá aquí con su nombre, su dirección y lo que le interesa.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="leads.ultima > 1" class="ia-paginas" aria-label="Páginas">
                    <button type="button" class="ia-icono" :disabled="leads.pagina <= 1 || leads.cargando" aria-label="Página anterior" @click="cargarLeads(leads.pagina - 1)">
                        <i class="fa fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <span class="cp-num">Página {{ leads.pagina }} de {{ leads.ultima }}</span>
                    <button type="button" class="ia-icono" :disabled="leads.pagina >= leads.ultima || leads.cargando" aria-label="Página siguiente" @click="cargarLeads(leads.pagina + 1)">
                        <i class="fa fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </nav>
            </section>
        </div>

        <!-- ═══ Base de conocimiento ═══ -->
        <div v-if="puedeKb" v-show="pestana === 'kb'" id="ia-panel-kb" role="tabpanel" aria-labelledby="ia-tab-kb" tabindex="0">
            <div class="ia-kb-cabeza">
                <p class="ia-panel-desc">Problemas frecuentes y los pasos para resolverlos. La asistente los sigue uno por uno, en sus palabras, y pasa la llamada si no se resuelve.</p>
                <button type="button" class="ia-boton ia-boton--primario" @click="abrirKb()">
                    <i class="fa fa-plus" aria-hidden="true"></i> Agregar problema
                </button>
            </div>

            <div v-if="kb.error" class="ia-aviso ia-aviso--error" role="alert">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ kb.error }}
            </div>

            <div v-if="!kb.cargada" class="ia-panel ia-panel--esqueleto" aria-hidden="true"></div>
            <template v-else>
                <section v-for="grupo in kbPorCategoria" :key="grupo.id" class="ia-panel ia-kb-grupo" :aria-labelledby="`ia-kb-${grupo.id}`">
                    <h2 :id="`ia-kb-${grupo.id}`" class="ia-kb-titulo">{{ grupo.titulo }} <span class="cp-tenue cp-num">{{ grupo.items.length }}</span></h2>
                    <ul class="ia-kb-lista">
                        <li v-for="item in grupo.items" :key="item.id" class="ia-kb-item">
                            <div class="ia-kb-texto">
                                <span class="ia-fuerte">{{ item.problem }}</span>
                                <span class="ia-secundario">
                                    {{ pasos(item).length }} {{ pasos(item).length === 1 ? 'paso' : 'pasos' }}<template v-if="item.escalate_if_fails"> · pasa a una persona si no se resuelve</template><template v-if="item.keywords"> · {{ item.keywords }}</template>
                                </span>
                            </div>
                            <div class="ia-kb-acciones">
                                <button type="button" class="ia-icono" :aria-label="`Editar «${item.problem}»`" title="Editar" @click="abrirKb(item)">
                                    <i class="fa fa-pen" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="ia-icono ia-icono--peligro" :aria-label="`Quitar «${item.problem}»`" title="Quitar" @click="kbBorrar = item">
                                    <i class="fa fa-trash-alt" aria-hidden="true"></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </section>
                <div v-if="!kb.filas.length && !kb.error" class="ia-panel ia-vacio ia-vacio--panel">
                    <i class="fa fa-book-open" aria-hidden="true"></i>
                    <p>Sin problemas todavía. Agrega los más comunes («sin internet», «internet lento», «¿cómo pago?») con sus pasos.</p>
                </div>
            </template>
        </div>

        <!-- Diálogo: una conversación -->
        <campanas-dialogo :abierto="!!convAbierta" variante="cajon" :oscuro="darkMode"
                          :titulo="convAbierta ? (convAbierta.cliente || convAbierta.telefono || 'Número oculto') : ''"
                          :descripcion="convAbierta ? `${fechaLarga(convAbierta.inicio)} · ${duracion(convAbierta.duracion)}` : ''"
                          @cerrar="convAbierta = null">
            <template v-if="convAbierta">
                <dl class="ia-ficha">
                    <div><dt>Resultado</dt><dd><span class="ia-insignia" :class="resultado(convAbierta).clase">{{ resultado(convAbierta).texto }}</span></dd></div>
                    <div><dt>Motivo</dt><dd>{{ catalogos.categorias[convAbierta.categoria] || 'Sin analizar' }}</dd></div>
                    <div><dt>Teléfono</dt><dd class="cp-num">{{ convAbierta.telefono || 'Oculto' }}</dd></div>
                    <div>
                        <dt>Cliente</dt>
                        <dd>
                            <a v-if="convAbierta.cliente_id" :href="`/cliente/editar/${convAbierta.cliente_id}`" target="_blank" rel="noopener" class="ia-enlace">
                                {{ convAbierta.cliente || `Cliente ${convAbierta.cliente_id}` }} <i class="fa fa-external-link-alt" aria-hidden="true"></i>
                            </a>
                            <template v-else>No es cliente</template>
                        </dd>
                    </div>
                    <div><dt>Costo de IA</dt><dd class="cp-num">{{ dinero(convAbierta.costo_usd) }}</dd></div>
                </dl>
                <p v-if="convAbierta.resumen" class="ia-resumen">{{ convAbierta.resumen }}</p>
                <h3 class="ia-subtitulo">Lo que se dijo</h3>
                <ol v-if="convAbierta.turnos.length" class="ia-chat-turnos ia-chat-turnos--lectura">
                    <li v-for="(t, i) in convAbierta.turnos" :key="i" class="ia-turno" :class="`ia-turno--${t.quien}`">
                        <span class="ia-turno-quien">{{ t.quien === 'asistente' ? 'Asistente' : 'Quien llama' }}</span>
                        <p class="ia-turno-texto">{{ t.texto || '…' }}</p>
                    </li>
                </ol>
                <p v-else class="cp-tenue">No quedó texto de esta llamada.</p>
            </template>
            <template #pie>
                <button type="button" class="ia-boton" data-autofoco @click="convAbierta = null">Cerrar</button>
            </template>
        </campanas-dialogo>

        <!-- Diálogo: un lead -->
        <campanas-dialogo :abierto="!!leadAbierto" :oscuro="darkMode" :titulo="leadAbierto ? (leadAbierto.name || 'Lead sin nombre') : ''"
                          :descripcion="leadAbierto ? `Llamó ${fechaLarga(leadAbierto.created_at)}` : ''" @cerrar="leadAbierto = null">
            <template v-if="leadAbierto">
                <dl class="ia-ficha">
                    <div><dt>Teléfono</dt><dd class="cp-num">{{ leadAbierto.phone || '—' }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ leadAbierto.email || '—' }}</dd></div>
                    <div><dt>Dirección</dt><dd>{{ leadAbierto.address || '—' }}</dd></div>
                    <div><dt>Le interesa</dt><dd>{{ interes(leadAbierto) || '—' }}</dd></div>
                </dl>
                <div class="cp-campo-grupo">
                    <label for="ia-lead-estado" class="cp-etiqueta">Estado</label>
                    <select id="ia-lead-estado" v-model="leadAbierto.status" class="cp-campo">
                        <option v-for="(t, id) in ESTADOS_LEAD" :key="id" :value="id">{{ t }}</option>
                    </select>
                </div>
                <div class="cp-campo-grupo">
                    <label for="ia-lead-notas" class="cp-etiqueta">Notas</label>
                    <textarea id="ia-lead-notas" v-model="leadAbierto.notes" class="cp-campo" rows="4" maxlength="5000"
                              placeholder="Qué se habló con esta persona, cuándo volver a llamarle…"></textarea>
                </div>
                <p v-if="leadAbierto.error" class="ia-aviso ia-aviso--error ia-aviso--chico" role="alert">{{ leadAbierto.error }}</p>
            </template>
            <template #pie>
                <button type="button" class="ia-boton" @click="leadAbierto = null">Cancelar</button>
                <button type="button" class="ia-boton ia-boton--primario" data-autofoco :disabled="guardandoLead" @click="guardarLead">
                    {{ guardandoLead ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </campanas-dialogo>

        <!-- Diálogo: problema de la base de conocimiento -->
        <campanas-dialogo :abierto="!!kbForm" :oscuro="darkMode" :titulo="kbForm && kbForm.id ? 'Editar problema' : 'Agregar problema'"
                          descripcion="La asistente sigue los pasos en orden, en sus propias palabras." @cerrar="kbForm = null">
            <form v-if="kbForm" id="ia-kb-form" novalidate @submit.prevent="guardarKb">
                <div class="cp-par">
                    <div class="cp-campo-grupo">
                        <label for="ia-kb-cat" class="cp-etiqueta">Tema</label>
                        <select id="ia-kb-cat" v-model="kbForm.category" class="cp-campo">
                            <option v-for="(t, id) in CATEGORIAS_KB" :key="id" :value="id">{{ t }}</option>
                        </select>
                    </div>
                    <div class="cp-campo-grupo">
                        <label for="ia-kb-problema" class="cp-etiqueta">Problema</label>
                        <input id="ia-kb-problema" v-model="kbForm.problem" class="cp-campo" maxlength="255" data-autofoco
                               placeholder="Sin internet" :aria-invalid="erroresKb.problem ? 'true' : null">
                        <p v-if="erroresKb.problem" class="cp-error">{{ erroresKb.problem }}</p>
                    </div>
                </div>
                <div class="cp-campo-grupo">
                    <label for="ia-kb-claves" class="cp-etiqueta">Cómo lo dicen <span class="cp-opcional">(opcional)</span></label>
                    <input id="ia-kb-claves" v-model="kbForm.keywords" class="cp-campo" maxlength="500" placeholder="no tengo internet, se cayó el wifi, no navega">
                </div>
                <fieldset class="cp-campo-grupo ia-pasos">
                    <legend class="cp-etiqueta">Pasos</legend>
                    <ol class="ia-pasos-lista">
                        <li v-for="(p, i) in kbForm.pasos" :key="p.id" class="ia-paso">
                            <span class="ia-paso-n cp-num" aria-hidden="true">{{ i + 1 }}</span>
                            <label :for="`ia-paso-${p.id}`" class="ia-sr">Paso {{ i + 1 }}</label>
                            <input :id="`ia-paso-${p.id}`" v-model="p.texto" class="cp-campo" maxlength="500"
                                   :placeholder="i === 0 ? '¿El módem tiene la luz verde encendida?' : 'Siguiente paso'">
                            <button type="button" class="ia-icono" :disabled="i === 0" :aria-label="`Subir el paso ${i + 1}`" @click="moverPaso(i, -1)">
                                <i class="fa fa-arrow-up" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="ia-icono ia-icono--peligro" :disabled="kbForm.pasos.length === 1" :aria-label="`Quitar el paso ${i + 1}`" @click="quitarPaso(i)">
                                <i class="fa fa-times" aria-hidden="true"></i>
                            </button>
                        </li>
                    </ol>
                    <button type="button" class="ia-boton ia-boton--chico" :disabled="kbForm.pasos.length >= 20" @click="agregarPaso">
                        <i class="fa fa-plus" aria-hidden="true"></i> Agregar paso
                    </button>
                    <p v-if="erroresKb.solution_steps" class="cp-error">{{ erroresKb.solution_steps }}</p>
                </fieldset>
                <label class="ia-check">
                    <input v-model="kbForm.escalate_if_fails" type="checkbox">
                    <span>Si con estos pasos no se resuelve, pasar la llamada a una persona</span>
                </label>
                <p v-if="erroresKb._general" class="ia-aviso ia-aviso--error ia-aviso--chico" role="alert">{{ erroresKb._general }}</p>
            </form>
            <template #pie>
                <button type="button" class="ia-boton" @click="kbForm = null">Cancelar</button>
                <button type="submit" form="ia-kb-form" class="ia-boton ia-boton--primario" :disabled="guardandoKb">
                    {{ guardandoKb ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </campanas-dialogo>

        <!-- Diálogo: confirmar quitar -->
        <campanas-dialogo :abierto="!!kbBorrar" :oscuro="darkMode" titulo="¿Quitar este problema?"
                          :descripcion="kbBorrar ? `«${kbBorrar.problem}» deja de estar en lo que sabe la asistente.` : ''" @cerrar="kbBorrar = null">
            <p class="ia-confirmar">Las llamadas que ya atendió no cambian. Para volver a tenerlo hay que agregarlo de nuevo.</p>
            <template #pie>
                <button type="button" class="ia-boton" data-autofoco @click="kbBorrar = null">Cancelar</button>
                <button type="button" class="ia-boton ia-boton--peligro" :disabled="borrandoKb" @click="borrarKb">
                    {{ borrandoKb ? 'Quitando…' : 'Quitar' }}
                </button>
            </template>
        </campanas-dialogo>
    </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';
import { message } from '../../../helpers/toastMsg.js';
import CampanasDialogo from '../cobranza/CampanasDialogo.vue';

// Lo que se edita en «Configuración» (el interruptor de encendido va aparte y guarda al instante).
const CAMPOS = [
    'system_prompt', 'greeting_customer', 'greeting_lead', 'voice', 'tono', 'velocidad', 'piloto_porcentaje', 'lineas',
    'horario_inicio', 'horario_fin', 'max_turns', 'max_duration_seconds', 'timeout_seconds', 'temperature',
];

const VACIA = {
    enabled: false, system_prompt: '', greeting_customer: '', greeting_lead: '', voice: 'nova', tono: 'calida', velocidad: 1,
    piloto_porcentaje: 0, lineas: 1, horario_inicio: '09:00', horario_fin: '20:00', max_turns: 12, max_duration_seconds: 480,
    timeout_seconds: 15, temperature: 0.3,
};

const ESTADOS_LEAD = { new: 'Nuevo', contacted: 'Contactado', interested: 'Interesado', converted: 'Contrató', rejected: 'No le interesó' };
const CATEGORIAS_KB = { internet: 'Internet', voip: 'Telefonía', facturacion: 'Pagos y facturación', cobertura: 'Cobertura', otro: 'Otro' };
const INTEGRACIONES = '/integraciones';
const IDEAS = [
    'No tengo internet desde ayer',
    'Mi internet está muy lento',
    '¿Cuánto debo y cómo pago?',
    '¿Por qué me suspendieron el servicio?',
    'Quiero contratar internet',
    '¿Tienen cobertura en mi colonia?',
    'Quiero hablar con una persona',
];

let idPaso = 0;

export default {
    name: 'VoipIaBotManager',
    components: { CampanasDialogo },
    props: {
        csrfToken: { type: String, default: '' },
        baseUrl:   { type: String, default: '/voip' },
        canConfig: { type: String, default: '0' },
        canLeads:  { type: String, default: '0' },
        canKb:     { type: String, default: '0' },
    },
    setup() {
        return { darkMode, ESTADOS_LEAD, CATEGORIAS_KB, IDEAS };
    },
    data() {
        return {
            cargado: false,
            errorCarga: '',
            form: { ...VACIA },
            guardado: { ...VACIA },
            catalogos: { voces: [], tonos: [], velocidad: { min: 0.75, max: 1.25, defecto: 1 }, lineas_max: 10, categorias: {}, prompt_base: '' },
            errores: {},
            guardando: false,
            errorGuardar: '',
            cambiandoInterruptor: false,

            estado: null,
            cargandoEstado: false,
            ahora: new Date(),

            pestana: this.canConfig === '1' ? 'config' : 'conversaciones',

            escucha: { cargando: null, sonando: null, error: '' },

            prueba: { numero: '', conVoz: false, texto: '', turnos: [], historial: [], pensando: false, error: '', cliente: null, terminada: false },

            conv: { filas: [], total: 0, pagina: 1, ultima: 1, cargando: false, cargada: false, error: '', buscar: '', tipo: '', estado: '', categoria: '' },
            convAbierta: null,

            leads: { filas: [], total: 0, pagina: 1, ultima: 1, cargando: false, cargada: false, error: '', estado: '' },
            leadAbierto: null,
            guardandoLead: false,

            kb: { filas: [], cargando: false, cargada: false, error: '' },
            kbForm: null,
            erroresKb: {},
            guardandoKb: false,
            kbBorrar: null,
            borrandoKb: false,
        };
    },
    computed: {
        puedeConfigurar() { return this.canConfig === '1'; },
        puedeLeads() { return this.canLeads === '1'; },
        puedeKb() { return this.canKb === '1'; },

        pestanas() {
            return [
                this.puedeConfigurar && { id: 'config', titulo: 'Configuración' },
                this.puedeConfigurar && { id: 'probar', titulo: 'Probar' },
                { id: 'conversaciones', titulo: 'Conversaciones' },
                this.puedeLeads && { id: 'leads', titulo: 'Leads', cuenta: this.estado?.leads_nuevos || 0 },
                this.puedeKb && { id: 'kb', titulo: 'Base de conocimiento' },
            ].filter(Boolean);
        },

        sucio() {
            return CAMPOS.some(c => String(this.form[c] ?? '') !== String(this.guardado[c] ?? ''));
        },

        gruposTexto() {
            const g = this.estado?.grupos || [];
            if (!g.length) return '';
            return g.length === 1 ? g[0].nombre : `${g.slice(0, -1).map(x => x.nombre).join(', ')} y ${g[g.length - 1].nombre}`;
        },

        dentroDeHorario() {
            const hm = `${String(this.ahora.getHours()).padStart(2, '0')}:${String(this.ahora.getMinutes()).padStart(2, '0')}`;
            return hm >= this.guardado.horario_inicio && hm <= this.guardado.horario_fin;
        },

        // Lo más importante de la pantalla: ¿contesta o no, y por qué?
        estadoGeneral() {
            const g = this.guardado;
            const e = this.estado;
            if (!this.cargado || !e) {
                return { tono: 'neutro', icono: 'fa fa-circle-notch fa-spin', titulo: 'Revisando si está lista…', detalle: '' };
            }
            if (!g.enabled) {
                return { tono: 'neutro', icono: 'fa fa-power-off', titulo: 'Apagada: las llamadas van directo a las personas',
                    detalle: this.puedeConfigurar ? 'Enciéndela con el interruptor de arriba cuando la hayas probado.' : 'Quien la configura puede encenderla desde esta pantalla.' };
            }
            if (!e.servicio.activo) {
                return { tono: 'mal', icono: 'fa fa-exclamation-triangle', titulo: 'No está contestando: su servicio no corre',
                    detalle: 'Todas las llamadas pasan a la cola. En Add-ons, VoIP → «Instalar dependencias» lo vuelve a levantar.' };
            }
            const faltan = e.funciones.filter(f => !f.ok);
            if (faltan.length) {
                return { tono: 'mal', icono: 'fa fa-exclamation-triangle', titulo: `No puede contestar: le falta IA para ${faltan.map(f => f.nombre.toLowerCase()).join(' y ')}`,
                    detalle: 'Al entrar una llamada pediría disculpas y la pasaría a una persona. Abajo dice qué falta.' };
            }
            if (!e.grupos.length) {
                return { tono: 'aviso', icono: 'fa fa-exclamation-circle', titulo: 'Ningún grupo le pasa llamadas',
                    detalle: 'Contesta en los grupos de timbrado tipo cola. Marca uno como cola en VoIP → Grupos de timbrado.' };
            }
            if (g.piloto_porcentaje <= 0) {
                return { tono: 'aviso', icono: 'fa fa-exclamation-circle', titulo: 'Encendida, pero atiende el 0 % de las llamadas',
                    detalle: 'Sube «Llamadas que atiende» en Configuración para que conteste.' };
            }
            if (!this.dentroDeHorario) {
                return { tono: 'neutro', icono: 'fa fa-moon', titulo: `Fuera de horario: vuelve a contestar a las ${g.horario_inicio}`,
                    detalle: `Contesta de ${g.horario_inicio} a ${g.horario_fin}. Mientras tanto las llamadas van a las personas.` };
            }
            const cuantas = g.piloto_porcentaje >= 100 ? 'todas las llamadas' : `el ${g.piloto_porcentaje} % de las llamadas`;
            return { tono: 'ok', icono: 'fa fa-check', titulo: `Contestando ${cuantas} de ${this.gruposTexto}`,
                detalle: `De ${g.horario_inicio} a ${g.horario_fin}, hasta ${g.lineas} ${g.lineas === 1 ? 'llamada' : 'llamadas'} a la vez; si está ocupada, la siguiente pasa a una persona.` };
        },

        requisitos() {
            const e = this.estado;
            if (!e) return [];
            return [
                { clave: 'servicio', nombre: 'Servicio', ok: e.servicio.activo, valor: e.servicio.activo ? 'Corriendo' : 'Detenido' },
                ...e.funciones.map(f => ({
                    clave: f.clave, nombre: f.nombre, ok: f.ok,
                    valor: f.ok ? `${f.proveedor} · ${f.modelo}` : 'Sin IA',
                })),
                { clave: 'grupos', nombre: 'Grupos', ok: e.grupos.length > 0, valor: e.grupos.length ? this.gruposTexto : 'Ninguno' },
            ];
        },

        problemas() {
            const e = this.estado;
            if (!e) return [];
            return e.funciones.filter(f => !f.ok).map(f => ({
                clave: f.clave, nombre: f.nombre, texto: f.problema, enlace: INTEGRACIONES, enlaceTexto: 'Ir a Integraciones',
            }));
        },

        nombreVoz() {
            const v = this.catalogos.voces.find(x => x.id === this.form.voice);
            const t = this.catalogos.tonos.find(x => x.id === this.form.tono);
            return [v ? v.etiqueta.replace(/\s*\(.*\)$/, '') : this.form.voice, t && this.estado?.voz_tono ? t.etiqueta.toLowerCase() : null].filter(Boolean).join(', ');
        },

        textoVelocidad() {
            const v = Number(this.form.velocidad) || 1;
            return Math.abs(v - 1) < 0.001 ? 'Normal' : `${v.toFixed(2)}×`;
        },

        ayudaPiloto() {
            const p = this.form.piloto_porcentaje;
            if (p <= 0) return 'En 0 % no contesta a nadie aunque esté encendida.';
            if (p >= 100) return 'Contesta todas las llamadas de la cola dentro del horario.';
            return `Contesta ${p} de cada 100 llamadas, al azar; las demás van directo a las personas. Sirve para probarla poco a poco.`;
        },

        opcionesDuracion() {
            const base = [2, 3, 4, 5, 6, 8, 10, 12, 15];
            const actual = Math.round((this.form.max_duration_seconds || 480) / 60);
            return base.includes(actual) ? base : [...base, actual].sort((a, b) => a - b);
        },

        opcionesSilencio() {
            const base = [8, 10, 12, 15, 20, 25, 30];
            const actual = this.form.timeout_seconds;
            return base.includes(actual) ? base : [...base, actual].sort((a, b) => a - b);
        },

        hayFiltrosConv() {
            return !!(this.conv.buscar || this.conv.tipo || this.conv.estado || this.conv.categoria);
        },

        segmentosLead() {
            return [{ id: '', texto: 'Todos' }, ...Object.entries(ESTADOS_LEAD).map(([id, texto]) => ({ id, texto }))];
        },

        kbPorCategoria() {
            const grupos = {};
            for (const item of this.kb.filas) {
                (grupos[item.category] = grupos[item.category] || []).push(item);
            }
            const orden = Object.keys(CATEGORIAS_KB);
            return Object.keys(grupos)
                .sort((a, b) => (orden.indexOf(a) + 1 || 99) - (orden.indexOf(b) + 1 || 99))
                .map(id => ({ id, titulo: CATEGORIAS_KB[id] || id, items: grupos[id] }));
        },
    },
    watch: {
        pestana(p) {
            if (p === 'conversaciones' && !this.conv.cargada) this.cargarConversaciones(1);
            if (p === 'leads' && !this.leads.cargada) this.cargarLeads(1);
            if (p === 'kb' && !this.kb.cargada) this.cargarKb();
        },
    },
    mounted() {
        this.cargarConfig();
        this.cargarEstado();
        if (this.pestana === 'conversaciones') this.cargarConversaciones(1);
        // El estado (horario) se recalcula solo, y el servicio se vuelve a revisar cada minuto.
        this.reloj = setInterval(() => {
            this.ahora = new Date();
            if (!document.hidden) this.cargarEstado(true);
        }, 60000);
        window.addEventListener('beforeunload', this.avisarSalida);
    },
    beforeUnmount() {
        clearInterval(this.reloj);
        clearTimeout(this.esperaBusqueda);
        window.removeEventListener('beforeunload', this.avisarSalida);
        this.pararAudio();
    },
    methods: {
        cabeceras() {
            return { headers: { 'X-CSRF-TOKEN': this.csrfToken } };
        },

        // Solo mensajes pensados para la persona; un 500 dice algo útil, no la traza.
        errorDe(e, porDefecto) {
            const r = e?.response;
            if (r && r.status < 500 && r.data) {
                if (r.data.message && !r.data.errors) return r.data.message;
                const primero = r.data.errors && Object.values(r.data.errors)[0];
                if (primero) return Array.isArray(primero) ? primero[0] : primero;
            }
            if (r?.status === 403) return 'No tienes permiso para esto.';
            if (!r) return 'No hay conexión con el servidor. Revisa la red e inténtalo de nuevo.';
            return porDefecto;
        },

        avisarSalida(ev) {
            if (this.sucio) {
                ev.preventDefault();
                ev.returnValue = '';
            }
        },

        // ── Configuración ────────────────────────────────────────────────
        async cargarConfig() {
            this.errorCarga = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/ia-bot/config`);
                this.aplicarConfig(data);
                this.cargado = true;
            } catch (e) {
                this.errorCarga = this.errorDe(e, 'No se pudo cargar la configuración de la asistente.');
            }
        },

        aplicarConfig(data) {
            const c = { ...VACIA };
            for (const k of Object.keys(VACIA)) {
                if (data.config[k] !== undefined && data.config[k] !== null) c[k] = data.config[k];
            }
            c.enabled = !!c.enabled;
            for (const k of ['velocidad', 'temperature', 'piloto_porcentaje', 'lineas', 'max_turns', 'max_duration_seconds', 'timeout_seconds']) {
                c[k] = Number(c[k]);
            }
            this.guardado = c;
            this.form = { ...c };
            this.catalogos = { ...this.catalogos, ...data.catalogos };
        },

        async guardar() {
            if (!this.sucio || this.guardando) return;
            this.guardando = true;
            this.errorGuardar = '';
            this.errores = {};
            const cuerpo = {};
            for (const c of CAMPOS) cuerpo[c] = this.form[c];
            try {
                const { data } = await axios.put(`${this.baseUrl}/ia-bot/config`, cuerpo, this.cabeceras());
                this.aplicarConfig(data);
                message('Cambios guardados. La siguiente llamada ya los usa.');
                this.cargarEstado(true);
            } catch (e) {
                if (e?.response?.status === 422 && e.response.data?.errors) {
                    this.errores = Object.fromEntries(Object.entries(e.response.data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
                    this.errorGuardar = 'Revisa los campos marcados.';
                } else {
                    this.errorGuardar = this.errorDe(e, 'No se pudo guardar. Inténtalo de nuevo.');
                }
            } finally {
                this.guardando = false;
            }
        },

        descartar() {
            this.form = { ...this.guardado };
            this.errores = {};
            this.errorGuardar = '';
        },

        async alternarEncendido() {
            const nuevo = !this.guardado.enabled;
            this.cambiandoInterruptor = true;
            try {
                const { data } = await axios.put(`${this.baseUrl}/ia-bot/config`, { enabled: nuevo }, this.cabeceras());
                // Solo el interruptor: lo que se esté editando se conserva.
                this.guardado = { ...this.guardado, enabled: !!data.config.enabled };
                this.form.enabled = this.guardado.enabled;
                message(nuevo ? 'Encendida: contesta las llamadas de la cola.' : 'Apagada: las llamadas van directo a las personas.');
                this.cargarEstado(true);
            } catch (e) {
                message(this.errorDe(e, 'No se pudo cambiar. Inténtalo de nuevo.'), 'error');
            } finally {
                this.cambiandoInterruptor = false;
            }
        },

        async cargarEstado(callado = false) {
            if (this.cargandoEstado) return;
            this.cargandoEstado = !callado;
            try {
                const { data } = await axios.get(`${this.baseUrl}/ia-bot/estado`);
                this.estado = data;
            } catch (e) {
                if (!callado) message(this.errorDe(e, 'No se pudo revisar el estado.'), 'error');
            } finally {
                this.cargandoEstado = false;
            }
        },

        // ── Escuchar el saludo ───────────────────────────────────────────
        iconoEscucha(para) {
            if (this.escucha.cargando === para) return 'fa fa-spinner fa-spin';
            return this.escucha.sonando === para ? 'fa fa-stop' : 'fa fa-play';
        },
        textoEscucha(para) {
            if (this.escucha.cargando === para) return 'Generando…';
            return this.escucha.sonando === para ? 'Detener' : 'Escuchar';
        },
        cambiosDeVoz() {
            const f = this.form;
            return { voice: f.voice, tono: f.tono, velocidad: f.velocidad, greeting_customer: f.greeting_customer, greeting_lead: f.greeting_lead };
        },
        async escucharSaludo(para) {
            if (this.escucha.sonando === para) {
                this.pararAudio();
                return;
            }
            this.pararAudio();
            this.escucha.error = '';
            this.escucha.cargando = para;
            try {
                const { data } = await axios.post(`${this.baseUrl}/ia-bot/escuchar`, { para, config: this.cambiosDeVoz() },
                    { ...this.cabeceras(), responseType: 'blob' });
                this.tocar(URL.createObjectURL(data), para);
            } catch (e) {
                let texto = 'No se pudo generar la voz.';
                try {
                    const cuerpo = JSON.parse(await e?.response?.data?.text?.());
                    texto = cuerpo.message || texto;
                } catch (_) { /* respuesta sin JSON */ }
                this.escucha.error = texto;
            } finally {
                this.escucha.cargando = null;
            }
        },
        tocar(url, para) {
            this.audio = new Audio(url);
            this.audioUrl = url;
            this.escucha.sonando = para;
            this.audio.addEventListener('ended', () => this.pararAudio());
            this.audio.play().catch(() => this.pararAudio());
        },
        pararAudio() {
            if (this.audio) {
                this.audio.pause();
                this.audio = null;
            }
            if (this.audioUrl) {
                URL.revokeObjectURL(this.audioUrl);
                this.audioUrl = null;
            }
            this.escucha.sonando = null;
        },

        // ── Probar ───────────────────────────────────────────────────────
        reiniciarPrueba() {
            this.prueba = { ...this.prueba, texto: '', turnos: [], historial: [], pensando: false, error: '', cliente: null, terminada: false };
            this.$nextTick(() => this.$refs.pruebaTexto?.focus());
        },
        async enviarPrueba() {
            const texto = this.prueba.texto.trim();
            if (!texto || this.prueba.pensando) return;
            this.prueba.error = '';
            this.prueba.texto = '';
            this.prueba.turnos.push({ quien: 'cliente', texto });
            this.prueba.pensando = true;
            this.bajarChat();
            const f = this.form;
            try {
                const { data } = await axios.post(`${this.baseUrl}/ia-bot/probar`, {
                    texto,
                    historial: this.prueba.historial,
                    numero: this.prueba.numero.trim() || null,
                    con_voz: this.prueba.conVoz,
                    config: {
                        system_prompt: f.system_prompt, temperature: f.temperature, voice: f.voice, tono: f.tono, velocidad: f.velocidad,
                        greeting_customer: f.greeting_customer, greeting_lead: f.greeting_lead,
                    },
                }, this.cabeceras());

                if (data.saludo) {
                    this.prueba.turnos.splice(this.prueba.turnos.length - 1, 0, { quien: 'asistente', texto: data.saludo });
                    this.prueba.historial.push({ rol: 'assistant', contenido: data.saludo });
                }
                if (data.cliente) this.prueba.cliente = data.cliente;
                this.prueba.historial.push({ rol: 'user', contenido: texto }, { rol: 'assistant', contenido: data.respuesta });

                const audio = data.audio ? `data:audio/mpeg;base64,${data.audio}` : null;
                this.prueba.turnos.push({
                    quien: 'asistente', texto: data.respuesta, transferir: data.transferir, audio,
                    meta: `${(data.ms / 1000).toFixed(1)} s · ${this.dinero(data.costo_usd)}`,
                });
                if (data.error) this.prueba.error = `La IA respondió con un error: ${data.error}`;
                else if (data.error_voz) this.prueba.error = `La respuesta salió, pero la voz falló: ${data.error_voz}`;
                if (data.transferir) this.prueba.terminada = true;
                if (audio) this.$nextTick(() => this.tocarUltimo());
            } catch (e) {
                this.prueba.turnos.pop();
                this.prueba.texto = texto;
                this.prueba.error = this.errorDe(e, 'No se pudo probar. Inténtalo de nuevo.');
            } finally {
                this.prueba.pensando = false;
                this.bajarChat();
                if (!this.prueba.terminada) this.$nextTick(() => this.$refs.pruebaTexto?.focus());
            }
        },
        tocarUltimo() {
            const audios = this.$refs.chat?.querySelectorAll('audio');
            const ultimo = audios && audios[audios.length - 1];
            if (ultimo) ultimo.play().catch(() => {});
        },
        bajarChat() {
            this.$nextTick(() => {
                const c = this.$refs.chat;
                if (c) c.scrollTop = c.scrollHeight;
            });
        },
        usarIdea(idea) {
            this.prueba.texto = idea;
            this.$nextTick(() => this.$refs.pruebaTexto?.focus());
        },
        modeloDe(clave) {
            const f = this.estado?.funciones.find(x => x.clave === clave);
            return f?.ok ? f.modelo : 'Sin IA';
        },
        textoSaldo(saldo) {
            const s = Number(saldo) || 0;
            if (s < -0.009) return `debe ${this.pesos(-s)}`;
            if (s > 0.009) return `${this.pesos(s)} a favor`;
            return 'al corriente';
        },

        // ── Conversaciones ───────────────────────────────────────────────
        async cargarConversaciones(pagina) {
            this.conv.cargando = true;
            this.conv.error = '';
            try {
                const params = { page: pagina, buscar: this.conv.buscar || undefined, type: this.conv.tipo || undefined, status: this.conv.estado || undefined, categoria: this.conv.categoria || undefined };
                const { data } = await axios.get(`${this.baseUrl}/ia-bot/conversaciones`, { params });
                this.conv.filas = Object.freeze(data.data);
                this.conv.total = data.total;
                this.conv.pagina = data.current_page;
                this.conv.ultima = data.last_page;
            } catch (e) {
                this.conv.error = this.errorDe(e, 'No se pudieron cargar las conversaciones.');
            } finally {
                this.conv.cargando = false;
                this.conv.cargada = true;
            }
        },
        buscarConversacionesLuego() {
            clearTimeout(this.esperaBusqueda);
            this.esperaBusqueda = setTimeout(() => this.cargarConversaciones(1), 350);
        },
        limpiarFiltrosConv() {
            Object.assign(this.conv, { buscar: '', tipo: '', estado: '', categoria: '' });
            this.cargarConversaciones(1);
        },
        verConversacion(c) {
            this.convAbierta = c;
        },
        resultado(c) {
            if (c.estado === 'transferred') return { texto: 'Pasó a una persona', clase: 'ia-insignia--info' };
            if (c.estado === 'failed') return { texto: 'Falló', clase: 'ia-insignia--mal' };
            if (c.estado === 'timeout') return { texto: 'Sin respuesta', clase: 'ia-insignia--aviso' };
            if (c.resuelta) return { texto: 'Resuelta', clase: 'ia-insignia--ok' };
            return { texto: 'Colgó', clase: '' };
        },

        // ── Leads ────────────────────────────────────────────────────────
        async cargarLeads(pagina) {
            this.leads.cargando = true;
            this.leads.error = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/ia-bot/leads`, { params: { page: pagina, status: this.leads.estado || undefined } });
                this.leads.filas = data.data;
                this.leads.total = data.total;
                this.leads.pagina = data.current_page;
                this.leads.ultima = data.last_page;
            } catch (e) {
                this.leads.error = this.errorDe(e, 'No se pudieron cargar los leads.');
            } finally {
                this.leads.cargando = false;
                this.leads.cargada = true;
            }
        },
        filtrarLeads(estado) {
            this.leads.estado = estado;
            this.cargarLeads(1);
        },
        interes(l) {
            return [l.interest_service, l.interest_plan].filter(Boolean).join(' · ');
        },
        async guardarEstadoLead(l) {
            try {
                await axios.put(`${this.baseUrl}/ia-bot/leads/${l.id}`, { status: l.status, notes: l.notes }, this.cabeceras());
                message('Lead actualizado.');
                this.cargarEstado(true);
            } catch (e) {
                message(this.errorDe(e, 'No se pudo actualizar el lead.'), 'error');
                this.cargarLeads(this.leads.pagina);
            }
        },
        abrirLead(l) {
            this.leadAbierto = { ...l, error: '' };
        },
        async guardarLead() {
            const l = this.leadAbierto;
            this.guardandoLead = true;
            l.error = '';
            try {
                await axios.put(`${this.baseUrl}/ia-bot/leads/${l.id}`, { status: l.status, notes: l.notes }, this.cabeceras());
                message('Lead guardado.');
                this.leadAbierto = null;
                this.cargarLeads(this.leads.pagina);
                this.cargarEstado(true);
            } catch (e) {
                l.error = this.errorDe(e, 'No se pudo guardar. Inténtalo de nuevo.');
            } finally {
                this.guardandoLead = false;
            }
        },

        // ── Base de conocimiento ─────────────────────────────────────────
        async cargarKb() {
            this.kb.cargando = true;
            this.kb.error = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/ia-bot/kb`);
                this.kb.filas = data.data;
            } catch (e) {
                this.kb.error = this.errorDe(e, 'No se pudo cargar la base de conocimiento.');
            } finally {
                this.kb.cargando = false;
                this.kb.cargada = true;
            }
        },
        // Los pasos viejos se guardaban como {step1: …}; los nuevos, como lista.
        pasos(item) {
            const p = item.solution_steps;
            return (Array.isArray(p) ? p : Object.values(p || {})).filter(x => String(x || '').trim() !== '');
        },
        abrirKb(item = null) {
            this.erroresKb = {};
            const pasos = item ? this.pasos(item) : [''];
            this.kbForm = {
                id: item?.id || null,
                category: item?.category || 'internet',
                problem: item?.problem || '',
                keywords: item?.keywords || '',
                escalate_if_fails: item ? !!item.escalate_if_fails : true,
                pasos: (pasos.length ? pasos : ['']).map(texto => ({ id: ++idPaso, texto })),
            };
        },
        agregarPaso() {
            this.kbForm.pasos.push({ id: ++idPaso, texto: '' });
            this.$nextTick(() => document.getElementById(`ia-paso-${idPaso}`)?.focus());
        },
        quitarPaso(i) {
            this.kbForm.pasos.splice(i, 1);
        },
        moverPaso(i, d) {
            const p = this.kbForm.pasos;
            [p[i + d], p[i]] = [p[i], p[i + d]];
        },
        async guardarKb() {
            const f = this.kbForm;
            this.erroresKb = {};
            if (!f.problem.trim()) this.erroresKb.problem = 'Escribe cuál es el problema.';
            if (!f.pasos.some(p => p.texto.trim())) this.erroresKb.solution_steps = 'Escribe al menos un paso.';
            if (Object.keys(this.erroresKb).length) return;

            this.guardandoKb = true;
            const cuerpo = {
                category: f.category, problem: f.problem.trim(), keywords: f.keywords.trim() || null,
                escalate_if_fails: f.escalate_if_fails, solution_steps: f.pasos.map(p => p.texto.trim()).filter(Boolean),
            };
            try {
                if (f.id) await axios.put(`${this.baseUrl}/ia-bot/kb/${f.id}`, cuerpo, this.cabeceras());
                else await axios.post(`${this.baseUrl}/ia-bot/kb`, cuerpo, this.cabeceras());
                message(f.id ? 'Problema actualizado.' : 'Problema agregado. La siguiente llamada ya lo sabe.');
                this.kbForm = null;
                this.cargarKb();
            } catch (e) {
                const errores = e?.response?.status === 422 ? e.response.data?.errors : null;
                if (errores) {
                    this.erroresKb = Object.fromEntries(Object.entries(errores).map(([k, v]) => [k.startsWith('solution_steps') ? 'solution_steps' : k, Array.isArray(v) ? v[0] : v]));
                } else {
                    this.erroresKb = { _general: this.errorDe(e, 'No se pudo guardar. Inténtalo de nuevo.') };
                }
            } finally {
                this.guardandoKb = false;
            }
        },
        async borrarKb() {
            this.borrandoKb = true;
            try {
                await axios.delete(`${this.baseUrl}/ia-bot/kb/${this.kbBorrar.id}`, this.cabeceras());
                message('Problema quitado.');
                this.kbBorrar = null;
                this.cargarKb();
            } catch (e) {
                message(this.errorDe(e, 'No se pudo quitar.'), 'error');
            } finally {
                this.borrandoKb = false;
            }
        },

        // ── Pestañas ─────────────────────────────────────────────────────
        irA(id) {
            this.pestana = id;
        },
        teclaPestana(ev) {
            const ids = this.pestanas.map(p => p.id);
            const i = ids.indexOf(this.pestana);
            const destino = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: ids.length - 1 }[ev.key];
            if (destino === undefined) return;
            ev.preventDefault();
            const j = (destino + ids.length) % ids.length;
            this.pestana = ids[j];
            this.$nextTick(() => this.$refs.pestanas?.[j]?.focus());
        },

        // ── Formato ──────────────────────────────────────────────────────
        n(v) {
            return new Intl.NumberFormat('es-MX').format(Number(v) || 0);
        },
        pesos(v) {
            const entero = Math.abs(v - Math.round(v)) < 0.005;
            return `$${new Intl.NumberFormat('es-MX', { minimumFractionDigits: entero ? 0 : 2, maximumFractionDigits: 2 }).format(v)}`;
        },
        // Lo de una llamada son centavos de dólar: con menos decimales todo saldría «0.00».
        dinero(usd) {
            const v = Number(usd) || 0;
            const decimales = v === 0 || v >= 1 ? 2 : (v >= 0.01 ? 3 : 4);
            return `US$ ${v.toFixed(decimales)}`;
        },
        duracion(s) {
            const t = Number(s) || 0;
            if (!t) return '—';
            const m = Math.floor(t / 60);
            const r = t % 60;
            return m ? `${m} min${r ? ` ${r} s` : ''}` : `${r} s`;
        },
        fecha(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            const hoy = new Date();
            const ayer = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 1);
            const hora = d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            if (d.toDateString() === hoy.toDateString()) return `Hoy ${hora}`;
            if (d.toDateString() === ayer.toDateString()) return `Ayer ${hora}`;
            const dia = d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', ...(d.getFullYear() !== hoy.getFullYear() ? { year: 'numeric' } : {}) });
            return `${dia} ${hora}`;
        },
        fechaLarga(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style>
/* Tokens de estado de la asistente: en .cp-raiz para que también lleguen a sus diálogos (van en <body>). */
.cp-raiz { --ia-ok: #15803d; --ia-ok-suave: #ecfdf3; --ia-aviso: #b54708; --ia-aviso-suave: #fffaeb; --ia-mal-suave: #fef3f2; }
.cp-raiz.cd-dark { --ia-ok: #4ade80; --ia-ok-suave: rgba(34, 197, 94, .12); --ia-aviso: #fdb022; --ia-aviso-suave: rgba(245, 158, 11, .12); --ia-mal-suave: rgba(239, 68, 68, .12); }
</style>

<style scoped>
/* La asistente comparte el mundo visual de Campañas (tokens y campos de .cp-raiz, diálogo accesible). */
.ia { font-size: 13.5px; container: ia / inline-size; padding-bottom: 8px; }
.ia-sr { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
.ia-fuerte { font-weight: 600; color: var(--cp-tinta); }
.ia-secundario { display: block; margin-top: 2px; font-size: 12.5px; color: var(--cp-tenue); }
.ia-dos-lineas { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 52ch; }
.ia-nowrap { white-space: nowrap; }
.ia-enlace { border: 0; background: none; padding: 0; color: var(--cp-acento); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }

/* ── Cabecera ─────────────────────────────────────────────────────────────── */
.ia-cabecera { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.ia-titulo { margin: 0; font-size: 1.375rem; font-weight: 600; letter-spacing: -.01em; color: var(--cp-tinta); }
.ia-sub { margin: 4px 0 0; font-size: 13.5px; color: var(--cp-tenue); }

.ia-interruptor {
    display: inline-flex; align-items: center; gap: 10px; min-height: 38px; padding: 6px 14px 6px 8px;
    border: 1px solid var(--cp-borde-campo); border-radius: 999px; background: var(--cp-superficie); color: var(--cp-tinta);
    font-size: 13.5px; font-weight: 600; cursor: pointer; transition: border-color .15s, background .15s;
}
.ia-interruptor:hover:not(:disabled) { border-color: var(--cp-acento); }
.ia-interruptor:disabled { opacity: .6; cursor: default; }
.ia-interruptor-pista { position: relative; width: 36px; height: 20px; border-radius: 999px; background: var(--cp-barra); transition: background .2s; }
.ia-interruptor-bola {
    position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 2px rgba(16, 24, 40, .25); transition: transform .2s cubic-bezier(.16, 1, .3, 1);
}
.ia-interruptor[aria-checked="true"] .ia-interruptor-pista { background: var(--ia-ok); }
.ia-interruptor[aria-checked="true"] .ia-interruptor-bola { transform: translateX(16px); }

/* ── Estado ───────────────────────────────────────────────────────────────── */
.cd-dark .ia-interruptor[aria-checked="true"] .ia-interruptor-pista { background: #16a34a; }

.ia-estado {
    margin-bottom: 20px; background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 12px;
    box-shadow: var(--cp-sombra); overflow: hidden;
}
.ia-estado-cabeza { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; border-bottom: 1px solid var(--cp-borde); }
.ia-estado-icono {
    flex: none; width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
    font-size: 15px; background: var(--cp-cabecera); color: var(--cp-tenue);
}
.ia-tono--ok .ia-estado-icono { background: var(--ia-ok-suave); color: var(--ia-ok); }
.ia-tono--aviso .ia-estado-icono { background: var(--ia-aviso-suave); color: var(--ia-aviso); }
.ia-tono--mal .ia-estado-icono { background: var(--ia-mal-suave); color: var(--cp-alerta); }
.ia-estado-texto { flex: 1; min-width: 0; }
.ia-estado-titulo { margin: 0; font-size: 15px; font-weight: 600; line-height: 1.35; color: var(--cp-tinta); text-wrap: balance; }
.ia-tono--mal .ia-estado-titulo { color: var(--cp-alerta); }
.ia-estado-detalle { margin: 3px 0 0; color: var(--cp-tenue); max-width: 80ch; }
.ia-estado-detalle:empty { display: none; }

.ia-requisitos { display: flex; flex-wrap: wrap; gap: 6px 22px; margin: 0; padding: 12px 18px; list-style: none; }
.ia-req { display: inline-flex; align-items: baseline; gap: 6px; min-width: 0; }
.ia-req i { color: var(--ia-ok); font-size: 12.5px; }
.ia-req--mal i, .ia-req--mal .ia-req-valor { color: var(--cp-alerta); }
.ia-req-nombre { font-weight: 600; color: var(--cp-tinta); }
.ia-req-valor { color: var(--cp-tenue); overflow-wrap: anywhere; }
.ia-req--esqueleto { width: 150px; }

.ia-problemas { margin: 0; padding: 0 18px 12px 40px; color: var(--cp-tinta); }
.ia-problemas li { margin-top: 2px; line-height: 1.5; }
.ia-problemas .ia-enlace { margin-left: 4px; }

.ia-hoy { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; margin: 0; background: var(--cp-borde); border-top: 1px solid var(--cp-borde); }
.ia-hoy > div { padding: 10px 18px; background: var(--cp-cabecera); min-width: 0; }
.ia-hoy dt { font-size: 12.5px; font-weight: 400; color: var(--cp-tenue); }
.ia-hoy dd { margin: 2px 0 0; font-size: 1.125rem; font-weight: 600; color: var(--cp-tinta); }

/* ── Avisos ───────────────────────────────────────────────────────────────── */
.ia-aviso {
    display: flex; gap: 8px; align-items: flex-start; margin: 0 0 16px; padding: 10px 14px; border-radius: 10px;
    border: 1px solid #fed7aa; background: #fff7ed; color: #9a3412; line-height: 1.45;
}
.ia-aviso i { margin-top: 3px; }
.ia-aviso--error { background: #fef2f2; color: #7f1d1d; border-color: #fecaca; }
.ia-aviso--chico { padding: 8px 12px; margin: 10px 0 0; }
.ia-aviso--en-panel { margin: 14px 18px 0; }
.cd-dark .ia-aviso { background: rgba(234, 88, 12, .12); color: #fdba74; border-color: rgba(234, 88, 12, .3); }
.cd-dark .ia-aviso--error { background: rgba(239, 68, 68, .12); color: #fca5a5; border-color: rgba(239, 68, 68, .3); }

/* ── Pestañas ─────────────────────────────────────────────────────────────── */
.ia-pestanas {
    display: flex; gap: 2px; margin-bottom: 16px; border-bottom: 1px solid var(--cp-borde);
    overflow-x: auto; scrollbar-width: none;
}
.ia-pestanas::-webkit-scrollbar { display: none; }
.ia-pestana {
    position: relative; display: inline-flex; align-items: center; gap: 7px; flex: none; padding: 10px 14px 11px; border: 0; background: none;
    color: var(--cp-tenue); font-size: 13.5px; font-weight: 500; white-space: nowrap; cursor: pointer; border-radius: 8px 8px 0 0;
}
.ia-pestana:hover { color: var(--cp-tinta); background: var(--cp-hover); }
.ia-pestana[aria-selected="true"] { color: var(--cp-tinta); font-weight: 600; }
.ia-pestana[aria-selected="true"]::after {
    content: ""; position: absolute; left: 10px; right: 10px; bottom: -1px; height: 2px; border-radius: 2px; background: var(--cp-acento);
}
.ia-pestana-n { min-width: 20px; padding: 1px 7px; border-radius: 999px; background: var(--cp-acento); color: var(--cp-acento-texto); font-size: 11.5px; font-weight: 700; }
[role="tabpanel"]:focus-visible { outline: none; }

/* ── Paneles y columnas ───────────────────────────────────────────────────── */
.ia-columnas { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
.ia-col { display: grid; gap: 16px; min-width: 0; }
@container ia (min-width: 980px) {
    .ia-columnas { grid-template-columns: minmax(0, 1.6fr) minmax(300px, 1fr); }
}
.ia-panel { background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 12px; box-shadow: var(--cp-sombra); min-width: 0; }
.ia-panel--esqueleto { height: 320px; background-image: linear-gradient(90deg, transparent, var(--cp-hover), transparent); background-size: 200% 100%; animation: ia-brillo 1.4s ease-in-out infinite; }
.ia-panel-cabeza { padding: 16px 18px 0; }
.ia-panel-titulo { margin: 0; font-size: 15px; font-weight: 600; color: var(--cp-tinta); }
.ia-panel-desc { margin: 3px 0 0; color: var(--cp-tenue); line-height: 1.45; max-width: 75ch; }
.ia-panel-cuerpo { padding: 14px 18px 18px; }
.ia-etiqueta-fila { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
.ia-valor { font-weight: 600; color: var(--cp-acento); font-size: 13px; }
.ia-prompt { min-height: 280px; line-height: 1.55; }
.ia-saludo { min-height: 64px; }
.ia-contador { float: right; margin-left: 12px; }
.ia-nota { margin: 12px 0 0; }
.ia-numero { max-width: 120px; }
.ia-error-par { margin: -8px 0 14px; }

.ia-detalles { margin-top: 4px; border: 1px solid var(--cp-borde); border-radius: 10px; background: var(--cp-cabecera); }
.ia-detalles summary { padding: 9px 12px; font-weight: 600; color: var(--cp-tinta); cursor: pointer; border-radius: 10px; }
.ia-detalles summary:hover { color: var(--cp-acento); }
.ia-detalles ul { margin: 0; padding: 0 14px 12px 30px; color: var(--cp-tenue); line-height: 1.5; }
.ia-detalles li + li { margin-top: 4px; }
.ia-detalles strong { color: var(--cp-tinta); font-weight: 600; }

/* Rango con el acento del sistema (el del navegador queda gris en oscuro) */
.ia-rango { width: 100%; margin: 4px 0 0; accent-color: var(--cp-acento); cursor: pointer; }
.ia-rango-extremos { display: flex; justify-content: space-between; font-size: 12px; color: var(--cp-tenue); margin-top: 2px; }

/* ── Botones ──────────────────────────────────────────────────────────────── */
.ia-boton {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 34px;
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta);
    border-radius: 8px; padding: 6px 13px; font-size: 13px; font-weight: 500; line-height: 1.2;
    cursor: pointer; text-decoration: none; white-space: nowrap; transition: background .15s, border-color .15s, color .15s;
}
.ia-boton i { font-size: 12px; }
.ia-boton:hover:not(:disabled) { border-color: var(--cp-acento); color: var(--cp-acento); }
.ia-boton:disabled { opacity: .5; cursor: default; }
.ia-boton--primario { background: var(--cp-acento); border-color: var(--cp-acento); color: var(--cp-acento-texto); font-weight: 600; }
.ia-boton--primario:hover:not(:disabled) { filter: brightness(1.08); color: var(--cp-acento-texto); }
.ia-boton--peligro { background: var(--cp-alerta); border-color: var(--cp-alerta); color: #fff; font-weight: 600; }
.ia-boton--peligro:hover:not(:disabled) { filter: brightness(1.08); color: #fff; }
.cd-dark .ia-boton--peligro { color: #1f0a08; }
.ia-boton--chico { min-height: 30px; padding: 4px 11px; font-size: 12.5px; }
.ia-icono {
    width: 34px; height: 34px; flex: none; border-radius: 8px; border: 1px solid transparent; background: transparent;
    color: var(--cp-tenue); display: inline-flex; align-items: center; justify-content: center; font-size: 12.5px; cursor: pointer;
    transition: background .15s, color .15s;
}
.ia-icono:hover:not(:disabled) { background: var(--cp-acento-suave); color: var(--cp-acento); }
.ia-icono:disabled { opacity: .35; cursor: default; }
.ia-icono--peligro:hover:not(:disabled) { background: var(--ia-mal-suave); color: var(--cp-alerta); }

.ia-check { display: inline-flex; align-items: flex-start; gap: 8px; cursor: pointer; color: var(--cp-tinta); line-height: 1.4; }
.ia-check input { width: 16px; height: 16px; margin-top: 1px; accent-color: var(--cp-acento); flex: none; }

/* ── Barra de guardar ─────────────────────────────────────────────────────── */
.ia-barra {
    position: sticky; bottom: 12px; z-index: 5; display: flex; align-items: center; gap: 8px; margin-top: 16px; padding: 10px 12px 10px 16px;
    background: var(--cp-superficie); border: 1px solid var(--cp-borde); border-radius: 12px;
    box-shadow: 0 8px 24px rgba(16, 24, 40, .12), 0 1px 3px rgba(16, 24, 40, .08);
    opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .18s ease-out, transform .22s cubic-bezier(.16, 1, .3, 1);
}
.cd-dark .ia-barra { box-shadow: 0 8px 24px rgba(0, 0, 0, .45); }
.ia-barra--visible { opacity: 1; transform: none; pointer-events: auto; }
.ia-barra-texto { flex: 1; margin: 0; color: var(--cp-tinta); font-weight: 500; }
.ia-barra-texto i { color: var(--cp-alerta); }

/* ── Probar ───────────────────────────────────────────────────────────────── */
.ia-probar { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
@container ia (min-width: 980px) {
    .ia-probar { grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr); }
}
.ia-chat { display: flex; flex-direction: column; min-width: 0; }
.ia-ideas-lista { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 14px 18px 4px; list-style: none; }
.ia-idea {
    border: 1px solid var(--cp-borde-campo); background: var(--cp-superficie); color: var(--cp-tinta); border-radius: 999px;
    padding: 5px 12px; font-size: 13px; line-height: 1.35; text-align: left; cursor: pointer; transition: border-color .15s, color .15s, background .15s;
}
.ia-idea:hover:not(:disabled) { border-color: var(--cp-acento); color: var(--cp-acento); background: var(--cp-acento-suave); }
.ia-idea:disabled { opacity: .5; cursor: default; }
.ia-ideas-usa { display: grid; gap: 8px; margin: 12px 18px 0; padding: 12px 0 0; border-top: 1px solid var(--cp-borde); }
.ia-ideas-usa > div { display: flex; justify-content: space-between; gap: 12px; }
.ia-ideas-usa dt { font-weight: 400; color: var(--cp-tenue); }
.ia-ideas-usa dd { margin: 0; color: var(--cp-tinta); font-weight: 600; text-align: right; overflow-wrap: anywhere; }
.ia-ideas-nota { margin: 12px 18px 18px; line-height: 1.45; }
.ia-chat-cabeza { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.ia-chat-opciones { display: flex; align-items: flex-end; gap: 12px 24px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--cp-borde); }
.ia-chat-numero { flex: 1 1 280px; max-width: 420px; margin: 0; }
.ia-chat-opciones .ia-check { margin-bottom: 26px; }
.ia-chat-turnos { display: flex; flex-direction: column; gap: 10px; min-height: 260px; max-height: 52vh; overflow-y: auto; margin: 0; padding: 16px 18px; list-style: none; background: var(--cp-cabecera); }
.ia-chat-turnos--lectura { max-height: none; min-height: 0; border-radius: 10px; padding: 12px; }
.ia-chat-vacio { margin: auto; max-width: 46ch; text-align: center; color: var(--cp-tenue); line-height: 1.5; }
.ia-turno { max-width: min(78%, 60ch); padding: 9px 12px; border-radius: 12px; background: var(--cp-superficie); border: 1px solid var(--cp-borde); }
.ia-turno--cliente { align-self: flex-end; background: var(--cp-acento-suave); border-color: transparent; border-bottom-right-radius: 4px; }
.ia-turno--asistente { align-self: flex-start; border-bottom-left-radius: 4px; }
.ia-turno-quien { display: block; font-size: 11.5px; font-weight: 600; color: var(--cp-tenue); margin-bottom: 2px; }
.ia-turno-texto { margin: 0; color: var(--cp-tinta); line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
.ia-turno-audio { display: block; width: 100%; max-width: 320px; height: 34px; margin-top: 8px; }
.ia-turno-meta { display: block; margin-top: 4px; font-size: 11.5px; color: var(--cp-tenue); }
.ia-turno .ia-insignia { margin-top: 6px; }
.ia-puntos { display: inline-flex; gap: 4px; padding: 4px 0; }
.ia-puntos span { width: 6px; height: 6px; border-radius: 50%; background: var(--cp-tenue); animation: ia-punto 1.2s ease-in-out infinite; }
.ia-puntos span:nth-child(2) { animation-delay: .15s; }
.ia-puntos span:nth-child(3) { animation-delay: .3s; }
.ia-chat-error { margin: 10px 18px 0; }
.ia-chat-entrada { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid var(--cp-borde); }
.ia-chat-entrada .cp-campo { flex: 1; min-width: 0; }

/* ── Filtros y tablas ─────────────────────────────────────────────────────── */
.ia-filtros { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--cp-borde); }
.ia-buscar { position: relative; flex: 1 1 220px; max-width: 320px; }
.ia-buscar i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--cp-tenue); font-size: 12.5px; pointer-events: none; }
.ia-buscar .cp-campo { padding-left: 32px; }
.ia-filtro { flex: 0 1 190px; width: auto; }
.ia-total { margin-left: auto; }
.ia-leads-desc { padding: 12px 18px 0; }

.ia-segmentos { display: inline-flex; padding: 3px; border-radius: 9px; background: var(--cp-cabecera); border: 1px solid var(--cp-borde); max-width: 100%; overflow-x: auto; scrollbar-width: none; }
.ia-segmento { border: 0; background: transparent; color: var(--cp-tenue); font-size: 13px; font-weight: 500; padding: 5px 11px; border-radius: 7px; white-space: nowrap; cursor: pointer; }
.ia-segmento:hover { color: var(--cp-tinta); }
.ia-segmento[aria-pressed="true"] { background: var(--cp-superficie); color: var(--cp-tinta); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.cd-dark .ia-segmento[aria-pressed="true"] { background: rgba(255, 255, 255, .12); box-shadow: none; }

.ia-tabla-caja { overflow-x: auto; transition: opacity .15s; }
.ia-cargando { opacity: .55; }
.ia-tabla { width: 100%; border-collapse: collapse; color: var(--cp-tinta); }
.ia-tabla thead th {
    background: var(--cp-cabecera); color: var(--cp-tenue); font-size: 12.5px; font-weight: 600; text-align: left;
    padding: 9px 12px; white-space: nowrap; border-bottom: 1px solid var(--cp-borde);
}
.ia-tabla tbody td { padding: 12px; border-bottom: 1px solid var(--cp-borde); vertical-align: middle; }
.ia-tabla tbody tr:last-child td { border-bottom: 0; }
.ia-tabla tbody tr:hover td { background: var(--cp-hover); }
.ia-tabla th:first-child, .ia-tabla td:first-child { padding-left: 18px; }
.ia-tabla th:last-child, .ia-tabla td:last-child { padding-right: 18px; }
.ia-col-num { text-align: right !important; white-space: nowrap; }
.ia-col-accion { text-align: right; width: 1%; white-space: nowrap; }
.ia-col-resumen { min-width: 220px; }
.ia-select-chico { min-height: 32px; padding-top: 5px; padding-bottom: 5px; width: 150px; }
.ia-vacio { padding: 40px 20px !important; text-align: center; color: var(--cp-tenue); }
.ia-tabla tbody tr:hover td.ia-vacio { background: none; }
.ia-vacio i { font-size: 22px; margin-bottom: 10px; opacity: .7; }
.ia-vacio p { margin: 0 auto; max-width: 52ch; line-height: 1.5; }
.ia-vacio--panel { padding: 40px 20px; }
.ia-paginas { display: flex; align-items: center; justify-content: flex-end; gap: 8px; padding: 10px 14px; border-top: 1px solid var(--cp-borde); color: var(--cp-tenue); }

.ia-insignia {
    display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600;
    white-space: nowrap; background: var(--cp-cabecera); color: var(--cp-tenue); border: 1px solid var(--cp-borde);
}
.ia-insignia--ok { background: var(--ia-ok-suave); color: var(--ia-ok); border-color: transparent; }
.ia-insignia--info { background: var(--cp-acento-suave); color: var(--cp-acento); border-color: transparent; }
.ia-insignia--aviso { background: var(--ia-aviso-suave); color: var(--ia-aviso); border-color: transparent; }
.ia-insignia--mal { background: var(--ia-mal-suave); color: var(--cp-alerta); border-color: transparent; }

/* ── Base de conocimiento ─────────────────────────────────────────────────── */
.ia-kb-cabeza { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.ia-kb-grupo { margin-bottom: 14px; }
.ia-kb-titulo { display: flex; align-items: baseline; gap: 8px; margin: 0; padding: 12px 18px; font-size: 14px; font-weight: 600; color: var(--cp-tinta); border-bottom: 1px solid var(--cp-borde); }
.ia-kb-lista { margin: 0; padding: 0; list-style: none; }
.ia-kb-item { display: flex; align-items: center; gap: 12px; padding: 11px 12px 11px 18px; }
.ia-kb-item + .ia-kb-item { border-top: 1px solid var(--cp-borde); }
.ia-kb-texto { flex: 1; min-width: 0; }
.ia-kb-acciones { display: flex; gap: 2px; flex: none; }

.ia-pasos { border: 0; padding: 0; margin: 0 0 14px; min-width: 0; }
.ia-pasos legend { float: none; width: auto; padding: 0; }
.ia-pasos-lista { margin: 0 0 8px; padding: 0; list-style: none; display: grid; gap: 6px; }
.ia-paso { display: flex; align-items: center; gap: 4px; }
.ia-paso .cp-campo { flex: 1; min-width: 0; }
.ia-paso-n { flex: none; width: 24px; height: 24px; margin-right: 4px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; background: var(--cp-acento-suave); color: var(--cp-acento); }

/* ── Diálogos ─────────────────────────────────────────────────────────────── */
.ia-ficha { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin: 0 0 16px; }
.ia-ficha dt { font-size: 12.5px; font-weight: 400; color: var(--cp-tenue); }
.ia-ficha dd { margin: 2px 0 0; color: var(--cp-tinta); overflow-wrap: anywhere; }
.ia-resumen { margin: 0 0 16px; padding: 10px 14px; border-radius: 10px; background: var(--cp-acento-suave); color: var(--cp-tinta); line-height: 1.5; }
.ia-subtitulo { margin: 0 0 8px; font-size: 13.5px; font-weight: 600; color: var(--cp-tinta); }
.ia-confirmar { margin: 0; color: var(--cp-tinta); line-height: 1.5; }

/* ── Carga ────────────────────────────────────────────────────────────────── */
.ia-esqueleto { display: block; height: 14px; width: 100%; border-radius: 6px; background: var(--cp-hover); background-image: linear-gradient(90deg, transparent, var(--cp-borde), transparent); background-size: 200% 100%; animation: ia-brillo 1.4s ease-in-out infinite; }
@keyframes ia-brillo { from { background-position: 100% 0; } to { background-position: -100% 0; } }
@keyframes ia-punto { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
@media (prefers-reduced-motion: reduce) {
    .ia-esqueleto, .ia-panel--esqueleto, .ia-puntos span { animation: none; }
    .ia-barra, .ia-interruptor-bola { transition: none; }
}

/* ── Angosto ──────────────────────────────────────────────────────────────── */
@container ia (max-width: 760px) {
    .ia-hoy { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ia-ocultable { display: none; }
    .ia-filtro { flex: 1 1 160px; }
    .ia-total { width: 100%; margin-left: 0; }
    .ia-turno { max-width: 92%; }
    .ia-ficha { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 767px) {
    .ia-boton, .ia-icono { min-height: 44px; }
    .ia-icono { width: 44px; height: 44px; }
    .ia-barra { bottom: 8px; flex-wrap: wrap; }
    .ia-barra-texto { width: 100%; flex: none; }
    .ia-barra .ia-boton { flex: 1; }
    .ia-chat-turnos { max-height: 60vh; }
}
</style>
