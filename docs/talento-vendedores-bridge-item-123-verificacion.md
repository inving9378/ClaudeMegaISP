# Item #123 — "Talento: sin bridge entre CommissionRule/TransactionSeller y el motor" (VERIFICACIÓN — premisa incorrecta)

## Premisa del item

> El sistema de comisiones de vendedores (CommissionRule / TransactionSeller) corre en paralelo al
> motor Talento. No está duplicado pero no hay puente entre ambos. Decidir: migrar al motor único o
> construir bridge de sincronización.

## Hallazgo

La premisa **"no hay puente entre ambos"** es falsa al día de hoy: el puente de **solo lectura**
ya existe, está commiteado desde la Fase 8/9 de Talento (commit `52c50009`, feat(talento): Fase 8
dashboards + escalafón + War Room + Fase 9 embajadores) y está **cableado y en uso**, no es código
muerto:

1. **Vista admin** — `TalentoEmbajadoresController::sellerData()`
   (`app/Modules/Addons/Talento/Controllers/TalentoEmbajadoresController.php:78-113`), ruta
   `GET /talento/api/colaboradores/{id}/seller-data`, lee `sellers` + `transaction_sellers`
   (últimas 4 semanas, `COUNT`/`SUM` de `commission_amount`) y responde un resumen. La consume
   `TalentoEmbajadores.vue` en la pantalla `/talento/embajadores-colabs`: badge "Vendedor" +
   comisión de las últimas 4 semanas en la tabla, y detalle completo en el modal que abre el botón
   "Cargar detalle" (`resources/js/components/module/talento/TalentoEmbajadores.vue:54-60,124`).
2. **Portal de Colaborador** — `Support/Actor.php::seller()`
   (`app/Modules/Addons/Talento/Support/Actor.php:57-65`) resuelve la fila `sellers` por
   `user_id` una vez por request (memoizada) y alimenta las secciones "Mis prospectos"/"Mi panel"
   del sidebar del portal cuando el colaborador es vendedor (`Actor.php:102-103`).
3. **Ya documentado** en `docs/modulos/talento.md` (sección 4, "Consume"): *"Módulo Embajadores /
   Seller — `TalentoEmbajadoresController` y `Support/Actor.php` hacen cross-link solo-lectura
   colaborador↔embajador/vendedor (`Seller` model)."*

Es decir: el "bridge de sincronización" que pide el item, en su forma de **visibilidad cruzada
solo-lectura** (que un colaborador que también es vendedor se vea reflejado con sus datos de
`CommissionRule`/`TransactionSeller` desde Talento), **ya está resuelto**. No hay nada que
construir ahí.

## Lo que NO se tocó (y por qué)

La otra mitad de la pregunta del item — **"¿migrar el motor de comisiones de vendedores al motor
único de Talento (que las comisiones se paguen/liquiden vía `TalentoLedgerEntry` en vez de
`CommissionRule`/`TransactionSeller`)?"** — sigue **sin decidirse**, y así debe quedar: es una
decisión de arquitectura que toca directamente el cálculo y pago de comisiones reales de
vendedores (**frontera dura de dinero**), no un cambio mecánico/reversible. El propio Revisor
(#338) escaló el item por esta razón antes del triaje mecánico. Ningún ejecutor on-box debe tomar
esa decisión unilateralmente; si en el futuro se quiere una migración real, es una decisión de
Irving, no de este circuito.

`Actor.php` ya deja constancia de que el esquema vendedor/embajador es excluyente y que la
"conversión de esquema" (cuál aplica a cada colaborador) se construye en una fase futura (Fase E),
ajena a este item.

## Conclusión

Sin cambio de código — el puente de solo lectura pedido por el item ya existe, está en uso
(`/talento/embajadores-colabs`) y ya está documentado en `docs/modulos/talento.md`. La migración
completa del motor de comisiones queda **fuera de alcance**, registrada aquí para que no se pierda,
y pendiente de una decisión explícita de Irving si algún día se quiere retomar.
