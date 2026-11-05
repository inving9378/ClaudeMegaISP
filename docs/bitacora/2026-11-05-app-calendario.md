## 2026-11-05 — App Talento: pantalla de calendario

Pedido de David, después de la leyenda del calendario web: «ok, ahora agrega esto a la app». La app no
tenía calendario; de las opciones eligió la pantalla completa. Como es algo nuevo, sube a la versión
**1.25 (125)**.

**Servidor** (rama `feat/talento-calendario-app`)
- `GET /talento/api/calendario?desde=&hasta=` (`TalentoMobileApiController::calendario`, Sanctum, solo
  la persona en sesión, rango de 62 días como máximo). Lo arma
  `OrdenTrabajoUnifiedService::calendarioDe`: órdenes y tareas generales asignadas a la persona, sin
  archivadas ni canceladas, y las de la tabla vieja `talento_work_orders`.
- `atrasada` igual que en la web: sigue abierta y ya pasó su hora de fin. Sin montos.

**App** (`/home/meganet/TalentoEquipo-avisos`, rama `feat/calendario`, sale de `feat/avisos-locales`)
- Pestaña «Calendario» para todos los perfiles, con vista de semana y de mes. La semana empieza en
  domingo, como en la web. Tocar un día muestra sus tareas.
- Los mismos colores y marcas que la web: prioridad (Alta, Media, Baja), estado (Por hacer, En progreso,
  Terminada) y «!» de atrasada. Una tarea de 24 h o más sale como «Todo el día» en cada día que abarca.
- La leyenda queda arriba, fuera de lo que se desplaza: se ve siempre.
- Tocar una orden abre su detalle; una tarea general muestra sus datos en un aviso. Se recarga al
  volver a la pestaña y al deslizar hacia abajo.
- Sin librerías nuevas.

**Límites.** Sin red no muestra tareas (no guarda copia en el teléfono) y solo avisa que no pudo cargar.
Es solo para ver: no se reprograma desde aquí (eso es lo pendiente de #1158).

Quién: sesión de David (Claude Code).

**Verificado**
- Servidor: `CalendarioAppTest` (7 pruebas): solo lo propio, rango, tarea que empezó antes, archivadas y
  borradas, atrasada, orden contra tarea, validación del rango y sin perfil de colaborador. En dev, con
  una cuenta real de técnico: 3 eventos de 6 semanas en 48 ms (solo lectura).
- App: la lógica de fechas con casos armados (meses de 4, 5 y 6 renglones, cambio de año, 31 de enero a
  febrero, tareas de varios días) en 3 zonas horarias. La pantalla no se pudo abrir en Android (aquí no
  hay emulador): se abrió en navegador con `react-native-web` y datos sintéticos a 360×640, 412×915 y
  640×360, claro y oscuro; al bajar hasta el final en Semana y en Mes la leyenda no se mueve, y sin red
  sale el mensaje. eslint sin errores; `assembleRelease` OK.
- APK: 1.25 (125), misma firma que la 1.24, `zipalign -P 16` OK y el bundle trae el calendario.
- **Falta:** probarla en un teléfono. APK de prueba, no registrado como actualización:
  https://dev.meganett.com.mx/downloads/meganetV125.apk (MD5 `d9756e199ac01e6598fe79afe46a15a8`). Lleva lo de la 1.24, que
  tampoco se ha vuelto a probar en un teléfono, y necesita esta consulta en main para cargar.
- **iPhone:** la rama `build/iphone` no tiene esto.
