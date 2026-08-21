# Medición de la lista TRIAJE_C_PLAIN/WORD — con la válvula de contexto activa (#902)

> Generado automáticamente por `php artisan circuito:medir-valvula` el 2026-08-20 20:28:12. Read-only: no
> modifica la lista ni el clasificador. Re-ejecutar este comando pisa este archivo con la medición
> más reciente.

## Cobertura de la ventana

⚠️ La válvula solo tiene datos reales desde **2026-08-20 16:06:05** (pedida desde 2026-07-21 20:28:12) — la ventana con válvula activa es más corta que la solicitada; no hay 30 días completos todavía. Re-correr este comando más adelante para una cifra sobre ventana completa.

## Tasa de nivel C (antes/después)

| Momento | Tasa de nivel C | Base |
|---|---:|---|
| Antes del clasificador (histórico) | 72% | 163 items / 30d (doc. en `ValvulaContextoService`) |
| Tras los arreglos de patrones (boilerplate/negación/límite de palabra), SIN válvula | 52% | 163 items / 30d (misma medición, doc. en `ValvulaContextoService`) |
| **Con la válvula de contexto activa (esta medición)** | **17.8%** | 45 items triados en la ventana, 8 terminaron en C |

## Por término — disparos del keyword vs aflojes de la válvula

La válvula solo se invoca cuando el keyword ya marcó C, así que "disparos" = veces que ese término
encendió la frontera dura; "aflojos" = de esos, cuántos la válvula leyó como MENCIÓN (no acción) y
bajó a B/`pendiente_revision`. Un término que afloja casi siempre es ancho de más; uno que casi
nunca afloja está bien calibrado.

| Término | Disparos | Aflojos | Fallos válvula | % aflojo | Lectura |
|---|---:|---:|---:|---:|---|
| `auth` | 2 (muestra chica) | 2 | 0 | 100% | insuficiente para opinar |
| `despliegue` | 2 (muestra chica) | 0 | 0 | 0% | insuficiente para opinar |
| `factura` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `pago` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |
| `permiso` | 21 | 16 | 0 | 76.2% | mixto — no concluyente |
| `produccion` | 2 (muestra chica) | 1 | 0 | 50% | insuficiente para opinar |
| `rol` | 1 (muestra chica) | 1 | 0 | 100% | insuficiente para opinar |

Total de invocaciones de la válvula en la ventana: 30.

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