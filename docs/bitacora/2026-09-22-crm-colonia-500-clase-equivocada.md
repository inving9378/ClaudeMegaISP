# Fix — Estado/Municipio/Colonia en CRM nunca cargaban (500), bug distinto al de ayer

## 2026-09-22 09:30 — Verificación del reporte de Irving (18-sep) antes de contestarle

### Contexto

Irving preguntó si lo que reportó el 18 de septiembre ("los menús se quedaban trabados al
cambiar de cliente o de prospecto de CRM sin recargar") seguía sin corregirse, dado que ayer
(21-sep) volví a arreglar algo con el mismo síntoma (Estado/Municipio/Colonia en la ficha del
cliente). Antes de contestarle, verifiqué a fondo qué cubrió cada fix.

### Lo que encontré

**El reporte del 18-sep SÍ se corrigió, y sigue corregido.** Ese día se arregló la misma familia
de bug en **8 componentes** de selects (estado del CRM, tipo de documento, y otros) — confirmado
en el historial de commits (`948475a9`+`c1f08690`, mergeados ese mismo día). Nada de eso se
rompió después; sigue funcionando.

**Lo que arreglé AYER (21-sep) fue un componente DIFERENTE, que nunca estuvo en esos 8.** El
combo "Estado + Municipio + Colonia" (usado en la dirección del cliente) vive en su propio
componente aparte, que **nunca fue tocado** por el fix del 18-sep — se quedó fuera por accidente.
Por eso, aunque Irving vio el reporte de "ya corregido" el 18-sep, la Colonia/Municipio del
cliente seguía trabándose: es un componente distinto al que sí se arregló ese día.

**Al verificar hoy con un prospecto real de CRM (no solo con clientes), encontré un TERCER bug,
separado de los dos anteriores:** en CRM, esos mismos campos (Estado/Municipio/Colonia) **nunca
cargaban en absoluto** — ni siquiera la primera vez que abrías un prospecto, sin necesidad de
cambiar de registro. La pantalla los pedía al servidor y el servidor respondía con un error 500.

**Causa:** el código que busca esos datos usa el "nombre" del modelo de datos como si fuera una
llave para saber a qué tabla preguntar. Para Clientes esa llave estaba escrita bien; para CRM (y
también para la información de la empresa) estaba escrita apuntando a la ruta vieja de antes de
una reorganización interna del código — así que nunca encontraba coincidencia y el servidor
tronaba. Corregido: ahora las tres rutas (Clientes, CRM, Empresa) usan la llave correcta.

### Verificación (datos reales, hoy)

Con dos clientes reales y dos prospectos de CRM reales, de colonias distintas cada uno: cargar el
primero, navegar sin recargar al segundo, y confirmar el dato — los 4 casos mostraron la colonia
correcta, sin ningún desplegable trabado ni error en consola (antes del fix, CRM daba 500 en los
4 intentos).

### Resumen para Irving

- Lo que reportaste el 18-sep: **corregido desde ese mismo día, sigue funcionando.**
- Lo de ayer (Estado/Municipio/Colonia del cliente al cambiar de registro): **era un componente
  aparte que se había quedado fuera del arreglo del 18-sep — ya corregido ayer.**
- Lo de hoy (Estado/Municipio/Colonia de CRM, que nunca cargaban): **bug nuevo, encontrado al
  verificar, ya corregido también.**

Los tres son componentes/causas distintas del mismo síntoma visual ("el campo no muestra lo que
debería"), no el mismo bug reapareciendo.
