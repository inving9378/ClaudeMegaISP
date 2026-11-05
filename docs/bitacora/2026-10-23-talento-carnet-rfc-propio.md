## 2026-10-23 — Talento: el carnet de la ficha propia muestra el RFC

En su propia ficha, el carnet salía sin RFC aunque la persona lo tuviera (y aunque ella misma lo
puede cambiar en «Editar foto y datos»): `miFicha()` oculta los datos de expediente a quien no tiene
`talento.expediente.view`, y el RFC del usuario es uno de ellos. Ahora, solo cuando la ficha es la
propia, el RFC del usuario vuelve a ser visible. Lo demás del expediente sigue oculto, y un
supervisor que ve la ficha de su equipo sigue sin ver el RFC.

Probado con peticiones reales: en main la ficha propia de la cuenta de prueba de mostrador no traía
RFC; en la rama sí, y el supervisor de prueba sigue sin verlo en su subordinado.
