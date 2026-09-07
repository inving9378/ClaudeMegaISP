# MR-19 Fase 2 — Frontend Carta de empalme + verificación de DoD con mufa real de Tultitlán (item #9990566)

**Fecha:** 2026-09-07

## 1. Frontend (pasos 1-3 del item) — ✅ ya implementado, verificado en esta vuelta

Una vuelta anterior (reanudada por timeout de turnos) ya dejó 3 commits en la rama del item:

- `151f682e` — helper `getCartaEmpalme` / `cartaEmpalmePdfUrl` en `empalmes-request.js` (mismo
  patrón de query params que `getEmpalmesExistentes`).
- `a231e89d` — `CartaEmpalmeDialog.vue` (nuevo, `components/others/`): tabla agrupada por bandeja,
  swatch de color (paleta EIA/TIA-598 en español → hex), botón "Exportar PDF" (`window.open` a la
  URL de descarga).
- `661da22b` — botón "Carta de empalme" en `EmpalmeConfigDialog.vue`, **un solo punto de cambio**
  que cubre las 3 pantallas (Mufa/NAP/Rack) porque las tres ya comparten ese dialog para
  `EmpalmesPanel.vue` — decisión correcta de minimalismo en vez de tocar
  `JunctionBoxConfiguration.vue`/`ServiceBoxConfiguration.vue`/`RackConfiguration.vue` una por una.

Verificación de esta vuelta:
- Rutas backend (`routes.php:28-29,154`) coinciden con el helper: `GET /mapa-red/api/empalmes/carta`
  y `GET /mapa-red/empalmes/carta-pdf`.
- Contrato de campos coincide: `EmpalmesController::filaDeEmpalme()` devuelve
  `hilo_a{numero,color,buffer,buffer_color,cable_id}` / `tipo` / `perdida_db` / `extremo_b`, y el
  dialog los consume tal cual (`hiloLabel`, `TIPO_LABELS`, `extremoLabel`, `colorHex`).
- `php -l` limpio en el controller tocado por la Fase 1.
- `bash deploy/circuito/npm-build.sh` → **compiló exitosamente** (`Mix: Compiled successfully`,
  exit 0; el único warning de webpack — `Conflicting values for '__VUE_OPTIONS_API__'` — es previo
  y no relacionado a este cambio).

## 2. Verificación de DoD con mufa real de Tultitlán — ❌ no hay ningún empalme real (ni de Tultitlán ni de ninguna zona)

```
mapared_empalmes:  0 filas
mapared_hilos:     0 filas
mapared_puertos:   0 filas
mapared_splitters: 0 filas
```

No es que falte capturar empalmes *de Tultitlán* — la tabla `mapared_empalmes` está **completamente
vacía** en dev hoy, para cualquier elemento contenedor de cualquier zona. Se localizaron los 8
proyectos/zonas cuyo nombre contiene "TULTITLAN" en `mapared_proyects` (ids 640, 712, 1496, 1956,
1957, 1962, 2033, 2088), pero ningún `mapared_layer` (mufa/NAP/rack) cuelga de esos ids con
empalmes registrados — de hecho ninguno en todo el sistema, porque la tabla de empalmes está en 0.

Esto es consistente con lo ya documentado para tablas hermanas del mismo subsistema (puertos/hilos/
splitters, ver comentario de `ValidarPresupuestoOpticoCommand.php:45` y el hallazgo paralelo de
`docs/mapared-mr16-fase2b-item-9990496-verificacion.md` sobre `mapared_enlaces_servicio`): el motor
de empalmes (Fase A/B del item #9990408, `EmpalmesController::store()`) es funcional y fue probado
en su momento con datos sintéticos que después se limpiaron, pero **nadie ha capturado empalmes
reales todavía** — es una operación manual desde la UI (Mufa/NAP/Rack → "Empalmes" → alta), no algo
que un import/backfill legado pueda poblar (los empalmes/fusiones no existen como concepto en el
sistema legado que `mapared:importar-legacy` espeja).

Tal como anticipa el propio spec del item ("Si no existen empalmes reales capturados aún en
Tultitlán, documentarlo como hallazgo — no bloquea el cierre de esta fase técnica, pero sí el DoD
completo del padre"): esta fase técnica (frontend) queda cerrada; el DoD visual completo del padre
(#955) sigue sin poder verificarse con datos reales hasta que alguien capture al menos un empalme
real vía la UI ya construida.

## Conclusión

- Frontend de MR-19 Fase 2: **completo y verificado** (build limpio, contrato de datos correcto,
  un solo punto de cambio para las 3 pantallas).
- DoD visual con datos reales de Tultitlán: **bloqueado por ausencia total de datos** de empalmes
  en dev (no es un bug de este item ni de la Fase 1) — hallazgo para el padre `#955`, vía el propio
  mecanismo de respuesta que el spec habilita.

**Siguiente paso real (fuera de alcance de este item):** capturar al menos un empalme real desde la
UI ya construida (Mufa/NAP/Rack → botón "Empalmes" del `EmpalmeConfigDialog`) para que un futuro
intento del DoD del padre tenga sobre qué verificar visualmente la carta.
