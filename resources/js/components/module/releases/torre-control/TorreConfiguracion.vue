<template>
  <div class="tconf">

    <!-- ── Cabecera ─────────────────────────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1"><i class="bi bi-gear-fill me-2"></i>Configuración de la Torre</h2>
        <p class="text-muted small mb-0">
          Todo lo que decide cuánto puede avanzar el circuito sin ti — en un solo lugar.
          Cada valor es el <b>medido en vivo</b>, con la fuente de la que salió.
        </p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" :disabled="cargando" @click="cargarTodo(true)">
          <i class="bi bi-arrow-clockwise" :class="{ 'spin': cargando }"></i> Recalcular
        </button>
      </div>
    </div>

    <div v-if="avisoConsolidacion" class="alert alert-secondary py-2 px-3 small">
      <i class="bi bi-signpost-split me-1"></i>
      Ésta es la <b>única</b> pantalla de configuración de la Torre. El engrane de la barra de
      pestañas y el de la cabecera del sistema traen aquí; ya no abren tableros propios.
    </div>

    <div v-if="!puedeEditar" class="alert alert-info py-2 px-3 small">
      <i class="bi bi-eye me-1"></i> <b>Solo lectura</b> — necesitas <code>torre.config.edit</code> para
      cambiar algo. Ves la configuración completa a propósito: saber bajo qué régimen corre el
      circuito no debería depender de poder cambiarlo.
    </div>

    <div v-if="error" class="alert alert-danger py-2 px-3 small">{{ error }}</div>

    <!-- ── Sub-pestañas ─────────────────────────────────────────────────────────────────── -->
    <ul class="nav nav-pills gap-1 mb-3 tconf-nav">
      <li v-for="s in secciones" :key="s.clave" class="nav-item">
        <a class="nav-link py-1 px-3" :class="{ active: seccion === s.clave }"
           href="#" @click.prevent="seccion = s.clave">
          <i class="bi me-1" :class="s.icono"></i>{{ s.titulo }}
          <span v-if="s.badge" class="badge rounded-pill ms-1"
                :class="s.badgeClase || 'bg-secondary'">{{ s.badge }}</span>
        </a>
      </li>
    </ul>

    <div v-if="cargando && !fronteras" class="text-muted small py-4">Midiendo la configuración vigente…</div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SECCIÓN 1 · FRONTERAS
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'fronteras'" v-if="fronteras">

      <div class="alert alert-warning py-2 px-3 small">
        <b>Esto gobierna el único control del circuito que no depende de un modelo.</b>
        La detección es determinista: coincidencia de términos anclada a palabra, sin líneas de
        proceso y respetando negaciones. Lo que se decide aquí es <i>qué se busca</i> y
        <i>qué pasa al encontrarlo</i>. Cada cambio queda registrado con quién, cuándo y el valor
        anterior — y aflojar se registra como alerta, no como un ajuste más.
      </div>

      <!-- Válvula ------------------------------------------------------------------------ -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <b><i class="bi bi-valve me-1"></i>Válvula de contexto</b>
          <span class="small text-muted">
            {{ fronteras.valvula?.resumen.invocaciones_registradas || 0 }} invocaciones registradas ·
            {{ fronteras.valvula?.resumen.sellados_mencion || 0 }} items sellados «mención» ·
            {{ fronteras.valvula?.resumen.sellados_accion || 0 }} «acción»
          </span>
        </div>
        <div class="card-body">
          <p class="small text-muted">
            Cuando el keyword marca frontera dura, se le pregunta a un modelo si el término se
            <b>usa</b> o sólo se <b>nombra</b>. Sólo puede aflojar, nunca ampliar. Si falla, se cae o
            tarda, queda el veredicto del keyword.
          </p>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar"
                       :checked="fronteras.valvula?.activa"
                       @change="pedirConfirmacion({
                         titulo: fronteras.valvula?.activa ? 'Apagar la válvula' : 'Encender la válvula',
                         cuerpo: fronteras.valvula?.activa
                           ? 'Sin válvula, toda coincidencia de término manda el item a tu bandeja aunque sólo mencione el tema. Es el comportamiento más estricto — y el más ruidoso.'
                           : 'Con la válvula encendida, un modelo puede ablandar el veredicto del keyword cuando el término sólo se menciona.',
                         afloja: !fronteras.valvula?.activa,
                         accion: () => guardarValvula({ activa: !fronteras.valvula?.activa })
                       })">
                <span class="form-check-label">
                  <b>Válvula {{ fronteras.valvula?.activa ? 'encendida' : 'apagada' }}</b>
                  <span class="d-block text-muted small">Modelo: <code>{{ fronteras.valvula?.modelo }}</code></span>
                </span>
              </label>
              <div v-if="!fronteras.valvula?.config_enabled" class="alert alert-warning py-1 px-2 small mt-2 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <code>circuito.valvula_contexto.enabled</code> está en <b>false</b> en la config: la
                válvula no corre aunque este interruptor diga que sí.
              </div>
            </div>

            <div class="col-md-6">
              <div class="small fw-semibold mb-1">Modo — hasta dónde puede ablandar</div>
              <label class="d-block small mb-1">
                <input type="radio" :disabled="!puedeEditar" :checked="fronteras.valvula?.modo === 'ablandar'"
                       @change="pedirConfirmacion({
                         titulo: 'Modo «ablandar»',
                         cuerpo: 'Un veredicto de MENCIÓN baja la frontera a «requiere Irving» — nunca a «pasa». El modelo puede decir «sólo lo menciona» y el efecto es que TÚ lo ves.',
                         afloja: false,
                         accion: () => guardarValvula({ modo: 'ablandar' })
                       })">
                <b>Ablandar</b> — baja a «requiere Irving», nunca a «pasa».
              </label>
              <label class="d-block small">
                <input type="radio" :disabled="!puedeEditar" :checked="fronteras.valvula?.modo === 'apagar'"
                       @change="pedirConfirmacion({
                         titulo: 'Modo «apagar» — la frontera DESAPARECE',
                         cuerpo: 'Con este modo, un veredicto de MENCIÓN hace que la frontera dura no se evalúe para ese item, y el sello queda guardado en la fila para siempre. Es el comportamiento que tenía el circuito hasta el 2026-08-27, y es la puerta por la que #182 quedó exento mientras implementaba control de acceso real.',
                         afloja: true,
                         accion: () => guardarValvula({ modo: 'apagar' })
                       })">
                <b>Apagar</b> — la frontera desaparece para ese item <span class="text-danger">(comportamiento anterior)</span>.
              </label>
            </div>
          </div>

          <hr class="my-3">

          <div class="small fw-semibold mb-2">Guardas — se aplican <u>antes</u> de que el modelo pueda aflojar</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar"
                       :checked="fronteras.valvula?.guarda_termino"
                       @change="pedirConfirmacion({
                         titulo: fronteras.valvula?.guarda_termino ? 'Quitar la guarda del término' : 'Poner la guarda del término',
                         cuerpo: fronteras.valvula?.guarda_termino
                           ? 'Sin esta guarda se puede volver a preguntar al modelo por una palabra que no está en el texto del item. Es exactamente lo que pasó con #182 y #191: al modelo se le pasó la categoría («dinero», «credenciales») en vez del término, y de la AUSENCIA de esa palabra concluyó «mención».'
                           : 'Verificación determinista, sin modelo: si el término no aparece en el texto que vería el modelo, la pregunta está mal formada y la válvula no afloja.',
                         afloja: fronteras.valvula?.guarda_termino,
                         accion: () => guardarValvula({ guarda_termino: !fronteras.valvula?.guarda_termino })
                       })">
                <span class="form-check-label">
                  <b>El término debe aparecer en el texto</b>
                  <span class="d-block text-muted small">
                    Determinista, antes de llamar al modelo. Una válvula sólo puede aflojar sobre
                    algo que está ahí.
                  </span>
                </span>
              </label>
            </div>
            <div class="col-md-6">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar"
                       :checked="fronteras.valvula?.guarda_razon"
                       @change="pedirConfirmacion({
                         titulo: fronteras.valvula?.guarda_razon ? 'Quitar la guarda de la razón' : 'Poner la guarda de la razón',
                         cuerpo: 'Si la razón que da el modelo no menciona el término por el que se le preguntó, contestó sobre otra cosa y su «mención» no sostiene nada. Nace apagada porque es nueva y sin medir: enciéndela cuando el contador diga qué tan seguido pasa.',
                         afloja: fronteras.valvula?.guarda_razon,
                         accion: () => guardarValvula({ guarda_razon: !fronteras.valvula?.guarda_razon })
                       })">
                <span class="form-check-label">
                  <b>La razón debe referirse a ese término</b>
                  <span class="d-block text-muted small">
                    Mismo criterio de procedencia que se le pide a JARVIS: toda afirmación con su cita.
                  </span>
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- #9990256 — qué categorías retienen aunque la válvula selle sólo MENCIÓN -------- -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <b><i class="bi bi-shield-exclamation me-1"></i>Qué retiene aunque sólo sea mención</b>
          <span class="small text-muted">Fuente: <code>{{ fronteras.mencion?.fuente }}</code></span>
        </div>
        <div class="card-body">
          <p class="small text-muted">
            Cuando la válvula sella un item como <b>MENCIÓN</b> (el término se nombra, no se
            ejecuta), el item deja de retenerse — salvo en las categorías marcadas aquí, que
            retienen siempre aunque sea sólo mención. Sólo aplica en modo <b>Ablandar</b>: en modo
            <b>Apagar</b> la frontera desaparece entera y esta lista no se consulta.
          </p>
          <div v-if="fronteras.valvula?.modo === 'apagar'" class="alert alert-secondary py-1 px-2 small mb-2">
            <i class="bi bi-info-circle me-1"></i>
            La válvula está en modo «Apagar» — esta lista no tiene efecto ahora mismo.
          </div>
          <div class="row g-2">
            <div class="col-md-6 col-lg-3" v-for="cat in (fronteras.mencion?.categorias || [])" :key="cat.clave">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar"
                       :checked="cat.retiene"
                       @change="toggleMencionCategoria(cat.clave)">
                <span class="form-check-label"><b>{{ cat.clave }}</b></span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Techo del autopilot ------------------------------------------------------------ -->
      <div class="card mb-3">
        <div class="card-header py-2">
          <b><i class="bi bi-speedometer2 me-1"></i>Techo del autopilot</b>
          <span class="small text-muted ms-2">
            vigente <b>{{ fronteras.autopilot?.tope_vigente }}</b> ·
            efectivo <b>{{ fronteras.autopilot?.efectivo || 'ninguno' }}</b>
            (topado por la política base: {{ fronteras.autopilot?.politica_base || 'manual' }})
          </span>
        </div>
        <div class="card-body">
          <p class="small text-muted mb-2">
            Fuente del valor vigente: <code>{{ fronteras.autopilot?.fuente }}</code>.
            La simulación usa <b>el mismo veredicto que aplica el real</b>, no una cuenta paralela —
            lo que promete esta tabla es lo que hace.
          </p>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
              <thead><tr><th>Poner el techo en</th><th>Califican hoy</th><th>Por qué no califican los demás</th><th></th></tr></thead>
              <tbody>
                <tr v-for="lv in ['A','B','C']" :key="lv"
                    :class="{ 'table-active': fronteras.autopilot?.tope_vigente === lv }">
                  <td><b>{{ lv }}</b></td>
                  <td>
                    <b :class="(fronteras.autopilot?.por_nivel[lv]?.califican || 0) === 0 ? 'text-muted' : ''">
                      {{ fronteras.autopilot?.por_nivel[lv]?.califican ?? '—' }}
                    </b>
                    de {{ fronteras.autopilot?.por_nivel[lv]?.de ?? '—' }} items en tu bandeja
                  </td>
                  <td class="small text-muted">
                    <span v-for="(n, m) in (fronteras.autopilot?.por_nivel[lv]?.top_motivos || {})" :key="m"
                          class="me-2"><code>{{ m }}</code> ×{{ n }}</span>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" :disabled="!puedeEditar || fronteras.autopilot?.tope_vigente === lv"
                            @click="pedirConfirmacion({
                              titulo: 'Mover el techo del autopilot a ' + lv,
                              cuerpo: 'Con el techo en ' + lv + ' calificarían ' + (fronteras.autopilot?.por_nivel[lv]?.califican ?? 0) + ' de ' + (fronteras.autopilot?.por_nivel[lv]?.de ?? 0) + ' items de tu bandeja. Los topes de frontera dura siguen aplicando por delante de esto.',
                              afloja: nivelOrden(lv) > nivelOrden(fronteras.autopilot?.tope_vigente),
                              accion: () => guardarTecho(lv)
                            })">Fijar en {{ lv }}</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- POR QUÉ EL CERO. Un «0 califican» admite dos lecturas opuestas y la perilla se mueve
               distinto en cada una: o el techo está mal puesto, o la población muere antes de
               llegar al gate de nivel. Sin esto, la tabla de arriba invita a mover la perilla
               equivocada. -->
          <div v-if="fronteras.autopilot?.diagnostico" class="alert alert-secondary py-2 px-3 small mb-2">
            <div class="fw-semibold mb-1">
              <i class="bi bi-question-circle me-1"></i>Por qué califican {{ fronteras.autopilot?.por_nivel.C?.califican ?? 0 }}
            </div>

            <p class="mb-2">
              <template v-if="dg?.decisiones_historicas.decisiones === 0">
                El autopilot <b>no ha decidido nunca</b> — 0 decisiones en toda la historia del roadmap.
                Pero el techo <b>no es una perilla desconectada</b>: el disparo existe y corre
                (<code>RevisorService::aplicarPreguntas()</code> → <code>intentar()</code>, cada vez que
                se escribe un brief). Lo que pasa es que la bandeja muere antes de llegar al gate de nivel.
              </template>
              <template v-else>
                El autopilot ha decidido <b>{{ dg.decisiones_historicas.decisiones }}</b> veces sobre
                {{ dg.decisiones_historicas.items }} items · última: {{ dg.decisiones_historicas.ultima }}.
              </template>
            </p>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <div class="border rounded p-2 h-100">
                  <b>{{ dg.sin_brief }}</b> de {{ fronteras.autopilot.candidatos }} <b>no tienen brief</b>
                  <span class="text-muted d-block">
                    por nivel:
                    <span v-for="(n, lv) in dg.sin_brief_por_nivel" :key="'sb'+lv" class="me-2">{{ lv }}: {{ n }}</span>
                  </span>
                  <span class="text-muted d-block">Sin brief no hay nada que decidir — el autopilot ni evalúa.</span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 h-100">
                  <b>{{ dg.irving_sin_responder }}</b> de {{ dg.preguntas }} preguntas están marcadas
                  <code>requiere_irving</code> y <b>sin responder</b>
                  <span class="text-muted d-block">
                    {{ dg.con_brief }} items sí tienen brief · sólo <b>{{ dg.todas_respondidas }}</b> lo
                    tienen completamente respondido.
                  </span>
                  <span class="text-muted d-block">Una sola pregunta tuya sin responder frena todo el item.</span>
                </div>
              </div>
            </div>

            <div class="fw-semibold mb-1">Quién escribe ese brief, y si está vivo</div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Quién</th><th>Qué escribe</th><th>A quién cubre</th><th>Estado</th></tr></thead>
                <tbody>
                  <tr v-for="(p, i) in dg.productores" :key="i">
                    <td><code>{{ p.quien }}</code></td>
                    <td>{{ p.escribe }}</td>
                    <td>{{ p.cubre }}</td>
                    <td>
                      <span v-if="p.estado.ultima" :class="p.estado.vencido ? 'text-danger' : 'text-success'">
                        latió {{ p.estado.ultima }}
                      </span>
                      <span v-else class="text-muted">{{ p.estado.cadencia }}</span>
                      <span v-if="p.estado.cadencia && p.estado.ultima" class="text-muted d-block">{{ p.estado.cadencia }}</span>
                      <span v-if="p.estado.agendado === false" class="text-danger d-block">NO está en el crontab</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <button class="btn btn-sm btn-link p-0 small" :disabled="!puedeEditar"
                  @click="pedirConfirmacion({
                    titulo: 'Devolver el techo a la config',
                    cuerpo: 'La perilla deja de mandar y vuelve a gobernar `config/circuito.php` → circuito.autopilot.max_nivel.',
                    afloja: false,
                    accion: () => guardarTecho('config')
                  })">Devolver el control a <code>config/circuito.php</code></button>
        </div>
      </div>

      <!-- Categorías --------------------------------------------------------------------- -->
      <div v-for="c in fronteras.categorias" :key="c.categoria" class="card mb-3"
           :class="{ 'border-secondary opacity-75': !c.activa }">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
          <div>
            <b class="text-capitalize">{{ c.categoria.replace('_', ' ') }}</b>
            <span class="badge ms-2" :class="badgeEfecto(c.efecto)">{{ c.efecto }}</span>
            <span v-if="!c.activa" class="badge bg-secondary ms-1">apagada</span>
          </div>
          <div class="small text-muted">
            <b>{{ c.metricas.items_que_disparan }}</b> items vivos la dispararían ·
            la válvula la abrió <b>{{ c.metricas.valvula_aperturas }}</b> {{ c.metricas.valvula_aperturas === 1 ? 'vez' : 'veces' }}
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar" :checked="c.activa"
                       @change="pedirConfirmacion({
                         titulo: (c.activa ? 'Apagar' : 'Encender') + ' la categoría ' + c.categoria,
                         cuerpo: c.activa
                           ? 'Apagada, ninguno de sus ' + c.terminos.length + ' términos se busca. Hoy la dispararían ' + c.metricas.items_que_disparan + ' items vivos: esos dejarían de frenarse por esta causa.'
                           : 'Encendida, sus términos vuelven a buscarse en el texto de cada item.',
                         afloja: c.activa,
                         accion: () => guardarCategoria(c.categoria, { activa: !c.activa })
                       })">
                <span class="form-check-label small"><b>Categoría {{ c.activa ? 'activa' : 'apagada' }}</b></span>
              </label>

              <div class="small fw-semibold mb-1">Efecto al dispararse</div>
              <label v-for="e in fronteras.efectos" :key="e.clave" class="d-block small mb-1">
                <input type="radio" :disabled="!puedeEditar" :checked="c.efecto === e.clave"
                       @change="pedirConfirmacion({
                         titulo: 'Efecto de ' + c.categoria + ' → ' + e.clave,
                         cuerpo: e.descripcion,
                         afloja: durezaEfecto(e.clave) < durezaEfecto(c.efecto),
                         accion: () => guardarCategoria(c.categoria, { efecto: e.clave })
                       })">
                <b>{{ e.clave }}</b> — <span class="text-muted">{{ e.descripcion }}</span>
              </label>
            </div>

            <div class="col-md-8">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="small fw-semibold">
                  Términos <span class="text-muted">({{ c.terminos.filter(t => t.activo).length }} activos de {{ c.terminos.length }})</span>
                </div>
                <span class="small text-muted">ordenados por cuánto disparan</span>
              </div>

              <div class="tconf-terminos">
                <div v-for="t in c.terminos" :key="t.termino"
                     class="d-flex align-items-center gap-2 py-1 border-bottom"
                     :class="{ 'opacity-50': !t.activo }">
                  <code class="flex-grow-1">{{ t.termino }}</code>
                  <span class="badge" :class="t.disparos > 0 ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-muted'">
                    {{ t.disparos }} {{ t.disparos === 1 ? 'item' : 'items' }}
                  </span>
                  <span class="badge bg-light text-muted" :title="t.palabra_completa
                        ? 'Sólo coincide la palabra exacta'
                        : 'Coincide también con sus flexiones (factura → facturación)'">
                    {{ t.palabra_completa ? 'exacta' : 'flexión' }}
                  </span>
                  <button v-if="t.activo" class="btn btn-sm btn-outline-secondary py-0 px-1" :disabled="!puedeEditar"
                          title="Cambiar el modo de coincidencia"
                          @click="pedirConfirmacion({
                            titulo: 'Modo de «' + t.termino + '»',
                            cuerpo: t.palabra_completa
                              ? 'Pasará a admitir flexiones: disparará también con las palabras que empiecen igual. Dispara MÁS.'
                              : 'Pasará a exigir palabra exacta: dejará de disparar con flexiones. Dispara MENOS.',
                            afloja: !t.palabra_completa,
                            accion: () => guardarTermino(c.categoria, t.termino, 'modo', !t.palabra_completa)
                          })"><i class="bi bi-arrow-left-right"></i></button>
                  <button v-if="t.activo" class="btn btn-sm btn-outline-danger py-0 px-1" :disabled="!puedeEditar"
                          title="Quitar el término"
                          @click="pedirConfirmacion({
                            titulo: 'Quitar «' + t.termino + '» de ' + c.categoria,
                            cuerpo: 'Hoy dispara en ' + t.disparos + ' items vivos: esos dejarían de frenarse por este término. Queda visible aquí como quitado, con su fecha — no se borra.',
                            afloja: true,
                            accion: () => guardarTermino(c.categoria, t.termino, 'quitar')
                          })"><i class="bi bi-x-lg"></i></button>
                  <button v-else class="btn btn-sm btn-outline-success py-0 px-1" :disabled="!puedeEditar"
                          title="Volver a poner el término"
                          @click="pedirConfirmacion({
                            titulo: 'Reponer «' + t.termino + '»',
                            cuerpo: 'Vuelve a buscarse en el texto de cada item.',
                            afloja: false,
                            accion: () => guardarTermino(c.categoria, t.termino, 'agregar', t.palabra_completa)
                          })"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
              </div>

              <div class="d-flex gap-2 mt-2">
                <input v-model="nuevoTermino[c.categoria]" class="form-control form-control-sm"
                       :disabled="!puedeEditar" placeholder="agregar término…"
                       @keyup.enter="agregarTermino(c.categoria)">
                <label class="small text-nowrap d-flex align-items-center gap-1">
                  <input type="checkbox" v-model="nuevoExacto[c.categoria]" :disabled="!puedeEditar"> exacta
                </label>
                <button class="btn btn-sm btn-outline-primary" :disabled="!puedeEditar || !(nuevoTermino[c.categoria] || '').trim()"
                        @click="agregarTermino(c.categoria)">Agregar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bitácora de fronteras ---------------------------------------------------------- -->
      <div class="card mb-3">
        <div class="card-header py-2"><b><i class="bi bi-clock-history me-1"></i>Cambios a las fronteras</b></div>
        <div class="card-body p-0">
          <p v-if="!fronteras.bitacora.length" class="text-muted small p-3 mb-0">
            Sin cambios registrados todavía. Cuando muevas algo aquí, aparecerá con quién, cuándo y el
            valor anterior.
          </p>
          <div v-else class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Cuándo</th><th>Quién</th><th>Qué</th><th>De</th><th>A</th><th></th></tr></thead>
              <tbody>
                <tr v-for="(b, i) in fronteras.bitacora" :key="i">
                  <td class="small text-nowrap">{{ b.cuando }}</td>
                  <td class="small">{{ b.quien }}</td>
                  <td class="small"><code>{{ b.compuerta }}</code> / {{ b.accion }}</td>
                  <td class="small text-muted">{{ b.de || '—' }}</td>
                  <td class="small">{{ b.a || '—' }}</td>
                  <td class="small text-danger">{{ b.detalle }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SECCIÓN 2 · INTERRUPTORES  ·  SECCIÓN 3 · NIVELES Y UMBRALES
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'interruptores'" v-if="config">
      <p class="small text-muted">
        Encendido/apagado. Sólo se pinta como interruptor lo que tiene endpoint de guardado real —
        lo demás vive en «Sin interruptor», con el motivo.
      </p>
      <div class="card mb-3">
        <div class="card-body">
          <label class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" :disabled="!puedeEditar" v-model="form.auditor_activo">
            <span class="form-check-label"><b>Auditor activo</b>
              <span class="d-block text-muted small">El generador de trabajo: escanea el sistema y crea los items que llenan la cola.</span></span>
          </label>
          <button class="btn btn-sm btn-primary" :disabled="!puedeEditar || guardando" @click="guardarConfig">
            {{ guardando ? 'Guardando…' : 'Guardar interruptores' }}
          </button>
        </div>
      </div>
      <control-lista :controles="controlesEditables" titulo="Otros controles verdes del catálogo (se editan donde viven)" mostrar-nota />
    </div>

    <div v-show="seccion === 'umbrales'" v-if="config">
      <div class="card mb-3">
        <div class="card-header py-2"><b>Política base de automatización</b></div>
        <div class="card-body">
          <p class="small text-muted">
            Valor por defecto, no techo: un <code>automatizacion_override = auto</code> sobre un item
            concreto puede excederla — salvo en <b>Manual</b>, que es absoluto.
          </p>
          <div class="d-flex flex-wrap gap-3 mb-3">
            <label v-for="n in config.politica.niveles" :key="n" class="small">
              <input type="radio" :value="n" v-model="form.nivel_automatizacion" :disabled="!puedeEditar">
              <b class="ms-1 text-capitalize">{{ n }}</b>
              <span class="text-muted"> → techo {{ techoDeNivel(n) || 'ninguno' }}</span>
            </label>
          </div>

          <div class="table-responsive">
            <table class="table table-sm mb-3">
              <thead><tr><th>Actor</th><th>Sub-techo</th><th>Efectivo</th><th></th></tr></thead>
              <tbody>
                <tr v-for="(d, a) in config.politica.actores" :key="a">
                  <td class="small">{{ a }}</td>
                  <td class="small">{{ d.sub_techo || '—' }}</td>
                  <td class="small"><b>{{ d.efectivo || 'ninguno' }}</b></td>
                  <td class="small text-muted">
                    {{ d.sub_techo === null ? 'sólo la base lo gobierna' : (d.topado ? 'topado por la base' : '') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="small d-block">Máximo de items por corrida del auditor <em>(1–20)</em>
                <input type="number" min="1" max="20" class="form-control form-control-sm"
                       v-model.number="form.auditor_max_por_corrida" :disabled="!puedeEditar"></label>
            </div>
            <div class="col-md-6">
              <label class="small d-block">Cooldown entre auditorías, minutos <em>(5–1440)</em>
                <input type="number" min="5" max="1440" class="form-control form-control-sm"
                       v-model.number="form.auditor_cooldown_min" :disabled="!puedeEditar"></label>
            </div>
            <div class="col-md-6">
              <label class="small d-block">Slots libres mínimos para disparar el auditor <em>(0–6)</em>
                <input type="number" min="0" max="6" class="form-control form-control-sm"
                       v-model.number="form.auditor_slots_libres_min" :disabled="!puedeEditar"></label>
            </div>
            <div class="col-md-6">
              <label class="small d-block">Terminales por el mismo módulo <em>(1–6)</em>
                <input type="number" min="1" max="6" class="form-control form-control-sm"
                       v-model.number="form.paralelo_mismo_modulo" :disabled="!puedeEditar"></label>
              <span class="text-muted small">
                Cuántas terminales pueden trabajar el mismo módulo a la vez; 1 = comportamiento
                histórico, default de fábrica.
                <template v-if="config?.politica?.paralelo_mismo_modulo">
                  Fuente vigente: {{ config.politica.paralelo_mismo_modulo.fuente }}.
                </template>
              </span>
            </div>
            <div class="col-md-6">
              <label class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" :disabled="!puedeEditar" v-model="form.auditor_gasto_reintento_activo">
                <span class="form-check-label"><b>Reintento del freno de sequía N2 (#712)</b>
                  <span class="d-block text-muted small">Permite un sondeo del auditor cada tantos minutos aunque el generador esté apagado por gasto.</span></span>
              </label>
            </div>
            <div class="col-md-6">
              <label class="small d-block">Minutos entre sondeos del freno de sequía N2 <em>(5–240)</em>
                <input type="number" min="5" max="240" class="form-control form-control-sm"
                       v-model.number="form.auditor_gasto_reintento_min" :disabled="!puedeEditar"></label>
              <span class="text-muted small">
                Cada cuántos minutos se permite un sondeo aunque el generador esté apagado por
                sequía Nivel 2 (#712).
              </span>
            </div>
          </div>

          <div class="mt-3" v-if="config?.politica?.auditor?.gasto">
            <span class="small text-muted d-block mb-1">Estado del freno de sequía N2 (solo lectura):</span>
            <span class="badge" :class="badgeGasto(config.politica.auditor.gasto.estado)">
              <template v-if="config.politica.auditor.gasto.estado === 'armado'">Freno armado</template>
              <template v-else-if="config.politica.auditor.gasto.estado === 'disparado'">
                Apagado desde {{ config.politica.auditor.gasto.desde }}<template v-if="config.politica.auditor.gasto.reintento_en_segundos !== null">, próximo sondeo en {{ config.politica.auditor.gasto.reintento_en_segundos }}s</template><template v-else>, reintento desactivado</template>
              </template>
              <template v-else-if="config.politica.auditor.gasto.estado === 'medio_abierto'">Sondeo disponible en el próximo ciclo</template>
            </span>
          </div>

          <button class="btn btn-sm btn-primary mt-3" :disabled="!puedeEditar || guardando" @click="guardarConfig">
            {{ guardando ? 'Guardando…' : 'Guardar niveles y umbrales' }}
          </button>
        </div>
      </div>

      <control-lista :controles="controlesUmbral" titulo="Umbrales medidos (solo lectura)" />
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SECCIÓN 4 · ACCIONES
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'acciones'">
      <p class="small text-muted">
        Cosas que se <b>hacen</b>, no que se ajustan. Cada fila trae su <b>valor medido</b> y de dónde
        salió; las acciones aparecen sólo donde existen de verdad, y donde no, se dice por qué —
        nunca se pinta un botón gris sin explicación.
        Ninguna corre con un clic suelto: el servidor exige la confirmación aunque esta pantalla ya
        haya preguntado.
      </p>

      <div v-if="!compuertas" class="text-muted small">Midiendo el estado de las compuertas…</div>
      <div v-else>
        <div class="alert py-2 px-3 small" :class="compuertas.corriendo ? 'alert-success' : 'alert-danger'">
          <b>{{ compuertas.linea }}</b>
          <span class="text-muted"> · medido {{ compuertas.medido_en }}</span>
          <span v-if="compuertas.snapshot && compuertas.snapshot.edad_seg !== null" class="text-muted">
            · lectura del sistema hace {{ compuertas.snapshot.edad_seg }}s
          </span>
        </div>

        <div v-for="c in compuertas.compuertas" :key="c.clave" class="card mb-2">
          <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span class="tconf-dot" :class="'tconf-' + c.semaforo"></span>
              <b>{{ c.nombre }}</b>
              <span class="badge bg-light text-dark">{{ c.valor }}</span>
              <span class="small text-muted">fuente: {{ etiquetaOrigen(c.origen) }}</span>
              <span v-if="c.bloquea" class="badge bg-danger">bloquea el circuito</span>
            </div>
            <div class="small text-muted mt-1">{{ c.por_que }}</div>

            <!-- Acciones REALES de esta compuerta -->
            <div v-if="c.control === 'disponible'" class="d-flex flex-wrap align-items-center gap-2 mt-2">
              <template v-for="a in c.acciones" :key="a.clave">
                <select v-if="a.opciones" class="form-select form-select-sm w-auto"
                        :disabled="!puedeEditar" v-model="valorSel[c.clave]">
                  <option v-for="o in a.opciones" :key="o" :value="o">{{ o }}</option>
                </select>
                <button class="btn btn-sm" :class="a.peligrosa ? 'btn-outline-danger' : 'btn-outline-primary'"
                        :disabled="!puedeEditar || a.disponible === false"
                        @click="pedirConfirmacion({
                          titulo: a.etiqueta + ' · ' + c.nombre,
                          cuerpo: a.confirmar,
                          afloja: !!a.peligrosa,
                          accion: () => ejecutarAccion(a, c)
                        })">{{ a.etiqueta }}</button>
                <span v-if="a.disponible === false" class="small text-muted">{{ a.motivo }}</span>
                <span v-if="a.permiso" class="small text-muted">requiere <code>{{ a.permiso }}</code></span>
              </template>
            </div>

            <!-- SIN control, CON motivo -->
            <div v-else-if="c.control && c.control !== 'sin_necesidad'" class="mt-2 small">
              <span class="badge bg-secondary">{{ etiquetaControl(c.control) }}</span>
              <span class="text-muted ms-1">{{ c.control_motivo }}</span>
              <div v-if="c.comando" class="mt-1">
                <code class="user-select-all">{{ c.comando }}</code>
                <span v-if="c.quien_puede" class="text-muted"> — {{ c.quien_puede }}</span>
              </div>
              <div v-if="c.permiso_faltante" class="text-muted">te falta <code>{{ c.permiso_faltante }}</code></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SECCIÓN 5 · SIN INTERRUPTOR, CON MOTIVO
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'sin-interruptor'" v-if="config">
      <p class="small text-muted">
        Lo que esta pantalla <b>no</b> puede cambiar, y por qué. Un control que se ve editable y no
        gobierna nada enseña a desconfiar del tablero entero — así que aquí no hay ninguno.
      </p>

      <div class="card mb-3">
        <div class="card-header py-2"><b><i class="bi bi-lock-fill me-1"></i>Nunca expuestos</b></div>
        <ul class="list-group list-group-flush">
          <li v-for="(g, i) in config.guardrails" :key="i" class="list-group-item small">
            {{ g.icono }} {{ g.texto }} <span class="text-muted">· {{ g.donde }}</span>
          </li>
        </ul>
      </div>

      <control-lista :controles="controlesSoloLectura" titulo="Solo lectura, con su motivo" mostrar-nota />

      <!-- Motores programados — estado, no perilla. La cadencia se edita en el crontab del
           servidor (o en Kernel.php cuando lo dice), nunca desde aquí: por eso vive en esta
           sección y no entre los interruptores. -->
      <div class="card mb-3" v-if="config.motores && config.motores.length">
        <div class="card-header py-2">
          <b><i class="bi bi-cpu me-1"></i>Motores programados</b>
          <span class="small text-muted ms-2">
            Un motor está vivo si <b>corrió bien hace poco</b>, no si alguien dejó un booleano en
            <code>true</code>: un flag es una intención, la última ejecución es un hecho.
          </span>
        </div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Motor</th><th>Cadencia</th><th>Última ejecución</th><th>Tope</th><th>Agendado</th></tr></thead>
            <tbody>
              <template v-for="m in config.motores" :key="m.comando">
                <tr :class="{ 'table-danger': m.vencido }">
                  <td class="small"><code>{{ m.comando }}</code></td>
                  <td class="small">{{ m.cadencia || '—' }}</td>
                  <td class="small">
                    <b v-if="m.at">hace {{ Math.round(m.horas) }} h</b>
                    <b v-else class="text-danger">NUNCA</b>
                  </td>
                  <td class="small">{{ m.max_horas }} h</td>
                  <td class="small">
                    <span v-if="m.agendado === false" class="text-danger">NO está en el crontab</span>
                    <span v-else-if="m.agendado">sí</span><span v-else>—</span>
                  </td>
                </tr>
                <tr v-if="m.ultimo_fallo" class="table-warning">
                  <td colspan="5" class="small">
                    <b>último fallo</b> {{ m.ultimo_fallo.ts }} — <code>{{ m.ultimo_fallo.error }}</code>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SECCIÓN · JARVIS · IDENTIDAD
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'identidad'">
      <p class="small text-muted">
        La cara de JARVIS es <b>una sola para todos</b>: no es una preferencia por usuario. El mismo
        icono se usa en la burbuja, en la cabecera del chat, en los avisos y en su pestaña — una
        fuente, no cuatro imágenes para lo mismo.
      </p>

      <div v-if="!identidad" class="text-muted small">Leyendo el catálogo…</div>

      <template v-else>
        <div v-if="!identidad.para_burbuja.length && !identidad.solo_pantalla.length"
             class="alert alert-warning py-2 px-3 small">
          <b>Todavía no hay iconos importados.</b>
          Deja los PNG en <code>{{ identidad.dir_origen }}</code> y corre
          <code>php artisan jarvis:iconos-importar</code>. Mientras tanto la burbuja usa el icono de
          fábrica — que es la verdad, no un hueco.
        </div>

        <template v-else>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="small text-muted">
              Catálogo generado {{ identidad.generado_en || '—' }} ·
              tamaños {{ identidad.tamanos.join(' · ') }} px
            </div>
            <div class="small text-muted">
              <i class="bi bi-eye me-1"></i>Pasa el ratón por un icono: <b>la burbuja de la esquina
              cambia de verdad</b> mientras miras. Sales y vuelve.
            </div>
          </div>

          <!-- Grupo 1 · los que SÍ pueden ir en la burbuja -->
          <div class="card mb-3">
            <div class="card-header py-2"><b>Para la burbuja</b>
              <span class="small text-muted ms-2">{{ identidad.para_burbuja.length }} disponibles</span>
            </div>
            <div class="card-body">
              <div class="jvsel-grid">
                <button v-for="ic in identidad.para_burbuja" :key="ic.slug" type="button"
                        class="jvsel" :class="{ 'jvsel-on': identidad.elegido === ic.slug, 'jvsel-ruido': !ic.legible_48 }"
                        :disabled="!puedeEditar"
                        @mouseenter="previsualizar(ic)" @mouseleave="previsualizar(null)"
                        @click="pedirConfirmacion({
                          titulo: 'Poner «' + ic.nombre + '» como cara de JARVIS',
                          cuerpo: 'Es un ajuste global: JARVIS se verá así para todos, en la burbuja, la cabecera, los avisos y su pestaña.'
                                  + (ic.legible_48 ? '' : ' ⚠ Este diseño se marcó como RUIDOSO a 48 px: en la burbuja va a leerse mal.'),
                          afloja: false,
                          accion: () => guardarIcono(ic.slug)
                        })">
                  <img :src="ic.urls[96] || ic.urls[192]" :alt="ic.nombre" class="jvsel-img">
                  <div class="jvsel-48">
                    <img :src="ic.urls[48]" :alt="ic.nombre + ' a 48px'" width="48" height="48">
                    <span class="jvsel-48-txt">48 px real</span>
                  </div>
                  <div class="jvsel-nombre">{{ ic.nombre }}</div>
                  <span v-if="identidad.elegido === ic.slug" class="badge bg-success jvsel-badge">en uso</span>
                  <span v-else-if="!ic.legible_48" class="badge bg-warning text-dark jvsel-badge">ruidoso en 48</span>
                  <span v-else class="badge bg-light text-muted jvsel-badge">se lee bien</span>
                  <div v-if="ic.nota" class="jvsel-nota">{{ ic.nota }}</div>
                </button>
              </div>

              <button class="btn btn-sm btn-outline-secondary mt-3" :disabled="!puedeEditar || !identidad.elegido"
                      @click="pedirConfirmacion({
                        titulo: 'Volver al icono de fábrica',
                        cuerpo: 'JARVIS deja de usar el icono elegido y vuelve al de fábrica.',
                        afloja: false,
                        accion: () => guardarIcono(null)
                      })">Volver al de fábrica</button>
            </div>
          </div>

          <!-- Grupo 2 · los que llevan la palabra escrita -->
          <div v-if="identidad.solo_pantalla.length" class="card mb-3">
            <div class="card-header py-2">
              <b>Con la palabra JARVIS escrita</b>
              <span class="small text-muted ms-2">
                pantalla de inicio y documentación — <b>no se ofrecen para la burbuja</b>: a 48 px la
                palabra es una mancha
              </span>
            </div>
            <div class="card-body">
              <div class="jvsel-grid">
                <div v-for="ic in identidad.solo_pantalla" :key="ic.slug" class="jvsel jvsel-quieto">
                  <img :src="ic.urls[192] || ic.urls[96]" :alt="ic.nombre" class="jvsel-img">
                  <div class="jvsel-nombre">{{ ic.nombre }}</div>
                  <span class="badge bg-secondary jvsel-badge">solo pantalla</span>
                </div>
              </div>
            </div>
          </div>
        </template>

        <div v-if="identidad.bitacora && identidad.bitacora.length" class="card mb-3">
          <div class="card-header py-2"><b>Cambios de icono</b></div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Cuándo</th><th>Quién</th><th>De</th><th>A</th></tr></thead>
              <tbody>
                <tr v-for="(b, i) in identidad.bitacora" :key="i">
                  <td class="small text-nowrap">{{ b.cuando }}</td>
                  <td class="small">{{ b.quien }}</td>
                  <td class="small text-muted">{{ b.de }}</td>
                  <td class="small">{{ b.a }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════════════
         SUB-PESTAÑA · PERMISOS
         ══════════════════════════════════════════════════════════════════════════════════ -->
    <div v-show="seccion === 'permisos'">
      <p class="small text-muted">
        Qué habilita cada permiso del circuito, qué se recomienda para cada rol, y qué está concedido
        hoy <b>de verdad</b> (Spatie manda; esta tabla lo lee, no lo adivina). Tu decisión se guarda
        aunque contradiga la recomendación — ese es justo el caso que hay que poder auditar después.
      </p>
      <div v-if="!permisos" class="text-muted small">Leyendo el catálogo de permisos…</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Permiso</th>
              <th v-for="r in permisos.roles" :key="r">{{ r }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in permisos.permisos" :key="p.permiso">
              <td>
                <code class="d-block">{{ p.permiso }}</code>
                <span class="small text-muted">{{ p.habilita }}</span>
                <span v-if="!p.existe" class="badge bg-warning text-dark ms-1">no existe en Spatie</span>
                <span class="small d-block text-muted fst-italic">{{ p.consecuencia }}</span>
              </td>
              <td v-for="r in permisos.roles" :key="r" class="text-center">
                <input type="checkbox" :disabled="!puedeEditar" :checked="p.roles[r].concedido"
                       @change="pedirConfirmacion({
                         titulo: (p.roles[r].concedido ? 'Revocar ' : 'Conceder ') + p.permiso + ' · ' + r,
                         cuerpo: p.consecuencia + (p.roles[r].recomendacion ? ' — Recomendación para este rol: ' + p.roles[r].recomendacion + '.' : ''),
                         afloja: !p.roles[r].concedido,
                         accion: () => togglePermiso(p.permiso, r, !p.roles[r].concedido)
                       })">
                <span v-if="p.roles[r].contradice" class="d-block badge bg-warning text-dark mt-1"
                      title="El estado actual contradice la recomendación">≠ recomendación</span>
                <span v-if="p.roles[r].decision" class="d-block small text-muted mt-1">
                  {{ p.roles[r].decision.por }} · {{ p.roles[r].decision.cuando }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Confirmación en dos pasos (compartida) ────────────────────────────────────────── -->
    <div v-if="confirmacion" class="tconf-backdrop" @click.self="confirmacion = null"></div>
    <div v-if="confirmacion" class="tconf-confirm card shadow">
      <div class="card-body">
        <h5 class="card-title">
          <i class="bi me-1" :class="confirmacion.afloja ? 'bi-exclamation-triangle-fill text-danger' : 'bi-question-circle'"></i>
          {{ confirmacion.titulo }}
        </h5>
        <p class="small">{{ confirmacion.cuerpo }}</p>
        <p v-if="confirmacion.afloja" class="small text-danger mb-2">
          <b>Esto AFLOJA un control.</b> Queda registrado como alerta, con tu nombre, la fecha y el
          valor anterior.
        </p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary" @click="cancelarConfirmacion">Cancelar</button>
          <button class="btn btn-sm" :class="confirmacion.afloja ? 'btn-danger' : 'btn-primary'"
                  :disabled="aplicando" @click="confirmar">
            {{ aplicando ? 'Aplicando…' : 'Sí, hacerlo' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="toast" class="tconf-toast" :class="toastError ? 'bg-danger' : 'bg-dark'">{{ toast }}</div>
  </div>
</template>

<script>
import axios from "axios";

// ⚠️ SE IMPORTA DE "vue", NO del `Vue` GLOBAL — y la diferencia no es de estilo.
//
// En este proyecto conviven DOS copias de Vue: la de npm (que es con la que se crea la app) y la
// UMD global de Quasar. Los hooks de ciclo de vida sólo funcionan si vienen de la MISMA copia que
// creó la app: un `onMounted` tomado del global **nunca se dispara**.
//
// Ese fue el defecto que dejó esta pestaña en blanco el 2026-08-27: como `onMounted` no corría,
// `cargarTodo()` no se llamaba, `fronteras` se quedaba en null, y todas las secciones —que penden
// de `v-if="fronteras"`— no pintaban nada. Sin excepción en consola: la pantalla simplemente
// quedaba vacía, que es el peor modo de fallo posible para un panel de configuración.
//
// El patrón se copió de `TorreConfigPanel.vue`, que usaba el global y funcionaba... porque no tenía
// un solo hook de ciclo de vida (sólo pintaba un botón). Todos los demás componentes de la Torre
// con hooks importan de "vue".
import { ref, reactive, computed, onMounted, defineComponent, h } from "vue";

/**
 * Lista de controles del catálogo con su procedencia. Se declara aquí (componente local, no global)
 * porque sólo esta pantalla la usa y registrarla en `app.js` añadiría superficie sin añadir uso.
 */
const ControlLista = defineComponent({
    name: "ControlLista",
    props: {
        controles: { type: Array, default: () => [] },
        titulo: { type: String, default: "" },
        mostrarNota: { type: Boolean, default: false },
    },
    setup(props) {
        const abierto = reactive({});
        return () => h("div", { class: "card mb-3" }, [
            h("div", { class: "card-header py-2" }, [h("b", props.titulo)]),
            h("div", { class: "card-body p-0" },
                props.controles.length === 0
                    ? [h("p", { class: "text-muted small p-3 mb-0" }, "Ninguno.")]
                    : props.controles.map((c) => h("div", { class: "border-bottom p-2" }, [
                        h("div", {
                            class: "d-flex align-items-center gap-2",
                            style: "cursor:pointer",
                            onClick: () => { abierto[c.clave] = !abierto[c.clave]; },
                        }, [
                            h("span", { class: "badge " + (c.bucket === "verde" ? "bg-success" : c.bucket === "azul" ? "bg-secondary" : "bg-dark") }, c.bucket),
                            h("code", { class: "flex-grow-1" }, c.clave),
                            h("b", c.valor),
                            h("i", { class: "bi " + (abierto[c.clave] ? "bi-chevron-up" : "bi-chevron-down") }),
                        ]),
                        abierto[c.clave]
                            ? h("div", { class: "small text-muted mt-2" }, [
                                h("div", [h("b", "Dónde: "), c.donde]),
                                h("div", [h("b", "Quién lo lee: "), c.quien_lee]),
                                h("div", [h("b", "Qué gobierna: "), c.que_gobierna]),
                                h("div", [h("b", "Consecuencia: "), c.consecuencia]),
                                c.nota && props.mostrarNota ? h("div", { class: "text-warning-emphasis" }, ["🔒 ", c.nota]) : null,
                            ])
                            : null,
                    ]))
            ),
        ]);
    },
});

export default {
    name: "TorreConfiguracion",
    components: { ControlLista },
    setup() {
        const secciones = [
            { clave: "fronteras", titulo: "Fronteras", icono: "bi-shield-lock" },
            { clave: "interruptores", titulo: "Interruptores", icono: "bi-toggles" },
            { clave: "umbrales", titulo: "Niveles y umbrales", icono: "bi-sliders" },
            { clave: "acciones", titulo: "Acciones", icono: "bi-lightning" },
            { clave: "sin-interruptor", titulo: "Sin interruptor", icono: "bi-lock" },
            { clave: "identidad", titulo: "JARVIS · identidad", icono: "bi-person-badge" },
            { clave: "permisos", titulo: "Permisos", icono: "bi-key" },
        ];

        const seccion = ref("fronteras");
        // Arranca en `true`: entre el montaje y la primera respuesta hay un hueco real, y la
        // pantalla debe decir «midiendo», no quedarse muda. Si además el hook no corriera, el
        // «midiendo» permanente es una señal visible en vez de un panel en blanco.
        const cargando = ref(true);
        const guardando = ref(false);
        const aplicando = ref(false);
        const error = ref("");
        const puedeEditar = ref(false);
        const avisoConsolidacion = ref(true);

        const fronteras = ref(null);
        const config = ref(null);
        const compuertas = ref(null);
        const permisos = ref(null);
        const identidad = ref(null);

        const form = reactive({
            nivel_automatizacion: "estandar",
            auditor_activo: true,
            auditor_max_por_corrida: 10,
            auditor_cooldown_min: 15,
            auditor_slots_libres_min: 2,
            paralelo_mismo_modulo: 1,
            auditor_gasto_reintento_min: 30,
            auditor_gasto_reintento_activo: true,
        });

        const nuevoTermino = reactive({});
        const nuevoExacto = reactive({});

        const confirmacion = ref(null);
        const toast = ref("");
        const toastError = ref(false);

        function avisar(msg, esError = false) {
            toast.value = msg;
            toastError.value = esError;
            setTimeout(() => { toast.value = ""; }, 4500);
        }

        // ── Carga ────────────────────────────────────────────────────────────────────────
        async function cargarTodo(refrescar = false) {
            cargando.value = true;
            error.value = "";
            try {
                const [f, c] = await Promise.all([
                    axios.get("/api/roadmap/torre/fronteras", { params: refrescar ? { refrescar: 1 } : {} }),
                    axios.get("/api/roadmap/torre/config"),
                ]);
                fronteras.value = f.data;
                config.value = c.data;
                puedeEditar.value = !!c.data.puede_editar;

                form.nivel_automatizacion = c.data.politica.nivel_automatizacion;
                form.auditor_activo = !!c.data.politica.auditor.activo;
                form.auditor_max_por_corrida = c.data.politica.auditor.max_por_corrida;
                form.auditor_cooldown_min = c.data.politica.auditor.cooldown_min;
                form.auditor_slots_libres_min = c.data.politica.auditor.slots_libres_min;
                form.paralelo_mismo_modulo = c.data.politica.paralelo_mismo_modulo?.valor ?? 1;
                form.auditor_gasto_reintento_min = c.data.politica.auditor.gasto_reintento_min;
                form.auditor_gasto_reintento_activo = !!c.data.politica.auditor.gasto_reintento_activo;
            } catch (e) {
                error.value = "No se pudo leer la configuración: " + (e?.response?.data?.message || e.message);
            } finally {
                cargando.value = false;
            }

            // Las compuertas y los permisos van aparte: su autorización es por ROL y puede negar sin
            // que eso deba tumbar el resto de la pantalla.
            axios.get("/api/roadmap/torre/compuertas")
                .then((r) => { compuertas.value = r.data; sembrarValoresSeleccionados(); })
                .catch(() => {});
            axios.get("/api/roadmap/torre/compuertas/permisos").then((r) => { permisos.value = r.data; }).catch(() => {});
            axios.get("/api/roadmap/torre/jarvis-identidad").then((r) => { identidad.value = r.data; }).catch(() => {});
        }

        // ── Confirmación en dos pasos ────────────────────────────────────────────────────
        function pedirConfirmacion(cfg) {
            confirmacion.value = cfg;
        }
        function cancelarConfirmacion() {
            confirmacion.value = null;
            // Los toggles se pintan desde el servidor (`:checked`, no `v-model`), así que cancelar
            // no deja la casilla movida: basta recargar el estado real.
            cargarTodo();
        }
        async function confirmar() {
            if (!confirmacion.value) return;
            aplicando.value = true;
            try {
                await confirmacion.value.accion();
            } finally {
                aplicando.value = false;
                confirmacion.value = null;
            }
        }

        async function post(url, payload, recargar = true) {
            try {
                const { data } = await axios.post(url, { ...payload, confirmado: true });
                avisar(data.mensaje || "Listo.", !data.ok);
                if (recargar) await cargarTodo();
                return data;
            } catch (e) {
                avisar(e?.response?.data?.mensaje || e?.response?.data?.message || e.message, true);
                return null;
            }
        }

        // ── Escrituras ───────────────────────────────────────────────────────────────────
        const guardarValvula = (p) => post("/api/roadmap/torre/fronteras/valvula", p);
        const guardarCategoria = (categoria, p) => post("/api/roadmap/torre/fronteras/categoria", { categoria, ...p });
        const guardarTecho = (nivel) => post("/api/roadmap/torre/fronteras/techo-autopilot", { nivel });
        const guardarTermino = (categoria, termino, accion, palabraCompleta = false) =>
            post("/api/roadmap/torre/fronteras/termino", { categoria, termino, accion, palabra_completa: palabraCompleta });
        const guardarMencion = (categorias) => post("/api/roadmap/torre/fronteras/mencion-categorias", { categorias });

        // #9990256 — el endpoint recibe la lista COMPLETA de categorías que retienen, no un toggle
        // de una sola; se arma a partir del estado vigente antes de mandarla.
        function toggleMencionCategoria(clave) {
            const actuales = (fronteras.value?.mencion?.categorias || [])
                .filter((c) => c.retiene).map((c) => c.clave);
            const retiene = actuales.includes(clave);
            const nuevas = retiene ? actuales.filter((c) => c !== clave) : [...actuales, clave];
            pedirConfirmacion({
                titulo: retiene
                    ? `Dejar de retener menciones de «${clave}»`
                    : `Retener menciones de «${clave}»`,
                cuerpo: retiene
                    ? "A partir de ahora, un item que sólo MENCIONE este tema (sin ejecutar la acción) dejará de retenerse — pasa aunque la válvula lo haya sellado como mención."
                    : "A partir de ahora, un item que sólo MENCIONE este tema seguirá reteniéndose, igual que si fuera una acción real.",
                afloja: retiene,
                accion: () => guardarMencion(nuevas),
            });
        }

        function agregarTermino(categoria) {
            const t = (nuevoTermino[categoria] || "").trim();
            if (!t) return;
            pedirConfirmacion({
                titulo: "Agregar «" + t + "» a " + categoria,
                cuerpo: "A partir de ahora, cualquier item cuyo texto contenga ese término disparará esta frontera. Agregar ENDURECE: manda más trabajo a tu bandeja, no menos.",
                afloja: false,
                accion: async () => {
                    await guardarTermino(categoria, t, "agregar", !!nuevoExacto[categoria]);
                    nuevoTermino[categoria] = "";
                    nuevoExacto[categoria] = false;
                },
            });
        }

        async function guardarConfig() {
            guardando.value = true;
            try {
                const { data } = await axios.post("/api/roadmap/torre/config", { ...form });
                avisar(Object.keys(data.cambios || {}).length ? "Configuración guardada." : "Sin cambios.");
                await cargarTodo();
            } catch (e) {
                avisar(e?.response?.data?.message || e.message, true);
            } finally {
                guardando.value = false;
            }
        }

        /**
         * VISTA PREVIA EN SITIO. La burbuja vive en OTRA instancia de Vue (se monta fuera de la
         * SPA para estar en todas las pantallas), así que el canal entre las dos es un evento del
         * navegador. Pasar el ratón por un icono cambia la burbuja REAL de la esquina; salir la
         * devuelve. Es la única forma de ver cómo queda antes de guardar — una miniatura en la
         * cuadrícula no dice cómo se lee a 48 px sobre el fondo oscuro de la burbuja.
         */
        function previsualizar(ic) {
            const url = ic ? (ic.urls[96] || ic.urls[48]) : null;
            window.dispatchEvent(new CustomEvent("jarvis:preview-icono", { detail: { url } }));
        }

        async function guardarIcono(slug) {
            const data = await post("/api/roadmap/torre/jarvis-identidad", { slug }, false);
            if (data && data.ok) {
                const urls = data.identidad?.urls || {};
                window.dispatchEvent(new CustomEvent("jarvis:icono-cambiado", {
                    detail: { url: urls[96] || urls[48] || null },
                }));
                const r = await axios.get("/api/roadmap/torre/jarvis-identidad");
                identidad.value = r.data;
            }
        }

        const togglePermiso = (permiso, rol, conceder) =>
            post("/api/roadmap/torre/compuertas/permisos", { permiso, rol, conceder });

        // ── Acciones (compuertas) ────────────────────────────────────────────────────────
        // NO se inventa una lista de acciones: se pinta la que MIDE el servidor. Una lista
        // hardcodeada aquí se desincronizaría del tablero real y acabaría ofreciendo botones que
        // no aplican (o escondiendo los que sí) — que es exactamente «un control falso».
        const valorSel = reactive({});

        function sembrarValoresSeleccionados() {
            (compuertas.value?.compuertas || []).forEach((c) => {
                (c.acciones || []).forEach((a) => {
                    if (a.opciones && valorSel[c.clave] === undefined) valorSel[c.clave] = a.opciones[0];
                });
            });
        }

        const ETIQUETA_CONTROL = {
            sin_privilegio: "EL PANEL NO PUEDE",
            sin_permiso: "TE FALTA PERMISO",
            no_implementado: "NO IMPLEMENTADO",
        };
        const etiquetaControl = (c) => ETIQUETA_CONTROL[c] || "";

        const ETIQUETA_ORIGEN = {
            bd: "base de datos", config: "archivo de config", env: ".env",
            so: "sistema operativo", "bd+so": "BD + sistema",
        };
        const etiquetaOrigen = (o) => ETIQUETA_ORIGEN[o] || o || "—";

        const ejecutarAccion = (a, c) =>
            post("/api/roadmap/torre/compuertas/accion", {
                accion: a.clave,
                valor: a.opciones ? valorSel[c.clave] : undefined,
            });

        // ── Derivados del catálogo de controles ──────────────────────────────────────────
        const todosLosControles = computed(() =>
            (config.value?.grupos_actor || []).flatMap((g) => (g.controles || []).map((c) => ({ ...c, grupo: g.titulo })))
        );
        // Controles que YA tienen su propia UI en otra sección de esta misma pantalla. No se
        // repiten en la lista genérica: pintarlos ahí como una fila verde sin interruptor sería
        // exactamente el «control falso» que esta pantalla existe para no tener.
        const EDITABLES_CON_UI_PROPIA = [
            "torre_config.nivel_automatizacion", "auditor.enabled",
            "auditor.cap_por_ciclo", "auditor.min_intervalo_minutos",
            "fronteras.categorias", "fronteras.terminos", "fronteras.efecto",
            "torre_config.valvula_activa", "torre_config.valvula_modo",
            "torre_config.valvula_guarda_termino", "torre_config.valvula_guarda_razon",
            "torre_config.autopilot_max_nivel",
        ];
        const controlesEditables = computed(() =>
            todosLosControles.value.filter((c) => c.bucket === "verde" && !EDITABLES_CON_UI_PROPIA.includes(c.clave))
        );
        const controlesUmbral = computed(() =>
            todosLosControles.value.filter((c) => c.bucket === "azul" && /max|umbral|min|cap|timeout|cooldown|nivel|tope/i.test(c.clave))
        );
        const controlesSoloLectura = computed(() =>
            todosLosControles.value.filter((c) => c.bucket !== "verde" && !controlesUmbral.value.includes(c))
        );

        // ── Utilidades de pintado ────────────────────────────────────────────────────────
        // Atajo de lectura: el diagnóstico se usa en una docena de sitios del template y
        // `fronteras.autopilot.diagnostico.x` en cada uno lo vuelve ilegible.
        const dg = computed(() => fronteras.value?.autopilot?.diagnostico || null);

        const TECHOS = { manual: null, estandar: "A", asistido: "B", autonomo: "C" };
        const techoDeNivel = (n) => TECHOS[n];
        const nivelOrden = (l) => ({ A: 1, B: 2, C: 3 }[l] || 0);
        const durezaEfecto = (e) => ({ bloquear: 3, bandeja: 2, avisar: 1 }[e] || 2);
        const badgeEfecto = (e) => ({
            bloquear: "bg-danger", bandeja: "bg-warning text-dark", avisar: "bg-info text-dark",
        }[e] || "bg-secondary");
        const badgeGasto = (estado) => ({
            armado: "bg-success", disparado: "bg-danger", medio_abierto: "bg-warning text-dark",
        }[estado] || "bg-secondary");

        onMounted(() => cargarTodo());

        return {
            secciones, seccion, cargando, guardando, aplicando, error, puedeEditar, avisoConsolidacion,
            fronteras, config, compuertas, permisos, identidad, form, nuevoTermino, nuevoExacto,
            confirmacion, toast, toastError, valorSel, etiquetaControl, etiquetaOrigen,
            cargarTodo, pedirConfirmacion, cancelarConfirmacion, confirmar,
            guardarValvula, guardarCategoria, guardarTecho, guardarTermino, agregarTermino,
            guardarMencion, toggleMencionCategoria,
            guardarConfig, togglePermiso, ejecutarAccion, previsualizar, guardarIcono,
            controlesEditables, controlesUmbral, controlesSoloLectura,
            techoDeNivel, nivelOrden, durezaEfecto, badgeEfecto, badgeGasto, dg,
        };
    },
};
</script>

<style scoped>
.tconf-nav .nav-link { font-size: .875rem; }
.tconf-terminos { max-height: 320px; overflow-y: auto; }
.tconf-backdrop {
    position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 2050;
}
.tconf-confirm {
    position: fixed; z-index: 2060; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: min(560px, 92vw);
}
.tconf-toast {
    position: fixed; z-index: 2070; bottom: 24px; right: 24px; color: #fff;
    padding: 10px 16px; border-radius: 6px; font-size: .875rem; max-width: 420px;
}
.tconf-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.tconf-verde { background: #2e9e5b; }
.tconf-ambar { background: #d9a406; }
.tconf-rojo  { background: #d63939; }
.jvsel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.jvsel {
    position: relative; border: 1px solid #dee2e6; border-radius: 10px; background: #fff;
    padding: 12px 10px 34px; text-align: center; cursor: pointer; transition: border-color .15s, box-shadow .15s;
}
.jvsel:hover:not(:disabled) { border-color: #0d6efd; box-shadow: 0 4px 14px rgba(13, 110, 253, .12); }
.jvsel:disabled { cursor: not-allowed; opacity: .7; }
.jvsel-on { border-color: #198754; box-shadow: 0 0 0 2px rgba(25, 135, 84, .18); }
.jvsel-ruido { border-style: dashed; }
.jvsel-quieto { cursor: default; }
.jvsel-img { width: 84px; height: 84px; object-fit: contain; }
/* El 48 px REAL al lado del grande: es el tamaño al que de verdad se va a ver en la esquina, y
   verlo aquí evita el «lo elegí y en chico no se entiende» de después. */
.jvsel-48 { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 6px; }
.jvsel-48-txt { font-size: 10px; color: #6c757d; }
.jvsel-nombre { font-size: 12px; font-weight: 600; margin-top: 6px; word-break: break-word; }
.jvsel-badge { position: absolute; left: 10px; bottom: 10px; font-size: 10px; }
.jvsel-nota { font-size: 10.5px; color: #6c757d; margin-top: 4px; }
.spin { animation: tconf-spin 1s linear infinite; display: inline-block; }
@keyframes tconf-spin { to { transform: rotate(360deg); } }
</style>
