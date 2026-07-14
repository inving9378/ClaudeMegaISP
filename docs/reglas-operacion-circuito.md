# Reglas de operación del Circuito CC — ADN (estabilidad · minimalismo · balance)

> Principios de ARQUITECTURA que rigen a TODO agente del circuito (ejecutor, revisor, decisor).
> Vivo: se inyecta en los prompts. Editable por Irving. No relaja la frontera dura de seguridad/dinero/prod/negocio.

## 1. Estabilidad — protege lo que YA funciona
- Un cambio que toca **código funcional ya aprobado / en uso** exige **justificación fuerte** (bug real, riesgo concreto, requisito explícito). Sin ella, **no se toca**.
- **Preferir aditivo/reversible**: agrega al lado en vez de reescribir; deja el camino viejo intacto hasta probar el nuevo.
- Si el riesgo de **romper algo funcional** es alto (mucho blast radius, código compartido por muchos consumidores, difícil de verificar) → el revisor **marca y ESCALA** a Irving, no se auto-ejecuta.
- **NO reescribir lo que sirve solo por estética** ni por preferencia de estilo.

## 2. Minimalismo — escribe lo mínimo, limpio
- **Lo mínimo que resuelve** el item: sin capas, abstracciones, flags ni indirecciones que nadie pidió. Sin "por si acaso".
- **Sin saltos innecesarios**: no cambies de patrón/librería/estructura si el existente sirve. Conéctate a lo que ya hay (servicios compartidos únicos).
- **Quita código muerto** que estorbe en el área que tocas (imports sin uso, ramas inalcanzables, TODOs cumplidos) — acotado a lo que tocas, no barridos globales.
- El revisor **premia lo limpio y acotado** y **rechaza la sobre-ingeniería** (solución más grande que el problema).

## 3. Balance — dónde aplica cada uno
- **Minimalismo** manda sobre código **NUEVO** (no lo infles) y **MUERTO** (quítalo).
- **Estabilidad** manda sobre código **FUNCIONAL** (no lo toques sin razón fuerte).
- Cuando choquen: **estabilidad gana** sobre lo funcional; minimalismo gana sobre lo nuevo/muerto.
- Regla práctica: *"¿Este cambio es el más pequeño que resuelve el item sin tocar lo que ya funciona? Si toco algo funcional, ¿tengo justificación fuerte?"* Si la respuesta flaquea → achica el cambio o escala.

## 4. Avisar antes de cambiar una API con consumidores
- Antes de modificar o eliminar una función/endpoint/contrato **con consumidores conocidos**, verifica quién la usa (grep en `app/`, `resources/`, `routes/`, `config/`) y dilo en el commit/comentario — no rompas en silencio.
- Si el registro central de contratos (#308) ya está mergeado a main, consúltalo primero en vez de solo grep.
- Sin consumidores confirmados (grep en cero) → no es una API compartida; aplica minimalismo normal (§2), incluido borrar código muerto.

## Frontera dura (recordatorio, NO negociable)
Dinero / seguridad-permisos-auth / producción-`.env`-deploy / negocio-estrategia → **siempre a Irving**, aunque el cambio sea pequeño y limpio. Estas reglas de arquitectura afinan el CÓMO; no abren la frontera.
