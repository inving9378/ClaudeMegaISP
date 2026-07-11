# Configuración crítica de PRODUCCIÓN (NO versionada) — referencia anti-regresión

> **Propósito:** inventariar la configuración de entorno de PROD (`192.168.105.108` /
> `/var/www/ClaudeMegaISP`, BD `meganet_prod_claude`) que vive **fuera de git** y que, si
> se pierde en un redeploy, en una reinstalación de `.env`, o al recrear el usuario MySQL,
> **revive el bug de cobros #126** (aplicar pago falla en silencio / los jobs no corren).
>
> Este doc es **referencia**, no ejecuta nada y **no toca prod**. Derivado del cierre de
> #126 (roadmap). Los valores reales viven solo en el `.env` de prod (gitignored) y en la
> config del servidor — aquí solo van los requisitos y placeholders.

---

## 1. `.env` de PRODUCCIÓN — requisitos que NO se pueden perder

| Clave | Valor requerido | Por qué (si falta) |
|---|---|---|
| `QUEUE_CONNECTION` | **`sync`** | En prod los jobs deben ejecutarse **de inmediato**, no esperando a un worker. Si queda en `database`/`redis` sin worker vivo, **aplicar un pago no completa su cadena** (`PaymentClientJob` queda encolado) → el saldo no se abona → bug de cobros #126. |

> ⚠️ Al reinstalar/regenerar el `.env` de prod, **verificar explícitamente**
> `QUEUE_CONNECTION=sync`. Es el primer sospechoso si "los pagos no abonan saldo".

---

## 2. Usuario MySQL de PRODUCCIÓN — privilegios que NO se pueden perder

Los triggers de la BD corren al **aplicar un pago**. Si el usuario MySQL de la app no
tiene el privilegio **`TRIGGER`**, el `INSERT`/`UPDATE` que dispara el trigger **falla en
silencio** → el pago no se refleja → bug de cobros #126.

- **Privilegio crítico:** `TRIGGER` sobre la BD de prod.
- **Acción al migrar de servidor / recrear el usuario MySQL:** confirmar que el `GRANT`
  incluya `TRIGGER`. **Confirmar con Carlos el `GRANT` exacto** (usuario/host reales de
  prod) antes de aplicarlo. Forma esperada (placeholder — ajustar usuario/host reales):

  ```sql
  GRANT TRIGGER ON `<BD_PROD>`.* TO '<USUARIO_APP>'@'<HOST>';
  FLUSH PRIVILEGES;
  ```

> ⚠️ Un `mysqldump`/restore o un usuario recreado con `GRANT ALL` acotado que **omita
> `TRIGGER`** reintroduce el bug. Validar tras cualquier migración de BD:
> `SHOW GRANTS FOR '<USUARIO_APP>'@'<HOST>';` debe listar `TRIGGER`.

---

## 3. Checklist rápido tras redeploy / migración de servidor de PROD

1. [ ] `.env` de prod: `QUEUE_CONNECTION=sync`.
2. [ ] Usuario MySQL de prod tiene privilegio `TRIGGER` (`SHOW GRANTS`).
3. [ ] Prueba de humo: aplicar un pago de prueba y verificar que **abona saldo** (cadena
   de triggers + `PaymentClientJob` completa).

---

## Pendiente de confirmación

- **GRANT MySQL exacto** de prod (usuario/host/privilegios reales) → **confirmar con
  Carlos** y actualizar la sección 2 con la forma canónica (manteniendo solo placeholders
  para el nombre de usuario si se considera sensible).
