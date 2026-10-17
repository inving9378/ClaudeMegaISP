<template>
    <div class="pca q-pa-md">
        <div v-if="cargando" class="q-pa-xl text-center text-grey-7">
            <q-spinner size="32px" color="primary" />
            <div class="q-mt-sm">Cargando configuración del Portal Cliente…</div>
        </div>

        <q-banner v-else-if="errorCarga" class="bg-red-1 text-red-9" rounded>
            {{ errorCarga }}
            <template #action><q-btn flat color="red-9" label="Reintentar" @click="cargar" /></template>
        </q-banner>

        <template v-else>
            <!-- Encabezado fijo: estado y acciones de guardado -->
            <div class="pca-header">
                <div class="pca-header-texto">
                    <div class="text-h5 text-weight-bold">Portal Cliente</div>
                    <div class="text-caption text-grey-7">
                        Presentación de las pantallas de acceso (iniciar sesión, crear cuenta y recuperar contraseña).
                        Los cambios se guardan como borrador; los clientes solo los ven al publicarlos.
                    </div>
                </div>
                <div class="pca-header-estado">
                    <q-chip v-if="sucio" color="orange-2" text-color="orange-10" icon="edit" dense>Cambios sin guardar</q-chip>
                    <q-chip v-else-if="estado.borrador" color="blue-1" text-color="blue-9" icon="drafts" dense>
                        Borrador guardado · {{ estado.borrador.updated_at }}
                    </q-chip>
                    <q-chip v-else color="green-1" text-color="green-9" icon="check_circle" dense>Sin cambios pendientes</q-chip>
                    <q-chip color="grey-2" text-color="grey-8" icon="public" dense>
                        {{ estado.publicada.numero ? `Publicada: versión ${estado.publicada.numero}` : 'Publicada: diseño de fábrica' }}
                    </q-chip>
                </div>
                <div v-if="puedeEditar" class="pca-header-acciones">
                    <q-btn outline color="negative" icon="delete_sweep" label="Descartar borrador" no-caps
                           :disable="!estado.borrador && !sucio" @click="dialogo.descartar = true" />
                    <q-btn color="primary" icon="save" label="Guardar borrador" no-caps
                           :loading="guardando" :disable="!sucio" @click="guardar" />
                </div>
            </div>

            <q-banner v-if="!puedeEditar" class="bg-grey-2 text-grey-9 q-mb-md" rounded dense>
                <template #avatar><q-icon name="visibility" /></template>
                Tienes acceso de solo lectura: puedes revisar la configuración y la vista previa, pero no modificarla.
            </q-banner>
            <q-banner v-if="estado.borrador && estado.borrador.base_desactualizada" class="bg-orange-1 text-orange-10 q-mb-md" rounded dense>
                <template #avatar><q-icon name="warning" /></template>
                Se publicó una versión después de que se creó este borrador. Si lo publicas, reemplazará por completo lo publicado.
            </q-banner>
            <q-banner v-if="estado.borrador && estado.borrador.restaurado_de" class="bg-blue-1 text-blue-10 q-mb-md" rounded dense>
                <template #avatar><q-icon name="history" /></template>
                Este borrador parte de la versión {{ estado.borrador.restaurado_de }} restaurada. Revísalo y publícalo para aplicarlo.
            </q-banner>

            <q-card flat bordered>
                <q-tabs v-model="tab" dense no-caps align="left" active-color="primary" indicator-color="primary"
                        class="text-grey-8" outside-arrows mobile-arrows>
                    <q-tab name="apariencia" icon="wallpaper" label="Apariencia" />
                    <q-tab name="identidad" icon="badge" label="Identidad corporativa" />
                    <q-tab name="mensajes" icon="title" label="Mensajes" />
                    <q-tab name="tarjetas" icon="view_module" label="Tarjetas de servicios" />
                    <q-tab name="pantallas" icon="login" label="Pantallas de acceso" />
                    <q-tab name="publicacion" icon="visibility" label="Vista previa y publicación" />
                </q-tabs>
                <q-separator />

                <q-tab-panels v-model="tab" animated keep-alive>
                    <!-- ═══ 1. APARIENCIA ═══ -->
                    <q-tab-panel name="apariencia">
                        <div class="text-subtitle1 text-weight-bold">Imágenes de fondo</div>
                        <div class="text-caption text-grey-7 q-mb-md">
                            JPG, PNG o WebP de hasta {{ pesoMaximoMb }} MB. Se optimizan y convierten a WebP al subirlas.
                            Usa solo imágenes con derechos de uso. Sin fotografía, el portal usa el degradado azul.
                        </div>
                        <div class="pca-grid pca-grid--3">
                            <div v-for="slot in slotsFondo" :key="slot.clave">
                                <q-card flat bordered class="pca-slot">
                                    <div class="pca-slot-img" :class="{ 'pca-slot-img--vacia': !urlDe(form.fondo[slot.clave]) }">
                                        <img v-if="urlDe(form.fondo[slot.clave])" :src="urlDe(form.fondo[slot.clave])" :alt="slot.titulo">
                                        <div v-else class="text-grey-6 text-center">
                                            <q-icon name="image" size="32px" /><div class="text-caption">Sin imagen</div>
                                        </div>
                                    </div>
                                    <q-card-section class="q-pa-sm">
                                        <div class="text-weight-bold">{{ slot.titulo }}</div>
                                        <div class="text-caption text-grey-7">{{ slot.ayuda }}</div>
                                        <div v-if="metaDe(form.fondo[slot.clave])" class="text-caption text-grey-8 q-mt-xs">
                                            {{ metaDe(form.fondo[slot.clave]).ancho }} × {{ metaDe(form.fondo[slot.clave]).alto }} px ·
                                            {{ tamano(metaDe(form.fondo[slot.clave]).peso) }}
                                        </div>
                                    </q-card-section>
                                    <q-card-actions v-if="puedeEditar" class="q-pt-none">
                                        <q-btn flat dense no-caps color="primary" icon="upload" label="Subir"
                                               :loading="subiendo[slot.clave]" @click="elegirArchivo(slot.clave)" />
                                        <q-btn v-if="form.fondo[slot.clave]" flat dense no-caps color="negative" icon="close"
                                               label="Quitar" @click="form.fondo[slot.clave] = null" />
                                    </q-card-actions>
                                </q-card>
                            </div>
                        </div>

                        <div class="pca-grid pca-grid--2 pca-grid--amplia q-mt-md">
                            <div>
                                <div class="text-subtitle1 text-weight-bold q-mb-sm">Encuadre y tono</div>
                                <div v-for="ctrl in controlesFondo" :key="ctrl.clave" class="q-mb-sm">
                                    <div class="pca-fila text-body2">
                                        <span>{{ ctrl.etiqueta }}</span><span class="text-grey-7">{{ form.fondo[ctrl.clave] }}%</span>
                                    </div>
                                    <q-slider v-model="form.fondo[ctrl.clave]" :min="ctrl.min" :max="ctrl.max" :step="1"
                                              color="primary" :disable="!puedeEditar" />
                                    <div class="text-caption text-grey-6">{{ ctrl.ayuda }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="text-subtitle1 text-weight-bold q-mb-sm">Previsualización de recorte</div>
                                <div class="pca-grid pca-recortes">
                                    <div>
                                        <div class="pca-recorte pca-recorte--escritorio" :style="estiloRecorte('escritorio')">
                                            <div class="pca-recorte-velo" :style="estiloVelo"></div>
                                            <span class="pca-recorte-etq">Escritorio</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="pca-recorte pca-recorte--movil" :style="estiloRecorte('movil')">
                                            <div class="pca-recorte-velo" :style="estiloVeloMovil"></div>
                                            <span class="pca-recorte-etq">Móvil</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-caption text-grey-7 q-mt-sm">
                                    Aproximación del encuadre. La pantalla completa está en «Vista previa y publicación».
                                </div>
                            </div>
                        </div>
                    </q-tab-panel>

                    <!-- ═══ 2. IDENTIDAD CORPORATIVA ═══ -->
                    <q-tab-panel name="identidad">
                        <div class="pca-grid pca-grid--2 pca-grid--amplia">
                            <div>
                                <div class="text-subtitle1 text-weight-bold">Logotipo</div>
                                <q-option-group v-model="usoLogo" :options="opcionesLogo" color="primary" :disable="!puedeEditar" />
                                <div class="pca-grid pca-grid--par q-mt-sm">
                                    <div><div class="pca-logo pca-logo--oscuro"><img v-if="logoActual" :src="logoActual" alt="Logotipo"></div></div>
                                    <div><div class="pca-logo pca-logo--claro"><img v-if="logoActual" :src="logoActual" alt="Logotipo"></div></div>
                                </div>
                                <div v-if="usoLogo === 'propio' && puedeEditar" class="q-mt-sm">
                                    <q-btn outline dense no-caps color="primary" icon="upload" label="Subir logotipo del portal"
                                           :loading="subiendo.logo" @click="elegirArchivo('logo')" />
                                    <div class="text-caption text-grey-7 q-mt-xs">PNG o WebP con fondo transparente, mínimo 120 × 40 px.</div>
                                </div>
                                <div class="text-caption text-grey-7 q-mt-sm">
                                    El logotipo propio solo se usa en el Portal Cliente; el logo de «Información de la Empresa» no cambia.
                                </div>

                                <div class="text-subtitle1 text-weight-bold q-mt-lg">Textos</div>
                                <q-input v-model="form.identidad.titulo" label="Título del portal" outlined dense counter maxlength="60"
                                         :disable="!puedeEditar" class="q-mb-sm" />
                                <q-input v-model="form.identidad.subtitulo" label="Subtítulo" outlined dense counter maxlength="80"
                                         :disable="!puedeEditar" class="q-mb-sm" />
                                <div class="pca-fila pca-fila--centro">
                                    <div class="pca-crece">
                                        <q-input v-model="form.identidad.eslogan" label="Eslogan" outlined dense counter maxlength="60"
                                                 :disable="!puedeEditar" />
                                    </div>
                                    <div>
                                        <q-toggle v-model="form.identidad.mostrar_eslogan" label="Mostrar junto al logo" :disable="!puedeEditar" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="pca-fila">
                                    <div class="text-subtitle1 text-weight-bold">Colores corporativos</div>
                                    <q-btn v-if="puedeEditar" flat dense no-caps size="sm" icon="restart_alt" label="Restablecer"
                                           @click="restablecerColores" />
                                </div>
                                <div v-for="color in coloresIdentidad" :key="color.clave" class="pca-fila pca-fila--centro q-mb-xs">
                                    <div>
                                        <input type="color" class="pca-color" :value="form.identidad.colores[color.clave]"
                                               :disabled="!puedeEditar"
                                               @input="form.identidad.colores[color.clave] = $event.target.value.toUpperCase()">
                                    </div>
                                    <div class="pca-crece">
                                        <q-input v-model="form.identidad.colores[color.clave]" :label="color.etiqueta" outlined dense
                                                 maxlength="7" :disable="!puedeEditar"
                                                 :rules="[v => /^#[0-9A-Fa-f]{6}$/.test(v) || 'Formato #RRGGBB']" lazy-rules />
                                    </div>
                                </div>

                                <div class="text-subtitle1 text-weight-bold q-mt-md">Tarjetas de servicios</div>
                                <div v-for="ctrl in controlesTarjetas" :key="ctrl.clave" class="q-mb-xs">
                                    <div class="pca-fila text-body2">
                                        <span>{{ ctrl.etiqueta }}</span><span class="text-grey-7">{{ form.identidad.tarjetas[ctrl.clave] }}{{ ctrl.unidad }}</span>
                                    </div>
                                    <q-slider v-model="form.identidad.tarjetas[ctrl.clave]" :min="ctrl.min" :max="ctrl.max" :disable="!puedeEditar" />
                                </div>

                                <div class="text-subtitle1 text-weight-bold q-mt-md">Formulario de acceso</div>
                                <div v-for="ctrl in controlesFormulario" :key="ctrl.clave" class="q-mb-xs">
                                    <div class="pca-fila text-body2">
                                        <span>{{ ctrl.etiqueta }}</span><span class="text-grey-7">{{ form.identidad.formulario[ctrl.clave] }}{{ ctrl.unidad }}</span>
                                    </div>
                                    <q-slider v-model="form.identidad.formulario[ctrl.clave]" :min="ctrl.min" :max="ctrl.max" :disable="!puedeEditar" />
                                </div>
                            </div>
                        </div>
                    </q-tab-panel>

                    <!-- ═══ 3. MENSAJES ═══ -->
                    <q-tab-panel name="mensajes">
                        <div class="pca-grid pca-grid--2 pca-grid--amplia">
                            <div>
                                <q-toggle v-model="form.mensajes.mostrar_titulo" label="Mostrar título principal" :disable="!puedeEditar" />
                                <q-input v-model="form.mensajes.titulo" label="Título principal" outlined dense counter maxlength="90"
                                         :disable="!puedeEditar || !form.mensajes.mostrar_titulo" class="q-mb-sm" />
                                <q-input v-model="form.mensajes.destacado" label="Palabra destacada (en color)" outlined dense counter
                                         maxlength="40" :disable="!puedeEditar || !form.mensajes.mostrar_titulo"
                                         :hint="destacadoNoEncontrado ? 'No aparece dentro del título: no se resaltará nada.' : 'Debe aparecer tal cual dentro del título.'"
                                         :class="{ 'pca-aviso-campo': destacadoNoEncontrado }" class="q-mb-md" />

                                <q-toggle v-model="form.mensajes.mostrar_descripcion" label="Mostrar descripción" :disable="!puedeEditar" />
                                <q-input v-model="form.mensajes.descripcion" label="Descripción" type="textarea" autogrow outlined dense
                                         counter maxlength="240" :disable="!puedeEditar || !form.mensajes.mostrar_descripcion" class="q-mb-md" />

                                <div class="text-body2 q-mb-xs">Tamaño del título</div>
                                <q-btn-toggle v-model="form.mensajes.tamano" no-caps unelevated toggle-color="primary" :disable="!puedeEditar"
                                              :options="[{ label: 'Compacto', value: 'compacto' }, { label: 'Normal', value: 'normal' }, { label: 'Grande', value: 'grande' }]" />
                                <div class="text-body2 q-mt-md q-mb-xs">Alineación</div>
                                <q-btn-toggle v-model="form.mensajes.alineacion" no-caps unelevated toggle-color="primary" :disable="!puedeEditar"
                                              :options="[{ label: 'Izquierda', value: 'izquierda' }, { label: 'Centro', value: 'centro' }]" />
                                <div class="text-caption text-grey-7 q-mt-md">
                                    Los textos se muestran tal cual, como texto plano: no se interpreta HTML.
                                </div>
                            </div>
                            <div>
                                <div class="text-body2 text-grey-7 q-mb-xs">Así se verá</div>
                                <div class="pca-mini" :style="estiloMini">
                                    <div class="pca-mini-mensaje" :class="['pca-mini--' + form.mensajes.tamano, { 'pca-mini--centro': form.mensajes.alineacion === 'centro' }]">
                                        <div class="pca-mini-raya"></div>
                                        <div v-if="form.mensajes.mostrar_titulo" class="pca-mini-titulo">
                                            {{ partesTitulo[0] }}<span v-if="partesTitulo[1]" :style="{ color: form.identidad.colores.acento }">{{ partesTitulo[1] }}</span>{{ partesTitulo[2] }}
                                        </div>
                                        <div v-if="form.mensajes.mostrar_descripcion" class="pca-mini-desc">{{ form.mensajes.descripcion }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </q-tab-panel>

                    <!-- ═══ 4. TARJETAS DE SERVICIOS ═══ -->
                    <q-tab-panel name="tarjetas">
                        <div class="text-caption text-grey-7 q-mb-md">
                            Las tarjetas son informativas: no llevan a ninguna parte. Cambia el orden con las flechas;
                            una tarjeta desactivada no se muestra.
                        </div>
                        <q-card v-for="(tarjeta, i) in form.tarjetas" :key="tarjeta.clave" flat bordered class="q-mb-md"
                                :class="{ 'pca-tarjeta--inactiva': !tarjeta.activa }">
                            <q-card-section class="pca-grid pca-tarjeta">
                                <div class="pca-tarjeta-orden">
                                    <q-btn flat round dense icon="arrow_upward" :disable="!puedeEditar || i === 0"
                                           aria-label="Subir tarjeta" @click="mover(i, -1)" />
                                    <div class="text-caption text-grey-7">{{ i + 1 }}</div>
                                    <q-btn flat round dense icon="arrow_downward" :disable="!puedeEditar || i === form.tarjetas.length - 1"
                                           aria-label="Bajar tarjeta" @click="mover(i, 1)" />
                                </div>
                                <div>
                                    <div class="pca-grid pca-tarjeta-campos">
                                        <div>
                                            <q-input v-model="tarjeta.titulo" label="Título" outlined dense counter maxlength="40" :disable="!puedeEditar" />
                                        </div>
                                        <div class="pca-centrado">
                                            <q-toggle v-model="tarjeta.activa" :label="tarjeta.activa ? 'Visible' : 'Oculta'" :disable="!puedeEditar" />
                                        </div>
                                        <div class="pca-ancho-total">
                                            <q-input v-model="tarjeta.descripcion" label="Descripción" outlined dense counter maxlength="140" :disable="!puedeEditar" />
                                        </div>
                                        <div>
                                            <q-select v-model="tarjeta.icono" :options="catalogo.iconos" option-value="clave" option-label="etiqueta"
                                                      emit-value map-options outlined dense label="Ícono" :disable="!puedeEditar">
                                                <template #prepend><span class="pca-icono-sel" v-html="svgIcono(tarjeta.icono)"></span></template>
                                                <template #option="scope">
                                                    <q-item v-bind="scope.itemProps" class="pca-opcion">
                                                        <q-item-section avatar><span class="pca-icono-sel" v-html="scope.opt.svg"></span></q-item-section>
                                                        <q-item-section>{{ scope.opt.etiqueta }}</q-item-section>
                                                    </q-item>
                                                </template>
                                            </q-select>
                                        </div>
                                        <div>
                                            <div class="text-caption text-grey-7 q-mb-xs">Color</div>
                                            <div class="pca-fila pca-fila--envolver">
                                                <button v-for="color in catalogo.colores" :key="color.clave" type="button"
                                                        class="pca-swatch" :class="{ 'pca-swatch--activo': tarjeta.color === color.clave }"
                                                        :style="{ background: `linear-gradient(135deg, ${color.desde}, ${color.hasta})` }"
                                                        :title="color.etiqueta" :aria-label="color.etiqueta" :aria-pressed="tarjeta.color === color.clave"
                                                        :disabled="!puedeEditar" @click="tarjeta.color = color.clave"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-caption text-grey-7 q-mb-xs">Vista previa</div>
                                    <div class="pca-svc" :style="estiloSvc">
                                        <span class="pca-svc-icono" :style="{ background: gradienteColor(tarjeta.color) }" v-html="svgIcono(tarjeta.icono)"></span>
                                        <div>
                                            <div class="pca-svc-titulo">{{ tarjeta.titulo }}</div>
                                            <div class="pca-svc-desc">{{ tarjeta.descripcion }}</div>
                                        </div>
                                    </div>
                                </div>
                            </q-card-section>
                        </q-card>
                    </q-tab-panel>

                    <!-- ═══ 5. PANTALLAS DE ACCESO ═══ -->
                    <q-tab-panel name="pantallas">
                        <div class="text-caption text-grey-7 q-mb-md">
                            Las tres pantallas comparten la misma identidad visual. Aquí solo se ajusta su presentación:
                            los campos, validaciones y pasos de cada una no cambian.
                        </div>
                        <div class="pca-grid pca-grid--3">
                            <div v-for="p in pantallas" :key="p.clave">
                                <q-card flat bordered class="full-height">
                                    <q-card-section>
                                        <div class="text-subtitle1 text-weight-bold"><q-icon :name="p.icono" class="q-mr-xs" />{{ p.titulo }}</div>
                                        <template v-if="p.clave !== 'login'">
                                            <q-input v-model="form.pantallas[p.clave].titulo" label="Título del formulario" outlined dense
                                                     counter maxlength="60" class="q-mt-sm" :disable="!puedeEditar" />
                                            <q-input v-model="form.pantallas[p.clave].subtitulo" label="Subtítulo" outlined dense
                                                     counter maxlength="80" class="q-mt-sm" :disable="!puedeEditar" />
                                        </template>
                                        <div v-else class="text-caption text-grey-7 q-mt-sm">
                                            Usa el título y subtítulo de «Identidad corporativa».
                                        </div>
                                        <q-toggle v-model="form.pantallas[p.clave].mostrar_identidad"
                                                  label="Mostrar mensaje de agradecimiento" class="q-mt-sm" :disable="!puedeEditar" />
                                        <q-toggle v-model="form.pantallas[p.clave].mostrar_tarjetas"
                                                  label="Mostrar tarjetas de servicios" :disable="!puedeEditar" />
                                    </q-card-section>
                                    <q-card-actions>
                                        <q-btn flat dense no-caps color="primary" icon="visibility" label="Ver en vista previa"
                                               @click="verPantalla(p.clave)" />
                                    </q-card-actions>
                                </q-card>
                            </div>
                        </div>
                    </q-tab-panel>

                    <!-- ═══ 6. VISTA PREVIA Y PUBLICACIÓN ═══ -->
                    <q-tab-panel name="publicacion">
                        <q-banner v-if="sucio" class="bg-orange-1 text-orange-10 q-mb-md" rounded dense>
                            <template #avatar><q-icon name="edit" /></template>
                            Tienes cambios sin guardar. La vista previa muestra el último borrador guardado.
                            <template #action>
                                <q-btn v-if="puedeEditar" flat color="orange-10" label="Guardar borrador" :loading="guardando" @click="guardar" />
                            </template>
                        </q-banner>

                        <div class="pca-previa-controles">
                            <q-btn-toggle v-model="previa.pantalla" no-caps unelevated toggle-color="primary"
                                          :options="pantallas.map(p => ({ label: p.titulo, value: p.clave }))" />
                            <q-btn-toggle v-model="previa.dispositivo" no-caps unelevated toggle-color="primary"
                                          :options="[{ label: 'Escritorio', value: 'escritorio', icon: 'desktop_windows' }, { label: 'Tablet', value: 'tablet', icon: 'tablet_mac' }, { label: 'Móvil', value: 'movil', icon: 'smartphone' }]" />
                            <q-btn-toggle v-model="previa.fuente" no-caps unelevated toggle-color="dark"
                                          :options="opcionesFuente" @update:model-value="previa.version = previa.fuente === 'version' ? previa.version : null" />
                            <q-btn flat dense no-caps icon="refresh" label="Recargar" @click="previa.sello = Date.now()" />
                            <q-btn flat dense no-caps icon="open_in_new" label="Abrir en pestaña" :href="urlPrevia" target="_blank" />
                        </div>
                        <div class="text-caption text-grey-7 q-mb-sm">
                            {{ dispositivoActual.etiqueta }} · {{ dispositivoActual.w }} × {{ dispositivoActual.h }} px ·
                            {{ descripcionFuente }}
                        </div>
                        <div ref="contenedorPrevia" class="pca-previa">
                            <div class="pca-previa-marco" :style="{ width: dispositivoActual.w * escala + 'px', height: dispositivoActual.h * escala + 'px' }">
                                <iframe :key="urlPrevia" :src="urlPrevia" title="Vista previa del Portal Cliente"
                                        :style="{ width: dispositivoActual.w + 'px', height: dispositivoActual.h + 'px', transform: `scale(${escala})` }"></iframe>
                            </div>
                        </div>

                        <div class="pca-grid pca-grid--5-7 q-mt-md">
                            <div>
                                <q-card flat bordered>
                                    <q-card-section>
                                        <div class="text-subtitle1 text-weight-bold">Publicar</div>
                                        <div v-if="!estado.borrador" class="text-body2 text-grey-7 q-mt-sm">
                                            No hay borrador. Haz cambios en las pestañas y guárdalos para poder publicarlos.
                                        </div>
                                        <template v-else>
                                            <div class="text-body2 q-mt-sm">Cambios del borrador frente a lo publicado:</div>
                                            <ul v-if="estado.cambios_pendientes.length" class="pca-cambios">
                                                <li v-for="c in estado.cambios_pendientes" :key="c">{{ c }}</li>
                                            </ul>
                                            <div v-else class="text-caption text-grey-7 q-mt-xs">El borrador es igual a lo publicado.</div>
                                            <q-input v-model="nota" label="Nota de la publicación (opcional)" outlined dense maxlength="255"
                                                     counter class="q-mt-sm" :disable="!puedePublicar" />
                                        </template>
                                    </q-card-section>
                                    <q-card-actions v-if="estado.borrador">
                                        <q-btn color="positive" icon="publish" label="Publicar cambios" no-caps
                                               :disable="!puedePublicar || sucio || !estado.cambios_pendientes.length"
                                               :loading="publicando" @click="dialogo.publicar = true" />
                                        <div v-if="!puedePublicar" class="text-caption text-grey-7 q-ml-sm">No tienes permiso para publicar.</div>
                                        <div v-else-if="sucio" class="text-caption text-grey-7 q-ml-sm">Guarda el borrador antes de publicar.</div>
                                    </q-card-actions>
                                </q-card>
                            </div>
                            <div>
                                <q-card flat bordered>
                                    <q-card-section class="pca-fila">
                                        <div class="text-subtitle1 text-weight-bold">Historial de publicaciones</div>
                                        <q-btn flat dense round icon="refresh" aria-label="Actualizar historial" @click="cargarVersiones" />
                                    </q-card-section>
                                    <q-separator />
                                    <div v-if="cargandoVersiones" class="q-pa-md text-center"><q-spinner color="primary" /></div>
                                    <div v-else-if="!versiones.length" class="q-pa-md text-grey-7 text-body2">
                                        Aún no hay publicaciones: el portal muestra el diseño de fábrica.
                                    </div>
                                    <q-list v-else separator>
                                        <q-item v-for="v in versiones" :key="v.id">
                                            <q-item-section>
                                                <q-item-label>
                                                    <span class="text-weight-bold">Versión {{ v.numero }}</span>
                                                    <q-badge v-if="v.actual" color="positive" class="q-ml-sm">Publicada</q-badge>
                                                </q-item-label>
                                                <q-item-label caption>{{ v.published_at }} · {{ v.publicado_por || '—' }}</q-item-label>
                                                <q-item-label v-if="v.nota" caption class="text-grey-8">«{{ v.nota }}»</q-item-label>
                                                <q-item-label caption>
                                                    <span v-for="c in v.cambios.slice(0, 3)" :key="c" class="pca-chip-cambio">{{ c }}</span>
                                                    <span v-if="v.cambios.length > 3" class="text-grey-7"> +{{ v.cambios.length - 3 }} más</span>
                                                </q-item-label>
                                            </q-item-section>
                                            <q-item-section side>
                                                <div class="column q-gutter-xs">
                                                    <q-btn flat dense no-caps size="sm" icon="visibility" label="Ver" @click="verVersion(v)" />
                                                    <q-btn v-if="puedeEditar && !v.actual" flat dense no-caps size="sm" color="primary"
                                                           icon="settings_backup_restore" label="Restaurar" @click="dialogo.restaurar = v" />
                                                </div>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-card>
                            </div>
                        </div>
                    </q-tab-panel>
                </q-tab-panels>
            </q-card>

            <input ref="archivo" type="file" class="pca-oculto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                   @change="subirArchivo">
        </template>

        <!-- Confirmar publicación -->
        <q-dialog v-model="dialogo.publicar">
            <q-card class="pca-dialogo" style="min-width: 360px; max-width: 560px">
                <q-card-section class="text-h6">¿Publicar los cambios?</q-card-section>
                <q-card-section class="q-pt-none">
                    <p>Los clientes verán estos cambios de inmediato en las pantallas de acceso del portal.</p>
                    <ul class="pca-cambios">
                        <li v-for="c in (estado && estado.cambios_pendientes) || []" :key="c">{{ c }}</li>
                    </ul>
                    <q-banner v-if="estado && estado.borrador && estado.borrador.base_desactualizada" class="bg-orange-1 text-orange-10 q-mt-sm" dense rounded>
                        Alguien publicó después de que se creó este borrador: lo publicado se reemplazará por completo.
                    </q-banner>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancelar" v-close-popup />
                    <q-btn color="positive" no-caps icon="publish" label="Sí, publicar" :loading="publicando" @click="publicar" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Confirmar descarte -->
        <q-dialog v-model="dialogo.descartar">
            <q-card class="pca-dialogo" style="min-width: 320px; max-width: 480px">
                <q-card-section class="text-h6">¿Descartar el borrador?</q-card-section>
                <q-card-section class="q-pt-none">
                    Se pierden los cambios no publicados (guardados y sin guardar). Lo publicado no cambia.
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancelar" v-close-popup />
                    <q-btn color="negative" no-caps label="Sí, descartar" :loading="descartando" @click="descartar" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Confirmar restauración -->
        <q-dialog :model-value="!!dialogo.restaurar" @update:model-value="v => { if (!v) dialogo.restaurar = null; }">
            <q-card v-if="dialogo.restaurar" class="pca-dialogo" style="min-width: 320px; max-width: 480px">
                <q-card-section class="text-h6">Restaurar la versión {{ dialogo.restaurar.numero }}</q-card-section>
                <q-card-section class="q-pt-none">
                    Su configuración se copiará al borrador{{ estado && estado.borrador ? ' y reemplazará el borrador actual' : '' }}.
                    Los clientes no verán nada hasta que la publiques.
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancelar" v-close-popup />
                    <q-btn color="primary" no-caps label="Restaurar al borrador" :loading="restaurando" @click="restaurar" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Conflicto entre administradores -->
        <q-dialog :model-value="!!dialogo.conflicto" persistent>
            <q-card v-if="dialogo.conflicto" class="pca-dialogo" style="min-width: 320px; max-width: 500px">
                <q-card-section class="text-h6"><q-icon name="group" class="q-mr-sm" />Otro administrador hizo cambios</q-card-section>
                <q-card-section class="q-pt-none">
                    <p>{{ dialogo.conflicto.message }}</p>
                    <p v-if="dialogo.conflicto.por" class="text-grey-8">Último cambio: {{ dialogo.conflicto.por }} · {{ dialogo.conflicto.fecha }}</p>
                    <p class="text-grey-8">Para no sobrescribir su trabajo, recarga y vuelve a aplicar tus cambios sobre su versión.</p>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Seguir viendo mis cambios" @click="dialogo.conflicto = null" />
                    <q-btn color="primary" no-caps label="Recargar" @click="recargarTrasConflicto" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
const BASE = '/configuracion/portal-cliente';

export default {
    name: 'PortalClienteApariencia',
    data() {
        return {
            cargando: true,
            errorCarga: null,
            estado: null,
            form: null,
            base: '',
            imagenes: {},
            tab: 'apariencia',
            guardando: false,
            publicando: false,
            descartando: false,
            restaurando: false,
            subiendo: { escritorio: false, movil: false, respaldo: false, logo: false },
            tipoPendiente: null,
            usoLogo: 'oficial',
            previa: { pantalla: 'login', dispositivo: 'escritorio', fuente: 'publicada', version: null, sello: Date.now() },
            escala: 1,
            observador: null,
            versiones: [],
            cargandoVersiones: false,
            nota: '',
            dialogo: { publicar: false, descartar: false, restaurar: null, conflicto: null },
            slotsFondo: [
                { clave: 'escritorio', titulo: 'Fotografía de escritorio', ayuda: 'Fachada o portada. Mínimo 1280 × 600 px; ideal 2560 × 1440.' },
                { clave: 'movil', titulo: 'Imagen para móviles', ayuda: 'Vertical, mínimo 600 × 800 px. Si falta, se usa la de escritorio.' },
                { clave: 'respaldo', titulo: 'Imagen de respaldo', ayuda: 'Opcional. Se usa si la de escritorio no está disponible.' },
            ],
            controlesFondo: [
                { clave: 'posicion_x', etiqueta: 'Posición horizontal', min: 0, max: 100, ayuda: '0 = borde izquierdo, 100 = borde derecho.' },
                { clave: 'posicion_y', etiqueta: 'Posición vertical', min: 0, max: 100, ayuda: '0 = arriba, 100 = abajo.' },
                { clave: 'degradado', etiqueta: 'Intensidad del degradado azul', min: 0, max: 100, ayuda: 'Más alto = texto más legible, foto menos visible.' },
                { clave: 'oscurecer', etiqueta: 'Oscurecimiento', min: 0, max: 80, ayuda: 'Capa negra adicional sobre la foto.' },
                { clave: 'opacidad_imagen', etiqueta: 'Transparencia de la imagen (opacidad)', min: 20, max: 100, ayuda: 'Menos opacidad deja ver el degradado de fondo.' },
            ],
            coloresIdentidad: [
                { clave: 'primario', etiqueta: 'Azul corporativo' },
                { clave: 'profundo', etiqueta: 'Azul profundo' },
                { clave: 'noche', etiqueta: 'Azul nocturno (fondo)' },
                { clave: 'electrico', etiqueta: 'Azul eléctrico (botones)' },
                { clave: 'acento', etiqueta: 'Acento (cian)' },
            ],
            controlesTarjetas: [
                { clave: 'opacidad', etiqueta: 'Opacidad', min: 20, max: 95, unidad: '%' },
                { clave: 'radio', etiqueta: 'Esquinas redondeadas', min: 6, max: 28, unidad: ' px' },
            ],
            controlesFormulario: [
                { clave: 'opacidad', etiqueta: 'Opacidad del vidrio', min: 55, max: 98, unidad: '%' },
                { clave: 'desenfoque', etiqueta: 'Desenfoque', min: 0, max: 30, unidad: ' px' },
                { clave: 'radio', etiqueta: 'Esquinas redondeadas', min: 8, max: 32, unidad: ' px' },
            ],
            pantallas: [
                { clave: 'login', titulo: 'Iniciar sesión', icono: 'login' },
                { clave: 'registro', titulo: 'Crear cuenta', icono: 'person_add' },
                { clave: 'recuperar', titulo: 'Recuperar contraseña', icono: 'key' },
            ],
            dispositivos: {
                escritorio: { etiqueta: 'Escritorio', w: 1366, h: 768 },
                tablet: { etiqueta: 'Tablet', w: 768, h: 1024 },
                movil: { etiqueta: 'Móvil', w: 390, h: 844 },
            },
        };
    },
    computed: {
        puedeEditar() { return !!(this.estado && this.estado.puede.editar); },
        puedePublicar() { return !!(this.estado && this.estado.puede.publicar); },
        sucio() { return !!this.form && JSON.stringify(this.form) !== this.base; },
        revision() { return (this.estado && this.estado.borrador) ? this.estado.borrador.revision : 0; },
        catalogo() { return (this.estado && this.estado.catalogo) || { iconos: [], colores: [] }; },
        pesoMaximoMb() { return Math.round((this.catalogo.peso_maximo_kb || 10240) / 1024); },
        dispositivoActual() { return this.dispositivos[this.previa.dispositivo]; },
        opcionesLogo() {
            return [
                { label: 'Logo oficial del sistema (Información de la Empresa)', value: 'oficial' },
                { label: 'Logo propio del Portal Cliente', value: 'propio' },
            ];
        },
        logoActual() {
            return (this.form && this.form.identidad.logo && this.urlDe(this.form.identidad.logo)) || (this.estado && this.estado.logo_oficial);
        },
        opcionesFuente() {
            const opciones = [
                { label: 'Borrador', value: 'borrador', disable: !(this.estado && this.estado.borrador) },
                { label: 'Publicada', value: 'publicada' },
            ];
            if (this.previa.fuente === 'version') {
                opciones.push({ label: `Versión ${this.versionVista ? this.versionVista.numero : ''}`, value: 'version' });
            }
            return opciones;
        },
        versionVista() { return this.versiones.find(v => v.id === this.previa.version) || null; },
        descripcionFuente() {
            if (this.previa.fuente === 'borrador') return 'Borrador guardado (no visible para clientes)';
            if (this.previa.fuente === 'version') return `Versión ${this.versionVista ? this.versionVista.numero : ''} del historial`;
            return 'Lo que ven hoy los clientes';
        },
        urlPrevia() {
            const q = new URLSearchParams({ fuente: this.previa.fuente });
            if (this.previa.fuente === 'version' && this.previa.version) q.set('version', this.previa.version);
            q.set('_', this.previa.sello);
            return `${BASE}/vista-previa/${this.previa.pantalla}?${q.toString()}`;
        },
        destacadoNoEncontrado() {
            const m = this.form && this.form.mensajes;
            return !!(m && m.destacado && !String(m.titulo || '').includes(m.destacado));
        },
        partesTitulo() {
            const m = this.form.mensajes;
            const titulo = String(m.titulo || '');
            const d = String(m.destacado || '');
            const pos = d ? titulo.indexOf(d) : -1;
            return pos < 0 ? [titulo, '', ''] : [titulo.slice(0, pos), d, titulo.slice(pos + d.length)];
        },
        estiloVelo() {
            const f = this.form.fondo;
            const c = this.form.identidad.colores;
            const k = Math.min(1, f.degradado / 80);
            const a = b => Math.min(0.98, b * k).toFixed(3);
            return {
                background: `linear-gradient(rgba(0,0,0,${f.oscurecer / 100}), rgba(0,0,0,${f.oscurecer / 100})),`
                    + ` linear-gradient(90deg, ${this.rgba(c.noche, a(0.94))} 0%, ${this.rgba(c.noche, a(0.80))} 38%,`
                    + ` ${this.rgba(c.profundo, a(0.50))} 62%, ${this.rgba(c.noche, a(0.70))} 100%)`,
            };
        },
        estiloVeloMovil() {
            const f = this.form.fondo;
            const c = this.form.identidad.colores;
            const k = Math.min(1, f.degradado / 80);
            const a = b => Math.min(0.98, b * k).toFixed(3);
            return {
                background: `linear-gradient(rgba(0,0,0,${f.oscurecer / 100}), rgba(0,0,0,${f.oscurecer / 100})),`
                    + ` linear-gradient(180deg, ${this.rgba(c.noche, a(0.82))} 0%, ${this.rgba(c.noche, a(0.93))} 55%, ${this.rgba(c.noche, a(0.97))} 100%)`,
            };
        },
        estiloMini() {
            const c = this.form.identidad.colores;
            return { background: `linear-gradient(140deg, ${c.noche} 0%, ${c.profundo} 60%, ${c.primario} 160%)` };
        },
        estiloSvc() {
            const t = this.form.identidad.tarjetas;
            const c = this.form.identidad.colores;
            const a = t.opacidad / 100;
            return {
                borderRadius: `${t.radio}px`,
                background: `linear-gradient(160deg, ${this.rgba(c.profundo, a)}, ${this.rgba(c.noche, Math.max(0.1, a - 0.12))}), ${c.noche}`,
            };
        },
    },
    watch: {
        usoLogo(valor) {
            if (valor === 'oficial' && this.form && this.form.identidad.logo) this.form.identidad.logo = null;
        },
        'previa.dispositivo'() { this.$nextTick(this.recalcularEscala); },
        tab(valor) {
            if (valor === 'publicacion') {
                if (!this.versiones.length) this.cargarVersiones();
                this.$nextTick(this.recalcularEscala);
            }
        },
    },
    mounted() {
        this.cargar();
        window.addEventListener('beforeunload', this.avisarSalida);
        window.addEventListener('resize', this.recalcularEscala);
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.avisarSalida);
        window.removeEventListener('resize', this.recalcularEscala);
    },
    methods: {
        async cargar() {
            this.cargando = true;
            this.errorCarga = null;
            try {
                const { data } = await axios.get(`${BASE}/estado`);
                this.aplicarEstado(data);
            } catch (e) {
                this.errorCarga = (e.response && e.response.status === 403)
                    ? 'No tienes permiso para ver esta configuración.'
                    : 'No se pudo cargar la configuración del Portal Cliente.';
            } finally {
                this.cargando = false;
                this.$nextTick(this.recalcularEscala);
            }
        },
        aplicarEstado(data) {
            this.estado = data;
            this.form = JSON.parse(JSON.stringify(data.borrador ? data.borrador.config : data.publicada.config));
            this.base = JSON.stringify(this.form);
            this.imagenes = Object.assign({}, this.imagenes, data.imagenes || {});
            this.usoLogo = this.form.identidad.logo ? 'propio' : 'oficial';
            if (this.previa.fuente !== 'version') this.previa.fuente = data.borrador ? 'borrador' : 'publicada';
            this.previa.sello = Date.now();
        },
        async guardar() {
            this.guardando = true;
            try {
                const { data } = await axios.post(`${BASE}/borrador`, { config: this.form, revision: this.revision });
                this.aplicarEstado(data);
                this.notificar(data.message, 'positive');
            } catch (e) {
                this.manejarError(e, 'No se pudo guardar el borrador.');
            } finally {
                this.guardando = false;
            }
        },
        async descartar() {
            this.descartando = true;
            try {
                const { data } = await axios.delete(`${BASE}/borrador`, { data: { revision: this.revision } });
                this.dialogo.descartar = false;
                this.previa.fuente = 'publicada';
                this.aplicarEstado(data);
                this.notificar(data.message, 'info');
            } catch (e) {
                this.dialogo.descartar = false;
                this.manejarError(e, 'No se pudo descartar el borrador.');
            } finally {
                this.descartando = false;
            }
        },
        async publicar() {
            this.publicando = true;
            try {
                const { data } = await axios.post(`${BASE}/publicar`, { revision: this.revision, nota: this.nota || null });
                this.dialogo.publicar = false;
                this.nota = '';
                this.previa.fuente = 'publicada';
                this.aplicarEstado(data);
                this.cargarVersiones();
                this.notificar(data.message, 'positive');
            } catch (e) {
                this.dialogo.publicar = false;
                this.manejarError(e, 'No se pudo publicar.');
            } finally {
                this.publicando = false;
            }
        },
        async cargarVersiones() {
            this.cargandoVersiones = true;
            try {
                const { data } = await axios.get(`${BASE}/versiones`);
                this.versiones = data.versiones || [];
            } catch (e) {
                this.notificar('No se pudo cargar el historial.', 'negative');
            } finally {
                this.cargandoVersiones = false;
            }
        },
        async restaurar() {
            const version = this.dialogo.restaurar;
            if (this.sucio && !window.confirm('Tienes cambios sin guardar que se perderán al restaurar. ¿Continuar?')) return;
            this.restaurando = true;
            try {
                const { data } = await axios.post(`${BASE}/versiones/${version.id}/restaurar`, { revision: this.revision });
                this.dialogo.restaurar = null;
                this.previa.fuente = 'borrador';
                this.aplicarEstado(data);
                this.notificar(data.message, 'positive');
            } catch (e) {
                this.dialogo.restaurar = null;
                this.manejarError(e, 'No se pudo restaurar la versión.');
            } finally {
                this.restaurando = false;
            }
        },
        elegirArchivo(tipo) {
            this.tipoPendiente = tipo;
            this.$refs.archivo.value = '';
            this.$refs.archivo.click();
        },
        async subirArchivo(evento) {
            const archivo = evento.target.files && evento.target.files[0];
            const tipo = this.tipoPendiente;
            if (!archivo || !tipo) return;
            const datos = new FormData();
            datos.append('tipo', tipo);
            datos.append('archivo', archivo);
            this.subiendo[tipo] = true;
            try {
                const { data } = await axios.post(`${BASE}/imagenes`, datos);
                this.imagenes = Object.assign({}, this.imagenes, { [data.imagen.uuid]: data.imagen });
                if (tipo === 'logo') {
                    this.form.identidad.logo = data.imagen.uuid;
                    this.usoLogo = 'propio';
                } else {
                    this.form.fondo[tipo] = data.imagen.uuid;
                }
                this.notificar(data.message, 'positive');
            } catch (e) {
                this.manejarError(e, 'No se pudo subir la imagen.');
            } finally {
                this.subiendo[tipo] = false;
                this.$refs.archivo.value = '';
            }
        },
        mover(i, delta) {
            const destino = i + delta;
            if (destino < 0 || destino >= this.form.tarjetas.length) return;
            const lista = this.form.tarjetas.slice();
            [lista[i], lista[destino]] = [lista[destino], lista[i]];
            this.form.tarjetas = lista;
        },
        restablecerColores() {
            this.form.identidad.colores = JSON.parse(JSON.stringify(this.estado.defaults.identidad.colores));
        },
        verPantalla(clave) {
            this.previa.pantalla = clave;
            this.tab = 'publicacion';
        },
        verVersion(v) {
            this.previa.version = v.id;
            this.previa.fuente = 'version';
            this.previa.sello = Date.now();
        },
        recargarTrasConflicto() {
            this.dialogo.conflicto = null;
            this.cargar();
        },
        manejarError(e, porDefecto) {
            const r = e.response;
            if (r && r.status === 409) {
                this.dialogo.conflicto = r.data;
                return;
            }
            if (r && r.status === 422 && r.data && r.data.errors) {
                const mensajes = [...new Set(Object.values(r.data.errors).flat())];
                this.notificar(mensajes.slice(0, 4).join(' · '), 'negative');
                return;
            }
            if (r && r.status === 403) {
                this.notificar('No tienes permiso para esta acción.', 'negative');
                return;
            }
            this.notificar((r && r.data && r.data.message) || porDefecto, 'negative');
        },
        urlDe(uuid) { return uuid && this.imagenes[uuid] ? this.imagenes[uuid].url : null; },
        metaDe(uuid) { return uuid ? this.imagenes[uuid] || null : null; },
        tamano(bytes) { return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`; },
        svgIcono(clave) {
            const icono = this.catalogo.iconos.find(i => i.clave === clave);
            return icono ? icono.svg : '';
        },
        gradienteColor(clave) {
            const color = this.catalogo.colores.find(c => c.clave === clave) || this.catalogo.colores[0];
            return color ? `linear-gradient(135deg, ${color.desde}, ${color.hasta})` : '#008CFF';
        },
        rgba(hex, alfa) {
            const limpio = /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.slice(1) : '04112C';
            const [r, g, b] = [0, 2, 4].map(i => parseInt(limpio.slice(i, i + 2), 16));
            return `rgba(${r}, ${g}, ${b}, ${alfa})`;
        },
        estiloRecorte(destino) {
            const f = this.form.fondo;
            const uuid = destino === 'movil'
                ? (f.movil || f.escritorio || f.respaldo)
                : (f.escritorio || f.respaldo);
            const url = this.urlDe(uuid);
            const c = this.form.identidad.colores;
            return {
                background: `linear-gradient(140deg, ${c.noche}, ${c.profundo} 50%, ${c.primario})`,
                '--pca-img': url ? `url("${url}")` : 'none',
                '--pca-pos': `${f.posicion_x}% ${f.posicion_y}%`,
                '--pca-opacidad': f.opacidad_imagen / 100,
            };
        },
        recalcularEscala() {
            const contenedor = this.$refs.contenedorPrevia;
            if (!contenedor) return;
            const ancho = contenedor.clientWidth - 16;
            this.escala = Math.min(1, Math.max(0.2, ancho / this.dispositivoActual.w));
        },
        avisarSalida(e) {
            if (!this.sucio) return undefined;
            e.preventDefault();
            e.returnValue = '';
            return '';
        },
        notificar(message, color) {
            if (this.$q && this.$q.notify) this.$q.notify({ message, color, timeout: 4000, classes: 'pca-notif' });
            else window.alert(message);
        },
    },
};
</script>

<style scoped>
/* El Bootstrap 5 global del sistema define `.row > * { width: 100%; padding: gutter }`, que choca con las
   filas INTERNAS de Quasar (pestañas, botones, interruptores, radios, campos, selectores, avisos). El panel
   no usa `.row` para su propio acomodo (usa .pca-grid), así que dentro de él toda `.row` es de Quasar y se
   neutraliza la regla de Bootstrap. Solo aquí: el resto del sistema no cambia. */
.pca :deep(.row),
.pca-dialogo :deep(.row) { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.pca :deep(.row > *),
.pca-dialogo :deep(.row > *) { width: auto; max-width: none; }
.pca :deep(.q-field__native),
.pca :deep(.q-field__input),
.pca-dialogo :deep(.q-field__native) { width: 100%; }
.pca-opcion { --bs-gutter-x: 0; }
.pca-opcion > * { width: auto; max-width: none; }
.pca-opcion > .q-item__section--avatar { min-width: 40px; padding-right: 12px; }
/* Bootstrap también fija el padding de los hijos de .row: se devuelven los rellenos de Quasar
   (valores de public/plugins/quasar/css/quasar.prod.css). */
.pca :deep(.q-tab) { padding: 0 16px; }
.pca :deep(.row > .q-btn),
.pca-dialogo :deep(.row > .q-btn) { padding: 4px 16px; }
.pca :deep(.row > .q-btn--dense),
.pca-dialogo :deep(.row > .q-btn--dense) { padding: .285em; }
.pca :deep(.row > .q-btn--round) { padding: 0; }
.pca :deep(.q-toggle__inner) { padding: .325em .3em; }
.pca :deep(.q-field__prepend),
.pca-dialogo :deep(.q-field__prepend) { padding-right: 12px; }
.pca :deep(.q-field__append),
.pca-dialogo :deep(.q-field__append) { padding-left: 12px; }
.pca :deep(.q-item__section--side) { padding-left: 16px; padding-right: 0; }

/* Acomodo propio del panel (CSS grid, sin las clases row/col compartidas con Bootstrap). */
.pca-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
.pca-grid--amplia { gap: 24px; }
.pca-grid--par { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.pca-recortes { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: end; gap: 8px; }
.pca-tarjeta { align-items: start; }
.pca-tarjeta-orden { display: flex; flex-direction: column; align-items: center; }
.pca-tarjeta-campos { gap: 8px; }
.pca-centrado { display: flex; align-items: center; }
@media (min-width: 600px) {
    .pca-tarjeta-campos { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
    .pca-tarjeta-campos > .pca-ancho-total { grid-column: 1 / -1; }
}
@media (min-width: 1024px) {
    .pca-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pca-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pca-grid--5-7 { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 24px; }
    .pca-tarjeta { grid-template-columns: auto minmax(0, 1fr) 300px; }
}
@media (max-width: 1023px) {
    .pca-tarjeta-orden { flex-direction: row; justify-content: flex-start; gap: 8px; }
}

.pca-fila { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pca-fila--centro { justify-content: flex-start; }
.pca-fila--envolver { flex-wrap: wrap; justify-content: flex-start; gap: 6px; }
.pca-crece { flex: 1 1 auto; min-width: 0; }

.pca-header {
    position: sticky;
    top: 70px; /* alto de la barra superior del sistema */
    z-index: 5;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 20px;
    margin: -16px -16px 16px;
    padding: 16px;
    background: var(--q-color-white, #fff);
    border-bottom: 1px solid rgba(0, 0, 0, .08);
}
.body--dark .pca-header { background: #1d1d1d; border-bottom-color: rgba(255, 255, 255, .1); }
.pca-header-texto { flex: 1 1 320px; }
.pca-header-estado { display: flex; flex-wrap: wrap; gap: 4px; }
.pca-header-acciones { display: flex; gap: 8px; flex-wrap: wrap; }

.pca-slot-img {
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #04112c;
}
.pca-slot-img img { width: 100%; height: 100%; object-fit: cover; }
.pca-slot-img--vacia { background: repeating-linear-gradient(45deg, #f3f5f9, #f3f5f9 10px, #eaeef5 10px, #eaeef5 20px); }

.pca-recorte {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, .12);
}
.pca-recorte::before {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--pca-img) var(--pca-pos) / cover no-repeat;
    opacity: var(--pca-opacidad, 1);
}
.pca-recorte--escritorio { aspect-ratio: 16 / 9; }
.pca-recorte--movil { aspect-ratio: 9 / 19.5; }
.pca-recorte-velo { position: absolute; inset: 0; }
.pca-recorte-etq {
    position: absolute;
    left: 6px;
    bottom: 6px;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(0, 0, 0, .55);
    color: #fff;
    font-size: 11px;
}

.pca-logo {
    height: 84px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    border: 1px solid rgba(0, 0, 0, .1);
}
.pca-logo img { max-height: 100%; max-width: 100%; }
.pca-logo--oscuro { background: #04112c; }
.pca-logo--claro { background: #eef4ff; }

.pca-color {
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid rgba(0, 0, 0, .2);
    border-radius: 8px;
    cursor: pointer;
    background: none;
}

.pca-mini {
    border-radius: 12px;
    padding: 28px 24px;
    min-height: 200px;
    color: #fff;
    display: flex;
    align-items: center;
}
.pca-mini-mensaje { width: 100%; }
.pca-mini-raya { width: 40px; height: 3px; border-radius: 3px; margin-bottom: 12px; background: linear-gradient(90deg, #00d5ff, #008cff); }
.pca-mini-titulo { font-size: 26px; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; word-break: break-word; }
.pca-mini--compacto .pca-mini-titulo { font-size: 21px; }
.pca-mini--grande .pca-mini-titulo { font-size: 32px; }
.pca-mini-desc { margin-top: 10px; font-size: 14px; line-height: 1.5; color: rgba(220, 233, 255, .85); }
.pca-mini--centro { text-align: center; }
.pca-mini--centro .pca-mini-raya { margin-left: auto; margin-right: auto; }

.pca-tarjeta--inactiva { opacity: .6; }
.pca-icono-sel { display: inline-flex; width: 20px; height: 20px; }
.pca-icono-sel :deep(svg) { width: 20px; height: 20px; }
.pca-swatch {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, .12);
}
.pca-swatch--activo { border-color: #fff; box-shadow: 0 0 0 2px #1976d2; }
.pca-swatch:focus-visible { outline: 2px solid #1976d2; outline-offset: 2px; }
.pca-swatch:disabled { cursor: not-allowed; opacity: .6; }

.pca-svc {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px;
    color: #fff;
    border: 1px solid rgba(0, 213, 255, .18);
}
.pca-svc-icono {
    flex: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
}
.pca-svc-icono :deep(svg) { width: 20px; height: 20px; }
.pca-svc-titulo { font-weight: 700; font-size: 14px; }
.pca-svc-desc { font-size: 12px; color: rgba(205, 222, 250, .8); margin-top: 2px; }

.pca-previa-controles { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; margin-bottom: 8px; }
.pca-previa {
    padding: 8px;
    border-radius: 10px;
    background: repeating-linear-gradient(45deg, #eef1f6, #eef1f6 12px, #e6eaf1 12px, #e6eaf1 24px);
    overflow: hidden;
}
.pca-previa-marco {
    position: relative;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, .25);
    background: #04112c;
}
.pca-previa-marco iframe {
    position: absolute;
    top: 0;
    left: 0;
    border: 0;
    transform-origin: 0 0;
    background: #04112c;
}

.pca-cambios { margin: 6px 0 0; padding-left: 18px; font-size: 13px; }
.pca-chip-cambio {
    display: inline-block;
    margin: 2px 4px 0 0;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(25, 118, 210, .08);
    color: #1d4f91;
    font-size: 11px;
}
.pca-aviso-campo :deep(.q-field__messages) { color: #b26a00; }
.pca-oculto { display: none; }
</style>

<style>
/* Notificaciones de Configuración → Portal Cliente: se pintan fuera del panel; mismo arreglo frente al
   `.row > *` de Bootstrap, limitado a la clase propia .pca-notif (no afecta otras notificaciones). */
.pca-notif .row { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.pca-notif .row > * { width: auto; max-width: none; }
</style>
