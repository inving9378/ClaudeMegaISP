<!-- Generada por `php artisan asistente:aprender crm` desde docs/modulos/crm.md, el menú y las pantallas de alta · huella 1c4782bacb867959a9cd46855a6c3d857aa8959d · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# CRM
**En pocas palabras:** Es la herramienta para gestionar prospectos y clientes del ISP.

## Para qué sirve
- Captura y gestiona contactos interesados en los servicios del ISP.
- Registra el seguimiento comercial y actividades relacionadas con cada prospecto.
- Convierte prospectos en clientes activos sin necesidad de recapturar información.

## Pantallas
- [Clientes potenciales › Crear](/crm/crear) — Para registrar un nuevo prospecto.
- [Clientes potenciales › Listar](/crm/listar) — Para visualizar y gestionar los prospectos existentes.

## Cómo se hace
### Registrar un nuevo prospecto
1. Ve a la pantalla [Clientes potenciales › Crear](/crm/crear).

## Qué datos pide
### Alta de prospecto ([Clientes potenciales › Crear](/crm/crear))
- **Obligatorios:** Nombre, Apellido Paterno, Apellido Materno, Telefono, Colonia
- **Opcionales:** Score, Last Contacted, Dia de instalacion, Tecnico, IFT, Correo, Comentario, Socios, Telefono2, Ubicación, RFC/CURP, Calle, Numero Exterior, Fecha de alta, Permitir Usuario Duplicado, Numero Interior, Codigo Postal
- **Se llenan solos:** CRM Status (ya viene puesto: Nuevo), Correo es requerido (ya viene puesto: sí), Vendedor (ya viene puesto: quien lo captura)

## Quién lo usa
Principalmente lo utilizan los agentes de ventas y supervisores que gestionan las relaciones con los prospectos y clientes.

## Con JARVIS
JARVIS ya puede registrar prospectos y listarlos. En el menú esta sección se llama 'Clientes potenciales'.
