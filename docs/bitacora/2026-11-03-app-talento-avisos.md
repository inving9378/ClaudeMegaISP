## 2026-11-03 — App Talento: avisos en el teléfono que arma la propia app (sin Firebase)

Pedido de David: verificar que la app avise cuando se asigna una tarea o un proyecto, si hubo error
al registrar la llegada o la salida, si se le hizo un préstamo, si no va cumpliendo el objetivo de la
semana, si se le aplicó una penalización, cuando ya puede revisar su pago, si se le asigna un
documento y si hay un curso nuevo para su rol o general. Después: «eso debe ser nativo de la app, no
que el servidor mande nada… la app debe poder notificar según los datos que le lleguen y tenga», y
«comienza por lo que no dependa de Firebase».

**Cómo estaba.** La app no avisaba en ninguno de los 9 casos. El único aviso programado (orden nueva,
`TalentoMobileApiController::sendPushToUser`) usa la API antigua de Firebase (`fcm/send` con server
key), que Google empezó a apagar el 22-jul-2024; además no hay clave de Firebase en el servidor ni
`google-services.json` en la app (0 teléfonos registrados en `talento_device_tokens`). En la web solo
la tarea asignada llega a la campanita. Eso se deja como está.

**Servidor** (rama `feat/talento-avisos-app`, commit `76d7af636`):
- `GET /talento/api/avisos` (`TalentoAvisosController::estado`, Sanctum, solo la persona en sesión):
  órdenes y tareas abiertas asignadas (mismo criterio que «Mi día», últimos 30 días), proyectos a su
  cargo (`lead_colaborador_id`), asistencia (horario de la ficha, registro de hoy, el que siga
  abierto y los días de las últimas 2 semanas «para revisión» o marcados como falta), préstamos
  (registrado/autorizado), penalizaciones, semanas pagadas (liquidaciones **cerradas**), documentos
  sin firmar, cursos de su rol o generales y el resultado semanal (no llegó al mínimo / bono). Sin
  montos: el aviso se ve en la pantalla bloqueada.
- Con `?objetivo=1` agrega cómo va la semana en curso contra el mínimo de cada regla que le aplica
  (`TalentoWeeklyRuleEvaluationService::avanceEnCurso`, mide igual que el cierre del domingo, solo
  lectura, sin `calidad_senal`).
- No manda nada al teléfono y no toca dinero ni permisos.

**App** (`/home/meganet/TalentoEquipo`, rama `feat/avisos-locales` en el worktree
`/home/meganet/TalentoEquipo-avisos`, sale de `build/play-store`; commits `3e7c434` avisos y
`3a78786` versión **1.24 (124)**, que sube por ser algo nuevo):
- La app pide ese resumen al abrirse, al volver a ella, después de marcar asistencia y cada ~15 min
  en segundo plano (`react-native-background-fetch` 4.4.2, MIT), lo compara con lo que ya había visto
  (guardado por persona en el teléfono) y muestra lo nuevo con Notifee 9.1.8. La primera vez solo
  recuerda; avisa de lo nuevo desde ahí. Más de 3 de un mismo tipo se juntan en un aviso.
- Recordatorios de asistencia programados en el teléfono (salen aunque no haya revisión): «No has
  marcado tu llegada» 30 min después de la entrada en sus días de trabajo, y «No has marcado tu
  salida» 30 min después de la salida esperada si el registro sigue abierto. Se quitan solos al
  marcar. Si la llegada o salida falla con la app en segundo plano, también avisa; el mensaje de
  error de la salida ahora dice el motivo del servidor, como la llegada.
- Objetivo de la semana: del jueves 17:00 al sábado 18:00, una vez por semana, si va abajo del
  mínimo de alguna regla («Llevas 20 de 36 puntos…»).
- Tocar un aviso abre su pantalla (la orden, Mi día o la sección de Talento correspondiente).
- Pide el permiso de avisos (Android 13+) y, una sola vez, quitar la restricción de batería.
- Notifee declara permisos que la app no usa; se quitan del manifiesto `FOREGROUND_SERVICE`,
  `SCHEDULE_EXACT_ALARM`, `BROADCAST_CLOSE_SYSTEM_DIALOGS`, `ACCESS_NOTIFICATION_POLICY` y su
  servicio en primer plano (Play pide justificar los dos primeros). Los recordatorios usan alarma no
  exacta (`SET_AND_ALLOW_WHILE_IDLE`): pueden salir unos minutos tarde.

**Valores elegidos** (los que se propusieron; David dijo «comienza» sin cambiarlos): tolerancia 30
min, objetivo el jueves a las 17:00, pago al cerrar la semana.

**Límites.** Con la app cerrada no es instantáneo (Android revisa como mucho cada 15 min; en ahorro
de batería o en marcas como Xiaomi/Huawei/Oppo puede tardar más). Si se fuerza el cierre de la app,
no revisa hasta que se abra. **Sin horario en la ficha no hay recordatorio de llegada** y la salida
solo se recuerda si el registro tiene hora esperada: en dev ningún colaborador activo tiene
`shift_start`/`shift_end`/`work_days` capturados.

**Probado.**
- Servidor: la consulta como `demo_*` y `prueba_mostrador` (200, ~100 ms; trae la tarea, el préstamo
  de prueba id 26 y el documento pendiente de la cuenta de prueba); con `?objetivo=1` trae la regla
  de cada perfil (técnico en puntos, vendedor en prospectos y ventas). Solo lectura.
- App: la lógica de comparación y recordatorios con casos armados (primera vez, nuevo, repetido,
  reabierto, agrupado, préstamo registrado → autorizado, horario lun–vie, llegada tarde, salida
  pendiente, objetivo jueves–sábado); eslint sin errores nuevos; bundle de Android;
  `assembleRelease` OK. El APK lleva `POST_NOTIFICATIONS` y no los permisos quitados ni el servicio
  en primer plano; misma firma que la 1.23 (SHA-256 del certificado igual) y `zipalign -P 16` OK.
- **Falta:** probarla en un teléfono. APK de prueba (no registrado como actualización del equipo):
  https://dev.meganett.com.mx/downloads/meganetV124.apk (MD5
  `634f7c326a24d5daed900f355b9b6d35`). Necesita la consulta del servidor en main para funcionar.
- **iPhone:** la rama `build/iphone` no tiene esto. Al juntarlas, agregar en `Info.plist`
  `UIBackgroundModes` (`fetch`, `processing`) y `BGTaskSchedulerPermittedIdentifiers`
  (`com.transistorsoft.fetch`), y correr `pod install`.

Quién: sesión de David (Claude Code).

## 2026-11-03 09:16 — La 1.24 se cerraba al abrir (venía de la versión para Play)

Al probar el APK de la 1.24 en un teléfono (Samsung, Android 17), la app se cerraba al abrir.

**Cómo se encontró.** Aquí no hay emulador: el servidor es una máquina virtual sin virtualización
anidada (CPU «QEMU Virtual CPU 2.5+», sin `/dev/kvm`) y la alternativa (Android en contenedor)
necesita root. Se agregó a la app una pantalla que, si se cae, muestra el error con botón de
compartir (`FallaActivity`, en su propio proceso `:falla`; el aviso se instala en
`MainApplication.attachBaseContext`, antes que las librerías que arrancan solas). El texto que
mandaron: `UnsatisfiedLinkError: cannot locate symbol "__cxa_init_primary_exception" referenced by
libVisionCamera.so`.

**Causa.** No eran los avisos: era la actualización a React Native 0.81 (rama `build/play-store`),
que nunca se había probado en un teléfono. Ahí se fijó el NDK 28.2 para los 16 KB; vision-camera
se compilaba con él y pedía funciones de la libc++ del NDK 28, pero la `libc++_shared.so` que va
en el APK es la de React Native (NDK 27), que no las tiene. Android no podía cargar la librería
de la cámara al arrancar. La 1.23 de Play (`meganetV123-play.apk`/`.aab`) tiene la misma falla:
**no subirla**.

**Arreglo.** NDK 27.1.12297006, el mismo de React Native 0.81 (commit `e9862a6` en
`build/play-store`, mezclado en `feat/avisos-locales`). Los 16 KB siguen: vision-camera, screens
y el plugin de React Native compilan con `-DANDROID_SUPPORT_FLEXIBLE_PAGE_SIZES=ON`. El NDK 27
del SDK estaba a medio instalar (solo `source.properties`); se instaló completo y la carpeta
incompleta quedó en `/opt/android-sdk/ndk-27.1-instalacion-incompleta`.

**Revisado en el APK** (sigue siendo 1.24/124, es corrección): ninguna librería nativa pide un
símbolo que no ofrezca otra librería del APK o el sistema, en las 4 arquitecturas (la revisión
marca justo este error en el APK anterior); todas las de 64 bits cargan en páginas de 16 KB;
`zipalign -P 16` y firma iguales a la 1.23. Mismo enlace:
https://dev.meganett.com.mx/downloads/meganetV124.apk (MD5 `7ba44d82e07954c34dcb0f364e5bf20a`).
Falta volver a probar en el teléfono; si algo más la cierra, sale la pantalla con el error.

Quién: sesión de David (Claude Code).
