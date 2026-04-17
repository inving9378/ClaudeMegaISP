
# Plan de Pruebas Unitarias para Clientes

## 1. Crear una Base de Datos

Crear una base de datos similar a la que tenemos en producción, pero solo con los tipos de clientes que vamos a usar.

### 1.1 ¿Cómo se va a Hacer?

1. **Crear un archivo SQL**  
   - Incluir todas las tablas vacías, excepto las que son de configuración y permisos. ✅  

2. **Eliminar migraciones existentes**  
   - Eliminar todas las migraciones actuales.  
   - Crear una nueva migración que ejecute el archivo SQL anterior.  

3. **Actualizar la tabla de migraciones**  
   - Agregar la nueva migración a la tabla de migraciones.

---

### 1.2 Creación de una copia para pruebas

- Una vez que se tenga la base de datos inicial configurada, se debe crear una copia para realizar las pruebas correspondientes.


### Tipos de Clientes
- **Cliente Recurrente**
- **Cliente Prepagos Personalizados**
- **Clientes Prepagos Diarios** (Este no se usará por el momento)

## 2. Tipos de Cliente
A cada cliente hay que crear distintas condiciones:

### Clientes Recurrentes
1. **Cliente Recurrente Activo**: No ha llegado su fecha de pago ni de corte (Pago ideal).
2. **Cliente Recurrente Activo**: Su fecha de pago pasó, pero no ha llegado su fecha de corte (Debe estar en periodo de gracia y saldo negativo).
3. **Cliente Recurrente Bloqueado**: Debe estar en periodo de gracia y saldo negativo.

### Clientes Personalizados
4. **Cliente Personalizado Activo**.
5. **Cliente Personalizado Bloqueado**.

## 3. Pruebas a Realizar
Las pruebas se realizarán según el tipo de cliente:

# Resultados Esperados
## **1. Cliente Recurrente Activo - Pago que cubre el monto de todos los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-03 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-04 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-03 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-04 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El estado del cliente debe permanecer como **Activo**.  
- No debe existir un balance pendiente después del pago.  
- Las fechas de pago y corte deben alinearse correctamente con las fechas esperadas.  

### 📝 **Notas Adicionales**
- Este caso representa el comportamiento ideal de un cliente recurrente activo con pagos al día.  
- Asegurarse de validar la sincronización entre las fechas de corte y pago esperadas.  

---
### **2. Cliente Recurrente Activo - Pago que no cubre el costo de los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `200`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `200`                   | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-03 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-04 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-03 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-01-04 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El estado del cliente debe permanecer como **Activo**.  
- El balance esperado debe reflejar el monto pendiente (`200`).  
- Las fechas de pago y corte deben coincidir con las fechas esperadas.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente recurrente activo que realizó un pago parcial, dejando un saldo pendiente.  
- Asegurarse de que las alertas y notificaciones de pago incompleto estén activas.  

### **3. Cliente Recurrente Activo (Periodo de gracia) - Pago que cubre el monto de los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `3`                     | Días hasta la expiración de la facturación. |
| **amount**                | `522`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `2`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2024-12-29 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2024-12-30 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-01-29 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El estado del cliente debe permanecer como **Activo**.  
- El cliente está en un **Periodo de gracia**, pero el pago cubre el monto de los servicios.  
- El balance esperado debe reflejar un saldo final de `2`.  
- Las fechas de pago y corte deben coincidir con las fechas esperadas.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente recurrente activo que realizó un pago completo dentro del periodo de gracia.  
- Validar que el sistema extienda correctamente las fechas de pago y corte esperadas.  

### **4. Cliente Recurrente Activo (Periodo de gracia) - Pago que NO cubre el monto de los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `3`                     | Días hasta la expiración de la facturación. |
| **amount**                | `300`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `-220`                  | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2024-12-29 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2024-12-30 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2024-12-29 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El estado del cliente debe permanecer como **Activo** debido al periodo de gracia.  
- El cliente mantiene un saldo pendiente de `-220`.  
- Las fechas de pago y corte deben alinearse con las fechas esperadas.  
- Se deben activar las notificaciones para el saldo pendiente.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente recurrente activo que realizó un pago parcial durante el periodo de gracia, dejando un saldo pendiente.  
- Validar que el sistema gestione correctamente el saldo pendiente y las alertas correspondientes.  


### **5. Cliente Recurrente Bloqueado - Pago que cubre el costo de los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `3`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-10 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-01-29 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe cambiar su estado de **Bloqueado** a **Activo** después de realizar el pago completo.  
- El saldo pendiente debe ser eliminado (`0`).  
- Las fechas de pago y corte deben alinearse correctamente con las fechas esperadas.  
- El sistema debe levantar cualquier restricción relacionada con el bloqueo.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente bloqueado que ha realizado un pago completo para cubrir sus servicios.  
- Validar que el sistema actualice correctamente el estado y desbloquee las funcionalidades restringidas.  
- Asegurarse de que las fechas de corte y pago se ajusten tras el desbloqueo.  


### **6. Cliente Recurrente Bloqueado - Pago que NO cubre el costo de los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `3`                     | Días hasta la expiración de la facturación. |
| **amount**                | `300`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `-220`                  | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-10 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `null`                  | No hay fecha de corte esperada.            |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente **debe permanecer bloqueado** debido al pago incompleto.  
- El saldo pendiente debe reflejar correctamente el monto restante (`-220`).  
- Las fechas de pago y corte deben alinearse con el estado de bloqueo actual.  
- El sistema debe mostrar notificaciones y alertas sobre el saldo pendiente.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente bloqueado que realizó un pago parcial, sin cubrir el monto total de los servicios.  
- Validar que el sistema no desbloquee funcionalidades hasta que el saldo pendiente sea completamente cubierto.  
- Asegurarse de que las alertas y notificaciones de saldo pendiente estén activas.  

### **7. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 27, 1 vez)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-28 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-27 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo** al realizar el pago un día después de la fecha límite, dentro del periodo de gracia.  
- El balance debe reflejar un saldo de `0`, indicando que el pago cubrió el monto total.  
- Las fechas de pago y corte deben alinearse con las fechas esperadas.  
- El periodo de gracia debe considerarse válido para esta operación.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente que realizó el pago un día después de la fecha límite pero dentro del periodo de gracia.  
- Validar que el sistema no aplique penalizaciones o bloqueos innecesarios.  
- Asegurarse de que las fechas de corte y pago se actualicen correctamente para el siguiente ciclo.  

### **8. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 27, 2 veces)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-28 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-27 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo** tras realizar el segundo pago.  
- El saldo debe reflejar un valor de `0`, indicando que se ha cubierto el monto total.  
- Las fechas de pago y corte deben actualizarse correctamente para el siguiente ciclo.  
- No se deben aplicar penalizaciones adicionales.

### 📝 **Notas Adicionales**
- Este caso representa a un cliente que realizó dos pagos consecutivos en días posteriores al vencimiento.  
- Validar que el sistema actualice las fechas correctamente y no genere duplicados de transacciones.  

---

### **9. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 29, 1 vez)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-29 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe permanecer **Bloqueado** debido al retraso en el pago.  
- El saldo debe reflejar un valor de `0`.  
- Las fechas de pago y corte deben alinearse correctamente.  
- Validar que el sistema gestione adecuadamente las restricciones por el bloqueo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó el pago fuera del periodo de gracia.  
- Asegurar que no se desbloqueen funcionalidades automáticamente.  

---

### **10. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 29, 2 veces)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-29 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe **permanecer bloqueado** a pesar de los pagos realizados fuera del periodo de gracia.  
- El saldo debe quedar en `0`.  
- Las fechas deben reflejar correctamente el nuevo ciclo de facturación.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó dos pagos consecutivos fuera del periodo de gracia.  
- Validar que el sistema no levante automáticamente el bloqueo.  

### **11. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 5 del siguiente mes, 1 vez)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-02-05 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe permanecer **Bloqueado** debido al retraso significativo en el pago.  
- El saldo debe reflejar `0` después del pago.  
- Las fechas de pago y corte deben ajustarse al nuevo ciclo correctamente.  

### 📝 **Notas Adicionales**
- Este caso representa a un cliente bloqueado que realizó el pago después de una fecha considerablemente tardía.  
- Validar que el sistema no desbloquee automáticamente las restricciones.  

---

### **12. Cliente - Fecha de pago 26, fecha de corte 2 días después (Prueba 1: Pago el día 5 del siguiente mes, 2 veces)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `2`                     | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-02-05 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-28 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe permanecer en estado **Bloqueado**, aunque haya realizado dos pagos.  
- El saldo debe reflejar `0`.  
- Las fechas de pago y corte deben actualizarse correctamente.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó dos pagos después de la fecha límite.  
- Asegurarse de que el sistema no desbloquee automáticamente las restricciones y que las fechas se actualicen correctamente.  

---

### **13. Cliente - Fecha de pago 26, fecha de corte 10 días después (Prueba 1: Pago el día 28, 1 vez)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `10`                    | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-02-06 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-28 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-08 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo** tras realizar el pago dentro del periodo de gracia.  
- El saldo debe reflejar `0`.  
- Las fechas de pago y corte deben actualizarse correctamente al siguiente ciclo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente activo que realizó un pago dentro del periodo de gracia definido por la fecha de corte extendida.  
- Asegurarse de que no se apliquen penalizaciones ni bloqueos innecesarios.  

### **14. Cliente - Fecha de pago 26, fecha de corte 10 días después (Prueba 1: Pago el día 28, 2 veces)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `10`                    | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-02-06 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-28 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-04-05 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo** después de realizar dos pagos consecutivos en periodo de gracia.  
- El saldo debe reflejar `0`.  
- Las fechas de pago y corte deben actualizarse correctamente al siguiente ciclo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente que realizó dos pagos dentro del periodo de gracia, asegurando que no haya penalizaciones ni bloqueos.  
- Validar que las fechas de pago y corte se actualicen correctamente para el siguiente ciclo.  

---

### **15. Cliente - Fecha de pago 26, fecha de corte 10 días después (Prueba 1: Pago el día 7, 1 vez)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `10`                    | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-02-07 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-08 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe permanecer **Bloqueado** debido a que el pago fue realizado después del periodo de gracia.  
- El saldo debe reflejar un valor de `0`.  
- Las fechas de pago y corte deben ajustarse correctamente al nuevo ciclo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó un único pago después del periodo de gracia.  
- Validar que el estado **Bloqueado** persista y que las fechas de corte se ajusten correctamente.  

---

### **16. Cliente - Fecha de pago 26, fecha de corte 10 días después (Prueba 1: Pago el día 7, 2 veces)**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2024-11-26 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `10`                    | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `-520`                  | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-02-26 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2025-01-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-02-07 10:00:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-26 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-04-05 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `true`                  | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe permanecer **Bloqueado** debido a los pagos realizados fuera del periodo de gracia.  
- El saldo debe reflejar `0`.  
- Las fechas de pago y corte deben alinearse correctamente al nuevo ciclo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó dos pagos después del periodo de gracia.  
- Validar que el estado **Bloqueado** se mantenga hasta que un administrador intervenga manualmente.  

### **17. Cliente Custom Activo - Pago que cubre el monto de todos los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-03 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-04 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-03 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-04 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo** tras realizar el pago que cubre el monto total de los servicios.  
- El saldo debe reflejar un valor de `0`.  
- Las fechas de pago y corte deben alinearse correctamente con las fechas esperadas.  
- No deben existir restricciones ni alertas adicionales.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente **Custom Activo** que realizó un pago completo dentro del periodo esperado.  
- Validar que las fechas se actualicen correctamente para el siguiente ciclo de facturación.  
- Asegurarse de que no existan discrepancias en el balance.  

### **18. Cliente Custom Activo - Pago que NO cubre el monto de todos los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `200`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `200`                   | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-03 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-04 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-01-03 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-01-04 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo**, pero con un saldo pendiente de `200`.  
- Las fechas de pago y corte deben reflejar el ciclo actual.  
- El sistema debe mostrar alertas sobre el saldo pendiente.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente **Custom Activo** que realizó un pago parcial.  
- Validar que el sistema gestione adecuadamente las alertas por pago incompleto.  

---

### **19. Cliente Custom Bloqueado - Pago que cubre el monto de todos los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `null`                  | No hay fecha de pago debido al bloqueo.    |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-02 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-03 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe cambiar su estado de **Bloqueado** a **Activo** después del pago completo.  
- El saldo debe reflejar `0`.  
- Las fechas de pago y corte deben actualizarse para el siguiente ciclo.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó un pago completo.  
- Validar que el sistema desbloquee correctamente al cliente después del pago.  

---

### **20. Cliente Custom Bloqueado - Pago que NO cubre el monto de todos los servicios**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `200`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `200`                   | Balance esperado después del pago.         |
| **fecha_pago**            | `null`                  | No hay fecha de pago debido al bloqueo.    |
| **fecha_corte**           | `null`                  | No hay fecha de corte debido al bloqueo.   |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-03 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `null`                  | No hay próxima fecha de pago esperada.     |
| **fecha_corte_esperada**  | `null`                  | No hay próxima fecha de corte esperada.    |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe **permanecer bloqueado** debido al pago incompleto.  
- El saldo debe reflejar un monto pendiente de `200`.  
- Las fechas de pago y corte no deben actualizarse.  
- El sistema debe mantener las restricciones de acceso.  

### 📝 **Notas Adicionales**
- Este caso representa un cliente bloqueado que realizó un pago parcial.  
- Validar que el sistema mantenga el estado bloqueado hasta que el saldo sea cubierto completamente.  

## **21. Cliente Custom Activo - Pago que cubre el monto de todos los servicios, pago 1 vez**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-25 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-26 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-07 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-25 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-26 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo**.  
- El saldo debe reflejar un valor de `0`.  
- Las fechas de pago y corte deben alinearse correctamente.  

### 📝 **Notas Adicionales**
- Representa un cliente **Custom Activo** con un pago único que cubre todos los servicios.  

---

## **22. Cliente Custom Activo - Pago que cubre el monto de todos los servicios, pago 2 veces**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `2025-01-25 23:59:59`    | Fecha y hora límite para el pago.          |
| **fecha_corte**           | `2025-01-26 23:59:59`    | Fecha de corte de facturación.             |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-07 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-25 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-26 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_ACTIVE`          | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe mantener el estado **Activo**.  
- El saldo debe reflejar `0`.  
- Las fechas deben ajustarse al siguiente ciclo correctamente.  

### 📝 **Notas Adicionales**
- Representa un cliente **Custom Activo** con pagos duplicados que cubren todos los servicios.  

---

## **23. Cliente Custom Bloqueado - Pago que cubre el monto de todos los servicios, pago 1 vez**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `520`                   | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `null`                  | Sin fecha de pago debido al bloqueo.       |
| **fecha_corte**           | `null`                  | Sin fecha de corte debido al bloqueo.      |
| **fecha_ultima_transaccion** | `2024-12-26 23:59:59` | Última transacción registrada.             |
| **fecha_realizacion_pago** | `2025-01-07 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-02-06 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-02-07 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---

### ✅ **Resultados Esperados**
- El cliente debe desbloquearse y pasar a estado **Activo**.  
- El saldo debe quedar en `0`.  
- Las fechas deben alinearse correctamente.  

### 📝 **Notas Adicionales**
- Representa un cliente bloqueado que realizó un pago completo para reactivar su estado.  

---

## **24. Cliente Custom Bloqueado - Pago que cubre el monto de todos los servicios, pago 2 veces**

| **Parámetro**            | **Valor**               | **Descripción**                            |
|---------------------------|-------------------------|--------------------------------------------|
| **fecha_creacion_cliente** | `2025-01-03 10:00:00`    | Fecha y hora de creación del cliente.      |
| **billing_expiration**     | `1`                     | Días hasta la expiración de la facturación. |
| **amount**                | `1040`                  | Monto total pagado por el cliente.         |
| **balance**               | `0`                     | Balance actual del cliente.                |
| **balance_esperado**      | `0`                     | Balance esperado después del pago.         |
| **fecha_pago**            | `null`                  | Sin fecha de pago debido al bloqueo.       |
| **fecha_corte**           | `null`                  | Sin fecha de corte debido al bloqueo.      |
| **fecha_realizacion_pago** | `2025-01-07 10:05:00`   | Fecha real en que se realizó el pago.      |
| **fecha_pago_esperada**   | `2025-03-06 23:59:59`    | Próxima fecha esperada para el pago.       |
| **fecha_corte_esperada**  | `2025-03-07 23:59:59`    | Próxima fecha de corte esperada.           |
| **estado**               | `STATE_BLOCKED`         | Estado actual del cliente.                 |
| **periodo_gracia**        | `false`                 | Indica si está en periodo de gracia.       |

---
