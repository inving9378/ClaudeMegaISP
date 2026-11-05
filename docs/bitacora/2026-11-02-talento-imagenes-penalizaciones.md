## 2026-11-02 — Talento: imágenes de referencia de las penalizaciones repuestas en dev

Pendiente del 30-oct: las imágenes de referencia de los tipos de penalización (subidas el
2026-09-09) no se veían en la web ni en los cursos de la app. La base de dev apuntaba a 10 archivos
`talento/penalty-types/*.png` (tipos 1 y 3 a 11), pero en dev no existía ni la carpeta
`storage/app/public/talento/`, ni copia en otros worktrees, en `/home/meganet`, en `/var/backups`
o en los respaldos por versión de `storage/backup_test`.

**De dónde salieron.** El servidor web de producción sí tiene los 10 archivos con los mismos
nombres (los genera Laravel al azar al subirlos, así que son la misma subida). Se bajaron por HTTP
(`GET http://192.168.105.108/storage/talento/penalty-types/<archivo>`, solo lectura: no se tocó
nada en producción), se revisó que fueran PNG y que la del tipo 1 fuera la de «Sin casco de
seguridad», y se copiaron a dev sin sobrescribir nada:

- Destino: `storage/app/public/talento/penalty-types/` (carpetas 2775 y archivos 664,
  grupo `www-data`, para que la web pueda volver a subir o reemplazar).
- Sin cambios en la base de datos ni en el código.

**Probado.**
- Los 10 abren por `http://192.168.105.11/storage/talento/penalty-types/…` (200).
- El enlace firmado que usa la app para los cursos
  (`/talento/api/curso-imagen-firmada/penalizacion/1`) devuelve la imagen (200, image/png); con la
  firma alterada, 403.
- No falta ningún otro archivo de Talento: penalizaciones, apelaciones y estándares de
  construcción no tienen archivos registrados en dev.

Quién: sesión de David (Claude Code).
