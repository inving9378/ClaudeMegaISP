# Item #642 — `circuito:re-triage` sin invocador (RESUELTO — premisa parcialmente incorrecta + bloqueador confirmado, ya documentado en #808)

## Lo que decía el item

Que `circuito:re-triage` (freno del clasificador caduca solo / freno humano se resurfacea) no
aparece en `crontab -l` ni en `Kernel.php`, y que por eso los items bloqueados por **anti-bucle**
(`bloqueado_por_bucle=true`, ejemplos citados: #57 y #182) "quedan colgados indefinidamente".

## Primer hallazgo — anti-bucle y freno humano son DOS mecanismos distintos

`circuito:re-triage` no tiene ninguna relación con `bloqueado_por_bucle`. Verificado leyendo el
código:

- **Freno humano** (lo que opera `circuito:re-triage`) = `origen_bloqueo === 'humano'` o rótulo
  `[BLOCKED-…]`/`[PARKED-…]` en el título (`RoadmapItem::tieneFrenoHumano()`,
  `sqlConFrenoHumano()`).
- **Anti-bucle** (lo que tenían #57/#182) = columna aparte `bloqueado_por_bucle`, la sella
  `RoadmapItem::contarEscalacion()` tras 3+ escalaciones idénticas por la MISMA causa. Por diseño
  explícito (comentarios de los items #637 y #710 en `JarvisService.php`) **solo se destraba a
  mano en la Torre o con un cambio material** — nunca automático. `circuito:re-triage` ni siquiera
  consulta esa columna.

Verificado en la BD de dev (hoy): `#57` → `origen_bloqueo=null`, `bloqueado_por_bucle=true`,
`estado_aprobacion=completado`. `#182` → `origen_bloqueo=null`, `bloqueado_por_bucle=false`,
`estado_aprobacion=completado`. Ninguno de los dos ejemplos citados tenía freno humano — así que
aunque `circuito:re-triage` corriera cada minuto, no los habría tocado. Ambos, además, ya están
`completado` (se resolvieron por trabajo posterior, no por este item).

## Segundo hallazgo — la mitad de "freno humano se resurfacea" YA corre sola

`DigestCommand` (cron diario `40 6 * * * cron-wrap.sh circuito:digest`) llama **directo** al
método estático `RetriageFrenosCommand::frenosHumanos()` (líneas 144 y 347 de
`DigestCommand.php`) para armar su propio reporte de frenos vigentes. El recordatorio a Irving de
"aquí hay una decisión que sigues re-aprobando" ya sucede todos los días — no depende de que el
comando `circuito:re-triage` tenga cron propio.

Lo único que de verdad no corre solo es el otro carril del comando: `caducarClasificador()` con
`--apply`, que vence (pone `origen_bloqueo=null` + motivo) los consejos automáticos del
CLASIFICADOR sin confirmar en 14 días. Verificado con dry-run: hoy 22 vivos, 0 vencen. Es aditivo
y reversible — nunca toca el freno humano (`revocados por este comando: 0 (por diseño)`).

## Tercer hallazgo — el fix (agregar la línea al crontab) ya se investigó y está bloqueado, desde el item #808

`docs/bitacora-sesiones.md`, entrada `2026-08-18 18:35` (item #808, mismo hallazgo que este item
repite dos semanas después):

> `circuito:re-triage — NO ESTÁ AGENDADO en el crontab. Se pierde: el freno del CLASIFICADOR nunca
> caduca». El circuito tomó #808 y concluyó por su cuenta que no puede escribir el crontab del SO;
> deja la línea documentada.

Se repitió la comprobación en esta sesión (wt-3, 2026-08-29): un intento de escribir el crontab
del sistema (`crontab -`) fue **bloqueado por el clasificador de auto-mode de Claude Code**
("Blocked by classifier") antes de ejecutarse — confirmación empírica independiente de que este
ejecutor on-box no tiene permiso para modificar el crontab del SO, más allá de los permisos Unix
del usuario. Mismo resultado que #808, dos vías distintas de comprobarlo.

La otra alternativa que proponía el item ("o al schedule de Kernel.php") no sirve en este box: el
propio comentario del crontab actual lo dice — *"en este box NO hay `* * * * * php artisan
schedule:run`: las 11 líneas del circuito van directo por `cron-wrap.sh`"*. Sin `schedule:run`
corriendo, agregar algo al `schedule()` de `Kernel.php` no tendría ningún efecto aquí.

## Conclusión

No hay cambio de código posible desde este worktree: la escritura del crontab del sistema está
fuera del alcance del ejecutor on-box (confirmado dos veces, #808 y aquí). La línea que falta
queda documentada para que Irving la agregue a mano cuando quiera (mismo patrón que las demás,
`cron-wrap.sh`):

```
0 7 * * * /var/www/megaisp/deploy/circuito/cron-wrap.sh circuito:re-triage --apply >/dev/null 2>&1
```

(diaria a las 07:00, una hora después del digest de las 06:40, para que el digest reporte el
estado de los frenos del clasificador ANTES de que `--apply` venza alguno). Efecto: solo silencia
consejos del clasificador sin confirmar en 14 días — nunca toca el freno humano ni el anti-bucle.

Se cierra #642 sin rama de código funcional — mismo patrón que #733/#741/#745 (solo esta nota de
verificación) — porque la acción pendiente real es un cambio de infraestructura fuera del alcance
de este ejecutor, no una tarea de programación.
