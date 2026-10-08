<!-- Generada por `php artisan asistente:aprender flotas` desde docs/modulos/flotas.md, el menú y las pantallas de alta · huella 76844fed1434d0facef60ffbe370a74b3513f29f · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Flotas
**En pocas palabras:** Gestión de la flota vehicular de la empresa y de clientes ISP.

## Para qué sirve
- Controla los vehículos de Meganet y de clientes ISP.
- Permite el registro, mantenimiento y documentación de los vehículos.
- Monitorea consumos de combustible y ubicación si cuentan con GPS.
- Administra asignaciones de operadores y alertas de vencimientos.

## Pantallas
- [Dashboard](/flotas) — Vista general del estado de la flota.
- [Vehículos](/flotas/vehiculos) — Alta y gestión de vehículos.
- [Mapa](/flotas/mapa) — Ubicación en tiempo real de los vehículos.
- [Geocercas](/flotas/geocercas) — Definición y gestión de áreas de operación.
- [Notificaciones](/flotas/notificaciones-log) — Registro de alertas y notificaciones.
- [Reglas de alertas](/flotas/reglas) — Configuración de reglas para notificaciones.
- [Documentos](/flotas/documentos) — Gestión de documentos de los vehículos.
- [Suscripciones](/flotas/suscripciones) — Manejo de suscripciones para clientes.

## Cómo se hace
### Alta de vehículo
1. Se hace en [Vehículos](/flotas/vehiculos).

### Asignación de operador
1. Se hace en [Vehículos](/flotas/vehiculos).

### Registro de mantenimiento
1. Se hace en [Vehículos](/flotas/vehiculos).

### Alta de documento
1. Se hace en [Documentos](/flotas/documentos).

### Registro de carga de combustible
1. Se hace en [Vehículos](/flotas/vehiculos).

### Registro de geocerca
1. Se hace en [Geocercas](/flotas/geocercas).

### Alta de notificación
1. Se hace en [Notificaciones](/flotas/notificaciones-log).

## Qué datos pide
### Alta de vehículo ([Vehículos](/flotas/vehiculos))
- **Obligatorios:** Placa, Marca, Modelo, Año, Tipo de vehículo.
- **Opcionales:** Usuario asignado.
  
### Alta de asignación de operador ([Vehículos](/flotas/vehiculos))
- **Obligatorios:** Usuario, Desde.

### Alta de mantenimiento ([Vehículos](/flotas/vehiculos))
- **Obligatorios:** Vehículo, Tipo, Fecha de servicio.

### Alta de documento ([Documentos](/flotas/documentos))
- **Obligatorios:** Tipo de documento.

### Alta de carga de combustible ([Vehículos](/flotas/vehiculos))
- **Obligatorios:** Vehículo, Fecha de repostaje, Litros, Costo.

### Alta de foto del vehículo ([Vehículos](/flotas/vehiculos))
- **Obligatorios:** Vehículo, Foto.

## Quién lo usa
Lo utilizan principalmente equipos de operaciones, logística y administración.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
