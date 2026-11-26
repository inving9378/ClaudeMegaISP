# 2026-11-25 — Campañas/Blaster: rediseño de la página de Campañas

## Pedido
Irving pidió pasar impeccable por la página de gestión de campañas («el diseño está horrible y no reutiliza los
datatables»). La crítica le dio 17/40. Pidió los 5 arreglos principales y «que quede bien profesional, bonito y
responsive». El ajuste de las píldoras en oscuro va **solo en Campañas**: la regla global de `dark_mode.scss` no se
tocó.

## Lo que cambió
1. **La lista se ve como el Dashboard de llamadas.** Incluye:
   - búsqueda por nombre o mensaje;
   - segmentos por estado con su conteo y filtro por tipo;
   - orden por columna (con `aria-sort`) y paginación de 10, 25, 50 o 100;
   - Exportar a Excel;
   - la vista guardada en la dirección, para que F5 la conserve.

   El `Datatable` compartido no se usó: pide HelperDatatable y su fila en `modules`, y no da tarjetas en celular.
   Para que se vea igual en tabla y en tarjeta, la página arma su propia lista.
2. **Estados en español, con ícono y texto:** Activa, En pausa, Borrador y Terminada. Se distinguen en claro y en
   oscuro.
3. **Activar dice antes a cuántos se les va a llamar.** El servicio nuevo es `CobranzaCampanaService::alcance()`, con
   la misma consulta que la carga, y la ruta es `GET campanas/{id}/alcance`. El diálogo muestra:
   - «N clientes recibirán la llamada», cuántos no tienen teléfono y, en cobranza, el adeudo total;
   - el mensaje, el horario y la voz;
   - el botón «Llamar a N clientes».

   Ya no hay `alert()` ni `confirm()`. Quien no tiene permiso no ve el botón: `puedeGestionar` y `puedeCortar` se
   pasan desde el controlador.
4. **Arriba van una cabecera y una banda de resumen:** activas, marcando ahora, por marcar (solo de campañas
   activas), contestadas hoy con su porcentaje y cuántos ya pagaron.
5. **Diálogos accesibles:** el componente nuevo es `CampanasDialogo.vue`.
   - Tiene `role="dialog"`, Esc cierra, el foco queda adentro y regresa al botón que lo abrió, y el fondo no se
     desplaza.
   - «Nueva campaña» sigue al 80% de la pantalla y tiene tres bloques: Mensaje, Cuándo llamar y Reintentos (plegado).
     Termina en «Siguiente: a quién llamar».
   - Se agregaron Duplicar campaña y Reanudar una pausada: ruta `POST campanas/{id}/reanudar`, que sigue con lo ya
     cargado.
   - El detalle es un panel a la derecha con las llamadas paginadas, filtro por estado, enlace a la ficha del cliente
     y «Escuchar las grabaciones».

## Responsive
- Los cortes de la lista miden el **espacio de la página** (container queries), no la pantalla: el menú lateral le
  quita 250 px.
- Con menos de 1020 px de espacio, cada campaña es una tarjeta. Es el caso de una laptop de 1280 con el menú abierto
  o de una tablet.
- El resumen queda en 5, 3+2 o 1+2+2 sin huecos: las líneas son el fondo asomando por un `gap` de 1 px.
- En celular los diálogos ocupan la pantalla y los controles miden 44 px.

## Cómo se probó
- **Servidor:** cada consulta nueva (data, kpis, alcance, reanudar) se probó llamándola directo. En test, el alcance
  de la cobranza da 2,173 clientes, y 7 sin teléfono.
- **Pantalla:** la sesión de test había caducado, así que se montó la página real del bundle en una página local:
  - Playwright sirvió los estáticos de `public/` y los datos reales de test (solo lecturas, mismas consultas del
    controlador).
  - Crear, pausar, reanudar y eliminar se contestaron simulados: no se escribió nada en la base.
  - Se revisó en 1440 claro y oscuro, en 1024 y en 390 claro y oscuro: sin desbordes a lo ancho y sin errores en
    consola.
  - Se probaron filtros, orden, búsqueda, Excel, el detalle con Esc y foco, Nueva (80% y campos con nombre), Activar
    con el alcance, Duplicar y Eliminar con confirmación.
- **Detalles que salieron en la revisión y se corrigieron:**
  - la tabla se salía del panel en 1440 con el menú;
  - el título «Cuándo llamar» quedaba sobre el borde de su caja;
  - la flecha de los selects tapaba el texto (y en oscuro salía repetida);
  - los segmentos se cortaban en celular;
  - el Excel y el enlace a grabaciones tomaban la fecha en UTC: después de las 6 p.m. ya era «mañana».
