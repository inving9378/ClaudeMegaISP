# Fase 2: Campañas 360 con IA · Reporte de QA (agente-qa) · 2026-11-13

**Veredicto de QA: APROBADA.**
- Las 11 pruebas propias pasan y la prueba de punta a punta de Henry pasa con ffmpeg real.
- El navegador confirma lo mismo y los pasos 2 a 7 de la skill pasan.
- Backend corrigió F-1, H-1, H-3 y H-5, y las pruebas se volvieron a correr; ver §11.
- H-2 y H-4 quedan como observaciones de severidad baja.
- El paso 1 (la suite completa) lo corre la sesión principal.

## 1. Comandos que se corrieron (todos en `/var/www/megaisp-henry-marketing`)

| Comando | Salida |
|---|---|
| captura de la línea base del motor (`scratchpad/fase2/qa/captura-base.php`, contra `megaisp_test`, en una transacción que se deshace) con el motor == HEAD `08d58e7e3` | `ok 4 casos` |
| `flock /tmp/megaisp-suite.lock timeout 900 php vendor/bin/phpunit tests/Feature/Marketing/Fase2/Qa/<X>Test.php` (cada archivo por nombre) | ver §2 |
| `flock … php artisan deploy:dry-run-migrations --keep --with-data=marketing_campaigns,marketing_generated_content,marketing_video_templates,permissions,roles,role_has_permissions` | exit 0: «Dry-run OK: 7 migraciones pendientes» (3 de Fase 1 + 4 de Fase 2) |
| `flock … env APP_ENV=testing DB_DATABASE=megaisp_dryrun php artisan migrate:rollback --step=4 --force` | exit 0 (las 4 de Fase 2) |
| `flock … env APP_ENV=testing DB_DATABASE=megaisp_dryrun php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force` | exit 0: 2 plantillas `guion_escenas` y columnas presentes |
| `php artisan serve --host=127.0.0.1 --port=8097 --no-reload` (`DB_DATABASE=megaisp_test`, `QUEUE_CONNECTION=database`) + Playwright (`scratchpad/fase2/qa/nav.js`) | ver §4 |
| `flock /tmp/megaisp-mkt-worker.lock timeout 900 nice -n 10 … queue:work database --queue=video-render --timeout=600 --tries=1 --stop-when-empty` | exit 0 en 225 s (2 renders: 81 s y 138 s) |
| greps de los pasos 5 y 6 sobre el diff | vacíos (ver §5 y §6) |

## 2. Resultado de cada prueba (`tests/Feature/Marketing/Fase2/Qa/`)

| Archivo | Pruebas | Resultado |
|---|---|---|
| `MigracionesFase2Test` | 8 | OK (63 aserciones) |
| `AccesoCampanas360Test` | 11 | OK (67) |
| `AsistenteCampanaTest` | 59 (40 casos de validación) | OK (325) |
| `GeneracionEstrategiaTest` | 19 | OK (141) |
| `GeneracionPiezasTest` | 22 | OK (394) |
| `EditarRegenerarPiezaTest` | 31 | OK (238) |
| `ProducirVideoTest` | 14 | OK (197) |
| `MotorEscenasTest` | 13 | OK (120) |
| `ProduccionPuntaAPuntaTest` | 1 | OK (61). Render real de Reel + Video: 117.7 s |
| `WhatsAppSoloTextoTest` | 3 | OK (29) |
| `EstadoCampanaTest` | 6 | OK (42) |

Apoyos: `Concerns/EscenarioFase2.php` (IA simulada sobre una integración ficticia `ia-qa.invalid`; toda otra URL se registra
y responde 599; los trabajos se corren a mano sobre `Queue::fake`), `Concerns/RendererEspia|TtsEspia|AssetsEspia.php`,
`Concerns/casos-motor-base.php`, `fixtures/escenas-base.json`, `fixtures/plantillas-base.json` y `fixtures/ia/*.json`
(estrategia, los 6 tipos válidos y 20 casos inválidos).

Ajustes a MIS pruebas tras la primera corrida (ninguno baja una exigencia del contrato):
- Un 403 «Solicitud bloqueada por seguridad» se acepta en textos con SQL. Lo da el WAF global `SqlInjectionProtection`,
  que existía antes de esta fase, y no se guarda nada.
- La IA caída se simula con 400. El adaptador central reintenta los 5xx con espera; es comportamiento existente del módulo IA.
- Se comparan los JSON sin depender del orden de las claves, porque MySQL reordena las de una columna JSON.
- `edad_min > edad_max` reporta el error en `edad_max`.
- En la regeneración, el contexto que viaja a la IA son las OTRAS variantes (§2.7).
- `{{ }}` en las indicaciones se rechaza con 422. El contrato pide «se rechaza o se sanea» y esto cumple.
- Las páginas Blade `ficha` y `editar` con un id ajeno o inexistente dan 200 como cascarón: solo montan el tag, sin ningún
  dato de la campaña, y la pantalla muestra «No encontrado» con el 404 de la API (§2.4, §2.9). La API sí da 404.

## 3. Cobertura

- Medida: **no aplica**. No hay pcov ni xdebug en el servidor (`php -m`).
- Funcional:

| Pieza | Prueba |
|---|---|
| 18 rutas, middleware, `throttle:6,1`, ids numéricos | AccesoCampanas360Test |
| POST, PUT y DELETE de campaña con validación §2.4.2 | AsistenteCampanaTest |
| generar, progreso y PUT estrategia; reparto bcmath | GeneracionEstrategiaTest |
| GenerarPiezasCampanaJob y la tabla §2.5.3 | GeneracionPiezasTest |
| PUT pieza, regenerar, RegenerarPiezaCampanaJob | EditarRegenerarPiezaTest |
| ProductorVideoCampana, GuionAMvm, producir, ver, miniatura | ProducirVideoTest |
| VideoTemplateRenderer (con y sin bandera) y RenderVideoJob | MotorEscenasTest |
| estado / transiciones / panel | EstadoCampanaTest |
| 4 migraciones | MigracionesFase2Test |
| WhatsApp solo texto, IA solo por las 2 claves | WhatsAppSoloTextoTest |
| criterio 7a | ProduccionPuntaAPuntaTest |

  Las 3 pantallas Vue se probaron en el navegador (§4). Sin huecos.

## 4. Navegador (8097, `megaisp_test`, usuarios temporales `qa2nav_*` ya borrados)

1. **Asistente**:
   - Sin desborde horizontal a 1440 ni a 375 px.
   - Llenado completo y **doble clic en «Guardar borrador»** → 1 sola fila (`1234.50`, MXN).
   - La URL pasa a `/{id}/editar`. **Recargar a mitad** y volver a guardar sigue dejando 1 fila.
   - «Revisar» muestra `$1,234.50 MXN` y la lista lo muestra como Borrador.
   - `<script>` en el nombre: se guarda limpio.
2. **Generar con IA sin IA asignada** (`megaisp_test` no tiene IA): doble clic → «Falta asignar una IA a «Estrategia de
   campañas 360»…». Despacha 0 trabajos.
3. **Producir con doble clic** (Reel y Video) → 1 video y 1 trabajo `video-render` por guion.
4. **Demorado**: con `created_at` forzado a hace 11 min, la ficha muestra «Sigue en cola: el procesador de…».
5. **Punta a punta en pantalla**:
   - Con el procesador de vista previa, los dos pasan a **Listo**.
   - El Reel sale en MP4 h264 1080×1920 de 11.4 s y el Video en 1920×1080 de 18.1 s. Los dos **se reproducen** en el
     navegador (`currentTime` avanza; la miniatura sale como poster).
   - `ver` responde 206 con `Range` y `miniatura` responde 200 `image/jpeg`.
   - La ficha avisa «Sin voz». Es correcto: no hay IA de voz en `megaisp_test`.
6. **Plantilla desactivada** → «La plantilla «Guion por escenas» no está disponible.» y no crea video.
   **Render fallido** → «Error · No se pudo producir el video», sin traza.
7. **Regenerar sin IA** con doble clic → «Falta asignar una IA a «Piezas de campañas 360…»». `regeneraciones` queda en 0.
8. **WhatsApp**: el único botón de acción es «Copiar» y el aviso «Solo texto: estos mensajes no se envían desde LM» se ve,
   a 1440 y a 375 px.
9. **Otro usuario de LM** (fila `marketing` y rol de staff) en las 4 URL → 403 genérico «LO SENTIMOS, NO ESTA AUTORIZADO…»,
   sin datos. Ver H-3. `/marketing/campanas-360/999999` y `/999999/editar` → «No encontrado».
10. En la consola del navegador, los únicos errores son de lo existente: 401 de `/permissions-auth` en `megaisp_test` y
    403 de `/api/marketing/niches` por el permiso `manage-marketing-niches`.

Capturas: `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase2/qa/capturas/`.

## 5. Revisión de seguridad (paso 5)

- **Llaves**: el grep del diff contra la base sale vacío. Las pruebas usan una llave ficticia y no hay valores en los docs.
- **Validación**: hay 7 FormRequests con listas cerradas. Comprobado con 40 casos de ataque en el alta y 18 casos inválidos
  en la edición.
- **Consultas**: el SQL de los filtros no rompe nada. Lo frena el WAF global o cae en 422.
- **Asignación masiva**: `company_id`, `status`, `spent`, `type` y `estrategia_estado` del cuerpo se ignoran (prueba).
- **XSS**: no hay `v-html` en `campanas360/`. HTML y `<script>` se limpian al guardar.
- **Archivos**: `ver` y `miniatura` resuelven la ruta con `realpath` dentro de `storage/app/marketing/videos`. Se probaron 5
  rutas manipuladas (`../../.env`, `/etc/passwd`…) y todas dan 404.
- **Inyección en ffmpeg**: la lista blanca del renderer descarta colores, tipos, `broll_source` raros y `{{ }}`, y el
  teléfono siempre viene del Brand Kit (MotorEscenasTest, ProducirVideoTest).

## 6. Reglas del sistema (paso 6)

- **IA**: solo `ClienteIACampana` llama a `IA::enviar`, y con las 2 claves. `modulos.php` tiene +2 líneas exactas.
  Los greps de `api.openai`, `anthropic` y `CLAUDE_API_KEY` salen vacíos.
- **WhatsApp**: el código nuevo no menciona gateway, Evolution, envío ni Sofía (grep y WhatsAppSoloTextoTest). No se crean
  filas en `marketing_messages` ni en `marketing_publications`.
- **Mapas y Meta**: sin coincidencias.
- **Dinero**:
  - En JSON siempre va como cadena con 2 decimales.
  - El reparto suma exacto (centavos `100.01`, presupuesto 0, porcentajes que no suman 100).
  - Los `float` del diff son duraciones, `bg_darken` o validación de `kpis.meta`; ninguno es dinero.
- **Acceso**: 18 rutas × 5 tipos de usuario sin acceso → 403. El inactivo → 401 `usuario_inactivo`. Sin sesión nunca da 200.

## 7. Regresión (paso 7)

- `git diff --name-only HEAD`: todo cae dentro de §2.1 o §6, con dos excepciones:
  - `resources/views/module-sidebar/marketing.blade.php` es el bloque del menú §2.11. Henry lo autorizó solo para ese
    bloque; no se probó, por instrucción.
  - `tests/Feature/Marketing/Fase0/Qa/RutasMarketingProtegidasTest.php` lo cambió la sesión principal, no QA.
- `resources/js/app.js` y `config/` no cambiaron.
- Pruebas viejas del motor (corridas por backend, por nombre): RenderVideoJob, VideoTemplateRenderer, Kinetic, FFmpeg y
  Tts quedan igual que en la base. Multivariant tiene los mismos 5 errores de la línea base. MotorEscenasTest confirma que
  la plantilla genérica y la de nicho piden **las mismas escenas y el mismo TTS** que en `08d58e7e3`.
- `npm run dev`: lo compiló frontend (13:31, exit 0, las mismas 2 advertencias de Fase 1).
- Pruebas existentes que comparan el total de rutas: QA no las toca, por regla de Henry. El resultado por nombre está en §9.

## 8. Hallazgos

```
F-1 · media · dueño: Henry/supervisor (contrato), luego backend o frontend
Qué pasa:     en el paso 1 del asistente, «Guardar borrador» con solo nombre y objetivo no guarda nada. La API responde 422
              porque público, oferta, canales, presupuesto y fechas son obligatorios en el POST (§2.4.2), y la pantalla
              lo explica bien («Aún no se puede guardar: faltan… Los pasos marcados en rojo dicen qué falta»).
Qué debería:  §1 y el criterio 1 dicen «se puede guardar como borrador en cualquier paso». Las dos partes del contrato se
              contradicen.
Pasos:        1. /marketing/campanas-360/nueva  2. escribir el nombre y elegir el objetivo  3. «Guardar borrador» → no se crea nada.
Prueba:       navegador §4.1 (captura paso1-guardar.png). AsistenteCampanaTest exige hoy el 422, como dice §2.4.2.
```

```
H-1 · baja · backend (riesgos R4/R11)
Qué pasa:     GenerarEstrategiaCampanaJob, GenerarPiezasCampanaJob y RegenerarPiezaCampanaJob tienen timeout de 170 s
              (Jobs/*CampanaJob.php:24). Cada uno puede hacer 2 llamadas a la IA (intento + reintento de validación), y el
              adaptador central da 120 s por llamada y reintenta los 5xx con espera
              (IA/Services/Adaptadores/AdaptadorHttpBase.php:24-27).
Qué debería:  con la IA real lenta, el trabajo puede morir por timeout. Hoy queda en error y no se cobra dos veces, pero
              conviene pasar 'timeout' en IA::enviar (Services/Campanas360/ClienteIACampana.php:30) o subir el timeout
              del trabajo.
```

```
H-2 · baja · backend (motor; defecto existente, no lo introduce la fase)
Qué pasa:     durante las pruebas, el render dejó archivos sueltos en storage/app/marketing/temp/cta1_*.mp4 (lo crea
              Services/Video/VideoTemplateRenderer.php:448). Además, cada video conserva su voz en
              storage/app/marketing/audio/voiceover/1/ por diseño. Crece el disco con cada render.
Prueba:       EscenarioFase2::limpiarArchivosQa los borra después de cada prueba.
```

```
H-3 · baja · Fase 1 (middleware; igual en /marketing)
Qué pasa:     un usuario con fila «marketing» que escribe una URL de Campañas 360 ve el 403 genérico del sistema, no el
              texto «No tienes acceso a Marketing» del punto 5 del navegador (§5). Es el mismo comportamiento de
              /marketing (Fase 1), así que no es una regresión. No se filtra ningún dato.
```

```
H-4 · baja · backend
Qué pasa:     el tope de 75 s del renderer (Services/Video/VideoTemplateRenderer.php:156-157) solo recorta la voz. Las
              escenas se dibujan con su duración completa: un guion manipulado en la BD con 8×15 s daría un video de 120 s.
              Los validadores limitan el Video a 75 s, así que solo pasa con datos alterados.
```

```
H-5 · baja · backend (decisión documentada en Requests/GenerarCampana360Request.php:12)
Qué pasa:     POST /generar con el cuerpo vacío equivale a alcance «todo» y lanza la IA.
Qué debería:  el contrato no lo prohíbe. Se anota porque un clic sin cuerpo gasta IA.
```

## 9. Pruebas existentes afectadas por las 18 rutas nuevas (QA no las toca; las ajusta la sesión principal)

Corridas por nombre a las 14:15 sobre el árbol actual:

| Prueba | Resultado | Causa |
|---|---|---|
| `Fase0/Qa/RutasMarketingProtegidasTest` | OK (11) | la sesión principal ya la ajustó |
| `Fase1/Qa/AccesoNegadoPorDefectoTest.php:89` | 1 falla | `assertCount(134)` → hay 152 (+18 de Fase 2). Las demás pruebas de ese archivo, que barren TODAS las rutas (auth, `marketing.empresa`, 403/401, sin sesión), pasan con las 18 nuevas. |
| `Fase1/Backend/AccesoSinRutaPermisoBackendTest.php:55` | 1 falla | `assertCount(134, $rutas)` → 152 |
| `Fase1/Qa/RestriccionEquipoEnvTest` | OK (20) | — |
| `Fase1/Qa/NavegacionTest.php:50, :80, :89` | 3 fallas | orden del menú, sub-items de Video/Publicación y «Conversaciones» ya no es item propio. Las causa la reorganización del menú §2.11 (`module.json`), no las rutas. |
| `Fase1/Backend/NavegacionBackendTest` | 1 error + 2 fallas | la misma causa: el menú §2.11 |

Las pruebas de menú caen dentro de §2.11; por instrucción, QA no prueba el menú en esta fase.

## 10. Limpieza

- `megaisp_test`:
  - Se borraron 2 campañas QA2NAV, 11 filas de contenido, 2 usuarios `qa2nav_*` y 4 archivos de video.
  - Quedan 0 campañas QA2 y 0 integraciones QA2. `ia_asignaciones` tiene 0 filas.
  - Las plantillas `guion_escenas` siguen activas.
- Las pruebas corren en transacción y borran sus archivos de video, de temp y de voz.
- El servidor 8097 está apagado y la contraseña temporal se borró.
- `megaisp_dryrun` quedó con `--keep`: es desechable y la siguiente corrida lo recrea.

## 11. Re-corrida tras las correcciones de backend (F-1, H-1, H-3, H-5, tamaño y resolución)

Se ajustaron las pruebas **sin aflojar nada**:
- **F-1.** Los 4 casos «canales vacío», «público sin descripción», «oferta sin descripción» y «presupuesto falta» salen de
  `invalidos()` y pasan a la prueba `AsistenteCampanaTest::test_borrador_incompleto_se_guarda_pero_no_genera_ni_activa`.
  Esa prueba comprueba:
  - el POST da 201 como borrador y el campo aparece en `faltantes`;
  - generar con `todo`, `estrategia` y `piezas`, aun con IA asignada, da 422 `campana_incompleta`, sin llamar a la IA ni
    despachar trabajos;
  - activar da 422, aunque la estrategia esté puesta en la BD.

  Se agrega `test_borrador_solo_con_nombre_y_objetivo_y_luego_se_completa`: guarda en el paso 1, `presupuesto_mxn` queda
  `null`, se completa con PUT y queda con `faltantes = []`; sin nombre u objetivo sigue dando 422.
  Los otros 36 casos de ataque siguen exigiendo 422.
- **H-5.** `GeneracionEstrategiaTest::test_generar_sin_alcance_da_422_sin_llamar_a_la_ia`: probado con cuerpo vacío, solo
  `reemplazar` y `alcance: null`. Siempre da 422 `errors.alcance`, con 0 llamadas a la IA y 0 trabajos.
- **H-3.** `AccesoCampanas360Test::test_pantallas_con_fila_marketing_o_ventas_muestran_no_tienes_acceso`: fila marketing
  y fila ventas en las 4 pantallas → 403 con «No tienes acceso a Marketing», sin el nombre de la campaña y sin el componente.
- No se tocaron pruebas de protección de rutas ni de navegación.

| Archivo | Resultado |
|---|---|
| AsistenteCampanaTest | OK (60 · 377 aserciones) |
| AccesoCampanas360Test | OK (12 · 72) |
| GeneracionEstrategiaTest | OK (20 · 154) |
| MigracionesFase2Test | OK (8 · 63) |
| EstadoCampanaTest | OK (6 · 42) |
| WhatsAppSoloTextoTest | OK (3 · 29) |
| GeneracionPiezasTest | OK (22 · 394) |
| EditarRegenerarPiezaTest | OK (31 · 238) |
| ProducirVideoTest | OK (14 · 197) |
| MotorEscenasTest | OK (13 · 120) |
| ProduccionPuntaAPuntaTest | OK (1 · 61); render real de Reel + Video: 127.8 s |

**11 de 11 archivos en verde (190 pruebas). Los archivos temporales del motor quedaron limpios.**

## 12. Re-corrida por la decisión de Henry (Reels una variante por llamada, motivo humano, nichos fuera del público)

`GeneracionPiezasTest` se ajustó **sin aflojar nada**:
- **Trabajos.** Los Reels son 1 trabajo del tipo (solo reparte, sin llamada a la IA) + 1 trabajo por variante, A y B. Con
  los 6 canales hay 8 trabajos en vez de 6, y con «solo reels» hay 3. Las llamadas a la IA son 1 de estrategia, 1 por tipo
  y 2 para Reels. Se comprueba que solo `reel_guion` se reparte por variante.
- **Reintento por variante** (10 casos inválidos):
  - La variante A mala da exactamente 2 llamadas: intento + UN reintento con «no cumplió».
  - La B se genera en su propia llamada, sin reintento, queda `ready` con su contenido y sin `failure_reason`, y no se toca.
  - En total: 4 llamadas.
- **`failure_reason`.**
  - Para un guion: empieza con «La IA no entregó un guion válido: », lleva el motivo esperado de cada caso (p. ej.
    «escena 1: cada escena dura de 2 a 15 segundos», «escena 3: máximo 24 caracteres», «el guion debe tener de 3 a 8
    escenas») y cierra con «Regenéralo o edítalo a mano.».
  - `generation_metadata.errores_validacion` existe con `intento_1` e `intento_2` (exactamente dos) y no guarda el texto
    de la IA.
  - Para una pieza de texto («Regenérala o edítala a mano.») con JSON roto: el motivo es «la respuesta no fue un JSON válido».
- **Prueba nueva `test_el_prompt_no_lleva_nichos_como_publico_y_si_el_tipo_de_publico`.** En TODAS las llamadas (estrategia
  y piezas) el prompt lleva el tipo de público y no lleva «NICHOS DEL PÚBLICO», motivadores, dolores ni vocabulario.
- **Escenario.** `prepararIaPara` lee la `variante` del trabajo para responder solo con esa variante.

| Archivo | Resultado |
|---|---|
| GeneracionPiezasTest | OK (23 · 635 aserciones) |
| AsistenteCampanaTest | OK (60 · 413) |
| AccesoCampanas360Test | OK (12 · 72) |
| GeneracionEstrategiaTest | OK (20 · 154) |
| MigracionesFase2Test | OK (8 · 63) |
| EstadoCampanaTest | OK (6 · 42) |
| WhatsAppSoloTextoTest | OK (3 · 29) |
| EditarRegenerarPiezaTest | OK (31 · 238) |
| ProducirVideoTest | OK (14 · 197) |
| MotorEscenasTest | OK (13 · 120) |
| ProduccionPuntaAPuntaTest | OK (1 · 61); render real de Reel + Video: 138.4 s |

**11 de 11 archivos en verde (191 pruebas).** Los archivos temporales del motor quedaron limpios. No se tocaron pruebas de
rutas ni de navegación.
