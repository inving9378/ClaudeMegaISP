## 2026-10-02 — Segunda ronda de feedback probando en el celular real

David probó la app v1.9 en su teléfono y mandó una lista larga de pendientes. Resumen de cada
uno y qué se hizo:

### 1. "ponle una orden del dia... debo poder hacer las cosas en ella como mismo la web"
Se agregó una OT real de hoy (#20, Soporte/reparación, cliente real) al colaborador demo. El
flujo de iniciar/evidencia/cierre YA estaba completo desde antes (v1.6) — solo faltaban datos
reales con fecha de hoy para poder probarlo (las 2 OTs que ya tenía el demo eran del 22 de
septiembre, fuera del filtro "hoy").

### 2. "lo mismo para con el flujo de campo, lo veo pero no puedo hacer nada"
Se investigó el panel web "Flujo de campo" (`TalentoCampo.vue`/`TalentoFieldFlowController`) a
fondo: los gates de permiso (subir evidencia, firmar, aceptar) están bien resueltos para una
cuenta admin/DESARROLLADOR — no se encontró ningún bug de permisos ahí. La lectura más probable
es que "flujo de campo" se refería al flujo de la propia app (Mi día/Órdenes), que antes de
agregar la OT de hoy se veía vacío — queda resuelto por el punto 1.

### 3. "en informacion faltan las credenciales como mismo esta en vendedores" (web y app)
Esto es el GAFETE/credencial de identificación (foto + nombre + puesto + teléfono + correo
sobre una plantilla con logo de la empresa), no el tab "Credenciales" de documentos — son dos
cosas distintas con nombre parecido. Se reusó el MISMO template compartido que ya usa Vendedores
(`App\Models\Credential`, las 3 imágenes configuradas en `/configuracion/credencial`): nueva
card "Mi credencial" en la pestaña Información, tanto en la ficha web como en la app, con botón
para descargar el mismo PDF.

### 4. "en ruta planta solo me sale que hay una pero no puedo ver nada mas"
Nueva pantalla de detalle de ruta (web ya lo tenía, la app no) — paradas con su OT vinculada +
desviaciones detectadas. Confirmado en la auditoría previa que ni la propia web deja al técnico
actuar sobre una ruta (solo verla) — no se inventó una acción que no existe.

### 5. "en credenciales que debe ir? porque esta vacio"
Este es el OTRO "credenciales" — licencia de conducir y documentos oficiales con fecha de
vencimiento (`TalentoCredentialController`, tipo `driver_license`/`other`), que un administrador
da de alta (el colaborador no se auto-registra ahí, se entera por correo). Estaba vacío porque
nadie lo había dado de alta para la cuenta demo — se agregó una licencia de conducir de prueba
(vigente, vence en 4 meses) para que se vea poblado.

### 6. "en academia debe salir identico a la web"
Se revisó el componente real de la web (`TalentoAcademia.vue`) y se acercó la app a ese patrón:
filtro por departamento (chips) + resumen "Mis certificaciones" (N de M cursos certificados) +
ícono de medalla en los cursos ya certificados. No es pixel-perfecto (no hay forma de comparar
visualmente sin navegador), pero la estructura y la información mostrada ya coinciden.

### 7. "en los cursos debo poder hacerlos desde la app"
Causa real encontrada: en toda la base de datos de dev había **0 exámenes** — por eso ningún
curso mostraba nada que "hacer" (el botón de examen nunca aparecía). Se corrigió además el
detalle de curso para mostrar bien los materiales de tipo texto/referencia (antes solo sabía de
video con URL, así que la mayoría de los materiales reales se veían vacíos). Se agregó un examen
real y persistente (4 preguntas) a "Seguridad en campo (NOM-001)" para poder probar de principio
a fin: tomar el examen, enviarlo y ver la calificación.

### 8. "en dispositivos debe mostrar el dispositivo actual... y el qr para descargar la app"
Causa real encontrada: el registro de dispositivo dependía de que Firebase/FCM funcionara, y
sin `google-services.json` configurado en dev eso nunca pasa — por eso "Dispositivos" se veía
vacío aunque la app sí estuviera instalada y en uso. Se desacopló: ahora se registra siempre al
loguear, sin depender de FCM. Se agregó además un código QR (generado en el servidor, sin
ninguna librería nueva en la app) que apunta al link real de descarga, para escanear desde otro
teléfono y vincularlo.

### Backend
8 endpoints Sanctum nuevos, todos delegando directo a la lógica que ya existe y prueba la web
(cero duplicación): credencial PDF (+ versión firmada pública para el navegador del teléfono),
vincular dispositivo propio (se corrigió el permiso — el método ya estaba diseñado para
autoservicio pero el gate era admin-only), detalle de ruta, y QR de descarga.

Commits backend: `a45cf56e` + `dbf41682` (merge a `main`).
Commit app: `b3edf80` en `/home/meganet/TalentoEquipo` (rama `master`).

**APK:** `http://192.168.105.11/downloads/talento-v1.10.apk` — versión **1.10** (versionCode 110).

### Pendiente
Validación visual de David en el teléfono real de cada punto.
