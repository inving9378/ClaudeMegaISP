<!-- Generada por `php artisan asistente:aprender vendedores` desde docs/modulos/vendedores.md, el menú y las pantallas de alta · huella cf1a12897750289f1341157f91643c5128be84c3 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Vendedores
**En pocas palabras:** Administrar la fuerza de ventas del ISP, comisiones y prospectos.

## Para qué sirve
- Llevar el control de los vendedores, sus comisiones y cortes de caja.
- Gestionar prospectos y estadísticas de ventas.
- Facilitar el cálculo y liquidación de comisiones.
  
## Pantallas
- [Dashboard](/vendedores) — Vista general y métricas de ventas.
- [Vendedores](/sellers/seller) — Registro y gestión de vendedores.
- [Prospectos](/vendedores/prospectos) — Seguimiento de clientes potenciales.
- [Estadísticas](/vendedores/dashboard) — Métricas y desempeño por vendedor.

## Cómo se hace
### Registrar un vendedor
1. Accede a la pantalla [Vendedores](/sellers/seller) y selecciona la opción para agregar un nuevo vendedor.

### Registrar un corte de caja
1. Accede a la pantalla adecuada para registrar el corte de caja del vendedor.

### Registrar un prospecto
1. Accede a la pantalla [Prospectos](/vendedores/prospectos) y selecciona la opción para agregar un nuevo prospecto.

## Qué datos pide
### Alta de vendedor ([Vendedores](/sellers/seller))
- **Obligatorios:** Nombre completo, Correo electrónico, Tipo de comisión, Porcentaje o monto de comisión, Estado.
- **Opcionales:** Teléfono de contacto, Zona o sector asignado.
  
### Alta de corte de caja (Corte de caja)
- **Obligatorios:** (ninguno)
  
### Alta de instalación (Corte de caja)
- **Obligatorios:** (ninguno)

### Alta de ingreso extra (Corte de caja)
- **Obligatorios:** (ninguno)

### Alta de gasto a proveedores (Corte de caja)
- **Obligatorios:** (ninguno)

### Alta de observación (Corte de caja)
- **Obligatorios:** (ninguno)

### Alta de regla de comisión ([Configuración](/configuracion/reglas-comisiones/crear))
- **Obligatorios:** (ninguno) 

## Quién lo usa
Lo utilizan principalmente el área de administración, supervisores de ventas y los propios vendedores para gestionar su desempeño.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
