# Verificación del clasificador AUTO/BANDEJA con datos reales — item #9990009

Torre 24/7 Pieza 4 — Fase 5 (real). Cadena de dependencias: #918 (criterio en
`config/circuito_hardening.php`) → #919 (paraguas, wiring real en su sub-item #9990008 →
#9990060) → #9990009 (esta verificación). Confirmado antes de tocar código: `merge_commit`
de #918 (`423123e0`) y de #9990060 (`58d6924c`) son ambos ancestros de `main`; el `merge_commit`
vacío de #919 se debe a que cerró por cascada como paraguas (el código real vive en la rama de
#9990060), no a que el trabajo falte — verificado con `git merge-base --is-ancestor` y grepeando
`CarrilSeguridad`/`config/circuito_hardening.php` ya presentes en `main`.

## Qué se corrió

`CarrilSeguridad::calcular()` (app/Modules/Addons/Roadmap/Support/CarrilSeguridad.php) es
**determinista** — no hace una segunda llamada a IA, solo evalúa `subcat`+`texto_brief` (ya
escritos por Opus) contra `config/circuito_hardening.php`. Eso permite verificarlo contra el
**100% del historial real** sin gastar una sola llamada nueva a la API: se tomaron los 108 items
del roadmap que ya tienen el sello `⟪SEG-TRIAGE⟫` de `PriorizarSeguridadCommand` (el 100% de la
población marcada a la fecha, no una submuestra), se extrajo de cada uno el bloque
`--- FIX + BRIEF DE RIESGO (Opus) ... ⟪SEG-TRIAGE⟫ ---` ya guardado en `comentarios_claude`
(categoría + subcat + brief tal como los escribió Opus en su momento), y se corrió cada uno por
`CarrilSeguridad::calcular()` en modo **lectura pura** (ningún `save()`, ningún `estado_aprobacion`
tocado, ningún item real modificado).

## Resultado — matriz completa (108/108)

| categoría | n | carril |
|---|---|---|
| seguridad | 14 | bandeja: **14** · auto: **0** |
| dinero | 7 | null (fuera de alcance del carril — `dinero` nunca pasa por AUTO, ver `PriorizarSeguridadCommand::aplicar()`) |
| negocio | 19 | null (fuera de alcance) |
| prod | 13 | null (fuera de alcance) |
| no_aplica | 55 | null (fuera de alcance) |

Los 14 items de categoría `seguridad` (los únicos donde el carril aplica):

| # | subcat | carril | naturaleza (inspección manual del brief completo) |
|---|---|---|---|
| #46 | secrets | bandeja | credenciales reales de Meta (App Secret, tokens long-lived) |
| #72 | secrets | bandeja | credenciales Firebase (service account JSON, OAuth2) |
| #101 | secrets | bandeja | credenciales Firebase FCM (server key, tokens de dispositivo) |
| #143 | webhook | bandeja | webhook OpenPay forjable sin HMAC/firma |
| #179 | passwords | bandeja | contraseñas en base64 reversible en cuentas privilegiadas |
| #199 | pii_datos_sensibles | bandeja | expediente RH con PII (CURP/RFC/NSS/salario) |
| #203 | idor_pii | bandeja | documentos PII servidos sin gate de acceso |
| #272 | roles_privilegio | bandeja | rol `SUPER_ADMIN` inexistente en lookup de Payments |
| #274 | roles_privilegio | bandeja | reactivar endpoints de gestión de permisos de usuario |
| #285 | roles_privilegio | bandeja | permiso declarado que no existe en la tabla Spatie |
| #625 | auth_otp | bandeja | login con toggle OTP sin flujo real (2FA roto) |
| #638 | auth_movil | bandeja | login/vinculación de sesión móvil padre↔hijo |
| #732 | roles_privilegio | bandeja | exportación de PII financiera sin gate específico |
| #756 | auth_bypass | bandeja | bypass real de aprobación en `elegibleAutoMerge()` |

**0/14 = 0% de falsos AUTO.** Los 14 son, sin excepción, hallazgos de frontera dura reales
(credenciales, permisos/roles, auth, PII, webhook forjable) — ninguno es en realidad hardening
trivial mal encasillado. Cumple con margen el umbral de **0% tolerancia** exigido por el item
(q2, opción aprobada por Irving) para la frontera dura.

Adicional: se buscó cada uno de los 6 `auto_terminos` de `config/circuito_hardening.php`
(`sanitizar_validar_entrada`, `parametrizar_queries`, `escapar_salida`, `headers_seguridad`,
`guards_null`, `bump_dependencia_cve`) como substring literal en título+subcat+brief de los 108
items completos (no solo los 14 de seguridad): **0 apariciones en todo el corpus real.**

## Hallazgo sobre el segundo criterio de aceptación (≥90% en "hardening trivial")

El item pedía que la muestra cubriera **dos** tipos: (a) frontera dura real → bandeja, y
(b) hardening trivial (guard de null, sanitizar input, escapar salida, headers, bump de
dependencia CVE) que hoy cae en bandeja solo por no existir el carril, pero que con el criterio
de #918 debería dar AUTO. **El corpus real de 108 items no contiene NINGÚN caso del tipo (b)** —
las 14 clasificaciones `seguridad` reales son 100% del tipo (a).

Esto no es una falla de la muestra ni algo que se pueda corregir ampliándola: es estructural.
`RevisorService::briefarSeguridad()` (líneas 999-1007) define la categoría `seguridad` para Opus
explícitamente como *vulnerabilidad real* (auth bypass, roles/privilegio, IDOR/PII, secrets,
webhook forjable, passwords) — el prompt nunca instruye a Opus a meter ahí un "falta guard de
null" o "falta sanitizar un input" genérico; eso cae naturalmente en `no_aplica` (55/108 items,
la categoría más grande del corpus) porque el propio clasificador de categoría no lo considera
seguridad. Sumado a que `auto_terminos` son etiquetas técnicas en snake_case
(`sanitizar_validar_entrada`) que el prosa natural de Opus jamás reproduce literalmente (0
apariciones confirmadas arriba), el carril=auto no tiene, hoy, ningún camino de entrada real —
ni siquiera hipotético — con el pipeline tal como existe.

Esto **confirma con datos reales** (no solo la sospecha documentada en el propio #9990060 al
implementarlo: *"si el pool de pruebas nunca cae en auto porque el brief no usa esas palabras
exactas, es una señal real"*) que el criterio (b) es **hoy no ejercitable**, no que esté mal
calibrado. Siguiendo la instrucción explícita de #9990060 para este escenario, la resolución es
el default seguro: dejarlo en bandeja. No es una regresión — es exactamente el arranque
conservador que pidió Irving en #918/#919 (lista corta y conservadora, "no ampliar sin ese mismo
nivel de revisión"). Decisión registrada vía `circuito:reportar --tipo=decision` en el item.

## Veredicto de la Fase 5

- **Criterio de frontera dura (0% tolerancia a falso AUTO): CUMPLE — 0/14, verificado con el
  100% del historial real, no una muestra parcial.**
- **Criterio de hardening trivial (≥90% de acuerdo): no aplicable — el corpus real no contiene
  casos de ese tipo (hallazgo documentado arriba, no un fallo del carril).**
- No se encontró ningún falso AUTO → no aplica el punto 5 del spec (bloquear/endurecer
  `auto_terminos`).
- El carril AUTO queda confirmado como **seguro pero de throughput ~0** con el vocabulario
  actual — coherente con el diseño conservador aprobado. Ampliar `auto_terminos` (o alinear su
  vocabulario con el que Opus realmente escribe) es una decisión de Irving a futuro, fuera de
  alcance de esta verificación.

## Reproducibilidad

Script de una sola pasada (solo lectura, sin tocar ningún item real):

```
php artisan tinker --execute="$(cat /tmp/carril_verify.php)"
```

Toma todo item con `comentarios_claude LIKE '%SEG-TRIAGE%'`, reconstruye `{categoria, subcat,
texto_brief}` del último sello guardado, y corre `CarrilSeguridad::calcular()` contra
`config('circuito_hardening')`. El script vive en `/tmp` (no committeado — es una herramienta de
verificación puntual, no parte del producto; si se quiere repetible en el futuro, promoverlo a un
comando Artisan es una mejora aparte, no requerida por este item).
