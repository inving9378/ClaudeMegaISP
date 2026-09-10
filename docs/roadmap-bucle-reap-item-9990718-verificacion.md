# Item #9990718 — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón documentado en CLAUDE.md para #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/
#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650. #9990718
(provisionador del módulo VoIP: que la actualización deje Asterisk instalado y funcionando) llegó
tras escalarse por DES-TRABE (Opus, ANTI-LOOP) y ser reaprobado por Irving. Una vuelta previa
(`wt-2`, 2026-09-10 15:39) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE,
`ya_timeouteo_antes`) y descompuso el trabajo en 7 sub-items siguiendo las propias secciones del
padre — **#9990722** (Fase 1, manifiesto `requisitos-voip.php`), **#9990723** (Fase 2, correcciones
mínimas del módulo, sec.6), **#9990724** (Fase 3, plan de numeración/perfiles, sec.7), **#9990725**
(Fase 4, extensiones sembradas por departamento, sec.8), **#9990726** (Fase 5, plantillas de
configuración, sec.5), **#9990727** (Fase 6, el provisionador + especificación de compilación,
secs.3-4) y **#9990728** (Fase 7, prueba E2E de punta a punta + cierre del paraguas, incluye quitar
el freno de #9990715). Pero esa vuelta murió sin intentar **cerrar** al padre — el log solo registra
`soltar-claim` ("terminó sin cerrar el item... muerte del proceso"), y el pool lo repartió de nuevo
sin trabajo propio que hacer. Verificado esta vuelta: los 7 hijos (`origen_item_id=9990718`) siguen
intactos, sin reclamar (`worker_sid` vacío), ninguno mergeado — la descomposición original seguía
siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante;
el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 7 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando los 7 sub-items cierren. **Sin cambio de código
de negocio** — el trabajo técnico real del provisionador VoIP sigue en
#9990722-#9990728, pendientes de aprobación/reclamo.
