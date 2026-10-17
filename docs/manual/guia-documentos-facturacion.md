# Guía rápida — Documentos de facturación (para mostrador)

> **Estado:** comprobado en el servidor de pruebas (dev) el 16/10/2026. En producción aparece
> **cuando se publique y se despliegue la versión que lo incluye** (items #480, #497, #498, #505).
> Hasta entonces, producción sigue como antes.

## Dónde

**Clientes › (abrir el cliente) › Facturación › Facturas.** Cada factura tiene sus botones al final
de la fila.

> El menú **Finanzas › Facturas** todavía abre la lista anterior y ahí **no** están estos botones
> (pendiente de decisión, #525). Usar la ficha del cliente.

## Un solo ícono de documento por factura

| La factura está… | Ícono (al pasar el mouse dice) | Qué abre |
|---|---|---|
| Sin pagar (emitida, enviada, pendiente o con abono) | 📄$ **Prefactura** | PDF que dice **PREFACTURA**, con el saldo a pagar, la fecha límite y los datos para pagar (banco, CLABE y referencia MEG). |
| Pagada | 📄 **Factura (sin valor fiscal)** | PDF que dice **FACTURA**, sello verde **PAGADA** y «Sin valor fiscal». |
| Cancelada | (sin ícono) | No hay documento ni botón «Enviar». |

- **No es factura fiscal (CFDI).** Si el cliente pide factura fiscal, el PDF le indica escribir a
  **facturacion@meganett.com.mx** con sus datos fiscales.
- La columna **Documento** de la tabla dice lo mismo: **Proforma** (ámbar) o **Factura s/v
  fiscal** (verde). Se puede filtrar por ella.

## Enviar (avión de papel)

**Enviar manda exactamente el mismo PDF que abre el ícono**, al correo que tiene el cliente en su
ficha. **Es un correo real al cliente**: revisar antes que el correo de la ficha sea el correcto.

- Factura sin pagar → pregunta «¿Enviar la prefactura?». Asunto: **Tu prefactura {periodo} — Meganet**.
- Factura **pagada** → pregunta «¿Enviar la factura pagada?» y avisa **«Esta factura ya está
  pagada; se enviará como factura PAGADA.»** Asunto: **Tu factura {periodo} (pagada) — Meganet**,
  con el PDF `Factura-{folio}-{fecha}.pdf`.
- Si hay duda, **Cancelar**: no se manda nada.
- Enviar ya **no** cambia una factura pagada a «Enviada».

## Otros cambios que se notan

- **Método de pago** muestra el nombre (por ejemplo «PAGO A TECNICO»), no un número. Las facturas
  marcadas como retroactivas dicen «Retroactiva»; sin método, «—».
- **Exportar** desde la ficha baja a Excel solo las facturas de ese cliente (incluye la columna
  «Tipo de factura», que ya no se ve en la tabla).
- Los correos del sistema llegan con su asunto real (ya no «Notificación»).

---

## WhatsApp para Diana, Alondra y Ariana (no enviado; lo manda Irving cuando se publique)

```
Cambio en Facturas (ficha del cliente › Facturación › Facturas):
• Un solo ícono de documento: sin pagar = PREFACTURA; pagada = FACTURA «PAGADA» (sin valor fiscal).
• Enviar manda ese mismo PDF al correo de la ficha; en pagadas pide confirmar. Es correo REAL al cliente.
• Cancelada = sin documento ni Enviar.
• Factura fiscal (CFDI): el cliente escribe a facturacion@meganett.com.mx.
Dudas, con Irving.
```
