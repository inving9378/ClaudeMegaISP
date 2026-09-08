# Medición de la lista TRIAJE_C_PLAIN/WORD — con la válvula de contexto activa (#902)

> Generado automáticamente por `php artisan circuito:medir-valvula` el 2026-08-27 14:34:05. Read-only: no
> modifica la lista ni el clasificador. Re-ejecutar este comando pisa este archivo con la medición
> más reciente.

## Cobertura de la ventana

⚠️ La válvula solo tiene datos reales desde **2026-08-25 16:50:53** (pedida desde 2026-07-28 14:34:05) — la ventana con válvula activa es más corta que la solicitada; no hay 30 días completos todavía. Re-correr este comando más adelante para una cifra sobre ventana completa.

## Tasa de nivel C (antes/después)

| Momento | Tasa de nivel C | Base |
|---|---:|---|
| Antes del clasificador (histórico) | 72% | 163 items / 30d (doc. en `ValvulaContextoService`) |
| Tras los arreglos de patrones (boilerplate/negación/límite de palabra), SIN válvula | 52% | 163 items / 30d (misma medición, doc. en `ValvulaContextoService`) |
| **Con la válvula de contexto activa (esta medición)** | **21.1%** | 123 items triados en la ventana, 26 terminaron en C |

## Por término — disparos del keyword vs aflojes de la válvula

La válvula solo se invoca cuando el keyword ya marcó C, así que "disparos" = veces que ese término
encendió la frontera dura; "aflojos" = de esos, cuántos la válvula leyó como MENCIÓN (no acción) y
bajó a B/`pendiente_revision`. Un término que afloja casi siempre es ancho de más; uno que casi
nunca afloja está bien calibrado.

| Término | Disparos | Aflojos | Fallos válvula | % aflojo | Lectura |
|---|---:|---:|---:|---:|---|
| `cobro` | 5 | 3 | 0 | 60% | mixto — no concluyente |
| `deploy` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `destructiv` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `dinero` | 3 (muestra chica) | 2 | 0 | 66.7% | insuficiente para opinar |
| `factura` | 7 | 5 | 0 | 71.4% | mixto — no concluyente |
| `login` | 4 (muestra chica) | 3 | 0 | 75% | insuficiente para opinar |
| `migración destructiva` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `pago` | 10 | 4 | 0 | 40% | mixto — no concluyente |
| `password` | 1 (muestra chica) | 0 | 0 | 0% | insuficiente para opinar |
| `permiso` | 12 | 6 | 0 | 50% | mixto — no concluyente |
| `prod` | 3 (muestra chica) | 3 | 0 | 100% | insuficiente para opinar |
| `produccion` | 1 (muestra chica) | 0 | 0 | 0% | insuficiente para opinar |
| `producción` | 11 | 5 | 0 | 45.5% | mixto — no concluyente |
| `rol` | 2 (muestra chica) | 2 | 0 | 100% | insuficiente para opinar |
| `roles` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `spatie` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |

Total de invocaciones de la válvula en la ventana: 64.

## Recomendación (dato, no decisión — el podar/ampliar/dejar es de Irving)

- Los términos marcados **"candidato a acotar"** arriba son los que, con muestra suficiente
  (≥5 disparos), la válvula terminó leyendo como mención casi siempre: son los primeros a revisar
  si se decide acotar la lista.
- Los marcados **"bien ancho"** están cumpliendo su función: cuando disparan, casi siempre es
  porque el trabajo SÍ toca el tema.
- Los de **muestra chica** no alcanzan para concluir nada todavía — necesitan más ventana, no un
  cambio ahora.
- **No se poda nada en esta pasada.** Esa decisión es la pregunta `q2` del item #902 y está marcada
  `requiere_irving` en su propio brief — este reporte es el insumo, no el veredicto.

## Listas medidas (referencia)

- `TRIAJE_C_PLAIN` (substring, 37 términos): dinero, cobro, cobros, pago, pagos, factura, facturación, facturacion, medussa, cfdi, facturama, fiscal, timbrado, producción, produccion, deploy, despliegue, remote:deploy, permiso, permisos, spatie, login, password, passwords, contraseña, contrasena, credencial, bcrypt, migración destructiva, migrate:fresh, migrate:refresh, migrate:reset, truncate, delete from, drop table, drop column, destructiv
- `TRIAJE_C_WORD` (palabra completa, 6 términos): iva, rol, roles, auth, sat, prod