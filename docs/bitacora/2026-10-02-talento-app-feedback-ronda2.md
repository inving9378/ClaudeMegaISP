## 2026-10-02 (continuación) — Tercera ronda: certificación, credencial visual, rutas y modo oscuro

David probó v1.10 y mandó feedback puntual. Resumen:

### 1. "cogí el 100% y no me marca como que pase el curso, ¿se debe aprobar por alguien?"
Respuesta: **sí**. `CertificationService::tryIssue()` exige examen aprobado **Y** una evaluación
práctica aprobada por un evaluador — es el diseño ya existente (certificación = examen + práctica),
no un bug. El problema real era que la app decía "¡Aprobado! Tu certificación quedó registrada"
sin comprobar si de verdad se había emitido. Corregido: ahora distingue 3 estados (reprobado /
aprobado y certificado / aprobado pero falta evaluación práctica) con su mensaje correspondiente.
Se agregó además una evaluación práctica de prueba (aprobada) para el colaborador demo, para que
la certificación completa ya se vea emitida al entrar a Academia.

### 2. "las credenciales... debe verse como mismo está en la web... que se muestren en la app"
"Mi credencial" en Información ahora es un gafete visual real (`ImageBackground` con foto, nombre,
puesto, teléfono y correo superpuestos sobre la misma plantilla compartida que usa Vendedores),
no solo un botón de descarga.

### 3. "en credenciales... lo que pone es driver_licence, valid"
Traducido a español: "Licencia de conducir" / "Vigente" / "Por vencer" / "Vencida", con los días
restantes o de vencida.

### 4. "en ruta planta... me muestra OT #8 y 9 pero no me deja hacer nada más, radio button que no hacen nada"
Cada parada ahora abre la orden real (mismo flujo de iniciar/evidencia/cierre que ya existía para
Órdenes). Backend: `TalentoRouteController::show()` enriquece cada parada con la misma resolución
de cliente/tipo/dirección que ya usa el resto de la app (`OrdenTrabajoUnifiedService::detail()`),
sin duplicar nada.

### 5. "lo pone todo concatenado, ej 2026-10-02T06:00:00.000000Z, arregla eso"
Fecha y hora separadas visualmente (chips con ícono de calendario/reloj), cliente y dirección con
su propia etiqueta — tanto en el detalle de ruta como en el listado.

### 6. "agrégale unos estilos más modernos y que tenga modo oscuro"
Sistema de tema nuevo (`ThemeContext`, sigue el tema del teléfono automáticamente vía
`useColorScheme` — cero librerías nuevas — con un toggle manual en el menú "Opciones" que se
recuerda). Aplicado a las 13 pantallas de Talento construidas en esta sesión. El encabezado se
queda teal de marca en ambos temas (como WhatsApp mantiene su verde en modo oscuro); el pad de
firma y el QR se quedan con fondo blanco fijo porque necesitan serlo para funcionar bien.

### Alcance
El modo oscuro se aplicó a las pantallas de Talento (todo lo construido en esta sesión) + Mi día.
Las pantallas más antiguas del flujo de campo (Evidencia, Cierre, detalle de OT, Mi semana, Login)
quedaron fuera de esta pasada — seguirían viéndose en modo claro aunque el teléfono esté en oscuro.
Si David quiere cobertura completa, es un siguiente paso acotado (mismo patrón, solo falta aplicarlo
a esas pantallas).

### Commits
Backend: `8e2d1901` + `2bdaf9e0` (rama `feature/talento-app-credenciales-certificacion-rutas-darkmode`),
merges `5766fdca` + `92cf525f` a `main`.
App: `45c6490` en `/home/meganet/TalentoEquipo` (rama `master`).

**APK:** `http://192.168.105.11/downloads/talento-v1.11.apk` — versión **1.11** (versionCode 111).
