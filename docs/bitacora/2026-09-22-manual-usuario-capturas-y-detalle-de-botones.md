# Manual de usuario: sin etiqueta de rol visible + captura y explicación botón por botón

## 2026-09-22 14:00 — Ajuste de Irving sobre el manual entregado antes

Irving pidió 3 ajustes sobre el manual de Talento entregado en la vuelta anterior
(`docs/bitacora/2026-09-22-manual-usuario-visibilidad-por-rol-talento.md`):

1. No mostrar la etiqueta "Visible para: administración, etc." dentro del documento —
   la restricción debe aplicarse (cada rol solo ve lo suyo), pero sin anunciarlo en la
   lectura.
2. Agregar una captura de pantalla real de cada parte.
3. No solo describir para qué sirve cada pantalla — explicar qué hace cada botón y
   control de ella.

### 1. Quitar la etiqueta de "Visible para"

`manual.blade.php` ya no pinta la nota "Visible solo para: X, Y" al leer una sección —
esa lista de roles solo se ve (y se edita) dentro del modo edición, como herramienta de
quien administra el manual. El filtro real (qué sección llega a qué rol) sigue
aplicándose exactamente igual, solo que ahora es invisible para quien lee.

### 2 y 3. Capturas reales + explicación botón por botón

Se recorrieron con el navegador las 29 pantallas del capítulo Talento (23 del panel de
administración + 6 del Portal de Colaborador) y se tomó una captura real de cada una,
además de revisar en pantalla qué hace cada botón, pestaña y filtro antes de
describirlo. El contenido de las 30 secciones se reescribió completo: cada una ahora
trae su captura embebida y una lista puntual de qué hace cada control, no solo un
párrafo general.

**Dos correcciones de contenido que salieron al revisar las pantallas de verdad** (la
vuelta anterior las había descrito mal, sin haberlas abierto):
- "Embajadores" (`/talento/embajadores-colabs`) no es el programa de referidos — es una
  vista de solo lectura que muestra si un colaborador es TAMBIÉN cliente/embajador o
  vendedor en otros módulos, sin tocar esas tablas. Se renombró a "Colaboradores con
  roles múltiples".
- "Roadmap" (`/talento/roadmap`) no es la ruta de crecimiento de un colaborador — es la
  bitácora de construcción del propio módulo Talento (fases de desarrollo). Se dejó
  aclarado explícitamente en el texto para no confundirlo con "Niveles"/"Escalafón",
  que sí son la ruta de crecimiento real.

**Cuenta de prueba dedicada para las 4 pantallas del Portal que necesitan datos
propios** (Mi día, Mi dinero, Mi material, Mis prospectos): la cuenta admin no las
muestra con nada útil (no tiene perfil de colaborador). En vez de repetir el error de
la vuelta del barrido de errores (tocar la cuenta real de Brandon), se creó una cuenta
de prueba nueva y dedicada (`manual_demo_tecnico`, rol Técnico+Vendedor, con 2 órdenes
de trabajo, 2 artículos en custodia y una cuenta de vendedor de ejemplo) — no toca
ningún colaborador real, y queda disponible para futuras capturas de otros módulos.

**Dónde viven las capturas:** se guardaron primero en `storage/app/public/` (el disco
de uploads), pero esa carpeta está excluida de git — no habría viajado a producción.
Se movieron a `public/images/manual/talento/` (29 PNG), que sí es contenido versionado
y llega con cualquier deploy normal.

**PDF:** el export a PDF (`isRemoteEnabled`, mismo patrón que ya usa el recibo de caja
de Vendedores) ahora también trae las capturas embebidas, respetando la misma
visibilidad por rol que la pantalla.

### Verificación

- `php -l` limpio en los 3 archivos PHP tocados.
- Playwright: 0 notas "Visible para" en la lectura, 29 imágenes cargando sin roto
  ninguna, 0 errores de consola, PDF 200 con las capturas embebidas.
- Re-verificado con usuarios reales (sin tocar sus contraseñas, vía sesión de servidor):
  Irving ve las 30 secciones, un Técnico real ve 16 (las suyas), una Vendedora real ve
  8, un cliente queda bloqueado — mismo resultado que antes del reescritura de
  contenido, confirmando que el filtro por rol no se rompió.
