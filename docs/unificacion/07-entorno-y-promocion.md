# 07 · Entorno y promoción a producción

## Cómo llega algo a producción [código]

```
 item del circuito ──► rama circuito/item-N ──► main LOCAL (MergeRunner, nunca hace push)
                                                    │
                       botón «+ Nueva versión» (permiso releases.emitir)
                                                    ▼
        DeploymentService::run: respaldo BD → tag → push a origin → GitHub Release   (main COMPLETO)
                                                    ▼
        producción consulta GitHub Releases / webhook → remote:deploy → migrate
```

- El circuito integra a `main` local y no empuja. Solo emitir una versión sube `main` completo.
- Por eso `main` **no es exclusiva de DEV**: lo integrado hoy sale en la próxima versión.
- `git_push` y `github_release` solo corren si la instancia es publicadora (`DEPLOY_IS_PUBLISHER`);
  `remote_deploy` se omite sin `DEPLOY_REMOTE_URL`.
- `remote:deploy-run-pending` corre cada minuto donde `updates.enabled=false` y procesa los
  `DeploymentLog` en `pending` creados por el webhook entrante.

## Webhook de despliegue de DEV [código]

`DeployWebhookController` es **receptor** (valida `X-Deploy-Token` y encola un log); no llama a nadie.
La única llamada saliente del pipeline es el paso `remote_deploy`, que usa `DEPLOY_REMOTE_URL`:
en DEV está vacío [DEV 2026-10-26], así que el paso se omite. DEV no apunta a producción.

## Regla del proyecto UNI-T

1. Los items se integran a `main` local como siempre y cada commit lleva `Proyecto-UNI: #<id>`.
2. Todo cambio de comportamiento va detrás de un interruptor de `config/unificacion.php`, apagado por defecto.
3. **Ninguna versión sale con trabajo del proyecto sin el procedimiento de versión parcial**: rama
   `release` desde el último tag + cherry-pick de lo que sí sale + publicar solo tag/rama.
4. Candado: `DeploymentService::candadoProyectoUni` detiene la emisión (antes de respaldo, tag o push)
   si entre el último tag y HEAD hay commits con `Proyecto-UNI:`. Se apaga solo con
   `UNIFICACION_CANDADO_PUBLICACION=false`. Excepción: usuario con el permiso
   `releases.excepcion_proyecto_uni` que manda `excepcion_proyecto_uni_motivo`; el motivo, el usuario y la
   hora quedan en el `payload` del `DeploymentLog` y en el log de aplicación.

## Interruptores

Convención y encendido en DEV: ver el encabezado de `config/unificacion.php`
(`UNIFICACION_<NOMBRE>=true` en el `.env` de DEV + `config:clear`; tras editar `.env`,
`ls -la .env` y `chgrp www-data .env`). Producción no se toca.
