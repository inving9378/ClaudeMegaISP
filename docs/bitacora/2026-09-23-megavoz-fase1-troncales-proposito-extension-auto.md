## 2026-09-23 18:30 — MegaVoz Fase 1: troncales con propósito + extensión automática por colaborador

Implementada la Fase 1 del plan MegaVoz (memoria `project_megavoz_plan_atencion_clientes.md`),
con un ajuste de diseño real encontrado durante la investigación previa (ver más abajo).

### Troncales con propósito

Columna aditiva y nullable `proposito` en `voip_troncales` (migración
`2026_09_23_180000_add_proposito_to_voip_troncales.php`), enum
`registro_ucm|saliente_cobranza|saliente_avisos|saliente_corte`. Las troncales que ya existen
quedan sin clasificar (null) — no rompe nada. `TroncalController::store()`/`update()` validan el
campo nuevo; `VoipTroncales.vue` trae el select en el modal + una etiqueta visible en la tabla
bajo el nombre de la troncal. Gateado por los permisos `voip.troncales.create`/`.edit` que ya
existían — no se creó ningún permiso nuevo, el campo vive dentro del mismo form ya protegido.

### Extensión SIP automática por colaborador

**Hallazgo antes de escribir código:** el plan original (escrito 22-sep) asumía un rango plano
"1001–1899" que no corresponde a nada real. El 10-sep (12 días antes) ya se había construido un
plan de numeración completo (item #9990718 §7/§8): 9 rangos con propósito
(oficinas/atención/técnicos/ventas/dirección/dispositivos/grupos/reserva/sistema) y 30 extensiones
genéricas ya pre-sembradas por departamento, esperando ser "reclamadas" desde la UI — NO un
esquema de "crear una nueva al momento del alta". Construir el plan tal cual habría duplicado un
sistema ya mejor construido.

**Diseño ajustado (aprobado por David):** `ReclamadorExtensionAutomatico` — al alta o activación de
un colaborador de Talento, si su rol es de atención directa, reclama la primera extensión libre
del rango que le toca (no crea ninguna), le pone su nombre real y le rota el secret genérico de
siembra por uno propio (`Str::random(32)`, cifrado con el mismo mutator `Crypt` que ya tenía el
modelo — no hubo que construir cifrado nuevo). Mapeo rol→rango, alcance decidido explícitamente
por David (solo atención directa, no todo `portal.colaborador`):

| Rol Spatie | Rango |
|---|---|
| TECNICO / TECNICO_INSTALADOR / TECNICO_PLANTA | `tecnicos` (1200-1299) |
| Vendedor | `ventas` (1300-1399) |
| Mostrador / SUPERVISOR_MOSTRADOR | `atencion` (1100-1199) |

Enganchado en `TalentoColaboradorObserver::created()` + `updated()` (cuando `status` pasa a
`active`), mismo patrón best-effort que `generarDocumentos()` — try/catch propio, un fallo nunca
bloquea el alta, solo queda en log. Idempotente: un colaborador que ya tiene extensión no reclama
una segunda. Si el rango se agota, no falla — solo loguea la advertencia para que un admin asigne
a mano desde Extensiones.

### Verificado

- `php -l` limpio en los 5 archivos PHP tocados/nuevos.
- Migración corrida en dev sin error (columna nullable, aditiva).
- **Flujo completo probado con una transacción de BD revertida al final** (sin tocar ningún dato
  real): (1) reclamo normal — colaborador TECNICO real (#2, JOSUE DAVID) reclamó la extensión 1201
  del rango "tecnicos" correctamente (nombre y `user_id` puestos bien); (2) idempotencia — un
  segundo reclamo sobre el mismo colaborador devolvió `null` sin duplicar; (3) rol fuera de
  alcance — un colaborador sin ninguno de los 6 roles de atención directa devolvió `null`
  correctamente. Los tres casos, `ROLLBACK` al final, cero filas persistidas.
- `npm run dev` compiló limpio con el campo `proposito` nuevo en `VoipTroncales.vue`.

### Rama y merge

Trabajado en `voip/fase1-troncales-proposito-y-extension-auto`, mergeado a `main` con permiso
explícito de David (el guardrail de migraciones —item #534— bloqueaba correr la migración porque
la rama no sigue el patrón `circuito/item-N` del circuito automático; esto es trabajo directo
fuera del circuito, así que se optó por mergear en vez de usar el escape hatch
`--force-uncommitted`).

### Pendiente / próximo paso

Validación visual de David en el navegador (`/voip/troncales` para el propósito nuevo). Con esto
cerrada, sigue la Fase 2 del plan (mini-teléfono WebRTC en MegaISP).
