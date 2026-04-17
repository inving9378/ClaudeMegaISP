# Documentación del Módulo de Inventario

En este módulo existen 4 secciones:

- **Artículos**
- **Tipo de Artículos**
- **Movimientos**
- **Almacenes**

## Requisitos Previos

Para usar este módulo, lo primero es que al menos exista un **almacén** y un **tipo de artículo**.

## Artículos

En la sección de **Artículos**, tenemos el botón de **Agregar Artículo**, donde se deben rellenar los siguientes campos:

- **Nombre** (requerido)
- **Descripción** (opcional)
- **Tipo de artículo** (requerido)
- **Cantidad inicial** (requerido, mínimo 1)
- **Almacén** (requerido)
- **Activar número de serie** (opcional, pero si está activo, pasa a ser requerido)
- **Estado del artículo** (opcional, pero si está activo, pasa a ser requerido)

Una vez que se agrega el artículo, se crea un **movimiento de entrada** al almacén correspondiente, logrando así un registro desde que entra un artículo hasta que llega a su destino final.

Además, tenemos el botón en la tabla de **Mover Artículo**, que genera un **modal** con la serie de movimientos que se pueden hacer:

- **Almacén a Almacén**
- **Almacén a Usuario** (técnico u otro tipo que no sea cliente)
- **Usuario a Almacén**
- **Usuario a Cliente**

### Validaciones para Realizar un Movimiento

Para realizar un movimiento se hacen las siguientes validaciones:

- Solo se puede hacer un **tipo de movimiento** a la vez.
- Los **almacenes deben ser distintos**.
- La cantidad debe ser **menor o igual al stock actual** del lugar desde donde se hace el movimiento.
