# Renombra "Manual General de la Empresa" a "Manual Operativo de Meganet"

## 2026-09-22 15:00 — Aclaración de Irving antes de arrancar VoIP/MegaVoz

Antes de empezar con el plan de MegaVoz, Irving pidió un ajuste sobre el manual
entregado en las vueltas anteriores. En el sistema hay **dos manuales distintos**:

- `/manual` ("Manual de Usuario", módulo `addon-manual`) — documentación genérica
  auto-generada por Claude API, uno por módulo. **No se tocó, sigue igual.**
- `/empresa/manual` ("Manual General de la Empresa", módulo `addon-empresa`) — el que
  se editó en las vueltas anteriores: capítulos de identidad/misión/valores + el
  capítulo Talento (30 secciones con capturas y explicación botón por botón).

Se confirmó con Irving (pregunta directa) que el capítulo Talento ya estaba en el
lugar correcto — no hacía falta mover nada de un manual a otro. Lo único pedido: **el
nombre**. "Manual General de la Empresa" sonaba a manual de políticas corporativas;
Irving lo quiere como **"Manual Operativo de Meganet"**, para que quede claro que es
donde el personal aprende a operar el sistema día a día.

### Cambios (solo el nombre visible — mismo módulo, mismas tablas, misma ruta)

- `module.json` del addon: `name` + `description` + descripciones de los 5 permisos.
- Sidebar: el link suelto pasa de "Manual de la Empresa" a "Manual Operativo de
  Meganet".
- `manual.blade.php`: `<title>`, `<h1>`, migaja de pan ("Meganet › Manual Operativo"),
  pie de página.
- `manual_pdf.blade.php`: `<title>`, `<h1>`, pie de página.
- `EmpresaManualController::pdf()`: el PDF descargado ahora se llama
  `manual-operativo-meganet.pdf` (antes `manual-general-empresa.pdf`).
- Migración nueva (`2026_09_22_150000`) que actualiza el nombre ya sembrado en
  `module_registry` para el slug `addon-empresa` (la migración original que lo
  sembró ya corrió — no se edita una migración ya aplicada, se corrige con una
  nueva, con su `down()` que restaura el nombre viejo).

**La URL `/empresa/manual` no cambió** — solo el nombre visible del documento, no
la dirección donde se accede.

### Verificación
`php -l` limpio en los 4 archivos PHP tocados, `module.json` sigue siendo JSON válido,
migración corrida en dev — `module_registry.name` para `addon-empresa` confirmado en
"Manual Operativo de Meganet".
