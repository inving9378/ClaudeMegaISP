<!-- Generada por `php artisan asistente:aprender planes` desde docs/modulos/planes.md, el menú y las pantallas de alta · huella b935b897530ccae3cd2cbf8aff5982a78fac1b7d · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Planes
**En pocas palabras:** Es el catálogo de servicios contratables que Meganet ofrece a sus clientes.

## Para qué sirve
- Permite gestionar y definir el portafolio de servicios del ISP.
- Ayuda a evitar la duplicación de configuraciones en los contratos de clientes.
- Facilita la asignación de planes y precios a los servicios contratados.
- Ofrece flexibilidad para crear planes personalizados y paquetes combinados.

## Pantallas
- [Planes › Internet](/internet) — Listar y gestionar los planes de Internet.
- [Planes › Voz](/voz) — Listar y gestionar los planes de VoIP.
- [Planes › Personalizado](/custom) — Listar y gestionar los planes personalizados.
- [Planes › Paquetes](/paquetes) — Listar y gestionar los paquetes combinados.
- [Planes › Servicios contratables](/planes/contratables) — Gestionar los servicios adicionales contratables.

## Cómo se hace
### Crear un nuevo plan en Internet
1. Ir a la pantalla [Planes › Internet](/internet) y seleccionar la opción de crear un nuevo plan.

### Crear un nuevo paquete
1. Ir a la pantalla [Planes › Paquetes](/paquetes) y seleccionar la opción de crear un nuevo paquete.

### Crear un nuevo plan de VoIP
1. Ir a la pantalla [Planes › Voz](/voz) y seleccionar la opción de crear un nuevo plan.

### Crear un nuevo plan personalizado
1. Ir a la pantalla [Planes › Personalizado](/custom) y seleccionar la opción de crear un nuevo plan.

## Qué datos pide
### Alta de plan en Internet ([Planes › Internet](/internet))
- **Obligatorios:** Título, Nombre del Servicio, Precio, IVA Incluido, IVA (0% (IVA 0%) / 16% (IVA 16%)), Velocidad de bajada (kbps), Velocidad de Subida, Lím. Velocidad Garantizada al, Prioridad (Baja / Normal / Alta), Agregación, Límite de Burst (Ninguno / Relativo / Fijo), Tipos de Facturación, Períodos de Pago (Mensual / Grupo de Días), Guaranteed speed
- **Opcionales:** Discount value, Discount value fixed, Discount period, Actualizar Descripcion, Actualizar Servicio, Socios, Umbral de Burst, Tiempo de Burst, Tarifas que se Pueden Elegir en Portal del Cliente, Categoría de la Transacción, Disponible cuando se registra en la red social, Activar Costo de Instalacion, Activar Promoción
- **Se llenan solos:** Costo de Activacion (ya viene puesto: 0), Costo de Instalación (ya viene puesto: 0)

### Alta de paquete ([Planes › Paquetes](/paquetes))
- **Obligatorios:** Título
- **Opcionales:** Descripción del Servicio, Precio, IVA Incluido, IVA, Socios, Tipos de Facturación, Categoría de la Transacción, Costo de Activación, Aplica activación, Emitir Factura, Duración del Contrato, Renovación Automática, Reactivar automáticamente, Cancelación previa, Tarifa de Cancelación Previa, Período de Descuento, Porciento de Descuento

### Alta de plan de VoIP ([Planes › Voz](/voz))
- **Obligatorios:** Título, Service name, Precio, Type, Tax include, Tax, Types of billing, Prepaid period, Prioridad, Partners
- **Opcionales:** (ninguno)

### Alta de plan personalizado ([Planes › Personalizado](/custom))
- **Obligatorios:** Título, Nombre del Servicio, Precio, Socios, IVA Incluido, IVA (0% (IVA 0%) / 16% (IVA 16%)), Tipos de Facturación, Períodos de Pago (Mensual / Grupo de Días), Prioridad (1 / 2 / 3 / 4 / 5 / 6)
- **Opcionales:** Discount value, Discount value fixed, Discount period, Actualizar Descripcion, Actualizar Servicio, Tarifas que se Pueden Elegir en Portal del Cliente, Categoría de la Transacción, Disponible el Auto Registro, Ancho de Banda, Bandwidth enable, Cost activation enable, Cost instalation enable, Mac, Ip, Numero de serie, Usuario, Contraseña, Activar Promoción
- **Se llenan solos:** Costo de Activacion (ya viene puesto: 0), Costo de Instalación (ya viene puesto: 0)

## Quién lo usa
Es utilizado principalmente por el equipo de administración y ventas para mantener actualizado el portafolio de servicios y asignar planes a los clientes.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
