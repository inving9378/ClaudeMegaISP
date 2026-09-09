# Voz Mayorista

Plano de control **comercial y administrativo** del servicio de telefonía que Meganet
revende a sus clientes.

## Es un módulo de OPERADOR — no se activa en instalaciones de cliente

El código de MegaISP viaja completo a todas las instalaciones, pero este módulo solo
debe funcionar en la de **Meganet**. Administra el negocio mayorista: tarifas de
carrier, márgenes, inventario de DID, capacidad contratada y límites de consumo de
cada cliente de voz. Nada de eso tiene sentido —ni debe ser visible— en la
instalación arrendada de un cliente.

**No confundir con el módulo VoIP/PBX.** Son cosas distintas:

| | Voz Mayorista (este) | VoIP / PBX |
|---|---|---|
| Dónde vive | Instalación de Meganet | Instalación del cliente |
| Qué administra | El negocio: quién contrata qué, cuánto consume, cuánto deja | La telefonía interna: extensiones, IVR, colas, grabaciones |
| Prefijo de tablas | `voz_` | `voip_` |
| Prefijo de permisos | `voz.` | `voip.` |

## Cómo se hace cumplir

El manifiesto declara `"instance_role": "operador"`, y hay **tres barreras
independientes**:

1. **Ciclo de vida** — `ModuleLifecycleService::install()` rechaza la activación si
   `INSTANCE_ROLE` no es `operador`, con mensaje claro, y **no deja fila en
   `module_registry`**. Este gate no admite `--force`.
2. **Rutas** — todas pasan por el middleware `rol.instancia:operador`, que devuelve
   403 sin revelar qué hay del otro lado.
3. **Boot del provider** — si la instalación no es de operador, no se carga ni una
   ruta, ni una vista, ni una migración; y como el menú se arma desde los módulos
   activos, la entrada del sidebar tampoco aparece.

Son tres porque cada una cubre el hueco de la anterior. Si alguien marca `active = 1`
directamente en la base, saltándose el ciclo de vida, las otras dos siguen cerradas.

## Configuración de despliegue

En el `.env` de la instalación de **Meganet** (dev y producción):

```
INSTANCE_ROLE=operador
```

El `.env` no se commitea, así que **este paso es manual en cada despliegue**. Si falta,
el valor por defecto es `cliente` —el restrictivo— y el módulo simplemente no se
enciende. Ese es el modo de fallo correcto: de menos, nunca de más.

## Alcance

Este módulo **no ejecuta telefonía**. No habla con Asterisk, AMI, ARI ni con el
carrier: todo pasa por las interfaces `CarrierDriver`, `PbxDriver`, `RatingEngine`,
`RoutingPolicy` y `FraudDetector`. La conmutación vive en la PBX de cada cliente y el
tránsito en el borde de voz de Meganet, que son sprints posteriores.

## Prefijo de rutas: `/voz-mayorista`

No `/voz`: ese prefijo ya lo usa el módulo **Planes** (`addon-planes`, activo) para los
planes de servicio de voz que se le venden al suscriptor final —`voz/crear`,
`voz/table`, modelo `Voise`, permisos `plan_*_voz` y `client_service_voz`—. Son
dominios distintos: aquel vende un plan a un cliente, este administra el negocio
mayorista.

Los permisos `voz.` y las tablas `voz_` sí se conservan: ahí no hay colisión.
