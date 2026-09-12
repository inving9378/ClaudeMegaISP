# Item #9991001 — Seguimiento de la pregunta sin resolver de #9990968 (permisos torre.cola.ver / torre.salud.manage)

## Contexto

`#9990968` ("CIRC-09 Fase 1 — Baja completa de las pestañas Cola, Reporte y Salud del
entorno") se cerró con 1 pregunta estructurada (`q2`) sin `opcion_elegida`:

> ¿Qué hacer con los permisos Spatie asociados a esas pestañas (si existen)?

El generador automático de seguimientos (`jarvis:generarSeguimientoPreguntas`) creó
`#9991001` a partir de eso. Irving aprobó la pregunta con la opción cuya clave
(`RoadmapItem::claveOpcion()`, `substr(sha1(texto), 0, 16)`) es `92b8302de589aee6`, que
corresponde a la **Opción 2**:

> Dejar los permisos huérfanos en BD y solo quitar UI/rutas. Pro: cero riesgo sobre
> roles/permisos existentes; totalmente reversible. Contra: deja basura en el catálogo
> de permisos.

## Hallazgo — la decisión ya estaba tomada y aplicada antes de que se creara el seguimiento

Es el mismo patrón de carrera de timing documentado ya varias veces en `CLAUDE.md`
(#733/#741/#753/#9990003/#9990353/#9990658, etc.): el ejecutor de `#9990968` **sí tomó
la decisión** y la dejó en texto libre en su propio log, minutos antes del cierre:

> `[2026-09-11 20:27 · wt-2 · decision]` ... "Opcion recomendada de q2 (dejar permisos
> huerfanos en BD, solo tocar UI/rutas) tambien aplicada: no se re[voca nada]..."

pero **nunca actualizó el campo estructurado** `preguntas[1].opcion_elegida` de su propio
item (sigue `null` en `#9990968` hoy mismo, verificado por tinker). El generador de
seguimientos lee ese arreglo estructurado, no el texto libre del log, así que creó
`#9991001` sin ver que la pregunta ya tenía respuesta.

## Verificación de que la Opción 2 es exactamente lo que se aplicó

Merge de `#9990968` a `main`: commit `a86f7580d754baa4902b41081becc0e134121155`.

- `git show --stat` del merge: 12 archivos tocados (controllers, rutas, componentes
  `.vue`, `config/torre_salud.php`, `EnvironmentHealthService.php`). **Cero migraciones**
  nuevas — ninguna migración de revocación/borrado de permisos (lo que habría requerido
  la Opción 1).
- Los permisos `torre.cola.ver` (id=733) y `torre.salud.manage` (id=735) **siguen
  existiendo** en la tabla `permissions` (verificado con `Spatie\Permission\Models\Permission::where('name', ...)`).
- `grep -rn "torre.cola.ver|torre.salud.manage" app/ resources/ routes/ config/` solo
  encuentra: (a) las migraciones originales que los crearon, y (b)
  `CatalogoPermisosCircuito.php` (catálogo descriptivo usado en la UI de asignación de
  permisos, no un gate). **Ningún** controlador/ruta/gate los consume ya — las rutas y
  controladores que los usaban (`RoadmapController::torreCola()`, `saludEntorno()`,
  `saludReintentarFallidos()`, `saludRecalentarCaches()`) fueron borrados por el mismo
  merge.

Esto es exactamente la Opción 2: los permisos quedan huérfanos en BD (sin nada que
gatear), la UI y las rutas ya no existen. El "contra" aceptado explícitamente por Irving
al elegir esta opción — "deja basura en el catálogo de permisos" — es justo lo que se
observa en `CatalogoPermisosCircuito::PERMISOS`, que sigue describiendo `torre.cola.ver`
y `torre.salud.manage` como si las pantallas existieran. Eso es el costo ya aceptado de
la propia opción elegida, no un pendiente nuevo.

## Conclusión

No hay nada que implementar: la decisión que este item pedía tomar ya estaba tomada y
aplicada en `main` desde el cierre de `#9990968`, antes incluso de que `#9991001` se
generara. Se cierra dejando esta constancia escrita, sin cambio de código de aplicación.
